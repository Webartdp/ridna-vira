<?php

namespace App\Models;

use App\Services\ProductImages;
use App\Support\ContentImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::saving(function (self $product) {
            if ($product->isDirty('images')) {
                foreach ($product->images ?? [] as $image) {
                    app(ProductImages::class)->generate($image);
                }
            }
        });
    }

    public function imageUrl(string $path, int $width = 800): string
    {
        $disk = Storage::disk('public');
        $variant = ProductImages::variant($path, $width);

        return $disk->url($disk->exists($variant) ? $variant : $path);
    }

    public function imageSrcset(string $path): string
    {
        return implode(', ', array_map(
            fn (array $image) => $image['src'].' '.$image['width'].'w',
            ContentImages::visual($path)['variants'],
        ));
    }

    protected function casts(): array
    {
        return ['images' => 'array', 'price_minor' => 'integer', 'stock' => 'integer', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
