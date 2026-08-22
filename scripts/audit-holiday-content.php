<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$calendar = require $root.'/config/faith_calendar.php';
$outputDir = $root.'/storage/app/content/holidays';
$reportPath = $root.'/storage/app/content/holidays-audit.json';

$issues = [
    'missing' => [],
    'too_short' => [],
    'mojibake' => [],
];
$checked = 0;

foreach (($calendar['months'] ?? []) as $monthName => $items) {
    foreach ($items as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $checked++;
        $slug = (string) ($item['slug'] ?? Str::slug($name));
        $path = $outputDir.'/'.$slug.'.html';

        if (!is_file($path)) {
            $issues['missing'][] = holidayIssue($slug, $name, $monthName, 0);
            continue;
        }

        $html = (string) file_get_contents($path);
        $characters = mb_strlen(compactText(strip_tags($html)), 'UTF-8');

        if ($characters < 12) {
            $issues['too_short'][] = holidayIssue($slug, $name, $monthName, $characters);
        }

        if (hasMojibake($html)) {
            $issues['mojibake'][] = holidayIssue($slug, $name, $monthName, $characters);
        }
    }
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'checked' => $checked,
    'missing_count' => count($issues['missing']),
    'too_short_count' => count($issues['too_short']),
    'mojibake_count' => count($issues['mojibake']),
    'issues' => $issues,
];

ensureDirectory(dirname($reportPath));
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

echo "Перевірено свят: {$checked}\n";
echo 'Немає файлу: '.count($issues['missing'])."\n";
echo 'Порожні або майже порожні: '.count($issues['too_short'])."\n";
echo 'Бите кодування: '.count($issues['mojibake'])."\n";
echo 'Звіт: storage/app/content/holidays-audit.json'."\n";

if ($issues['missing'] !== [] || $issues['too_short'] !== [] || $issues['mojibake'] !== []) {
    exit(2);
}

/** @return array{slug:string,name:string,month:string,characters:int} */
function holidayIssue(string $slug, string $name, string $month, int $characters): array
{
    return [
        'slug' => $slug,
        'name' => $name,
        'month' => $month,
        'characters' => $characters,
    ];
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

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}
