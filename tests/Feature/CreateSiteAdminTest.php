<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSiteAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_terminal_encoding_is_rejected_before_requesting_credentials(): void
    {
        $this->artisan('site:admin')
            ->expectsQuestion('Ім’я адміністратора', "\xD1admin")
            ->expectsOutputToContain('Ім’я містить пошкоджені символи')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_replacement_characters_are_reported_instead_of_saved(): void
    {
        $this->artisan('site:admin')
            ->expectsQuestion('Ім’я адміністратора', "Ім’я\u{FFFD}")
            ->expectsOutputToContain('Ім’я містить пошкоджені символи')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_valid_ukrainian_name_can_create_an_admin(): void
    {
        $this->artisan('site:admin')
            ->expectsQuestion('Ім’я адміністратора', 'Адміністратор')
            ->expectsQuestion('Електронна пошта', 'admin@example.test')
            ->expectsQuestion('Пароль (мінімум 12 символів)', 'TestOnlyPassword12345')
            ->expectsQuestion('Повторіть пароль', 'TestOnlyPassword12345')
            ->assertSuccessful();

        $user = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertSame('Адміністратор', $user->name);
        $this->assertTrue($user->is_admin);
    }
}
