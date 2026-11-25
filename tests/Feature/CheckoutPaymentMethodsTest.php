<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ManageOrders;
use App\Mail\MakerOrderReceived;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout;
use App\Services\WayForPay;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

use function Illuminate\Support\defer;

class CheckoutPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'shop.wayforpay_account' => 'test_store',
            'shop.wayforpay_secret' => 'test-secret',
            'services.telegram.bot_token' => null,
            'services.maker_telegram.bot_token' => null,
        ]);
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_a_disabled_or_deleted_method_cannot_be_used_for_new_checkout(): void
    {
        $product = $this->product();
        $gateway = PaymentMethod::where('code', 'wayforpay')->sole();
        $this->openCart($product)->assertSee('value="wayforpay"', false)->assertSee('value="cod"', false);
        $this->post(route('shop.checkout'), $this->checkoutData($gateway->code))->assertRedirect();
        $order = Order::sole();
        $this->assertSame($gateway->name, $order->paymentMethodLabel());

        $gateway->update(['is_active' => false]);
        $this->openCart($product)->assertDontSee('value="wayforpay"', false)->assertSee('value="cod"', false);
        $data = $this->checkoutData($gateway->code);
        $this->from(route('shop.cart'))->post(route('shop.checkout'), $data)
            ->assertRedirect(route('shop.cart'))->assertSessionHasErrors('payment_method');

        $gateway->delete();
        $this->from(route('shop.cart'))->post(route('shop.checkout'), $data)
            ->assertRedirect(route('shop.cart'))->assertSessionHasErrors('payment_method');
        $this->post(route('shop.pay', $order->access_token))
            ->assertOk()->assertSee('https://secure.wayforpay.com/pay', false);
        $this->postJson(route('shop.webhook'), $this->signedPayment($order))->assertOk();
        $this->assertSame('paid', $order->refresh()->payment_status);
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(4, $product->refresh()->stock);
    }

    public function test_manual_option_is_saved_on_the_order_and_confirmed_in_the_admin_panel(): void
    {
        $maker = User::factory()->create(['role' => 'maker']);
        $product = $this->product(['maker_id' => $maker->id, 'maker_price_minor' => 10000]);
        $method = PaymentMethod::create([
            'driver' => PaymentMethod::MANUAL,
            'name' => 'Оплата за рахунком',
            'description' => 'Реквізити після оформлення.',
            'instructions' => 'У призначенні платежу зазначте номер замовлення.',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $this->openCart($product)->assertSeeInOrder([$method->name, 'Онлайн карткою через WayForPay'])
            ->assertSee($method->description)->assertDontSee($method->instructions);
        $this->post(route('shop.checkout'), [
            ...$this->checkoutData($method->code),
            'payment_instructions' => 'Підмінені реквізити',
        ])->assertRedirect();
        $order = Order::sole();
        $this->assertTrue($order->isManualPayment());
        $this->assertSame($method->name, $order->paymentMethodLabel());
        $this->assertSame($method->instructions, $order->payment_instructions);
        Mail::assertNotSent(MakerOrderReceived::class);

        $method->update(['name' => 'Змінена назва', 'instructions' => 'Змінені реквізити']);
        $method->delete();
        $this->get($order->publicUrl())->assertOk()->assertSee($order->paymentMethodLabel())
            ->assertSee($order->payment_instructions)->assertDontSee('Змінені реквізити');
        $this->post(route('shop.pay', $order->access_token))->assertConflict();
        $this->postJson(route('shop.webhook'), $this->signedPayment($order))->assertConflict();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(ManageOrders::class)->callTableAction('confirmManualPayment', $order)->assertHasNoTableActionErrors();
        defer()->invoke();
        $this->assertSame('paid', $order->refresh()->payment_status);
        $this->assertNotNull($order->paid_at);
        Mail::assertSent(MakerOrderReceived::class, fn (MakerOrderReceived $mail) => $mail->hasTo($maker->email));

        try {
            app(Checkout::class)->confirmManualPayment($order);
            $this->fail('A manual payment must only be confirmed once.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }
        defer()->invoke();
        Mail::assertSent(MakerOrderReceived::class, 1);
    }

    private function product(array $attributes = []): Product
    {
        return Product::create([
            'name' => 'Книга', 'slug' => 'knyha', 'price_minor' => 12550, 'stock' => 5, 'is_active' => true,
            ...$attributes,
        ]);
    }

    private function openCart(Product $product): TestResponse
    {
        return $this->withSession(['cart' => [$product->id => 1]])->get(route('shop.cart'))->assertOk();
    }

    private function checkoutData(string $code): array
    {
        return [
            'customer_name' => 'Покупець',
            'email' => 'buyer@example.test',
            'phone' => '+380671234567',
            'delivery_method' => 'nova_poshta',
            'delivery_address' => 'Київ, відділення 1',
            'payment_method' => $code,
            'checkout_token' => session('checkout_token'),
            'consent' => '1',
        ];
    }

    private function signedPayment(Order $order): array
    {
        $data = [
            'merchantAccount' => 'test_store',
            'orderReference' => $order->reference,
            'amount' => Money::decimal($order->total_minor),
            'currency' => 'UAH',
            'authCode' => '123',
            'cardPan' => '42****4242',
            'transactionStatus' => 'Approved',
            'reasonCode' => '1100',
        ];

        return [...$data, 'merchantSignature' => app(WayForPay::class)->sign(array_values($data))];
    }
}
