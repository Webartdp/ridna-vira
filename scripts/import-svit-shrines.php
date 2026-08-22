<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require_once dirname(__DIR__).'/vendor/autoload.php';

/**
 * Archives the legacy shrine section from svit.in.ua/sva.htm.
 * The importer keeps the public pages local: body HTML goes to storage, while
 * images and linked documents are copied under public/assets/shrines.
 */

const SOURCE_PAGE = 'https://www.svit.in.ua/sva.htm';
const USER_AGENT = 'Mozilla/5.0 (compatible; RidnaViraShrineMigration/1.0; +https://ridnavira.com.ua)';

$projectRoot = dirname(__DIR__);
$storageRoot = $projectRoot.'/storage/app/content/shrines';
$pagesRoot = $storageRoot.'/pages';
$assetRoot = $projectRoot.'/public/assets/shrines';
$manifestPath = $storageRoot.'/manifest.json';

try {
    ensureDirectory($pagesRoot);
    ensureDirectory($assetRoot);

    $sourceResponse = fetchUrl(SOURCE_PAGE);
    $sourceHtml = normalizeEncoding($sourceResponse['body'], $sourceResponse['contentType']);
    $links = extractShrineLinks($sourceHtml, SOURCE_PAGE);

    if ($links === []) {
        throw new RuntimeException('На сторінці святинь не знайдено матеріалів для імпорту.');
    }

    echo 'Знайдено святинь: '.count($links).PHP_EOL;

    $shrines = [];
    $regions = [];
    $failed = [];
    $usedSlugs = [];
    $imported = 0;

    foreach ($links as $link) {
        $title = $link['title'];
        $slug = uniqueSlug($title, $link['url'], $usedSlugs);
        $assetDir = $assetRoot.'/'.$slug;
        $assets = [];

        try {
            ensureDirectory($assetDir);

            $response = fetchUrl($link['url']);
            $html = normalizeEncoding($response['body'], $response['contentType']);
            $content = sanitizeShrineHtml($html, $title, $link['url'], $slug, $assetDir, $assets);
            $characters = textLength($content);
            $images = substr_count($content, '<img ');

            if ($characters < 40 && $images === 0) {
                throw new RuntimeException('отримано надто короткий матеріал');
            }

            $destination = $pagesRoot.'/'.$slug.'.html';
            file_put_contents($destination, rtrim($content).PHP_EOL);

            $entry = [
                'slug' => $slug,
                'title' => $title,
                'region' => $link['region'],
                'path' => relativePath($storageRoot, $destination),
                'characters' => $characters,
                'images' => $images,
                'assets' => $assets,
                'source_url' => $link['url'],
            ];

            $shrines[$slug] = $entry;
            $regions[$link['region']] = $link['region'];
            $imported++;

            echo '[OK] '.$title.' -> '.$entry['path'].' ('.$characters.' знаків, '.$images.' зобр.)'.PHP_EOL;
        } catch (Throwable $exception) {
            $failed[] = $title.': '.$exception->getMessage();
            fwrite(STDERR, '[FAIL] '.$title.': '.$exception->getMessage().PHP_EOL);
        }
    }

    $manifest = [
        'generated_at' => date(DATE_ATOM),
        'source' => SOURCE_PAGE,
        'storage' => 'storage/app/content/shrines',
        'assets' => 'public/assets/shrines',
        'shrines_total' => count($links),
        'shrines_imported' => $imported,
        'regions' => array_values($regions),
        'failed' => $failed,
        'shrines' => $shrines,
    ];

    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);

    echo PHP_EOL;
    echo 'Святинь у списку: '.count($links).PHP_EOL;
    echo 'Святинь перенесено: '.$imported.PHP_EOL;
    echo 'Областей: '.count($regions).PHP_EOL;
    echo 'Звіт: storage/app/content/shrines/manifest.json'.PHP_EOL;

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

/**
 * @return array<int, array{title:string,region:string,url:string}>
 */
