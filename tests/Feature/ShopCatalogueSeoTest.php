<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Support\Cms;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCatalogueSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_seo_describes_the_current_category_and_links_only_public_products(): void
    {
        $this->withoutVite();
        config(['app.url' => 'https://ridnavira.com.ua']);
        $category = ProductCategory::create(['name' => 'Кераміка', 'slug' => 'seo-keramika']);
        $subcategory = $category->children()->create(['name' => 'Глиняні миски', 'slug' => 'seo-mysky']);
        $empty = $category->children()->create(['name' => 'Глечики', 'slug' => 'seo-hlechyky']);
        $bowl = $this->product($subcategory, 'seo-bowl', 'Глиняна миска');
        $this->product($empty, 'seo-hidden', 'Прихований глечик', active: false);
        $this->product($empty, 'seo-deleted', 'Видалений глечик')->delete();

        $this->get('/kramnychka')->assertOk()->assertSee('href="'.$category->publicUrl().'"', false);
        $this->get($category->publicUrl())->assertOk()
            ->assertSee('href="'.$subcategory->publicUrl().'"', false)
            ->assertDontSee('href="'.$empty->publicUrl().'"', false);
        $this->get($empty->publicUrl())->assertNotFound();

        $url = 'https://ridnavira.com.ua'.$subcategory->publicUrl();
        $response = $this->get($subcategory->publicUrl())->assertOk()
            ->assertSee('<h1>Глиняні миски</h1>', false)
            ->assertSee('Глиняні миски · Кераміка — Крамниця', false)
            ->assertSee('href="'.$url.'"', false)
            ->assertSee('index, follow, max-image-preview:large');
        $document = new DOMXPath(Cms::document($response->getContent()));
        $schema = $document->query('//script[@type="application/ld+json"]')->item(0)->textContent;
        $graph = collect(json_decode($schema, true, flags: JSON_THROW_ON_ERROR)['@graph']);
        $items = $graph->firstWhere('@type', 'CollectionPage')['mainEntity']['itemListElement'];
        $this->assertSame(['https://ridnavira.com.ua'.route('shop.product', $bowl, false)], array_column($items, 'url'));
        $this->assertSame(['Головна', 'Крамниця', $category->name, $subcategory->name], array_column(
            $graph->firstWhere('@type', 'BreadcrumbList')['itemListElement'], 'name',
        ));
    }

    public function test_category_pagination_is_self_canonical_and_facets_remain_out_of_the_index(): void
    {
        $this->withoutVite();
        config(['app.url' => 'https://ridnavira.com.ua']);
        $category = ProductCategory::create(['name' => 'Оздоблення оселі', 'slug' => 'seo-ozdoblennia']);
        for ($i = 1; $i <= 13; $i++) {
            $this->product($category, 'seo-didukh-'.$i, 'Дідух №'.$i);
        }
        $path = $category->publicUrl();
        $this->get('/kramnychka?category='.$category->slug.'&page=2&price_from=100')
            ->assertStatus(301)->assertRedirect($path.'?page=2&price_from=100');
        $this->get($path.'?page=2&price_from=100')->assertOk()
            ->assertSee('name="robots" content="noindex, follow"', false)->assertDontSee('application/ld+json');
        $this->get($path.'?page=2&utm_source=test')->assertOk()
            ->assertSee('rel="canonical" href="https://ridnavira.com.ua'.$path.'?page=2"', false)
            ->assertSee('Сторінка 2')->assertSee('Дідух №1')->assertDontSee('Дідух №13');
        $this->get($path.'?page=1')->assertOk()
            ->assertSee('rel="canonical" href="https://ridnavira.com.ua'.$path.'"', false);
        $this->get($path.'?page=3')->assertNotFound();
        $this->get('/kramnychka?page=3')->assertNotFound();
    }

    public function test_shop_copy_is_server_rendered_after_the_first_page_paginator(): void
    {
        $this->withoutVite();
        $category = ProductCategory::where('slug', 'oberehy-dlia-domu')->firstOrFail();
        $subcategory = $category->children()->where('slug', 'oberehy-dlia-domu-motanky')->firstOrFail();
        for ($i = 1; $i <= 13; $i++) {
            $this->product($subcategory, 'seo-motanka-copy-'.$i, 'Мотанка №'.$i);
        }

        $paragraphs = config('shop_catalogue_copy.categories.'.$category->slug);
        $this->assertNotEmpty($paragraphs);
        $this->get($category->publicUrl())->assertOk()->assertSeeInOrder([
            'aria-label="Сторінки крамниці"',
            'id="shop-catalogue-copy-title"',
            ...array_map(fn (string $paragraph) => e($paragraph), $paragraphs),
            'Читати повністю',
        ], false)->assertSee('aria-expanded="true" aria-controls="shop-catalogue-copy-text" hidden', false);

        $this->get($subcategory->publicUrl())->assertOk()
            ->assertSee(config('shop_catalogue_copy.categories.'.$subcategory->slug.'.0'))
            ->assertDontSee($paragraphs[0]);
        $this->get('/kramnychka')->assertOk()->assertSee(config('shop_catalogue_copy.catalogue.0'));
        $this->get($category->publicUrl().'?page=2')->assertOk()->assertDontSee('id="shop-catalogue-copy-title"', false);
        $this->get($category->publicUrl().'?price_from=100')->assertOk()->assertDontSee('id="shop-catalogue-copy-title"', false);
    }

    private function product(ProductCategory $category, string $slug, string $name, bool $active = true): Product
    {
        return Product::create([
            'product_category_id' => $category->id,
            'slug' => $slug,
            'name' => $name,
            'price_minor' => 12500,
            'stock' => 1,
            'is_active' => $active,
        ]);
    }
}
