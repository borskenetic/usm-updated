<?php

declare(strict_types=1);

namespace App\Services\Migration;

use App\Models\User;
use Illuminate\Database\Connection;
use RuntimeException;

final class LegacyDatabaseImporter
{
    /** @var list<string> */
    private const EXTRA_TARGET_TABLES = [
        'attendance_pending_students',
        'attendance_pending_employees',
        'attendance_settings',
        'attendance_logs',
        'attendance_feedback',
        'library_attendance_logs',
        'model_has_roles',
        'model_has_permissions',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly LegacyImportPlan $plan,
        private readonly string $sourceSchema,
        private readonly string $targetSchema,
    ) {
        $this->plan->assertIdentifier($this->sourceSchema);
        $this->plan->assertIdentifier($this->targetSchema);

        if (strcasecmp($this->sourceSchema, $this->targetSchema) === 0) {
            throw new RuntimeException('Source and target database schemas must be distinct.');
        }

        if ($this->connection->getDriverName() !== 'mysql') {
            throw new RuntimeException('The production legacy importer requires a MySQL connection.');
        }
    }

    /**
     * @return array{copied: array<string, int>, skipped: list<string>, roles: int}
     */
    public function import(bool $fresh): array
    {
        $this->preflight($fresh);
        $this->connection->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            return $this->connection->transaction(function () use ($fresh): array {
                if ($fresh) {
                    $this->clearTarget();
                }

                $copied = [];
                $skipped = [];

                foreach ($this->plan->copies() as $copy) {
                    if (! $this->tableExists($this->sourceSchema, $copy['source'])) {
                        $skipped[] = $copy['source'].' → '.$copy['target'];

                        continue;
                    }

                    $count = $this->copyTable(
                        $copy['source'],
                        $copy['target'],
                        $copy['overrides'] ?? [],
                    );
                    $copied[$copy['source'].' → '.$copy['target']] = $count;
                }

                $copied['attendance_logs → library_attendance_logs'] = $this->copyLibraryAttendanceLogs();
                $roles = $this->synchronizeRoles();
                $this->assertNoForeignKeyOrphans();

                return compact('copied', 'skipped', 'roles');
            });
        } finally {
            $this->connection->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function preflight(bool $fresh): void
    {
        if (! $this->schemaExists($this->sourceSchema)) {
            throw new RuntimeException("Source schema [{$this->sourceSchema}] does not exist.");
        }

        if (! $this->schemaExists($this->targetSchema)) {
            throw new RuntimeException("Target schema [{$this->targetSchema}] does not exist.");
        }

        foreach ($this->plan->copies() as $copy) {
            if (! $this->tableExists($this->targetSchema, $copy['target'])) {
                throw new RuntimeException("Required target table [{$copy['target']}] does not exist; migrate the fresh target first.");
            }

            if ($copy['required'] && ! $this->tableExists($this->sourceSchema, $copy['source'])) {
                throw new RuntimeException("Required legacy source table [{$copy['source']}] does not exist.");
            }
        }

        foreach (['attendance_logs'] as $table) {
            if (! $this->tableExists($this->sourceSchema, $table)) {
                throw new RuntimeException("Required legacy source table [{$table}] does not exist.");
            }
        }

        foreach (self::EXTRA_TARGET_TABLES as $table) {
            if (! $this->tableExists($this->targetSchema, $table)) {
                throw new RuntimeException("Required target table [{$table}] does not exist; migrate the fresh target first.");
            }
        }

        $occupied = [];
        foreach ($this->managedTargetTables() as $table) {
            $count = $this->tableCount($this->targetSchema, $table);
            if ($count > 0) {
                $occupied[$table] = $count;
            }
        }

        if (! $fresh && $occupied !== []) {
            $details = implode(', ', array_map(
                static fn (string $table, int $count): string => "{$table}={$count}",
                array_keys($occupied),
                array_values($occupied),
            ));

            throw new RuntimeException(
                "Target is not fresh ({$details}). Rehearse against an empty migrated target or explicitly use --fresh --force."
            );
        }
    }

    private function clearTarget(): void
    {
        foreach (array_reverse($this->managedTargetTables()) as $table) {
            $this->connection->statement('DELETE FROM '.$this->quote($table));
        }
    }

    /**
     * @param  array<string, string>  $baseOverrides
     */
    private function copyTable(string $sourceTable, string $targetTable, array $baseOverrides): int
    {
        $sourceColumns = $this->columnMetadata($this->sourceSchema, $sourceTable);
        $targetColumns = array_keys($this->columnMetadata($this->targetSchema, $targetTable));
        $overrides = array_merge($baseOverrides, $this->overrides($sourceTable, $targetTable, array_keys($sourceColumns)));
        $selected = array_values(array_intersect($targetColumns, array_keys($sourceColumns)));

        foreach (array_keys($overrides) as $column) {
            if (in_array($column, $targetColumns, true) && ! in_array($column, $selected, true)) {
                $selected[] = $column;
            }
        }

        if ($selected === []) {
            throw new RuntimeException("No compatible columns for [{$sourceTable} → {$targetTable}].");
        }

        $select = [];
        foreach ($selected as $column) {
            if (isset($overrides[$column])) {
                $select[] = $overrides[$column].' AS '.$this->quote($column);

                continue;
            }

            $qualified = 'src.'.$this->quote($column);
            $type = strtolower($sourceColumns[$column]);
            $select[] = in_array($type, ['date', 'datetime', 'timestamp'], true)
                ? "NULLIF(NULLIF({$qualified}, '0000-00-00'), '0000-00-00 00:00:00') AS ".$this->quote($column)
                : $qualified;
        }

        $columns = implode(', ', array_map($this->quote(...), $selected));
        $sql = sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s AS src',
            $this->qualified($this->targetSchema, $targetTable),
            $columns,
            implode(', ', $select),
            $this->qualified($this->sourceSchema, $sourceTable),
        );

        $expected = $this->tableCount($this->sourceSchema, $sourceTable);
        $this->connection->statement($sql);
        $actual = $this->tableCount($this->targetSchema, $targetTable);

        if ($actual !== $expected) {
            throw new RuntimeException(
                "Count mismatch for [{$sourceTable} → {$targetTable}]: source={$expected}, target={$actual}."
            );
        }

        return $actual;
    }

