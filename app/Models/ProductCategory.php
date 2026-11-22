<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $guarded = ['id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function publishedProducts(): HasMany
    {
        return $this->hasMany(Product::class)->where('is_active', true);
    }

    public function scopeWithPublishedProducts(Builder $query): Builder
    {
        return $query->where(fn (Builder $category) => $category
            ->whereHas('publishedProducts')->orWhereHas('children.publishedProducts'));
    }

    public function publicUrl(): string
    {
        return route('shop.category', ['category' => $this->slug], false);
    }

    public static function catalogue(): Collection
    {
        return self::whereNull('parent_id')->with('children')->orderBy('name')->get();
    }
}
