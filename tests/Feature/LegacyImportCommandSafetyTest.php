<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class LegacyImportCommandSafetyTest extends TestCase
{
    public function test_source_schema_is_required(): void
    {
        $this->artisan('usm:import-dump')->assertFailed();
    }

    public function test_destructive_fresh_import_requires_force_flag(): void
    {
        $this->artisan('usm:import-dump', [
            '--source' => 'legacy_usm',
            '--fresh' => true,
        ])->assertFailed();
    }

    public function test_non_mysql_connection_fails_non_zero(): void
    {
        $this->artisan('usm:import-dump', [
            '--source' => 'legacy_usm',
            '--connection' => 'sqlite',
        ])->assertFailed();
    }
}
