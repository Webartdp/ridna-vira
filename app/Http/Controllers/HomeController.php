<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

final class HomeController extends Controller
{
    public function __invoke(): View
    {
        $today = CarbonImmutable::now('Europe/Kyiv')->startOfDay();
        $holidays = [];
        $icons = [
            'Велес' => 'veles', 'Коляда' => 'kolyada', 'Коляди' => 'kolyada',
            'Водосвяття' => 'vodo', 'Мокош' => 'mokosh', 'Радогощ' => 'radogosh',
            'Спас' => 'spas', 'Колод' => 'kolodiy', 'Великдень' => 'velykden',
            'Ярило' => 'yarylo', 'Перун' => 'perun', 'Купал' => 'kupalo', 'Трійця' => 'zeleni',
        ];

        foreach (array_values(config('faith_calendar.months', [])) as $month => $items) {
            foreach ($items as $item) {
                // Search a full leap-year cycle so February 29 is never normalized to March 1.
                for ($year = $today->year; $year <= $today->year + 4; $year++) {
                    $day = (int) $item['day'];
                    // The calendar's ** note defines Viy as the last day of February.
                    if ($month === 1 && $day === 29 && str_contains($item['name'], '**')) {
                        $day = $today->setDate($year, 2, 1)->daysInMonth;
                    }
                    if (! checkdate($month + 1, $day, $year)) {
                        continue;
                    }
                    $date = $today->setDate($year, $month + 1, $day);
                    if ($date->lessThan($today)) {
                        continue;
                    }
                    $image = 'wheel';
                    foreach ($icons as $fragment => $icon) {
                        if (str_contains($item['name'], $fragment)) {
                            $image = 'holiday-'.$icon;
                            break;
                        }
                    }
                    $holidays[] = [
                        'name' => $item['name'],
                        'slug' => $item['slug'] ?? Str::slug($item['name']),
                        'date' => $date,
                        'image' => $image,
                    ];
                    break;
                }
            }
        }
        usort($holidays, static fn (array $a, array $b): int => $a['date']->getTimestamp() <=> $b['date']->getTimestamp());

        // The import has no publication dates. Show the last imported entries without inventing dates.
        $path = storage_path('app/content/articles/manifest.json');
        $manifest = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
        $articles = [];
        foreach (array_reverse($manifest['articles'] ?? [], true) as $key => $entry) {
            $slug = (string) ($entry['slug'] ?? $key);
            $relativePath = trim((string) ($entry['path'] ?? ''), '/\\');
            if (! preg_match('/^[a-z0-9\-]+$/', $slug) || empty($entry['title']) || $relativePath === '' || str_contains($relativePath, '..')) {
                continue;
            }
            if (! is_file(storage_path('app/content/articles/'.$relativePath))) {
                continue;
            }
            $articles[] = ['title' => $entry['title'], 'slug' => $slug];
            if (count($articles) === 3) {
                break;
            }
        }

        return view('home', ['upcomingHolidays' => array_slice($holidays, 0, 4), 'homeArticles' => $articles]);
    }
}
