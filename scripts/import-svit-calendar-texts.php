<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

/**
 * Completes holiday pages with the short texts from the legacy calendar page.
 * Run after import-svit-holidays.php: long imported articles are kept, missing
 * pages get local HTML built from https://www.svit.in.ua/cal.htm.
 */

const CALENDAR_SOURCE = 'https://www.svit.in.ua/cal.htm';
const USER_AGENT = 'Mozilla/5.0 (compatible; RidnaViraCalendarTextMigration/1.0; +https://ridnavira.com.ua)';

$root = dirname(__DIR__);
$calendar = require $root.'/config/faith_calendar.php';
$shortDescriptions = require $root.'/config/faith_holidays.php';
$outputDir = $root.'/storage/app/content/holidays';
$reportPath = $root.'/storage/app/content/holidays-calendar-texts.json';

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
$html = normalizeEncoding($response['body'], $response['contentType']);
$tokens = calendarTokens($html);
$snippets = extractCalendarSnippets($tokens, $targets);

$report = [
    'generated_at' => date(DATE_ATOM),
    'calendar_source' => CALENDAR_SOURCE,
    'snippets_found' => count($snippets),
    'written' => [],
    'kept_full' => [],
    'missing' => [],
];

foreach ($targets as $target) {
    $slug = $target['slug'];
    $destination = $outputDir.'/'.$slug.'.html';
    $existing = is_file($destination) ? (string) file_get_contents($destination) : '';
    $existingLength = mb_strlen(compactText(strip_tags($existing)));

    if ($existing !== '' && $existingLength >= 250 && !hasMojibake($existing)) {
        $report['kept_full'][] = [
            'slug' => $slug,
            'name' => $target['name'],
            'characters' => $existingLength,
        ];
        continue;
    }

    $text = $snippets[$slug]['text'] ?? ($shortDescriptions[$target['name']] ?? '');
    $text = compactText($text);

    if ($text === '') {
        $report['missing'][] = [
            'slug' => $slug,
            'name' => $target['name'],
            'date' => sprintf('%02d.%02d', $target['day'], $target['month']),
        ];
        continue;
    }

    $content = calendarSnippetHtml($target, $text);
    file_put_contents($destination, $content."\n");

    $report['written'][] = [
        'slug' => $slug,
        'name' => $target['name'],
        'date' => sprintf('%02d.%02d', $target['day'], $target['month']),
        'characters' => mb_strlen($text),
        'source' => $snippets[$slug]['source'] ?? 'config/faith_holidays.php',
    ];

    echo '[OK] '.$target['name'].' -> '.$slug.'.html'.PHP_EOL;
}

file_put_contents(
    $reportPath,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
);

echo PHP_EOL;
echo 'Текстів знайдено в cal.htm: '.count($snippets).PHP_EOL;
echo 'Сторінок доповнено коротким текстом: '.count($report['written']).PHP_EOL;
echo 'Повних матеріалів залишено без змін: '.count($report['kept_full']).PHP_EOL;
echo 'Без тексту: '.count($report['missing']).PHP_EOL;
echo 'Звіт: storage/app/content/holidays-calendar-texts.json'.PHP_EOL;

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

/** @return array{body:string,contentType:string} */
function fetchUrl(string $url): array
{
    $ch = curl_init($url);

    if ($ch === false) {
        throw new RuntimeException('Не вдалося ініціалізувати cURL.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.5',
            'Accept-Language: uk-UA,uk;q=0.9',
        ],
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if (!is_string($body)) {
        throw new RuntimeException("Помилка завантаження {$url}: {$error}");
    }

    if ($body === '' || $status < 200 || $status >= 400) {
        throw new RuntimeException("Сервер повернув HTTP {$status} для {$url}");
    }

    return ['body' => $body, 'contentType' => $contentType];
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

/** @return array<int, string> */
function repairMojibakeCandidates(string $text): array
{
    if (!hasMojibake($text)) {
        return [];
    }

    $candidates = [];

    foreach (['Windows-1251', 'CP1251', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
        $bytes = @iconv('UTF-8', $encoding.'//IGNORE', $text);
        if (is_string($bytes) && $bytes !== '' && mb_check_encoding($bytes, 'UTF-8')) {
            $candidates[] = $bytes;
        }
    }

    return $candidates;
}

/** @param array<int|string, string> $candidates */
function chooseBestEncodingCandidate(array $candidates): string
{
    $best = '';
    $bestScore = PHP_INT_MIN;

    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '' || !mb_check_encoding($candidate, 'UTF-8')) {
            continue;
        }

        $ukrainian = preg_match_all('/[АБВГҐДЕЄЖЗИІЇЙКЛМНОПРСТУФХЦЧШЩЬЮЯабвгґдеєжзиіїйклмнопрстуфхцчшщьюя]/u', $candidate) ?: 0;
        $mojibake = mojibakeScore($candidate);
        $score = ($ukrainian * 8) - ($mojibake * 60) - (substr_count($candidate, '�') * 120);

        if ($score > $bestScore) {
            $best = $candidate;
            $bestScore = $score;
        }
    }

    return stripUtf8Bom($best);
}

function hasMojibake(string $text): bool
{
    return mojibakeScore($text) > 0;
}

function mojibakeScore(string $text): int
{
    $score = 0;
    $markers = ['Р’', 'Р°', 'Рµ', 'Рё', 'Рґ', 'Р¶', 'Р·', 'Р№', 'Рє', 'Р»', 'Рј', 'РЅ', 'Рѕ', 'Рї', 'СЂ', 'СЃ', 'С‚', 'Сѓ', 'С„', 'С…', 'С†', 'С‡', 'С€', 'С‰', 'СЊ', 'СЋ', 'СЏ', 'С–', 'С—', 'С”', 'Р†', 'Р™', 'вЂ', 'Ð', 'Ñ', 'Â', '�'];

    foreach ($markers as $marker) {
        $score += substr_count($text, $marker);
    }

    return $score;
}

function stripUtf8Bom(string $content): string
{
    return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
}

/** @return array<int, array{type:string,text:string}> */
function calendarTokens(string $html): array
{
    $html = preg_replace('/<meta\b[^>]*charset\s*=\s*["\']?[^"\'\s>]+[^>]*>/i', '', $html) ?? $html;
    $html = preg_replace('/<meta\b[^>]*http-equiv\s*=\s*["\']?content-type["\']?[^>]*>/i', '', $html) ?? $html;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body instanceof DOMElement) {
        return [];
    }

    $tokens = [];
    appendTokens($body, $tokens);

    return mergeTextTokens($tokens);
}

