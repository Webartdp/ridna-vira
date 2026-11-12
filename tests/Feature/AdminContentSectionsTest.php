<?php

namespace Tests\Feature;

use App\Filament\Resources\ArticleResource\Pages\EditArticleEntry;
use App\Filament\Resources\ArticleResource\Pages\ManageArticleEntries;
use App\Filament\Resources\ContentPageResource\Pages\ManageContentPages;
use App\Filament\Resources\NewsResource\Pages\ManageNewsEntries;
use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminContentSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $user->forceFill(['is_admin' => true])->save();
        $this->actingAs($user);
    }

    public function test_news_articles_and_pages_have_independent_lists(): void
    {
        $news = ContentPage::create(['section' => 'news', 'title' => 'Окрема новина', 'path' => '/novyny/test', 'body' => '<p>Текст</p>']);
        $article = ContentPage::create(['section' => 'articles', 'title' => 'Окрема стаття', 'path' => '/statti/test', 'body' => '<p>Текст</p>']);
        $page = ContentPage::create(['section' => 'pages', 'title' => 'Окрема сторінка', 'path' => '/test', 'body' => '<p>Текст</p>']);
        Livewire::test(ManageNewsEntries::class)->assertCanSeeTableRecords([$news])->assertCanNotSeeTableRecords([$article, $page]);
        Livewire::test(ManageArticleEntries::class)->assertCanSeeTableRecords([$article])->assertCanNotSeeTableRecords([$news, $page]);
        Livewire::test(ManageContentPages::class)->assertCanSeeTableRecords([$page])->assertCanNotSeeTableRecords([$news, $article]);
        foreach (array_keys(ContentPage::sections()) as $section) {
            $this->get('/admin/'.($section === 'pages' ? 'content-pages' : $section))->assertOk();
        }
    }

    public function test_add_news_automatically_uses_news_section_and_updates_public_site(): void
    {
        Livewire::test(ManageNewsEntries::class)->callAction('create', data: [
            'title' => 'Новина з окремого розділу', 'path' => '/novyny/new-entry', 'body' => '<p>Новий текст</p>', 'editor_mode' => 'reading', 'is_published' => true,
        ])->assertHasNoActionErrors();
        $this->assertDatabaseHas('content_pages', ['path' => '/novyny/new-entry', 'section' => 'news']);
        $this->get('/novyny')->assertOk()->assertSee('Новина з окремого розділу');
        $this->get('/novyny/new-entry')->assertOk()->assertSee('Новий текст');
    }

    public function test_article_author_can_be_changed_in_admin_and_searched_on_the_site(): void
    {
        $article = ContentPage::create([
            'section' => 'articles', 'title' => 'Редагована стаття', 'path' => '/statti/edit-author',
            'body' => '<p>Текст</p>', 'editor_mode' => 'reading', 'is_published' => true,
        ]);
        Livewire::test(EditArticleEntry::class, ['record' => $article->getRouteKey()])
            ->fillForm(['author' => 'Оксана Ткачук'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('Оксана Ткачук', $article->refresh()->author);
        $this->withoutVite()->get('/statti?author='.urlencode('оксана ткачук'))->assertOk()
            ->assertSeeInOrder(['Оксана Ткачук', 'Редагована стаття'])
            ->assertSee('name="robots" content="noindex, follow"', false);
    }

    public function test_news_editor_cannot_accidentally_create_an_article(): void
    {
        Livewire::test(ManageNewsEntries::class)->callAction('create', data: [
            'title' => 'Перевірка розділу', 'section' => 'articles', 'path' => '/statti/wrong', 'body' => '<p>Текст</p>', 'editor_mode' => 'reading', 'is_published' => true,
        ])->assertHasActionErrors(['section']);
        $this->assertDatabaseMissing('content_pages', ['path' => '/statti/wrong']);
    }
}