    /**
     * @param  list<string>  $sourceColumns
     * @return array<string, string>
     */
    private function overrides(string $source, string $target, array $sourceColumns): array
    {
        $has = static fn (string $column): bool => in_array($column, $sourceColumns, true);

        return match ($target) {
            'library_roles' => [
                'description' => $has('description') ? 'src.`description`' : 'src.`name`',
            ],
            'library_catalog_frameworks' => [
                'code' => "CONCAT('fw_', src.`id`)",
                'is_default' => '0',
            ],
            'library_marc_fields' => [
                'name' => $has('name')
                    ? 'src.`name`'
                    : ($has('label') ? 'COALESCE(src.`label`, src.`tag`)' : 'src.`tag`'),
                'required' => '0',
                'select_options' => $has('options') ? 'src.`options`' : 'NULL',
                'description' => 'NULL',
            ],
            'library_catalog_framework_fields' => [
                'catalog_framework_id' => 'src.`framework_id`',
            ],
            'attendance_program_years' => [
                'year_number' => 'src.`year_level`',
                'label' => "CONCAT('Year ', src.`year_level`)",
            ],
            'attendance_program_courses' => [
                'program_id' => sprintf(
                    '(SELECT py.`program_id` FROM %s AS py WHERE py.`id` = src.`program_year_id`)',
                    $this->qualified($this->sourceSchema, 'program_years'),
                ),
            ],
            'library_holidays' => [
                'date' => $has('date') ? 'src.`date`' : 'src.`holiday_date`',
            ],
            'attendance_students' => [
                'student_id' => 'src.`id_number`',
                'birth_date' => 'src.`birthday`',
            ],
            'library_books' => [
                'accession_no' => "NULLIF(TRIM(src.`accession_no`), '')",
                'barcode' => "NULLIF(TRIM(src.`barcode`), '')",
                'rfid' => "NULLIF(TRIM(src.`rfid`), '')",
            ],
            'library_rooms' => ['is_active' => '1'],
            'library_reservation_students' => [
                'room_reservation_id' => 'src.`reservation_id`',
            ],
            'library_reservation_logs' => [
                'room_reservation_id' => 'src.`reservation_id`',
            ],
            'library_prospectuses' => [
                'course_name' => 'src.`course`',
                'course_code' => 'src.`subject`',
                'year_number' => 'NULL',
                'sort_order' => '0',
                'program_id' => 'NULL',
            ],
            default => [],
        };
    }

