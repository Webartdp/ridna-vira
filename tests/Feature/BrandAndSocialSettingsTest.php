<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BrandAndSocialSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_social_links_and_hide_cleared_links(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin', 'is_active' => true])->save();
        $this->actingAs($admin);
        Livewire::test(SiteSettings::class)->set('data.social_links', [
            'facebook' => 'https://www.facebook.com/example', 'telegram' => 'https://t.me/example',
            'instagram' => '', 'youtube' => '', 'tiktok' => '',
        ])->call('save')->assertHasNoErrors();
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('href="https://t.me/example" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('title="Facebook"', $html);
        $this->assertStringNotContainsString('title="Instagram"', $html);
        $this->assertStringContainsString('sameAs', $html);
        Livewire::test(SiteSettings::class)->set('data.social_links.telegram', '')->call('save')->assertHasNoErrors();
        $this->get('/')->assertDontSee('href="https://t.me/example"', false);
    }

    public function test_settings_reject_unsafe_social_urls_and_rendering_ignores_invalid_saved_urls(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin', 'is_active' => true])->save();
        $this->actingAs($admin);
        Livewire::test(SiteSettings::class)->set('data.social_links.telegram', 'javascript:alert(1)')->call('save')->assertHasErrors(['data.social_links.telegram']);
        SiteSetting::write('social_links', json_encode(['telegram' => 'javascript:alert(1)', 'facebook' => 'http://example.com']));
        $this->get('/')->assertOk()->assertDontSee('footer-socials')->assertDontSee('javascript:alert(1)', false);
    }

    public function test_shared_placeholder_uses_brand_svg_and_keeps_real_images(): void
    {
        $empty = view('partials.publication-image', ['visual' => null])->render();
        $this->assertStringContainsString('/assets/brand/image-placeholder.svg', $empty);
        $this->assertStringContainsString('loading="lazy"', $empty);
        $real = view('partials.publication-image', ['visual' => ['src' => '/assets/example.webp', 'width' => 800, 'height' => 600]])->render();
        $this->assertStringContainsString('/assets/example.webp', $real);
        $this->assertStringNotContainsString('image-placeholder.svg', $real);
        $home = $this->withoutVite()->get('/')->assertOk();
        $home->assertSee('/assets/brand/image-placeholder.svg')->assertSee('sacred-grove-1600.webp');
    }
}
