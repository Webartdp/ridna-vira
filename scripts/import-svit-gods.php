<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/vendor/autoload.php';

const SOURCE_PAGE = 'https://www.svit.in.ua/bog.htm';
const USER_AGENT = 'Mozilla/5.0 (compatible; RidnaViraGodMigration/1.0; +https://ridnavira.com.ua)';

$projectRoot = dirname(__DIR__);
$storageRoot = $projectRoot.'/storage/app/content/gods';
$pagesRoot = $storageRoot.'/pages';
$assetRoot = $projectRoot.'/public/assets/gods';
$manifestPath = $storageRoot.'/manifest.json';
$config = require $projectRoot.'/config/faith_gods.php';
$gods = $config['gods'] ?? [];

try {
    ensureDirectory($pagesRoot);
    ensureDirectory($assetRoot);

    $imported = 0;
    $failed = [];
    $entries = [];

    foreach ($gods as $god) {
        if (!is_array($god)) {
            continue;
        }

        $title = (string) ($god['title'] ?? 'Бог');
        $slug = (string) ($god['slug'] ?? 'bog');
        $sourceUrl = (string) ($god['source_url'] ?? '');
        $motif = (string) ($god['motif'] ?? 'world');
        $assetDir = $assetRoot.'/'.$slug;
        $assets = [];
        $entry = [
            'slug' => $slug,
            'title' => $title,
            'source_url' => $sourceUrl,
            'image' => '/assets/gods/'.$slug.'/portrait.svg',
            'assets' => [],
            'path' => null,
            'characters' => 0,
            'images' => 0,
            'summary' => null,
        ];

        try {
            ensureDirectory($assetDir);
            writeGodPortrait($title, $slug, $motif, $assetDir.'/portrait.svg');

            if ($sourceUrl === '') {
                throw new RuntimeException('не задано джерело');
            }

            if (isDocumentUrl($sourceUrl)) {
                $localUrl = archiveRemoteAsset($sourceUrl, $slug, $assetDir, $assets, documentExtensions());
                if ($localUrl === null) {
                    throw new RuntimeException('не вдалося перенести документ');
                }

                $content = documentContent($title, $localUrl);
            } else {
                $response = fetchUrl($sourceUrl);
                $html = normalizeEncoding($response['body'], $response['contentType']);
                $content = sanitizeGodHtml($html, $title, $sourceUrl, $slug, $assetDir, $assets);
            }

            $characters = textLength($content);
            $images = substr_count($content, '<img ');

            if ($characters < 40 && $images === 0) {
                throw new RuntimeException('отримано надто короткий матеріал');
            }

            $destination = $pagesRoot.'/'.$slug.'.html';
            file_put_contents($destination, rtrim($content).PHP_EOL);

            $entry['path'] = relativePath($storageRoot, $destination);
            $entry['characters'] = $characters;
            $entry['images'] = $images;
            $entry['assets'] = $assets;
            $entry['summary'] = makeSummary($content);
            $entries[$slug] = $entry;
            $imported++;

            echo '[OK] '.$title.' -> '.$entry['path'].' ('.$characters.' знаків, '.$images.' зобр.)'.PHP_EOL;
        } catch (Throwable $exception) {
            $failed[] = $title.': '.$exception->getMessage();
            $entries[$slug] = $entry;
            fwrite(STDERR, '[FAIL] '.$title.': '.$exception->getMessage().PHP_EOL);
        }
    }

    $manifest = [
        'generated_at' => date(DATE_ATOM),
        'source' => $config['source'] ?? SOURCE_PAGE,
        'storage' => 'storage/app/content/gods',
        'assets' => 'public/assets/gods',
        'gods_total' => count($gods),
        'gods_imported' => $imported,
        'failed' => $failed,
        'gods' => $entries,
    ];

    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

    echo PHP_EOL;
    echo 'Богів у списку: '.count($gods).PHP_EOL;
    echo 'Богів перенесено: '.$imported.PHP_EOL;
    echo 'Образи: public/assets/gods'.PHP_EOL;
    echo 'Звіт: storage/app/content/gods/manifest.json'.PHP_EOL;

    if ($failed !== []) {
        fwrite(STDERR, PHP_EOL.'Не перенесено '.count($failed).' матеріал(ів):'.PHP_EOL.'- '.implode(PHP_EOL.'- ', $failed).PHP_EOL);
        exit(2);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[FATAL] '.$exception->getMessage().PHP_EOL);
    exit(1);
}

/** @return array{body:string,contentType:string,effectiveUrl:string} */
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
            'Accept: text/html,application/xhtml+xml,image/avif,image/webp,image/apng,image/*,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/rtf,application/octet-stream,*/*;q=0.8',
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
        throw new RuntimeException('Помилка завантаження '.$url.': '.$error);
    }

    if ($status < 200 || $status >= 400) {
        throw new RuntimeException('Сервер повернув HTTP '.$status.' для '.$url);
    }

    if ($body === '') {
        throw new RuntimeException('Отримано порожній файл із '.$url);
    }

    return [
        'body' => $body,
        'contentType' => $contentType,
        'effectiveUrl' => $effectiveUrl !== '' ? $effectiveUrl : $url,
    ];
}

