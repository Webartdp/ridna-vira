<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Safety pass after holiday import.
 * Replaces missing, empty, mojibake, or accidentally imported full-calendar pages
 * with the local holiday description from config/faith_holidays.php.
 */

$root = dirname(__DIR__);
$calendar = require $root.'/config/faith_calendar.php';
$descriptions = require $root.'/config/faith_holidays.php';
$outputDir = $root.'/storage/app/content/holidays';
$reportPath = $root.'/storage/app/content/holidays-repair.json';

ensureDirectory($outputDir);
ensureDirectory(dirname($reportPath));

$report = [
    'generated_at' => date(DATE_ATOM),
    'repaired' => [],
    'missing_description' => [],
];

foreach (($calendar['months'] ?? []) as $monthName => $items) {
    foreach ($items as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $slug = (string) ($item['slug'] ?? Str::slug($name));
        $path = $outputDir.'/'.$slug.'.html';
        $html = is_file($path) ? (string) file_get_contents($path) : '';
        $characters = mb_strlen(compactText(strip_tags($html)), 'UTF-8');
        $reason = repairReason($html, $characters);

        if ($reason === null) {
            continue;
        }

        $text = compactText((string) ($descriptions[$name] ?? ''));
        if ($text === '') {
            $report['missing_description'][] = [
                'slug' => $slug,
                'name' => $name,
                'month' => $monthName,
                'reason' => $reason,
            ];
            continue;
        }

        file_put_contents($path, snippetHtml($name, $text)."\n");

        $report['repaired'][] = [
            'slug' => $slug,
            'name' => $name,
            'month' => $monthName,
            'reason' => $reason,
            'characters' => mb_strlen($text, 'UTF-8'),
        ];

        echo '[FIX] '.$name.' -> '.$reason.PHP_EOL;
    }
}

file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

echo PHP_EOL;
echo 'Виправлено сторінок свят: '.count($report['repaired']).PHP_EOL;
echo 'Без опису в конфігу: '.count($report['missing_description']).PHP_EOL;
echo 'Звіт: storage/app/content/holidays-repair.json'.PHP_EOL;

if ($report['missing_description'] !== []) {
    exit(2);
}

function repairReason(string $html, int $characters): ?string
{
    if ($html === '') {
        return 'missing';
    }

    if ($characters < 12) {
        return 'empty';
    }

    if (hasMojibake($html)) {
        return 'mojibake';
    }

    if (looksLikeCalendarIndexDump($html)) {
        return 'calendar-index-dump';
    }

    return null;
}

function looksLikeCalendarIndexDump(string $html): bool
{
    $text = mb_strtolower(compactText(strip_tags($html)), 'UTF-8');
    $monthHits = 0;

    foreach (['січень', 'лютий', 'березень', 'квітень', 'травень', 'червень', 'липень', 'серпень', 'вересень', 'жовтень', 'листопад', 'грудень'] as $month) {
        if (str_contains($text, $month)) {
            $monthHits++;
        }
    }

    if ($monthHits >= 6) {
        return true;
    }

    return $monthHits >= 3
        && (str_contains($text, 'руський православний календар')
            || str_contains($text, 'дивіться книжки та статті')
            || str_contains($text, 'календар пам'));
}

function snippetHtml(string $name, string $text): string
{
    return '<div class="holiday-imported-article holiday-imported-article--calendar">'."\n"
        .'<h2>'.escapeHtml($name).'</h2>'."\n"
        .'<p>'.escapeHtml($text).'</p>'."\n"
        .'</div>';
}

function compactText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function hasMojibake(string $text): bool
{
    foreach (['Р’', 'Р°', 'Рµ', 'Рё', 'Рґ', 'Р¶', 'Р·', 'Р№', 'Рє', 'Р»', 'Рј', 'РЅ', 'Рѕ', 'Рї', 'СЂ', 'СЃ', 'С‚', 'Сѓ', 'С„', 'С…', 'С†', 'С‡', 'С€', 'С‰', 'СЊ', 'СЋ', 'СЏ', 'С–', 'С—', 'С”', 'Р†', 'Р™', 'вЂ', 'Ð', 'Ñ', 'Â', '�'] as $marker) {
        if (str_contains($text, $marker)) {
            return true;
        }
    }

    return false;
}

function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}