    private function copyLibraryAttendanceLogs(): int
    {
        $invalid = (int) ($this->connection->selectOne(sprintf(
            "SELECT COUNT(*) AS aggregate
             FROM %s AS a
             LEFT JOIN %s AS s
               ON a.`student_id` REGEXP '^[0-9]+$'
              AND s.`id` = CAST(a.`student_id` AS UNSIGNED)
             WHERE a.`student_id` IS NOT NULL
               AND (a.`student_id` NOT REGEXP '^[0-9]+$' OR s.`id` IS NULL)",
            $this->qualified($this->sourceSchema, 'attendance_logs'),
            $this->qualified($this->targetSchema, 'library_students'),
        ))->aggregate ?? 0);

        if ($invalid > 0) {
            throw new RuntimeException(
                "Legacy attendance_logs contains {$invalid} invalid/orphan patron reference(s); no visit logs were dropped."
            );
        }

        $sourceColumns = $this->columnMetadata($this->sourceSchema, 'attendance_logs');
        $targetColumns = array_keys($this->columnMetadata($this->targetSchema, 'library_attendance_logs'));
        $columns = array_values(array_intersect(
            ['id', 'student_id', 'status', 'section', 'scanned_at', 'created_at', 'updated_at'],
            $targetColumns,
        ));
        $select = [];

        foreach ($columns as $column) {
            if ($column === 'student_id') {
                $select[] = 'CAST(src.`student_id` AS UNSIGNED)';
            } elseif (isset($sourceColumns[$column])) {
                $select[] = 'src.'.$this->quote($column);
            } elseif ($column === 'section') {
                $select[] = 'NULL';
            } else {
                throw new RuntimeException("Legacy attendance_logs is missing required column [{$column}].");
            }
        }

        $expected = $this->tableCount($this->sourceSchema, 'attendance_logs');
        $this->connection->statement(sprintf(
            'INSERT INTO %s (%s) SELECT %s FROM %s AS src',
            $this->qualified($this->targetSchema, 'library_attendance_logs'),
            implode(', ', array_map($this->quote(...), $columns)),
            implode(', ', $select),
            $this->qualified($this->sourceSchema, 'attendance_logs'),
        ));
        $actual = $this->tableCount($this->targetSchema, 'library_attendance_logs');

        if ($actual !== $expected) {
            throw new RuntimeException(
                "Count mismatch for [attendance_logs → library_attendance_logs]: source={$expected}, target={$actual}."
            );
        }

        return $actual;
    }

    private function synchronizeRoles(): int
    {
        $users = $this->connection->table('users')->select(['id', 'role'])->orderBy('id')->get();
        $roleIds = [];
        $assigned = 0;

        foreach ($users as $user) {
            $canonical = $this->plan->canonicalRole($user->role);
            if ($canonical === null) {
                continue;
            }

            if (! isset($roleIds[$canonical])) {
                $this->connection->table('roles')->updateOrInsert(
                    ['name' => $canonical, 'guard_name' => 'web'],
                    ['created_at' => now(), 'updated_at' => now()],
                );
                $roleIds[$canonical] = (int) $this->connection->table('roles')
                    ->where('name', $canonical)
                    ->where('guard_name', 'web')
                    ->value('id');
            }

            $this->connection->table('users')->where('id', $user->id)->update(['role' => $canonical]);
            $this->connection->table('model_has_roles')->insert([
                'role_id' => $roleIds[$canonical],
                'model_type' => User::class,
                'model_id' => $user->id,
            ]);
            $assigned++;
        }

        return $assigned;
    }

