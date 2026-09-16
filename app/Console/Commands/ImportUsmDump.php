<?php

namespace App\Console\Commands;

use App\Services\Migration\LegacyDatabaseImporter;
use App\Services\Migration\LegacyImportPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportUsmDump extends Command
{
    protected $signature = 'usm:import-dump
        {--source= : Required legacy MySQL schema on the same server}
        {--connection=mysql : Target Laravel MySQL connection}
        {--fresh : Delete managed target data before importing}
        {--force : Required with --fresh to acknowledge destructive deletion}';

    protected $description = 'Strictly import a separate legacy USM schema into a fresh migrated target';

    public function handle(): int
    {
        $source = trim((string) $this->option('source'));
        $connectionName = trim((string) $this->option('connection'));
        $fresh = (bool) $this->option('fresh');

        if ($source === '') {
            $this->error('The --source option is required.');

            return self::FAILURE;
        }

        if ($fresh && ! $this->option('force')) {
            $this->error('Destructive fresh import requires both --fresh and --force.');

            return self::FAILURE;
        }

        try {
            $connection = DB::connection($connectionName);
            $target = (string) $connection->getDatabaseName();
            $this->info("Importing [{$source}] → [{$target}] on connection [{$connectionName}]");

            $result = (new LegacyDatabaseImporter(
                $connection,
                new LegacyImportPlan,
                $source,
                $target,
            ))->import($fresh);

            foreach ($result['copied'] as $mapping => $count) {
                $this->line("  {$mapping}: {$count}");
            }

            foreach ($result['skipped'] as $mapping) {
                $this->warn("  optional source absent: {$mapping}");
            }

            $this->info("Import reconciled successfully; {$result['roles']} user role assignment(s) synchronized.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Import failed and returned non-zero: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
