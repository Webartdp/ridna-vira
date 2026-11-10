<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\Product;
use App\Support\Publications;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SocialMetadataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://ridnavira.com.ua']);
        $this->withoutVite();
    }

    private function graph(string $html): array
    {
        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $json);

        return json_decode($json[1], true, 512, JSON_THROW_ON_ERROR)['@graph'];
    }

    public function test_open_graph_uses_real_image_dimensions_mime_and_article_dates(): void
    {
        Storage::fake('public');
        $upload = UploadedFile::fake()->image('cover.jpg', 1600, 900);
        Storage::disk('public')->put('covers/social.jpg', file_get_contents($upload->getRealPath()));
        $page = ContentPage::create(['path' => '/novyny/social-test', 'title' => 'Нова подія', 'section' => 'news', 'body' => '<p>Матеріал про подію та діяльність духовного центру.</p>', 'image' => 'covers/social.jpg', 'published_at' => now()->subDay()]);
        $response = $this->get($page->path)->assertOk();
        foreach (['og:title', 'og:type', 'og:url', 'og:description', 'og:site_name', 'og:locale', 'og:image', 'og:image:url', 'og:image:secure_url', 'og:image:type', 'og:image:width', 'og:image:height', 'og:image:alt', 'article:published_time', 'article:modified_time', 'article:section'] as $property) {
            $response->assertSee('property="'.$property.'"', false);
        }
        $response->assertSee('content="1200"', false)->assertSee('content="675"', false)->assertSee('content="image/webp"', false)->assertSee('social-1200.webp', false)->assertSee('name="twitter:image:alt"', false);
        $graph = collect($this->graph($response->getContent()));
        $article = $graph->firstWhere('@type', 'NewsArticle');
        $this->assertSame('https://ridnavira.com.ua'.$page->path, $article['mainEntityOfPage']);
        $this->assertSame($page->published_at->toIso8601String(), $article['datePublished']);
        $this->assertSame('Новини', $article['articleSection']);
        $this->assertArrayNotHasKey('author', $article);
        $this->assertSame(1200, $graph->firstWhere('@type', 'ImageObject')['width']);
    }

    public function test_native_news_dates_and_youtube_video_metadata_work_without_cms_override(): void
    {
        $entry = collect((new Publications)->catalogue()['news'])->first(fn ($entry) => ! empty($entry['videos']) && empty($entry['redirect']));
        $response = $this->get('/novyny/'.$entry['slug'])->assertOk()->assertSee('property="article:published_time"', false)->assertSee('property="og:video"', false)->assertSee('property="og:video:secure_url"', false)->assertSee('property="og:video:type" content="text/html"', false);
        $article = collect($this->graph($response->getContent()))->firstWhere('@type', 'NewsArticle');
        $this->assertStringStartsWith($entry['date'], $article['datePublished']);
        $this->assertArrayNotHasKey('dateModified', $article);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/'.$entry['videos'][0], $response->getContent());
    }

    public function test_page_types_logo_and_breadcrumb_entities_are_valid_and_product_links_to_offer(): void
    {
        foreach (['/pro-tsentr' => 'AboutPage', '/zviazok' => 'ContactPage', '/novyny' => 'CollectionPage', '/' => 'WebPage'] as $path => $type) {
            $graph = collect($this->graph($this->get($path)->assertOk()->getContent()));
            $this->assertNotNull($graph->firstWhere('@type', $type));
            $organization = $graph->firstWhere('@type', 'Organization');
            $this->assertSame('ImageObject', $organization['logo']['@type']);
            $this->assertGreaterThan(100, $organization['logo']['width']);
            if ($path !== '/') {
                $this->assertNotNull($graph->firstWhere('@type', 'BreadcrumbList'));
            }
        }
        $product = Product::create(['name' => 'Книга', 'slug' => 'schema-book', 'price_minor' => 32000, 'stock' => 0, 'is_active' => true]);
        $graph = collect($this->graph($this->get('/kramnychka/'.$product->slug)->assertOk()->getContent()));
        $this->assertSame($graph->firstWhere('@type', 'Product')['@id'], $graph->firstWhere('@type', 'WebPage')['mainEntity']['@id']);
        $this->assertSame('https://schema.org/OutOfStock', $graph->firstWhere('@type', 'Product')['offers']['availability']);
    }

    public function test_icons_have_real_files_correct_dimensions_and_head_links(): void
    {
        $response = $this->get('/')->assertOk();
        foreach (['favicon.svg', 'favicon-16x16.png', 'favicon-32x32.png', 'favicon.ico', 'apple-touch-icon.png', 'site.webmanifest'] as $file) {
            $this->assertFileExists(public_path($file));
            $this->assertGreaterThan(0, filesize(public_path($file)));
            $response->assertSee('/'.$file, false);
        }
        $this->assertSame([180, 180], array_slice(getimagesize(public_path('apple-touch-icon.png')), 0, 2));
        $this->assertSame([32, 32], array_slice(getimagesize(public_path('favicon-32x32.png')), 0, 2));
        $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($manifest['icons'] as $icon) {
            [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], $width.'x'.$height);
        }
        $this->get('/admin/login')->assertOk()->assertSee('favicon.svg', false);
        $this->assertSame([], Seo::imageInfo('https://external.example/image.png'));
        $this->assertSame([], Seo::imageInfo('/assets/../.env'));
        $this->assertStringContainsString('Sitemap: https://ridnavira.com.ua/sitemap.xml', file_get_contents(public_path('robots.txt')));
    }

    public function test_sitemap_updates_on_each_publication_edit_schedule_restore_and_product_change(): void
    {
        $page = ContentPage::create(['path' => '/novyny/sitemap-live', 'section' => 'news', 'title' => 'Оновлення', 'body' => '<p>Текст.</p>', 'is_published' => false]);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee($page->path, false);
        $page->update(['is_published' => true]);
        $response = $this->get('/sitemap.xml')->assertOk()->assertSee($page->path, false);
        $this->assertStringContainsString('must-revalidate', $response->headers->get('Cache-Control'));
        $this->travel(2)->minutes();
        $page->update(['title' => 'Оновлена назва']);
        $this->get('/sitemap.xml')->assertOk()->assertSee($page->fresh()->updated_at->toAtomString(), false);
        $page->update(['published_at' => now()->addDay()]);
        $this->get('/sitemap.xml')->assertDontSee($page->path, false);
        $this->travel(2)->days();
        $this->get('/sitemap.xml')->assertSee($page->path, false);
        $page->delete();
        $this->get('/sitemap.xml')->assertDontSee($page->path, false);
        $page->restore();
        $this->get('/sitemap.xml')->assertSee($page->path, false);
        $product = Product::create(['name' => 'Товар', 'slug' => 'sitemap-product', 'price_minor' => 10000, 'stock' => 1, 'is_active' => true]);
        $this->get('/sitemap.xml')->assertSee('/kramnychka/'.$product->slug, false);
        $product->update(['is_active' => false]);
        $this->get('/sitemap.xml')->assertDontSee('/kramnychka/'.$product->slug, false);
    }
}
