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
        return json_decode(SiteSetting::read('navigation', json_encode(config('site_navigation'), JSON_UNESCAPED_UNICODE)), true);
    }
}
