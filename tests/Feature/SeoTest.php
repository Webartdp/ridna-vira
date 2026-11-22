<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\ProductImages;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $structuredData = json_decode($json[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('https://schema.org', $structuredData['@context']);
        $graph = $structuredData['@graph'];
        $this->assertSame('NewsArticle', $graph[2]['@type']);
        $this->assertSame($page->seo_description, $graph[2]['description']);
    }

    public function test_canonicals_remove_tracking_and_keep_real_pagination(): void
    {
        $this->get('/novyny?page=2&utm_source=facebook')->assertOk()
            ->assertSee('href="https://ridnavira.com.ua/novyny?page=2"', false)->assertSee('Сторінка 2')->assertDontSee('og:url" content="https://ridnavira.com.ua/novyny?page=2&amp;utm_', false);
        $this->get('/pro-tsentr?page=2&category=unrelated&q=tracking')->assertOk()
            ->assertSee('<link rel="canonical" href="https://ridnavira.com.ua/pro-tsentr">', false)
            ->assertSee('name="robots" content="index, follow, max-image-preview:large"', false)
            ->assertDontSee('Сторінка 2');
        foreach (['/novyny?page=400', '/statti?page=999', '/tvorchist?page=999'] as $url) {
            $this->get($url)->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
        $this->get('/tvorchist?category='.urlencode('Поезія'))->assertOk()->assertSee('name="robots" content="noindex, follow"', false);
        $this->get('/koshyk')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertDontSee('application/ld+json', false);
        $this->get('/admin/login')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/missing-seo-page')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_imported_documents_have_one_head_and_use_the_visible_document_title(): void
    {
        $originalPublic = public_path();
        $fixture = storage_path('framework/testing/document-seo-'.bin2hex(random_bytes(6)));
        $documents = $fixture.'/assets/documents/';
        mkdir($documents, 0777, true);
        file_put_contents($documents.'manifest.json', json_encode([
            'seo-legacy' => ['title' => 'Статут Духовного центру', 'path' => 'assets/documents/legacy.html', 'extension' => 'html'],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($documents.'legacy.html', '<html><head><title>Старий портал</title></head><body>'
            .'<table><tr><td><a>До розділу</a><h1>Старий портал</h1><blockquote><p>Цей статут визначає організацію роботи та основні засади діяльності Духовного центру.</p></blockquote></td></tr></table>'
            .'</body></html>');

        $this->app->usePublicPath($fixture);
        try {
            ContentPage::forceCreate(['path' => '/dokumenti/seo-legacy', 'section' => 'documents', 'title' => 'Документ',
                'seo_title' => 'Документ — Рідна Віра', 'seo_description' => 'Старий портал. До розділу. Текст документа.',
                'is_imported' => true, 'body' => '<p>Імпортований фрагмент.</p>'])->forceFill(['is_override' => false])->saveQuietly();
            $response = $this->get('/dokumenti/seo-legacy')->assertOk()
                ->assertSee('<title>Статут Духовного центру — Рідна Віра</title>', false)
                ->assertSee('content="Цей статут визначає організацію роботи та основні засади діяльності Духовного центру."', false)
                ->assertDontSee('Старий портал');
            $this->assertSame(1, substr_count($response->getContent(), '<head>'));
            $this->assertSame(1, substr_count($response->getContent(), '<meta name="description"'));
        } finally {
            $this->app->usePublicPath($originalPublic);
            unlink($documents.'legacy.html');
            unlink($documents.'manifest.json');
            rmdir($documents);
            rmdir($fixture.'/assets');
            rmdir($fixture);
        }
    }

    public function test_product_markup_uses_actual_price_stock_and_custom_seo(): void
    {
        $product = Product::create([
            'name' => 'Книга', 'slug' => 'seo-book', 'sku' => 'BOOK-42', 'price_minor' => 25950, 'stock' => 3,
            'is_active' => true, 'description' => '<p>Книга про річне Коло Свароже.</p><iframe>Вміст, якого не видно</iframe>',
            'seo_title' => 'Книга про традицію', 'seo_description' => 'Опис книги для пошуку.',
        ]);
        $response = $this->get('/kramnychka/'.$product->slug)->assertOk()
            ->assertSee('<title>Книга про традицію</title>', false)
            ->assertSee('name="description" content="Опис книги для пошуку."', false)
            ->assertSee('property="og:type" content="product"', false)
            ->assertSee('property="product:price:amount" content="259.50"', false)
            ->assertSee('property="product:price:currency" content="UAH"', false)
            ->assertSee('property="product:availability" content="in stock"', false);
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $response->getContent(), $json);
        $productSchema = collect(json_decode($json[1], true)['@graph'])->firstWhere('@type', 'Product');
        $this->assertSame('259.50', $productSchema['offers']['price']);
        $this->assertSame('UAH', $productSchema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $productSchema['offers']['availability']);
        $this->assertSame('BOOK-42', $productSchema['sku']);
        $this->assertSame('Книга про річне Коло Свароже.', $productSchema['description']);
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

    public function test_approved_maker_listings_generate_seo_from_current_listing_data(): void
    {
        Storage::fake('public');
        $maker = User::factory()->create(['role' => 'maker']);
        $category = ProductCategory::where('slug', 'oberehy-dlia-domu-motanky')->firstOrFail();
        $this->actingAs($maker)->post(route('maker.products.store'), [
            'name' => 'Лляна мотанка', 'description' => 'Льон і вовна.', 'price' => '1000',
            'product_category_id' => $category->id,
            'images' => [UploadedFile::fake()->image('front.jpg', 320, 480), UploadedFile::fake()->image('back.jpg', 320, 480)],
        ])->assertRedirect(route('maker.index'));

        $product = Product::firstOrFail();
        $path = '/kramnychka/'.$product->slug;
        $url = 'https://ridnavira.com.ua'.$path;
        $this->get($path)->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/sitemap.xml')->assertDontSee($path, false);

        $product->update(['is_active' => true]);
        $this->artisan('site:seo')->assertSuccessful();
        $this->assertNull($product->fresh()->seo_title);
        $this->assertNull($product->fresh()->seo_description);
        $photos = array_map(fn ($path) => 'https://ridnavira.com.ua/storage/'.ProductImages::variant($path, 1200), $product->images);
        $response = $this->get($path.'?page=3&category=unrelated&utm_medium=test')->assertOk()
            ->assertSee('<title>Лляна мотанка — Крамниця «Рідна Віра»</title>', false)
            ->assertSee('name="description" content="Льон і вовна. Лляна мотанка · '.$category->name.'"', false)
            ->assertSee('property="og:image" content="'.$photos[0].'"', false)
            ->assertSee('property="product:availability" content="available for order"', false)
            ->assertSee('<link rel="canonical" href="'.$url.'">', false);
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $response->getContent(), $json);
        $graph = json_decode($json[1], true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $productSchema = collect($graph)->firstWhere('@type', 'Product');
        $this->assertSame($photos, $productSchema['image']);
        $this->assertSame($category->parent->name.' > '.$category->name, $productSchema['category']);
        $this->assertSame('Льон і вовна.', $productSchema['description']);
        $this->assertSame(Money::decimal($product->price_minor), $productSchema['offers']['price']);
        $this->assertSame('https://schema.org/MadeToOrder', $productSchema['offers']['availability']);
        $this->assertArrayNotHasKey('sku', $productSchema);
        $this->get('/sitemap.xml')->assertSee($url, false);

        $product->update(['name' => 'Мотанка з льону', 'description' => '<p>Робота з льону.</p>']);
        $this->get($path)->assertOk()->assertSee('<title>Мотанка з льону — Крамниця «Рідна Віра»</title>', false)
            ->assertSee('name="description" content="Робота з льону. Мотанка з льону', false);

        $product->update(['seo_title' => 'Авторська назва', 'seo_description' => 'Опис, заданий адміністратором.']);
        $this->artisan('site:seo')->assertSuccessful();
        $this->get($path)->assertOk()->assertSee('<title>Авторська назва</title>', false)
            ->assertSee('name="description" content="Опис, заданий адміністратором."', false);
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