function sanitizeGodHtml(string $html, string $title, string $sourceUrl, string $slug, string $assetDir, array &$assets): string
{
    $html = stripDeclaredCharset(repairMojibakeDeep($html));

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    foreach (['script', 'style', 'iframe', 'form', 'input', 'button', 'svg', 'object', 'embed', 'video', 'audio', 'noscript'] as $tag) {
        removeElementsByTag($dom, $tag);
    }

    $container = largestArticleContainer($dom) ?? $dom->getElementsByTagName('body')->item(0);
    if (!$container instanceof DOMElement) {
        return '';
    }

    $cleanDom = new DOMDocument('1.0', 'UTF-8');
    $wrapper = $cleanDom->createElement('div');
    $cleanDom->appendChild($wrapper);

    foreach ($container->childNodes as $child) {
        $clean = sanitizeNode($child, $cleanDom, $sourceUrl, $slug, $assetDir, $assets);
        if ($clean !== null) {
            $wrapper->appendChild($clean);
        }
    }

    removeDuplicateTitleNode($wrapper, $title);

    $result = '';
    foreach ($wrapper->childNodes as $child) {
        $result .= $cleanDom->saveHTML($child);
    }

    $result = repairMojibakeDeep($result);
    $result = removeEmptyBlocks($result);

    return trim($result);
}

function largestArticleContainer(DOMDocument $dom): ?DOMElement
{
    $best = null;
    $bestScore = PHP_INT_MIN;

    foreach (['article', 'main', 'blockquote', 'td', 'div', 'body'] as $tag) {
        foreach ($dom->getElementsByTagName($tag) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $text = cleanText((string) $node->textContent);
            $textLength = mb_strlen($text, 'UTF-8');
            $images = $node->getElementsByTagName('img')->length;
            $assetLinks = countAssetLinks($node);
            $score = $textLength + ($images * 700) + ($assetLinks * 300) - (legacyNavigationScore($text) * 1200);

            if ($tag === 'body') {
                $score -= 1200;
            }

            if ($textLength < 40 && $images === 0 && $assetLinks === 0) {
                continue;
            }

            if ($best === null || $score > $bestScore) {
                $best = $node;
                $bestScore = $score;
            }
        }
    }

    return $best instanceof DOMElement ? $best : null;
}

function countAssetLinks(DOMElement $node): int
{
    $count = 0;
    foreach ($node->getElementsByTagName('a') as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }

        if (isImportableAssetReference((string) $link->getAttribute('href'))) {
            $count++;
        }
    }

    return $count;
}

function legacyNavigationScore(string $text): int
{
    $normalized = mb_strtolower(cleanText($text), 'UTF-8');
    $score = 0;

    foreach (['новини', 'календар', 'статті', 'книги', 'святині', 'пошук на сайті', 'http://www.svit.in.ua', 'www.svit.in.ua'] as $marker) {
        $score += substr_count($normalized, $marker);
    }

    return $score;
}

