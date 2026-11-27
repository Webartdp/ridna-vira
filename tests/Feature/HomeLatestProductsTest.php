<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\Product;
use App\Services\ProductImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeLatestProductsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_home_links_to_four_latest_public_products_before_the_footer(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('cover.jpg', 1000, 1000)->store('products', 'public');
        $names = ['Стара дерев’яна ложка', 'Різьблена миска', 'Лляна крайка', 'Вишитий рушник', 'Ґердан з бісеру'];
        foreach (array_slice($names, 1, preserve_keys: true) as $index => $name) {
            $this->product($name, 'home-item-'.$index, $index === 4 ? ['images' => [$image]] : []);
        }
        $this->product($names[0], 'home-old', ['created_at' => '2026-11-22 12:00:00']);
        $this->product('Виріб на модерації', 'home-hidden', ['is_active' => false]);
        $this->product('Видалений виріб', 'home-deleted')->delete();

        $this->get('/')->assertOk()
            ->assertSeeInOrder(['id="home-latest-products-title"', $names[4], $names[3], $names[2], $names[1], '</main>', '<footer'], false)
            ->assertSee('/kramnychka/home-item-4')
            ->assertSee('Усі товари')
            ->assertSee('450,00 ₴')
            ->assertSee('srcset="'.Storage::disk('public')->url(ProductImages::variant($image, 400)).' 400w,', false)
            ->assertSee('/assets/brand/image-placeholder.svg')
            ->assertDontSee($names[0])
            ->assertDontSee('Виріб на модерації')
            ->assertDontSee('Видалений виріб');
    }

    public function test_cms_home_keeps_one_live_product_section_at_the_end(): void
    {
        $this->product('Різьблений гребінь', 'home-comb');
        $home = ContentPage::create([
            'path' => '/', 'section' => 'pages', 'title' => 'Головна сторінка', 'editor_mode' => 'layout',
            'is_published' => true, 'body' => '<section>[[live:home-latest-products]]<p>Опис від редактора</p></section>',
        ]);

        $html = $this->get('/')->assertOk()
            ->assertSeeInOrder(['Опис від редактора', 'id="home-latest-products-title"', 'Різьблений гребінь', '</main>', '<footer'], false)
            ->assertDontSee('[[live:home-latest-products]]')->getContent();
        $this->assertSame(1, substr_count($html, 'id="home-latest-products-title"'));

        $home->update(['editor_mode' => 'reading', 'body' => '<p>Окремий текст головної</p>']);
        $this->get('/')->assertOk()
            ->assertSeeInOrder(['Окремий текст головної', 'id="home-latest-products-title"', 'Різьблений гребінь', '</main>', '<footer'], false);
    }

    public function test_home_hides_the_section_when_no_products_are_public(): void
    {
        $this->product('Виріб на модерації', 'home-hidden', ['is_active' => false]);

        $this->get('/')->assertOk()->assertDontSee('id="home-latest-products-title"', false);
    }

    private function product(string $name, string $slug, array $attributes = []): Product
    {
        return Product::create([
            'name' => $name, 'slug' => $slug, 'price_minor' => 45000, 'is_active' => true,
            'created_at' => '2026-11-23 12:00:00', ...$attributes,
        ]);
    }
}
