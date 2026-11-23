<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\AdminTelegramWebhook;
use App\Services\TelegramAlerts;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminTelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.telegram.org/bot123456:admin_TEST/';

    private const ADMIN_CHAT = 424242;

    private const SITE_URL = 'https://ridna.example.test';

    private const PATH = '/telegram/admin/webhook';

    private const SECRET_HEADER = 'X-Telegram-Bot-Api-Secret-Token';

    private string $remoteWebhookUrl = '';

    private int $nextUpdateId = 1000;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => self::SITE_URL,
            'services.telegram.bot_token' => '123456:admin_TEST',
            'services.telegram.chat_id' => (string) self::ADMIN_CHAT,
        ]);
        $this->withoutVite();
        Http::preventStrayRequests();
        Http::fake([
            self::API.'getWebhookInfo' => fn () => Http::response(['ok' => true, 'result' => ['url' => $this->remoteWebhookUrl]]),
            self::API.'setWebhook' => function (Request $request) {
                $this->remoteWebhookUrl = $request['url'];

                return Http::response(['ok' => true, 'result' => true]);
            },
            self::API.'deleteWebhook' => function () {
                $this->remoteWebhookUrl = '';

                return Http::response(['ok' => true, 'result' => true]);
            },
            self::API.'sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 52]]),
            self::API.'answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
            self::API.'editMessageReplyMarkup' => Http::response(['ok' => true, 'result' => ['message_id' => 52]]),
        ]);
    }

    public function test_install_registers_a_stable_authenticated_webhook_without_dropping_updates_and_leaves_outgoing_alerts_working(): void
    {
        $secret = $this->installWebhook();
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]{1,256}$/D', $secret);
        $this->assertSame($secret, $this->installWebhook());
        $this->assertCount(2, $this->requests('getWebhookInfo'));

        foreach ($this->requests('setWebhook') as $request) {
            $this->assertSame(self::SITE_URL.self::PATH, $request['url']);
            $this->assertSame(['message', 'callback_query'], $request['allowed_updates']);
            $this->assertNotTrue($request['drop_pending_updates'] ?? false);
        }
        $this->assertTrue(app(AdminTelegramWebhook::class)->enabled());

        app(TelegramAlerts::class)->enqueue('test:admin-webhook', 'Вихідне сповіщення');
        $this->artisan('telegram:alerts')->assertSuccessful();

        $this->assertCount(0, $this->requests('getUpdates'));
        $this->assertStringContainsString('Вихідне сповіщення', $this->requests('sendMessage')->sole()['text']);
        $this->assertDatabaseHas('telegram_alerts', ['event_key' => 'test:admin-webhook', 'attempts' => 1, 'message' => null]);
    }

    public function test_install_leaves_another_apps_webhook_untouched(): void
    {
        $this->remoteWebhookUrl = 'https://other.example.test/telegram-hook';

        $this->artisan('telegram:alerts', ['--webhook' => true])->assertFailed();

        $this->assertCount(0, $this->requests('setWebhook'));
        $this->assertFalse(app(AdminTelegramWebhook::class)->enabled());
    }

    public function test_callback_requires_exact_secret_header_and_publishes_with_no_csrf_token(): void
    {
        $product = $this->pendingProduct();
        $update = $this->callbackUpdate($product);
        $secret = $this->installWebhook();

        $this->postJson(self::PATH, [...$update, 'secret_token' => $secret])->assertForbidden();
        $this->postJson(self::PATH, $update, [self::SECRET_HEADER => $secret.'x'])->assertForbidden();
        $this->assertContains(ltrim(self::PATH, '/'), app(PreventRequestForgery::class)->getExcludedPaths());
        $this->assertFalse($product->refresh()->is_active);
        $this->assertCount(0, $this->requests('answerCallbackQuery'));

        $this->postJson(self::PATH, $update, [self::SECRET_HEADER => $secret])->assertNoContent();
        $this->assertTrue($product->refresh()->is_active);
        $this->assertNull($product->moderation_token);
        $this->get(route('shop.product', $product))->assertOk();

        $answer = $this->requests('answerCallbackQuery')->sole();
        $this->assertSame($update['callback_query']['id'], $answer['callback_query_id']);
        $button = $this->requests('editMessageReplyMarkup')->sole()['reply_markup']['inline_keyboard'][0][0];
        $this->assertSame(self::SITE_URL.route('shop.product', $product, false), $button['url']);
        $this->assertArrayNotHasKey('callback_data', $button);

        $publishedAt = $product->updated_at->toDateTimeString();
        $this->travel(2)->seconds();
        $this->postJson(self::PATH, $update, [self::SECRET_HEADER => $secret])->assertNoContent();
        $this->assertSame($publishedAt, $product->fresh()->updated_at->toDateTimeString());
    }

    public function test_authentic_webhook_cannot_publish_an_old_revision_or_from_a_different_sender(): void
    {
        $product = $this->pendingProduct();
        $secret = $this->installWebhook();

        $this->postJson(self::PATH, $this->callbackUpdate($product, revision: str_repeat('x', 24)), [self::SECRET_HEADER => $secret])->assertNoContent();
        $this->postJson(self::PATH, $this->callbackUpdate($product, sender: 123456), [self::SECRET_HEADER => $secret])->assertNoContent();

        $this->assertFalse($product->refresh()->is_active);
        $this->assertNotNull($product->moderation_token);
        $this->assertCount(0, $this->requests('editMessageReplyMarkup'));
        $this->assertCount(2, $this->requests('answerCallbackQuery'));
    }

    public function test_switching_back_to_polling_preserves_pending_updates_and_revokes_webhook_access(): void
    {
        $secret = $this->installWebhook();
        $product = $this->pendingProduct();

        $this->artisan('telegram:alerts', ['--polling' => true])->assertSuccessful();
        $this->assertFalse(app(AdminTelegramWebhook::class)->enabled());
        $this->assertNotTrue($this->requests('deleteWebhook')->sole()['drop_pending_updates'] ?? false);
        $this->postJson(self::PATH, $this->callbackUpdate($product), [self::SECRET_HEADER => $secret])->assertForbidden();
        $this->assertFalse($product->refresh()->is_active);
    }

    private function installWebhook(): string
    {
        $this->artisan('telegram:alerts', ['--webhook' => true])->assertSuccessful();

        return $this->requests('setWebhook')->last()['secret_token'];
    }

    private function pendingProduct(): Product
    {
        $maker = User::factory()->create(['role' => 'maker', 'is_active' => true]);
        $product = new Product([
            'name' => 'Глиняна миска',
            'slug' => 'hlyniana-myska',
            'maker_id' => $maker->id,
            'price_minor' => 13800,
            'stock' => 2,
            'is_active' => false,
        ]);
        $product->moderation_token = str_repeat('t', 24);
        $product->save();

        return $product;
    }

    private function callbackUpdate(Product $product, int $sender = self::ADMIN_CHAT, ?string $revision = null): array
    {
        $updateId = $this->nextUpdateId++;

        return [
            'update_id' => $updateId,
            'callback_query' => [
                'id' => '22334455'.$updateId,
                'from' => ['id' => $sender, 'is_bot' => false],
                'message' => ['message_id' => 52, 'chat' => ['id' => self::ADMIN_CHAT, 'type' => 'private']],
                'data' => 'product:'.$product->id.':mod:'.($revision ?? $product->moderation_token),
            ],
        ];
    }

    private function requests(string $method): Collection
    {
        return Http::recorded(fn (Request $request) => $request->url() === self::API.$method)
            ->map(fn (array $exchange) => $exchange[0])->values();
    }
}
