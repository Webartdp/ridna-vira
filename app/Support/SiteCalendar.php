<?php

namespace App\Support;

use App\Models\ContentPage;
use App\Models\SiteSetting;
use Illuminate\Support\Str;

class SiteCalendar
{
    public static function data(): array
    {
        $calendar = json_decode(SiteSetting::read('calendar', json_encode(config('faith_calendar'), JSON_UNESCAPED_UNICODE)), true);
        $pages = Cms::ready() ? ContentPage::publicOverrides()->where('section', 'holidays')->get()->keyBy('path') : collect();
        foreach ($calendar['months'] as &$holidays) {
            foreach ($holidays as $key => &$holiday) {
                $holiday['slug'] ??= Str::slug($holiday['name']);
                $page = $pages->get('/ridna-vira/kalendar/'.$holiday['slug']);
                if ($page && ! $page->visible()) {
                    unset($holidays[$key]);
                } elseif ($page?->is_override) {
                    $holiday['name'] = $page->title;
                }
            }
            unset($holiday);
        }
        unset($holidays);

        return $calendar;
    }
}
