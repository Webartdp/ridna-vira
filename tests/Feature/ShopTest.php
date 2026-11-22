<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ManageOrders;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Checkout;
use App\Services\ProductImages;
use App\Services\WayForPay;
use App\Support\Cms;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
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
        $product = $this->product(['images' => ['products/book.jpg']]);
        $this->product(['slug' => 'hidden', 'name' => 'Прихований товар', 'is_active' => false]);
        $this->get('/kramnychka')->assertOk()->assertSee('125,50')->assertDontSee('Прихований товар');
        $this->post('/koshyk/knyha', ['quantity' => 2, 'add' => 1, 'price_minor' => 1])->assertRedirect('/koshyk');
        $this->get('/koshyk')->assertOk()->assertSee('251,00')->assertSee('/storage/products/book.jpg', false)
            ->assertSeeInOrder(['Товари в кошику', 'Контактні дані', 'Доставка', 'До оплати за товари'])
            ->assertSee('form="checkout-form" name="payment_method"', false);
        $this->assertSame(5, $product->fresh()->stock);
        $this->post('/koshyk/knyha', ['quantity' => 6])->assertSessionHasErrors('cart');
        $this->post('/koshyk/knyha', ['quantity' => 0])->assertRedirect('/koshyk');
        $this->get('/koshyk')->assertSee('Кошик поки порожній');
    }

    public function test_catalogue_filters_by_price_and_nested_category(): void
    {
        $parent = ProductCategory::create(['name' => 'Прикраси', 'slug' => 'jewelry']);
        $child = $parent->children()->create(['name' => 'Браслети', 'slug' => 'bracelets']);
        $this->product(['product_category_id' => $child->id, 'name' => 'Мідний браслет', 'price_minor' => 12550]);
        $this->product(['product_category_id' => $child->id, 'name' => 'Срібний браслет', 'slug' => 'silver', 'price_minor' => 15000]);
        $this->product(['name' => 'Дерев’яний гребінь', 'slug' => 'comb', 'price_minor' => 12550]);

        $this->get(route('shop.index', ['price_from' => '126']))
            ->assertOk()
            ->assertSee('Срібний браслет')
            ->assertDontSee('Мідний браслет')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        foreach ([$parent, $child] as $category) {
            $this->get(route('shop.category', ['category' => $category->slug, 'price_from' => '125,50', 'price_to' => '125.50']))
                ->assertOk()
                ->assertSee('Мідний браслет')
                ->assertDontSee('Срібний браслет')
                ->assertDontSee('Дерев’яний гребінь')
                ->assertSee('<details class="shop-category-group" open', false);
        }
    }

    public function test_product_navigation_links_to_its_categories_and_maker(): void
    {
        $maker = User::factory()->create(['name' => 'Олена Різьбярка', 'role' => 'maker', 'email' => 'private-maker@example.test']);
        $category = ProductCategory::create(['name' => 'Дерев’яні прикраси', 'slug' => 'shop-nav-jewelry']);
        $child = $category->children()->create(['name' => 'Різьблені підвіски', 'slug' => 'shop-nav-pendants']);
        $product = $this->product([
            'name' => 'Дубова підвіска', 'product_category_id' => $child->id, 'maker_id' => $maker->id, 'maker_price_minor' => 10000,
        ]);

        $response = $this->get(route('shop.product', $product))->assertOk()
            ->assertSee($maker->name)->assertDontSee($maker->email);
        $document = new \DOMXPath(Cms::document($response->getContent()));
        $navigation = $document->query('//main//nav[.//a[normalize-space(.) = "Різьблені підвіски"]]')->item(0);
        $this->assertNotNull($navigation);
        $this->assertMatchesRegularExpression('~Головна.*Крамниця.*Дерев’яні прикраси.*Різьблені підвіски.*Дубова підвіска~su', $navigation->textContent);
        $this->assertSame($category->publicUrl(), $document->query('.//a[normalize-space(.) = "Дерев’яні прикраси"]', $navigation)->item(0)->getAttribute('href'));
        $this->assertSame($child->publicUrl(), $document->query('.//a[normalize-space(.) = "Різьблені підвіски"]', $navigation)->item(0)->getAttribute('href'));
        $this->assertStringEndsWith(route('shop.index', ['maker' => $maker->id], false), $document->query('//main//a[normalize-space(.) = "Олена Різьбярка"]')->item(0)->getAttribute('href'));

        $schema = $document->query('//script[@type = "application/ld+json"]')->item(0);
        $graph = json_decode($schema->textContent, true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $breadcrumbs = collect($graph)->firstWhere('@type', 'BreadcrumbList')['itemListElement'];
        $this->assertSame(['Головна', 'Крамниця', $category->name, $child->name, $product->name], array_column($breadcrumbs, 'name'));
    }

    public function test_maker_catalogue_keeps_its_published_products_and_catalogue_filters(): void
    {
        $maker = User::factory()->create(['name' => 'Олена Гончарка', 'role' => 'maker', 'email' => 'private-maker@example.test']);
        $anotherMaker = User::factory()->create(['role' => 'maker']);
        $category = ProductCategory::create(['name' => 'Кераміка для дому', 'slug' => 'maker-filter-pottery']);
        $child = $category->children()->create(['name' => 'Чаші', 'slug' => 'maker-filter-bowls']);
        $details = ['maker_id' => $maker->id, 'maker_price_minor' => 15000, 'product_category_id' => $child->id];

        $this->product([...$details, 'name' => 'Керамічна чаша', 'slug' => 'maker-filter-bowl']);
        $this->product([...$details, 'name' => 'Керамічна таця', 'slug' => 'maker-filter-tray', 'maker_price_minor' => 7000]);
        $this->product([...$details, 'name' => 'Керамічна підвіска', 'slug' => 'maker-filter-pendant', 'product_category_id' => null]);
        $this->product([...$details, 'name' => 'Глиняна скульптура', 'slug' => 'maker-filter-sculpture']);
        $this->product([...$details, 'name' => 'Керамічна кружка іншого майстра', 'slug' => 'maker-filter-mug', 'maker_id' => $anotherMaker->id]);
        $this->product([...$details, 'name' => 'Прихована чаша майстра', 'slug' => 'maker-filter-unpublished', 'is_active' => false]);
        $this->product([...$details, 'name' => 'Видалена чаша майстра', 'slug' => 'maker-filter-deleted'])->delete();
        $this->product(['name' => 'Книга крамниці', 'slug' => 'maker-filter-book']);

        $this->get(route('shop.index', ['maker' => $maker->id]))->assertOk()
            ->assertSee($maker->name)->assertDontSee($maker->email)
            ->assertSee('Керамічна чаша')->assertSee('Керамічна таця')
            ->assertDontSee('Керамічна кружка іншого майстра')->assertDontSee('Книга крамниці')
            ->assertDontSee('Прихована чаша майстра')->assertDontSee('Видалена чаша майстра')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);

        $this->get(route('shop.index', ['maker' => $maker->id, 'q' => 'Керамічна', 'category' => $category->slug, 'price_from' => '150']))
            ->assertOk()->assertSee('Керамічна чаша')->assertDontSee('Керамічна таця')
            ->assertDontSee('Керамічна підвіска')->assertDontSee('Глиняна скульптура')->assertDontSee('Керамічна кружка іншого майстра');

        $buyer = User::factory()->create(['role' => 'user']);
        $inactiveMaker = User::factory()->create(['role' => 'maker', 'is_active' => false]);
        foreach ([$buyer->id, $inactiveMaker->id, 'invalid', ['invalid']] as $invalidMaker) {
            $this->get(route('shop.index', ['maker' => $invalidMaker]))->assertNotFound();
        }
    }

    public function test_price_slider_is_available_with_one_product_and_empty_results(): void
    {
        $this->product(['price_minor' => 58500]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('data-price-slider', false)
            ->assertSee('type="range" min="0" max="58500"', false);

        $this->get(route('shop.index', ['q' => 'missing', 'price_from' => '600']))
            ->assertOk()
            ->assertSee('Товарів не знайдено')
            ->assertSee('type="range" min="0" max="60000"', false);
    }

    public function test_cart_quantity_update_returns_totals_and_persists_only_valid_changes(): void
    {
        $product = $this->product();
        $otherProduct = $this->product(['slug' => 'second', 'price_minor' => 10000]);
        $url = route('shop.cart.update', $product);
        $token = (string) Str::uuid();

        $this->withSession(['cart' => [$product->id => 1, $otherProduct->id => 2], 'checkout_token' => $token])
            ->postJson($url, ['quantity' => 3])
            ->assertOk()
            ->assertExactJson([
                'quantity' => 3,
                'unitPrice' => '125,50 ₴',
                'lineTotal' => '376,50 ₴',
                'total' => '576,50 ₴',
                'count' => 5,
            ]);

        $this->postJson($url, ['quantity' => 6])->assertUnprocessable()->assertJsonValidationErrors('cart');
        $this->post('/zamovlennia', [...$this->customer(), 'checkout_token' => $token, 'consent' => '1'])->assertRedirect();
        $this->assertDatabaseHas('orders', ['total_minor' => 57650]);
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
        $this->post('/zamovlennia', [...$this->customer(), 'checkout_token' => session('checkout_token'),
            'consent' => '1', 'payment_method' => 'wayforpay'])->assertStatus(503);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_nova_poshta_cash_on_delivery_can_be_confirmed_without_online_payment(): void
    {
        config(['shop.wayforpay_secret' => null]);
        $product = $this->product();
        $this->withSession(['cart' => [$product->id => 2]])->get('/koshyk')->assertOk()
            ->assertSee('При отриманні')->assertSee('Онлайн карткою через WayForPay')
            ->assertDontSee('Укрпошта')->assertDontSee('Самовивіз');
        $payload = [...$this->customer(), 'checkout_token' => session('checkout_token'), 'consent' => '1', 'payment_method' => 'cod'];
        $this->post('/zamovlennia', [...$payload, 'delivery_method' => 'pickup'])->assertSessionHasErrors('delivery_method');
        $this->post('/zamovlennia', $payload)->assertRedirect();
        $order = Order::firstOrFail();
        $this->assertTrue($order->isCashOnDelivery());
        $this->assertSame(3, $product->refresh()->stock);
        $this->get($order->publicUrl())->assertOk()
            ->assertSee($order->displayNumber())->assertSee('Оплата за вироби — при отриманні')->assertDontSee('Оплатити карткою через WayForPay');
        $this->post(route('shop.pay', $order->access_token))->assertConflict();

        $this->travel(31)->minutes();
        $this->artisan('shop:expire-reservations')->assertSuccessful();
        $this->assertSame('pending', $order->refresh()->payment_status);
        $this->assertSame(3, $product->refresh()->stock);

        $order->update(['fulfilment_status' => 'shipped']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        Livewire::test(ManageOrders::class)
            ->callTableAction('confirmCashOnDelivery', $order)->assertHasNoTableActionErrors();
        $this->assertSame('paid', $order->refresh()->payment_status);
        $this->assertSame('completed', $order->fulfilment_status);
    }

    public function test_admin_can_find_orders_by_the_readable_number_or_legacy_reference(): void
    {
        $product = $this->product();
        $first = $this->order($product, 1);
        $second = $this->order($product, 1);
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::withQueryParams(['search' => $first->displayNumber()])
            ->test(ManageOrders::class)
            ->assertSee($first->displayNumber())
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second])
            ->searchTable($second->reference)
            ->assertCanSeeTableRecords([$second])
            ->assertCanNotSeeTableRecords([$first]);
    }

    public function test_cancelling_cash_on_delivery_releases_stock_only_once(): void
    {
        $product = $this->product();
        $checkout = app(Checkout::class);
        $order = $checkout->create([...$this->customer(), 'payment_method' => 'cod'], [$product->id => 2], (string) Str::uuid());
        $checkout->release($order, cancel: true);
        $checkout->release($order, cancel: true);
        $this->assertSame('cancelled', $order->refresh()->fulfilment_status);
        $this->assertSame(5, $product->refresh()->stock);
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