    private function assertNoForeignKeyOrphans(): void
    {
        $tables = $this->managedTargetTables();
        $placeholders = implode(', ', array_fill(0, count($tables), '?'));
        $foreignKeys = $this->connection->select(
            "SELECT TABLE_NAME AS child_table, COLUMN_NAME AS child_column,
                    REFERENCED_TABLE_NAME AS parent_table, REFERENCED_COLUMN_NAME AS parent_column
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
               AND TABLE_NAME IN ({$placeholders})",
            [$this->targetSchema, ...$tables],
        );

        foreach ($foreignKeys as $foreignKey) {
            $orphanCount = (int) ($this->connection->selectOne(sprintf(
                'SELECT COUNT(*) AS aggregate FROM %s AS child_row
                 LEFT JOIN %s AS parent_row ON parent_row.%s = child_row.%s
                 WHERE child_row.%s IS NOT NULL AND parent_row.%s IS NULL',
                $this->qualified($this->targetSchema, (string) $foreignKey->child_table),
                $this->qualified($this->targetSchema, (string) $foreignKey->parent_table),
                $this->quote((string) $foreignKey->parent_column),
                $this->quote((string) $foreignKey->child_column),
                $this->quote((string) $foreignKey->child_column),
                $this->quote((string) $foreignKey->parent_column),
            ))->aggregate ?? 0);

            if ($orphanCount > 0) {
                throw new RuntimeException(sprintf(
                    'Foreign-key reconciliation failed: %s.%s has %d orphan reference(s) to %s.%s.',
                    $foreignKey->child_table,
                    $foreignKey->child_column,
                    $orphanCount,
                    $foreignKey->parent_table,
                    $foreignKey->parent_column,
                ));
            }
        }

        $orphanRoles = (int) ($this->connection->selectOne(
            'SELECT COUNT(*) AS aggregate FROM model_has_roles AS m
             LEFT JOIN users AS u ON u.id = m.model_id
             WHERE m.model_type = ? AND u.id IS NULL',
            [User::class],
        )->aggregate ?? 0);

        if ($orphanRoles > 0) {
            throw new RuntimeException("Role reconciliation failed: {$orphanRoles} user role assignment(s) are orphaned.");
        }

        $orphanUserPatrons = (int) ($this->connection->selectOne(
            'SELECT COUNT(*) AS aggregate FROM users AS u
             LEFT JOIN library_students AS s ON s.id = u.student_id
             WHERE u.student_id IS NOT NULL AND s.id IS NULL',
        )->aggregate ?? 0);

        if ($orphanUserPatrons > 0) {
            throw new RuntimeException("User reconciliation failed: {$orphanUserPatrons} Library patron link(s) are orphaned.");
        }

        $orphanTokens = (int) ($this->connection->selectOne(
            'SELECT COUNT(*) AS aggregate FROM personal_access_tokens AS token
             LEFT JOIN users AS u ON u.id = token.tokenable_id
             WHERE token.tokenable_type = ? AND u.id IS NULL',
            [User::class],
        )->aggregate ?? 0);

        if ($orphanTokens > 0) {
            throw new RuntimeException("Token reconciliation failed: {$orphanTokens} imported user token(s) are orphaned.");
        }
    }

    /** @return list<string> */
    private function managedTargetTables(): array
    {
        return array_values(array_unique([
            ...array_column($this->plan->copies(), 'target'),
            ...self::EXTRA_TARGET_TABLES,
        ]));
    }

    private function schemaExists(string $schema): bool
    {
        return $this->connection->selectOne(
            'SELECT 1 AS found FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1',
            [$schema],
        ) !== null;
    }

    private function tableExists(string $schema, string $table): bool
    {
        return $this->connection->selectOne(
            'SELECT 1 AS found FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
            [$schema, $table],
        ) !== null;
    }

    /** @return array<string, string> */
    private function columnMetadata(string $schema, string $table): array
    {
        $rows = $this->connection->select(
            'SELECT COLUMN_NAME AS name, DATA_TYPE AS type
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             ORDER BY ORDINAL_POSITION',
            [$schema, $table],
        );

        $columns = [];
        foreach ($rows as $row) {
            $columns[(string) $row->name] = (string) $row->type;
        }

        return $columns;
    }

    private function tableCount(string $schema, string $table): int
    {
        return (int) ($this->connection->selectOne(
            'SELECT COUNT(*) AS aggregate FROM '.$this->qualified($schema, $table),
        )->aggregate ?? 0);
    }

    private function qualified(string $schema, string $table): string
    {
        return $this->quote($schema).'.'.$this->quote($table);
    }

    private function quote(string $identifier): string
    {
        return '`'.$this->plan->assertIdentifier($identifier).'`';
    }
}
