<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$contentRoot = $root.'/storage/app/content';
$assetRoot = $root.'/public/assets';
$reportPath = $root.'/storage/app/content/legacy-import-decor-cleanup.json';

$report = [
    'generated_at' => date(DATE_ATOM),
    'content_root' => 'storage/app/content',
    'cleaned_files' => [],
    'cleaned_manifests' => [],
    'removed_russian_version_files' => [],
    'removed_asset_files' => [],
];

if (!is_dir($contentRoot)) {
    throw new RuntimeException('Не знайдено storage/app/content.');
}

foreach (htmlFiles($contentRoot) as $path) {
    $html = (string) file_get_contents($path);
    $hadRussianVersionReference = containsRussianVersionReference($html);
    $cleaned = cleanLegacyImportDecor($html);

    if ($cleaned === $html) {
        continue;
    }

    file_put_contents($path, rtrim($cleaned).PHP_EOL);
    $relativePath = relativePath($root, $path);
    $report['cleaned_files'][] = $relativePath;

    if ($hadRussianVersionReference && !containsRussianVersionReference($cleaned)) {
        $report['removed_russian_version_files'][] = $relativePath;
    }

    echo '[CLEAN] '.$relativePath.PHP_EOL;
}

if (is_dir($assetRoot)) {
    foreach (legacyAssetFiles($assetRoot) as $path) {
        if (@unlink($path)) {
            $report['removed_asset_files'][] = relativePath($root, $path);
            echo '[DELETE] '.relativePath($root, $path).PHP_EOL;
        }
    }
}

foreach (jsonFiles($contentRoot) as $path) {
    $relativePath = relativePath($root, $path);
    if ($relativePath === 'storage/app/content/legacy-import-decor-cleanup.json') {
        continue;
    }

    $json = (string) file_get_contents($path);
    $cleaned = cleanManifestJson($json);

    if ($cleaned === $json) {
        continue;
    }

    file_put_contents($path, rtrim($cleaned).PHP_EOL);
    $report['cleaned_manifests'][] = $relativePath;
    echo '[MANIFEST] '.$relativePath.PHP_EOL;
}

file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

echo PHP_EOL;
echo 'Очищено HTML-файлів: '.count($report['cleaned_files']).PHP_EOL;
echo 'Очищено JSON-маніфестів: '.count($report['cleaned_manifests']).PHP_EOL;
echo 'Прибрано посилань на російські версії у файлах: '.count($report['removed_russian_version_files']).PHP_EOL;
echo 'Видалено GIF-файлів: '.count($report['removed_asset_files']).PHP_EOL;
echo 'Звіт: storage/app/content/legacy-import-decor-cleanup.json'.PHP_EOL;

/** @return Generator<int, string> */
function htmlFiles(string $root): Generator
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) === 'html') {
            yield $file->getPathname();
        }
    }
}

/** @return Generator<int, string> */
function jsonFiles(string $root): Generator
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) === 'json') {
            yield $file->getPathname();
        }
    }
}

/** @return Generator<int, string> */
function legacyAssetFiles(string $root): Generator
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        if (strtolower($file->getExtension()) === 'gif' || isLegacyDecorFilename($file->getBasename())) {
            yield $file->getPathname();
        }
    }
}

function cleanLegacyImportDecor(string $html): string
{
    $before = $html;

    $html = removeRussianVersionReferences($html);
    $html = removeLegacyNavigationBlocks($html);

    $html = preg_replace_callback(
        '~<img\b[^>]*>~isu',
        static fn (array $match): string => isLegacyDecorImageTag($match[0]) ? '' : $match[0],
        $html
    ) ?? $html;

    $html = removeEmptyStructuralMarkup($html);

    // Старий сайт іноді дає криву конструкцію <ol><h5>Джерела</h5></ol>.
    $html = preg_replace('~<ol\b[^>]*>\s*(<h[1-6]\b[^>]*>.*?</h[1-6]>)\s*</ol>\s*~isu', '$1', $html) ?? $html;

    $html = removeEmptyStructuralMarkup($html);
    $html = preg_replace('~(?:\s|&nbsp;){2,}~u', ' ', $html) ?? $html;

    if ($html === '' || $html === $before || trim($html) === trim($before)) {
        return $before;
    }

    return trim($html);
}

