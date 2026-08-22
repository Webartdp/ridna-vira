<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Final holiday import pass.
 * Reads cal.htm in order, tracks month/day text around every holiday link, and
 * writes full linked articles to our calendar pages. Name matching is only a
 * fallback because the legacy calendar often uses shorter link labels.
 */

const SOURCE_ROOT = 'https://www.svit.in.ua/';
const CALENDAR_SOURCE = 'https://www.svit.in.ua/cal.htm';
const USER_AGENT = 'Mozilla/5.0 (compatible; RidnaViraCalendarFullTextMigration/2.0; +https://ridnavira.com.ua)';

$root = dirname(__DIR__);
$calendar = require $root.'/config/faith_calendar.php';
$outputDir = $root.'/storage/app/content/holidays';
$reportPath = $root.'/storage/app/content/holidays-calendar-fulltext.json';

ensureDirectory($outputDir);
ensureDirectory(dirname($reportPath));

$targets = collectTargets($calendar);
$response = fetchUrl(CALENDAR_SOURCE);

if ($response === null) {
    throw new RuntimeException('Не вдалося завантажити '.CALENDAR_SOURCE);
}

$calendarHtml = normalizeEncoding($response['body'], $response['contentType']);
$linksBySlug = extractHolidayLinks($calendarHtml, $targets);
$pageCache = [];

$report = [
    'generated_at' => date(DATE_ATOM),
    'calendar_source' => CALENDAR_SOURCE,
    'linked_holidays' => count($linksBySlug),
    'imported' => [],
    'skipped' => [],
    'without_article_link' => [],
];

foreach ($targets as $target) {
    $slug = $target['slug'];
    $links = array_values($linksBySlug[$slug] ?? []);

    if ($links === []) {
        $report['without_article_link'][] = issue($target, '', 'without-article-link');
        continue;
    }

    $best = null;

    foreach ($links as $url) {
        if (!array_key_exists($url, $pageCache)) {
            $pageCache[$url] = fetchUrl($url);
        }

        $articleResponse = $pageCache[$url];
        if ($articleResponse === null) {
            $report['skipped'][] = issue($target, $url, 'fetch-failed');
            continue;
        }

        $articlePage = normalizeEncoding($articleResponse['body'], $articleResponse['contentType']);
        $article = extractArticleHtml($articlePage, $target['name']);
        $length = textLength($article);

        if ($length < 250) {
            $report['skipped'][] = issue($target, $url, 'too-short', $length);
            continue;
        }

        if ($best === null || $length > $best['characters']) {
            $best = [
                'url' => $url,
                'html' => $article,
                'characters' => $length,
            ];
        }
    }

    if ($best === null) {
        continue;
    }

    file_put_contents($outputDir.'/'.$slug.'.html', $best['html']."\n");

    $report['imported'][] = [
        'slug' => $slug,
        'name' => $target['name'],
        'date' => sprintf('%02d.%02d', $target['day'], $target['month']),
        'source' => $best['url'],
        'characters' => $best['characters'],
    ];

    echo '[FULL] '.$target['name'].' -> '.$best['url'].' ('.$best['characters'].' символів)'.PHP_EOL;
}

file_put_contents(
    $reportPath,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
);

echo PHP_EOL;
echo 'Свят із посиланням на статтю: '.count($linksBySlug).PHP_EOL;
echo 'Повних текстів перенесено з посилань cal.htm: '.count($report['imported']).PHP_EOL;
echo 'Без окремого посилання: '.count($report['without_article_link']).PHP_EOL;
echo 'Пропущено: '.count($report['skipped']).PHP_EOL;
echo 'Звіт: storage/app/content/holidays-calendar-fulltext.json'.PHP_EOL;

