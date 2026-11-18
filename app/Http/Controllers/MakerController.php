<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ProductImages;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Throwable;

class MakerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->roleName(), ['user', 'maker'], true), 403);

        return view('maker.index', ['products' => $user->roleName() === 'maker'
            ? Product::where('maker_id', $user->id)->latest('id')->paginate(12)
            : null]);
    }

    public function activate(Request $request)
    {
        $user = $request->user();
        abort_unless($user->roleName() === 'user', 403);
        $user->newQuery()->whereKey($user->id)->where('role', 'user')->update(['role' => 'maker']);

        return redirect()->route('maker.index');
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->roleName() === 'maker', 403);
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'required|string|max:5000',
            'price' => ['required', 'string', 'regex:/^\d{1,7}(?:[.,]\d{1,2})?$/D'],
            'stock' => 'nullable|integer|min:0|max:1000000',
            'images' => 'required|array|min:1|max:8',
            'images.*' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb'), 'dimensions:max_width=4000,max_height=4000'],
        ]);
        $price = Money::minor($data['price']);
        if ($price < 1 || Product::makerRetailPrice($price) > 999999999) {
            throw ValidationException::withMessages(['price' => 'Ціна має бути більшою за 0, а роздрібна ціна — до 9 999 999,99 грн.']);
        }

        if (array_sum(array_map(fn ($file) => $file->getSize(), $data['images'])) > 6 * 1024 * 1024) {
            throw ValidationException::withMessages(['images' => 'Загальний розмір фотографій має бути до 6 МБ.']);
        }

        $images = [];
        try {
            foreach ($data['images'] as $file) {
                $path = $file->store('products/makers', 'public');
                if ($path === false) {
                    throw ValidationException::withMessages(['images' => 'Фото не вдалося зберегти. Спробуйте ще раз.']);
                }
                $images[] = $path;
            }

            $stock = $data['stock'] ?? null;
            Product::create([
                'name' => $data['name'],
                'slug' => (Str::slug(Str::limit($data['name'], 100, '')) ?: 'vyrob').'-'.Str::lower((string) Str::ulid()),
                'description' => '<p>'.nl2br(e($data['description']), false).'</p>',
                'images' => $images,
                'maker_id' => $request->user()->id,
                'maker_price_minor' => $price,
                'made_to_order' => $stock === null,
                'stock' => $stock ?? 0,
                'is_active' => false,
            ]);
        } catch (Throwable $error) {
            $disk = Storage::disk('public');
            foreach ($images as $path) {
                $disk->delete([$path, ProductImages::variant($path, 400), ProductImages::variant($path, 800), ProductImages::variant($path, 1200)]);
            }

            throw $error;
        }

        return redirect()->route('maker.index')->with('status', 'Виріб додано. Він з’явиться в крамниці після схвалення адміністратором.');
    }
}