function cleanManifestJson(string $json): string
{
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return $json;
    }

    $cleaned = cleanManifestValue($data);
    if ($cleaned === $data) {
        return $json;
    }

    $encoded = json_encode($cleaned, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return is_string($encoded) ? $encoded : $json;
}

function cleanManifestValue($value)
{
    if (!is_array($value)) {
        return $value;
    }

    if (isSequentialArray($value)) {
        $items = [];
        foreach ($value as $item) {
            if (is_array($item) && isGifAssetEntry($item)) {
                continue;
            }

            $items[] = cleanManifestValue($item);
        }

        return $items;
    }

    foreach ($value as $key => $item) {
        $value[$key] = cleanManifestValue($item);
    }

    if (isset($value['assets']) && is_array($value['assets'])) {
        $assets = [];
        foreach ($value['assets'] as $asset) {
            if (is_array($asset) && isGifAssetEntry($asset)) {
                continue;
            }

            $assets[] = $asset;
        }

        $value['assets'] = $assets;
    }

    if (array_key_exists('images', $value) && isset($value['assets']) && is_array($value['assets'])) {
        $value['images'] = countRealImageAssets($value['assets']);
    }

    return $value;
}

function isSequentialArray(array $value): bool
{
    return $value === [] || array_keys($value) === range(0, count($value) - 1);
}

function isGifAssetEntry(array $entry): bool
{
    foreach (assetReferenceKeys() as $key) {
        if (isset($entry[$key]) && is_scalar($entry[$key]) && isGifPath((string) $entry[$key])) {
            return true;
        }
    }

    return false;
}

function countRealImageAssets(array $assets): int
{
    $count = 0;
    foreach ($assets as $asset) {
        if (!is_array($asset)) {
            continue;
        }

        foreach (assetReferenceKeys() as $key) {
            if (isset($asset[$key]) && is_scalar($asset[$key]) && isRealImagePath((string) $asset[$key])) {
                $count++;
                break;
            }
        }
    }

    return $count;
}

/** @return array<int, string> */
function assetReferenceKeys(): array
{
    return ['path', 'src', 'url', 'href', 'source', 'source_url', 'original_url'];
}

function isGifPath(string $path): bool
{
    $basename = normalizedPathBasename($path);

    return str_ends_with($basename, '.gif') || isLegacyDecorFilename($basename);
}

function isRealImagePath(string $path): bool
{
    $basename = normalizedPathBasename($path);

    return preg_match('~\.(?:jpe?g|png|webp|svg)$~i', $basename) === 1 && !isLegacyDecorFilename($basename);
}

