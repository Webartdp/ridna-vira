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

                $storageContentPath = storage_path('app/content/holidays/'.$slug.'.html');
                $resourceContentPath = resource_path('content/holidays/'.$slug.'.html');
                $content = null;
                $contentSource = null;

                if (is_file($storageContentPath)) {
                    $content = (string) file_get_contents($storageContentPath);
                    $contentSource = 'imported-full';
                } elseif (is_file($resourceContentPath)) {
                    $content = (string) file_get_contents($resourceContentPath);
                    $contentSource = 'editable';
                }

                return view('pages.holiday', [
                    'holiday' => $item,
                    'holidaySlug' => $slug,
                    'month' => $month,
                    'content' => $content,
                    'contentSource' => $contentSource,
                ]);
            }
        }

        abort(404, 'Свято не знайдено.');
    }
}
