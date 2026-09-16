<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_staff_can_open_their_profile(): void
    {
        $user = $this->staffUser();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSee($user->email);
    }

    public function test_staff_can_update_names_and_email_without_changing_role(): void
    {
        $user = $this->staffUser();

        $this->actingAs($user)
            ->patch('/profile', [
                'fname' => 'Updated',
                'lname' => 'Administrator',
                'email' => 'updated@example.com',
            ])
            ->assertRedirect(route('profile.edit', absolute: false))
            ->assertSessionHas('status', 'profile-updated');

        $user->refresh();

        $this->assertSame('Updated', $user->fname);
        $this->assertSame('Administrator', $user->lname);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertSame('library_staff', $user->getRawOriginal('role'));
        $this->assertTrue($user->hasRole('library_staff'));
    }

    public function test_staff_can_change_password_with_the_current_password(): void
    {
        $user = $this->staffUser();

        $this->actingAs($user)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'A stronger password 2026!',
                'password_confirmation' => 'A stronger password 2026!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'password-updated');

        $this->assertTrue(Hash::check('A stronger password 2026!', $user->fresh()->password));
    }

    public function test_staff_cannot_change_password_with_an_invalid_current_password(): void
    {
        $user = $this->staffUser();

        $this->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'incorrect',
                'password' => 'A stronger password 2026!',
                'password_confirmation' => 'A stronger password 2026!',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    private function staffUser(): User
    {
        $user = User::factory()->create([
            'role' => 'library_staff',
            'is_active' => true,
        ]);
        $user->assignRole('library_staff');

        return $user;
    }
}
