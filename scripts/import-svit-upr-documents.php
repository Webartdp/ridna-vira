<?php

declare(strict_types=1);

/**
 * One-time migration of documents referenced by the legacy management page.
 * The public site never links to the legacy domain: HTML pages are sanitized
 * and binary documents are copied into public/assets/documents.
 */

const SOURCE_PAGE = 'https://www.svit.in.ua/upr.htm';
const USER_AGENT = 'RidnaViraDocumentMigration/1.0 (+https://ridnavira.com.ua)';

$projectRoot = dirname(__DIR__);

$targets = [
    [
        'needles' => ['статут духовного центру'],
        'destination' => 'public/assets/documents/upravlinnia/statut-duhovnoho-tsentru.html',
        'title' => 'Статут Духовного центру «Рідна Віра»',
        'mode' => 'html',
    ],
    [
        'needles' => ['внутрішні положення'],
        'destination' => 'public/assets/documents/upravlinnia/vnutrishni-polozhennia.html',
        'title' => 'Внутрішні Положення',
        'mode' => 'html',
    ],
    [
        'needles' => ['заява про вступ релігійної громади'],
        'destination' => 'public/assets/documents/upravlinnia/zaiava-pro-vstup-hromady.docx',
        'title' => 'Заява про вступ релігійної громади',
        'mode' => 'binary',
    ],
    [
        'needles' => ['документи для реєстрації статуту громади'],
        'destination' => 'public/assets/documents/upravlinnia/reiestratsiia-statutu-hromady.html',
        'title' => 'Документи для реєстрації статуту громади',
        'mode' => 'html',
    ],
    [
        'needles' => ['статут академії рідної віри'],
        'destination' => 'public/assets/documents/upravlinnia/statut-akademii-ridnoi-viry.html',
        'title' => 'Статут Академії Рідної Віри',
        'mode' => 'html',
    ],
    [
        'needles' => ['про свободу совісті та релігійні організації'],
        'destination' => 'public/assets/documents/zakonodavstvo/pro-svobodu-sovisti.docx',
        'title' => 'Закон України «Про свободу совісті та релігійні організації»',
        'mode' => 'binary',
    ],
    [
        'needles' => ['про поховання та похоронну справу'],
        'destination' => 'public/assets/documents/zakonodavstvo/pro-pohovannia.docx',
        'title' => 'Закон України «Про поховання та похоронну справу»',
        'mode' => 'binary',
    ],
    [
        'needles' => ['про службу військового капеланства'],
        'destination' => 'public/assets/documents/zakonodavstvo/pro-viiskove-kapelanstvo.docx',
        'title' => 'Закон України «Про Службу військового капеланства»',
        'mode' => 'binary',
    ],
    [
        'needles' => ['виписки із законів україни', 'виписки з законів україни'],
        'destination' => 'public/assets/documents/zakonodavstvo/vypysky-iz-zakoniv.html',
        'title' => 'Виписки із законів України',
        'mode' => 'html',
    ],
    [
        'needles' => ['науково-практичний коментар до кримінального кодексу'],
        'destination' => 'public/assets/documents/zakonodavstvo/komentar-kryminalnoho-kodeksu.rar',
        'title' => 'Науково-практичний коментар до Кримінального кодексу України',
        'mode' => 'binary',
    ],
];

