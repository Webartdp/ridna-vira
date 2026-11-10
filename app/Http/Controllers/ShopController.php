<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\WayForPay;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::where('is_active', true)
            ->when($request->filled('category'), fn ($query) => $query->whereHas('category', fn ($category) => $category->where('slug', $request->string('category')->toString())))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.mb_substr($request->string('q')->toString(), 0, 100).'%'))
            ->latest()->paginate(12)->withQueryString();

        return view('shop.catalogue', ['products' => $products, 'categories' => ProductCategory::orderBy('name')->get()]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);

        return view('shop.product', compact('product'));
    }

    public function cart(Cart $cart, WayForPay $gateway)
    {
        $token = session('checkout_token');
        if (! $token || Order::where('checkout_token', $token)->exists()) {
            session()->put('checkout_token', (string) Str::uuid());
        }

        return view('shop.cart', ['items' => $cart->items(), 'total' => $cart->total(), 'paymentsReady' => $gateway->ready()]);
    }

    public function updateCart(Request $request, Product $product, Cart $cart)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0|max:99', 'add' => 'sometimes|boolean']);
        $quantity = (int) $data['quantity'];
        if ($request->boolean('add')) {
            $quantity += (int) session('cart.'.$product->id, 0);
        }
        $cart->set($product, $quantity);

        return redirect()->route('shop.cart')->with('status', 'Кошик оновлено.');
    }

    public function checkout(Request $request, Checkout $checkout, WayForPay $gateway)
    {
        abort_unless($gateway->ready(), 503, 'Оплата тимчасово недоступна.');
        $data = $request->validate([
            'customer_name' => 'required|string|max:150', 'email' => 'required|email|max:190',
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d ()-]{9,30}$/'],
            'delivery_method' => 'required|in:nova_poshta,ukrposhta,pickup',
            'delivery_address' => 'required|string|max:1000', 'comment' => 'nullable|string|max:2000',
            'checkout_token' => 'required|uuid', 'consent' => 'accepted',
        ]);
        abort_unless(hash_equals((string) session('checkout_token'), $data['checkout_token']), 419);
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

    public function returned(string $token)
    {
        // Browser redirects are not proof of payment; only the verified server callback changes status.
        return redirect()->route('shop.order', $token, 303);
    }
}
