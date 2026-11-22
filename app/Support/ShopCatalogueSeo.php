<?php

namespace App\Support;

use App\Models\ProductCategory;
use DOMDocument;
use DOMXPath;

final class ShopCatalogueSeo
{
    public const FILTERS = ['q', 'search', 'price_from', 'price_to', 'maker'];

    public static function title(ProductCategory $category): string
    {
        $context = $category->parent ? ' · '.$category->parent->name : '';

        return $category->name.$context.' — Крамниця «Рідна Віра»';
    }

    public static function description(ProductCategory $category): string
    {
        return config('seo.shop_categories.'.$category->slug) ?? Seo::text(
            $category->name.($category->parent ? ' · '.$category->parent->name : '')
            .' у крамниці «Рідна Віра». Перегляньте вироби, їхні світлини, ціни та наявність.',
        );
    }

    /** @return list<array{name: string, path: string}> */
    public static function categoryTrail(ProductCategory $category): array
    {
        $trail = [
            ['name' => 'Головна', 'path' => route('home', absolute: false)],
            ['name' => 'Крамниця', 'path' => route('shop.index', absolute: false)],
        ];
        if ($category->parent) {
            $trail[] = ['name' => $category->parent->name, 'path' => $category->parent->publicUrl()];
        }
        $trail[] = ['name' => $category->name, 'path' => $category->publicUrl()];

        return $trail;
    }

    public static function itemList(DOMDocument $document): ?array
    {
        $items = [];
        foreach ((new DOMXPath($document))->query('//a[@data-shop-product]') as $link) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'url' => Seo::url($link->getAttribute('href')),
                'name' => trim($link->textContent),
            ];
        }

        return $items ? ['@type' => 'ItemList', 'itemListElement' => $items] : null;
    }
}
