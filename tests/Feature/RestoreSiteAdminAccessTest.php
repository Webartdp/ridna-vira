<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class RestoreSiteAdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_changes_password_and_grants_panel_access(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test', 'password' => 'OldPassword12345']);
        $this->artisan('site:admin-reset')
            ->expectsQuestion('Електронна пошта адміністратора', ' OWNER@example.test ')
            ->expectsQuestion('Новий пароль (від 12 символів, латиниця та цифри)', 'NewTestPassword67890')
            ->expectsQuestion('Повторіть новий пароль', 'NewTestPassword67890')
            ->assertSuccessful();
        $this->assertDatabaseCount('users', 1);
        $this->assertTrue($user->fresh()->is_admin);
        $this->assertTrue(Hash::check('NewTestPassword67890', $user->fresh()->password));
        $this->assertFalse(Hash::check('OldPassword12345', $user->fresh()->password));

        $this->get('/admin/login')->assertOk();
        Livewire::test(Login::class)->fillForm(['email' => 'owner@example.test', 'password' => 'NewTestPassword67890'])
            ->call('authenticate')->assertHasNoFormErrors()->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_creates_an_admin_when_previous_creation_failed(): void
    {
        $this->artisan('site:admin-reset')
            ->expectsQuestion('Електронна пошта адміністратора', 'owner@example.test')
            ->expectsQuestion('Новий пароль (від 12 символів, латиниця та цифри)', 'NewTestPassword67890')
            ->expectsQuestion('Повторіть новий пароль', 'NewTestPassword67890')
            ->assertSuccessful();
        $user = User::firstOrFail();
        $this->assertSame('Admin', $user->name);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('NewTestPassword67890', $user->password));
    }

    public function test_mismatched_confirmation_does_not_change_credentials_or_permissions(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.test']);
        $original = $user->password;
        $this->artisan('site:admin-reset')
            ->expectsQuestion('Електронна пошта адміністратора', 'owner@example.test')
            ->expectsQuestion('Новий пароль (від 12 символів, латиниця та цифри)', 'NewTestPassword67890')
            ->expectsQuestion('Повторіть новий пароль', 'DifferentPassword12345')
            ->assertFailed();
        $this->assertSame($original, $user->fresh()->password);
        $this->assertFalse($user->fresh()->is_admin);
    }
}