function sanitizeNode(DOMNode $node, DOMDocument $targetDom, string $sourceUrl, string $slug, string $assetDir, array &$assets): ?DOMNode
{
    if ($node instanceof DOMText) {
        $text = repairMojibakeDeep(preg_replace('/\s+/u', ' ', $node->nodeValue ?? '') ?? '');

        return trim($text) === '' ? null : $targetDom->createTextNode($text);
    }

    if (!$node instanceof DOMElement) {
        return null;
    }

    $tag = strtolower($node->tagName);
    if (in_array($tag, ['script', 'style', 'iframe', 'form', 'input', 'button', 'svg', 'object', 'embed', 'video', 'audio', 'noscript'], true)) {
        return null;
    }

    if (in_array($tag, ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) && isLegacyAuthorByline((string) $node->textContent)) {
        return null;
    }

    if ($tag === 'img') {
        return archiveImageNode($node, $targetDom, $sourceUrl, $slug, $assetDir, $assets);
    }

    if ($tag === 'a') {
        $href = trim((string) $node->getAttribute('href'));
        $assetUrl = archiveLinkedAsset($href, $sourceUrl, $slug, $assetDir, $assets);
        if ($assetUrl !== null) {
            $copy = $targetDom->createElement('a');
            $copy->setAttribute('href', $assetUrl);
            $copy->appendChild($targetDom->createTextNode(cleanText((string) $node->textContent)));

            return $copy;
        }

        $fragment = $targetDom->createDocumentFragment();
        foreach ($node->childNodes as $child) {
            $clean = sanitizeNode($child, $targetDom, $sourceUrl, $slug, $assetDir, $assets);
            if ($clean !== null) {
                $fragment->appendChild($clean);
            }
        }

        return $fragment->hasChildNodes() ? $fragment : null;
    }

    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr', 'figure', 'figcaption'];

    if (!in_array($tag, $allowed, true)) {
        $fragment = $targetDom->createDocumentFragment();
        foreach ($node->childNodes as $child) {
            $clean = sanitizeNode($child, $targetDom, $sourceUrl, $slug, $assetDir, $assets);
            if ($clean !== null) {
                $fragment->appendChild($clean);
            }
        }

        return $fragment->hasChildNodes() ? $fragment : null;
    }

    $copy = $targetDom->createElement($tag);
    foreach ($node->childNodes as $child) {
        $clean = sanitizeNode($child, $targetDom, $sourceUrl, $slug, $assetDir, $assets);
        if ($clean !== null) {
            $copy->appendChild($clean);
        }
    }

    if (!$copy->hasChildNodes() && !in_array($tag, ['br', 'hr'], true)) {
        return null;
    }

    return $copy;
}

function archiveImageNode(DOMElement $node, DOMDocument $targetDom, string $sourceUrl, string $slug, string $assetDir, array &$assets): ?DOMElement
{
    $src = trim((string) $node->getAttribute('src'));
    if ($src === '' || isLegacyDecorReference($src)) {
        return null;
    }

    $remoteUrl = resolveUrl($src, $sourceUrl);
    $localUrl = archiveRemoteAsset($remoteUrl, $slug, $assetDir, $assets, imageExtensions());
    if ($localUrl === null) {
        return null;
    }

    $copy = $targetDom->createElement('img');
    $copy->setAttribute('src', $localUrl);
    $copy->setAttribute('alt', repairMojibakeDeep(trim((string) $node->getAttribute('alt'))));
    $copy->setAttribute('loading', 'lazy');

    foreach (['width', 'height'] as $attribute) {
        $value = trim((string) $node->getAttribute($attribute));
        if ($value !== '' && ctype_digit($value)) {
            $copy->setAttribute($attribute, $value);
        }
    }

    return $copy;
}

function archiveLinkedAsset(string $href, string $sourceUrl, string $slug, string $assetDir, array &$assets): ?string
{
    if ($href === '' || isLegacyDecorReference($href)) {
        return null;
    }

    return archiveRemoteAsset(resolveUrl($href, $sourceUrl), $slug, $assetDir, $assets, assetExtensions());
}

function archiveRemoteAsset(string $remoteUrl, string $slug, string $assetDir, array &$assets, array $allowedExtensions): ?string
{
    if ($remoteUrl === '' || !isSvitUrl($remoteUrl)) {
        return null;
    }

    $extension = extensionOf($remoteUrl);
    if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
        return null;
    }

    $response = fetchUrl($remoteUrl);
    if ($response['body'] === '') {
        return null;
    }

    $filename = safeAssetFilename($remoteUrl, $extension);
    $destination = uniqueAssetPath($assetDir, $filename);
    file_put_contents($destination, $response['body']);

    $publicUrl = '/assets/gods/'.$slug.'/'.basename($destination);
    $assets[] = [
        'source' => $remoteUrl,
        'path' => $publicUrl,
        'bytes' => strlen($response['body']),
    ];

    return $publicUrl;
}

