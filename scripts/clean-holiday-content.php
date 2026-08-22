<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Removes imported duplicate title headings from holiday content files.
 * Page templates already render the holiday title, so imported article bodies
 * should start with byline/body text or a real section heading.
 */

$root = dirname(__DIR__);
$calendar = require $root.'/config/faith_calendar.php';
$outputDir = $root.'/storage/app/content/holidays';
$reportPath = $root.'/storage/app/content/holidays-cleanup.json';

ensureDirectory($outputDir);
ensureDirectory(dirname($reportPath));

$report = [
    'generated_at' => date(DATE_ATOM),
    'cleaned' => [],
    'missing' => [],
];

foreach (($calendar['months'] ?? []) as $monthName => $items) {
    foreach ($items as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $slug = (string) ($item['slug'] ?? Str::slug($name));
        $path = $outputDir.'/'.$slug.'.html';

        if (!is_file($path)) {
            $report['missing'][] = [
                'slug' => $slug,
                'name' => $name,
                'month' => $monthName,
            ];
            continue;
        }

        $html = (string) file_get_contents($path);
        $cleaned = removeDuplicateTitleHeadings($html, $name);

        if ($cleaned === $html) {
            continue;
        }

        file_put_contents($path, rtrim($cleaned)."\n");
        $report['cleaned'][] = [
            'slug' => $slug,
            'name' => $name,
            'month' => $monthName,
        ];

        echo '[CLEAN] '.$name.PHP_EOL;
    }
}

file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

echo PHP_EOL;
echo 'Прибрано дубльованих заголовків: '.count($report['cleaned']).PHP_EOL;
echo 'Немає файлу: '.count($report['missing']).PHP_EOL;
echo 'Звіт: storage/app/content/holidays-cleanup.json'.PHP_EOL;

function removeDuplicateTitleHeadings(string $html, string $holidayName): string
{
    $removed = false;

    return preg_replace_callback(
        '~<h[1-4]\b[^>]*>(.*?)</h[1-4]>\s*~isu',
        static function (array $match) use ($holidayName, &$removed): string {
            if ($removed) {
                return $match[0];
            }

            $heading = compactText(strip_tags($match[1]));
            if (!headingMatchesHoliday($heading, $holidayName)) {
                return $match[0];
            }

            $removed = true;

            return '';
        },
        $html,
        1
    ) ?? $html;
}

function headingMatchesHoliday(string $heading, string $holidayName): bool
{
    $heading = normalizeTitle($heading);
    $holiday = normalizeTitle($holidayName);

    if ($heading === '' || $holiday === '') {
        return false;
    }

    if ($heading === $holiday || str_contains($heading, $holiday)) {
        return true;
    }

    if (mb_strlen($heading, 'UTF-8') >= 5 && str_contains($holiday, $heading)) {
        return true;
    }

    similar_text($heading, $holiday, $percent);

    return $percent >= 88;
}

function normalizeTitle(string $text): string
{
    $text = mb_strtolower(compactText($text), 'UTF-8');
    $text = str_replace(['’', 'ʼ', '`', '*', '—', '–', '.', ',', ':', ';', '(', ')', '"', "'"], ' ', $text);
    $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text) ?? $text;

    return compactText($text);
}

function compactText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}
