<?php

namespace App\Support;

use App\Models\SiteSetting;

class Site
{
    public static function socialNetworks(): array
    {
        return ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'telegram' => 'Telegram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok'];
    }

    public static function socialLinks(): array
    {
        $saved = json_decode(SiteSetting::read('social_links', '{}'), true) ?? [];
        $links = [];
        foreach (self::socialNetworks() as $network => $label) {
            $url = trim($saved[$network] ?? '');
            if (filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https') {
                $links[$network] = ['label' => $label, 'url' => $url];
            }
        }

        return $links;
    }

    public static function navigation(): array
    {
        return self::links('navigation', config('site_navigation'));
    }

    public static function links(string $setting, array $defaults = []): array
    {
        return self::withoutEncyclopediaLinks(json_decode(SiteSetting::read($setting, json_encode($defaults)), true) ?? []);
    }

    private static function withoutEncyclopediaLinks(array $items): array
    {
        $visible = [];
        foreach ($items as $item) {
            if (self::isEncyclopediaLink($item['url'] ?? '')) {
                continue;
            }
            if (isset($item['children'])) {
                $item['children'] = self::withoutEncyclopediaLinks($item['children']);
            }
            $visible[] = $item;
        }

        return $visible;
    }

    public static function isEncyclopediaLink(string $url): bool
    {
        $parts = parse_url(trim($url));

        return in_array(strtolower(rtrim($parts['host'] ?? '', '.')), ['wiki.svit.in.ua', 'www.wiki.svit.in.ua'], true)
            || rtrim($parts['path'] ?? '', '/') === '/pro-tsentr/entsyklopediia';
    }

    public static function removeEncyclopediaLinksFromHtml(string $html): string
    {
        if (! preg_match('~wiki\.svit\.in\.ua|/pro-tsentr/entsyklopediia~i', $html)) {
            return $html;
        }

        return preg_replace_callback('~<a\b[^>]*>(.*?)</a\s*>~is', function (array $match) {
            $link = Cms::document($match[0])->getElementsByTagName('a')->item(0);
            if (! $link || ! self::isEncyclopediaLink($link->getAttribute('href'))) {
                return $match[0];
            }

            return trim($link->textContent) === 'Енциклопедія' ? '' : $match[1];
        }, $html);
    }
}
