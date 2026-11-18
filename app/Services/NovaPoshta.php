<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class NovaPoshta
{
    public function configured(): bool
    {
        return $this->key() !== '';
    }

    public function cities(string $search): array
    {
        return $this->lookup('getCities', ['FindByString' => $search, 'Limit' => 30], function (array $row): string {
            return implode(', ', array_filter([$row['Description'] ?? null, $row['AreaDescription'] ?? null]));
        });
    }

    public function warehouses(string $city, string $search, int $page): array
    {
        return $this->lookup('getWarehouses', ['CityRef' => $city, 'FindByString' => $search, 'Page' => $page, 'Limit' => 50],
            fn (array $row): string => $row['Description'] ?? '');
    }

    private function key(): string
    {
        return trim((string) SiteSetting::read('nova_poshta_api_key', config('shop.nova_poshta_api_key')));
    }

    private function lookup(string $method, array $properties, callable $label): array
    {
        $key = $this->key();
        abort_if($key === '', 503);
        $cacheKey = 'nova-poshta:'.hash('sha256', json_encode([$key, $method, $properties], JSON_THROW_ON_ERROR));

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($key, $method, $properties, $label): array {
            try {
                $response = Http::asJson()->acceptJson()->connectTimeout(3)->timeout(6)
                    ->post('https://api.novaposhta.ua/v2.0/json/', [
                        'apiKey' => $key, 'modelName' => 'Address', 'calledMethod' => $method, 'methodProperties' => $properties,
                    ]);
            } catch (ConnectionException) {
                throw new ServiceUnavailableHttpException;
            }

            $data = $response->json();
            if (! $response->successful() || ($data['success'] ?? false) !== true || ! is_array($data['data'] ?? null)) {
                throw new ServiceUnavailableHttpException;
            }

            $results = [];
            foreach ($data['data'] as $row) {
                $ref = $row['Ref'] ?? '';
                $name = $label($row);
                if (Str::isUuid($ref) && $name !== '') {
                    $results[] = ['id' => $ref, 'name' => $name];
                }
            }

            return $results;
        });
    }
}
