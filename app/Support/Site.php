<?php

namespace App\Support;

use App\Models\SiteSetting;

class Site
{
    public static function navigation(): array
    {
        return json_decode(SiteSetting::read('navigation', json_encode(config('site_navigation'), JSON_UNESCAPED_UNICODE)), true);
    }
}
