<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class Cart
{
    public function items(): array
    {
        $cart = session('cart', []);
        $products = Product::whereIn('id', array_keys($cart))->get()->keyBy('id');
        $items = [];
        foreach ($cart as $id => $quantity) {
            $product = $products->get($id);
            if ($product) {
                $items[] = ['product' => $product, 'quantity' => (int) $quantity, 'total' => $product->price_minor * (int) $quantity];
            } else {
                unset($cart[$id]);
            }
        }
        session(['cart' => $cart]);

        return $items;
    }

    public function set(Product $product, int $quantity): void
    {
        $cart = session('cart', []);
        if ($quantity === 0) {
            unset($cart[$product->id]);
        } else {
            if (! isset($cart[$product->id]) && count($cart) >= 50) {
                throw ValidationException::withMessages(['cart' => 'У кошику може бути до 50 різних товарів.']);
            }
            if (! $product->is_active || $quantity > $product->stock || $quantity > 99) {
                throw ValidationException::withMessages(['cart' => 'Цієї кількості товару немає в наявності.']);
            }
            $cart[$product->id] = $quantity;
        }
        session(['cart' => $cart]);
    }

    public function total(): int
    {
        return array_sum(array_column($this->items(), 'total'));
    }
}
