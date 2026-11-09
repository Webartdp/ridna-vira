<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class HomeTest extends TestCase
{
    private string $fixtureStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->fixtureStorage = storage_path('framework/testing/home-'.bin2hex(random_bytes(6)));
        mkdir($this->fixtureStorage.'/app/content/articles', 0777, true);
        $this->app->useStoragePath($this->fixtureStorage);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        (new Filesystem)->deleteDirectory($this->fixtureStorage);
        parent::tearDown();
    }

    public function test_home_works_without_an_article_import_and_links_to_existing_destinations(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Дізнатися більше')
            ->assertSee(route('contact').'#communities-map-title', false)
            ->assertSee(route('faith.calendar'), false)
            ->assertSee('Книги про Рідну Віру')
            ->assertSee('site-header', false)
            ->assertSee('site-footer', false)
            ->assertDontSee('20.03.2027');
    }

    public function test_upcoming_holidays_include_today_and_roll_over_to_the_next_year(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-25 18:00', 'Europe/Kyiv'));
        $holidays = $this->get('/')->assertOk()->viewData('upcomingHolidays');
        $this->assertCount(4, $holidays);
        $this->assertSame('2026-12-25', $holidays[0]['date']->format('Y-m-d'));
        $this->assertSame('2027-01-01', $holidays[3]['date']->format('Y-m-d'));
        foreach ($holidays as $holiday) {
            $this->get(route('faith.holiday', $holiday['slug']))->assertOk();
        }
    }

    public function test_leap_day_is_not_changed_to_march_first(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-02-28', 'Europe/Kyiv'));
        config(['faith_calendar.months' => ['Січень' => [], 'Лютий' => [['day' => '29', 'name' => 'Вій']]]]);
        $holidays = $this->get('/')->assertOk()->viewData('upcomingHolidays');
        $this->assertSame('2028-02-29', $holidays[0]['date']->format('Y-m-d'));
    }

    public function test_articles_use_imported_titles_and_skip_missing_or_unsafe_files(): void
    {
        $root = $this->fixtureStorage.'/app/content/articles/';
        file_put_contents($root.'real.html', '<p>Текст статті</p>');
        file_put_contents($root.'manifest.json', json_encode(['articles' => [
            'real' => ['title' => 'Жива традиція', 'path' => 'real.html'],
            'missing' => ['title' => 'Немає файлу', 'path' => 'missing.html'],
            'unsafe' => ['title' => 'Хибний шлях', 'path' => '../real.html'],
        ]], JSON_UNESCAPED_UNICODE));
        $this->get('/')->assertOk()
            ->assertSee('Жива традиція')
            ->assertSee(route('articles.show', 'real'), false)
            ->assertDontSee('Немає файлу')
            ->assertDontSee('Хибний шлях');
        $this->get(route('articles.show', 'real'))->assertOk();
    }

    public function test_calendar_note_places_viy_on_the_last_day_of_february(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-02-28', 'Europe/Kyiv'));
        $holidays = $this->get('/')->assertOk()->viewData('upcomingHolidays');
        $this->assertSame('Вій**. Закінчення Зими', $holidays[0]['name']);
        $this->assertSame('2027-02-28', $holidays[0]['date']->format('Y-m-d'));
    }
}