/** @param array<int, array{type:string,text:string}> $tokens */
function appendTokens(DOMNode $node, array &$tokens): void
{
    if ($node instanceof DOMText) {
        $text = compactText($node->nodeValue ?? '');
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
        $text = cleanAnchorText((string) $node->textContent);
        if ($text !== '') {
            $tokens[] = ['type' => 'link', 'text' => $text];
        }
        return;
    }

    foreach ($node->childNodes as $child) {
        appendTokens($child, $tokens);
    }
}

/**
 * @param array<int, array{type:string,text:string}> $tokens
 * @return array<int, array{type:string,text:string}>
 */
function mergeTextTokens(array $tokens): array
{
    $merged = [];

    foreach ($tokens as $token) {
        $lastIndex = count($merged) - 1;
        if ($lastIndex >= 0 && $token['type'] === 'text' && $merged[$lastIndex]['type'] === 'text') {
            $merged[$lastIndex]['text'] = compactText($merged[$lastIndex]['text'].' '.$token['text']);
            continue;
        }

        $merged[] = $token;
    }

    return $merged;
}

/**
 * @param array<int, array{type:string,text:string}> $tokens
 * @param array<int, array{month:int,day:int,name:string,slug:string}> $targets
 * @return array<string, array{text:string,source:string}>
 */
function extractCalendarSnippets(array $tokens, array $targets): array
{
    $snippets = [];
    $count = count($tokens);

    for ($index = 0; $index < $count; $index++) {
        $token = $tokens[$index];
        if ($token['type'] !== 'link') {
            continue;
        }

        $target = matchLinkTarget($token['text'], $targets);
        if ($target === null) {
            continue;
        }

        $parts = [];
        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $next = $tokens[$cursor];

            if ($next['type'] === 'link' && matchLinkTarget($next['text'], $targets) !== null) {
                break;
            }

            $parts[] = $next['text'];
        }

        $text = cleanCalendarSnippet(implode(' ', $parts));
        if ($text !== '') {
            $snippets[$target['slug']] = [
                'text' => $text,
                'source' => CALENDAR_SOURCE,
            ];
        }
    }

    return $snippets;
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

        $linkWords = significantWords($link);
        $nameWords = significantWords($name);
        $common = array_intersect($linkWords, $nameWords);
        if ($common !== []) {
            $score += min(30, count($common) * 10);
        }

        if ($score > $bestScore) {
            $bestScore = $score;
            $best = $target;
        }
    }

    return $bestScore >= 76 ? $best : null;
}

/** @return array<int, string> */
function significantWords(string $text): array
{
    return array_values(array_filter(
        explode(' ', $text),
        static fn (string $word): bool => mb_strlen($word, 'UTF-8') >= 4
    ));
}

function cleanAnchorText(string $text): string
{
    $text = compactText($text);
    $text = preg_replace('/^[\-–—\s]+/u', '', $text) ?? $text;
    $text = preg_replace('/\s+$/u', '', $text) ?? $text;

    return compactText($text);
}

function cleanCalendarSnippet(string $text): string
{
    $text = compactText($text);
    $text = preg_replace('/\s*\*\s*\*\s*\*\s*[\s\S]*$/u', '', $text) ?? $text;
    $text = preg_replace('/\bОбчислення дат ведеться[\s\S]*$/u', '', $text) ?? $text;
    $text = preg_replace('/\bПашник\s+С\.Д\.[\s\S]*$/u', '', $text) ?? $text;
    $text = preg_replace('/\s+\d{1,2}\s*$/u', '', $text) ?? $text;

    return compactText(trim($text, " .\t\n\r\0\x0B"));
}

function calendarSnippetHtml(array $target, string $text): string
{
    return '<div class="holiday-imported-article holiday-imported-article--calendar">'."\n"
        .'<h2>'.escapeHtml((string) $target['name']).'</h2>'."\n"
        .'<p>'.escapeHtml($text).'</p>'."\n"
        .'</div>';
}

function normalizeTitle(string $text): string
{
    $text = mb_strtolower(compactText($text), 'UTF-8');
    $text = str_replace(['’', 'ʼ', '`', '*', '—', '–', '.', ',', ':', ';', '(', ')', '"', "'"], ' ', $text);
    $text = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $text) ?? $text;

    return compactText($text);
}

function compactText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function escapeHtml(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}
