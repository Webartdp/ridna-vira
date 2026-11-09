<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\Checkout;
use App\Services\ProductImages;
use App\Services\WayForPay;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['shop.wayforpay_account' => 'test_store', 'shop.wayforpay_secret' => 'test-secret', 'shop.wayforpay_domain' => 'ridnavira.com.ua']);
        Mail::fake();
    }

    private function product(array $values = []): Product
    {
        return Product::create([...['name' => 'Книга', 'slug' => 'knyha', 'price_minor' => 12550, 'stock' => 5, 'is_active' => true], ...$values]);
    }

    private function customer(): array
    {
        return ['customer_name' => 'Олена Тест', 'email' => 'buyer@example.com', 'phone' => '+380671234567', 'delivery_method' => 'nova_poshta', 'delivery_address' => 'Київ, відділення 1'];
    }

    private function order(Product $product, int $quantity = 2): Order
    {
        return app(Checkout::class)->create($this->customer(), [$product->id => $quantity], (string) Str::uuid());
    }

    private function signedPayload(Order $order, array $changes = []): array
    {
        $data = [...['merchantAccount' => 'test_store', 'orderReference' => $order->reference, 'amount' => Money::decimal($order->total_minor), 'currency' => 'UAH', 'authCode' => '123', 'cardPan' => '42****4242', 'transactionStatus' => 'Approved', 'reasonCode' => 1100], ...$changes];
        $data['merchantSignature'] = app(WayForPay::class)->sign(array_values($data));

        return $data;
    }

    public function test_catalogue_and_cart_use_real_products_and_server_prices(): void
    {
        $product = $this->product();
        $this->product(['slug' => 'hidden', 'name' => 'Прихований товар', 'is_active' => false]);
        $this->get('/kramnychka')->assertOk()->assertSee('125,50')->assertDontSee('Прихований товар');
        $this->post('/koshyk/knyha', ['quantity' => 2, 'add' => 1, 'price_minor' => 1])->assertRedirect('/koshyk');
        $this->get('/koshyk')->assertOk()->assertSee('251,00');
        $this->assertSame(5, $product->fresh()->stock);
        $this->post('/koshyk/knyha', ['quantity' => 6])->assertSessionHasErrors('cart');
        $this->post('/koshyk/knyha', ['quantity' => 0])->assertRedirect('/koshyk');
        $this->get('/koshyk')->assertSee('Кошик поки порожній');
    }

    public function test_checkout_reserves_once_and_ignores_client_total(): void
    {
        $product = $this->product();
        $token = (string) Str::uuid();
        $payload = [...$this->customer(), 'checkout_token' => $token, 'consent' => '1', 'total_minor' => 1];
        $this->withSession(['cart' => [$product->id => 2], 'checkout_token' => $token])->post('/zamovlennia', $payload)->assertRedirect();
        $this->post('/zamovlennia', $payload)->assertRedirect();
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame(25100, Order::first()->total_minor);
        $this->assertSame(3, $product->fresh()->stock);
        $this->assertSame(2, Order::first()->items()->first()->quantity);
        $this->get('/koshyk')->assertOk();
        $this->assertNotSame($token, session('checkout_token'));
    }

    public function test_stock_changes_roll_back_all_checkout_items(): void
    {
        $one = $this->product();
        $two = $this->product(['slug' => 'second', 'stock' => 0]);
        try {
            app(Checkout::class)->create($this->customer(), [$one->id => 1, $two->id => 1], (string) Str::uuid());
            $this->fail('Unavailable stock must be rejected.');
        } catch (ValidationException) {
            $this->assertSame(5, $one->fresh()->stock);
            $this->assertDatabaseCount('orders', 0);
        }
    }

    public function test_missing_credentials_disable_checkout_without_creating_orders(): void
    {
        config(['shop.wayforpay_secret' => null]);
        $this->get('/koshyk')->assertOk();
        $this->post('/zamovlennia', [])->assertStatus(503);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_purchase_is_signed_and_contains_snapshots_and_local_urls(): void
    {
        $product = $this->product();
        $order = $this->order($product);
        $product->update(['price_minor' => 99999, 'name' => 'Інша назва']);
        $fields = app(WayForPay::class)->purchase($order);
        $this->assertSame(['Книга'], $fields['productName']);
        $this->assertSame(['125.50'], $fields['productPrice']);
        $this->assertSame('251.00', $fields['amount']);
        $expected = hash_hmac('md5', implode(';', ['test_store', 'ridnavira.com.ua', $order->reference, $order->created_at->timestamp, '251.00', 'UAH', 'Книга', 2, '125.50']), 'test-secret');
        $this->assertSame($expected, $fields['merchantSignature']);
        $this->assertSame(route('shop.webhook'), $fields['serviceUrl']);
        $this->post(route('shop.pay', $order->access_token))->assertOk()->assertSee('https://secure.wayforpay.com/pay')->assertDontSee('test-secret');
    }

    public function test_browser_return_cannot_confirm_payment(): void
    {
        $order = $this->order($this->product());
        $this->post(route('shop.return', $order->access_token), ['transactionStatus' => 'Approved'])->assertRedirect($order->publicUrl());
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_verified_callback_is_idempotent_and_cannot_downgrade_paid_order(): void
    {
        $product = $this->product();
        $order = $this->order($product);
        $data = $this->signedPayload($order);
        $response = $this->postJson('/oplata/wayforpay', $data)->assertOk()->assertJsonPath('status', 'accept');
        $ack = $response->json();
        $this->assertSame(hash_hmac('md5', $order->reference.';accept;'.$ack['time'], 'test-secret'), $ack['signature']);
        $this->postJson('/oplata/wayforpay', $data)->assertOk();
        $this->postJson('/oplata/wayforpay', $this->signedPayload($order, ['transactionStatus' => 'Declined', 'reasonCode' => 1105]))->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
        app(Checkout::class)->release($order);
        $this->assertSame(3, $product->fresh()->stock);
    }

    public function test_invalid_signatures_amount_currency_and_merchant_are_rejected(): void
    {
        $order = $this->order($this->product());
        $data = $this->signedPayload($order);
        $this->postJson('/oplata/wayforpay', [...$data, 'merchantSignature' => 'bad'])->assertForbidden();
        $this->postJson('/oplata/wayforpay', $this->signedPayload($order, ['amount' => '1.00']))->assertStatus(422);
        $this->postJson('/oplata/wayforpay', $this->signedPayload($order, ['currency' => 'USD']))->assertStatus(422);
        $this->postJson('/oplata/wayforpay', $this->signedPayload($order, ['merchantAccount' => 'other']))->assertForbidden();
        $this->assertSame('pending', $order->fresh()->payment_status);
    }

    public function test_expiration_restores_stock_once_and_delayed_payment_requires_review(): void
    {
        $product = $this->product();
        $order = $this->order($product);
        $this->travel(31)->minutes();
        $this->artisan('shop:expire-reservations')->assertSuccessful();
        $this->artisan('shop:expire-reservations')->assertSuccessful();
        $this->assertSame(5, $product->fresh()->stock);
        $this->post(route('shop.pay', $order->access_token))->assertStatus(409);
        $this->postJson('/oplata/wayforpay', $this->signedPayload($order))->assertOk();
        $this->assertSame('review', $order->fresh()->payment_status);
        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_refund_is_preserved_against_replayed_approved_callback(): void
    {
        $order = $this->order($this->product());
        $this->postJson('/oplata/wayforpay', $this->signedPayload($order))->assertOk();
        $this->postJson('/oplata/wayforpay', $this->signedPayload($order, ['transactionStatus' => 'Refunded']))->assertOk();
        $this->postJson('/oplata/wayforpay', $this->signedPayload($order))->assertOk();
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_order_access_requires_unguessable_token_and_is_not_cached(): void
    {
        $order = $this->order($this->product());
        $this->get($order->publicUrl())->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex');
        $this->get('/zamovlennia/'.str_repeat('a', 64))->assertNotFound();
    }

    public function test_product_images_have_small_local_webp_variants(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('book.jpg', 1600, 2000);
        $path = $image->store('products', 'public');
        $product = $this->product(['images' => [$path]]);
        foreach ([400, 800, 1200] as $width) {
            Storage::disk('public')->assertExists(ProductImages::variant($path, $width));
        }
        $this->get('/kramnychka/knyha')->assertOk()->assertSee('srcset=', false)->assertSee('-400.webp', false);
        $this->assertStringContainsString('320w', $product->imageSrcset($path));
    }

    public function test_disabling_checkout_still_accepts_existing_payment_confirmations(): void
    {
        $order = $this->order($this->product());
        SiteSetting::write('shop_enabled', '0');
        $this->post('/zamovlennia')->assertStatus(503);
        $this->postJson('/oplata/wayforpay', $this->signedPayload($order))->assertOk();
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_deleted_products_are_removed_from_cart_and_header_count(): void
    {
        $product = $this->product();
        $this->withSession(['cart' => [$product->id => 2]]);
        $product->delete();
        $this->get('/koshyk')->assertOk()->assertSee('Кошик поки порожній')->assertSee('Кошик, 0 товарів');
        $this->assertSame([], session('cart'));
    }
}
