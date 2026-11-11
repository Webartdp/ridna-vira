<?php

namespace Tests\Feature;

use App\Filament\Resources\HolidayResource\Pages\EditHolidayEntry;
use App\Filament\Resources\PrayerResource\Pages\EditPrayerEntry;
use App\Models\ContentPage;
use App\Models\User;
use App\Support\Cms;
use App\Support\CmsHtml;
use App\Support\ContentEditor;
use App\Support\PageBlocks;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LegacyContentEditorTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_materials_load_only_the_reading_content(): void
    {
        foreach (['holidays' => 'holiday-sheet__content', 'shrines' => 'shrine-reading-content', 'documents' => 'document-sheet__content', 'gods' => 'god-reading-content', 'articles' => 'article-prose'] as $section => $class) {
            $html = '<section><h1>Заголовок</h1><nav>Головна / Рідна Віра / Розділ</nav></section><article><header>Дата й службовий заголовок</header><div class="'.$class.'"><h2>Зміст</h2><p>Справжній текст.</p><img src="/assets/test.jpg" alt="Фото"></div><footer>Повернутися до списку</footer></article>';
            $data = ContentEditor::prepare(['section' => $section, 'editor_mode' => 'layout', 'body' => $html, 'is_override' => false]);
            $this->assertSame('fragment', $data['editor_mode']);
            $this->assertStringContainsString('Справжній текст.', $data['body']);
            $this->assertStringContainsString('/assets/test.jpg', $data['body']);
            $this->assertStringNotContainsString('Головна', $data['body']);
            $this->assertStringNotContainsString('службовий', $data['body']);
            $this->assertStringNotContainsString('Повернутися', $data['body']);
            $this->assertStringNotContainsString('Головна', $data['excerpt']);
            $rendered = Cms::fragment($html, '<p>Оновлений текст.</p>', 'Нова назва');
            $this->assertStringContainsString('Оновлений текст.', $rendered);
            $this->assertStringContainsString('Повернутися до списку', $rendered);
        }
    }

    public function test_existing_holiday_opens_as_normal_editor_and_saves_into_native_template(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
        $holiday = config('faith_calendar.months.Січень')[0];
        $slug = Str::slug($holiday['name']);
        $page = ContentPage::create(['section' => 'holidays', 'path' => '/ridna-vira/kalendar/'.$slug, 'title' => $holiday['name'], 'editor_mode' => 'layout', 'body' => '<nav>Головна / Календар</nav><article><header>Дата</header><div class="holiday-sheet__content"><p>Старий повний текст</p></div><footer>До календаря</footer></article>']);
        $page->forceFill(['is_imported' => true, 'is_override' => false])->saveQuietly();
        $component = Livewire::test(EditHolidayEntry::class, ['record' => $page->getKey()])
            ->assertSet('data.editor_mode', 'fragment');
        $body = $component->get('data.body');
        if (is_array($body)) {
            $body = RichContentRenderer::make($body)->toHtml();
        }
        $this->assertStringContainsString('Старий повний текст', $body);
        $this->assertStringNotContainsString('Календар', $body);
        $this->assertSame('layout', $page->fresh()->editor_mode);
        $component->set('data.body', '<p>Новий текст свята</p>')->call('save')->assertHasNoErrors();
        $this->assertSame('fragment', $page->fresh()->editor_mode);
        $this->get($page->path)->assertOk()->assertSee('Новий текст свята')->assertSee('holiday-date-card')->assertSee('До календаря свят');
    }

    public function test_page_blocks_exclude_navigation_and_preserve_layout_when_paragraphs_are_added(): void
    {
        $html = '<section class="layout"><h1>Назва</h1><nav><a href="/">Головна</a> / <span>Розділ</span></nav><div class="copy"><p>Перший текст</p><p>Другий текст</p></div><h2>Наступний розділ</h2>[[live:materials]]</section>';
        $blocks = PageBlocks::fields($html);
        $this->assertCount(2, $blocks);
        foreach ($blocks as &$block) {
            if ($block['kind'] === 'rich') {
                $block['value'] = '<p>Новий текст</p><p>Доданий абзац</p><p>Ще абзац</p>';
            }
            if ($block['kind'] === 'heading') {
                $block['value'] = 'Оновлений розділ';
            }
            $this->assertStringNotContainsString('Головна', $block['label']);
        }
        unset($block);
        $output = PageBlocks::apply($html, $blocks);
        $this->assertStringContainsString('class="layout"', $output);
        $this->assertStringContainsString('class="copy"', $output);
        $this->assertStringContainsString('Доданий абзац', $output);
        $this->assertStringContainsString('Оновлений розділ', $output);
        $this->assertStringContainsString('[[live:materials]]', $output);
        $this->assertStringContainsString('<nav>', $output);
    }

    public function test_long_existing_prayer_can_be_opened_and_saved_without_losing_the_reading_text(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
        $catalogue = json_decode(file_get_contents(resource_path('content/prayers/catalogue.json')), true);
        $entries = collect($catalogue['entries'])->filter(fn ($entry) => isset($entry['page']))->sortByDesc(fn ($entry) => filesize(resource_path('content/prayers/'.$entry['page'])));
        $slug = $entries->keys()->first();
        $entry = $entries->first();
        $html = $this->get('/ridna-vira/molytvy/'.$slug)->assertOk()->getContent();
        $body = Cms::fragment($html);
        $this->assertNotNull($body);
        $page = ContentPage::create(['section' => 'prayers', 'path' => '/ridna-vira/molytvy/'.$slug, 'title' => $entry['title'], 'editor_mode' => 'fragment', 'body' => $body]);
        $page->forceFill(['is_imported' => true, 'is_override' => false])->saveQuietly();
        Livewire::test(EditPrayerEntry::class, ['record' => $page->getKey()])->call('save')->assertHasNoErrors();
        $this->assertTrue($page->fresh()->is_override);
        $reading = CmsHtml::render($page->fresh()->body);
        $text = fn ($value) => trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('~</(?:p|h[1-6]|li|div|section|blockquote)>|<br\s*/?>~i', ' ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $this->assertStringContainsString(mb_substr($text($body), 0, 120), $text($reading));
        $this->get($page->path)->assertOk()->assertSee('article-reader');
    }
}
