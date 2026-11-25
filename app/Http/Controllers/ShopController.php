<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\NovaPoshta;
use App\Services\WayForPay;
use App\Support\Money;
use App\Support\ShopCatalogueSeo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        if ($request->filled('category') && ! $request->filled('maker')) {
            $category = ProductCategory::withPublishedProducts()->where('slug', $request->string('category', strict: true)->toString())->first();
            if ($category) {
                $query = http_build_query($request->except('category'), encoding_type: PHP_QUERY_RFC3986);

                return redirect()->to($category->publicUrl().($query !== '' ? '?'.$query : ''), 301);
            }
        }

        return $this->catalogue($request);
    }

    public function category(Request $request, ProductCategory $category)
    {
        abort_unless(ProductCategory::withPublishedProducts()->whereKey($category->id)->exists(), 404);

        return $this->catalogue($request, $category->load('parent'));
    }

    private function catalogue(Request $request, ?ProductCategory $browseCategory = null)
    {
        $maker = null;
        if ($request->filled('maker')) {
            $makerId = filter_var($request->query('maker'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            abort_if($makerId === false, 404);
            $maker = User::where('role', 'maker')->where('is_active', true)
                ->whereIn('id', Product::where('is_active', true)->select('maker_id'))
                ->findOrFail($makerId, ['id', 'name']);
        }

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
        $selectedCategory = $browseCategory?->slug ?? $request->string('category', strict: true)->toString();

        $catalogue = Product::where('is_active', true)
            ->when($maker, fn ($query) => $query->where('maker_id', $maker->id))
            ->when($selectedCategory !== '', fn ($query) => $query->whereHas('category', fn ($category) => $category->where(fn ($match) => $match
                ->where('slug', $selectedCategory)
                ->orWhereHas('parent', fn ($parent) => $parent->where('slug', $selectedCategory)))))
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.mb_substr($request->string('q')->toString(), 0, 100).'%'));

        $sliderMaximum = max(10_000, (int) (clone $catalogue)->max('price_minor'), $minimum ?? 0, $maximum ?? 0);

        $products = $catalogue
            ->when($minimum !== null, fn ($query) => $query->where('price_minor', '>=', $minimum))
            ->when($maximum !== null, fn ($query) => $query->where('price_minor', '<=', $maximum))
            ->latest()->orderByDesc('id')->paginate(12)->withQueryString();
        abort_if($products->currentPage() > $products->lastPage(), 404);

        $categoryLinks = ProductCategory::withPublishedProducts()
            ->where('parent_id', $browseCategory?->id)->orderBy('name')->get();
        $catalogueCopy = $products->onFirstPage() && ! $request->anyFilled([...ShopCatalogueSeo::FILTERS, 'category'])
            ? config($browseCategory ? 'shop_catalogue_copy.categories.'.$browseCategory->slug : 'shop_catalogue_copy.catalogue', [])
            : [];

        return view('shop.catalogue', [
            'products' => $products,
            'categories' => ProductCategory::catalogue(),
            'categoryLinks' => $categoryLinks,
            'browseCategory' => $browseCategory,
            'categoryDescription' => $browseCategory ? ShopCatalogueSeo::description($browseCategory) : null,
            'catalogueCopy' => $catalogueCopy,
            'selectedCategory' => $selectedCategory,
            'sliderMaximum' => $sliderMaximum,
            'maker' => $maker,
        ]);
    }

    public function show(Product $product)
    {
        abort_unless($product->is_active, 404);
        $product->load([
            'category.parent',
            'maker' => fn ($query) => $query->select(['id', 'name'])->where('role', 'maker')->where('is_active', true),
        ]);

        return view('shop.product', compact('product'));
    }

    public function cart(Cart $cart, WayForPay $gateway, NovaPoshta $carrier)
    {
        $token = session('checkout_token');
        if (! $token || Order::withTrashed()->where('checkout_token', $token)->exists()) {
            session()->put('checkout_token', (string) Str::uuid());
        }

        $items = $cart->items();

        $paymentMethods = PaymentMethod::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        $wayForPayReady = $gateway->ready();
        $availablePaymentCodes = $paymentMethods->filter(fn (PaymentMethod $method) => $method->availableForCheckout($wayForPayReady))->pluck('code');

        return view('shop.cart', [
            'items' => $items,
            'total' => array_sum(array_column($items, 'total')),
            'paymentMethods' => $paymentMethods,
            'availablePaymentCodes' => $availablePaymentCodes,
            'ordersEnabled' => $this->ordersEnabled(),
            'novaPoshtaReady' => $carrier->configured(),
        ]);
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

    public function checkout(Request $request, Checkout $checkout)
    {
        abort_unless($this->ordersEnabled(), 503, 'Оформлення замовлень тимчасово недоступне.');
        $data = $request->validate([
            'customer_name' => 'required|string|max:150', 'email' => 'required|email|max:190',
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d ()-]{9,30}$/'],
            'delivery_method' => 'required|in:nova_poshta',
            'payment_method' => 'sometimes|required|string|max:48',
            'delivery_address' => 'required|string|max:1000', 'comment' => 'nullable|string|max:2000',
            'checkout_token' => 'required|uuid', 'consent' => 'accepted',
        ]);
        abort_unless(hash_equals((string) session('checkout_token'), $data['checkout_token']), 419);
        $token = $data['checkout_token'];
        $paymentCode = $data['payment_method'] ?? PaymentMethod::WAYFORPAY;
        unset($data['checkout_token'], $data['consent'], $data['payment_method']);
        if ($request->user()) {
            $data['email'] = $request->user()->email;
        }
        $order = $checkout->create($data, session('cart', []), $token, $request->user()?->id, $paymentCode);
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
        abort_unless($order->isWayForPay(), 409, 'Для цього замовлення обрано інший спосіб оплати.');
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
