<?php

namespace Tests\Feature;

use App\Support\ArticleContent;
use App\Support\ContentImages;
use App\Support\Publications;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class PublicationTest extends TestCase
{
    public function test_homepage_displays_twelve_real_distinct_material_links_and_real_news_dates(): void
    {
        $this->withoutVite();
        $response = $this->get('/')->assertOk()->assertDontSee('20.03.2027')->assertDontSee('Світолад української оселі');
        $sections = $response->viewData('materialSections');
        $this->assertCount(3, $sections);
        $links = [];
        foreach ($sections as $section) {
            $this->assertCount(4, $section['items']);
            foreach ($section['items'] as $item) {
                $response->assertSee($item['title'])->assertSee($item['url']);
                $links[] = $item['url'];
            }
        }
        $this->assertCount(12, array_unique($links));
        $this->assertSame('2026-11-08', $sections[0]['items'][0]['date']);
        $this->assertArrayNotHasKey('date', $sections[1]['items'][0]);
        $this->assertArrayNotHasKey('date', $sections[2]['items'][0]);
        $videoNews = (new Publications)->find('news', '2026-10-30-d9bda10b');
        $this->assertSame('youtube', $videoNews['image_origin']);
        $this->assertSame($videoNews['videos'][0], $videoNews['video_cover_id']);
        $response->assertSee($videoNews['image']['src'], false);
    }

    public function test_news_and_creativity_are_paginated_and_all_imported_pages_open(): void
    {
        $this->withoutVite();
        $library = new Publications;
        $this->assertCount(19, $library->entries('news'));
        $this->assertCount(44, $library->entries('creativity'));
        $this->get('/novyny')->assertOk()->assertSee('На Харківщині прославили Сварога')->assertDontSee('Showing');
        foreach ($library->entries('news') as $entry) {
            if (! empty($entry['videos'])) {
                $this->assertNotEmpty($entry['image']);
                $this->assertFileExists(public_path(ltrim($entry['image']['src'], '/')));
            }
        }
        $this->get('/novyny?page=2')->assertOk()->assertSee('Дідух: значення')->assertDontSee('На Харківщині прославили Сварога');
        $this->get('/tvorchist')->assertOk()->assertSee('Сонцеслав');
        $this->get('/tvorchist?category='.urlencode('Писанкарство'))->assertOk()->assertSee('Писанкарство — колекція')->assertDontSee('Олеся Вакуленко');
        foreach (['news' => 'news.show', 'creativity' => 'creativity.show'] as $section => $route) {
            foreach ($library->entries($section) as $entry) {
                $response = $this->get(route($route, $entry['slug']));
                if (! empty($entry['redirect'])) {
                    $response->assertRedirect($entry['redirect']);
                } else {
                    $response->assertOk()->assertSee($entry['title']);
                    if ($section === 'news') {
                        foreach ($entry['videos'] ?? [] as $video) {
                            $response->assertSee('https://www.youtube-nocookie.com/embed/'.$video, false);
                        }
                    }
                    $content = file_get_contents(resource_path('content/publications/'.$entry['path']));
                    $response->assertSee(ContentImages::limitUpscaling($content), false);
                    $this->assertDoesNotMatchRegularExpression('/<(?:script|iframe|font|form)\b|\s(?:style|bgcolor|onclick)=/i', $content);
                    preg_match_all('/<img[^>]+src="([^"]+)"/', $content, $matches);
                    foreach ($matches[1] as $path) {
                        $this->assertStringStartsWith('/assets/publications/', $path);
                        $this->assertFileExists(public_path(ltrim($path, '/')));
                    }
                }
            }
        }
        $this->get('/novyny/missing')->assertNotFound();
        $this->get('/tvorchist/missing')->assertNotFound();
    }

    public function test_homepage_keeps_existing_deployed_article_slugs_and_does_not_change_the_import(): void
    {
        $this->withoutVite();
        config(['cache.default' => 'array']);
        $originalStorage = storage_path();
        $fixture = storage_path('framework/testing/home-'.bin2hex(random_bytes(6)));
        mkdir($fixture.'/app/content/articles', 0777, true);
        $this->app->useStoragePath($fixture);
        $manifest = json_encode(['articles' => ['legacy-article-address' => [
            'slug' => 'legacy-article-address', 'title' => 'Ridna Vira', 'extension' => 'html',
            'source_url' => 'http://svit.in.ua/stat/st60.htm', 'path' => 'existing.html',
        ]]]);
        file_put_contents($fixture.'/app/content/articles/manifest.json', $manifest);
        file_put_contents($fixture.'/app/content/articles/existing.html', '<h1>Структура замовляння у слов’ян</h1><p>Чинний матеріал.</p>');
        try {
            $this->get('/')->assertOk()->assertSee('/statti/legacy-article-address', false);
            $this->get('/statti/legacy-article-address')->assertOk()->assertSee('Чинний матеріал.');
            $this->get('/statti/sosnu-pidpalyly')->assertOk()->assertSee('Сосну підпалили');
            $this->assertSame($manifest, file_get_contents($fixture.'/app/content/articles/manifest.json'));
        } finally {
            $this->app->useStoragePath($originalStorage);
            (new Filesystem)->deleteDirectory($fixture);
        }
    }

    public function test_image_only_galleries_and_small_artwork_survive_cleaning(): void
    {
        $html = '<article><p>Колекція</p><table><tr><td><img src="/assets/large.jpg" height="200"></td><td><img src="/assets/pattern.gif" height="17"></td></tr></table></article>';
        $content = (new ArticleContent(true))->body($html, 'Галерея');
        $this->assertStringContainsString('/assets/large.jpg', $content);
        $this->assertStringContainsString('/assets/pattern.gif', $content);
        $this->assertStringNotContainsString('/assets/pattern.gif', (new ArticleContent)->body($html, 'Галерея'));
        $this->assertStringNotContainsString('<table', $content);
    }
}
