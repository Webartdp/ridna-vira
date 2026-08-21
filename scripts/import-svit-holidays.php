<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

const SOURCE_ROOT = 'https://www.svit.in.ua/';
const CALENDAR_SOURCE = 'https://www.svit.in.ua/cal.htm';
const USER_AGENT = 'Mozilla/5.0 (compatible; RidnaViraHolidayMigration/2.0; +https://ridnavira.com.ua)';

$root = dirname(__DIR__);
$calendar = require $root.'/config/faith_calendar.php';
$outputDir = $root.'/storage/app/content/holidays';
$reportPath = $root.'/storage/app/content/holidays-import.json';

if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
    throw new RuntimeException('Не вдалося створити каталог '.$outputDir);
}

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

$monthWords = [
    'січня' => 1,
    'лютого' => 2,
    'березня' => 3,
    'квітня' => 4,
    'травня' => 5,
    'червня' => 6,
    'липня' => 7,
    'серпня' => 8,
    'вересня' => 9,
    'жовтня' => 10,
    'листопада' => 11,
    'грудня' => 12,
];

$targets = [];
foreach (($calendar['months'] ?? []) as $monthName => $items) {
    $monthNumber = $monthNumbers[$monthName] ?? null;
    if ($monthNumber === null) {
        continue;
    }

    foreach ($items as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        $day = (int) ($item['day'] ?? 0);
        if ($name === '' || $day < 1) {
            continue;
        }

        $slug = (string) ($item['slug'] ?? Str::slug($name));
        $targets[$monthNumber.'-'.$day] = [
            'month' => $monthNumber,
            'day' => $day,
            'name' => $name,
            'slug' => $slug,
        ];
    }
}

$preferredSources = [
    'kupalo' => SOURCE_ROOT.'bogy/kupalo.htm',
    'perun' => SOURCE_ROOT.'bogy/perun.htm',
    Str::slug('Різдво Коляди*') => SOURCE_ROOT.'pra/12p5.htm',
    Str::slug('Ярило Вишній') => SOURCE_ROOT.'pra/4p5.htm',
    Str::slug('Сорочини. Жайворонки') => SOURCE_ROOT.'pra/3p3.htm',
    Str::slug('Рахманський Великдень. Права Середа') => SOURCE_ROOT.'pra/4p3.htm',
    Str::slug('Обертіння') => SOURCE_ROOT.'pra/2p7.htm',
    Str::slug('Май. Зустріч Предків') => SOURCE_ROOT.'pra/5p1.htm',
    Str::slug('Похорон Ярила') => SOURCE_ROOT.'pra/7p1.htm',
    Str::slug('Денниця') => SOURCE_ROOT.'pra/6p2.htm',
];

$report = [
    'generated_at' => date(DATE_ATOM),
    'calendar_source' => CALENDAR_SOURCE,
    'imported' => [],
    'not_found' => [],
];

$best = [];
$candidateUrls = discoverCalendarUrls();

// Резерв: старі сторінки календаря мають стабільну схему /pra/{місяць}p{номер}.htm.
for ($month = 1; $month <= 12; $month++) {
    for ($page = 1; $page <= 20; $page++) {
        $candidateUrls[] = SOURCE_ROOT."pra/{$month}p{$page}.htm";
    }
}

$candidateUrls = array_values(array_unique($candidateUrls));
echo 'Посилань зі старого календаря для перевірки: '.count($candidateUrls).PHP_EOL;

foreach ($candidateUrls as $url) {
    $response = fetchUrl($url);
    if ($response === null) {
        continue;
    }

    $html = toUtf8($response['body'], $response['contentType']);
    $plain = compactText(strip_tags($html));
    $target = matchTarget($html, $plain, $targets, $monthWords);

    if ($target === null) {
        continue;
    }

    $article = extractArticleHtml($html, $target['name']);
    if ($article === '') {
        continue;
    }

    keepBest($best, $target['slug'], $target, $url, $article);
}

// Відомі великі статті мають пріоритет, якщо вони довші за календарний матеріал.
foreach ($preferredSources as $slug => $url) {
    $target = null;
    foreach ($targets as $candidate) {
        if ($candidate['slug'] === $slug) {
            $target = $candidate;
            break;
        }
    }

    if ($target === null) {
        continue;
    }

    $response = fetchUrl($url);
    if ($response === null) {
        continue;
    }

    $html = toUtf8($response['body'], $response['contentType']);
    $article = extractArticleHtml($html, $target['name']);
    if ($article !== '') {
        keepBest($best, $slug, $target, $url, $article);
    }
}

foreach ($targets as $target) {
    $slug = $target['slug'];
    $destination = $outputDir.'/'.$slug.'.html';

    if (!isset($best[$slug])) {
        // Прибираємо старий зіпсований імпорт, щоб він не перекривав короткий коректний опис.
        if (is_file($destination)) {
            @unlink($destination);
        }

        $report['not_found'][] = [
            'slug' => $slug,
            'name' => $target['name'],
            'date' => sprintf('%02d.%02d', $target['day'], $target['month']),
        ];
        continue;
    }

    file_put_contents($destination, $best[$slug]['html']."\n");

    $report['imported'][] = [
        'slug' => $slug,
        'name' => $target['name'],
        'date' => sprintf('%02d.%02d', $target['day'], $target['month']),
        'source' => $best[$slug]['url'],
        'characters' => mb_strlen(strip_tags($best[$slug]['html'])),
    ];

    echo '[OK] '.$target['name'].' -> '.$best[$slug]['url'].' ('.$best[$slug]['length'].' символів)'.PHP_EOL;
}