/** @return array<int, array{month:int,day:int,name:string,slug:string}> */
function collectTargets(array $calendar): array
{
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
    $tokens = calendarTokens(stripDeclaredCharset($html));
    $targetsByDate = targetsByDate($targets);
    $monthNumbers = [
        'січень' => 1,
        'лютий' => 2,
        'березень' => 3,
        'квітень' => 4,
        'травень' => 5,
        'червень' => 6,
        'липень' => 7,
        'серпень' => 8,
        'вересень' => 9,
        'жовтень' => 10,
        'листопад' => 11,
        'грудень' => 12,
    ];

    $links = [];
    $currentMonth = null;
    $currentDay = null;
    $currentDateKey = null;
    $dayLinkCount = 0;

    foreach ($tokens as $token) {
        if ($token['type'] === 'text') {
            $text = $token['text'];
            $lower = mb_strtolower($text, 'UTF-8');

            if (calendarFooterReached($lower)) {
                break;
            }

            foreach ($monthNumbers as $monthName => $monthNumber) {
                if (preg_match('/\b'.preg_quote($monthName, '/').'\b/u', $lower)) {
                    $currentMonth = $monthNumber;
                    $currentDay = null;
                    $currentDateKey = null;
                    $dayLinkCount = 0;
                    break;
                }
            }

            $day = lastCalendarDayInText($text);
            if ($currentMonth !== null && $day !== null) {
                $newDateKey = $currentMonth.'-'.$day;
                if ($newDateKey !== $currentDateKey) {
                    $dayLinkCount = 0;
                }

                $currentDay = $day;
                $currentDateKey = $newDateKey;
            }

            continue;
        }

        if ($token['type'] !== 'link') {
            continue;
        }

        $url = resolveSourceUrl($token['href'] ?? '');
        if (!isImportableHtmlUrl($url)) {
            continue;
        }

        $target = null;
        $dateTargets = $currentDateKey !== null ? ($targetsByDate[$currentDateKey] ?? []) : [];

        if ($dateTargets !== []) {
            $matchedByName = matchHolidayTarget($token['text'], $dateTargets, 45.0);
            if ($dayLinkCount === 0) {
                $target = $matchedByName ?? $dateTargets[0];
            } else {
                $target = $matchedByName;
            }
        }

        $target ??= matchHolidayTarget($token['text'], $targets, 72.0);

        if ($target === null) {
            continue;
        }

        $links[$target['slug']][$url] = $url;

        if ($currentMonth !== null && $currentDay !== null) {
            $dayLinkCount++;
        }
    }

    return $links;
}

/** @return array<int, array{type:string,text:string,href?:string}> */
function calendarTokens(string $html): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) {
        return [];
    }

    $tokens = [];
    appendCalendarTokens($body, $tokens);

    return mergeAdjacentTextTokens($tokens);
}

/** @param array<int, array{type:string,text:string,href?:string}> $tokens */
function appendCalendarTokens(DOMNode $node, array &$tokens): void
{
    if ($node instanceof DOMText) {
        $text = cleanText($node->nodeValue ?? '');
        if ($text !== '') {
            $tokens[] = ['type' => 'text', 'text' => $text];
        }

        return;
    }

    if (!$node instanceof DOMElement) {
        return;
    }

    $tag = strtolower($node->tagName);
    if (in_array($tag, ['script', 'style', 'iframe', 'form', 'select', 'button', 'input', 'noscript'], true)) {
        return;
    }

    if ($tag === 'a') {
        $text = cleanText((string) $node->textContent);
        $href = trim((string) $node->getAttribute('href'));
        if ($text !== '' && $href !== '') {
            $tokens[] = ['type' => 'link', 'text' => $text, 'href' => $href];
        }

        return;
    }

    foreach ($node->childNodes as $child) {
        appendCalendarTokens($child, $tokens);
    }
}

/**
 * @param array<int, array{type:string,text:string,href?:string}> $tokens
 * @return array<int, array{type:string,text:string,href?:string}>
 */
function mergeAdjacentTextTokens(array $tokens): array
{
    $merged = [];

    foreach ($tokens as $token) {
        $lastIndex = count($merged) - 1;
        if ($lastIndex >= 0 && $token['type'] === 'text' && $merged[$lastIndex]['type'] === 'text') {
            $merged[$lastIndex]['text'] = cleanText($merged[$lastIndex]['text'].' '.$token['text']);
            continue;
        }

        $merged[] = $token;
    }

    return $merged;
}