function normalizeEncoding(string $content, string $contentType = ''): string
{
    $candidates = [];
    foreach (['UTF-8', 'Windows-1251', 'CP1251', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
        $converted = in_array(strtolower($encoding), ['utf-8', 'utf8'], true)
            ? (mb_check_encoding($content, 'UTF-8') ? $content : '')
            : (string) @iconv($encoding, 'UTF-8//IGNORE', $content);

        if ($converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
            $candidates[sha1($converted)] = $converted;
            foreach (repairMojibakeCandidates($converted) as $candidate) {
                if ($candidate !== '' && mb_check_encoding($candidate, 'UTF-8')) {
                    $candidates[sha1($candidate)] = $candidate;
                }
            }
        }
    }

    return chooseBestEncodingCandidate($candidates) ?: $content;
}

/** @return array<int, string> */
function repairMojibakeCandidates(string $text): array
{
    $candidates = [$text];
    foreach (['Windows-1251', 'CP1251', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
        $decoded = decodeMojibakeThrough($text, $encoding);
        if ($decoded !== null && $decoded !== $text) {
            $candidates[] = $decoded;
        }
    }

    return $candidates;
}

function repairMojibakeDeep(string $text): string
{
    return chooseBestEncodingCandidate(repairMojibakeCandidates($text)) ?: $text;
}

function decodeMojibakeThrough(string $text, string $encoding): ?string
{
    $bytes = @iconv('UTF-8', $encoding.'//IGNORE', $text);
    if (!is_string($bytes) || $bytes === '' || !mb_check_encoding($bytes, 'UTF-8')) {
        return null;
    }

    return $bytes;
}

/** @param array<int|string, string> $candidates */
function chooseBestEncodingCandidate(array $candidates): string
{
    $best = null;
    $bestStats = null;

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '' || !mb_check_encoding($candidate, 'UTF-8')) {
            continue;
        }

        $stats = encodingStats($candidate);
        if ($best === null || $bestStats === null || $stats['score'] > $bestStats['score']) {
            $best = $candidate;
            $bestStats = $stats;
        }
    }

    return $best ?? '';
}

/** @return array{score:int,ukrainian:int,mojibake:int,replacement:int} */
function encodingStats(string $text): array
{
    $ukrainian = preg_match_all('/[АБВГҐДЕЄЖЗИІЇЙКЛМНОПРСТУФХЦЧШЩЬЮЯабвгґдеєжзиіїйклмнопрстуфхцчшщьюя]/u', $text) ?: 0;
    $mojibake = mojibakeScore($text);
    $replacement = substr_count($text, '�');

    return [
        'score' => ($ukrainian * 8) - ($mojibake * 90) - ($replacement * 150),
        'ukrainian' => $ukrainian,
        'mojibake' => $mojibake,
        'replacement' => $replacement,
    ];
}

function mojibakeScore(string $text): int
{
    $score = 0;
    foreach (['Р’', 'Р†', 'Р™', 'Р°', 'Рµ', 'РЅ', 'Рѕ', 'СЂ', 'СЃ', 'С‚', 'С–', 'С—', 'С”', 'вЂ', 'Ð', 'Ñ', 'Â', 'Ã', '�'] as $marker) {
        $score += substr_count($text, $marker) * ($marker === '�' ? 20 : 1);
    }

    return $score;
}

function documentContent(string $title, string $localUrl): string
{
    $title = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $localUrl = htmlspecialchars($localUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return '<p>Матеріал розділу перенесено як окремий файл.</p><p><a href="'.$localUrl.'">Завантажити матеріал: '.$title.'</a></p>';
}

function makeSummary(string $html): string
{
    $text = cleanText(strip_tags($html));
    if (mb_strlen($text, 'UTF-8') <= 180) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, 180, 'UTF-8')).'...';
}

function removeDuplicateTitleNode(DOMElement $wrapper, string $title): void
{
    foreach (iterator_to_array($wrapper->childNodes) as $child) {
        if (!$child instanceof DOMElement) {
            if ($child instanceof DOMText && trim($child->nodeValue ?? '') === '') {
                continue;
            }
            break;
        }

        $tag = strtolower($child->tagName);
        if (!in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
            break;
        }

        if (headingMatchesTitle(cleanText((string) $child->textContent), $title)) {
            $wrapper->removeChild($child);
        }
        break;
    }
}

function headingMatchesTitle(string $heading, string $title): bool
{
    $heading = normalizeTitle($heading);
    $title = normalizeTitle($title);

    return $heading !== '' && $title !== '' && ($heading === $title || str_contains($heading, $title) || str_contains($title, $heading));
}

function normalizeTitle(string $text): string
{
    $text = mb_strtolower(cleanText($text), 'UTF-8');
    $text = str_replace(['’', 'ʼ', '`', '*', '—', '–', '.', ',', ':', ';', '(', ')', '"', "'"], ' ', $text);

    return cleanText($text);
}

function isLegacyAuthorByline(string $text): bool
{
    $key = mb_strtolower(cleanText($text), 'UTF-8');
    $key = str_replace([' ', '.', ',', ':', ';', '-', '—', '–', "'", '’', 'ʼ'], '', $key);

    return in_array($key, [
        'світовитпашник',
        'волхврідноївіри',
        'волхврпк',
        'світовитпашникволхврідноївіри',
        'світовитпашникволхврпк',
    ], true);
}

