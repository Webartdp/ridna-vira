<?php

namespace Tests\Feature;

use App\Filament\Pages\AboutPhotos;
use App\Models\ContentPage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\AboutGallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AboutGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_upload_reorder_and_clear_the_gallery(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'editor']));

        [$first, $second, $third] = AboutGallery::paths();
        $this->get('/pro-tsentr')->assertOk()->assertSeeInOrder([$first, $second, $third]);
        $this->get(AboutPhotos::getUrl())->assertOk()->assertSee('Галерея сторінки');

        Livewire::test(AboutPhotos::class)
            ->set('data.photos', [$third, $first])
            ->upload('data.photos', [UploadedFile::fake()->image('gathering.jpg', 900, 600)])
            ->call('save')->assertHasNoErrors();

        [$firstShown, $secondShown, $upload] = AboutGallery::paths();
        $this->assertSame([$third, $first], [$firstShown, $secondShown]);
        Storage::disk('public')->assertExists($upload);
        $this->get('/pro-tsentr')->assertOk()
            ->assertSeeInOrder([$third, $first, $upload])
            ->assertDontSee($second);

        Livewire::test(AboutPhotos::class)->set('data.photos', [])->call('save')->assertHasNoErrors();
        $this->assertSame([], AboutGallery::paths());
        $this->get('/pro-tsentr')->assertOk()->assertDontSee('data-about-gallery');
    }

    public function test_saved_about_layout_uses_current_gallery_and_preserves_copy(): void
    {
        $this->withoutVite();
        $photo = AboutGallery::paths()[0];
        SiteSetting::write('about_gallery', json_encode([$photo]));
        ContentPage::create([
            'path' => '/pro-tsentr', 'section' => 'pages', 'title' => 'Про центр', 'editor_mode' => 'layout',
            'body' => '<section class="about-page"><p>Оновлений текст центру</p><section class="about-gallery"><img src="/old.jpg"></section></section>',
        ]);

        $this->get('/pro-tsentr')->assertOk()
            ->assertSee('Оновлений текст центру')->assertSee($photo)->assertDontSee('/old.jpg')
            ->assertDontSee('data-about-gallery-prev');
    }
}
