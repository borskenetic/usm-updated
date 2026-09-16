<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class BootstrapSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_one_time_super_admin_with_a_generated_password(): void
    {
        $this->artisan('system:bootstrap-super-admin', [
            'email' => 'OWNER@Example.com',
            '--generate' => true,
        ])->assertSuccessful();

        $user = User::query()->where('email', 'owner@example.com')->firstOrFail();

        $this->assertSame('super_admin', $user->getRawOriginal('role'));
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_it_refuses_to_run_after_a_super_admin_exists(): void
    {
        $this->artisan('system:bootstrap-super-admin', [
            'email' => 'owner@example.com',
            '--generate' => true,
        ])->assertSuccessful();

        $this->artisan('system:bootstrap-super-admin', [
            'email' => 'other@example.com',
            '--generate' => true,
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }

    public function test_existing_user_requires_explicit_promotion_and_receives_prompted_password(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'role' => 'staff',
        ]);

        $this->artisan('system:bootstrap-super-admin', [
            'email' => 'owner@example.com',
            '--generate' => true,
        ])->assertFailed();

        $this->artisan('system:bootstrap-super-admin', [
            'email' => 'owner@example.com',
            '--promote' => true,
        ])
            ->expectsQuestion('Password (minimum 12 characters)', 'Correct Horse Battery Staple!')
            ->expectsQuestion('Confirm password', 'Correct Horse Battery Staple!')
            ->assertSuccessful();

        $user->refresh();
        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertTrue(Hash::check('Correct Horse Battery Staple!', $user->password));
    }

    public function test_it_rejects_an_invalid_email(): void
    {
        $this->artisan('system:bootstrap-super-admin', [
            'email' => 'not-an-email',
            '--generate' => true,
        ])->assertFailed();
    }

    public function test_predictable_account_seeders_are_disabled_in_production(): void
    {
        $this->app->detectEnvironment(static fn (): string => 'production');

        foreach ([DatabaseSeeder::class, SuperAdminSeeder::class] as $seeder) {
            try {
                (new $seeder)->run();
                $this->fail("Expected {$seeder} to be disabled in production.");
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('production', $exception->getMessage());
            }
        }

        $this->assertDatabaseMissing('users', ['email' => 'super_admin@pantas.test']);
    }
}