function removeEmptyBlocks(string $html): string
{
    $html = preg_replace('/<p[^>]*>[\s&;nbsp]*<\/p>/iu', '', $html) ?? $html;
    $html = preg_replace('/<(blockquote|div)[^>]*>[\s&;nbsp]*<\/\1>/iu', '', $html) ?? $html;

    return $html;
}

function stripDeclaredCharset(string $html): string
{
    $html = preg_replace('/<meta[^>]*charset[^>]*>/i', '', $html) ?? $html;

    return preg_replace('/<meta[^>]*http-equiv[^>]*content-type[^>]*>/i', '', $html) ?? $html;
}

function isDocumentUrl(string $url): bool
{
    return in_array(extensionOf($url), documentExtensions(), true);
}

function isImportableAssetReference(string $reference): bool
{
    $extension = extensionOf($reference);

    return $extension !== '' && in_array($extension, assetExtensions(), true) && !isLegacyDecorReference($reference);
}

function isLegacyDecorReference(string $reference): bool
{
    $basename = basename(normalizedReferencePath($reference));

    return str_ends_with($basename, '.gif') || in_array($basename, ['rozdil.gif', 'lin.gif', 'line.gif', 'artic.gif', 'article.gif', 'spacer.gif', 'blank.gif', 'pixel.gif', 'dot.gif', 'hr.gif', 'punkt.gif', 'bullet.gif'], true);
}

function extensionOf(string $reference): string
{
    return strtolower(pathinfo(normalizedReferencePath($reference), PATHINFO_EXTENSION));
}

function normalizedReferencePath(string $reference): string
{
    $decoded = trim(html_entity_decode($reference, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $path = (string) (parse_url($decoded, PHP_URL_PATH) ?? $decoded);

    return strtolower(rawurldecode($path));
}

/** @return array<int, string> */
function imageExtensions(): array
{
    return ['jpg', 'jpeg', 'png', 'webp', 'svg'];
}

/** @return array<int, string> */
function documentExtensions(): array
{
    return ['pdf', 'doc', 'docx', 'djvu', 'rtf', 'rar', 'zip'];
}

/** @return array<int, string> */
function assetExtensions(): array
{
    return array_merge(imageExtensions(), documentExtensions());
}

function resolveUrl(string $href, string $baseUrl): string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($href === '' || str_starts_with($href, '#')) {
        return '';
    }

    $scheme = (string) (parse_url($href, PHP_URL_SCHEME) ?? '');
    if ($scheme !== '') {
        return $href;
    }

    $base = parse_url($baseUrl);
    $baseScheme = (string) ($base['scheme'] ?? 'https');
    $host = (string) ($base['host'] ?? 'www.svit.in.ua');

    if (str_starts_with($href, '//')) {
        return $baseScheme.':'.$href;
    }

    if (str_starts_with($href, '/')) {
        return $baseScheme.'://'.$host.$href;
    }

    $basePath = (string) ($base['path'] ?? '/');
    $directory = dirname($basePath);
    if ($directory === '.' || $directory === '\\') {
        $directory = '';
    }

    return $baseScheme.'://'.$host.normalizeUrlPath('/'.$directory.'/'.$href);
}

function normalizeUrlPath(string $path): string
{
    $segments = [];
    foreach (explode('/', $path) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    return '/'.implode('/', $segments);
}

function isSvitUrl(string $url): bool
{
    $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));

    return in_array($host, ['svit.in.ua', 'www.svit.in.ua'], true);
}

function safeAssetFilename(string $url, string $extension): string
{
    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
    $base = rawurldecode(basename($path));
    $base = preg_replace('/[^A-Za-z0-9._-]+/', '-', $base) ?? '';
    $base = trim($base, '.-_');

    if ($base === '' || !str_contains($base, '.')) {
        $base = sha1($url).'.'.$extension;
    }

    return $base;
}

function uniqueAssetPath(string $directory, string $filename): string
{
    $path = $directory.'/'.$filename;
    if (!is_file($path)) {
        return $path;
    }

    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $stem = pathinfo($filename, PATHINFO_FILENAME);
    $suffix = 2;

    do {
        $candidate = $directory.'/'.$stem.'-'.$suffix.($extension !== '' ? '.'.$extension : '');
        $suffix++;
    } while (is_file($candidate));

    return $candidate;
}

