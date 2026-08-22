<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Removes imported duplicate title headings and legacy author bylines from
 * holiday content files. Page templates already render the holiday title, so
 * imported article bodies should start with the real article content.
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
        $removedDuplicateTitle = $cleaned !== $html;

        $beforeBylineCleanup = $cleaned;
        $cleaned = removeLegacyAuthorBylines($cleaned);
        $removedLegacyByline = $cleaned !== $beforeBylineCleanup;
        $cleaned = removeEmptyParagraphs($cleaned);

        if ($cleaned === $html) {
            continue;
        }

        file_put_contents($path, rtrim($cleaned)."\n");
        $report['cleaned'][] = [
            'slug' => $slug,
            'name' => $name,
            'month' => $monthName,
            'duplicate_title' => $removedDuplicateTitle,
            'legacy_author_byline' => $removedLegacyByline,
        ];

        echo '[CLEAN] '.$name.PHP_EOL;
    }
}

$duplicateTitleCount = count(array_filter($report['cleaned'], static fn (array $item): bool => (bool) ($item['duplicate_title'] ?? false)));
$legacyBylineCount = count(array_filter($report['cleaned'], static fn (array $item): bool => (bool) ($item['legacy_author_byline'] ?? false)));

file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

echo PHP_EOL;
echo 'Прибрано дубльованих заголовків: '.$duplicateTitleCount.PHP_EOL;
echo 'Прибрано службових підписів автора: '.$legacyBylineCount.PHP_EOL;
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

function removeLegacyAuthorBylines(string $html): string
{
    return preg_replace_callback(
        '~<(p|h[1-6])\b[^>]*>(.*?)</\1>\s*~isu',
        static fn (array $match): string => isLegacyAuthorByline($match[2]) ? '' : $match[0],
        $html
    ) ?? $html;
}

function isLegacyAuthorByline(string $html): bool
{
    $text = compactText(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', $html)));
    $key = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text, 'UTF-8')) ?? '';

    return in_array($key, [
        'світовитпашник',
        'волхврідноївіри',
        'волхврпк',
        'світовитпашникволхврідноївіри',
        'світовитпашникволхврпк',
    ], true);
}

function removeEmptyParagraphs(string $html): string
{
    return preg_replace('~<p\b[^>]*>\s*(?:&nbsp;)?\s*</p>\s*~iu', '', $html) ?? $html;
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
