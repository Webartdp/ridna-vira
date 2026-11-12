<?php

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class ArticleTest extends TestCase
{
    private string $fixtureStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['cache.default' => 'array']);
        $this->fixtureStorage = storage_path('framework/testing/articles-'.bin2hex(random_bytes(6)));
        mkdir($this->fixtureStorage.'/app/content/articles', 0777, true);
        $this->app->useStoragePath($this->fixtureStorage);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->fixtureStorage);
        parent::tearDown();
    }

    public function test_existing_import_is_presented_with_real_titles_and_clean_content_without_changing_urls(): void
    {
        $root = $this->fixtureStorage.'/app/content/articles/';
        $original = '<table width="750" bgcolor="yellow"><tr><td><h2>Традиція українського дому</h2><p><font size="2">Перший абзац про традицію.</font></p><p>Другий абзац.</p></td></tr></table>';
        file_put_contents($root.'legacy.html', $original);
        $manifest = json_encode(['articles' => ['ridna-vira-abc123' => [
            'title' => 'Ridna Vira', 'extension' => 'html', 'path' => 'legacy.html',
        ]]], JSON_UNESCAPED_UNICODE);
        file_put_contents($root.'manifest.json', $manifest);
        $this->get('/statti')->assertOk()
            ->assertSee('Традиція українського дому')
            ->assertSee('/statti/ridna-vira-abc123', false)
            ->assertSee('Перший абзац про традицію.')
            ->assertDontSee('Ridna Vira')
            ->assertDontSee('HTML')
            ->assertDontSee('імпорт');
        $this->get('/statti/ridna-vira-abc123')->assertOk()
            ->assertSee('Традиція українського дому')
            ->assertSee('<p>Другий абзац.</p>', false)
            ->assertDontSee('bgcolor', false)
            ->assertDontSee('<font', false)
            ->assertDontSee('HTML');
        $this->get('/statti/ridna-vira-abc123/zavantazhyty')->assertOk();
        $this->assertSame($original, file_get_contents($root.'legacy.html'));
        $this->assertSame($manifest, file_get_contents($root.'manifest.json'));
    }

    public function test_catalogue_groups_by_source_author_and_filters_by_title_and_author(): void
    {
        $root = $this->fixtureStorage.'/app/content/articles/';
        file_put_contents($root.'study.pdf', '%PDF-1.4');
        $base = ['extension' => 'pdf', 'path' => 'study.pdf'];
        $entries = [
            'traditions' => $base + ['title' => 'Традиції слов’ян', 'source_url' => 'https://svit.in.ua/stat/st60.htm'],
            'another' => $base + ['title' => 'Інша стаття', 'source_url' => 'https://www.svit.in.ua/stat/st60.htm'],
            'anonymous' => $base + ['title' => 'Анонімна стаття'],
        ];
        file_put_contents($root.'manifest.json', json_encode(['articles' => $entries]));

        $this->get('/statti')->assertOk()
            ->assertSeeInOrder(['Башлакова В.', 'Інша стаття', 'Традиції слов’ян', 'Автор не вказаний', 'Анонімна стаття'])
            ->assertDontSee('article-preview__cover');

        $query = http_build_query(['q' => "СЛОВ'ЯН", 'author' => 'бАШЛАК']);
        $this->get('/statti?'.$query)->assertOk()
            ->assertSee('Традиції слов’ян')
            ->assertDontSee('Інша стаття')
            ->assertDontSee('Анонімна стаття');

        $this->get('/statti?author='.urlencode('Невідомий автор'))->assertOk()
            ->assertSee('За цим запитом статей не знайдено.')
            ->assertSee('Скинути пошук');
    }

    public function test_articles_are_paginated_and_non_html_materials_still_download(): void
    {
        $root = $this->fixtureStorage.'/app/content/articles/';
        file_put_contents($root.'study.pdf', '%PDF-1.4');
        $entries = [];
        for ($index = 1; $index <= 19; $index++) {
            $slug = 'study-'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $entries[$slug] = ['slug' => $slug, 'title' => 'Дослідження '.str_pad((string) $index, 2, '0', STR_PAD_LEFT).' (pdf)', 'extension' => 'pdf', 'path' => 'study.pdf'];
        }
        file_put_contents($root.'manifest.json', json_encode(['articles' => $entries]));
        $this->get('/statti')->assertOk()->assertSee('Дослідження 01')->assertDontSee('Дослідження 19')->assertDontSee('(pdf)');
        $this->get('/statti?page=2')->assertOk()->assertSee('Дослідження 19')->assertDontSee('Дослідження 01');
        $this->get('/statti/study-19')->assertOk()->assertSee('Завантажити PDF');
        $this->get('/statti/study-19/zavantazhyty')->assertOk();
    }
}