function extractShrineLinks(string $html, string $baseUrl): array
{
    $parts = preg_split('~(<a\b[^>]*>.*?</a>)~isu', stripDeclaredCharset($html), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
    if ($parts === false) {
        return [];
    }

    $links = [];
    $seen = [];
    $currentRegion = 'Святині';

    foreach ($parts as $part) {
        if (preg_match('~^<a\b~iu', $part)) {
            $label = cleanText(strip_tags($part));
            if (!str_starts_with($label, '-')) {
                continue;
            }

            $title = trim((string) preg_replace('/^[-\s]+/u', '', $label));
            $url = resolveUrl(anchorHref($part), $baseUrl);

            if ($title === '' || !isImportableLegacyPage($url)) {
                continue;
            }

            $key = strtolower($url);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $links[] = [
                'title' => $title,
                'region' => $currentRegion,
                'url' => $url,
            ];
            continue;
        }

        $text = cleanText(strip_tags($part));
        if ($text === '') {
            continue;
        }

        if (preg_match_all('~([А-ЯІЇЄҐ][А-Яа-яІЇЄҐіїєґ\s\-]+(?:обл\.|Автономна Республіка Крим|Росія))\s*:~u', $text, $matches) && $matches[1] !== []) {
            $currentRegion = trim((string) end($matches[1]));
        }
    }

    return $links;
}

function sanitizeShrineHtml(string $html, string $title, string $sourceUrl, string $slug, string $assetDir, array &$assets): string
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

    $result = '';
    foreach ($wrapper->childNodes as $child) {
        $result .= $cleanDom->saveHTML($child);
    }

    $result = repairMojibakeDeep($result);
    $result = removeDuplicateTitleHeadings($result, $title);
    $result = removeLegacyAuthorBylines($result);
    $result = removeLegacySvitLinks($result);
    $result = removeEmptyBlocks($result);
    $result = preg_replace('~(?:\s|&nbsp;){2,}~u', ' ', $result) ?? $result;

    return trim($result);
}