/**
 * @param array<int, array{month:int,day:int,name:string,slug:string}> $targets
 * @return array<string, array<int, array{month:int,day:int,name:string,slug:string}>>
 */
function targetsByDate(array $targets): array
{
    $indexed = [];

    foreach ($targets as $target) {
        $indexed[$target['month'].'-'.$target['day']][] = $target;
    }

    return $indexed;
}

function lastCalendarDayInText(string $text): ?int
{
    if (!preg_match_all('/(?<![\p{L}\p{N}])([0-3]?\d)(?![\p{L}\p{N}])/u', $text, $matches)) {
        return null;
    }

    for ($index = count($matches[1]) - 1; $index >= 0; $index--) {
        $day = (int) $matches[1][$index];
        if ($day >= 1 && $day <= 31) {
            return $day;
        }
    }

    return null;
}

function calendarFooterReached(string $lowerText): bool
{
    return str_contains($lowerText, 'обчислення дат ведеться')
        || str_contains($lowerText, 'пашник с.д.')
        || str_contains($lowerText, 'дивіться книжки та статті')
        || str_contains($lowerText, 'календар пам');
}

function extractArticleHtml(string $html, string $holidayName): string
{
    $html = stripDeclaredCharset($html);

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    foreach (['script', 'style', 'iframe', 'form', 'input', 'button', 'svg', 'object', 'embed', 'video', 'audio', 'img', 'noscript'] as $tag) {
        removeElementsByTag($dom, $tag);
    }

    $container = largestArticleContainer($dom);
    if (!$container instanceof DOMElement) {
        $container = $dom->getElementsByTagName('body')->item(0);
    }
    if (!$container instanceof DOMElement) {
        return '';
    }

    $cleanDom = new DOMDocument('1.0', 'UTF-8');
    $wrapper = $cleanDom->createElement('div');
    $wrapper->setAttribute('class', 'holiday-imported-article');
    $cleanDom->appendChild($wrapper);

    foreach ($container->childNodes as $child) {
        $clean = sanitizeNode($child, $cleanDom);
        if ($clean !== null) {
            $wrapper->appendChild($clean);
        }
    }

    $html = '';
    foreach ($wrapper->childNodes as $child) {
        $html .= $cleanDom->saveHTML($child);
    }

    $html = repairMojibake($html);
    $html = removeDuplicateHolidayHeadings($html, $holidayName);
    $html = preg_replace('~<p>\s*(?:&nbsp;)?\s*</p>~iu', '', $html) ?? $html;
    $html = preg_replace('~(?:\s|&nbsp;){2,}~u', ' ', $html) ?? $html;

    return textLength($html) >= 250 ? trim($html) : '';
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

    if ($bestBlockquote instanceof DOMElement && $bestBlockquoteLength >= 250) {
        return $bestBlockquote;
    }

    $best = null;
    $bestLength = 0;

    foreach (['article', 'main', 'td', 'div'] as $tag) {
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

    return $bestLength >= 250 ? $best : null;
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

    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'hr'];

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

/**
 * @param array<int, array{month:int,day:int,name:string,slug:string}> $targets
 * @return array{month:int,day:int,name:string,slug:string}|null
 */
function matchHolidayTarget(string $linkText, array $targets, float $threshold): ?array
{
    $link = normalizeTitle($linkText);
    if ($link === '') {
        return null;
    }

    $best = null;
    $bestScore = 0.0;

    foreach ($targets as $target) {
        $name = normalizeTitle($target['name']);
        similar_text($link, $name, $percent);
        $score = $percent;

        if ($link === $name) {
            $score += 60;
        } elseif (str_contains($name, $link) || str_contains($link, $name)) {
            $score += 35;
        }

        $commonWords = array_intersect(significantWords($link), significantWords($name));
        if ($commonWords !== []) {
            $score += min(30, count($commonWords) * 10);
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $target;
        }
    }

    return $bestScore >= $threshold ? $best : null;
}

function removeDuplicateHolidayHeadings(string $html, string $holidayName): string
{
    return preg_replace_callback(
        '~^\s*<h[1-6]\b[^>]*>(.*?)</h[1-6]>\s*~isu',
        static fn (array $match): string => headingMatchesHoliday(cleanText(strip_tags($match[1])), $holidayName) ? '' : $match[0],
        $html,
        1
    ) ?? $html;
}

function headingMatchesHoliday(string $heading, string $holidayName): bool
{
    $heading = normalizeTitle($heading);
    $holiday = normalizeTitle($holidayName);

    if ($heading === '' || $holiday === '') {
        return false;
    }

    if ($heading === $holiday || str_contains($heading, $holiday)) {
        return true;
    }

    if (mb_strlen($heading, 'UTF-8') >= 5 && str_contains($holiday, $heading)) {
        return true;
    }

    similar_text($heading, $holiday, $percent);

    return $percent >= 88;
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

function isImportableHtmlUrl(string $url): bool
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

        $stats = encodingStats($candidate);
        if ($best === null || $bestStats === null || encodingIsBetter($stats, $bestStats)) {
            $best = $candidate;
            $bestStats = $stats;
        }
    }

    return stripUtf8Bom($best ?? '');
}