try {
    $sourceResponse = fetchUrl(SOURCE_PAGE);
    $sourceHtml = toUtf8($sourceResponse['body'], $sourceResponse['contentType']);
    $links = extractLinks($sourceHtml, SOURCE_PAGE);

    if ($links === []) {
        throw new RuntimeException('На сторінці джерела не знайдено жодного посилання.');
    }

    $failed = [];

    foreach ($targets as $target) {
        $match = findMatchingLink($links, $target['needles']);

        if ($match === null) {
            $failed[] = $target['title'] . ': посилання не знайдено';
            fwrite(STDERR, "[MISS] {$target['title']}\n");
            continue;
        }

        try {
            $response = fetchUrl($match['url']);
            $destination = $projectRoot . '/' . $target['destination'];
            ensureDirectory(dirname($destination));

            if ($target['mode'] === 'html' || isHtmlResponse($response)) {
                $utf8 = toUtf8($response['body'], $response['contentType']);
                $localHtml = sanitizeHtmlDocument($utf8, $target['title']);
                file_put_contents($destination, $localHtml);
            } else {
                file_put_contents($destination, $response['body']);
            }

            $size = filesize($destination) ?: 0;
            echo "[OK] {$target['title']} -> {$target['destination']} ({$size} bytes)\n";
        } catch (Throwable $exception) {
            $failed[] = $target['title'] . ': ' . $exception->getMessage();
            fwrite(STDERR, "[FAIL] {$target['title']}: {$exception->getMessage()}\n");
        }
    }

    writeMigrationReadme($projectRoot, $targets, $failed);

    if ($failed !== []) {
        fwrite(STDERR, "\nНе перенесено " . count($failed) . " матеріал(ів):\n- " . implode("\n- ", $failed) . "\n");
        exit(2);
    }

    echo "\nУсі документи перенесено до локального сховища.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, '[FATAL] ' . $exception->getMessage() . "\n");
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
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/octet-stream,*/*;q=0.8',
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

function toUtf8(string $content, string $contentType = ''): string
{
    if (mb_check_encoding($content, 'UTF-8')) {
        return $content;
    }

    $encoding = null;

    if (preg_match('/charset\s*=\s*["\']?([^;"\'\s]+)/i', $contentType, $match)) {
        $encoding = $match[1];
    }

    if ($encoding === null && preg_match('/charset\s*=\s*["\']?([^;"\'\s>]+)/i', substr($content, 0, 4096), $match)) {
        $encoding = $match[1];
    }

    $encoding = $encoding ?: (mb_detect_encoding($content, ['Windows-1251', 'KOI8-U', 'ISO-8859-5'], true) ?: 'Windows-1251');
    $converted = @iconv($encoding, 'UTF-8//IGNORE', $content);

    if ($converted === false || $converted === '') {
        throw new RuntimeException("Не вдалося перетворити кодування {$encoding} у UTF-8.");
    }

    return $converted;
}

/** @return array<int, array{text:string, normalized:string, url:string}> */
function extractLinks(string $html, string $baseUrl): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $links = [];

    foreach ($dom->getElementsByTagName('a') as $anchor) {
        $href = trim((string) $anchor->getAttribute('href'));
        $text = trim(preg_replace('/\s+/u', ' ', (string) $anchor->textContent) ?? '');

        if ($href === '' || $text === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            continue;
        }

        $links[] = [
            'text' => $text,
            'normalized' => normalizeText($text),
            'url' => resolveUrl($baseUrl, $href),
        ];
    }

    return $links;
}

/** @param array<int, array{text:string, normalized:string, url:string}> $links */
function findMatchingLink(array $links, array $needles): ?array
{
    foreach ($needles as $needle) {
        $normalizedNeedle = normalizeText($needle);

        foreach ($links as $link) {
            if (str_contains($link['normalized'], $normalizedNeedle)) {
                return $link;
            }
        }
    }

    return null;
}

function normalizeText(string $text): string
{
    $text = str_replace(["\u{00A0}", '’', '`', 'ʼ'], [' ', "'", "'", "'"], $text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s\'\-]+/u', ' ', $text) ?? $text;

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
}

function resolveUrl(string $baseUrl, string $href): string
{
    if (preg_match('~^https?://~i', $href)) {
        return $href;
    }

    $base = parse_url($baseUrl);

    if ($base === false || !isset($base['scheme'], $base['host'])) {
        throw new RuntimeException("Некоректна базова адреса: {$baseUrl}");
    }

    if (str_starts_with($href, '//')) {
        return $base['scheme'] . ':' . $href;
    }

    $origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');

    if (str_starts_with($href, '/')) {
        return $origin . normalizeUrlPath($href);
    }

    $basePath = $base['path'] ?? '/';
    $directory = rtrim(str_replace('\\', '/', dirname($basePath)), '/');

    return $origin . normalizeUrlPath(($directory !== '' ? $directory : '') . '/' . $href);
}

function normalizeUrlPath(string $path): string
{
    $query = '';
    $fragment = '';

    if (($hashPosition = strpos($path, '#')) !== false) {
        $fragment = substr($path, $hashPosition);
        $path = substr($path, 0, $hashPosition);
    }

    if (($queryPosition = strpos($path, '?')) !== false) {
        $query = substr($path, $queryPosition);
        $path = substr($path, 0, $queryPosition);
    }

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

    return '/' . implode('/', $segments) . $query . $fragment;
}

