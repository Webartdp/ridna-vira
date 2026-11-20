<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\Product;
use App\Support\ShopPolicies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPolicyPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_drafts_are_editable_records_and_absent_from_public_pages_and_sitemap(): void
    {
        $this->withoutVite();
        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $home = $this->get('/')->assertOk()->assertDontSee('aria-label="Документи крамниці"', false);

        foreach (array_keys(ShopPolicies::LINKS) as $path) {
            $page = ContentPage::where('path', $path)->sole();
            $this->assertSame('pages', $page->section);
            $this->assertSame('reading', $page->editor_mode);
            $this->assertFalse($page->visible());
            $this->assertNotEmpty($page->body);
            $this->get($path)->assertNotFound();
            $sitemap->assertDontSee($path);
            $home->assertDontSee(url($path), false);
        }
    }

    public function test_approved_policy_appears_on_site_and_rerunning_migration_keeps_the_reviewed_text(): void
    {
        $this->withoutVite();
        $offer = ContentPage::where('path', '/publichna-oferta')->sole();
        $offer->update(['body' => '<h2>Затверджена редакція</h2><p>Тестовий текст опублікованої сторінки.</p>', 'is_published' => true]);
        $migration = require database_path('migrations/2026_11_20_000002_add_shop_policy_drafts.php');
        $migration->up();

        $this->assertTrue($offer->fresh()->visible());
        $this->get($offer->path)->assertOk()->assertSee('Затверджена редакція');
        $this->get('/')->assertOk()
            ->assertSee('aria-label="Документи крамниці"', false)
            ->assertSee(url($offer->path), false)
            ->assertDontSee(url('/umovy-spivpratsi-z-maistramy'), false);
        $this->get('/sitemap.xml')->assertOk()->assertSee($offer->path);

        $product = Product::create(['name' => 'Книга', 'slug' => 'knyha', 'price_minor' => 15000, 'stock' => 1, 'is_active' => true]);
        $this->withSession(['cart' => [$product->id => 1]])->get('/koshyk')
            ->assertOk()
            ->assertSee(url($offer->path), false)
            ->assertSee(url('/umovy-kramnytsi'), false)
            ->assertSee('name="consent"', false);
    }
}
