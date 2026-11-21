<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ManageProducts;
use App\Mail\ContactFormMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\TelegramAlerts;
use App\Services\WayForPay;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

use function Illuminate\Support\defer;

class TelegramAlertsTest extends TestCase
{
    use RefreshDatabase;

    private const TELEGRAM = 'https://api.telegram.org/bot123456:abc_DE-f/sendMessage';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.bot_token' => '123456:abc_DE-f',
            'services.telegram.chat_id' => '424242',
            'app.url' => 'https://ridna.example.test',
            'shop.wayforpay_account' => 'test_store',
            'shop.wayforpay_secret' => 'test-secret',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            self::TELEGRAM => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
            'https://api.telegram.org/bot123456:abc_DE-f/getUpdates*' => Http::response(['ok' => true, 'result' => []]),
        ]);
        Mail::fake();
    }

    public function test_checkout_and_verified_payment_deliver_separate_alerts_once(): void
    {
        $product = Product::create([
            'name' => 'Книжка', 'slug' => 'knyzhka', 'price_minor' => 12550, 'stock' => 5, 'is_active' => true,
        ]);
        $token = (string) Str::uuid();
        $customer = [
            'customer_name' => 'Ім’я <покупця>',
            'email' => 'customer@example.test',
            'phone' => '+380671234567',
            'delivery_method' => 'nova_poshta',
            'delivery_address' => 'Дніпро — Поштомат №1 & вхід 2',
            'comment' => 'Зателефонуйте після 16:00 & перед відправкою',
            'checkout_token' => $token,
            'consent' => '1',
        ];
        $this->withSession(['cart' => [$product->id => 2], 'checkout_token' => $token])
            ->post(route('shop.checkout'), $customer)->assertRedirect();
        $this->post(route('shop.checkout'), $customer)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('telegram_alerts', 1);
        Http::assertSentCount(1);
        $this->assertCount(1, $this->sentMessages());

        $order = Order::sole();
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
        $this->assertDatabaseCount('telegram_alerts', 1);
        $payment['merchantSignature'] = app(WayForPay::class)->sign(array_values($payment));
        $this->postJson(route('shop.webhook'), $payment)->assertOk();
        $this->postJson(route('shop.webhook'), $payment)->assertOk();
        $this->assertDatabaseCount('telegram_alerts', 2);
        Http::assertSentCount(2);
        $this->assertCount(2, $this->sentMessages());

        $this->artisan('telegram:alerts')->assertSuccessful();
        $messages = $this->sentMessages();
        $this->assertCount(2, $messages);
        foreach ($messages as $message) {
            $this->assertEquals('424242', $message['chat_id']);
            $this->assertSame('HTML', $message['parse_mode']);
            $this->assertTrue($message['protect_content']);
            $this->assertStringContainsString($order->displayNumber(), $message['text']);
            $this->assertStringNotContainsString($order->reference, $message['text']);
            $this->assertStringContainsString('251,00', $message['text']);
            $this->assertStringContainsString('<a href="https://ridna.example.test/admin/orders?search='.$order->displayNumber().'"', $message['text']);
            foreach (['Ім’я &lt;покупця&gt;', $customer['phone'], 'Нова пошта', e($customer['delivery_address'])] as $detail) {
                $this->assertStringContainsString($detail, $message['text']);
            }
            $this->assertStringNotContainsString($customer['customer_name'], $message['text']);
            $this->assertStringNotContainsString($customer['delivery_address'], $message['text']);
            foreach ([$order->access_token, $token, $payment['cardPan'], $payment['merchantSignature']] as $secret) {
                $this->assertStringNotContainsString($secret, $message['text']);
            }
        }

        $newOrder = $messages->first()['text'];
        foreach ([$customer['email'], '16:00 &amp; перед відправкою', $product->name] as $detail) {
            $this->assertStringContainsString($detail, $newOrder);
        }
    }

    public function test_valid_maker_submission_and_previously_published_edit_send_moderation_alerts_immediately(): void
    {
        Storage::fake('public');
        $maker = User::factory()->create(['role' => 'maker', 'name' => 'Майстриня', 'email' => 'maker@example.test']);
        $details = ['name' => 'Глиняна миска', 'description' => 'Опис <виробу> & догляд', 'price' => '100', 'stock' => 2];

        $this->actingAs($maker)->post(route('maker.products.store'), $details)->assertSessionHasErrors('images');
        $this->assertDatabaseCount('telegram_alerts', 0);
        $this->post(route('maker.products.store'), [
            ...$details,
            'images' => [UploadedFile::fake()->image('bowl.jpg')],
        ])->assertRedirect(route('maker.index'));
        $this->assertDatabaseCount('telegram_alerts', 1);
        $alert = $this->sentMessages()->sole()['text'];
        foreach ([$details['name'], $maker->name, $maker->email, 'Опис &lt;виробу&gt; &amp; догляд', '100,00', '138,00'] as $detail) {
            $this->assertStringContainsString($detail, $alert);
        }

        $product = Product::sole();
        $update = [...$details, 'name' => 'Миска з розписом', 'stock_before' => 2];
        $this->put(route('maker.products.update', $product), $update)->assertRedirect(route('maker.index'));
        $this->assertDatabaseCount('telegram_alerts', 1);

        $product->update(['is_active' => true]);
        $this->put(route('maker.products.update', $product), $update)->assertRedirect(route('maker.index'));
        $this->assertDatabaseCount('telegram_alerts', 2);
        Http::assertSentCount(2);
        $messages = $this->sentMessages();
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('Миска з розписом', $messages->last()['text']);
    }

    public function test_new_product_in_the_admin_panel_also_sends_an_alert_after_the_response(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Адміністратор']);
        $this->actingAs($admin);
        Livewire::test(ManageProducts::class)->callAction('create', data: [
            'name' => 'Крамнична книга', 'slug' => 'kramnychna-knyha', 'price_minor' => '275.50', 'stock' => 3,
        ])->assertHasNoActionErrors();

        $this->assertDatabaseCount('telegram_alerts', 1);
        defer()->invoke();
        $message = $this->sentMessages()->sole()['text'];
        $this->assertStringContainsString('Новий товар додано в адмінці', $message);
        $this->assertStringContainsString('Крамнична книга', $message);
        $this->assertStringContainsString('275,50', $message);
    }

    public function test_contact_alert_preserves_long_messages_with_safe_html(): void
    {
        $contact = [
            'name' => 'Ім’я автора',
            'email' => 'sender@example.test',
            'phone' => '+380673456789',
            'city' => 'Дніпро',
            'organization' => 'Організація',
            'topic' => 'cooperation',
            'message' => 'Початок <звернення> & '.str_repeat('🌻', 3500).' Кінець звернення.',
            'consent' => '1',
        ];
        $this->postJson(route('contact.submit'), [...$contact, 'website' => 'bot.example.test'])->assertOk();
        $this->postJson(route('contact.submit'), [...$contact, 'message' => ''])->assertSessionHasErrors('message');
        Mail::assertNothingSent();
        $this->assertDatabaseCount('telegram_alerts', 0);

        $this->postJson(route('contact.submit'), $contact)->assertOk();
        Mail::assertSent(ContactFormMessage::class, 1);
        $this->assertSame(0, DB::table('telegram_alerts')->whereNotNull('message')->count());
        $messages = $this->sentMessages();
        $this->assertGreaterThan(1, $messages->count());
        Http::assertSentCount($messages->count());
        $combined = $messages->pluck('text')->implode('');
        foreach (['Співпраця', 'Початок &lt;звернення&gt; &amp;', 'Кінець звернення.', $contact['email'],
            $contact['name'], $contact['phone'], $contact['city'], $contact['organization']] as $detail) {
            $this->assertStringContainsString($detail, $combined);
        }
        $this->assertSame(3500, substr_count($combined, '🌻'));

        foreach ($messages as $message) {
            $decoded = html_entity_decode(strip_tags($message['text']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $this->assertLessThanOrEqual(4096, strlen(mb_convert_encoding($decoded, 'UTF-16LE', 'UTF-8')) / 2);
        }
    }

    public function test_contact_form_keeps_working_without_telegram_configuration(): void
    {
        config(['services.telegram.bot_token' => null, 'services.telegram.chat_id' => null]);
        $this->postJson(route('contact.submit'), [
            'name' => 'Ім’я автора', 'email' => 'sender@example.test', 'topic' => 'other',
            'message' => 'Тестове звернення через форму сайту.', 'consent' => '1',
        ])->assertOk();

        Mail::assertSent(ContactFormMessage::class, 1);
        $this->assertDatabaseCount('telegram_alerts', 0);
        Http::assertNothingSent();
    }

    public function test_delayed_telegram_response_reschedules_the_alert(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            self::TELEGRAM => Http::sequence()
                ->push(['ok' => false, 'parameters' => ['retry_after' => 90]], 429)
                ->push(['ok' => true]),
            'https://api.telegram.org/bot123456:abc_DE-f/getUpdates' => Http::response(['ok' => true, 'result' => []]),
        ]);
        app(TelegramAlerts::class)->enqueue('test:retry', 'Виріб готовий до модерації', ['Пошта' => 'maker@example.test']);
        Http::assertNothingSent();
        $this->assertStringNotContainsString('maker@example.test', DB::table('telegram_alerts')->value('message'));
        defer()->invoke();
        $this->assertCount(1, $this->sentMessages());

        $this->artisan('telegram:alerts')->assertSuccessful();
        $this->assertCount(1, $this->sentMessages());
        $this->assertDatabaseHas('telegram_alerts', ['event_key' => 'test:retry', 'attempts' => 1, 'sent_at' => null]);

        $this->travel(91)->seconds();
        $this->artisan('telegram:alerts')->assertSuccessful();
        $this->assertCount(2, $this->sentMessages());
        $this->assertDatabaseHas('telegram_alerts', ['event_key' => 'test:retry', 'attempts' => 2, 'message' => null]);
    }

    public function test_rolled_back_alert_is_not_scheduled_for_delivery(): void
    {
        DB::beginTransaction();
        app(TelegramAlerts::class)->enqueue('test:rollback', 'Виріб на модерації');
        DB::rollBack();

        defer()->invoke();
        $this->assertDatabaseMissing('telegram_alerts', ['event_key' => 'test:rollback']);
        Http::assertNothingSent();
    }

    public function test_bot_answers_commands_only_in_the_configured_private_chat(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake([
            self::TELEGRAM => Http::response(['ok' => true]),
            'https://api.telegram.org/bot123456:abc_DE-f/getUpdates' => Http::sequence()
                ->push(['ok' => true, 'result' => [
                    ['update_id' => 100, 'message' => ['chat' => ['type' => 'private', 'id' => 100], 'text' => '/start']],
                    ['update_id' => 101, 'message' => ['chat' => ['type' => 'group', 'id' => -100], 'text' => '/help']],
                    ['update_id' => 102, 'message' => ['chat' => ['type' => 'private', 'id' => 424242], 'text' => '/status']],
                ]])->push(['ok' => true, 'result' => []]),
        ]);

        $this->artisan('telegram:alerts')->assertSuccessful();
        $this->artisan('telegram:alerts')->assertSuccessful();
        $message = $this->sentMessages()->sole();
        $this->assertSame('424242', $message['chat_id']);
        $this->assertStringContainsString('Бот підключено', $message['text']);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/getUpdates') && $request['offset'] === 103);
    }

    private function sentMessages(): Collection
    {
        return Http::recorded(fn (Request $request) => $request->url() === self::TELEGRAM)->map(fn (array $exchange) => $exchange[0]);
    }
}
