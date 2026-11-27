<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CalendarDownloadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_calendar_links_use_the_same_address_for_identical_downloads(): void
    {
        $this->withoutVite();
        Storage::fake('local');
        $public = Storage::fake('public');
        $this->app->usePublicPath($public->path(''));
        $bookDirectory = 'assets/books/ruskii-pravoslavnii-kalendar/';
        $holiday = 'assets/holidays/zivii-vogon/';
        $public->put($bookDirectory.'ruskii-pravoslavnii-kalendar.pdf', 'calendar edition');
        $public->put($holiday.'calendar.pdf', 'calendar edition');
        $public->put($holiday.'another-edition.pdf', 'another edition');
        $public->put($holiday.'scan.djvu', 'same scan');
        $public->put('assets/holidays/earlier-entry/scan.djvu', 'same scan');

        ContentPage::create([
            'path' => '/ridna-vira/kalendar/zivii-vogon',
            'section' => 'holidays',
            'title' => 'Живий Вогонь',
            'editor_mode' => 'reading',
            'is_override' => true,
            'is_published' => true,
            'body' => '<p><a href="/'.$holiday.'calendar.pdf#page=3">Календар</a></p>'
                .'<p><a href="/'.$holiday.'scan.djvu">Скан</a></p>'
                .'<p><a href="/'.$holiday.'another-edition.pdf">Інше видання</a></p>'
                .'<p><a href="https://example.org/'.$holiday.'calendar.pdf">Зовнішній файл</a></p>',
        ]);

        $this->artisan('calendar:deduplicate-downloads')->assertSuccessful();

        $bookDownload = route('faith.books.download', ['book' => 'ruskii-pravoslavnii-kalendar', 'format' => 'pdf'], absolute: false);
        $this->get('/ridna-vira/kalendar/zivii-vogon')->assertOk()
            ->assertSee('href="'.$bookDownload.'#page=3"', false)
            ->assertSee('href="/assets/holidays/earlier-entry/scan.djvu"', false)
            ->assertSee('href="/'.$holiday.'another-edition.pdf"', false)
            ->assertSee('href="https://example.org/'.$holiday.'calendar.pdf"', false)
            ->assertDontSee('href="/'.$holiday.'calendar.pdf', false);

        $this->get($bookDownload)->assertOk();
        $public->delete('assets/holidays/earlier-entry/scan.djvu');
        $book = ContentPage::where('path', '/ridna-vira/knyhy/ruskii-pravoslavnii-kalendar')->firstOrFail();
        $book->update(['book' => [...($book->book ?? []), 'pdf_enabled' => false]]);
        $this->get('/ridna-vira/kalendar/zivii-vogon')->assertOk()
            ->assertSee('href="/'.$holiday.'calendar.pdf#page=3"', false)
            ->assertSee('href="/'.$holiday.'scan.djvu"', false);
    }
}
