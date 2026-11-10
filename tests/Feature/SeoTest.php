<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.url' => 'https://ridnavira.com.ua']);
    }

    public function test_public_sections_have_distinct_titles_descriptions_and_canonical_urls(): void
    {
        $titles = [];
        $descriptions = [];
        foreach (array_diff(array_keys(config('seo.pages')), ['/umovy-kramnytsi']) as $path) {
            $response = $this->get($path)->assertOk();
            $html = $response->getContent();
            preg_match('~<title>(.*?)</title>~s', $html, $title);
            preg_match('~<meta name="description" content="(.*?)">~s', $html, $description);
            $titles[] = $title[1];
            $descriptions[] = $description[1];
            $response->assertSee('<link rel="canonical" href="https://ridnavira.com.ua'.$path.'">', false)->assertSee('application/ld+json', false);
            $this->assertSame(1, substr_count($html, '<title>'));
            $this->assertSame(1, substr_count($html, '<meta name="description"'));
        }
        $this->assertCount(count($titles), array_unique($titles));
        $this->assertCount(count($descriptions), array_unique($descriptions));
    }

    public function test_custom_seo_is_used_after_native_body_overrides_and_is_safely_escaped(): void
    {
        $page = ContentPage::create(['path' => '/novyny/seo-test', 'section' => 'news', 'title' => 'Назва матеріалу', 'body' => '<p>Текст новини.</p>', 'seo_title' => 'Своя назва <script>alert(1)</script>', 'seo_description' => 'Опис із "лапками" та & символом.']);
        $response = $this->get($page->path)->assertOk()->assertSee('<title>Своя назва &lt;script&gt;alert(1)&lt;/script&gt;</title>', false)
            ->assertSee('og:type" content="article', false)->assertDontSee('<script>alert(1)</script>', false);
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $response->getContent(), $json);
        $graph = json_decode($json[1], true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $this->assertSame('Article', $graph[2]['@type']);
        $this->assertSame($page->seo_description, $graph[2]['description']);
    }

    public function test_canonicals_remove_tracking_and_keep_real_pagination(): void
    {
        $this->get('/novyny?page=2&utm_source=facebook')->assertOk()
            ->assertSee('href="https://ridnavira.com.ua/novyny?page=2"', false)->assertSee('Сторінка 2')->assertDontSee('og:url" content="https://ridnavira.com.ua/novyny?page=2&amp;utm_', false);
        $this->get('/tvorchist?category='.urlencode('Поезія'))->assertOk()->assertSee('name="robots" content="noindex, follow"', false);
        $this->get('/koshyk')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('application/ld+json', false);
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/missing-seo-page')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_product_markup_uses_actual_price_stock_and_custom_seo(): void
    {
        $product = Product::create(['name' => 'Книга', 'slug' => 'seo-book', 'price_minor' => 25950, 'stock' => 3, 'is_active' => true, 'seo_title' => 'Книга про традицію', 'seo_description' => 'Опис книги для пошуку.']);
        $response = $this->get('/kramnychka/'.$product->slug)->assertOk()->assertSee('<title>Книга про традицію</title>', false);
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $response->getContent(), $json);
        $productSchema = collect(json_decode($json[1], true)['@graph'])->firstWhere('@type', 'Product');
        $this->assertSame('259.50', $productSchema['offers']['price']);
        $this->assertSame('UAH', $productSchema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $productSchema['offers']['availability']);
        $this->assertArrayNotHasKey('aggregateRating', $productSchema);
    }

    public function test_sitemap_excludes_drafts_future_deleted_private_and_inactive_products(): void
    {
        foreach (['/novyny/draft' => ['is_published' => false], '/novyny/future' => ['published_at' => now()->addDay()], '/novyny/deleted' => [], '/novyny/public' => [], '/ridna-vira/bohy/perun' => ['is_published' => false], '/zamovlennia/secret' => []] as $path => $attrs) {
            $page = ContentPage::create(['path' => $path, 'section' => 'news', 'title' => 'Тест', 'body' => '<p>Текст.</p>', ...$attrs]);
            if ($path === '/novyny/deleted') {
                $page->delete();
            }
        }
        Product::create(['name' => 'Прихований', 'slug' => 'hidden', 'price_minor' => 100, 'stock' => 1, 'is_active' => false]);
        $response = $this->get('/sitemap.xml')->assertOk()->assertSee('https://ridnavira.com.ua/novyny/public', false);
        foreach (['/novyny/draft', '/novyny/future', '/novyny/deleted', '/ridna-vira/bohy/perun', '/zamovlennia/', '/kramnychka/hidden', '/admin', '/koshyk'] as $path) {
            $response->assertDontSee($path, false);
        }
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $this->assertGreaterThan(100, count($xml->url));
    }

    public function test_backfill_is_idempotent_and_preserves_body_visibility_and_editor_seo(): void
    {
        $page = ContentPage::create(['path' => '/ridna-vira/bohy/test', 'section' => 'gods', 'title' => 'Бог', 'body' => '<p>Опис світоглядного образу та його символіки в духовній традиції українців.</p>', 'seo_title' => 'Редакторський заголовок']);
        $page->forceFill(['is_override' => false])->saveQuietly();
        $before = $page->fresh();
        $revisions = $page->revisions()->count();
        $this->artisan('site:seo')->assertSuccessful();
        $after = $page->fresh();
        $this->assertSame('Редакторський заголовок', $after->seo_title);
        $this->assertStringContainsString('Опис світоглядного образу', $after->seo_description);
        $this->assertFalse($after->is_override);
        $this->assertSame($before->body, $after->body);
        $this->assertTrue($before->updated_at->equalTo($after->updated_at));
        $this->assertSame($revisions, $page->revisions()->count());
        $this->artisan('site:seo')->expectsOutputToContain('Оновлено SEO: 0.')->assertSuccessful();
    }
}
