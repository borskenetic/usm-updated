<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

final class BootstrapSuperAdmin extends Command
{
    protected $signature = 'system:bootstrap-super-admin
        {email : Email address for the initial super administrator}
        {--generate : Generate and display a strong one-time password}
        {--promote : Explicitly allow promotion and password reset of an existing user}';

    protected $description = 'Securely create or promote the one-time initial super administrator';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('A valid email address is required.');

            return self::FAILURE;
        }

        if ($this->superAdminExists()) {
            $this->error('A super_admin already exists. This one-time bootstrap command will not make another.');

            return self::FAILURE;
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing !== null && ! $this->option('promote')) {
            $this->error('That user already exists. Re-run with --promote to acknowledge promotion and password reset.');

            return self::FAILURE;
        }

        $generated = (bool) $this->option('generate');
        $password = $generated ? Str::password(32) : $this->promptForPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $user = DB::transaction(function () use ($email, $password, $existing): User {
                $role = Role::findOrCreate('super_admin', 'web');
                $user = $existing ?? new User;

                if (! $user->exists) {
                    $user->email = $email;
                    $user->fname = 'System';
                    $user->lname = 'Administrator';
                    $user->email_verified_at = now();
                }

                $user->password = Hash::make($password);
                $user->role = 'super_admin';
                $user->is_active = true;
                $user->save();
                $user->syncRoles([$role->name]);

                return $user;
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Unable to bootstrap super_admin: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Initial super_admin created for [{$user->email}].");

        if ($generated) {
            $this->warn('One-time generated password (store it securely; it will not be shown again):');
            $this->line($password);
        }

        return self::SUCCESS;
    }

    private function promptForPassword(): ?string
    {
        $password = (string) $this->secret('Password (minimum 12 characters)');
        $confirmation = (string) $this->secret('Confirm password');

        if (strlen($password) < 12) {
            $this->error('Password must contain at least 12 characters.');

            return null;
        }

        if (! hash_equals($password, $confirmation)) {
            $this->error('Password confirmation does not match.');

            return null;
        }

        return $password;
    }

    private function superAdminExists(): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'super_admin')
            ->where('roles.guard_name', 'web')
            ->where('model_has_roles.model_type', User::class)
            ->exists();
    }
}
