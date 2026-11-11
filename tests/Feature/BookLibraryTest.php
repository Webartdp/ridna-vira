<?php

namespace Tests\Feature;

use App\Filament\Resources\BookResource\Pages\EditBookEntry;
use App\Models\ContentPage;
use App\Models\User;
use App\Support\BookLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BookLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_all_existing_books_are_imported_without_overwriting_edits(): void
    {
        $this->assertSame(count(config('faith_books.books')), ContentPage::where('section', 'books')->count());
        $page = ContentPage::where('section', 'books')->firstOrFail();
        $page->update(['title' => 'Назва редактора']);
        $this->assertSame(0, BookLibrary::import());
        $this->assertSame('Назва редактора', $page->fresh()->title);
        $this->get('/ridna-vira/knyhy')->assertOk()->assertSee('Назва редактора')->assertDontSee('https://svit.in.ua/kny/', false);
    }

    public function test_editor_changes_reach_saved_catalogue_and_drafts_are_hidden(): void
    {
        $editor = User::factory()->create();
        $editor->forceFill(['role' => 'editor', 'is_active' => true])->save();
        $this->actingAs($editor);
        $page = ContentPage::where('section', 'books')->firstOrFail();
        ContentPage::updateOrCreate(['path' => '/ridna-vira/knyhy'], ['section' => 'pages', 'title' => 'Бібліотека', 'is_published' => true, 'editor_mode' => 'layout',
            'body' => '<h1>Бібліотека</h1><p>Вступ власника</p><dl class="faith-books-summary"><dt>0</dt></dl><ol class="faith-books-list"><li>ЗАСТАРІЛА КНИГА</li></ol>']);
        Livewire::test(EditBookEntry::class, ['record' => $page->getKey()])->set('data.title', 'Книга після редагування')->set('data.excerpt', 'Новий опис видання.')->set('data.book.author', 'Автор після редагування')->set('data.book.pages', 123)->call('save')->assertHasNoErrors();
        $this->get('/ridna-vira/knyhy')->assertOk()->assertSee('Книга після редагування')->assertSee('Автор після редагування')->assertSee('Вступ власника')->assertDontSee('ЗАСТАРІЛА КНИГА');
        $this->get($page->path)->assertOk()->assertSee('Автор після редагування');
        $page->refresh()->update(['is_published' => false]);
        $this->get('/ridna-vira/knyhy')->assertDontSee('Книга після редагування');
        $this->get($page->path)->assertNotFound();
        $this->get($page->path.'/zavantazhyty/pdf')->assertNotFound();
    }

    public function test_uploaded_files_download_and_other_disk_paths_are_rejected(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('books/new.pdf', '%PDF-1.4 test');
        $page = ContentPage::create(['section' => 'books', 'path' => '/ridna-vira/knyhy/new-book', 'title' => 'Нова книга', 'editor_mode' => 'reading', 'body' => '<p>Новий текст</p>', 'is_published' => true,
            'book' => ['author' => 'Автор', 'pdf' => 'books/new.pdf', 'pdf_enabled' => true, 'docx_enabled' => false]]);
        $this->get($page->path.'/zavantazhyty/pdf')->assertOk()->assertDownload();
        $this->get('/ridna-vira/knyhy')->assertSee('Нова книга');
        $this->get($page->path.'/zavantazhyty/docx')->assertNotFound();
        Storage::disk('public')->put('private.pdf', '%PDF secret');
        $page->update(['book' => ['pdf' => 'private.pdf', 'pdf_enabled' => true]]);
        $this->get($page->path.'/zavantazhyty/pdf')->assertNotFound();
    }
}
