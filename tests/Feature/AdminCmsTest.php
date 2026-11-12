<?php

namespace Tests\Feature;

use App\Filament\Pages\CalendarSettings;
use App\Filament\Pages\Communities;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\ContentPageResource\Pages\EditContentPage;
use App\Filament\Resources\ContentPageResource\Pages\ManageContentPages;
use App\Filament\Resources\NewsResource\Pages\ManageNewsEntries;
use App\Filament\Resources\ProductResource\Pages\ManageProducts;
use App\Models\ContentPage;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Cms;
use App\Support\CmsHtml;
use App\Support\PageBlocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCmsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_only_admin_can_access_panel_and_resources(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
        $this->actingAs($this->admin());
        foreach (['/admin', '/admin/content-pages', '/admin/products', '/admin/product-categories', '/admin/orders', '/admin/media-assets', '/admin/site-settings', '/admin/communities'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_new_material_appears_on_site_list_and_home(): void
    {
        ContentPage::create(['path' => '/novyny/nova-podiia', 'section' => 'news', 'title' => 'Нова подія громади', 'body' => '<p>Наш текст.</p>', 'excerpt' => 'Коротко про подію', 'published_at' => now()]);
        $this->get('/novyny/nova-podiia')->assertOk()->assertSee('Наш текст.');
        $this->get('/novyny')->assertOk()->assertSee('Нова подія громади');
        $this->get('/')->assertOk()->assertSee('Нова подія громади');
    }

    public function test_draft_future_and_deleted_materials_are_hidden(): void
    {
        $page = ContentPage::create(['path' => '/novyny/not-visible', 'section' => 'news', 'title' => 'Невидима новина', 'body' => '<p>Текст</p>', 'is_published' => false]);
        $this->get($page->path)->assertNotFound();
        $this->get('/novyny')->assertDontSee('Невидима новина');
        $page->update(['is_published' => true, 'published_at' => now()->addDay()]);
        $this->get($page->path)->assertNotFound();
        $page->update(['published_at' => now()->subDay()]);
        $this->get($page->path)->assertOk();
        $page->delete();
        $this->get($page->path)->assertNotFound();
    }

    public function test_cms_preserves_native_forms_and_live_home_feed(): void
    {
        $native = $this->get('/')->assertOk()->getContent();
        preg_match('~<main>(.*?)</main>~s', $native, $match);
        [$body] = Cms::islands($match[1]);
        $this->assertStringContainsString('[[live:materials]]', $body);
        ContentPage::create(['path' => '/', 'section' => 'pages', 'title' => 'Рідна Віра', 'body' => $body.'<p>Редакторський текст</p>', 'editor_mode' => 'layout']);
        ContentPage::create(['path' => '/novyny/live-news', 'section' => 'news', 'title' => 'Нова жива стрічка', 'body' => '<p>Текст</p>', 'published_at' => now()]);
        $this->get('/')->assertOk()->assertSee('Редакторський текст')->assertSee('Нова жива стрічка')->assertDontSee('[[live:', false);
        [$template, $islands] = Cms::islands('<p>Вступ</p><form method="post"><input name="_token" value="fresh-token"></form>');
        $this->assertStringNotContainsString('fresh-token', $template);
        $this->assertStringContainsString('fresh-token', strtr($template, $islands));
    }

    public function test_text_overrides_preserve_native_url_and_record_revisions(): void
    {
        ContentPage::create(['path' => '/ridna-vira/bohy/rod', 'section' => 'gods', 'title' => 'Род', 'body' => '<p>Оновлений опис Рода.</p>', 'editor_mode' => 'fragment']);
        $this->get('/ridna-vira/bohy/rod')->assertOk()->assertSee('Оновлений опис Рода.')->assertSee('god-reading-figure');
        $page = ContentPage::where('path', '/ridna-vira/bohy/rod')->firstOrFail();
        $page->update(['body' => '<p>Наступна версія</p>']);
        $this->assertSame(1, $page->revisions()->count());
        $this->assertStringContainsString('Оновлений опис Рода', $page->revisions()->first()->snapshot);
    }

    public function test_secret_is_encrypted_and_never_hydrated_back_to_settings_form(): void
    {
        SiteSetting::write('wayforpay_secret', 'private-test-secret');
        $this->assertNotSame('private-test-secret', SiteSetting::where('key', 'wayforpay_secret')->value('value'));
        $this->assertSame('private-test-secret', SiteSetting::read('wayforpay_secret'));
        $this->actingAs($this->admin());
        Livewire::test(SiteSettings::class)->assertSet('data.wayforpay_secret', '')->assertDontSee('private-test-secret')->call('save')->assertHasNoErrors();
        $this->assertSame('private-test-secret', SiteSetting::read('wayforpay_secret'));
    }

    public function test_editor_sanitizes_active_html_but_preserves_prayer_formatting(): void
    {
        $page = ContentPage::create(['path' => '/bezpeka', 'title' => 'Перевірка', 'body' => '<script>alert(1)</script><p onclick="x()">Звичайний текст</p><a href="javascript:alert(1)">Посилання</a><div class="ritual-passage ritual-passage--prayer"><p>Молитва</p></div>']);
        $this->assertStringNotContainsString('<script', $page->body);
        $this->assertStringNotContainsString('onclick', $page->body);
        $this->assertStringNotContainsString('javascript:', $page->body);
        $this->get('/bezpeka')->assertOk()->assertSee('ritual-passage--prayer', false)->assertSee('Звичайний текст');
    }

    public function test_encyclopedia_links_are_hidden_in_saved_navigation_and_content(): void
    {
        SiteSetting::write('navigation', json_encode([
            ['label' => 'Про центр', 'url' => '/pro-tsentr', 'children' => [
                ['label' => 'Енциклопедія', 'url' => 'https://wiki.svit.in.ua/'],
                ['label' => 'Громади', 'url' => '/hromady'],
            ]],
        ]));
        ContentPage::create([
            'title' => 'Перевірка посилань', 'path' => '/test-encyclopedia-links', 'section' => 'pages',
            'body' => '<p>Опис <a href="https://wiki.svit.in.ua/Молитва">молитви</a>.</p>',
        ]);

        $this->get('/test-encyclopedia-links')->assertOk()
            ->assertSee('Опис молитви.')->assertSee('Громади')
            ->assertDontSee('wiki.svit.in.ua', false)->assertDontSee('Енциклопедія');
    }

    public function test_editor_can_add_a_circle_and_assign_it_to_a_map_location(): void
    {
        $this->actingAs($this->admin());
        $component = Livewire::test(Communities::class);
        $circles = $component->get('data.circles');
        $circles[] = ['id' => 'test-circle', 'label' => 'Нове тестове коло'];
        $locations = $component->get('data.locations');
        $key = array_key_first($locations);
        $component->set('data.circles', $circles)
            ->set('data.locations.'.$key.'.circle', 'test-circle')
            ->call('save')->assertHasNoErrors();

        $saved = json_decode(SiteSetting::read('communities'), true);
        $this->assertSame('test-circle', $saved[0]['entries'][0]['circle']);

        $this->get('/zviazok')->assertOk()
            ->assertSee('value="test-circle"', false)
            ->assertSee('data-map-circle-group aria-label="Нове тестове коло"', false);
    }

    public function test_community_settings_are_used_by_live_map(): void
    {
        $locations = config('communities');
        $locations[0]['city'] = 'Місто після редагування';
        SiteSetting::write('communities', json_encode($locations, JSON_UNESCAPED_UNICODE));
        $this->get('/zviazok')->assertOk()->assertSee('Місто після редагування');
    }

    public function test_admin_can_create_and_edit_products_with_prices_in_hryvnias(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(ManageProducts::class)
            ->callAction('create', data: ['name' => 'Нова книга', 'slug' => 'new-book', 'price_minor' => '149.95', 'stock' => 10, 'is_active' => true])
            ->assertHasNoActionErrors();
        $product = Product::firstOrFail();
        $this->assertSame(14995, $product->price_minor);
        Livewire::test(ManageProducts::class)
            ->callTableAction('edit', $product, data: ['price_minor' => '150.50', 'stock' => 9, 'stock_before' => 10])->assertHasNoTableActionErrors();
        $this->assertSame(15050, $product->fresh()->price_minor);
        $this->assertSame(9, $product->fresh()->stock);
    }

    public function test_admin_can_create_a_page_using_visual_editor(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(ManageContentPages::class)
            ->callAction('create', data: ['title' => 'Нова сторінка', 'path' => '/nova-storinka', 'section' => 'pages', 'editor_mode' => 'reading', 'body' => '<p>Текст із редактора</p>', 'is_published' => true])->assertHasNoActionErrors();
        $this->get('/nova-storinka')->assertOk()->assertSee('Текст із редактора');
    }

    public function test_reading_blocks_render_with_labels_and_safe_text(): void
    {
        $config = htmlspecialchars(json_encode(['kind' => 'praise', 'text' => "Слава!\nДругий рядок <script>"], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
        ContentPage::create(['title' => 'Славлення', 'path' => '/slavlennia', 'body' => '<div data-type="customBlock" data-id="reading-passage" data-config="'.$config.'"></div>']);
        $this->get('/slavlennia')->assertOk()->assertSee('ritual-passage--praise', false)->assertSee('Другий рядок')->assertDontSee('<script>', false);
    }

    public function test_native_layout_texts_can_be_edited_without_html(): void
    {
        $this->actingAs($this->admin());
        $page = ContentPage::create(['path' => '/pro-tsentr', 'title' => 'Про центр', 'body' => '<section class="preserved-layout"><h1>Про центр</h1><p>Початковий текст</p><a href="/zviazok">Зв’язок</a></section>', 'editor_mode' => 'layout']);
        $fields = PageBlocks::fields($page->body);
        foreach ($fields as &$field) {
            if (str_contains($field['value'], 'Початковий текст')) {
                $field['value'] = str_replace('Початковий текст', 'Текст після редагування', $field['value']);
            }
        }
        unset($field);
        Livewire::test(EditContentPage::class, ['record' => $page->getKey()])->set('data.page_blocks', $fields)->call('save')->assertHasNoErrors();
        $this->get('/pro-tsentr')->assertOk()->assertSee('Текст після редагування')->assertSee('preserved-layout');
    }

    public function test_calendar_saves_selected_month_and_preserves_other_months(): void
    {
        $this->actingAs($this->admin());
        $component = Livewire::test(CalendarSettings::class);
        $entries = $component->get('data.entries');
        $key = array_key_first($entries);
        $component->set('data.entries.'.$key.'.name', 'Свято після редагування')->call('save')->assertHasNoErrors();
        $calendar = json_decode(SiteSetting::read('calendar'), true);
        $this->assertSame('Свято після редагування', $calendar['months']['Січень'][0]['name']);
        $this->assertSame(config('faith_calendar.months.Лютий'), $calendar['months']['Лютий']);
        $this->get('/ridna-vira/kalendar')->assertOk()->assertSee('Свято після редагування');
        $component->set('month', 'Лютий')->assertHasNoErrors();
        $february = array_values($component->get('data.entries'));
        $this->assertSame(config('faith_calendar.months.Лютий.0.name'), $february[0]['name']);
    }

    public function test_import_is_idempotent_and_preserves_editor_changes(): void
    {
        $this->withoutVite();
        $this->artisan('site:import')->assertSuccessful();
        $count = ContentPage::count();
        $this->assertGreaterThan(200, $count);
        $page = ContentPage::where('path', '/pro-tsentr')->firstOrFail();
        $page->update(['body' => '<p>Збережений редактором текст</p>', 'editor_mode' => 'reading']);
        $this->artisan('site:import')->assertSuccessful();
        $this->assertSame($count, ContentPage::count());
        $this->assertSame('<p>Збережений редактором текст</p>', $page->fresh()->body);
    }

    public function test_wrong_section_address_is_rejected_by_admin_form(): void
    {
        $this->actingAs($this->admin());
        Livewire::test(ManageNewsEntries::class)
            ->callAction('create', data: ['title' => 'Новина', 'section' => 'news', 'path' => '/wrong-path', 'editor_mode' => 'reading', 'body' => '<p>Текст</p>', 'is_published' => true])
            ->assertHasActionErrors(['path']);
        $this->assertDatabaseMissing('content_pages', ['path' => '/wrong-path']);
    }

    public function test_existing_prayer_blocks_keep_formatting_when_loaded_into_visual_editor(): void
    {
        $html = '<p>Опис</p><div class="ritual-passage ritual-passage--prayer"><p class="ritual-passage__label">Молитва</p><p><em>Перший рядок</em><br>Другий рядок</p></div>';
        $editable = CmsHtml::forEditor($html);
        $this->assertStringContainsString('data-type="customBlock"', $editable);
        $rendered = CmsHtml::render($editable);
        $this->assertStringContainsString('ritual-passage--prayer', $rendered);
        $this->assertStringContainsString('<em>Перший рядок</em><br>Другий рядок', $rendered);
        $this->assertStringContainsString('<p>Опис</p>', $rendered);
    }

    public function test_prayers_and_rituals_can_be_added_through_cms(): void
    {
        foreach (['prayers' => 'molytvy', 'rituals' => 'obriady'] as $section => $path) {
            ContentPage::create(['section' => $section, 'path' => '/ridna-vira/'.$path.'/new-entry', 'title' => 'Новий матеріал '.$section, 'excerpt' => 'Опис', 'body' => '<p>Текст</p>']);
            $this->get('/ridna-vira/'.$path)->assertOk()->assertSee('Новий матеріал '.$section);
            $this->get('/ridna-vira/'.$path.'/new-entry')->assertOk()->assertSee('Текст');
        }
    }
}
