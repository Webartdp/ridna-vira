<?php

namespace Tests\Feature;

use App\Models\ContentPage;
use App\Models\SiteMcpToken;
use App\Models\User;
use App\Services\McpContent;
use App\Services\ProductImages;
use App\Support\Cms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteMcpTest extends TestCase
{
    use RefreshDatabase;

    private string $plain;

    private SiteMcpToken $token;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        $this->withoutVite();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->plain = 'rv_mcp_'.bin2hex(random_bytes(32));
        $this->token = SiteMcpToken::create(['user_id' => $this->admin->id, 'name' => 'test', 'token_hash' => hash('sha256', $this->plain), 'scopes' => ['content:read', 'content:write', 'content:publish', 'media:write'], 'expires_at' => now()->addDay()]);
    }

    private function rpc(string $method, array $params = [])
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.$this->plain, 'Accept' => 'application/json', 'MCP-Protocol-Version' => '2025-06-18'])->postJson('http://localhost/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params]);
    }

    private function tool(string $name, array $arguments = [])
    {
        return $this->rpc('tools/call', ['name' => $name, 'arguments' => $arguments]);
    }

    private function page(array $attributes = []): ContentPage
    {
        return ContentPage::create(['path' => '/novyny/mcp-test', 'section' => 'news', 'title' => 'Тестова новина', 'body' => '<p>Текст новини для перевірки прямого підключення.</p>', 'editor_mode' => 'reading', 'is_published' => false, ...$attributes])->fresh();
    }

    public function test_authentication_requires_active_token_and_current_admin(): void
    {
        $payload = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'];
        $this->postJson('http://localhost/mcp', $payload)->assertUnauthorized();
        $this->actingAs($this->admin)->postJson('http://localhost/mcp', $payload)->assertUnauthorized();
        $this->token->update(['expires_at' => now()->subSecond()]);
        $this->rpc('tools/list')->assertUnauthorized();
        $this->token->update(['expires_at' => now()->addDay(), 'revoked_at' => now()]);
        $this->rpc('tools/list')->assertUnauthorized();
        $this->token->update(['revoked_at' => null]);
        $this->admin->forceFill(['is_admin' => false])->save();
        $this->rpc('tools/list')->assertUnauthorized();
    }

    public function test_real_http_handshake_and_tool_catalog_are_supported(): void
    {
        $this->rpc('initialize', ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass, 'clientInfo' => ['name' => 'test', 'version' => '1.0']])->assertOk()->assertJsonPath('result.protocolVersion', '2025-06-18');
        $response = $this->rpc('tools/list')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame((string) strlen($response->getContent()), $response->headers->get('Content-Length'));
        $tools = collect($response->json('result.tools'))->keyBy('name');
        $this->assertCount(7, $tools);
        $this->assertTrue($tools['get-content']['annotations']['readOnlyHint']);
        $this->assertTrue($tools['update-content']['annotations']['destructiveHint']);
        $this->assertContains('expected_version', $tools['update-content']['inputSchema']['required']);
        foreach (['sql', 'shell', 'exec', 'payment', 'users'] as $name) {
            $this->assertArrayNotHasKey($name, $tools);
        }
        $this->assertNotNull($this->token->fresh()->last_used_at);
    }

    public function test_read_update_publish_and_revision_audit_work_end_to_end(): void
    {
        $page = $this->page();
        $this->tool('list-content', ['section' => 'news', 'search' => 'Тестова'])->assertOk()->assertJsonPath('result.structuredContent.total', 1);
        $read = $this->tool('get-content', ['id' => $page->id])->assertOk()->json('result.structuredContent');
        $this->assertSame($page->body, $read['body_html']);
        $result = $this->tool('update-content', ['id' => $page->id, 'expected_version' => $read['version'], 'title' => 'Оновлена новина', 'body_html' => '<p>Оновлений текст.</p><script>alert(1)</script>', 'seo_title' => 'Новина для пошуку', 'is_published' => true])->assertOk()->assertJsonPath('result.isError', false)->json('result.structuredContent');
        $this->assertFalse($page->fresh()->is_published);
        $this->assertStringNotContainsString('<script', $page->fresh()->body);
        $this->get($page->path)->assertNotFound();
        $this->tool('publish-content', ['id' => $page->id, 'expected_version' => $result['version'], 'is_published' => true])->assertOk()->assertJsonPath('result.isError', false);
        $this->get($page->path)->assertOk()->assertSee('<title>Новина для пошуку</title>', false)->assertSee('Оновлений текст.');
        $this->assertSame(2, $page->revisions()->count());
        $this->assertSame($this->admin->id, $page->revisions()->first()->user_id);
        $this->assertDatabaseCount('site_mcp_audits', 2);
        $this->tool('list-content-revisions', ['id' => $page->id])->assertOk()->assertJsonPath('result.structuredContent.revisions.0.title', 'Оновлена новина');
    }

    public function test_stale_versions_and_read_only_tokens_cannot_modify_content(): void
    {
        $page = $this->page();
        $old = McpContent::version($page);
        $page->update(['title' => 'Зміна з адмінки']);
        $this->tool('update-content', ['id' => $page->id, 'expected_version' => $old, 'title' => 'Затерта зміна'])->assertOk()->assertJsonPath('result.isError', true)->assertSee('CONFLICT');
        $this->assertSame('Зміна з адмінки', $page->fresh()->title);
        $this->token->update(['scopes' => ['content:read']]);
        $this->tool('get-content', ['id' => $page->id])->assertOk()->assertJsonPath('result.isError', false);
        foreach (['update-content' => ['title' => 'Нова'], 'publish-content' => ['is_published' => true], 'set-content-image' => ['clear' => true]] as $tool => $input) {
            $this->tool($tool, ['id' => $page->id, 'expected_version' => McpContent::version($page->fresh()), ...$input])->assertOk()->assertJsonPath('result.isError', true)->assertSee('Permission denied');
        }
        $this->assertDatabaseCount('site_mcp_audits', 0);
    }

    public function test_native_composed_page_uses_blocks_and_preserves_live_community_map(): void
    {
        $native = $this->get('/zviazok')->assertOk()->getContent();
        preg_match('~<main\b[^>]*>(.*?)</main>~s', $native, $main);
        [$body] = Cms::islands($main[1]);
        $page = $this->page(['path' => '/zviazok', 'section' => 'pages', 'editor_mode' => 'layout', 'body' => $body, 'is_published' => true]);
        $data = $this->tool('get-content', ['id' => $page->id])->json('result.structuredContent');
        $block = collect($data['blocks'])->first(fn ($block) => in_array($block['kind'], ['rich', 'paragraph']));
        $this->assertNotNull($block);
        $this->tool('update-content', ['id' => $page->id, 'expected_version' => $data['version'], 'body_html' => '<p>Не замінювати сторінку</p>'])->assertJsonPath('result.isError', true);
        $this->tool('update-content', ['id' => $page->id, 'expected_version' => $data['version'], 'blocks' => [['key' => $block['key'], 'value' => '<p>Оновлений вступ для зв’язку.</p>']]])->assertJsonPath('result.isError', false);
        $this->get('/zviazok')->assertOk()->assertSee('Оновлений вступ для зв’язку.')->assertSee('data-community-map', false)->assertSee('Мапа Рідної Віри в Україні');
        $this->assertStringContainsString('[[live:', $page->fresh()->body);
    }

    public function test_images_are_replaced_optimized_cleared_and_not_downloaded_from_urls(): void
    {
        Storage::fake('public');
        $page = $this->page(['path' => '/ridna-vira/bohy/perun', 'section' => 'gods', 'is_published' => true]);
        $this->tool('get-content', ['id' => $page->id])->assertOk();
        $this->assertSame([], Storage::disk('public')->allFiles());
        $file = UploadedFile::fake()->image('photo.jpg', 900, 1200);
        $image = base64_encode(file_get_contents($file->getRealPath()));
        $uploaded = $this->tool('set-content-image', ['id' => $page->id, 'expected_version' => McpContent::version($page), 'image_base64' => $image])->assertOk();
        $this->assertFalse($uploaded->json('result.isError'), json_encode($uploaded->json()));
        $page->refresh();
        $this->assertNotNull($page->image);
        Storage::disk('public')->assertExists(ProductImages::variant($page->image, 800));
        $this->get('/ridna-vira/bohy')->assertOk()->assertSee('/storage/mcp/images/', false);
        $this->get($page->path)->assertOk()->assertSee('/storage/mcp/images/', false);
        $this->tool('set-content-image', ['id' => $page->id, 'expected_version' => McpContent::version($page), 'image_base64' => 'http://127.0.0.1/.env'])->assertJsonPath('result.isError', true);
        $this->tool('set-content-image', ['id' => $page->id, 'expected_version' => McpContent::version($page), 'clear' => true])->assertJsonPath('result.isError', false);
        $this->assertNull($page->fresh()->image);
        $this->get($page->path)->assertOk()->assertDontSee('/storage/mcp/images/', false);
    }

    public function test_creating_materials_always_creates_drafts_and_rejects_reserved_paths(): void
    {
        $this->tool('create-content', ['section' => 'news', 'path' => '/admin/hack', 'title' => 'Назва'])->assertJsonPath('result.isError', true);
        $this->tool('create-content', ['section' => 'news', 'path' => '/novyny/new-draft', 'title' => 'Нова чернетка', 'body_html' => '<p>Текст.</p>', 'is_published' => true])->assertJsonPath('result.isError', false)->assertJsonPath('result.structuredContent.is_published', false);
        $this->get('/novyny/new-draft')->assertNotFound();
        $this->assertDatabaseCount('site_mcp_audits', 1);
        $this->tool('create-content', ['section' => 'news', 'path' => '/novyny/new-draft', 'title' => 'Повтор'])->assertJsonPath('result.isError', true);
    }

    public function test_seo_only_update_preserves_layout_and_status_without_exposing_account_data(): void
    {
        $page = $this->page(['path' => '/pro-tsentr', 'section' => 'pages', 'editor_mode' => 'layout', 'is_published' => true, 'body' => '<section class="about"><h1>Про центр</h1><p>Наш світогляд і цінності.</p>[[live:community]]</section>']);
        $body = $page->body;
        $response = $this->tool('update-content', ['id' => $page->id, 'expected_version' => McpContent::version($page), 'seo_title' => 'Про духовний центр', 'image' => '../../.env', 'is_published' => false])->assertJsonPath('result.isError', false);
        $this->assertSame($body, $page->fresh()->body);
        $this->assertTrue($page->fresh()->is_published);
        $this->assertNull($page->fresh()->image);
        $response->assertDontSee('token_hash')->assertDontSee('password')->assertDontSee('../../.env');
    }

    public function test_token_command_displays_secret_once_stores_only_hash_and_revokes_access(): void
    {
        Artisan::call('site:mcp-token', ['email' => $this->admin->email, '--read-only' => true, '--days' => 7]);
        preg_match('/rv_mcp_[a-f0-9]{64}/', Artisan::output(), $match);
        $this->assertCount(1, $match);
        $token = SiteMcpToken::latest('id')->first();
        $this->assertSame(hash('sha256', $match[0]), $token->token_hash);
        $this->assertSame(['content:read'], $token->scopes);
        $this->assertStringNotContainsString($match[0], $token->toJson());
        Artisan::call('site:mcp-token', ['action' => 'list']);
        $this->assertStringNotContainsString($match[0], Artisan::output());
        $this->plain = $match[0];
        $this->rpc('tools/list')->assertOk();
        Artisan::call('site:mcp-token', ['action' => 'revoke', '--id' => $token->id]);
        $this->rpc('tools/list')->assertUnauthorized();
        $this->artisan('site:mcp-token', ['email' => 'missing@example.com'])->assertFailed();
    }

    public function test_origin_and_host_are_validated_and_mcp_is_not_in_sitemap(): void
    {
        $this->withHeader('Origin', 'https://evil.example')->rpc('tools/list')->assertForbidden();
        $this->withHeaders(['Origin' => 'http://localhost', 'Authorization' => 'Bearer '.$this->plain])->postJson('http://evil.example/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertStatus(400);
        $this->flushHeaders();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/mcp<', false);
    }
}
