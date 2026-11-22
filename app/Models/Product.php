<?php

namespace App\Models;

use App\Services\ProductImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes;

    public const MAKER_MARKUP_PERCENT = 38;


    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $product) {
            if ($product->maker_price_minor !== null) {
                $product->price_minor = self::makerRetailPrice($product->maker_price_minor);
            }
            if ($product->isDirty('images')) {
                foreach ($product->images ?? [] as $image) {
                    app(ProductImages::class)->generate($image);
                }
            }
        });
    }

    public static function makerRetailPrice(int $minor): int
    {
        return intdiv($minor * (100 + self::MAKER_MARKUP_PERCENT) + 50, 100);
    }

    public function purchaseLimit(): int
    {
        return $this->is_active ? ($this->made_to_order ? 99 : min(99, $this->stock)) : 0;
    }

    public function availabilityLabel(): string
    {
        return $this->made_to_order ? 'Під замовлення' : ($this->stock > 0 ? 'Є в наявності' : 'Немає в наявності');
    }

    public function maker(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function imageUrl(string $path, int $width = 800): string
    {
        $disk = Storage::disk('public');
        $variant = ProductImages::variant($path, $width);

        return $disk->url($disk->exists($variant) ? $variant : $path);
    }

    public function imageSrcset(string $path): string
    {
        $disk = Storage::disk('public');
        $items = [];
        foreach ([400, 800, 1200] as $width) {
            $variant = ProductImages::variant($path, $width);
            if ($disk->exists($variant)) {
                $actual = @getimagesize($disk->path($variant));
                if ($actual) {
                    $items[$actual[0]] = $disk->url($variant).' '.$actual[0].'w';
                }
            }
        }

        return implode(', ', $items);
    }

    protected function casts(): array
    {
        return ['images' => 'array', 'price_minor' => 'integer', 'stock' => 'integer', 'maker_price_minor' => 'integer', 'made_to_order' => 'boolean', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** @return list<array{name: string, path: string}> */
    public function breadcrumbTrail(): array
    {
        $trail = [
            ['name' => 'Головна', 'path' => route('home', absolute: false)],
            ['name' => 'Крамниця', 'path' => route('shop.index', absolute: false)],
        ];
        foreach (array_filter([$this->category?->parent, $this->category]) as $category) {
            $trail[] = ['name' => $category->name, 'path' => $category->publicUrl()];
        }
        $trail[] = ['name' => $this->name, 'path' => route('shop.product', $this, false)];

        return $trail;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