file_put_contents(
    $reportPath,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
);

echo PHP_EOL.'Повних матеріалів перенесено: '.count($report['imported']).PHP_EOL;
echo 'Без окремої статті на старому сайті: '.count($report['not_found']).PHP_EOL;
echo 'Звіт: storage/app/content/holidays-import.json'.PHP_EOL;

/** @return array<int, string> */
function discoverCalendarUrls(): array
{
    $response = fetchUrl(CALENDAR_SOURCE);
    if ($response === null) {
        return [];
    }

    $html = toUtf8($response['body'], $response['contentType']);
    $urls = [];

    if (!preg_match_all('~href\s*=\s*["\']([^"\']+)["\']~iu', $html, $matches)) {
        return [];
    }

    foreach ($matches[1] as $href) {
        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($href === '' || str_starts_with($href, '#') || preg_match('~^(?:mailto:|tel:|javascript:)~i', $href)) {
            continue;
        }

        $url = resolveSourceUrl($href);
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');

        if (!in_array($host, ['svit.in.ua', 'www.svit.in.ua'], true)) {
            continue;
        }

        if (!preg_match('~\.(?:html?|php)$~i', $path)) {
            continue;
        }

        // Не тягнемо службові архіви/навігацію, але беремо всі тематичні сторінки, на які посилається cal.htm.
        if (preg_match('~/(?:new_arh|index|forum|shop|gos|upr)~i', $path)) {
            continue;
        }

        $urls[] = $url;
    }

    return array_values(array_unique($urls));
}

function resolveSourceUrl(string $href): string
{
    if (preg_match('~^https?://~i', $href)) {
        return $href;
    }

    if (str_starts_with($href, '//')) {
        return 'https:'.$href;
    }

    return SOURCE_ROOT.ltrim(preg_replace('~^(?:\.\./|\./)+~', '', $href) ?? $href, '/');
}

/** @return array{body:string,contentType:string}|null */
function fetchUrl(string $url): ?array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => 12,
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
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if (!is_string($body) || $body === '' || $status < 200 || $status >= 400) {
        return null;
    }

    return ['body' => $body, 'contentType' => $contentType];
}

