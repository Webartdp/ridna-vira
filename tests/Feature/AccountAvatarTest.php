<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_replace_and_remove_a_private_avatar(): void
    {
        $this->withoutVite();
        $disk = Storage::fake('local');
        $user = User::factory()->create(['role' => 'maker']);

        $this->get(route('account.avatar.show'))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('account.profile'))->assertOk()->assertSee('Фото профілю');
        $this->post(route('account.avatar.update'), ['avatar' => UploadedFile::fake()->image('first.png')])
            ->assertRedirect(route('account.profile'));
        $first = $user->refresh()->avatar_path;
        $disk->assertExists($first);
        $this->get(route('maker.index'))->assertOk()->assertSee('Аватарка профілю');
        $this->get(route('account.avatar.show'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('account.avatar.show', ['user_id' => $user->id]))->assertNotFound();

        $this->actingAs($user)->post(route('account.avatar.update'), ['avatar' => UploadedFile::fake()->image('second.jpg')])
            ->assertRedirect(route('account.profile'));
        $second = $user->refresh()->avatar_path;
        $this->assertNotSame($first, $second);
        $disk->assertMissing($first)->assertExists($second);

        $this->delete(route('account.avatar.destroy'))->assertRedirect(route('account.profile'));
        $disk->assertMissing($second);
        $this->get(route('account.avatar.show'))->assertNotFound();
    }

    public function test_only_raster_images_are_accepted(): void
    {
        Storage::fake('local');
        $svg = UploadedFile::fake()->createWithContent('profile.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->actingAs(User::factory()->create())->post(route('account.avatar.update'), ['avatar' => $svg])
            ->assertSessionHasErrors('avatar');
        $this->assertSame([], Storage::disk('local')->allFiles('avatars'));
    }
}
