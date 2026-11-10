<?php

namespace Tests\Feature;

use App\Filament\Resources\GodResource\Pages\ManageGodEntries;
use App\Models\ContentPage;
use App\Models\User;
use App\Support\Cms;
use App\Support\ContentEditor;
use App\Support\ContentImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ContentImagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_native_god_image_appears_in_separate_field_and_can_be_saved_replaced_and_cleared(): void
    {
        Storage::fake('public');
        $this->withoutVite();
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
        $page = ContentPage::create(['path' => '/ridna-vira/bohy/perun', 'section' => 'gods', 'title' => 'Перун', 'editor_mode' => 'fragment', 'body' => '<p>Зміст матеріалу.</p>']);
        $page->forceFill(['is_override' => false])->saveQuietly();
        $component = Livewire::test(ManageGodEntries::class)->mountTableAction('edit', $page);
        $data = $component->get('mountedActions.0.data');
        $this->assertNotEmpty($data['image']);
        $component->callMountedTableAction()->assertHasNoTableActionErrors();
        $page->refresh();
        $this->assertNotNull($page->image_origin);
        $component = Livewire::test(ManageGodEntries::class)->mountTableAction('edit', $page)
            ->set('mountedActions.0.data.image', [UploadedFile::fake()->image('new.jpg', 800, 1000)])
            ->callMountedTableAction()->assertHasNoTableActionErrors();
        $page->refresh();
        $replacement = ContentImages::visual($page->image)['src'];
        $this->get('/ridna-vira/bohy')->assertOk()->assertSee($replacement, false);
        $this->get($page->path)->assertOk()->assertSee($replacement, false);
        Livewire::test(ManageGodEntries::class)->mountTableAction('edit', $page)->set('mountedActions.0.data.image', [])->callMountedTableAction()->assertHasNoTableActionErrors();
        $this->assertNull($page->fresh()->image);
        $this->assertNull(Cms::merge('gods', [['slug' => 'perun', 'image' => ['src' => '/old.jpg']]], '/ridna-vira/bohy')[0]['image']);
        $this->get($page->path)->assertOk()->assertDontSee($replacement, false);
    }

    public function test_primary_image_is_removed_from_prose_but_gallery_and_captions_remain(): void
    {
        Storage::fake('public');
        $source = '/assets/figma/home/hero.png';
        $prepared = ContentEditor::prepare(['path' => '/statti/image-test', 'section' => 'articles', 'editor_mode' => 'layout', 'body' => '<article><div class="article-prose"><p>Текст.</p><picture><img src="'.$source.'"></picture><p>Підпис світлини</p><img src="/assets/gallery.jpg"></div></article>']);
        $this->assertNotEmpty($prepared['image']);
        $this->assertStringNotContainsString($source, $prepared['body']);
        $this->assertStringContainsString('/assets/gallery.jpg', $prepared['body']);
        $origin = ['body_source' => $source];
        $html = ContentImages::strip('<p>Текст</p><picture><img src="'.$source.'"></picture><p>Підпис світлини</p><img src="/assets/gallery.jpg">', $origin);
        $this->assertStringNotContainsString($source, $html);
        $this->assertStringContainsString('Підпис світлини', $html);
        $this->assertStringContainsString('/assets/gallery.jpg', $html);
        $this->assertNull(ContentImages::import('https://evil.example/assets/photo.jpg'));
        $this->assertNull(ContentImages::import('/assets/../.env'));
    }

    public function test_managed_cover_is_shared_by_news_blog_creativity_shrine_cards_and_details(): void
    {
        Storage::fake('public');
        $this->withoutVite();
        $upload = UploadedFile::fake()->image('cover.jpg', 900, 600);
        Storage::disk('public')->put('content/cover.jpg', file_get_contents($upload->getRealPath()));
        foreach (['news' => '/novyny', 'articles' => '/statti', 'creativity' => '/tvorchist', 'shrines' => '/ridna-vira/sviatyni'] as $section => $prefix) {
            $page = ContentPage::create(['path' => $prefix.'/image-test', 'section' => $section, 'title' => 'ААА Зображення', 'editor_mode' => 'reading', 'body' => '<p>Текст матеріалу.</p>', 'image' => 'content/cover.jpg']);
            $src = ContentImages::visual($page->image)['src'];
            $this->get($prefix)->assertOk()->assertSee($src, false);
            $response = $this->get($page->path)->assertOk()->assertSee($src, false)->assertSee('property="og:image"', false);
            $images = iterator_to_array(Cms::document($response->getContent())->getElementsByTagName('img'));
            $this->assertCount(1, array_filter($images, fn ($image) => ContentImages::same($image->getAttribute('src'), $src)), $section);
        }
    }

    public function test_replacement_is_rendered_once_with_dimensions_and_responsive_variants(): void
    {
        Storage::fake('public');
        $upload = UploadedFile::fake()->image('main.jpg', 1000, 800);
        Storage::disk('public')->put('content/main.jpg', file_get_contents($upload->getRealPath()));
        $page = ContentPage::create(['path' => '/statti/cover', 'section' => 'articles', 'title' => 'Матеріал', 'body' => '<p>Текст.</p>', 'image' => 'content/main.jpg', 'image_origin' => ['body_source' => '/old.jpg', 'slot' => 'content']]);
        $html = ContentImages::render('<article><div class="article-prose"><img src="/old.jpg"><p>Текст.</p><img src="/gallery.jpg"></div></article>', $page);
        $this->assertStringNotContainsString('/old.jpg', $html);
        $this->assertStringContainsString('/gallery.jpg', $html);
        $this->assertSame(1, substr_count($html, 'src="/storage/content/main-800.webp"'));
        $this->assertStringContainsString('srcset=', $html);
        $this->assertStringContainsString('width="800"', $html);
    }
}
