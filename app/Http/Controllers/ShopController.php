<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\NovaPoshta;
use App\Services\WayForPay;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $priceRules = ['nullable', 'regex:/^\d{1,7}(?:[.,]\d{1,2})?$/D'];
        $prices = $request->validate([
            'price_from' => $priceRules,
            'price_to' => $priceRules,
        ], [
            'price_from.regex' => 'Вкажіть коректну ціну від.',
            'price_to.regex' => 'Вкажіть коректну ціну до.',
        ]);
        $minimum = isset($prices['price_from']) ? Money::minor($prices['price_from']) : null;
        $maximum = isset($prices['price_to']) ? Money::minor($prices['price_to']) : null;

        $products = Product::where('is_active', true)
            ->when($request->filled('category'), fn ($query) => $query->whereHas('category', fn ($category) => $category->where(fn ($match) => $match
                ->where('slug', $request->string('category')->toString())
                ->orWhereHas('parent', fn ($parent) => $parent->where('slug', $request->string('category')->toString())))))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.mb_substr($request->string('q')->toString(), 0, 100).'%'))
            ->when($minimum !== null, fn ($query) => $query->where('price_minor', '>=', $minimum))
            ->when($maximum !== null, fn ($query) => $query->where('price_minor', '<=', $maximum))
            ->latest()->paginate(12)->withQueryString();

        return view('shop.catalogue', ['products' => $products, 'categories' => ProductCategory::catalogue()]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        return view('shop.product', compact('product'));
    }

    public function cart(Cart $cart, WayForPay $gateway, NovaPoshta $carrier)
    {
        $token = session('checkout_token');
        if (! $token || Order::where('checkout_token', $token)->exists()) {
            session()->put('checkout_token', (string) Str::uuid());
        }

        $items = $cart->items();

        return view('shop.cart', ['items' => $items, 'total' => array_sum(array_column($items, 'total')), 'paymentsReady' => $gateway->ready(), 'ordersEnabled' => $this->ordersEnabled(), 'novaPoshtaReady' => $carrier->configured()]);
    }

    public function updateCart(Request $request, Product $product, Cart $cart)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:99', 'add' => 'sometimes|boolean']);
        $quantity = (int) $data['quantity'];
        if ($request->boolean('add')) {
            $quantity += (int) session('cart.'.$product->id, 0);
        }
        $cart->set($product, $quantity);

        if ($request->expectsJson()) {
            $items = $cart->items();

            return response()->json([
                'quantity' => $quantity,
                'unitPrice' => Money::display($product->price_minor),
                'lineTotal' => Money::display($product->price_minor * $quantity),
                'total' => Money::display(array_sum(array_column($items, 'total'))),
                'count' => array_sum(array_column($items, 'quantity')),
            ]);
        }

        return redirect()->route('shop.cart')->with('status', 'Кошик оновлено.');
    }

    public function checkout(Request $request, Checkout $checkout, WayForPay $gateway)
    {
        abort_unless($this->ordersEnabled(), 503, 'Оформлення замовлень тимчасово недоступне.');
        $data = $request->validate([
            'customer_name' => 'required|string|max:150', 'email' => 'required|email|max:190',
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d ()-]{9,30}$/'],
            'delivery_method' => 'required|in:nova_poshta',
            'payment_method' => 'sometimes|required|in:wayforpay,cod',
            'delivery_address' => 'required|string|max:1000', 'comment' => 'nullable|string|max:2000',
            'checkout_token' => 'required|uuid', 'consent' => 'accepted',
        ]);
        abort_unless(hash_equals((string) session('checkout_token'), $data['checkout_token']), 419);
        $data['payment_method'] ??= 'wayforpay';
        abort_if($data['payment_method'] === 'wayforpay' && ! $gateway->ready(), 503, 'Онлайн-оплата тимчасово недоступна.');
        $token = $data['checkout_token'];
        unset($data['checkout_token'], $data['consent']);
        if ($request->user()) {
            $data['email'] = $request->user()->email;
        }
        $order = $checkout->create($data, session('cart', []), $token, $request->user()?->id);
        session()->forget('cart');

        return redirect($order->publicUrl());
    }

    public function order(string $token)
    {
        $order = Order::where('access_token', $token)->with('items')->firstOrFail();

        return response()->view('shop.order', compact('order'))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex');
    }

    public function pay(string $token, WayForPay $gateway, Checkout $checkout)
    {
        $order = Order::where('access_token', $token)->firstOrFail();
        abort_if($order->isCashOnDelivery(), 409, 'Для цього замовлення обрано оплату при отриманні.');
        if ($order->reserved_until->isPast() && ! $order->paid_at) {
            $checkout->release($order);
        }
        abort_unless($order->fresh()->payment_status === 'pending' && $order->reserved_until->isFuture(), 409, 'Це замовлення вже оплачене або термін його оплати минув.');

        return response()->view('shop.payment', ['fields' => $gateway->purchase($order)])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex');
    }

    public function webhook(Request $request, WayForPay $gateway, Checkout $checkout)
    {
        $data = $request->json()->all();
        $gateway->verify($data);
        $order = $checkout->payment($data);

        return response()->json($gateway->acknowledgement($order->reference));
    }

    private function ordersEnabled(): bool
    {
        return filter_var(SiteSetting::read('shop_enabled', '1'), FILTER_VALIDATE_BOOLEAN);
    }

    public function returned(string $token)
    {
        // Browser redirects are not proof of payment; only the verified server callback changes status.
        return redirect()->route('shop.order', $token, 303);
    }
}
