<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ManageProducts;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_maker_registration_requires_verified_email_and_does_not_grant_admin_access(): void
    {
        $this->withoutVite();
        Notification::fake();
        $this->get(route('maker.register'))->assertOk()->assertSee('Реєстрація майстра');
        $this->post(route('maker.register.submit'), [
            'name' => 'Майстриня', 'email' => 'maker@example.com',
            'password' => 'StrongPassword123', 'password_confirmation' => 'StrongPassword123', 'role' => 'admin',
        ])->assertRedirect(route('maker.index'));

        $user = User::where('email', 'maker@example.com')->firstOrFail();
        $this->assertSame('maker', $user->roleName());
        $this->assertFalse($user->canManage('shop'));
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->post(route('maker.products.store'))->assertRedirect(route('verification.notice'));
        $this->assertDatabaseCount('products', 0);
    }

    public function test_maker_can_submit_photos_and_only_sees_own_products(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        $maker = User::factory()->create(['role' => 'maker']);
        $other = User::factory()->create(['role' => 'maker']);
        $payload = ['name' => 'Глиняна миска', 'description' => 'Миска ручної роботи', 'price' => '100,05'];
        $this->actingAs($maker)->post(route('maker.products.store'), [
            ...$payload, 'images' => [UploadedFile::fake()->image('bowl.jpg'), UploadedFile::fake()->image('back.png')],
            'maker_id' => $other->id, 'price_minor' => 1, 'is_active' => true,
        ])->assertRedirect(route('maker.index'));

        $product = Product::firstOrFail();
        $this->assertSame($maker->id, $product->maker_id);
        $this->assertSame(10005, $product->maker_price_minor);
        $this->assertSame(13007, $product->price_minor);
        $this->assertTrue($product->made_to_order);
        $this->assertFalse($product->is_active);
        $this->assertCount(2, $product->images);
        Storage::disk('public')->assertExists($product->images);
        $this->get(route('shop.product', $product))->assertNotFound();
        $this->get(route('maker.index'))->assertOk()->assertSee('Глиняна миска')->assertSee('130,07');
        $this->actingAs($other)->get(route('maker.index'))->assertOk()->assertDontSee('Глиняна миска');

        $this->actingAs($maker)->post(route('maker.products.store'), [
            ...$payload, 'stock' => 0, 'images' => [UploadedFile::fake()->image('bowl.jpg')],
        ])->assertRedirect(route('maker.index'));
        $this->assertFalse(Product::latest('id')->firstOrFail()->made_to_order);
    }

    public function test_maker_edits_photos_hides_and_removes_only_own_products(): void
    {
        $this->withoutVite();
        Storage::fake('public');
        $maker = User::factory()->create(['role' => 'maker']);
        $other = User::factory()->create(['role' => 'maker']);
        $product = Product::create([
            'name' => 'Крайка', 'slug' => 'maker-kraika', 'description' => '<p>Опис</p>',
            'maker_id' => $maker->id, 'maker_price_minor' => 10000, 'is_active' => true, 'stock' => 2,
        ]);
        $this->actingAs($other)->get(route('maker.products.edit', $product))->assertNotFound();
        $this->patch(route('maker.products.hide', $product))->assertNotFound();
        $this->delete(route('maker.products.destroy', $product))->assertNotFound();

        $this->actingAs($maker)->get(route('maker.index'))->assertOk()->assertSee('Приховати Крайка');
        $this->get(route('maker.products.edit', $product))->assertOk()->assertSee('Редагувати виріб');
        $this->patch(route('maker.products.hide', $product))->assertRedirect();
        $this->assertFalse($product->refresh()->is_active);
        $product->update(['is_active' => true]);
        $this->put(route('maker.products.update', $product), [
            'name' => 'Нова крайка', 'description' => 'Новий опис', 'price' => '200',
            'stock' => 2, 'stock_before' => 2, 'images' => [UploadedFile::fake()->image('photo.jpg')],
        ])->assertRedirect(route('maker.index'));
        $this->assertFalse($product->refresh()->is_active);
        $this->assertSame(26000, $product->price_minor);
        $this->assertCount(1, $product->images);
        $this->get(route('maker.products.edit', $product))->assertOk()->assertSee('Поточні фото');
        $this->delete(route('maker.products.destroy', $product))->assertRedirect(route('maker.index'));
        $this->assertSoftDeleted($product);
    }

    public function test_guest_can_switch_account_type_and_form(): void
    {
        $this->withoutVite();
        $this->get(route('login'))->assertOk()->assertSee(route('maker.login'), false);
        $this->get(route('maker.login'))->assertOk()->assertSee(route('maker.register'), false);
        $this->get(route('maker.register'))->assertOk()->assertSee(route('register'), false)->assertSee(route('maker.login'), false);
    }

    public function test_sample_products_stay_private_until_an_administrator_approves(): void
    {
        $this->withoutVite();
        $maker = User::factory()->create(['role' => 'maker']);
        $manager = User::factory()->create(['role' => 'shop_manager']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->artisan('shop:seed-maker-samples', ['email' => $maker->email])->assertSuccessful();
        $this->artisan('shop:seed-maker-samples', ['email' => $maker->email])->assertSuccessful();
        $this->assertSame(2, Product::where('maker_id', $maker->id)->where('is_active', false)->count());

        $product = Product::where('maker_id', $maker->id)->firstOrFail();
        $this->get(route('shop.product', $product))->assertNotFound();

        $this->actingAs($manager);
        Livewire::test(ManageProducts::class)
            ->mountTableAction('edit', $product)->set('mountedActions.0.data.is_active', true)
            ->callMountedTableAction()->assertHasNoTableActionErrors();
        $this->assertFalse($product->refresh()->is_active);

        $this->actingAs($admin);
        Livewire::test(ManageProducts::class)
            ->mountTableAction('edit', $product)->set('mountedActions.0.data.is_active', true)
            ->callMountedTableAction()->assertHasNoTableActionErrors();
        $this->assertTrue($product->refresh()->is_active);
        $this->get(route('shop.product', $product))->assertOk();
    }

    public function test_made_to_order_items_use_retail_price_and_do_not_change_stock(): void
    {
        $this->withoutVite();
        $product = Product::create([
            'name' => 'Ткана крайка', 'slug' => 'tkana-kraika', 'maker_price_minor' => 10000,
            'made_to_order' => true, 'stock' => 0, 'is_active' => true,
        ]);
        $this->get(route('shop.product', $product))->assertOk()->assertSee('Під замовлення')->assertSee('130,00');
        $this->post(route('shop.cart.update', $product), ['quantity' => 2])->assertRedirect(route('shop.cart'));
        $this->get(route('shop.cart'))->assertOk()->assertSee('260,00');
        $checkout = app(Checkout::class);
        $order = $checkout->create([
            'customer_name' => 'Покупець', 'email' => 'buyer@example.com', 'phone' => '+380671234567',
            'delivery_method' => 'pickup', 'delivery_address' => 'Дніпро',
        ], [$product->id => 2], (string) Str::uuid());
        $this->assertSame(26000, $order->total_minor);
        $this->assertTrue($order->items()->firstOrFail()->made_to_order);
        $this->assertSame(0, $product->refresh()->stock);
        $checkout->release($order);
        $this->assertSame(0, $product->refresh()->stock);
    }
}