function largestArticleContainer(DOMDocument $dom): ?DOMElement
{
    $bestBlockquote = null;
    $bestBlockquoteLength = 0;

    foreach ($dom->getElementsByTagName('blockquote') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $length = mb_strlen(cleanText((string) $node->textContent), 'UTF-8');
        if ($length > $bestBlockquoteLength) {
            $bestBlockquote = $node;
            $bestBlockquoteLength = $length;
        }
    }

    if ($bestBlockquote instanceof DOMElement && $bestBlockquoteLength >= 40) {
        return $bestBlockquote;
    }

    $best = null;
    $bestLength = 0;

    foreach (['article', 'main', 'td', 'div', 'body'] as $tag) {
        foreach ($dom->getElementsByTagName($tag) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $length = mb_strlen(cleanText((string) $node->textContent), 'UTF-8');
            if ($length > $bestLength) {
                $best = $node;
                $bestLength = $length;
            }
        }
    }

    return $best instanceof DOMElement ? $best : null;
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
    if ($src === '') {
        return null;
    }

    $remoteUrl = resolveUrl($src, $sourceUrl);
    $localUrl = archiveRemoteAsset($remoteUrl, $slug, $assetDir, $assets, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    if ($localUrl === null) {
        return null;
    }

    $copy = $targetDom->createElement('img');
    $copy->setAttribute('src', $localUrl);
    $copy->setAttribute('alt', repairMojibakeDeep(trim((string) $node->getAttribute('alt'))));
    $copy->setAttribute('loading', 'lazy');

    foreach (['width', 'height'] as $attribute) {
        $value = trim((string) $node->getAttribute($attribute));
        if ($value !== '' && preg_match('/^\d+$/', $value)) {
            $copy->setAttribute($attribute, $value);
        }
    }

    return $copy;
}

function archiveLinkedAsset(string $href, string $sourceUrl, string $slug, string $assetDir, array &$assets): ?string
{
    if ($href === '') {
        return null;
    }

    $remoteUrl = resolveUrl($href, $sourceUrl);

    return archiveRemoteAsset($remoteUrl, $slug, $assetDir, $assets, ['pdf', 'doc', 'docx', 'djvu', 'rtf', 'rar', 'zip', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
}

function archiveRemoteAsset(string $remoteUrl, string $slug, string $assetDir, array &$assets, array $allowedExtensions): ?string
{
    if ($remoteUrl === '' || !isSvitUrl($remoteUrl)) {
        return null;
    }

    $path = (string) (parse_url($remoteUrl, PHP_URL_PATH) ?? '');
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
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

    $publicUrl = '/assets/shrines/'.$slug.'/'.basename($destination);
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

    foreach (detectEncodingCandidates($content, $contentType) as $encoding) {
        $converted = in_array(strtolower($encoding), ['utf-8', 'utf8'], true)
            ? (mb_check_encoding($content, 'UTF-8') ? $content : '')
            : (string) @iconv($encoding, 'UTF-8//IGNORE', $content);

        addEncodingCandidate($candidates, $converted);
    }

    if ($candidates === []) {
        addEncodingCandidate($candidates, (string) @iconv('UTF-8', 'UTF-8//IGNORE', $content));
    }

    return chooseBestEncodingCandidate($candidates) ?: $content;
}

/** @return array<int, string> */
function detectEncodingCandidates(string $content, string $contentType): array
{
    $encodings = [];

    if (preg_match('/charset\s*=\s*["\']?([^;"\'\s>]+)/i', $contentType, $match)) {
        $encodings[] = trim($match[1]);
    }

    $head = substr($content, 0, 16384);
    if (preg_match('/<meta[^>]+charset\s*=\s*["\']?([^"\'\s>]+)/i', $head, $match)) {
        $encodings[] = trim($match[1]);
    }
    if (preg_match('/<meta[^>]+content\s*=\s*["\'][^"\']*charset\s*=\s*([^;"\'\s>]+)/i', $head, $match)) {
        $encodings[] = trim($match[1]);
    }

    $encodings = array_merge($encodings, ['UTF-8', 'Windows-1251', 'CP1251', 'KOI8-U', 'Windows-1252', 'ISO-8859-1']);
    $unique = [];

    foreach ($encodings as $encoding) {
        $encoding = trim($encoding);
        if ($encoding !== '') {
            $unique[strtolower($encoding)] = $encoding;
        }
    }

    return array_values($unique);
}

/** @param array<string, string> $candidates */
function addEncodingCandidate(array &$candidates, string $text): void
{
    if ($text === '' || !mb_check_encoding($text, 'UTF-8')) {
        return;
    }

    foreach (repairMojibakeCandidates($text) as $candidate) {
        if ($candidate !== '' && mb_check_encoding($candidate, 'UTF-8')) {
            $candidates[sha1($candidate)] = $candidate;
        }
    }
}

/** @return array<int, string> */
function repairMojibakeCandidates(string $text): array
{
    $candidates = [$text];
    $queue = [$text];

    for ($round = 0; $round < 3; $round++) {
        $next = [];
        foreach ($queue as $candidate) {
            foreach (['Windows-1251', 'CP1251', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
                $decoded = decodeMojibakeThrough($candidate, $encoding);
                if ($decoded !== null && $decoded !== $candidate) {
                    $next[] = $decoded;
                }

                $chunked = repairMojibakeChunks($candidate, $encoding);
                if ($chunked !== $candidate) {
                    $next[] = $chunked;
                }
            }
        }

        $queue = [];
        foreach ($next as $candidate) {
            if ($candidate !== '' && mb_check_encoding($candidate, 'UTF-8')) {
                $key = sha1($candidate);
                if (!isset($candidates[$key])) {
                    $candidates[$key] = $candidate;
                    $queue[] = $candidate;
                }
            }
        }

        if ($queue === []) {
            break;
        }
    }

    return array_values($candidates);
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

function repairMojibakeChunks(string $text, string $encoding): string
{
    if (!hasMojibake($text)) {
        return $text;
    }

    $parts = preg_split('~(<[^>]+>|\s+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $text;
    }

    foreach ($parts as $index => $part) {
        if ($part === '' || str_starts_with($part, '<') || !hasMojibake($part)) {
            continue;
        }

        $decoded = decodeMojibakeThrough($part, $encoding);
        if ($decoded !== null && isBetterEncodingStats(encodingStats($decoded), encodingStats($part))) {
            $parts[$index] = $decoded;
        }
    }

    return implode('', $parts);
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

        $candidate = stripUtf8Bom($candidate);
        $stats = encodingStats($candidate);

        if ($best === null || $bestStats === null || isBetterEncodingStats($stats, $bestStats)) {
            $best = $candidate;
            $bestStats = $stats;
        }
    }

    return $best ?? '';
}

/** @return array{score:int,ukrainian:int,mojibake:int,replacement:int,length:int} */
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
        'length' => mb_strlen($text, 'UTF-8'),
    ];
}

/** @param array{score:int,ukrainian:int,mojibake:int,replacement:int,length:int} $candidate */
/** @param array{score:int,ukrainian:int,mojibake:int,replacement:int,length:int} $current */
function isBetterEncodingStats(array $candidate, array $current): bool
{
    if ($candidate['score'] !== $current['score']) {
        return $candidate['score'] > $current['score'];
    }

    if ($candidate['mojibake'] !== $current['mojibake']) {
        return $candidate['mojibake'] < $current['mojibake'];
    }

    if ($candidate['replacement'] !== $current['replacement']) {
        return $candidate['replacement'] < $current['replacement'];
    }

    if ($candidate['ukrainian'] !== $current['ukrainian']) {
        return $candidate['ukrainian'] > $current['ukrainian'];
    }

    return $candidate['length'] > $current['length'];
}

function hasMojibake(string $text): bool
{
    return mojibakeScore($text) > 0;
}

function mojibakeScore(string $text): int
{
    $score = 0;
    $markers = [
        'Р’', 'Рђ', 'Р†', 'Р™', 'РЋ', 'Р°', 'Р±', 'РІ', 'Рі', 'Рґ', 'Рµ', 'Р¶', 'Р·', 'Рё', 'Р№', 'Рє', 'Р»', 'Рј', 'РЅ', 'Рѕ', 'Рї', 'СЂ', 'СЃ', 'С‚', 'Сѓ', 'С„', 'С…', 'С†', 'С‡', 'С€', 'С‰', 'СЊ', 'СЋ', 'СЏ', 'С–', 'С—', 'С”',
        'вЂ', 'в„', 'в€¦', 'в‚', 'Ð', 'Ñ', 'Â', 'Ã', 'ЃР', 'ЃС', '�',
    ];

    foreach ($markers as $marker) {
        $score += substr_count($text, $marker) * ($marker === '�' ? 20 : 1);
    }

    $matches = preg_match_all('/[\x{0080}-\x{009F}]/u', $text);
    if ($matches !== false) {
        $score += $matches * 4;
    }

    return $score;
}

function removeDuplicateTitleHeadings(string $html, string $title): string
{
    return preg_replace_callback(
        '~^\s*<h[1-6]\b[^>]*>(.*?)</h[1-6]>\s*~isu',
        static fn (array $match): string => headingMatchesTitle(cleanText(strip_tags($match[1])), $title) ? '' : $match[0],
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

function removeLegacySvitLinks(string $html): string
{
    $html = preg_replace_callback(
        '~<(p|li|h[1-6])\b[^>]*>(.*?)</\1>\s*~isu',
        static fn (array $match): string => containsLegacySvitUrl($match[0]) ? '' : $match[0],
        $html
    ) ?? $html;

    $html = preg_replace(
        '~<a\b[^>]*href\s*=\s*(["\'])https?://(?:www\.)?svit\.in\.ua[^"\']*\1[^>]*>.*?</a>\s*~isu',
        '',
        $html
    ) ?? $html;

    return preg_replace('~\s*https?://(?:www\.)?svit\.in\.ua[^\s<"\']*~iu', '', $html) ?? $html;
}

function removeEmptyBlocks(string $html): string
{
    $html = preg_replace('~<p\b[^>]*>\s*(?:&nbsp;)?\s*</p>\s*~iu', '', $html) ?? $html;

    return preg_replace('~<(?:blockquote|div)\b[^>]*>\s*</(?:blockquote|div)>\s*~iu', '', $html) ?? $html;
}

function isLegacyAuthorByline(string $html): bool
{
    $text = cleanText(strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], ' ', $html)));
    $key = preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($text, 'UTF-8')) ?? '';

    return in_array($key, [
        'світовитпашник',
        'волхврідноївіри',
        'волхврпк',
        'світовитпашникволхврідноївіри',
        'світовитпашникволхврпк',
    ], true);
}