function writeGodPortrait(string $title, string $slug, string $motif, string $destination): void
{
    [$background, $accent, $ink, $light] = paletteForMotif($motif, $slug);
    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_XML1, 'UTF-8');
    $initial = htmlspecialchars(mb_substr($title, 0, 1, 'UTF-8'), ENT_QUOTES | ENT_XML1, 'UTF-8');
    $symbol = motifSvg($motif, $accent, $ink, $light);
    $id = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? $slug;

    $svg = <<<SVG
<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 720 900' role='img' aria-labelledby='title-{$id} desc-{$id}'>
  <title id='title-{$id}'>{$safeTitle}</title>
  <desc id='desc-{$id}'>Векторний образ для розділу Боги Рідної Віри</desc>
  <defs>
    <radialGradient id='glow-{$id}' cx='50%' cy='30%' r='70%'>
      <stop offset='0%' stop-color='{$light}' stop-opacity='0.95'/>
      <stop offset='62%' stop-color='{$background}' stop-opacity='1'/>
      <stop offset='100%' stop-color='{$ink}' stop-opacity='1'/>
    </radialGradient>
    <filter id='soft-{$id}' x='-20%' y='-20%' width='140%' height='140%'>
      <feDropShadow dx='0' dy='18' stdDeviation='18' flood-color='#1c0803' flood-opacity='0.28'/>
    </filter>
  </defs>
  <rect width='720' height='900' fill='url(#glow-{$id})'/>
  <path d='M90 90 H630 V810 H90 Z' fill='none' stroke='{$light}' stroke-opacity='0.5' stroke-width='6'/>
  <path d='M126 126 H594 V774 H126 Z' fill='none' stroke='{$accent}' stroke-opacity='0.35' stroke-width='2'/>
  <g filter='url(#soft-{$id})'>{$symbol}</g>
  <circle cx='360' cy='438' r='118' fill='none' stroke='{$light}' stroke-opacity='0.24' stroke-width='16'/>
  <text x='360' y='466' text-anchor='middle' font-family='Georgia, Times New Roman, serif' font-size='138' font-weight='700' fill='{$light}' opacity='0.22'>{$initial}</text>
  <text x='360' y='744' text-anchor='middle' font-family='Georgia, Times New Roman, serif' font-size='44' font-weight='700' fill='{$light}' letter-spacing='2'>{$safeTitle}</text>
  <path d='M250 782 H470' stroke='{$accent}' stroke-width='5' stroke-linecap='round'/>
</svg>
SVG;

    file_put_contents($destination, $svg);
}

/** @return array{0:string,1:string,2:string,3:string} */
function paletteForMotif(string $motif, string $slug): array
{
    $palettes = [
        'sun' => ['#5a2a17', '#f2b93b', '#1f150d', '#fff0b8'],
        'sun-rider' => ['#623018', '#f6c14a', '#1c120b', '#fff2bd'],
        'thunder' => ['#263a2c', '#f0bf36', '#131b16', '#f8edc6'],
        'water' => ['#234555', '#6dc7d6', '#10222a', '#d8f7f3'],
        'winter' => ['#2f4050', '#b9d9ef', '#101b24', '#eef8ff'],
        'moon' => ['#252635', '#c8c8d8', '#0f1018', '#f2f0ff'],
        'loom' => ['#48321f', '#d7a84c', '#1b120b', '#ffe2a6'],
        'forge' => ['#3d3027', '#e06b34', '#160e0a', '#ffe0bf'],
        'wind' => ['#334842', '#94d2bd', '#13211e', '#def7ec'],
    ];

    if (isset($palettes[$motif])) {
        return $palettes[$motif];
    }

    $hash = hexdec(substr(sha1($slug), 0, 6));
    $hue = $hash % 360;

    return [
        'hsl('.$hue.' 38% 24%)',
        'hsl('.(($hue + 45) % 360).' 72% 58%)',
        'hsl('.$hue.' 40% 10%)',
        'hsl('.(($hue + 30) % 360).' 82% 88%)',
    ];
}

