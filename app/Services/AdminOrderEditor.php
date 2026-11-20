<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminOrderEditor
{
    public function __construct(private Checkout $checkout) {}

    public function update(Order $order, array $data): Order
    {
        $data = Validator::make($data, [
            'customer_name' => 'sometimes|required|string|max:150',
            'phone' => ['sometimes', 'required', 'string', 'max:30', 'regex:/^\+?[\d ()-]{9,30}$/'],
            'email' => 'sometimes|required|email|max:190',
            'delivery_address' => 'sometimes|required|string|max:1000',
            'comment' => 'sometimes|nullable|string|max:2000',
            'tracking_number' => 'sometimes|nullable|string|max:190',
            'admin_notes' => 'sometimes|nullable|string|max:10000',
            'fulfilment_status' => 'sometimes|required|in:new,processing,shipped,completed,cancelled',
            'items' => 'sometimes|required|array|min:1',
            'items.*' => 'array:id,product_id,quantity',
            'items.*.id' => 'nullable|integer|min:1|distinct',
            'items.*.product_id' => 'nullable|integer|min:1',
            'items.*.quantity' => 'required|integer|min:1|max:99',
        ])->validate();

        return DB::transaction(function () use ($order, $data) {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $status = $data['fulfilment_status'] ?? $locked->fulfilment_status;
            $this->validateFulfilment($locked, $status);
            $cancelling = $status === 'cancelled' && $locked->fulfilment_status !== 'cancelled';

            if (array_key_exists('items', $data)) {
                if (! $locked->canEditItems()) {
                    throw ValidationException::withMessages(['items' => 'Склад можна змінювати до відправлення замовлення з післяплатою.']);
                }

                $locked->total_minor = $this->updateItems($locked, $data['items']);
            }

            $locked->fill(Arr::except($data, ['items']))->save();

            if ($cancelling && $locked->hasUnpaidReservation()) {
                $this->checkout->release($locked, cancel: true);
                $locked->refresh();
            }

            return $locked;
        }, 3);
    }

    private function validateFulfilment(Order $order, string $status): void
    {
        if ($status === $order->fulfilment_status) {
            return;
        }

        $wasSent = in_array($order->fulfilment_status, ['shipped', 'completed'], true);
        if (($order->stock_released_at || ($wasSent && ! $order->paid_at)) && in_array($status, ['new', 'processing'], true)) {
            throw ValidationException::withMessages(['fulfilment_status' => 'Для цього замовлення резерв уже знято або товар відправлено.']);
        }

        $canShipCashOnDelivery = $status === 'shipped' && $order->isCashOnDelivery()
            && $order->payment_status === 'pending' && ! $order->stock_released_at;

        if (in_array($status, ['shipped', 'completed'], true) && $order->payment_status !== 'paid' && ! $canShipCashOnDelivery) {
            throw ValidationException::withMessages(['fulfilment_status' => 'Для цього статусу потрібно підтвердити оплату.']);
        }

        if ($status === 'cancelled' && $wasSent && $order->hasUnpaidReservation()) {
            throw ValidationException::withMessages(['fulfilment_status' => 'Поверніть відправлений товар перед скасуванням замовлення.']);
        }
    }

    private function updateItems(Order $order, array $rows): int
    {
        $existingItems = $order->items()->get()->keyBy('id');
        $productIds = $existingItems->pluck('product_id')->merge(array_column($rows, 'product_id'))->filter()->unique();
        $products = Product::withTrashed()->whereKey($productIds->all())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $stockChanges = [];
        foreach ($existingItems as $item) {
            if ($item->product_id && ! $item->made_to_order) {
                $stockChanges[$item->product_id] = ($stockChanges[$item->product_id] ?? 0) + $item->quantity;
            }
        }

        $updated = [];
        $added = [];
        $seenProducts = [];
        $total = 0;
        foreach ($rows as $row) {
            $existingId = $row['id'] ?? null;
            $item = $existingItems->get((int) $existingId);
            $selectedProductId = isset($row['product_id']) ? (int) $row['product_id'] : null;
            if ($existingId && (! $item || ($selectedProductId !== null && $selectedProductId !== (int) $item->product_id))) {
                throw ValidationException::withMessages(['items' => 'Одна з позицій змінилася. Оновіть сторінку та перевірте склад замовлення.']);
            }

            $productId = $item ? $item->product_id : $selectedProductId;
            if ($productId && isset($seenProducts[$productId])) {
                throw ValidationException::withMessages(['items' => 'Додайте кожен товар один раз і вкажіть його кількість.']);
            }
            if ($productId) {
                $seenProducts[$productId] = true;
            }

            $product = $products->get($productId);
            $quantity = (int) $row['quantity'];
            if ($quantity > ($item?->quantity ?? 0) && (! $product || $product->trashed() || ! $product->is_active)) {
                throw ValidationException::withMessages(['items' => 'Вибраний товар зараз недоступний для замовлення.']);
            }

            if ($item) {
                $updated[$item->id] = $quantity;
            } else {
                $added[] = Checkout::itemSnapshot($product, $quantity);
            }

            $total += ($item?->price_minor ?? $product->price_minor) * $quantity;
            if ($total > 999999999) {
                throw ValidationException::withMessages(['items' => 'Сума замовлення перевищує допустиму. Зменште кількість товарів.']);
            }

            if ($productId && ! ($item?->made_to_order ?? $product?->made_to_order)) {
                $stockChanges[$productId] = ($stockChanges[$productId] ?? 0) - $quantity;
            }
        }

        foreach ($products as $productId => $product) {
            $change = $stockChanges[$productId] ?? 0;
            if ($change < 0 && $product->stock < -$change) {
                throw ValidationException::withMessages(['items' => "Недостатньо товару «{$product->name}». Перевірте кількість."]);
            }
            if ($change > 0) {
                Product::withTrashed()->whereKey($productId)->increment('stock', $change);
            } elseif ($change < 0) {
                Product::whereKey($productId)->decrement('stock', -$change);
            }
        }

        $removed = $existingItems->keys()->diff(array_keys($updated));
        if ($removed->isNotEmpty()) {
            $order->items()->whereIn('id', $removed)->delete();
        }
        foreach ($updated as $id => $quantity) {
            $existingItems[$id]->update(['quantity' => $quantity]);
        }
        $order->items()->createMany($added);

        return $total;
    }
}