function normalizedPathBasename(string $path): string
{
    $decoded = trim(html_entity_decode($path, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $urlPath = (string) (parse_url($decoded, PHP_URL_PATH) ?? $decoded);

    return strtolower(rawurldecode(basename($urlPath)));
}

function removeRussianVersionReferences(string $html): string
{
    $html = preg_replace_callback(
        '~<(p|li|h[1-6])\b[^>]*>.*?</\1>\s*~isu',
        static fn (array $match): string => containsRussianVersionReference($match[0]) ? '' : $match[0],
        $html
    ) ?? $html;

    $html = preg_replace_callback(
        '~<a\b[^>]*>.*?</a>\s*~isu',
        static fn (array $match): string => containsRussianVersionReference($match[0]) ? '' : $match[0],
        $html
    ) ?? $html;

    return preg_replace(
        '~(?:^|<br\s*/?>)\s*(?:[-–—]\s*)?(?:російськ(?:а|ою|ої)|русск(?:ая|ой|ую)|russian)\s+(?:версі(?:я|ї|ю)|верс(?:ия|ии|ию)|version)(?:\s+(?:статт(?:і|ю|я)|article))?\s*(?=<br\s*/?>|$)~imu',
        '',
        $html
    ) ?? $html;
}

function removeLegacyNavigationBlocks(string $html): string
{
    return preg_replace_callback(
        '~<(p|li|td|th|h[1-6])\b[^>]*>.*?</\1>\s*~isu',
        static fn (array $match): string => isLegacyNavigationBlock($match[0]) ? '' : $match[0],
        $html
    ) ?? $html;
}

function removeEmptyStructuralMarkup(string $html): string
{
    $html = preg_replace('~<p\b[^>]*>\s*(?:&nbsp;)?\s*</p>\s*~iu', '', $html) ?? $html;
    $html = preg_replace('~<h([1-6])\b[^>]*>\s*(?:&nbsp;)?\s*</h\1>\s*~iu', '', $html) ?? $html;
    $html = preg_replace('~<li\b[^>]*>\s*(?:&nbsp;)?\s*</li>\s*~iu', '', $html) ?? $html;
    $html = preg_replace('~<t[dh]\b[^>]*>\s*(?:&nbsp;)?\s*</t[dh]>\s*~iu', '', $html) ?? $html;
    $html = preg_replace('~<tr\b[^>]*>\s*</tr>\s*~iu', '', $html) ?? $html;
    $html = preg_replace('~<(?:thead|tbody|tfoot|table|ul|ol)\b[^>]*>\s*</(?:thead|tbody|tfoot|table|ul|ol)>\s*~iu', '', $html) ?? $html;

    return preg_replace_callback(
        '~<table\b[^>]*>.*?</table>\s*~isu',
        static fn (array $match): string => compactText(strip_tags($match[0])) === '' ? '' : $match[0],
        $html
    ) ?? $html;
}

function containsRussianVersionReference(string $html): bool
{
    $text = compactText(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', $html)));

    return preg_match('~(?:російськ(?:а|ою|ої)|русск(?:ая|ой|ую)|russian)\s+(?:версі(?:я|ї|ю)|верс(?:ия|ии|ию)|version)(?:\s+(?:статт(?:і|ю|я)|article))?~iu', $text) === 1
        || preg_match('~(?:статт(?:я|і|ю)|article)\s+(?:російською|русском|russian)~iu', $text) === 1;
}

function isLegacyNavigationBlock(string $html): bool
{
    $key = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(compactText(strip_tags($html)), 'UTF-8')) ?? '';

    return in_array($key, [
        'дорозділу',
        'дороздiлу',
        'достатей',
        'доматеріалів',
        'назаддорозділу',
    ], true);
}

function isLegacyDecorImageTag(string $tag): bool
{
    $src = imageAttribute($tag, 'src');
    $width = imageDimension($tag, 'width');
    $height = imageDimension($tag, 'height');

    if ($src === '') {
        return false;
    }

    $basename = normalizedPathBasename($src);

    if (str_ends_with($basename, '.gif') || isLegacyDecorFilename($basename)) {
        return true;
    }

    if ($width !== null && $height !== null) {
        if ($height <= 16 && $width >= 120) {
            return true;
        }

        if ($width <= 40 && $height <= 40 && preg_match('~(?:art|article|ico|icon|bullet|punkt|marker)~i', $basename)) {
            return true;
        }
    }

    return false;
}

function isLegacyDecorFilename(string $filename): bool
{
    $filename = strtolower(rawurldecode($filename));
    $normalized = preg_replace('/-\d+(?=\.[a-z0-9]+$)/i', '', $filename) ?? $filename;

    return in_array($normalized, [
        'lin.gif',
        'line.gif',
        'artic.gif',
        'article.gif',
        'spacer.gif',
        'space.gif',
        'blank.gif',
        'pixel.gif',
        'dot.gif',
        'hr.gif',
        'punkt.gif',
        'bullet.gif',
    ], true);
}

function imageAttribute(string $tag, string $attribute): string
{
    if (!preg_match('~\b'.preg_quote($attribute, '~').'\s*=\s*(["\'])(.*?)\1~isu', $tag, $match)) {
        return '';
    }

    return trim((string) html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function imageDimension(string $tag, string $attribute): ?int
{
    $value = imageAttribute($tag, $attribute);

    return $value !== '' && preg_match('/^\d+$/', $value) ? (int) $value : null;
}

function compactText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function relativePath(string $root, string $path): string
{
    return str_replace('\\', '/', ltrim(str_replace($root, '', $path), '/\\'));
}
