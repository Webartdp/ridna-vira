<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\EditOrder;
use App\Filament\Resources\OrderResource\Pages\ManageOrders;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminOrderEditor;
use App\Services\Checkout;
use App\Services\MakerPayouts;
use App\Services\WayForPay;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $changes = []): Product
    {
        return Product::create([
            ...['name' => 'Книга', 'slug' => 'knyha', 'price_minor' => 12550, 'stock' => 5, 'is_active' => true],
            ...$changes,
        ]);
    }

    private function order(Product $product, string $method = 'cod'): Order
    {
        return app(Checkout::class)->create([
            'customer_name' => 'Олена Тест',
            'email' => 'buyer@example.com',
            'phone' => '+380671234567',
            'delivery_method' => 'nova_poshta',
            'delivery_address' => 'Київ, відділення 1',
            'payment_method' => $method,
        ], [$product->id => 2], (string) Str::uuid());
    }

    public function test_admin_can_edit_contact_and_delivery_on_the_order_page(): void
    {
        $order = $this->order($this->product(), 'wayforpay');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('filament.admin.resources.orders.edit', $order))->assertOk()->assertSee($order->displayNumber());

        Livewire::test(EditOrder::class, ['record' => $order->getRouteKey()])
            ->fillForm([
                'customer_name' => 'Олена Покупець',
                'phone' => '+380678765432',
                'email' => 'updated@example.com',
                'delivery_address' => 'Дніпро, поштомат 12',
                'tracking_number' => '20450000000000',
            ])
            ->call('save')->assertHasNoFormErrors();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'customer_name' => 'Олена Покупець', 'phone' => '+380678765432',
            'email' => 'updated@example.com', 'delivery_address' => 'Дніпро, поштомат 12',
            'tracking_number' => '20450000000000', 'payment_method' => 'wayforpay', 'total_minor' => 25100,
        ]);
    }

    public function test_order_deletion_releases_unshipped_stock_once_and_can_be_restored(): void
    {
        $product = $this->product();
        $order = $this->order($product);
        $this->actingAs(User::factory()->create(['role' => 'shop_manager']));
        Livewire::test(ManageOrders::class)->assertTableActionHidden('delete', $order);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(ManageOrders::class)
            ->callTableAction('delete', $order)->assertHasNoTableActionErrors()
            ->assertCanNotSeeTableRecords([$order]);
        $this->assertSoftDeleted($order);
        $this->get($order->publicUrl())->assertNotFound();
        $this->assertSame(5, $product->refresh()->stock);
        app(Checkout::class)->archiveOrder($order);
        $this->assertSame(5, $product->refresh()->stock);

        Livewire::test(ManageOrders::class)
            ->filterTable('trashed', false)->assertCanSeeTableRecords([$order])
            ->callTableAction('restore', $order)->assertHasNoTableActionErrors();
        $this->assertNotSoftDeleted($order);
        $this->assertSame('cancelled', $order->refresh()->fulfilment_status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_late_online_payment_for_deleted_order_returns_it_for_review(): void
    {
        Mail::fake();
        config(['shop.wayforpay_account' => 'test_store', 'shop.wayforpay_secret' => 'test-secret']);
        $product = $this->product();
        $order = $this->order($product, 'wayforpay');
        app(Checkout::class)->archiveOrder($order);

        $payment = [
            'merchantAccount' => 'test_store', 'orderReference' => $order->reference,
            'amount' => Money::decimal($order->total_minor), 'currency' => 'UAH',
            'authCode' => '123', 'cardPan' => '42****4242', 'transactionStatus' => 'Approved', 'reasonCode' => 1100,
        ];
        $payment['merchantSignature'] = app(WayForPay::class)->sign(array_values($payment));

        $this->postJson(route('shop.webhook'), $payment)->assertOk();
        $this->assertNotSoftDeleted($order);
        $this->assertSame('review', $order->refresh()->payment_status);
        $this->assertSame(5, $product->refresh()->stock);
    }

    public function test_paid_orders_can_be_removed_from_the_list_without_changing_stock_or_losing_the_maker_payout(): void
    {
        $maker = User::factory()->create(['role' => 'maker']);
        $admin = User::factory()->create(['role' => 'admin']);
        $product = $this->product(['maker_id' => $maker->id, 'maker_price_minor' => 10000]);
        $order = $this->order($product);
        $order->update(['paid_at' => now(), 'payment_status' => 'paid', 'fulfilment_status' => 'completed']);
        $item = $order->items()->sole();

        app(Checkout::class)->archiveOrder($order);
        $this->assertSoftDeleted($order);
        $this->assertSame(3, $product->refresh()->stock);
        $this->assertSame($order->id, $item->order->id);
        app(MakerPayouts::class)->record($admin, $item, '200.00', 'BANK-ARCHIVED');
        $this->assertSame('BANK-ARCHIVED', $item->refresh()->maker_payout_reference);
    }

    public function test_editing_cash_on_delivery_items_adjusts_inventory_and_preserves_old_unit_prices(): void
    {
        $product = $this->product();
        $other = $this->product(['name' => 'Крайка', 'slug' => 'kraika', 'price_minor' => 9800, 'stock' => 2]);
        $order = $this->order($product);
        $line = $order->items()->sole();
        $product->update(['price_minor' => 90000]);

        $edited = app(AdminOrderEditor::class)->update($order, ['items' => [
            ['id' => $line->id, 'product_id' => $product->id, 'quantity' => 3],
            ['product_id' => $other->id, 'quantity' => 1],
        ]]);

        $this->assertSame(47450, $edited->total_minor);
        $this->assertSame(12550, $line->refresh()->price_minor);
        $this->assertSame(2, $product->refresh()->stock);
        $this->assertSame(1, $other->refresh()->stock);

        $otherLine = $order->items()->where('product_id', $other->id)->sole();
        app(AdminOrderEditor::class)->update($order, ['items' => [
            ['id' => $otherLine->id, 'product_id' => $other->id, 'quantity' => 1],
        ]]);
        $this->assertSame(5, $product->refresh()->stock);
        $this->assertSame(9800, $order->refresh()->total_minor);
    }

    public function test_rejected_item_edit_leaves_order_and_inventory_unchanged(): void
    {
        $product = $this->product();
        $order = $this->order($product);
        $line = $order->items()->sole();

        try {
            app(AdminOrderEditor::class)->update($order, [
                'customer_name' => 'Новий покупець',
                'items' => [['id' => $line->id, 'product_id' => $product->id, 'quantity' => 6]],
            ]);
            $this->fail('The order exceeds available stock.');
        } catch (ValidationException) {
            $this->assertSame('Олена Тест', $order->refresh()->customer_name);
            $this->assertSame(2, $line->refresh()->quantity);
            $this->assertSame(3, $product->refresh()->stock);
        }
    }
}
