<?php

namespace Tests\Feature;

use App\Filament\Resources\CommunityResource\Pages\ManageCommunities;
use App\Models\ContentPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommunitiesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        ContentPage::where('section', 'communities')->forceDelete();
    }

    private function community(string $slug, array $attributes = []): ContentPage
    {
        return ContentPage::create([...[
            'path' => '/hromady/'.$slug, 'section' => 'communities', 'title' => 'Громада '.$slug,
            'body' => '<h2>Про нашу громаду</h2><p>Текст громади.</p>', 'editor_mode' => 'reading',
            'is_published' => true, 'community' => ['type' => 'community', 'city' => 'Дніпро', 'featured' => true, 'position' => 100, 'person' => 'Провідник громади', 'phones' => ['093 123 45 67'], 'emails' => ['community@example.com']],
        ], ...$attributes]);
    }

    public function test_profiles_catalogue_seo_and_visibility(): void
    {
        $page = $this->community('prava');
        $this->community('draft', ['is_published' => false]);
        $this->community('future', ['published_at' => now()->addDay()]);
        $this->community('deleted')->delete();
        $this->get('/hromady')->assertOk()->assertSee('Громада prava')->assertDontSee('Громада draft')->assertDontSee('Громада future')->assertDontSee('Громада deleted');
        $this->get($page->path)->assertOk()->assertSee('Текст громади.')->assertSee('Провідник громади')->assertSee('tel:0931234567', false)->assertSee('mailto:community@example.com', false)->assertSee('og:title')->assertSee('BreadcrumbList');
        foreach (['draft', 'future', 'deleted', 'missing'] as $slug) {
            $this->get('/hromady/'.$slug)->assertNotFound();
        }
        $this->get('/sitemap.xml')->assertOk()->assertSee('/hromady/prava')->assertDontSee('/hromady/draft')->assertDontSee('/hromady/future')->assertDontSee('/hromady/deleted');
    }

    public function test_home_selection_order_and_legacy_saved_layout_refresh(): void
    {
        $this->community('second', ['community' => ['city' => 'Дніпро', 'featured' => true, 'position' => 20]]);
        $first = $this->community('first', ['community' => ['city' => 'Київ', 'featured' => true, 'position' => 10]]);
        $this->community('hidden', ['community' => ['featured' => false, 'position' => 0]]);
        $this->community('unpublished', ['is_published' => false]);
        $this->get('/')->assertOk()->assertSeeInOrder(['Громада first', 'Громада second'])->assertDontSee('Громада hidden')->assertDontSee('Громада unpublished')->assertSee('/zviazok#communities-map-title')->assertSee('sacred-grove-1600.webp')->assertDontSee('language-switch');
        ContentPage::create(['path' => '/', 'section' => 'pages', 'title' => 'Наш центр', 'editor_mode' => 'layout', 'is_published' => true,
            'body' => '<section><div class="communities-section__background"></div><h2>Збережений текст власника</h2><div class="communities-grid"><a href="#">СТАРА КАРТКА</a></div><a href="/zviazok#communities-map-title">Всі громади</a></section>']);
        $this->get('/')->assertOk()->assertSee('Збережений текст власника')->assertSeeInOrder(['Громада first', 'Громада second'])->assertDontSee('СТАРА КАРТКА')->assertSee('sacred-grove-1600.webp');
        $first->update(['community' => ['featured' => false, 'position' => 10]]);
        $this->get('/')->assertDontSee('Громада first')->assertSee('Громада second');
    }

    public function test_editor_can_manage_selection_and_manager_cannot(): void
    {
        $page = $this->community('editable');
        $editor = User::factory()->create();
        $editor->forceFill(['role' => 'editor', 'is_active' => true])->save();
        $this->actingAs($editor);
        Livewire::test(ManageCommunities::class)->mountTableAction('edit', $page)->setTableActionData([
            'community.featured' => false, 'community.position' => 7,
        ])->callMountedTableAction()->assertHasNoTableActionErrors();
        $this->assertFalse($page->fresh()->community['featured']);
        $this->assertSame(7, $page->fresh()->community['position']);
        $this->get('/admin/community-pages')->assertOk();
        $manager = User::factory()->create();
        $manager->forceFill(['role' => 'shop_manager', 'is_active' => true])->save();
        $this->actingAs($manager)->get('/admin/community-pages')->assertForbidden();
    }

    public function test_editor_can_create_a_complete_profile_through_admin(): void
    {
        $editor = User::factory()->create();
        $editor->forceFill(['role' => 'editor', 'is_active' => true])->save();
        $this->actingAs($editor);
        Livewire::test(ManageCommunities::class)->callAction('create', data: [
            'title' => 'Нове коло', 'path' => '/hromady/nove-kolo', 'section' => 'communities',
            'editor_mode' => 'reading', 'body' => '<p>Опис нової громади.</p>', 'is_published' => true,
            'community' => ['type' => 'community', 'city' => 'Київ', 'featured' => true, 'position' => 5, 'phones' => ['050 123 45 67']],
        ])->assertHasNoActionErrors();
        $this->get('/hromady/nove-kolo')->assertOk()->assertSee('Опис нової громади.')->assertSee('050 123 45 67');
        $this->get('/')->assertSee('Нове коло');
    }
}
