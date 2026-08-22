<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Imports full holiday articles by following the exact links from cal.htm.
 * This pass runs after the broad holiday importer and before the short-text
 * fallback, so linked full pages win over one-line calendar descriptions.
 */

const SOURCE_ROOT = 'https://www.svit.in.ua/';
const CALENDAR_SOURCE = 'https://www.svit.in.ua/cal.htm';
const USER_AGENT = 'Mozilla/5.0 (compatible; RidnaViraCalendarArticleMigration/1.0; +https://ridnavira.com.ua)';

$root = dirname(__DIR__);
$calendar = require $root.'/config/faith_calendar.php';
$outputDir = $root.'/storage/app/content/holidays';
$reportPath = $root.'/storage/app/content/holidays-calendar-articles.json';

ensureDirectory($outputDir);
ensureDirectory(dirname($reportPath));

$monthNumbers = [
    'Січень' => 1,
    'Лютий' => 2,
    'Березень' => 3,
    'Квітень' => 4,
    'Травень' => 5,
    'Червень' => 6,
    'Липень' => 7,
    'Серпень' => 8,
    'Вересень' => 9,
    'Жовтень' => 10,
    'Листопад' => 11,
    'Грудень' => 12,
];

$targets = collectTargets($calendar, $monthNumbers);
$response = fetchUrl(CALENDAR_SOURCE);

if ($response === null) {
    throw new RuntimeException('Не вдалося завантажити '.CALENDAR_SOURCE);
}

$calendarHtml = normalizeEncoding($response['body'], $response['contentType']);
$linksBySlug = extractHolidayLinks($calendarHtml, $targets);

$report = [
    'generated_at' => date(DATE_ATOM),
    'calendar_source' => CALENDAR_SOURCE,
    'linked_holidays' => count($linksBySlug),
    'imported' => [],
    'kept_existing' => [],
    'missing_links' => [],
    'skipped' => [],
];

foreach ($targets as $target) {
    $slug = $target['slug'];
    $links = array_values($linksBySlug[$slug] ?? []);

    if ($links === []) {
        $report['missing_links'][] = [
            'slug' => $slug,
            'name' => $target['name'],
            'date' => sprintf('%02d.%02d', $target['day'], $target['month']),
        ];
        continue;
    }

    $best = null;

    foreach ($links as $url) {
        $articleResponse = fetchUrl($url);
        if ($articleResponse === null) {
            $report['skipped'][] = skippedArticle($target, $url, 'fetch-failed');
            continue;
        }

        $articlePage = normalizeEncoding($articleResponse['body'], $articleResponse['contentType']);
        $article = extractArticleHtml($articlePage, $target['name']);
        $length = textLength($article);

        if ($length < 250) {
            $report['skipped'][] = skippedArticle($target, $url, 'too-short', $length);
            continue;
        }

        if ($best === null || $length > $best['length']) {
            $best = [
                'url' => $url,
                'html' => $article,
                'length' => $length,
            ];
        }
    }

    if ($best === null) {
        continue;
    }

    $destination = $outputDir.'/'.$slug.'.html';
    $existing = is_file($destination) ? (string) file_get_contents($destination) : '';
    $existingLength = textLength($existing);
    $existingIsGood = $existing !== ''
        && $existingLength >= $best['length']
        && !hasMojibake($existing)
        && !isCalendarSnippet($existing)
        && !hasDuplicateTitleHeading($existing, $target['name']);

    if ($existingIsGood) {
        $report['kept_existing'][] = [
            'slug' => $slug,
            'name' => $target['name'],
            'characters' => $existingLength,
        ];
        continue;
    }

    file_put_contents($destination, $best['html']."\n");

    $report['imported'][] = [
        'slug' => $slug,
        'name' => $target['name'],
        'date' => sprintf('%02d.%02d', $target['day'], $target['month']),
        'source' => $best['url'],
        'characters' => $best['length'],
    ];

    echo '[OK] '.$target['name'].' -> '.$best['url'].' ('.$best['length'].' символів)'.PHP_EOL;
}

file_put_contents(
    $reportPath,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
);

