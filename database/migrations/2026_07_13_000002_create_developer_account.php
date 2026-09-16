<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $exists = DB::table('roles')
                ->where('name', 'developer')
                ->where('guard_name', 'web')
                ->exists();

            if (! $exists) {
                DB::table('roles')->insert([
                    'name' => 'developer',
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // The role may be assigned to an explicitly created account. Never
        // remove authorization data or users during a migration rollback.
    }
};