function motifSvg(string $motif, string $accent, string $ink, string $light): string
{
    $sun = "<g transform='translate(360 390)'><circle r='78' fill='{$accent}'/><g stroke='{$accent}' stroke-width='16' stroke-linecap='round'><path d='M0 -150 V-118'/><path d='M0 118 V150'/><path d='M-150 0 H-118'/><path d='M118 0 H150'/><path d='M-106 -106 L-84 -84'/><path d='M106 -106 L84 -84'/><path d='M-106 106 L-84 84'/><path d='M106 106 L84 84'/></g></g>";

    return match ($motif) {
        'horns' => "<g transform='translate(360 410)' fill='none' stroke='{$accent}' stroke-width='24' stroke-linecap='round'><path d='M-120 -40 C-210 -118 -210 -226 -132 -268'/><path d='M120 -40 C210 -118 210 -226 132 -268'/><circle cx='0' cy='12' r='96' fill='{$ink}' stroke='{$light}' stroke-width='10'/><path d='M-58 100 C-25 132 25 132 58 100'/></g>",
        'eye' => "<g transform='translate(360 410)'><path d='M-180 0 C-92 -95 92 -95 180 0 C92 95 -92 95 -180 0Z' fill='{$ink}' stroke='{$accent}' stroke-width='18'/><circle r='68' fill='{$accent}'/><circle r='28' fill='{$light}'/></g>",
        'mountain' => "<g transform='translate(360 430)'><path d='M-210 140 L-70 -130 L25 50 L95 -82 L230 140Z' fill='{$ink}' stroke='{$accent}' stroke-width='14'/><path d='M-70 -130 L-32 -58 L-96 -58Z' fill='{$light}'/><path d='M95 -82 L126 -22 L70 -22Z' fill='{$light}'/></g>",
        'water' => "<g transform='translate(360 420)' fill='none' stroke='{$accent}' stroke-width='22' stroke-linecap='round'><path d='M-210 -40 C-150 -92 -90 12 -30 -40 S90 -92 150 -40 210 12 250 -20'/><path d='M-210 40 C-150 -12 -90 92 -30 40 S90 -12 150 40 210 92 250 60'/><path d='M-110 130 C-58 96 2 158 60 126 S146 94 206 126'/></g>",
        'bird', 'winged-fire' => "<g transform='translate(360 410)' fill='{$accent}'><path d='M0 -132 C-46 -44 -118 10 -230 34 C-128 58 -44 44 0 -22 C44 44 128 58 230 34 C118 10 46 -44 0 -132Z'/><path d='M0 -34 C-34 16 -32 70 0 118 C32 70 34 16 0 -34Z' fill='{$light}'/></g>",
        'thread', 'loom', 'cradle', 'calendar' => "<g transform='translate(360 410)' fill='none' stroke='{$accent}' stroke-width='16' stroke-linecap='round'><path d='M-150 -150 H150 V150 H-150Z'/><path d='M-150 -50 H150'/><path d='M-150 50 H150'/><path d='M-50 -150 V150'/><path d='M50 -150 V150'/><path d='M0 -116 L116 0 L0 116 L-116 0Z' stroke='{$light}'/></g>",
        'tear', 'flame-tear' => "<g transform='translate(360 420)'><path d='M0 -170 C95 -36 130 34 96 112 C64 184 -64 184 -96 112 C-130 34 -95 -36 0 -170Z' fill='{$accent}' stroke='{$light}' stroke-width='10'/><path d='M0 -56 C38 10 50 44 34 82 C18 120 -18 120 -34 82 C-50 44 -38 10 0 -56Z' fill='{$ink}' opacity='0.45'/></g>",
        'star', 'dawn-star' => "<g transform='translate(360 410)'><path d='M0 -188 L42 -44 L188 0 L42 44 L0 188 L-42 44 L-188 0 L-42 -44Z' fill='{$accent}'/><circle r='56' fill='{$light}' opacity='0.75'/></g>",
        'fire-water' => "<g transform='translate(360 420)'><path d='M0 -176 C82 -70 96 -6 42 56 C96 40 132 78 120 132 C106 190 -106 190 -120 132 C-132 78 -96 40 -42 56 C-96 -6 -82 -70 0 -176Z' fill='{$accent}'/><path d='M-180 130 C-110 90 -70 168 0 130 S110 90 180 130' fill='none' stroke='{$light}' stroke-width='18' stroke-linecap='round'/></g>",
        'wreath' => "<g transform='translate(360 420)' fill='none' stroke='{$accent}' stroke-width='18' stroke-linecap='round'><circle r='128'/><path d='M-92 -92 C-150 -60 -176 -10 -160 48'/><path d='M92 -92 C150 -60 176 -10 160 48'/><path d='M-62 20 C-22 72 22 72 62 20' stroke='{$light}'/></g>",
        'thunder' => "<g transform='translate(360 410)'><path d='M42 -190 L-90 30 H12 L-42 190 L118 -44 H16Z' fill='{$accent}' stroke='{$light}' stroke-width='8'/><path d='M-128 144 C-50 100 50 100 128 144' fill='none' stroke='{$ink}' stroke-width='18' stroke-linecap='round'/></g>",
        'winter', 'snow' => "<g transform='translate(360 410)' stroke='{$accent}' stroke-width='18' stroke-linecap='round'><path d='M0 -170 V170'/><path d='M-147 -85 L147 85'/><path d='M147 -85 L-147 85'/><circle r='62' fill='{$light}' stroke='none' opacity='0.75'/></g>",
        'moon', 'gate' => "<g transform='translate(360 410)'><path d='M70 -170 C-70 -125 -110 92 64 168 C-62 178 -170 78 -170 -54 C-170 -174 -48 -242 70 -170Z' fill='{$accent}'/><path d='M-90 170 V30 C-90 -60 -36 -110 0 -110 C36 -110 90 -60 90 30 V170' fill='none' stroke='{$light}' stroke-width='18'/></g>",
        'pillar' => "<g transform='translate(360 410)'><path d='M-72 -190 H72 L104 170 H-104Z' fill='{$ink}' stroke='{$accent}' stroke-width='14'/><circle cy='-90' r='38' fill='{$light}'/><circle cy='10' r='38' fill='{$accent}'/><circle cy='110' r='38' fill='{$light}' opacity='0.8'/></g>",
        'wind' => "<g transform='translate(360 410)' fill='none' stroke='{$accent}' stroke-width='20' stroke-linecap='round'><path d='M-210 -70 H80 C156 -70 156 -150 76 -150'/><path d='M-170 20 H180 C252 20 252 -58 182 -58'/><path d='M-210 108 H78 C154 108 154 188 74 188'/></g>",
        'world-tree', 'world' => "<g transform='translate(360 430)' fill='none' stroke='{$accent}' stroke-width='18' stroke-linecap='round'><path d='M0 170 V-170'/><path d='M0 -116 C-110 -140 -178 -82 -190 2'/><path d='M0 -116 C110 -140 178 -82 190 2'/><path d='M0 -20 C-92 -28 -132 28 -144 92'/><path d='M0 -20 C92 -28 132 28 144 92'/><path d='M-88 170 C-38 122 38 122 88 170' stroke='{$light}'/></g>",
        'forge' => "<g transform='translate(360 430)'><path d='M-150 -80 H150 L110 20 H-110Z' fill='{$ink}' stroke='{$accent}' stroke-width='14'/><path d='M-80 20 H80 V130 H-80Z' fill='{$accent}'/><path d='M-180 150 H180' stroke='{$light}' stroke-width='18' stroke-linecap='round'/></g>",
        'sprout-sun', 'spear-sun' => "{$sun}<g transform='translate(360 500)' fill='none' stroke='{$light}' stroke-width='18' stroke-linecap='round'><path d='M0 150 V-20'/><path d='M0 54 C-72 34 -108 -10 -112 -76'/><path d='M0 78 C72 58 108 14 112 -52'/></g>",
        'solar-horse', 'sun-horse' => "{$sun}<g transform='translate(360 530)' fill='none' stroke='{$light}' stroke-width='18' stroke-linecap='round'><path d='M-168 48 C-80 -52 66 -52 152 42'/><path d='M-92 80 V142'/><path d='M86 80 V142'/><path d='M152 42 L198 10'/></g>",
        'horizon' => "<g transform='translate(360 430)'>{$sun}<path d='M-220 112 H220' stroke='{$light}' stroke-width='20' stroke-linecap='round'/><path d='M-170 162 H170' stroke='{$accent}' stroke-width='12' stroke-linecap='round'/></g>",
        'thorn' => "<g transform='translate(360 420)' fill='none' stroke='{$accent}' stroke-width='18' stroke-linecap='round'><path d='M0 -170 C-74 -92 -82 -8 0 70 C82 -8 74 -92 0 -170Z' fill='{$ink}'/><path d='M0 -94 V170'/><path d='M0 -20 L-92 -88'/><path d='M0 34 L92 -34'/></g>",
        default => $sun,
    };
}

function textLength(string $html): int
{
    return mb_strlen(cleanText(strip_tags($html)), 'UTF-8');
}

function cleanText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function relativePath(string $root, string $path): string
{
    return str_replace('\\', '/', ltrim(str_replace($root, '', $path), '/\\'));
}

function removeElementsByTag(DOMDocument $dom, string $tag): void
{
    while (true) {
        $nodes = $dom->getElementsByTagName($tag);
        if ($nodes->length === 0) {
            break;
        }

        $node = $nodes->item(0);
        $node?->parentNode?->removeChild($node);
    }
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Не вдалося створити каталог '.$directory);
    }
}
