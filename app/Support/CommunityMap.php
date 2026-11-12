<?php

namespace App\Support;

use App\Models\SiteSetting;

final class CommunityMap
{
    public static function circles(): array
    {
        return self::setting('community_circles', config('community_map.circles'));
    }

    public static function locations(): array
    {
        $labels = array_column(self::circles(), 'label', 'id');

        return array_map(function (array $location) use ($labels) {
            $location['circleLabel'] = $labels[$location['circle']] ?? $location['circleLabel'] ?? '';
            $location['entries'] = array_values($location['entries']);

            return $location;
        }, self::setting('communities', config('communities')));
    }

    private static function setting(string $key, array $default): array
    {
        return array_values(json_decode(SiteSetting::read($key, 'null'), true) ?? $default);
    }
}