echo PHP_EOL;
echo 'Свят із посиланнями в cal.htm: '.count($linksBySlug).PHP_EOL;
echo 'Повних статей за посиланнями перенесено: '.count($report['imported']).PHP_EOL;
echo 'Повних наявних матеріалів залишено: '.count($report['kept_existing']).PHP_EOL;
echo 'Без прямого посилання: '.count($report['missing_links']).PHP_EOL;
echo 'Звіт: storage/app/content/holidays-calendar-articles.json'.PHP_EOL;

/**
 * @param array<string, mixed> $calendar
 * @param array<string, int> $monthNumbers
 * @return array<int, array{month:int,day:int,name:string,slug:string}>
 */
function collectTargets(array $calendar, array $monthNumbers): array
{
    $targets = [];

    foreach (($calendar['months'] ?? []) as $monthName => $items) {
        $month = $monthNumbers[$monthName] ?? null;
        if ($month === null) {
            continue;
        }

        foreach ($items as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            $day = (int) ($item['day'] ?? 0);

            if ($name === '' || $day < 1) {
                continue;
            }

            $targets[] = [
                'month' => $month,
                'day' => $day,
                'name' => $name,
                'slug' => (string) ($item['slug'] ?? Str::slug($name)),
            ];
        }
    }

    return $targets;
}

/** @return array{body:string,contentType:string}|null */
function fetchUrl(string $url): ?array
{
    foreach ([true, false] as $verifyTls) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 8,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => USER_AGENT,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => $verifyTls,
            CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
                'Accept-Language: uk-UA,uk;q=0.9',
            ],
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if (is_string($body) && $body !== '' && $status >= 200 && $status < 400) {
            return ['body' => $body, 'contentType' => $contentType];
        }
    }

    return null;
}

/**
 * @param array<int, array{month:int,day:int,name:string,slug:string}> $targets
 * @return array<string, array<string, string>>
 */
function extractHolidayLinks(string $html, array $targets): array
{
    $html = preg_replace('/<meta\b[^>]*charset\s*=\s*["\']?[^"\'\s>]+[^>]*>/i', '', $html) ?? $html;
    $html = preg_replace('/<meta\b[^>]*http-equiv\s*=\s*["\']?content-type["\']?[^>]*>/i', '', $html) ?? $html;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $links = [];

    foreach ($dom->getElementsByTagName('a') as $anchor) {
        if (!$anchor instanceof DOMElement) {
            continue;
        }

        $href = trim((string) $anchor->getAttribute('href'));
        $text = cleanAnchorText((string) $anchor->textContent);

        if ($href === '' || $text === '') {
            continue;
        }

        $target = matchLinkTarget($text, $targets);
        if ($target === null) {
            continue;
        }

        $url = resolveSourceUrl($href);
        if (!isImportableArticleUrl($url)) {
            continue;
        }

        $links[$target['slug']][$url] = $url;
    }

    return $links;
}

function resolveSourceUrl(string $href): string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if (preg_match('~^https?://~i', $href)) {
        return $href;
    }

    if (str_starts_with($href, '//')) {
        return 'https:'.$href;
    }

    return SOURCE_ROOT.ltrim(preg_replace('~^(?:\.\./|\./)+~', '', $href) ?? $href, '/');
}

function isImportableArticleUrl(string $url): bool
{
    $parts = parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');

    if (!in_array($host, ['svit.in.ua', 'www.svit.in.ua'], true)) {
        return false;
    }

    if (!preg_match('~\.(?:html?|php)$~i', $path)) {
        return false;
    }

    return !preg_match('~/(?:new_arh|index|forum|shop|gos|upr)~i', $path);
}

/** @param array<int, array{month:int,day:int,name:string,slug:string}> $targets */
function matchLinkTarget(string $linkText, array $targets): ?array
{
    $link = normalizeTitle($linkText);
    if ($link === '') {
        return null;
    }

    $best = null;
    $bestScore = 0.0;

    foreach ($targets as $target) {
        $name = normalizeTitle($target['name']);
        if ($name === '') {
            continue;
        }

        similar_text($link, $name, $percent);
        $score = $percent;

        if ($link === $name) {
            $score += 60;
        } elseif (str_contains($name, $link) || str_contains($link, $name)) {
            $score += 35;
        }

        $common = array_intersect(significantWords($link), significantWords($name));
        if ($common !== []) {
            $score += min(30, count($common) * 10);
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $target;
        }
    }

    return $bestScore >= 72 ? $best : null;
}

