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
    'removed_asset_files' => [],
];

if (!is_dir($contentRoot)) {
    throw new RuntimeException('Не знайдено storage/app/content.');
}

foreach (htmlFiles($contentRoot) as $path) {
    $html = (string) file_get_contents($path);
    $cleaned = cleanLegacyImportDecor($html);

    if ($cleaned === $html) {
        continue;
    }

    file_put_contents($path, rtrim($cleaned).PHP_EOL);
    $report['cleaned_files'][] = relativePath($root, $path);
    echo '[CLEAN] '.relativePath($root, $path).PHP_EOL;
}

if (is_dir($assetRoot)) {
    foreach (legacyAssetFiles($assetRoot) as $path) {
        if (@unlink($path)) {
            $report['removed_asset_files'][] = relativePath($root, $path);
            echo '[DELETE] '.relativePath($root, $path).PHP_EOL;
        }
    }
}

file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

echo PHP_EOL;
echo 'Очищено HTML-файлів: '.count($report['cleaned_files']).PHP_EOL;
echo 'Видалено службових GIF-файлів: '.count($report['removed_asset_files']).PHP_EOL;
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
function legacyAssetFiles(string $root): Generator
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }

        if (isLegacyDecorFilename($file->getBasename())) {
            yield $file->getPathname();
        }
    }
}

function cleanLegacyImportDecor(string $html): string
{
    $before = $html;

    $html = preg_replace_callback(
        '~<img\b[^>]*>~isu',
        static fn (array $match): string => isLegacyDecorImageTag($match[0]) ? '' : $match[0],
        $html
    ) ?? $html;

    $html = preg_replace('~<p\b[^>]*>\s*(?:&nbsp;)?\s*</p>\s*~iu', '', $html) ?? $html;
    $html = preg_replace('~<h([1-6])\b[^>]*>\s*(?:&nbsp;)?\s*</h\1>\s*~iu', '', $html) ?? $html;
    $html = preg_replace('~<li\b[^>]*>\s*(?:&nbsp;)?\s*</li>\s*~iu', '', $html) ?? $html;
    $html = preg_replace('~<(?:ul|ol)\b[^>]*>\s*</(?:ul|ol)>\s*~iu', '', $html) ?? $html;

    // Старий сайт іноді дає криву конструкцію <ol><h5>Джерела</h5></ol>.
    $html = preg_replace('~<ol\b[^>]*>\s*(<h[1-6]\b[^>]*>.*?</h[1-6]>)\s*</ol>\s*~isu', '$1', $html) ?? $html;

    $html = preg_replace('~(?:\s|&nbsp;){2,}~u', ' ', $html) ?? $html;

    if ($html === '' || $html === $before || trim($html) === trim($before)) {
        return $before;
    }

    return trim($html);
}

function isLegacyDecorImageTag(string $tag): bool
{
    $src = imageAttribute($tag, 'src');
    $width = imageDimension($tag, 'width');
    $height = imageDimension($tag, 'height');

    if ($src === '') {
        return false;
    }

    $basename = strtolower(rawurldecode(basename((string) parse_url(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH))));

    if (isLegacyDecorFilename($basename)) {
        return true;
    }

    if (str_ends_with($basename, '.gif') && $width !== null && $height !== null) {
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

function relativePath(string $root, string $path): string
{
    return str_replace('\\', '/', ltrim(str_replace($root, '', $path), '/\\'));
}
