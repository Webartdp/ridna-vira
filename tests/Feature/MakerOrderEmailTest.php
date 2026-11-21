<?php

namespace Tests\Feature;

use App\Mail\MakerOrderReceived;
use App\Mail\OrderPaid;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout;
use App\Services\WayForPay;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

use function Illuminate\Support\defer;

class MakerOrderEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://ridna.example.test',
            'services.telegram.bot_token' => null,
            'services.maker_telegram.bot_token' => null,
            'shop.wayforpay_account' => 'test_store',
            'shop.wayforpay_secret' => 'test-secret',
        ]);
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_cash_on_delivery_emails_each_eligible_maker_with_their_items_and_shipping_details(): void
    {
        $first = User::factory()->create(['role' => 'maker']);
        $second = User::factory()->create(['role' => 'maker']);
        $inactive = User::factory()->create(['role' => 'maker', 'is_active' => false]);
        $unverified = User::factory()->unverified()->create(['role' => 'maker']);
        $bowl = $this->product('Миска <глиняна>', $first, 12500);
        $cup = $this->product('Чашка', $first, 25000, madeToOrder: true);
        $wovenItem = $this->product('Ткана крайка', $second, 45000);
        $inactiveProduct = $this->product('Виріб неактивного майстра', $inactive, 47000);
        $unverifiedProduct = $this->product('Виріб непідтвердженого майстра', $unverified, 53000);
        $shopProduct = $this->product('Книга крамниці', null, 96000);
        $cart = [
            $bowl->id => 2, $cup->id => 1, $wovenItem->id => 3,
            $inactiveProduct->id => 1, $unverifiedProduct->id => 1, $shopProduct->id => 1,
        ];
        $token = (string) Str::uuid();

        DB::beginTransaction();
        $order = $this->checkout('cod', $cart, $token);
        $this->assertSame($order->id, $this->checkout('cod', $cart, $token)->id);
        defer()->invoke();
        Mail::assertNothingSent();
        DB::commit();
        defer()->invoke();

        Mail::assertSent(MakerOrderReceived::class, 2);
        Http::assertNothingSent();
        $firstMail = Mail::sent(MakerOrderReceived::class, fn ($mail) => $mail->hasTo($first->email))->sole();
        $secondMail = Mail::sent(MakerOrderReceived::class, fn ($mail) => $mail->hasTo($second->email))->sole();
        $this->assertSame(50000, $firstMail->totalMinor);
        $this->assertSame(135000, $secondMail->totalMinor);
        $this->assertSame([$bowl->name, $cup->name], array_column($firstMail->items, 'name'));
        $this->assertSame([[
            'name' => $wovenItem->name, 'quantity' => 3, 'made_to_order' => false, 'total_minor' => 135000,
        ]], $secondMail->items);
        $this->assertTrue(collect($firstMail->items)->firstWhere('name', $cup->name)['made_to_order']);

        foreach ([$firstMail, $secondMail] as $mail) {
            $this->assertCount(1, $mail->to);
            $this->assertSame($order->displayNumber(), $mail->orderNumber);
            $this->assertSame('Оплата при отриманні', $mail->paymentStatus);
            $this->assertSame('https://ridna.example.test'.route('maker.order', ['order' => $order->reference], false), $mail->orderUrl);
            $html = $mail->render();
            $this->assertStringContainsString($order->displayNumber(), $html);
            $this->assertStringContainsString($mail->orderUrl, $html);
            $this->assertStringContainsString(Money::display($mail->totalMinor), $html);
            $this->assertShippingDetails($order, $mail, $html);
            foreach ([
                $order->email, $order->comment, $order->access_token, $token,
                $shopProduct->name, $inactiveProduct->name,
                $unverifiedProduct->name, Money::display($order->total_minor),
            ] as $privateValue) {
                $this->assertStringNotContainsString($privateValue, $html);
            }
        }
        $this->assertStringContainsString('Миска &lt;глиняна&gt;', $firstMail->render());
        $this->assertStringNotContainsString($wovenItem->name, $firstMail->render());
        $this->assertStringNotContainsString($cup->name, $secondMail->render());
    }

    public function test_online_order_emails_maker_only_after_verified_payment_once(): void
    {
        $maker = User::factory()->create(['role' => 'maker']);
        $product = $this->product('Миска', $maker, 12500);
        $order = $this->checkout('wayforpay', [$product->id => 2]);
        defer()->invoke();
        Mail::assertNotSent(MakerOrderReceived::class);

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
        Mail::assertNotSent(MakerOrderReceived::class);

        $payment['merchantSignature'] = app(WayForPay::class)->sign(array_values($payment));
        $this->postJson(route('shop.webhook'), $payment)->assertOk();
        $this->postJson(route('shop.webhook'), $payment)->assertOk();

        $mail = Mail::sent(MakerOrderReceived::class)->sole();
        $this->assertTrue($mail->hasTo($maker->email));
        $this->assertSame('Оплачено', $mail->paymentStatus);
        $this->assertSame(25000, $mail->totalMinor);
        $html = $mail->render();
        $this->assertShippingDetails($order, $mail, $html);
        foreach ([$order->email, $order->comment, $payment['cardPan'], $payment['merchantSignature']] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $html);
        }
        Mail::assertSent(OrderPaid::class, fn ($confirmation) => $confirmation->hasTo($order->email));
    }

    public function test_rolled_back_order_never_emails_a_maker(): void
    {
        $maker = User::factory()->create(['role' => 'maker']);
        $product = $this->product('Миска', $maker, 12500);

        DB::beginTransaction();
        $order = $this->checkout('cod', [$product->id => 1]);
        DB::rollBack();
        defer()->invoke();

        $this->assertDatabaseMissing('orders', ['id' => $order->id]);
        Mail::assertNothingSent();
    }

    private function assertShippingDetails(Order $order, MakerOrderReceived $mail, string $html): void
    {
        $expected = [
            'Отримувач' => $order->customer_name,
            'Телефон' => $order->phone,
            'Доставка' => 'Нова пошта',
            'Відділення або адреса' => $order->delivery_address,
        ];
        $this->assertEquals($expected, $mail->deliveryDetails);
        $this->assertStringContainsString('Дані для відправлення', $html);
        foreach ($expected as $label => $value) {
            $this->assertStringContainsString($label, $html);
            $this->assertStringContainsString(e($value), $html);
        }
        $this->assertStringNotContainsString($order->customer_name, $html);
        $this->assertStringNotContainsString($order->delivery_address, $html);
    }

    private function product(string $name, ?User $maker, int $price, bool $madeToOrder = false): Product
    {
        return Product::create([
            'name' => $name, 'slug' => Str::slug($name), 'stock' => 10, 'is_active' => true,
            'price_minor' => $price, 'maker_id' => $maker?->id, 'maker_price_minor' => $maker ? $price : null,
            'made_to_order' => $madeToOrder,
        ]);
    }

    private function checkout(string $method, array $cart, ?string $token = null): Order
    {
        return app(Checkout::class)->create([
            'customer_name' => 'Покупець <Тестовий>',
            'email' => 'customer@example.test',
            'phone' => '+380671234567',
            'delivery_method' => 'nova_poshta',
            'delivery_address' => 'Дніпро — Поштомат №123 & вхід 2',
            'comment' => 'Домофон недоступний',
            'payment_method' => $method,
        ], $cart, $token ?? (string) Str::uuid());
    }
}
