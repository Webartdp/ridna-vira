<?php

namespace Tests\Feature;

use App\Filament\Resources\PaymentMethodResource\Pages\ManagePaymentMethods;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPaymentMethodsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_can_create_toggle_edit_and_delete_a_payment_method(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get('/admin/payment-methods')->assertOk()->assertSee('Способи оплати');

        $page = Livewire::test(ManagePaymentMethods::class);
        $fields = [
            'name' => 'Оплата за рахунком',
            'driver' => PaymentMethod::MANUAL,
            'description' => 'Інструкція в замовленні.',
            'instructions' => 'Вкажіть номер замовлення.',
            'sort_order' => 30,
            'is_active' => true,
        ];
        $page->callAction('create', data: [...$fields, 'instructions' => ''])->assertHasActionErrors(['instructions' => 'required']);
        $page->setActionData($fields)->callMountedAction()->assertHasNoActionErrors();
        $method = PaymentMethod::where('driver', PaymentMethod::MANUAL)->sole();
        $this->assertTrue($method->is_active);

        $page->call('updateTableColumnState', 'is_active', (string) $method->id, false);
        $this->assertFalse($method->refresh()->is_active);
        $page->call('updateTableColumnState', 'is_active', (string) $method->id, true);
        $this->assertTrue($method->refresh()->is_active);

        $page->callTableAction('edit', $method, data: ['name' => 'Оплата на рахунок'])->assertHasNoTableActionErrors();
        $this->assertSame('Оплата на рахунок', $method->refresh()->name);
        $page->callTableAction('delete', $method)->assertHasNoTableActionErrors();
        $this->assertModelMissing($method);
    }

    public function test_the_payment_method_resource_is_restricted_to_administrators(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'shop_manager']))
            ->get('/admin/payment-methods')->assertForbidden();
    }
}
