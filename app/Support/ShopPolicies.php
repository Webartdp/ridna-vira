<?php

namespace App\Support;

use App\Models\ContentPage;
use Illuminate\Support\Arr;

final class ShopPolicies
{
    public const LINKS = [
        '/rekvizyty-kramnytsi' => 'Реквізити крамниці',
        '/publichna-oferta' => 'Публічна оферта',
        '/oplata-i-dostavka' => 'Оплата й доставка',
        '/obmin-povernennia-ta-harantiia' => 'Обмін, повернення та гарантія',
        '/polityka-konfidentsiinosti-kramnytsi' => 'Конфіденційність',
        '/umovy-spivpratsi-z-maistramy' => 'Умови для майстрів',
    ];

    /** @return array<string, string> */
    public static function publicLinks(): array
    {
        if (! Cms::ready()) {
            return [];
        }

        $attributes = request()->attributes;
        if (! $attributes->has('shop-policies.links')) {
            $pages = ContentPage::where('section', 'pages')
                ->whereIn('path', array_keys(self::LINKS))
                ->get(['path', 'is_published', 'published_at', 'deleted_at'])
                ->filter(fn (ContentPage $page) => $page->visible())
                ->keyBy('path');

            $attributes->set('shop-policies.links', array_intersect_key(self::LINKS, $pages->all()));
        }

        return $attributes->get('shop-policies.links');
    }

    /** @return array<string, string> */
    public static function buyerLinks(): array
    {
        return Arr::except(self::publicLinks(), '/umovy-spivpratsi-z-maistramy');
    }

    /** @return array<string, string> */
    public static function makerLinks(): array
    {
        return Arr::only(self::publicLinks(), ['/umovy-spivpratsi-z-maistramy', '/polityka-konfidentsiinosti-kramnytsi']);
    }
}
