<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NovaPoshtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_lookups_use_server_side_key_and_return_only_labels_and_ids(): void
    {
        config(['cache.default' => 'array']);
        $city = '11111111-1111-4111-8111-111111111111';
        $warehouse = '22222222-2222-4222-8222-222222222222';
        SiteSetting::write('nova_poshta_api_key', 'test-nova-key');
        $this->assertNotSame('test-nova-key', SiteSetting::where('key', 'nova_poshta_api_key')->value('value'));

        Http::preventStrayRequests();
        Http::fake(['api.novaposhta.ua/*' => fn ($request) => Http::response([
            'success' => true,
            'data' => $request['calledMethod'] === 'getCities'
                ? [['Ref' => $city, 'Description' => 'Київ', 'AreaDescription' => 'Київська']]
                : [['Ref' => $warehouse, 'Description' => 'Відділення №1']],
        ])]);

        $this->getJson(route('shop.nova-poshta.cities', ['q' => 'Київ']))->assertExactJson([['id' => $city, 'name' => 'Київ, Київська']]);
        $this->getJson(route('shop.nova-poshta.warehouses', ['city' => $city, 'q' => '1']))
            ->assertExactJson([['id' => $warehouse, 'name' => 'Відділення №1']]);
        Http::assertSent(fn ($request) => $request['apiKey'] === 'test-nova-key'
            && $request['calledMethod'] === 'getWarehouses' && $request['methodProperties']['CityRef'] === $city && $request['methodProperties']['FindByString'] === '1');
        $this->getJson(route('shop.nova-poshta.warehouses', ['city' => 'invalid']))->assertUnprocessable();
    }
}
