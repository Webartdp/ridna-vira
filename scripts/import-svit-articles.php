<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require_once dirname(__DIR__).'/vendor/autoload.php';

/**
 * One-time migration of articles and article documents from the legacy portal.
 * The public site reads the generated manifest and never links to svit.in.ua.
 */

const SOURCE_PAGE = 'https://www.svit.in.ua/sta.htm';
const USER_AGENT = 'Mozilla/5.0 (compatible; RidnaViraArticleMigration/1.0; +https://ridnavira.com.ua)';

$projectRoot = dirname(__DIR__);
$storageRoot = $projectRoot.'/storage/app/content/articles';
$manifestPath = $storageRoot.'/manifest.json';

try {
    ensureDirectory($storageRoot.'/pages');
    ensureDirectory($storageRoot.'/files');

    $sourceResponse = fetchUrl(SOURCE_PAGE);
    $sourceHtml = normalizeEncoding($sourceResponse['body'], $sourceResponse['contentType']);
    $links = extractArticleLinks($sourceHtml, SOURCE_PAGE);

    if ($links === []) {
        throw new RuntimeException('На сторінці статей не знайдено матеріалів для імпорту.');
    }

    echo 'Знайдено матеріалів: '.count($links)."\n";

    $articles = [];
    $failed = [];
    $usedSlugs = [];
    $imported = 0;

    foreach ($links as $link) {
        try {
            $response = fetchUrl($link['url']);
            $isHtml = isHtmlResponse($response);
            $extension = detectExtension($response, $link['url'], $isHtml);
            $contentTitle = $link['title'];
            $body = $response['body'];
            $directory = $extension === 'html' ? 'pages' : 'files';

            if ($isHtml) {
                $utf8 = normalizeEncoding($body, $response['contentType']);
                $contentTitle = titleFromHtml($utf8) ?: $contentTitle;
                $slug = uniqueSlug($contentTitle, $link['url'], $usedSlugs);
                $destination = $storageRoot.'/'.$directory.'/'.$slug.'.html';
                file_put_contents($destination, sanitizeHtmlArticle($utf8, $contentTitle, $link['url']));
            } else {
                rejectHtmlResponse($response, $link['url']);
                $slug = uniqueSlug($contentTitle, $link['url'], $usedSlugs);
                $destination = $storageRoot.'/'.$directory.'/'.$slug.'.'.$extension;
                writeBinaryFile($destination, $body);
            }

            $articles[$slug] = [
                'slug' => $slug,
                'title' => $contentTitle,
                'extension' => $extension,
                'path' => relativePath($storageRoot, $destination),
                'size' => filesize($destination) ?: 0,
                'source_url' => $link['url'],
                'source_label' => $link['title'],
            ];

            $imported++;
            echo "[OK] {$contentTitle} -> {$articles[$slug]['path']}\n";
        } catch (Throwable $exception) {
            $message = $link['title'].': '.$exception->getMessage();
            $failed[] = $message;
            fwrite(STDERR, "[FAIL] {$message}\n");
        }
    }

    $manifest = [
        'generated_at' => date(DATE_ATOM),
        'source' => SOURCE_PAGE,
        'storage' => 'storage/app/content/articles',
        'articles_total' => count($links),
        'articles_imported' => $imported,
        'failed' => $failed,
        'articles' => $articles,
    ];

    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

    echo "\nСтатей у списку: ".count($links)."\n";
    echo "Матеріалів перенесено: {$imported}\n";
    echo "Звіт: storage/app/content/articles/manifest.json\n";

    if ($failed !== []) {
        fwrite(STDERR, "\nНе перенесено ".count($failed)." матеріал(ів):\n- ".implode("\n- ", $failed)."\n");
        exit(2);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[FATAL] '.$exception->getMessage()."\n");
    exit(1);
}

/** @return array{body:string, contentType:string, effectiveUrl:string} */
function fetchUrl(string $url): array
{
    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('Не вдалося ініціалізувати cURL.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 180,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_REFERER => SOURCE_PAGE,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/rtf,application/octet-stream,*/*;q=0.8',
            'Accept-Language: uk-UA,uk;q=0.9,en;q=0.5',
        ],
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if (!is_string($body)) {
        throw new RuntimeException("Помилка завантаження {$url}: {$error}");
    }

    if ($status < 200 || $status >= 400) {
        throw new RuntimeException("Сервер повернув HTTP {$status} для {$url}");
    }

    if ($body === '') {
        throw new RuntimeException("Отримано порожній файл із {$url}");
    }

    return [
        'body' => $body,
        'contentType' => $contentType,
        'effectiveUrl' => $effectiveUrl !== '' ? $effectiveUrl : $url,
    ];
}

function normalizeEncoding(string $content, string $contentType = ''): string
{
    $candidates = [];
    $declaredEncoding = detectDeclaredEncoding($content, $contentType);

    if ($declaredEncoding !== null) {
        $converted = @iconv($declaredEncoding, 'UTF-8//IGNORE', $content);
        if (is_string($converted) && $converted !== '') {
            $candidates[] = stripUtf8Bom($converted);
        }
    }

    if (mb_check_encoding($content, 'UTF-8')) {
        $candidates[] = stripUtf8Bom($content);
    }

    foreach (['Windows-1251', 'CP1251', 'KOI8-U', 'ISO-8859-5', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
        $converted = @iconv($encoding, 'UTF-8//IGNORE', $content);
        if (is_string($converted) && $converted !== '') {
            $candidates[] = stripUtf8Bom($converted);
        }
    }

    foreach ($candidates as $candidate) {
        foreach (repairMojibakeVariants($candidate) as $repaired) {
            $candidates[] = $repaired;
        }
    }

    $candidates = array_values(array_unique(array_filter($candidates, static fn (string $candidate): bool => $candidate !== '')));
    $best = $candidates[0] ?? $content;
    $bestScore = PHP_INT_MIN;

    foreach ($candidates as $candidate) {
        $score = textQualityScore($candidate);

        if ($score > $bestScore) {
            $best = $candidate;
            $bestScore = $score;
        }
    }

    return $best;
}

function detectDeclaredEncoding(string $content, string $contentType): ?string
{
    if (preg_match('/charset\s*=\s*["\']?([^;"\'\s]+)/i', $contentType, $match)) {
        return trim($match[1]);
    }

    $head = substr($content, 0, 8192);

    if (preg_match('/<meta[^>]+charset\s*=\s*["\']?([^"\'\s>]+)/i', $head, $match)) {
        return trim($match[1]);
    }

    if (preg_match('/<meta[^>]+content\s*=\s*["\'][^"\']*charset=([^;"\'\s>]+)/i', $head, $match)) {
        return trim($match[1]);
    }

    return null;
}

/** @return array<int, string> */
function repairMojibakeVariants(string $text): array
{
    if (!hasMojibakeMarkers($text)) {
        return [];
    }

    $variants = [];

    foreach (['Windows-1251', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
        $bytes = @iconv('UTF-8', $encoding.'//IGNORE', $text);

        if (is_string($bytes) && $bytes !== '' && mb_check_encoding($bytes, 'UTF-8')) {
            $variants[] = stripUtf8Bom($bytes);
        }
    }

    return $variants;
}

function hasMojibakeMarkers(string $text): bool
{
    foreach (mojibakeMarkers() as $marker) {
        if (str_contains($text, $marker)) {
            return true;
        }
    }

    return false;
}

function textQualityScore(string $text): int
{
    $ukrainianLetters = preg_match_all('/[А-ЩЬЮЯЄІЇҐа-щьюяєіїґ]/u', $text) ?: 0;
    $markers = 0;

    foreach (mojibakeMarkers() as $marker) {
        $markers += substr_count($text, $marker);
    }

    return ($ukrainianLetters * 4) - ($markers * 35) - (substr_count($text, '�') * 100);
}

/** @return array<int, string> */
function mojibakeMarkers(): array
{
    return ['Р’', 'Р°', 'Рµ', 'Рё', 'Рґ', 'Р¶', 'Р·', 'Р№', 'Рє', 'Р»', 'Рј', 'РЅ', 'Рѕ', 'Рї', 'СЂ', 'СЃ', 'С‚', 'Сѓ', 'С„', 'С…', 'С†', 'С‡', 'С€', 'С‰', 'СЊ', 'СЋ', 'СЏ', 'С–', 'С—', 'С”', 'Р†', 'Р™', 'вЂ', 'Ð', 'Ñ', 'Â'];
}

function stripUtf8Bom(string $content): string
{
    return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
}

/** @return array<int, array{title:string,url:string}> */
function extractArticleLinks(string $html, string $baseUrl): array
{
    $html = normalizeHtmlCharset($html);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $links = [];
    $usedUrls = [];

    foreach ($dom->getElementsByTagName('a') as $anchor) {
        $href = trim((string) $anchor->getAttribute('href'));

        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            continue;
        }

        $url = resolveUrl($baseUrl, html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($host, ['svit.in.ua', 'www.svit.in.ua'], true)) {
            continue;
        }

        if (!str_starts_with($path, '/stat/') && !in_array($extension, ['pdf', 'doc', 'docx', 'rtf'], true)) {
            continue;
        }

        if (isset($usedUrls[$url])) {
            continue;
        }

        $title = cleanLinkTitle((string) $anchor->textContent);

        if ($title === '' || mb_strlen($title, 'UTF-8') < 4) {
            $title = pathinfo($path, PATHINFO_FILENAME) ?: 'Матеріал';
        }

        $usedUrls[$url] = true;
        $links[] = [
            'title' => $title,
            'url' => $url,
        ];
    }

    return $links;
}

function cleanLinkTitle(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = compactText($text);
    $text = preg_replace('/^[-–—\s]+/u', '', $text) ?? $text;

    return compactText($text);
}

function normalizeHtmlCharset(string $html): string
{
    $html = preg_replace('/<meta\b[^>]*charset\s*=\s*["\']?[^"\'\s>]+[^>]*>/i', '', $html) ?? $html;
    $html = preg_replace('/<meta\b[^>]*http-equiv\s*=\s*["\']?content-type["\']?[^>]*>/i', '', $html) ?? $html;

    return $html;
}

function titleFromHtml(string $html): ?string
{
    $html = normalizeHtmlCharset($html);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    foreach (['h1', 'h2', 'title'] as $tagName) {
        $node = $dom->getElementsByTagName($tagName)->item(0);
        $title = $node !== null ? compactText((string) $node->textContent) : '';

        if ($title !== '') {
            return $title;
        }
    }

    return null;
}

function sanitizeHtmlArticle(string $html, string $title, string $sourceUrl): string
{
    $html = normalizeHtmlCharset($html);
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    foreach (['script', 'iframe', 'object', 'embed', 'base', 'link', 'style', 'form', 'input', 'select', 'button'] as $tagName) {
        $nodes = [];
        foreach ($dom->getElementsByTagName($tagName) as $node) {
            $nodes[] = $node;
        }
        foreach ($nodes as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    foreach ($dom->getElementsByTagName('*') as $element) {
        $attributesToRemove = [];

        foreach ($element->attributes ?? [] as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);

            if (str_starts_with($name, 'on') || $name === 'style' || $name === 'class' || $name === 'id') {
                $attributesToRemove[] = $attribute->name;
                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $absolute = resolveUrl($sourceUrl, $value);
                $host = strtolower((string) parse_url($absolute, PHP_URL_HOST));

                if ($host === 'svit.in.ua' || $host === 'www.svit.in.ua') {
                    $attributesToRemove[] = $attribute->name;
                }
            }
        }

        foreach ($attributesToRemove as $attributeName) {
            $element->removeAttribute($attributeName);
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);

    if ($body === null) {
        return '';
    }

    $content = contentAfterFirstHeading($dom, $body, $title);

    return trim($content);
}

function contentAfterFirstHeading(DOMDocument $dom, DOMElement $body, string $title): string
{
    $content = '';
    $started = false;
    $normalizedTitle = normalizeText($title);

    foreach ($body->childNodes as $child) {
        if (!$started) {
            $text = normalizeText((string) $child->textContent);
            $tagName = $child instanceof DOMElement ? strtolower($child->tagName) : '';

            if (in_array($tagName, ['h1', 'h2', 'h3'], true) || ($normalizedTitle !== '' && $text !== '' && str_contains($normalizedTitle, $text))) {
                $started = true;
            } else {
                continue;
            }
        }

        $content .= $dom->saveHTML($child);
    }

    if (trim(strip_tags($content)) !== '') {
        return $content;
    }

    foreach ($body->childNodes as $child) {
        $content .= $dom->saveHTML($child);
    }

    return $content;
}

function normalizeText(string $text): string
{
    $text = str_replace(["\u{00A0}", '’', '`', 'ʼ', '«', '»', '„', '“', '”'], [' ', "'", "'", "'", ' ', ' ', ' ', ' ', ' '], $text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s\'\-]+/u', ' ', $text) ?? $text;

    return compactText($text);
}

function compactText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

/** @param array{body:string, contentType:string, effectiveUrl:string} $response */
function isHtmlResponse(array $response): bool
{
    $contentType = strtolower($response['contentType']);

    if (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/xhtml+xml')) {
        return true;
    }

    $sample = ltrim(substr($response['body'], 0, 1024));

    return preg_match('/^(?:<!doctype\s+html|<html\b|<head\b|<body\b)/i', $sample) === 1;
}

/** @param array{body:string, contentType:string, effectiveUrl:string} $response */
function rejectHtmlResponse(array $response, string $url): void
{
    if (isHtmlResponse($response)) {
        throw new RuntimeException("замість файлу отримано HTML-сторінку: {$url}");
    }
}

/** @param array{body:string, contentType:string, effectiveUrl:string} $response */
function detectExtension(array $response, string $sourceUrl, bool $isHtml): string
{
    if ($isHtml) {
        return 'html';
    }

    $body = $response['body'];
    $contentType = strtolower($response['contentType']);
    $pathExtension = strtolower(pathinfo((string) parse_url($response['effectiveUrl'] ?: $sourceUrl, PHP_URL_PATH), PATHINFO_EXTENSION));

    if (str_starts_with($body, '%PDF')) {
        return 'pdf';
    }

    if (str_starts_with($body, "PK\x03\x04")) {
        return 'docx';
    }

    if (str_starts_with($body, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
        return 'doc';
    }

    if (in_array($pathExtension, ['pdf', 'doc', 'docx', 'rtf'], true)) {
        return $pathExtension;
    }

    return match (true) {
        str_contains($contentType, 'pdf') => 'pdf',
        str_contains($contentType, 'wordprocessingml') => 'docx',
        str_contains($contentType, 'msword') => 'doc',
        str_contains($contentType, 'rtf') => 'rtf',
        default => 'bin',
    };
}

/** @param array<string, bool> $usedSlugs */
function uniqueSlug(string $title, string $url, array &$usedSlugs): string
{
    $base = Str::slug($title);

    if ($base === '') {
        $base = Str::slug(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME));
    }

    if ($base === '') {
        $base = 'material';
    }

    $slug = $base;
    $suffix = substr(sha1($url), 0, 8);

    if (isset($usedSlugs[$slug])) {
        $slug = $base.'-'.$suffix;
    }

    $usedSlugs[$slug] = true;

    return $slug;
}

function writeBinaryFile(string $destination, string $body): void
{
    ensureDirectory(dirname($destination));

    $temporaryPath = $destination.'.tmp';

    if (file_put_contents($temporaryPath, $body, LOCK_EX) === false) {
        throw new RuntimeException("Не вдалося записати {$temporaryPath}");
    }

    if (!rename($temporaryPath, $destination)) {
        @unlink($temporaryPath);
        throw new RuntimeException("Не вдалося замінити {$destination}");
    }
}

function relativePath(string $root, string $path): string
{
    return str_replace('\\', '/', ltrim(str_replace($root, '', $path), '/\\'));
}

function resolveUrl(string $baseUrl, string $href): string
{
    if (preg_match('~^https?://~i', $href)) {
        return $href;
    }

    if (str_starts_with($href, '//')) {
        return 'https:'.$href;
    }

    $base = parse_url($baseUrl);
    $scheme = $base['scheme'] ?? 'https';
    $host = $base['host'] ?? '';

    if (str_starts_with($href, '/')) {
        return $scheme.'://'.$host.$href;
    }

    $basePath = $base['path'] ?? '/';
    $directory = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
    $combined = ($directory === '' ? '' : $directory).'/'.$href;
    $parts = [];

    foreach (explode('/', $combined) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    return $scheme.'://'.$host.'/'.implode('/', $parts);
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}
