<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Migration\LegacyDatabaseImporter;
use App\Services\Migration\LegacyImportPlan;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LegacyImportPlanTest extends TestCase
{
    public function test_it_rejects_unsafe_database_identifiers(): void
    {
        $plan = new LegacyImportPlan;

        $this->assertSame('legacy_2026', $plan->assertIdentifier('legacy_2026'));

        $this->expectException(InvalidArgumentException::class);
        $plan->assertIdentifier('legacy`; DROP DATABASE production; --');
    }

    public function test_it_maps_legacy_roles_to_authoritative_roles(): void
    {
        $plan = new LegacyImportPlan;

        $this->assertSame('library_admin', $plan->canonicalRole('admin'));
        $this->assertSame('library_staff', $plan->canonicalRole(' STAFF '));
        $this->assertSame('attendance_admin', $plan->canonicalRole('attendance_admin'));
        $this->assertSame('faculty', $plan->canonicalRole('employee'));
        $this->assertNull($plan->canonicalRole(null));
    }

    public function test_it_fails_on_an_unknown_legacy_role(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new LegacyImportPlan)->canonicalRole('root_owner');
    }

    public function test_importer_rejects_the_same_source_and_target_schema(): void
    {
        $connection = $this->createMock(Connection::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be distinct');

        new LegacyDatabaseImporter(
            $connection,
            new LegacyImportPlan,
            'production',
            'PRODUCTION',
        );
    }

    public function test_active_patrons_are_mapped_into_both_domains_and_visits_are_not_school_attendance(): void
    {
        $copies = (new LegacyImportPlan)->copies();

        $this->assertContains(
            ['source' => 'students', 'target' => 'library_students', 'required' => true],
            $copies,
        );
        $this->assertContains(
            ['source' => 'students', 'target' => 'attendance_students', 'required' => true],
            $copies,
        );
        $this->assertContains(
            ['source' => 'employees', 'target' => 'library_employees', 'required' => true],
            $copies,
        );
        $this->assertContains(
            ['source' => 'employees', 'target' => 'attendance_employees', 'required' => true],
            $copies,
        );
        $this->assertNotContains(
            ['source' => 'attendance_logs', 'target' => 'attendance_logs', 'required' => true],
            $copies,
        );
    }
}