/** @return array<int, string> */
function significantWords(string $text): array
{
    return array_values(array_filter(
        explode(' ', $text),
        static fn (string $word): bool => mb_strlen($word, 'UTF-8') >= 4
    ));
}

function extractArticleHtml(string $html, string $targetName): string
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    foreach (['script', 'style', 'iframe', 'form', 'input', 'button', 'svg', 'object', 'embed', 'video', 'audio', 'img', 'noscript'] as $tag) {
        removeElementsByTag($dom, $tag);
    }

    $container = findArticleContainer($dom, $targetName);
    if (!$container instanceof DOMElement) {
        return '';
    }

    $cleanDom = new DOMDocument('1.0', 'UTF-8');
    $wrapper = $cleanDom->createElement('div');
    $wrapper->setAttribute('class', 'holiday-imported-article');
    $cleanDom->appendChild($wrapper);

    foreach ($container->childNodes as $child) {
        $copy = sanitizeNode($child, $cleanDom);
        if ($copy !== null) {
            $wrapper->appendChild($copy);
        }
    }

    $result = '';
    foreach ($wrapper->childNodes as $child) {
        $result .= $cleanDom->saveHTML($child);
    }

    $result = repairMojibake($result);
    $result = stripDuplicateTitleHeadings($result, $targetName);
    $result = preg_replace('~<p>\s*(?:&nbsp;)?\s*</p>~iu', '', $result) ?? $result;
    $result = preg_replace('~<p>\s*(?:Image|Зображення)\s*</p>~iu', '', $result) ?? $result;
    $result = preg_replace('~(?:<[^>]+>\s*)*Пашник\s+С\.Д\.\s*Руський\s+Православний\s+Календар[\s\S]*$~iu', '', $result) ?? $result;
    $result = preg_replace('~https?://(?:www\.)?svit\.in\.ua/?~iu', '', $result) ?? $result;
    $result = preg_replace('~(?:\s|&nbsp;){2,}~u', ' ', $result) ?? $result;

    return textLength($result) >= 250 ? trim($result) : '';
}

function findArticleContainer(DOMDocument $dom, string $targetName): ?DOMElement
{
    $bestBlockquote = null;
    $bestLength = 0;

    foreach ($dom->getElementsByTagName('blockquote') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $length = mb_strlen(compactText((string) $node->textContent), 'UTF-8');
        if ($length > $bestLength) {
            $bestBlockquote = $node;
            $bestLength = $length;
        }
    }

    if ($bestBlockquote instanceof DOMElement && $bestLength >= 250) {
        return $bestBlockquote;
    }

    $heading = findBestHeading($dom, $targetName);
    $container = $heading;

    while ($container?->parentNode instanceof DOMElement) {
        $textLength = mb_strlen(compactText((string) $container->textContent), 'UTF-8');
        if ($textLength >= 900) {
            break;
        }

        $container = $container->parentNode;
    }

    if ($container instanceof DOMElement) {
        return $container;
    }

    $body = $dom->getElementsByTagName('body')->item(0);

    return $body instanceof DOMElement ? $body : null;
}

function findBestHeading(DOMDocument $dom, string $targetName): ?DOMElement
{
    $target = normalizeTitle($targetName);
    $best = null;
    $bestScore = -1.0;

    foreach (['h1', 'h2', 'h3', 'h4', 'b', 'strong'] as $tag) {
        foreach ($dom->getElementsByTagName($tag) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $text = compactText((string) $node->textContent);
            if ($text === '' || mb_strlen($text, 'UTF-8') > 180) {
                continue;
            }

            $normalized = normalizeTitle($text);
            similar_text($target, $normalized, $percent);
            $score = $percent;

            $firstTargetWord = preg_split('/\s+/u', $target)[0] ?? '';
            if ($firstTargetWord !== '' && str_contains($normalized, $firstTargetWord)) {
                $score += 30;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $node;
            }
        }
    }

    return $best;
}

