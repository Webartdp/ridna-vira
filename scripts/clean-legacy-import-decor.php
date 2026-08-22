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

file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

echo PHP_EOL;
echo 'Очищено HTML-файлів: '.count($report['cleaned_files']).PHP_EOL;
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

    $basename = strtolower(rawurldecode(basename((string) parse_url(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH))));

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