/** @param array{body:string, contentType:string, effectiveUrl:string} $response */
function isHtmlResponse(array $response): bool
{
    if (str_contains(strtolower($response['contentType']), 'text/html')) {
        return true;
    }

    return preg_match('/^\s*(?:<!doctype\s+html|<html|<head|<body)/i', $response['body']) === 1;
}

function sanitizeHtmlDocument(string $html, string $title): string
{
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    foreach (['script', 'style', 'link', 'base', 'iframe', 'object', 'embed', 'form', 'nav', 'noscript', 'img'] as $tag) {
        $nodes = $dom->getElementsByTagName($tag);
        while ($nodes->length > 0) {
            $node = $nodes->item(0);
            $node?->parentNode?->removeChild($node);
        }
    }

    foreach ($xpath->query('//*[@href or @src or @background or @style]') ?: [] as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        foreach (['href', 'src', 'background', 'style'] as $attribute) {
            $node->removeAttribute($attribute);
        }
    }

    foreach ($xpath->query('//*[@onload or @onclick or @onmouseover or @onerror]') ?: [] as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        foreach (['onload', 'onclick', 'onmouseover', 'onerror'] as $attribute) {
            $node->removeAttribute($attribute);
        }
    }

    $body = $dom->getElementsByTagName('body')->item(0);
    $bodyHtml = '';

    if ($body instanceof DOMElement) {
        foreach ($body->childNodes as $child) {
            $bodyHtml .= $dom->saveHTML($child);
        }
    } else {
        $bodyHtml = $dom->saveHTML() ?: '';
    }

    $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    return <<<HTML
<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,follow">
    <title>{$safeTitle} — Рідна Віра</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 34px 18px 70px; color: #1c0803; background: #f5f0e7; font: 16px/1.55 Arial, sans-serif; }
        main { max-width: 980px; margin: 0 auto; padding: 34px clamp(20px, 5vw, 62px); border: 1px solid #dfd2bc; border-radius: 18px; background: #fff; box-shadow: 0 12px 32px rgba(28,8,3,.1); }
        h1 { margin: 0 0 30px; color: #401403; font: 700 clamp(25px, 4vw, 38px)/1.15 Georgia, serif; text-align: center; }
        h2, h3, h4 { color: #401403; font-family: Georgia, serif; }
        p { margin: 0 0 1em; }
        table { width: 100% !important; border-collapse: collapse; }
        td, th { padding: 8px; border: 1px solid #d8ccb7; vertical-align: top; }
        a { color: inherit; text-decoration: none; }
        .source-note { margin-top: 35px; padding-top: 18px; border-top: 1px solid #e3d9c8; color: #746b5c; font-size: 13px; text-align: center; }
        @media print { body { padding: 0; background: #fff; } main { max-width: none; padding: 0; border: 0; box-shadow: none; } }
    </style>
</head>
<body>
<main>
    <h1>{$safeTitle}</h1>
    {$bodyHtml}
    <p class="source-note">Локальна копія документа в інформаційній базі Духовного центру «Рідна Віра».</p>
</main>
</body>
</html>
HTML;
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}

/** @param array<int, array<string, mixed>> $targets */
function writeMigrationReadme(string $projectRoot, array $targets, array $failed): void
{
    $directory = $projectRoot . '/public/assets/documents';
    ensureDirectory($directory);

    $lines = [
        '# Локальна бібліотека документів',
        '',
        'Цей каталог наповнюється автоматичним імпортером `scripts/import-svit-upr-documents.php`.',
        'Публічні сторінки нового сайту використовують лише локальні файли.',
        '',
        '## Очікувані матеріали',
        '',
    ];

    foreach ($targets as $target) {
        $lines[] = '- `' . $target['destination'] . '` — ' . $target['title'];
    }

    if ($failed !== []) {
        $lines[] = '';
        $lines[] = '## Не перенесено під час останнього запуску';
        $lines[] = '';
        foreach ($failed as $message) {
            $lines[] = '- ' . $message;
        }
    }

    file_put_contents($directory . '/README.md', implode("\n", $lines) . "\n");
}
