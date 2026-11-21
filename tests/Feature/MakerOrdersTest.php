<?php

namespace Tests\Feature;

use App\Models\MakerTelegramLink;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MakerOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_orders_reveal_only_the_signed_in_makers_items_and_amounts(): void
    {
        $this->withoutVite();
        $maker = User::factory()->create(['role' => 'maker']);
        $other = User::factory()->create(['role' => 'maker']);
        $mixed = $this->order('cod');
        $unrelated = $this->order('cod');

        $this->addItem($mixed, $maker, 'Мідний оберіг', 2, 25000);
        $this->addItem($mixed, $other, 'Лляна крайка', 7, 40000);
        $this->addItem($unrelated, $other, 'Ткана сорочка', 1, 80000);

        $this->actingAs($maker)->get(route('maker.orders'))->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee($mixed->displayNumber())->assertSee('2 шт.')->assertSee('500,00')
            ->assertDontSee($unrelated->displayNumber())->assertDontSee('7 шт.')->assertDontSee('3 300,00');

        $this->get(route('maker.order', $mixed->reference))->assertOk()
            ->assertSee('Мідний оберіг')->assertSee('Оплата при отриманні')
            ->assertDontSee('Лляна крайка')->assertDontSee($mixed->customer_name)
            ->assertDontSee($mixed->email)->assertDontSee($mixed->phone)->assertDontSee($mixed->delivery_address)
            ->assertDontSee($mixed->comment)->assertDontSee($mixed->access_token);
        $this->get(route('maker.order', $unrelated->reference))->assertNotFound();

        $this->actingAs($other)->get(route('maker.order', $mixed->reference))->assertOk()
            ->assertSee('Лляна крайка')->assertDontSee('Мідний оберіг');
    }

    public function test_online_order_appears_after_payment_and_only_verified_makers_have_access(): void
    {
        $this->withoutVite();
        $maker = User::factory()->create(['role' => 'maker']);
        $unverified = User::factory()->unverified()->create(['role' => 'maker']);
        $customer = User::factory()->create(['role' => 'user']);
        $order = $this->order('wayforpay');
        $this->addItem($order, $maker, 'Глиняна миска', 1, 15000);

        $this->get(route('maker.orders'))->assertRedirect(route('login'));
        $this->actingAs($customer)->get(route('maker.orders'))->assertForbidden();
        $this->post(route('maker.telegram.connect'))->assertForbidden();
        $this->delete(route('maker.telegram.disconnect'))->assertForbidden();
        $this->actingAs($unverified)->get(route('maker.orders'))->assertRedirect(route('verification.notice'));

        $this->actingAs($maker)->get(route('maker.orders'))->assertOk()->assertDontSee($order->displayNumber());
        $this->get(route('maker.order', $order->reference))->assertNotFound();
        $order->update(['payment_status' => 'paid', 'paid_at' => now(), 'fulfilment_status' => 'processing']);
        $this->get(route('maker.order', $order->reference))->assertOk()->assertSee('Оплачено')->assertSee('Готується');

        $order->update(['payment_status' => 'refunded', 'fulfilment_status' => 'cancelled']);
        $this->get(route('maker.order', $order->reference))->assertOk()->assertSee('Кошти повернено')->assertSee('Скасовано');
        $order->delete();
        $this->get(route('maker.order', $order->reference))->assertNotFound();
    }

    public function test_maker_can_open_and_revoke_a_private_telegram_link_from_the_cabinet(): void
    {
        $this->withoutVite();
        config([
            'services.maker_telegram.bot_token' => '919191:maker_TEST',
            'services.maker_telegram.bot_username' => 'ridna_maker_test_bot',
        ]);
        $maker = User::factory()->create(['role' => 'maker', 'is_active' => true]);
        $other = User::factory()->create(['role' => 'maker', 'is_active' => true]);

        $this->actingAs($maker)->get(route('maker.orders'))->assertOk()->assertSee('Підключити Telegram');
        $response = $this->post(route('maker.telegram.connect'))->assertStatus(303)
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $location = $response->headers->get('Location');
        $this->assertMatchesRegularExpression('~^https://t\.me/ridna_maker_test_bot\?start=[a-f0-9]{48}$~D', $location);
        $code = substr($location, -48);

        $this->get(route('maker.orders'))->assertOk()->assertSee('Скасувати підключення')->assertDontSee($code);
        $this->actingAs($other)->get(route('maker.orders'))->assertOk()
            ->assertSee('Підключити Telegram')->assertDontSee('Скасувати підключення')->assertDontSee($code);
        $this->delete(route('maker.telegram.disconnect'))->assertRedirect(route('maker.orders'));
        $this->assertDatabaseHas('maker_telegram_links', ['user_id' => $maker->id, 'pairing_hash' => hash('sha256', $code)]);

        MakerTelegramLink::where('user_id', $maker->id)->update(['chat_id' => '177717777777', 'pairing_hash' => null, 'pairing_expires_at' => null]);
        $this->actingAs($maker)->get(route('maker.orders'))->assertOk()
            ->assertSee('Telegram підключено')->assertSee('Від’єднати Telegram')->assertDontSee('177717777777');
        $this->delete(route('maker.telegram.disconnect'))->assertRedirect(route('maker.orders'));
        $this->assertDatabaseMissing('maker_telegram_links', ['user_id' => $maker->id]);
    }

    private function order(string $paymentMethod): Order
    {
        return Order::create([
            'reference' => (string) Str::uuid(), 'access_token' => Str::random(64),
            'checkout_token' => (string) Str::uuid(), 'customer_name' => 'Private Buyer',
            'email' => 'private-buyer@example.test', 'phone' => '+380670000011',
            'delivery_method' => 'nova_poshta', 'delivery_address' => 'Private Delivery Address',
            'comment' => 'Private Buyer Note', 'total_minor' => 330000,
            'payment_method' => $paymentMethod, 'reserved_until' => now()->addHour(),
        ]);
    }

    private function addItem(Order $order, User $maker, string $name, int $quantity, int $price): void
    {
        $order->items()->create([
            'maker_id' => $maker->id, 'name' => $name, 'quantity' => $quantity,
            'maker_price_minor' => $price, 'price_minor' => $price,
        ]);
    }
}
