<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SiteSetting;
use App\Support\Money;

class WayForPay
{
    public function setting(string $key): string
    {
        return (string) SiteSetting::read($key, config('shop.'.$key));
    }

    public function ready(): bool
    {
        return $this->configured() && filter_var(SiteSetting::read('shop_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    public function configured(): bool
    {
        return $this->setting('wayforpay_account') !== '' && $this->setting('wayforpay_secret') !== '';
    }

    public function sign(array $values): string
    {
        return hash_hmac('md5', implode(';', $values), $this->setting('wayforpay_secret'));
    }

    public function purchase(Order $order): array
    {
        abort_unless($this->ready(), 503, 'Оплата тимчасово недоступна.');
        $items = $order->items;
        $data = [
            'merchantAccount' => $this->setting('wayforpay_account'),
            'merchantDomainName' => $this->setting('wayforpay_domain'),
            'merchantAuthType' => 'SimpleSignature',
            'merchantTransactionSecureType' => 'AUTO',
            'orderReference' => $order->reference,
            'orderDate' => $order->created_at->timestamp,
            'amount' => Money::decimal($order->total_minor),
            'currency' => 'UAH',
            'productName' => $items->pluck('name')->all(),
            'productCount' => $items->pluck('quantity')->all(),
            'productPrice' => $items->map(fn ($item) => Money::decimal($item->price_minor))->all(),
            'clientFirstName' => $order->customer_name,
            'clientEmail' => $order->email,
            'clientPhone' => $order->phone,
            'language' => 'UA',
            'serviceUrl' => route('shop.webhook'),
            'returnUrl' => route('shop.return', $order->access_token),
            'orderTimeout' => max(1, $order->reserved_until->timestamp - now()->timestamp),
            'orderLifetime' => max(1, $order->reserved_until->timestamp - $order->created_at->timestamp),
            'merchantTransactionType' => 'SALE',
            'regularMode' => 'none',
        ];
        $data['merchantSignature'] = $this->sign([
            $data['merchantAccount'], $data['merchantDomainName'], $data['orderReference'], $data['orderDate'],
            $data['amount'], $data['currency'], ...$data['productName'], ...$data['productCount'], ...$data['productPrice'],
        ]);

        return $data;
    }

    public function verify(array $data): void
    {
        if (! $this->configured()) {
            abort(503);
        }
        $fields = ['merchantAccount', 'orderReference', 'amount', 'currency', 'authCode', 'cardPan', 'transactionStatus', 'reasonCode'];
        foreach ($fields as $field) {
            abort_unless(isset($data[$field]) && is_scalar($data[$field]), 400);
        }
        abort_unless(is_string($data['merchantSignature'] ?? null) && hash_equals($this->sign(array_map(fn ($key) => (string) $data[$key], $fields)), $data['merchantSignature']), 403);
        abort_unless(hash_equals($this->setting('wayforpay_account'), (string) $data['merchantAccount']), 403);
    }

    public function acknowledgement(string $reference): array
    {
        $time = now()->timestamp;

        return ['orderReference' => $reference, 'status' => 'accept', 'time' => $time, 'signature' => $this->sign([$reference, 'accept', $time])];
    }
}