function containsLegacySvitUrl(string $html): bool
{
    $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return preg_match('~https?://(?:www\.)?svit\.in\.ua|(?:^|[\s/"\'>])(?:www\.)?svit\.in\.ua~iu', $decoded) === 1;
}

function headingMatchesTitle(string $heading, string $title): bool
{
    $heading = normalizeTitle($heading);
    $title = normalizeTitle($title);

    if ($heading === '' || $title === '') {
        return false;
    }

    if ($heading === $title || str_contains($heading, $title) || str_contains($title, $heading)) {
        return true;
    }

    similar_text($heading, $title, $percent);

    return $percent >= 88;
}

function normalizeTitle(string $text): string
{
    $text = mb_strtolower(cleanText($text), 'UTF-8');
    $text = str_replace(['’', 'ʼ', '`', '*', '—', '–', '.', ',', ':', ';', '(', ')', '"', "'"], ' ', $text);
    $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text) ?? $text;

    return cleanText($text);
}

function anchorHref(string $anchorHtml): string
{
    if (!preg_match('~href\s*=\s*(["\'])(.*?)\1~isu', $anchorHtml, $match)) {
        return '';
    }

    return trim(html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function resolveUrl(string $href, string $baseUrl): string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($href === '' || str_starts_with($href, '#') || preg_match('~^(?:mailto:|tel:|javascript:)~i', $href)) {
        return '';
    }

    if (preg_match('~^https?://~i', $href)) {
        return $href;
    }

    $base = parse_url($baseUrl);
    $scheme = (string) ($base['scheme'] ?? 'https');
    $host = (string) ($base['host'] ?? 'www.svit.in.ua');

    if (str_starts_with($href, '//')) {
        return $scheme.':'.$href;
    }

    if (str_starts_with($href, '/')) {
        return $scheme.'://'.$host.$href;
    }

    $basePath = (string) ($base['path'] ?? '/');
    $directory = preg_replace('~/[^/]*$~', '/', $basePath) ?? '/';
    $path = normalizeUrlPath($directory.$href);

    return $scheme.'://'.$host.$path;
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

function isImportableLegacyPage(string $url): bool
{
    if (!isSvitUrl($url)) {
        return false;
    }

    $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');

    return preg_match('~\.(?:html?|php)$~i', $path) === 1 && !preg_match('~/(?:new_arh|forum|shop|gos|upr)~i', $path);
}

function stripDeclaredCharset(string $html): string
{
    $html = preg_replace('/<meta\b[^>]*charset\s*=\s*["\']?[^"\'\s>]+[^>]*>/i', '', $html) ?? $html;

    return preg_replace('/<meta\b[^>]*http-equiv\s*=\s*["\']?content-type["\']?[^>]*>/i', '', $html) ?? $html;
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

/** @param array<string, bool> $usedSlugs */
function uniqueSlug(string $title, string $url, array &$usedSlugs): string
{
    $base = Str::slug($title);
    if ($base === '') {
        $base = 'sviatynia-'.substr(sha1($url), 0, 8);
    }

    $slug = $base;
    $suffix = 2;

    while (isset($usedSlugs[$slug])) {
        $slug = $base.'-'.$suffix;
        $suffix++;
    }

    $usedSlugs[$slug] = true;

    return $slug;
}

function relativePath(string $root, string $path): string
{
    return str_replace('\\', '/', ltrim(str_replace($root, '', $path), '/\\'));
}

function textLength(string $html): int
{
    return mb_strlen(cleanText(strip_tags($html)), 'UTF-8');
}

function cleanText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function stripUtf8Bom(string $content): string
{
    return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
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
