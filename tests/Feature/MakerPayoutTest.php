<?php

namespace Tests\Feature;

use App\Filament\Resources\MakerPayoutResource\Pages\ListMakerPayouts;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MakerPayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_payout_uses_frozen_price_and_keeps_other_makers_private(): void
    {
        $this->withoutVite();
        $maker = User::factory()->create(['role' => 'maker']);
        $other = User::factory()->create(['role' => 'maker']);
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'shop_manager']);

        $first = Product::create(['maker_id' => $maker->id, 'name' => 'Глиняна миска', 'slug' => 'myska', 'maker_price_minor' => 100000, 'stock' => 3, 'is_active' => true]);
        $second = Product::create(['maker_id' => $other->id, 'name' => 'Дерев’яна ложка', 'slug' => 'lozhka', 'maker_price_minor' => 60000, 'stock' => 3, 'is_active' => true]);
        $order = app(Checkout::class)->create([
            'customer_name' => 'Покупець', 'email' => 'buyer@example.com', 'phone' => '+380671111111',
            'delivery_method' => 'pickup', 'delivery_address' => 'Київ',
        ], [$first->id => 2, $second->id => 1], (string) Str::uuid());
        [$item, $otherItem] = $order->items()->orderBy('id')->get()->all();
        $first->update(['maker_price_minor' => 90000]);
        $this->assertSame(200000, $item->makerAccruedMinor());

        $this->actingAs($maker)->get(route('maker.payouts'))->assertOk()->assertDontSee('Глиняна миска');
        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
        $this->get(route('maker.payouts'))->assertOk()
            ->assertSee('Глиняна миска')->assertSee('Очікує виконання')
            ->assertDontSee('Дерев’яна ложка')->assertDontSee('buyer@example.com');
        $this->actingAs($manager)->get('/admin/maker-payouts')->assertForbidden();
        $this->actingAs($admin)->get('/admin/maker-payouts')->assertOk();
        Livewire::test(ListMakerPayouts::class)->assertTableActionHidden('recordPayout', $item);

        $order->update(['fulfilment_status' => 'completed']);
        Livewire::test(ListMakerPayouts::class)->assertCanSeeTableRecords([$item, $otherItem])
            ->filterTable('maker_id', $maker->id)->assertCanNotSeeTableRecords([$otherItem])
            ->callTableAction('recordPayout', $item, data: ['amount' => '1900,50', 'reference' => 'BANK-123'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(190050, $item->refresh()->maker_paid_minor);
        $this->assertSame($admin->id, $item->maker_paid_by);
        Livewire::test(ListMakerPayouts::class)->assertTableActionHidden('recordPayout', $item);
        $this->actingAs($maker)->get(route('maker.payouts'))->assertOk()->assertSee('BANK-123')->assertSee('Виплачено');

        $order->update(['payment_status' => 'refunded']);
        $this->get(route('maker.payouts'))->assertOk()->assertSee('Повернення після виплати');
        $this->assertSame('Скасовано', $otherItem->fresh()->makerPayoutStatus());
    }
}
