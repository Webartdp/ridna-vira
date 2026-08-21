<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

final class HolidayController extends Controller
{
    public function __invoke(string $holiday): View
    {
        $calendar = config('faith_calendar');
        $descriptions = config('faith_holidays', []);
        $months = $calendar['months'] ?? [];

        foreach ($months as $month => $items) {
            foreach ($items as $item) {
                $name = (string) ($item['name'] ?? '');
                $slug = (string) ($item['slug'] ?? Str::slug($name));

                if ($slug !== $holiday) {
                    continue;
                }

                $item['description'] = (string) ($item['description'] ?? ($descriptions[$name] ?? ''));

                $contentPath = resource_path('content/holidays/'.$slug.'.html');
                $content = is_file($contentPath) ? (string) file_get_contents($contentPath) : null;

                return view('pages.holiday', [
                    'holiday' => $item,
                    'holidaySlug' => $slug,
                    'month' => $month,
                    'content' => $content,
                ]);
            }
        }

        abort(404, 'Свято не знайдено.');
    }
}