function toUtf8(string $content, string $contentType): string
{
    return normalizeEncoding($content, $contentType);
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

    if (preg_match('/charset\s*=\s*["\']?([^;"\'\s>]+)/i', $contentType, $m)) {
        $encodings[] = trim($m[1]);
    }

    $head = substr($content, 0, 16384);
    if (preg_match('/<meta[^>]+charset\s*=\s*["\']?([^"\'\s>]+)/i', $head, $m)) {
        $encodings[] = trim($m[1]);
    }
    if (preg_match('/<meta[^>]+content\s*=\s*["\'][^"\']*charset\s*=\s*([^;"\'\s>]+)/i', $head, $m)) {
        $encodings[] = trim($m[1]);
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
        if ($encoding === '') {
            continue;
        }

        $unique[strtolower($encoding)] = $encoding;
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
        if ($candidate === '' || !mb_check_encoding($candidate, 'UTF-8')) {
            continue;
        }

        $candidates[sha1($candidate)] = $candidate;
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
        $whole = decodeMojibakeThrough($text, $encoding);
        if ($whole !== null && $whole !== $text) {
            $candidates[] = $whole;
        }

        $chunked = repairMojibakeChunks($text, $encoding);
        if ($chunked !== $text) {
            $candidates[] = $chunked;
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

function repairMojibakeChunks(string $text, string $encoding): string
{
    if (mojibakeScore($text) === 0) {
        return $text;
    }

    $parts = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return $text;
    }

    foreach ($parts as $index => $part) {
        if ($part === '' || mojibakeScore($part) === 0) {
            continue;
        }

        $decoded = decodeMojibakeThrough($part, $encoding);
        if ($decoded !== null && isBetterEncodingStats(encodingCandidateStats($decoded), encodingCandidateStats($part))) {
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

        $stats = encodingCandidateStats($candidate);
        if ($best === null || $bestStats === null || isBetterEncodingStats($stats, $bestStats)) {
            $best = $candidate;
            $bestStats = $stats;
        }
    }

    return $best ?? '';
}

/** @return array{score:int,ukrainian:int,mojibake:int,length:int} */
function encodingCandidateStats(string $text): array
{
    $ukrainian = ukrainianCyrillicCount($text);
    $mojibake = mojibakeScore($text);

    return [
        'score' => ($ukrainian * 8) - ($mojibake * 60),
        'ukrainian' => $ukrainian,
        'mojibake' => $mojibake,
        'length' => mb_strlen($text),
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
    return preg_match_all('/[АБВГҐДЕЄЖЗИІЇЙКЛМНОПРСТУФХЦЧШЩЬЮЯабвгґдеєжзиіїйклмнопрстуфхцчшщьюя]/u', $text, $m) ?: 0;
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

/** @return array{day:int,month:int}|null */
function detectHolidayDate(string $text, array $monthWords): ?array
{
    foreach ($monthWords as $word => $month) {
        if (preg_match('/\b([0-3]?\d)\s+'.preg_quote($word, '/').'\b/iu', $text, $m)) {
            $day = (int) $m[1];
            if ($day >= 1 && $day <= 31) {
                return ['day' => $day, 'month' => $month];
            }
        }
    }

    return null;
}

/** @param array<string, array{month:int,day:int,name:string,slug:string}> $targets */
function matchTarget(string $html, string $plain, array $targets, array $monthWords): ?array
{
    $date = detectHolidayDate($plain, $monthWords);
    if ($date !== null) {
        $byDate = $targets[$date['month'].'-'.$date['day']] ?? null;
        if ($byDate !== null) {
            return $byDate;
        }
    }

    $pageTitle = extractPageTitle($html);
    if ($pageTitle === '') {
        return null;
    }

    $normalizedTitle = normalizeText($pageTitle);
    $best = null;
    $bestScore = 0.0;

    foreach ($targets as $target) {
        $name = normalizeText($target['name']);
        similar_text($normalizedTitle, $name, $percent);

        $firstWord = preg_split('/\s+/u', $name)[0] ?? '';
        if ($firstWord !== '' && str_contains($normalizedTitle, $firstWord)) {
            $percent += 25;
        }

        if ($percent > $bestScore) {
            $bestScore = $percent;
            $best = $target;
        }
    }

    return $bestScore >= 72 ? $best : null;
}

function extractPageTitle(string $html): string
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    foreach (['h1', 'h2', 'h3'] as $tag) {
        $node = $dom->getElementsByTagName($tag)->item(0);
        if ($node instanceof DOMElement) {
            $text = compactText((string) $node->textContent);
            if ($text !== '') {
                return $text;
            }
        }
    }

    $title = $dom->getElementsByTagName('title')->item(0);
    return $title instanceof DOMElement ? compactText((string) $title->textContent) : '';
}

function extractArticleHtml(string $html, string $targetName): string
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    foreach (['script', 'style', 'iframe', 'form', 'input', 'button', 'svg', 'object', 'embed', 'video', 'audio', 'noscript'] as $tag) {
        removeElementsByTag($dom, $tag);
    }

    $heading = findBestHeading($dom, $targetName);
    $container = $heading;

    while ($container?->parentNode instanceof DOMElement) {
        $textLength = mb_strlen(compactText((string) $container->textContent));
        if ($textLength >= 900) {
            break;
        }
        $container = $container->parentNode;
    }

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
    $result = preg_replace('~<p>\s*(?:Image|Зображення)\s*</p>~iu', '', $result) ?? $result;
    $result = preg_replace('~(?:<[^>]+>\s*)*Пашник\s+С\.Д\.\s*Руський\s+Православний\s+Календар[\s\S]*$~iu', '', $result) ?? $result;
    $result = preg_replace('~https?://(?:www\.)?svit\.in\.ua/?~iu', '', $result) ?? $result;
    $result = preg_replace('~(?:\s|&nbsp;){2,}~u', ' ', $result) ?? $result;

    $plainLength = mb_strlen(compactText(strip_tags($result)));
    return $plainLength >= 250 ? trim($result) : '';
}

function findBestHeading(DOMDocument $dom, string $targetName): ?DOMElement
{
    $target = normalizeText($targetName);
    $best = null;
    $bestScore = -1.0;

    foreach (['h1', 'h2', 'h3', 'h4', 'b', 'strong'] as $tag) {
        foreach ($dom->getElementsByTagName($tag) as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }

            $text = compactText((string) $node->textContent);
            if ($text === '' || mb_strlen($text) > 180) {
                continue;
            }

            $normalized = normalizeText($text);
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
        return $text === '' ? null : $targetDom->createTextNode($text);
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
        return $fragment;
    }

    $copy = $targetDom->createElement($tag);
    foreach ($node->childNodes as $child) {
        $clean = sanitizeNode($child, $targetDom);
        if ($clean !== null) {
            $copy->appendChild($clean);
        }
    }

    return $copy;
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

/** @param array<string, array{target:array,url:string,html:string,length:int}> $best */
function keepBest(array &$best, string $slug, array $target, string $url, string $html): void
{
    $length = mb_strlen(compactText(strip_tags($html)));
    if ($length < 250) {
        return;
    }

    if (!isset($best[$slug]) || $length > $best[$slug]['length']) {
        $best[$slug] = [
            'target' => $target,
            'url' => $url,
            'html' => $html,
            'length' => $length,
        ];
    }
}

function compactText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
}

function normalizeText(string $text): string
{
    $text = mb_strtolower(compactText($text));
    $text = str_replace(['’', 'ʼ', '`', '*', '—', '–', '.', ',', ':', ';', '(', ')', '"', "'"], ' ', $text);
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}
