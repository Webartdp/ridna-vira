<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use Tests\TestCase;

class SitemapCoverageTest extends TestCase
{
    use RefreshDatabase;

    private const IMAGE_NAMESPACE = 'http://www.google.com/schemas/sitemap-image/1.1';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://ridnavira.com.ua']);
    }

    public function test_sitemap_lists_published_shop_categories_and_existing_product_images(): void
    {
        Storage::fake('public');
        $photo = 'products/sitemap-front.jpg';
        $variant = ProductImages::variant($photo, 1200);
        Storage::disk('public')->put($variant, UploadedFile::fake()->image('photo.jpg', 1200, 800)->getContent());
        $parent = ProductCategory::create(['name' => 'Обереги', 'slug' => 'sitemap-protective-charms']);
        $child = $parent->children()->create(['name' => 'Мотанки', 'slug' => 'sitemap-lialky']);
        $empty = ProductCategory::create(['name' => 'Без товарів', 'slug' => 'sitemap-empty']);
        $product = Product::create([
            'name' => 'Мотанка',
            'slug' => 'sitemap-motanka',
            'product_category_id' => $child->id,
            'price_minor' => 10000,
            'images' => [$photo, 'products/missing.jpg', $photo],
            'is_active' => true,
        ]);
        $urls = $this->sitemapUrls();
        $this->assertTrue($urls->has('https://ridnavira.com.ua/kramnychka/katehorii/'.$parent->slug));
        $this->assertTrue($urls->has('https://ridnavira.com.ua/kramnychka/katehorii/'.$child->slug));
        $this->assertFalse($urls->has('https://ridnavira.com.ua/kramnychka/katehorii/'.$empty->slug));
        $entry = $urls->get('https://ridnavira.com.ua/kramnychka/'.$product->slug);
        $this->assertSame($product->updated_at->toAtomString(), (string) $entry->lastmod);
        $images = $entry->children(self::IMAGE_NAMESPACE)->image;
        $this->assertCount(1, $images);
        $this->assertSame('https://ridnavira.com.ua/storage/'.$variant, (string) $images[0]->children(self::IMAGE_NAMESPACE)->loc);

        $product->update(['is_active' => false]);
        $urls = $this->sitemapUrls();
        $this->assertFalse($urls->has('https://ridnavira.com.ua/kramnychka/katehorii/'.$parent->slug));
        $this->assertFalse($urls->has('https://ridnavira.com.ua/kramnychka/katehorii/'.$child->slug));
        $this->assertFalse($urls->has('https://ridnavira.com.ua/kramnychka/'.$product->slug));
    }

    public function test_sitemap_uses_the_later_scheduled_publication_date_and_omits_missing_update_timestamps(): void
    {
        $publication = ContentPage::create([
            'path' => '/novyny/sitemap-scheduled', 'section' => 'news', 'title' => 'Подія',
            'body' => '<p>Опис події.</p>', 'published_at' => now()->addDay(),
        ]);
        $product = Product::create([
            'name' => 'Книга', 'slug' => 'sitemap-legacy-book', 'price_minor' => 10000, 'is_active' => true,
        ]);
        Product::whereKey($product->id)->toBase()->update(['updated_at' => null]);
        $this->assertFalse($this->sitemapUrls()->has('https://ridnavira.com.ua'.$publication->path));

        $this->travel(2)->days();
        $urls = $this->sitemapUrls();
        $this->assertSame($publication->published_at->toAtomString(), (string) $urls->get('https://ridnavira.com.ua'.$publication->path)->lastmod);
        $this->assertFalse(isset($urls->get('https://ridnavira.com.ua/kramnychka/'.$product->slug)->lastmod));
    }

    public function test_robots_excludes_payment_endpoints_but_allows_the_shipping_policy(): void
    {
        $robots = str_replace("\r\n", "\n", file_get_contents(public_path('robots.txt')));
        $this->assertStringContainsString("Disallow: /oplata/\n", $robots);
        $this->assertStringNotContainsString("Disallow: /oplata\n", $robots);
    }

    private function sitemapUrls(): Collection
    {
        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $sitemap = simplexml_load_string($response->getContent());
        $this->assertInstanceOf(SimpleXMLElement::class, $sitemap);

        return collect(iterator_to_array($sitemap->url, preserve_keys: false))->keyBy(fn (SimpleXMLElement $entry): string => (string) $entry->loc);
    }
}