function sanitizeNode(DOMNode $node, DOMDocument $targetDom): ?DOMNode
{
    if ($node instanceof DOMText) {
        $text = preg_replace('/\s+/u', ' ', $node->nodeValue ?? '') ?? '';

        return trim($text) === '' ? null : $targetDom->createTextNode($text);
    }

    if (!$node instanceof DOMElement) {
        return null;
    }

    $tag = strtolower($node->tagName);
    $drop = ['script', 'style', 'iframe', 'form', 'input', 'button', 'svg', 'object', 'embed', 'video', 'audio', 'img', 'noscript'];
    if (in_array($tag, $drop, true)) {
        return null;
    }

    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr'];

    if ($tag === 'a' || !in_array($tag, $allowed, true)) {
        $fragment = $targetDom->createDocumentFragment();
        foreach ($node->childNodes as $child) {
            $clean = sanitizeNode($child, $targetDom);
            if ($clean !== null) {
                $fragment->appendChild($clean);
            }
        }

        return $fragment->hasChildNodes() ? $fragment : null;
    }

    $copy = $targetDom->createElement($tag);
    foreach ($node->childNodes as $child) {
        $clean = sanitizeNode($child, $targetDom);
        if ($clean !== null) {
            $copy->appendChild($clean);
        }
    }

    if (!$copy->hasChildNodes() && !in_array($tag, ['br', 'hr'], true)) {
        return null;
    }

    return $copy;
}

function stripDuplicateTitleHeadings(string $html, string $targetName): string
{
    return preg_replace_callback(
        '~^\s*<h[1-4]\b[^>]*>(.*?)</h[1-4]>\s*~isu',
        static fn (array $match): string => headingMatchesTarget(compactText(strip_tags($match[1])), $targetName) ? '' : $match[0],
        $html,
        1
    ) ?? $html;
}

function hasDuplicateTitleHeading(string $html, string $targetName): bool
{
    if (!preg_match_all('~<h[1-4]\b[^>]*>(.*?)</h[1-4]>~isu', $html, $matches)) {
        return false;
    }

    foreach (array_slice($matches[1], 0, 3) as $heading) {
        if (headingMatchesTarget(compactText(strip_tags($heading)), $targetName)) {
            return true;
        }
    }

    return false;
}

function headingMatchesTarget(string $heading, string $targetName): bool
{
    $heading = normalizeTitle($heading);
    $target = normalizeTitle($targetName);

    if ($heading === '' || $target === '') {
        return false;
    }

    if ($heading === $target || str_contains($heading, $target)) {
        return true;
    }

    if (mb_strlen($heading, 'UTF-8') >= 5 && str_contains($target, $heading)) {
        return true;
    }

    similar_text($heading, $target, $percent);

    return $percent >= 88;
}

function isCalendarSnippet(string $html): bool
{
    return str_contains($html, 'holiday-imported-article--calendar');
}

/** @return array{slug:string,name:string,date:string,source:string,reason:string,characters:int} */
function skippedArticle(array $target, string $url, string $reason, int $characters = 0): array
{
    return [
        'slug' => $target['slug'],
        'name' => $target['name'],
        'date' => sprintf('%02d.%02d', $target['day'], $target['month']),
        'source' => $url,
        'reason' => $reason,
        'characters' => $characters,
    ];
}

function normalizeEncoding(string $content, string $contentType = ''): string
{
    $candidates = [];

    addEncodingCandidate($candidates, $content);

    foreach (detectEncodingCandidates($content, $contentType) as $encoding) {
        if (in_array(strtolower($encoding), ['utf-8', 'utf8'], true)) {
            continue;
        }

        $converted = @iconv($encoding, 'UTF-8//IGNORE', $content);
        if (is_string($converted) && $converted !== '') {
            addEncodingCandidate($candidates, $converted);
        }
    }

    if ($candidates === []) {
        $fallback = @iconv('UTF-8', 'UTF-8//IGNORE', $content);

        return is_string($fallback) && $fallback !== '' ? $fallback : $content;
    }

    return chooseBestEncodingCandidate($candidates);
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

    $encodings = array_merge($encodings, [
        'UTF-8',
        'Windows-1251',
        'CP1251',
        'KOI8-U',
        'ISO-8859-1',
        'Windows-1252',
    ]);

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

    foreach (array_merge([$text], repairMojibakeCandidates($text)) as $candidate) {
        if ($candidate !== '' && mb_check_encoding($candidate, 'UTF-8')) {
            $candidates[sha1($candidate)] = $candidate;
        }
    }
}

