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
    'legacy_author_byline' => [],
    'legacy_svit_link' => [],
];
$checked = 0;
$totalImages = 0;
$filesWithImages = 0;

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
            $issues['missing'][] = holidayIssue($slug, $name, $monthName, 0, 0);
            continue;
        }

        $html = (string) file_get_contents($path);
        $characters = mb_strlen(compactText(strip_tags($html)), 'UTF-8');
        $images = substr_count($html, '<img ');
        $totalImages += $images;
        if ($images > 0) {
            $filesWithImages++;
        }

        if ($characters < 12 && $images === 0) {
            $issues['too_short'][] = holidayIssue($slug, $name, $monthName, $characters, $images);
        }

        if (hasMojibake($html)) {
            $issues['mojibake'][] = holidayIssue($slug, $name, $monthName, $characters, $images);
        }

        if (hasLegacyAuthorByline($html)) {
            $issues['legacy_author_byline'][] = holidayIssue($slug, $name, $monthName, $characters, $images);
        }

        if (hasLegacySvitLink($html)) {
            $issues['legacy_svit_link'][] = holidayIssue($slug, $name, $monthName, $characters, $images);
        }
    }
}

$report = [
    'generated_at' => date(DATE_ATOM),
    'checked' => $checked,
    'image_count' => $totalImages,
    'files_with_images_count' => $filesWithImages,
    'missing_count' => count($issues['missing']),
    'too_short_count' => count($issues['too_short']),
    'mojibake_count' => count($issues['mojibake']),
    'legacy_author_byline_count' => count($issues['legacy_author_byline']),
    'legacy_svit_link_count' => count($issues['legacy_svit_link']),
    'issues' => $issues,
];

ensureDirectory(dirname($reportPath));
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

echo "Перевірено свят: {$checked}\n";
echo 'Зображень у матеріалах: '.$totalImages."\n";
echo 'Файлів зі зображеннями: '.$filesWithImages."\n";
echo 'Немає файлу: '.count($issues['missing'])."\n";
echo 'Порожні або майже порожні без зображень: '.count($issues['too_short'])."\n";
echo 'Бите кодування: '.count($issues['mojibake'])."\n";
echo 'Службовий підпис автора: '.count($issues['legacy_author_byline'])."\n";
echo 'Старі посилання svit.in.ua: '.count($issues['legacy_svit_link'])."\n";
echo 'Звіт: storage/app/content/holidays-audit.json'."\n";

if ($issues['missing'] !== [] || $issues['too_short'] !== [] || $issues['mojibake'] !== [] || $issues['legacy_author_byline'] !== [] || $issues['legacy_svit_link'] !== []) {
    exit(2);
}

/** @return array{slug:string,name:string,month:string,characters:int,images:int} */
function holidayIssue(string $slug, string $name, string $month, int $characters, int $images): array
{
    return [
        'slug' => $slug,
        'name' => $name,
        'month' => $month,
        'characters' => $characters,
        'images' => $images,
    ];
}

function compactText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function hasMojibake(string $text): bool
{
    foreach ([
        'Р’', 'Рђ', 'Р†', 'Р™', 'РЋ', 'Р°', 'Р±', 'РІ', 'Рі', 'Рґ', 'Рµ', 'Р¶', 'Р·', 'Рё', 'Р№', 'Рє', 'Р»', 'Рј', 'РЅ', 'Рѕ', 'Рї',
        'СЂ', 'СЃ', 'С‚', 'Сѓ', 'С„', 'С…', 'С†', 'С‡', 'С€', 'С‰', 'СЊ', 'СЋ', 'СЏ', 'С–', 'С—', 'С”',
        'вЂ', 'в„', 'в€¦', 'в‚', 'Ð', 'Ñ', 'Â', 'Ã', 'ЃР', 'ЃС', '�',
    ] as $marker) {
        if (str_contains($text, $marker)) {
            return true;
        }
    }

    return false;
}

function hasLegacyAuthorByline(string $html): bool
{
    if (preg_match_all('~<(p|h[1-6])\b[^>]*>(.*?)</\1>~isu', $html, $matches)) {
        foreach ($matches[2] as $fragment) {
            if (isLegacyAuthorByline($fragment)) {
                return true;
            }
        }
    }

    return false;
}

function hasLegacySvitLink(string $html): bool
{
    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return preg_match('~https?://(?:www\.)?svit\.in\.ua|(?:^|[\s/"\'>])(?:www\.)?svit\.in\.ua~iu', $decoded) === 1;
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

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}