/** @return array{score:int,ukrainian:int,mojibake:int,length:int} */
function encodingStats(string $text): array
{
    $ukrainian = preg_match_all('/[АБВГҐДЕЄЖЗИІЇЙКЛМНОПРСТУФХЦЧШЩЬЮЯабвгґдеєжзиіїйклмнопрстуфхцчшщьюя]/u', $text) ?: 0;
    $mojibake = mojibakeScore($text);

    return [
        'score' => ($ukrainian * 8) - ($mojibake * 70) - (substr_count($text, '�') * 120),
        'ukrainian' => $ukrainian,
        'mojibake' => $mojibake,
        'length' => mb_strlen($text, 'UTF-8'),
    ];
}

/** @param array{score:int,ukrainian:int,mojibake:int,length:int} $candidate */
/** @param array{score:int,ukrainian:int,mojibake:int,length:int} $current */
function encodingIsBetter(array $candidate, array $current): bool
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

function mojibakeScore(string $text): int
{
    $score = 0;
    $cp1251Marks = preg_quote('ЂЃ‚ѓ„…†‡€‰Љ‹ЊЌЋЏђ‘’“”•–—™љ›њќћџЎўЈ¤¦§Ё©«¬®°±µ¶·ё№»јЅѕ', '/');
    $patterns = [
        '/(?:вЂ.|в„–|в€¦|в‚¬)/u' => 6,
        '/(?:Ð.|Ñ.|Â.|â€.|â„–|â€¦|â€™|â€œ|â€\x{009d}|â€“)/u' => 5,
        '/(?:Р|С)['.$cp1251Marks.']/u' => 4,
        '/[\x{0080}-\x{009F}]/u' => 4,
        '/�/u' => 20,
    ];

    foreach ($patterns as $pattern => $weight) {
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

/** @return array<int, string> */
function significantWords(string $text): array
{
    return array_values(array_filter(
        explode(' ', $text),
        static fn (string $word): bool => mb_strlen($word, 'UTF-8') >= 4
    ));
}

/** @return array{slug:string,name:string,date:string,source:string,reason:string,characters:int} */
function issue(array $target, string $url, string $reason, int $characters = 0): array
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

function normalizeTitle(string $text): string
{
    $text = mb_strtolower(cleanText($text), 'UTF-8');
    $text = str_replace(['’', 'ʼ', '`', '*', '—', '–', '.', ',', ':', ';', '(', ')', '"', "'"], ' ', $text);
    $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text) ?? $text;

    return cleanText($text);
}

function stripDeclaredCharset(string $html): string
{
    $html = preg_replace('/<meta\b[^>]*charset\s*=\s*["\']?[^"\'\s>]+[^>]*>/i', '', $html) ?? $html;

    return preg_replace('/<meta\b[^>]*http-equiv\s*=\s*["\']?content-type["\']?[^>]*>/i', '', $html) ?? $html;
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

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}
