<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout;
use App\Services\MakerTelegram;
use App\Services\WayForPay;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

use function Illuminate\Support\defer;

class MakerTelegramTest extends TestCase
{
    use RefreshDatabase;

    private const MAKER_API = 'https://api.telegram.org/bot876543:makers_TEST/';
    private const ADMIN_API = 'https://api.telegram.org/bot123456:admin_TEST/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://ridna.example.test',
            'services.telegram.bot_token' => '123456:admin_TEST',
            'services.telegram.chat_id' => '424242',
            'services.maker_telegram.bot_token' => '876543:makers_TEST',
            'services.maker_telegram.bot_username' => 'ridna_makers_test_bot',
            'shop.wayforpay_account' => 'test_store',
            'shop.wayforpay_secret' => 'test-secret',
        ]);

        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_bot_setup_checks_the_username_and_rejects_the_admin_token(): void
    {
        $this->fakeTelegram([self::MAKER_API.'getMe' => Http::response([
            'ok' => true,
            'result' => ['is_bot' => true, 'username' => 'ridna_makers_test_bot'],
        ])]);

        $this->artisan('telegram:makers', ['--check' => true])->assertSuccessful();

        config(['services.maker_telegram.bot_username' => 'another_makers_test_bot']);
        $this->artisan('telegram:makers', ['--check' => true])->assertFailed();

        config(['services.maker_telegram.bot_token' => config('services.telegram.bot_token')]);
        $this->assertFalse(app(MakerTelegram::class)->configured());
    }

    public function test_pairing_requires_a_private_chat_and_cannot_be_reused_or_steal_another_makers_chat(): void
    {
        $maker = User::factory()->create(['role' => 'maker']);
        $other = User::factory()->create(['role' => 'maker']);
        $token = $this->pairingToken($maker);
        $otherToken = $this->pairingToken($other);

        $this->assertDatabaseHas('maker_telegram_links', [
            'user_id' => $maker->id,
            'pairing_hash' => hash('sha256', $token),
            'chat_id' => null,
        ]);

        $updates = Http::sequence()
            ->push(['ok' => true, 'result' => [$this->startUpdate(100, $token, '111111', 'group')]])
            ->push(['ok' => true, 'result' => [$this->startUpdate(101, $token, '111111')]])
            ->push(['ok' => true, 'result' => [
                $this->startUpdate(102, $token, '222222'),
                $this->startUpdate(103, $otherToken, '111111'),
            ]]);
        $this->fakeTelegram([
            self::MAKER_API.'getUpdates' => fn (Request $request) => $request['limit'] === 1
                ? Http::response(['ok' => true, 'result' => []])
                : $updates($request),
        ]);

        $this->artisan('telegram:makers')->assertSuccessful();
        $this->assertDatabaseHas('maker_telegram_links', ['user_id' => $maker->id, 'chat_id' => null]);
        $this->assertCount(0, $this->makerMessages());

        $this->artisan('telegram:makers')->assertSuccessful();
        $this->assertDatabaseHas('maker_telegram_links', [
            'user_id' => $maker->id, 'chat_id' => '111111', 'pairing_hash' => null, 'pairing_expires_at' => null,
        ]);
        $this->assertSame('111111', (string) $this->makerMessages()->sole()['chat_id']);

        $this->artisan('telegram:makers')->assertSuccessful();
        $this->assertDatabaseHas('maker_telegram_links', ['user_id' => $maker->id, 'chat_id' => '111111']);
        $this->assertDatabaseMissing('maker_telegram_links', ['chat_id' => '222222']);
        $this->assertDatabaseMissing('maker_telegram_links', ['user_id' => $other->id, 'chat_id' => '111111']);
        Http::assertSent(fn (Request $request) => $request->url() === self::MAKER_API.'getUpdates'
            && $request['offset'] === 102 && $request['limit'] === 10);
        Http::assertSent(fn (Request $request) => $request->url() === self::MAKER_API.'getUpdates'
            && $request['offset'] === 104 && $request['limit'] === 1);
    }

    public function test_expired_pairing_cannot_link_a_chat(): void
    {
        $maker = User::factory()->create(['role' => 'maker']);
        $token = $this->pairingToken($maker);
        $this->travel(16)->minutes();

        $this->fakeTelegram([
            self::MAKER_API.'getUpdates' => fn (Request $request) => Http::response([
                'ok' => true,
                'result' => $request['limit'] === 1 ? [] : [$this->startUpdate(100, $token, '111111')],
            ]),
        ]);
        $this->artisan('telegram:makers')->assertSuccessful();
        $this->assertDatabaseMissing('maker_telegram_links', ['chat_id' => '111111']);
    }

    public function test_scheduler_polls_both_telegram_bots_in_the_current_process(): void
    {
        $this->fakeTelegram();

        $this->artisan('schedule:run')->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->url() === self::MAKER_API.'getUpdates');
        Http::assertSent(fn (Request $request) => $request->url() === self::ADMIN_API.'getUpdates');
        Http::assertSentCount(2);
    }

    public function test_a_cash_on_delivery_order_alerts_each_eligible_linked_maker_with_their_items_and_shipping_details(): void
    {
        $this->fakeTelegram();

        $maker = User::factory()->create(['role' => 'maker']);
        $other = User::factory()->create(['role' => 'maker']);
        $unlinked = User::factory()->create(['role' => 'maker']);
        $inactive = User::factory()->create(['role' => 'maker', 'is_active' => false]);
        $this->linkMaker($maker, '111111');
        $this->linkMaker($other, '222222');
        $this->linkMaker($inactive, '333333');
        $first = $this->product('Миска <глиняна>', $maker, 12500);
        $second = $this->product('Чашка', $maker, 25000);
        $third = $this->product('Ткана крайка', $other, 45000);
        $fourth = $this->product('Ручна лялька', $unlinked, 30000);
        $inactiveItem = $this->product('Виріб неактивного майстра', $inactive, 33000);
        $shopItem = $this->product('Книга крамниці', null, 96000);
        $cart = [$first->id => 2, $second->id => 1, $third->id => 3, $fourth->id => 1, $inactiveItem->id => 1, $shopItem->id => 1];
        $token = (string) Str::uuid();
        $order = $this->createOrder('cod', $cart, $token);
        $this->assertSame($order->id, $this->createOrder('cod', $cart, $token)->id);

        $this->assertDatabaseCount('orders', 1);
        Http::assertNothingSent();
        defer()->invoke();

        $this->assertCount(2, $this->makerMessages());
        $this->assertCount(1, $this->messages(self::ADMIN_API));
        $messages = $this->makerMessages()->keyBy(fn (Request $request) => (string) $request['chat_id']);
        $link = 'https://ridna.example.test'.route('maker.order', ['order' => $order->reference], false);
        foreach ($messages as $message) {
            $this->assertSame('HTML', $message['parse_mode']);
            $this->assertTrue($message['protect_content']);
            $this->assertStringContainsString($order->displayNumber(), $message['text']);
            $this->assertStringContainsString($link, $message['text']);
            $this->assertShippingDetails($order, $message['text']);
            foreach ([
                $order->email, $order->comment, $order->access_token, $token,
                $fourth->name, $inactiveItem->name, $shopItem->name, Money::display($order->total_minor),
            ] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $message['text']);
            }
        }

        $firstMessage = $messages['111111']['text'];
        $this->assertStringContainsString('Миска &lt;глиняна&gt;', $firstMessage);
        $this->assertStringContainsString($second->name, $firstMessage);
        $this->assertStringContainsString(Money::display(50000), $firstMessage);
        $this->assertStringNotContainsString($third->name, $firstMessage);
        $secondMessage = $messages['222222']['text'];
        $this->assertStringContainsString($third->name, $secondMessage);
        $this->assertStringContainsString(Money::display(135000), $secondMessage);
        $this->assertStringNotContainsString($second->name, $secondMessage);

        $this->artisan('telegram:alerts')->assertSuccessful();
        $this->artisan('telegram:makers')->assertSuccessful();
        $this->assertCount(2, $this->makerMessages());
        $this->assertCount(1, $this->messages(self::ADMIN_API));
    }

    public function test_an_online_order_alerts_the_maker_after_verified_payment_only_once(): void
    {
        $this->fakeTelegram();
        $maker = User::factory()->create(['role' => 'maker']);
        $this->linkMaker($maker, '111111');
        $product = $this->product('Миска', $maker, 12500);

        $order = $this->createOrder('wayforpay', [$product->id => 2]);
        defer()->invoke();
        $this->assertCount(0, $this->makerMessages());
        $this->assertCount(1, $this->messages(self::ADMIN_API));

        $payment = [
            'merchantAccount' => 'test_store',
            'orderReference' => $order->reference,
            'amount' => Money::decimal($order->total_minor),
            'currency' => 'UAH',
            'authCode' => '123',
            'cardPan' => '42****4242',
            'transactionStatus' => 'Approved',
            'reasonCode' => 1100,
        ];
        $this->postJson(route('shop.webhook'), [...$payment, 'merchantSignature' => 'invalid'])->assertForbidden();
        $this->assertCount(0, $this->makerMessages());

        $payment['merchantSignature'] = app(WayForPay::class)->sign(array_values($payment));
        $this->postJson(route('shop.webhook'), $payment)->assertOk();
        $this->postJson(route('shop.webhook'), $payment)->assertOk();

        $alert = $this->makerMessages()->sole();
        $this->assertSame('111111', (string) $alert['chat_id']);
        $this->assertStringContainsString($order->displayNumber(), $alert['text']);
        $this->assertShippingDetails($order, $alert['text']);
        foreach ([$order->email, $order->comment, $payment['cardPan'], $payment['merchantSignature']] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $alert['text']);
        }
        $this->assertCount(2, $this->messages(self::ADMIN_API));
    }

    public function test_failed_maker_delivery_is_retried_by_the_separate_bot_with_the_same_recipient(): void
    {
        $this->fakeTelegram([self::MAKER_API.'sendMessage' => Http::sequence()
            ->push(['ok' => false, 'parameters' => ['retry_after' => 90]], 429)
            ->push(['ok' => true])]);
        $maker = User::factory()->create(['role' => 'maker']);
        $this->linkMaker($maker, '111111');
        $product = $this->product('Миска', $maker, 12500);

        $this->createOrder('cod', [$product->id => 1]);
        defer()->invoke();
        $this->assertCount(1, $this->makerMessages());

        $this->travel(91)->seconds();
        $this->artisan('telegram:alerts')->assertSuccessful();
        $this->assertCount(1, $this->makerMessages());

        $this->artisan('telegram:makers')->assertSuccessful();
        $this->artisan('telegram:makers')->assertSuccessful();
        $this->assertCount(2, $this->makerMessages());
        foreach ($this->makerMessages() as $message) {
            $this->assertSame('111111', (string) $message['chat_id']);
        }
        $this->assertCount(1, $this->messages(self::ADMIN_API));
    }

    public function test_disconnect_does_not_send_a_pending_alert_to_a_relinked_account(): void
    {
        $this->fakeTelegram([self::MAKER_API.'sendMessage' => Http::sequence()
            ->push(['ok' => false], 503)
            ->push(['ok' => true])]);
        $maker = User::factory()->create(['role' => 'maker']);
        $this->linkMaker($maker, '111111');
        $product = $this->product('Миска', $maker, 12500);

        $this->createOrder('cod', [$product->id => 1]);
        defer()->invoke();
        $this->assertCount(1, $this->makerMessages());

        app(MakerTelegram::class)->disconnect($maker);
        $this->linkMaker($maker, '222222');
        $this->travel(2)->minutes();
        $this->artisan('telegram:makers')->assertSuccessful();
        $this->assertCount(1, $this->makerMessages());
    }

    private function fakeTelegram(array $overrides = []): void
    {
        Http::fake($overrides + [
            self::MAKER_API.'sendMessage' => Http::response(['ok' => true]),
            self::MAKER_API.'getUpdates' => Http::response(['ok' => true, 'result' => []]),
            self::ADMIN_API.'sendMessage' => Http::response(['ok' => true]),
            self::ADMIN_API.'getUpdates' => Http::response(['ok' => true, 'result' => []]),
        ]);
    }

    private function pairingToken(User $maker): string
    {
        $url = app(MakerTelegram::class)->connectUrl($maker->fresh());
        $this->assertStringStartsWith('https://t.me/ridna_makers_test_bot?start=', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_-]{32,64}$/D', $query['start']);

        return $query['start'];
    }

    private function startUpdate(int $updateId, string $token, string $chatId, string $type = 'private'): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'chat' => ['id' => (int) $chatId, 'type' => $type],
                'from' => ['id' => (int) $chatId, 'is_bot' => false],
                'text' => '/start '.$token,
            ],
        ];
    }

    private function linkMaker(User $maker, string $chatId): void
    {
        DB::table('maker_telegram_links')->insert(['user_id' => $maker->id, 'chat_id' => $chatId]);
    }

    private function product(string $name, ?User $maker, int $price): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => Str::slug($name),
            'stock' => 10,
            'is_active' => true,
            'price_minor' => $price,
            'maker_id' => $maker?->id,
            'maker_price_minor' => $maker ? $price : null,
        ]);
    }

    private function createOrder(string $paymentMethod, array $cart, ?string $token = null): Order
    {
        return app(Checkout::class)->create([
            'customer_name' => 'Покупець <Тестовий>',
            'email' => 'customer@example.test',
            'phone' => '+380671234567',
            'delivery_method' => 'nova_poshta',
            'delivery_address' => 'Дніпро — Поштомат №123 & вхід 2',
            'comment' => 'Домофон недоступний',
            'payment_method' => $paymentMethod,
        ], $cart, $token ?? (string) Str::uuid());
    }

    private function assertShippingDetails(Order $order, string $text): void
    {
        foreach ([
            'Отримувач' => $order->customer_name,
            'Телефон' => $order->phone,
            'Доставка' => 'Нова пошта',
            'Відділення або адреса' => $order->delivery_address,
        ] as $label => $value) {
            $this->assertStringContainsString('<b>'.$label.': </b>'.e($value), $text);
        }
        $this->assertStringNotContainsString($order->customer_name, $text);
        $this->assertStringNotContainsString($order->delivery_address, $text);
    }

    private function makerMessages(): Collection
    {
        return $this->messages(self::MAKER_API);
    }

    private function messages(string $botApi): Collection
    {
        return Http::recorded(fn (Request $request) => $request->url() === $botApi.'sendMessage')
            ->map(fn (array $exchange) => $exchange[0]);
    }
}