function repairMojibake(string $text): string
{
    if ($text === '' || !mb_check_encoding($text, 'UTF-8')) {
        return $text;
    }

    return chooseBestEncodingCandidate(array_merge([$text], repairMojibakeCandidates($text)));
}

/** @return array<int, string> */
function repairMojibakeCandidates(string $text): array
{
    $candidates = [];

    foreach (['Windows-1251', 'CP1251', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
        $decoded = decodeMojibakeThrough($text, $encoding);
        if ($decoded !== null && $decoded !== $text) {
            $candidates[] = $decoded;
        }
    }

    return $candidates;
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

        $stats = encodingCandidateStats($candidate);
        if ($best === null || $bestStats === null || isBetterEncodingStats($stats, $bestStats)) {
            $best = $candidate;
            $bestStats = $stats;
        }
    }

    return stripUtf8Bom($best ?? '');
}

/** @return array{score:int,ukrainian:int,mojibake:int,length:int} */
function encodingCandidateStats(string $text): array
{
    $ukrainian = ukrainianCyrillicCount($text);
    $mojibake = mojibakeScore($text);

    return [
        'score' => ($ukrainian * 8) - ($mojibake * 60) - (substr_count($text, '�') * 120),
        'ukrainian' => $ukrainian,
        'mojibake' => $mojibake,
        'length' => mb_strlen($text, 'UTF-8'),
    ];
}

/** @param array{score:int,ukrainian:int,mojibake:int,length:int} $candidate */
/** @param array{score:int,ukrainian:int,mojibake:int,length:int} $current */
function isBetterEncodingStats(array $candidate, array $current): bool
{
    if ($candidate['score'] !== $current['score']) {
        return $candidate['score'] > $current['score'];
    }

    if ($candidate['mojibake'] !== $current['mojibake']) {
        return $candidate['mojibake'] < $current['mojibake'];
    }

    if ($candidate['ukrainian'] !== $current['ukrainian']) {
        return $candidate['ukrainian'] > $current['ukrainian'];
    }

    return $candidate['length'] > $current['length'];
}

function ukrainianCyrillicCount(string $text): int
{
    return preg_match_all('/[АБВГҐДЕЄЖЗИІЇЙКЛМНОПРСТУФХЦЧШЩЬЮЯабвгґдеєжзиіїйклмнопрстуфхцчшщьюя]/u', $text) ?: 0;
}

function hasMojibake(string $text): bool
{
    return mojibakeScore($text) > 0;
}

function mojibakeScore(string $text): int
{
    $score = 0;
    $cp1251Marks = preg_quote('ЂЃ‚ѓ„…†‡€‰Љ‹ЊЌЋЏђ‘’“”•–—™љ›њќћџЎўЈ¤¦§Ё©«¬®°±µ¶·ё№»јЅѕ', '/');
    $weightedPatterns = [
        '/(?:вЂ.|в„–|в€¦|в‚¬)/u' => 6,
        '/(?:Ð.|Ñ.|Â.|â€.|â„–|â€¦|â€™|â€œ|â€\x{009d}|â€“)/u' => 5,
        '/(?:Р|С)['.$cp1251Marks.']/u' => 4,
        '/[\x{0080}-\x{009F}]/u' => 4,
        '/�/u' => 20,
    ];

    foreach ($weightedPatterns as $pattern => $weight) {
        $matches = preg_match_all($pattern, $text);
        if ($matches !== false && $matches > 0) {
            $score += $matches * $weight;
        }
    }

    return $score;
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

function cleanAnchorText(string $text): string
{
    $text = compactText($text);
    $text = preg_replace('/^[\-–—\s]+/u', '', $text) ?? $text;
    $text = preg_replace('/\s+$/u', '', $text) ?? $text;

    return compactText($text);
}

function normalizeTitle(string $text): string
{
    $text = mb_strtolower(compactText($text), 'UTF-8');
    $text = str_replace(['’', 'ʼ', '`', '*', '—', '–', '.', ',', ':', ';', '(', ')', '"', "'"], ' ', $text);
    $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text) ?? $text;

    return compactText($text);
}

function textLength(string $html): int
{
    return mb_strlen(compactText(strip_tags($html)), 'UTF-8');
}

function compactText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function stripUtf8Bom(string $content): string
{
    return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}
