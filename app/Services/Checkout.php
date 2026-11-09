<?php

namespace App\Services;

use App\Mail\OrderPaid;
use App\Models\Order;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Checkout
{
    public function create(array $customer, array $cart, string $token): Order
    {
        return DB::transaction(function () use ($customer, $cart, $token) {
            if ($existing = Order::where('checkout_token', $token)->first()) {
                return $existing;
            }
            if ($cart === []) {
                throw ValidationException::withMessages(['cart' => 'Кошик порожній.']);
            }
            $products = Product::whereIn('id', array_keys($cart))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($existing = Order::where('checkout_token', $token)->lockForUpdate()->first()) {
                return $existing;
            }
            $total = 0;
            $items = [];
            foreach ($cart as $id => $quantity) {
                $product = $products->get($id);
                if (! $product || ! $product->is_active || $quantity < 1 || $quantity > 99 || $quantity > $product->stock) {
                    throw ValidationException::withMessages(['cart' => 'Наявність товарів змінилася. Перевірте кошик.']);
                }
                $total += $product->price_minor * $quantity;
                if ($total > 999999999) {
                    throw ValidationException::withMessages(['cart' => 'Сума замовлення перевищує допустиму. Зменште кількість товарів.']);
                }
                $items[] = ['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'quantity' => $quantity, 'price_minor' => $product->price_minor];
                $product->decrement('stock', $quantity);
            }
            $order = Order::create([
                ...$customer, 'reference' => (string) Str::uuid(), 'access_token' => Str::random(64),
                'checkout_token' => $token, 'total_minor' => $total, 'reserved_until' => now()->addMinutes(config('shop.reservation_minutes')),
            ]);
            $order->items()->createMany($items);

            return $order;
        }, 3);
    }

    public function release(Order $order, string $status = 'expired'): void
    {
        DB::transaction(function () use ($order, $status) {
            $locked = Order::lockForUpdate()->findOrFail($order->id);
            if ($locked->stock_released_at || $locked->paid_at) {
                return;
            }
            foreach ($locked->items()->orderBy('product_id')->get() as $item) {
                Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
            }
            $locked->update(['stock_released_at' => now(), 'payment_status' => $status]);
        }, 3);
    }

    public function payment(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::where('reference', $data['orderReference'])->lockForUpdate()->firstOrFail();
            try {
                $amount = Money::minor((string) $data['amount']);
            } catch (\InvalidArgumentException) {
                abort(422);
            }
            abort_unless($amount === $order->total_minor && $data['currency'] === $order->currency, 422);
            $status = $data['transactionStatus'];
            $wasPaid = (bool) $order->paid_at;
            $order->gateway_status = $status;
            if ($status === 'Approved' && (string) $data['reasonCode'] === '1100' && ! $order->paid_at && $order->payment_status !== 'refunded') {
                // A delayed payment after reservation release must be reconciled by an administrator.
                $order->payment_status = $order->stock_released_at ? 'review' : 'paid';
                $order->paid_at = now();
            } elseif ($status === 'Refunded' && $order->paid_at) {
                $order->payment_status = 'refunded';
            }
            $order->save();
            if (! $wasPaid && $order->payment_status === 'paid') {
                DB::afterCommit(function () use ($order) {
                    $recipients = array_unique(array_filter([$order->email, SiteSetting::read('order_email')]));
                    foreach ($recipients as $recipient) {
                        try {
                            Mail::to($recipient)->send(new OrderPaid($order));
                        } catch (\Throwable $exception) {
                            Log::warning('Order confirmation could not be delivered.', ['order_id' => $order->id, 'exception' => $exception::class]);
                        }
                    }
                });
            }

            return $order;
        }, 3);
    }
}
