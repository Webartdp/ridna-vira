<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductImages;
use App\Services\TelegramAlerts;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class MakerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->roleName(), ['user', 'maker'], true), 403);

        return view('maker.index', ['products' => $user->roleName() === 'maker'
            ? Product::where('maker_id', $user->id)->with('category.parent')->latest('id')->paginate(12)
            : null, 'categories' => ProductCategory::catalogue()]);
    }

    public function payouts(Request $request)
    {
        abort_unless($request->user()->roleName() === 'maker', 403);

        $items = OrderItem::where('maker_id', $request->user()->id)
            ->whereNotNull('maker_price_minor')->with('order:id,reference,payment_status,fulfilment_status,created_at')
            ->whereHas('order', fn ($query) => $query->whereNotNull('paid_at'))
            ->latest('id')->paginate(12);

        return view('maker.payouts', compact('items'));
    }

    public function activate(Request $request)
    {
        $user = $request->user();
        abort_unless($user->roleName() === 'user', 403);
        $user->newQuery()->whereKey($user->id)->where('role', 'user')->update(['role' => 'maker']);

        return redirect()->route('maker.index');
    }

    public function edit(Request $request, Product $product)
    {
        $this->authorizeOwner($request, $product);

        return view('maker.edit', ['product' => $product, 'categories' => ProductCategory::catalogue()]);
    }

    public function store(Request $request)
    {
        return $this->saveProduct($request);
    }

    public function update(Request $request, Product $product)
    {
        $this->authorizeOwner($request, $product);

        return $this->saveProduct($request, $product);
    }

    public function hide(Request $request, Product $product)
    {
        $this->authorizeOwner($request, $product);
        Product::whereKey($product->id)->where('maker_id', $request->user()->id)
            ->update(['is_active' => false, 'moderation_token' => null]);

        return back()->with('status', 'Виріб приховано.');
    }

    public function destroy(Request $request, Product $product)
    {
        $this->authorizeOwner($request, $product);
        Product::whereKey($product->id)->where('maker_id', $request->user()->id)->delete();

        return redirect()->route('maker.index')->with('status', 'Виріб видалено.');
    }

    private function authorizeOwner(Request $request, Product $product): void
    {
        abort_unless($request->user()->roleName() === 'maker' && $product->maker_id === $request->user()->id, 404);
    }

    private function saveProduct(Request $request, ?Product $product = null)
    {
        abort_unless($request->user()->roleName() === 'maker', 403);
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'required|string|max:5000',
            'product_category_id' => 'sometimes|nullable|integer|exists:product_categories,id',
            'price' => ['required', 'string', 'regex:/^\d{1,7}(?:[.,]\d{1,2})?$/D'],
            'stock' => 'nullable|integer|min:0|max:1000000',
            'stock_before' => $product ? 'required|integer|min:0' : 'nullable|integer',
            'remove_images' => 'nullable|array|max:8',
            'remove_images.*' => 'string|max:255',
            'images' => $product ? 'nullable|array|max:8' : 'required|array|min:1|max:8',
            'images.*' => 'bail|required|file|max:2048|mimes:jpg,jpeg,png,webp|dimensions:max_width=4000,max_height=4000',
        ], [
            'images.required' => 'Додайте щонайменше одне фото виробу.',
            'images.array' => 'Виберіть фотографії виробу ще раз.',
            'images.min' => 'Додайте щонайменше одне фото виробу.',
            'images.max' => 'Можна завантажити до 8 фотографій.',
            'images.*.required' => 'Виберіть фото №:position ще раз.',
            'images.*.uploaded' => 'Фото №:position не вдалося завантажити. Виберіть менший файл і спробуйте ще раз.',
            'images.*.file' => 'Фото №:position має бути файлом зображення.',
            'images.*.max' => 'Фото №:position важить понад 2 МБ. Виберіть менший файл.',
            'images.*.mimes' => 'Фото №:position має бути у форматі JPG, PNG або WebP.',
            'images.*.dimensions' => 'Фото №:position має бути не більше 4000 × 4000 пікселів.',
        ]);
        $price = Money::minor($data['price']);
        if ($price < 1 || Product::makerRetailPrice($price) > 999999999) {
            throw ValidationException::withMessages(['price' => 'Ціна має бути більшою за 0, а роздрібна ціна — до 9 999 999,99 грн.']);
        }

        if (array_sum(array_map(fn ($file) => $file->getSize(), $data['images'] ?? [])) > 6 * 1024 * 1024) {
            throw ValidationException::withMessages(['images' => 'Загальний розмір фотографій має бути до 6 МБ.']);
        }

        $images = [];
        try {
            foreach ($data['images'] ?? [] as $file) {
                $path = $file->store('products/makers', 'public');
                if ($path === false) {
                    throw ValidationException::withMessages(['images' => 'Фото не вдалося зберегти. Спробуйте ще раз.']);
                }
                $images[] = $path;
            }

            DB::transaction(function () use ($request, $product, $data, $price, $images) {
                $locked = $product
                    ? Product::where('maker_id', $request->user()->id)->lockForUpdate()->findOrFail($product->id)
                    : new Product(['maker_id' => $request->user()->id,
                        'slug' => (Str::slug(Str::limit($data['name'], 100, '')) ?: 'vyrob').'-'.Str::lower((string) Str::ulid())]);
                if ($product && (int) $data['stock_before'] !== $locked->stock) {
                    throw ValidationException::withMessages(['stock' => 'Залишок змінився. Відкрийте виріб повторно та перевірте кількість.']);
                }
                $photos = array_values(array_diff($locked->images ?? [], $data['remove_images'] ?? []));
                $photos = [...$photos, ...$images];
                if (count($photos) < 1 || count($photos) > 8) {
                    throw ValidationException::withMessages(['images' => 'Залиште або завантажте від 1 до 8 фотографій.']);
                }
                $stock = $data['stock'] ?? null;
                $locked->fill([
                    ...array_intersect_key($data, ['product_category_id' => true]),
                    'name' => $data['name'],
                    'description' => '<p>'.nl2br(e($data['description']), false).'</p>',
                    'images' => $photos,
                    'maker_price_minor' => $price,
                    'made_to_order' => $stock === null,
                    'stock' => $stock ?? 0,
                    'is_active' => false,
                ]);
                if (! $locked->isDirty() && $locked->moderation_token !== null) {
                    return;
                }

                $locked->moderation_token = Str::random(24);
                $locked->save();
                app(TelegramAlerts::class)->productAdded($locked, $request->user(), $data['description']);
            }, 3);
        } catch (Throwable $error) {
            $disk = Storage::disk('public');
            foreach ($images as $path) {
                $disk->delete([$path, ProductImages::variant($path, 400), ProductImages::variant($path, 800), ProductImages::variant($path, 1200)]);
            }

            throw $error;
        }

        return redirect()->route('maker.index')->with('status', 'Виріб збережено. Він з’явиться в крамниці після схвалення адміністратором.');
    }
}
