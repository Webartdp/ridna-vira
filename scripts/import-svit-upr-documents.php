<?php

declare(strict_types=1);

/**
 * One-time migration of management documents from the legacy portal.
 * The public site never links to the legacy domain. All matched files are
 * copied into public/assets/documents and HTML pages are sanitized.
 */

const SOURCE_PAGE = 'https://www.svit.in.ua/upr.htm';
const USER_AGENT = 'Mozilla/5.0 (compatible; RidnaViraDocumentMigration/2.0; +https://ridnavira.com.ua)';

$projectRoot = dirname(__DIR__);

$targets = [
    [
        'key' => 'statut-duhovnoho-tsentru',
        'needles' => ['статут духовного центру'],
        'directory' => 'public/assets/documents/upravlinnia',
        'title' => 'Статут Духовного центру «Рідна Віра»',
    ],
    [
        'key' => 'vnutrishni-polozhennia',
        'needles' => ['внутрішні положення'],
        'directory' => 'public/assets/documents/upravlinnia',
        'title' => 'Внутрішні Положення',
    ],
    [
        'key' => 'zaiava-pro-vstup-hromady',
        'needles' => ['заява про вступ релігійної громади', 'заява про вступ громади'],
        'directory' => 'public/assets/documents/upravlinnia',
        'title' => 'Заява про вступ релігійної громади',
    ],
    [
        'key' => 'reiestratsiia-statutu-hromady',
        'needles' => ['документи для реєстрації статуту громади', 'реєстрації статуту громади'],
        'directory' => 'public/assets/documents/upravlinnia',
        'title' => 'Документи для реєстрації статуту громади',
    ],
    [
        'key' => 'statut-akademii-ridnoi-viry',
        'needles' => ['статут академії рідної віри', 'статут академії'],
        'directory' => 'public/assets/documents/upravlinnia',
        'title' => 'Статут Академії Рідної Віри',
    ],
    [
        'key' => 'pro-svobodu-sovisti',
        'needles' => ['про свободу совісті та релігійні організації', 'свободу совісті'],
        'directory' => 'public/assets/documents/zakonodavstvo',
        'title' => 'Закон України «Про свободу совісті та релігійні організації»',
    ],
    [
        'key' => 'pro-pohovannia',
        'needles' => ['про поховання та похоронну справу', 'про поховання'],
        'directory' => 'public/assets/documents/zakonodavstvo',
        'title' => 'Закон України «Про поховання та похоронну справу»',
    ],
    [
        'key' => 'pro-viiskove-kapelanstvo',
        'needles' => ['про службу військового капеланства', 'військового капеланства'],
        'directory' => 'public/assets/documents/zakonodavstvo',
        'title' => 'Закон України «Про Службу військового капеланства»',
    ],
    [
        'key' => 'vypysky-iz-zakoniv',
        'needles' => ['виписки із законів україни', 'виписки з законів україни', 'виписки із законів'],
        'directory' => 'public/assets/documents/zakonodavstvo',
        'title' => 'Виписки із законів України',
    ],
    [
        'key' => 'komentar-kryminalnoho-kodeksu',
        'needles' => ['науково-практичний коментар до кримінального кодексу', 'коментар до кримінального кодексу'],
        'directory' => 'public/assets/documents/zakonodavstvo',
        'title' => 'Науково-практичний коментар до Кримінального кодексу України',
    ],
];

try {
    $sourceResponse = fetchUrl(SOURCE_PAGE);
    $sourceHtml = toUtf8($sourceResponse['body'], $sourceResponse['contentType']);
    $links = extractLinks($sourceHtml, SOURCE_PAGE);

    if ($links === []) {
        throw new RuntimeException('На сторінці джерела не знайдено жодного посилання.');
    }

    echo 'Знайдено посилань на сторінці: ' . count($links) . "\n";

    $failed = [];
    $manifest = [];
    $usedUrls = [];

    foreach ($targets as $target) {
        $match = findMatchingLink($links, $target['needles'], $usedUrls);

        if ($match === null) {
            $failed[] = $target['title'] . ': посилання не знайдено';
            fwrite(STDERR, "[MISS] {$target['title']}\n");
            continue;
        }

        try {
            $response = fetchUrl($match['url']);
            $isHtml = isHtmlResponse($response);
            $extension = detectExtension($response, $match['url'], $isHtml);
            $directory = $projectRoot . '/' . $target['directory'];
            ensureDirectory($directory);
            removeOldTargetFiles($directory, $target['key']);

            $destination = $directory . '/' . $target['key'] . '.' . $extension;

            if ($isHtml) {
                $utf8 = toUtf8($response['body'], $response['contentType']);
                file_put_contents($destination, sanitizeHtmlDocument($utf8, $target['title']));
            } else {
                file_put_contents($destination, $response['body']);
            }

            $usedUrls[] = $match['url'];
            $relativePath = ltrim(str_replace($projectRoot, '', $destination), '/');
            $size = filesize($destination) ?: 0;

            $manifest[$target['key']] = [
                'title' => $target['title'],
                'path' => $relativePath,
                'extension' => $extension,
                'size' => $size,
                'source_label' => $match['text'],
            ];

            echo "[OK] {$target['title']} -> {$relativePath} ({$size} bytes)\n";
        } catch (Throwable $exception) {
            $failed[] = $target['title'] . ': ' . $exception->getMessage();
            fwrite(STDERR, "[FAIL] {$target['title']}: {$exception->getMessage()}\n");
        }
    }

    $documentsRoot = $projectRoot . '/public/assets/documents';
    ensureDirectory($documentsRoot);
    file_put_contents(
        $documentsRoot . '/manifest.json',
        json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    writeMigrationReadme($documentsRoot, $manifest, $failed);

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
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => USER_AGENT,
        CURLOPT_ENCODING => '',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_REFERER => SOURCE_PAGE,
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/octet-stream,*/*;q=0.8',
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
    $declaredEncoding = detectDeclaredEncoding($content, $contentType);

    if ($declaredEncoding !== null && !in_array(strtolower($declaredEncoding), ['utf-8', 'utf8'], true)) {
        $converted = @iconv($declaredEncoding, 'UTF-8//IGNORE', $content);

        if (is_string($converted) && $converted !== '') {
            return stripUtf8Bom($converted);
        }
    }

    if (mb_check_encoding($content, 'UTF-8')) {
        return stripUtf8Bom($content);
    }

    $detected = mb_detect_encoding($content, ['Windows-1251', 'KOI8-U', 'ISO-8859-5'], true) ?: 'Windows-1251';
    $converted = @iconv($detected, 'UTF-8//IGNORE', $content);

    if (!is_string($converted) || $converted === '') {
        throw new RuntimeException("Не вдалося перетворити кодування {$detected} у UTF-8.");
    }

    return stripUtf8Bom($converted);
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

function stripUtf8Bom(string $content): string
{
    return str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
}

/** @return array<int, array{text:string, context:string, normalized:string, contextNormalized:string, hrefNormalized:string, url:string}> */
function extractLinks(string $html, string $baseUrl): array
{
    $html = normalizeHtmlCharset($html);
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    $links = [];

    foreach ($dom->getElementsByTagName('a') as $anchor) {
        $href = trim((string) $anchor->getAttribute('href'));

        if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
            continue;
        }

        $textParts = [
            (string) $anchor->textContent,
            (string) $anchor->getAttribute('title'),
            (string) $anchor->getAttribute('download'),
        ];
        $text = compactText(implode(' ', $textParts));
        $context = compactText((string) ($anchor->parentNode?->textContent ?? ''));
        $absoluteUrl = resolveUrl($baseUrl, html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        $links[] = [
            'text' => $text,
            'context' => $context,
            'normalized' => normalizeText($text),
            'contextNormalized' => normalizeText($context),
            'hrefNormalized' => normalizeText(rawurldecode($href)),
            'url' => $absoluteUrl,
        ];
    }

    return $links;
}

function normalizeHtmlCharset(string $html): string
{
    $html = preg_replace('/<meta\b[^>]*charset\s*=\s*["\']?[^"\'\s>]+[^>]*>/i', '', $html) ?? $html;
    $html = preg_replace('/<meta\b[^>]*http-equiv\s*=\s*["\']?content-type["\']?[^>]*>/i', '', $html) ?? $html;

    return $html;
}

/**
 * @param array<int, array{text:string, context:string, normalized:string, contextNormalized:string, hrefNormalized:string, url:string}> $links
 * @param array<int, string> $usedUrls
 * @return array{text:string, context:string, normalized:string, contextNormalized:string, hrefNormalized:string, url:string}|null
 */
function findMatchingLink(array $links, array $needles, array $usedUrls): ?array
{
    $best = null;
    $bestScore = 0;

    foreach ($links as $link) {
        if (in_array($link['url'], $usedUrls, true)) {
            continue;
        }

        foreach ($needles as $needle) {
            $normalizedNeedle = normalizeText($needle);
            $score = 0;

            if ($normalizedNeedle !== '' && str_contains($link['normalized'], $normalizedNeedle)) {
                $score = 1000 + mb_strlen($normalizedNeedle, 'UTF-8');
            } elseif ($normalizedNeedle !== '' && str_contains($link['contextNormalized'], $normalizedNeedle)) {
                $score = 300 + mb_strlen($normalizedNeedle, 'UTF-8');
            } else {
                $needleWords = array_values(array_filter(explode(' ', $normalizedNeedle), static fn (string $word): bool => mb_strlen($word, 'UTF-8') >= 5));
                $matchedWords = 0;

                foreach ($needleWords as $word) {
                    if (str_contains($link['normalized'] . ' ' . $link['hrefNormalized'], $word)) {
                        $matchedWords++;
                    }
                }

                if ($matchedWords >= 2) {
                    $score = 100 + ($matchedWords * 10);
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $link;
            }
        }
    }

    return $best;
}

function compactText(string $text): string
{
    return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
}

function normalizeText(string $text): string
{
    $text = str_replace(["\u{00A0}", '’', '`', 'ʼ', '«', '»', '„', '“', '”'], [' ', "'", "'", "'", ' ', ' ', ' ', ' ', ' '], $text);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^\p{L}\p{N}\s\'\-]+/u', ' ', $text) ?? $text;

    return compactText($text);
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
function detectExtension(array $response, string $sourceUrl, bool $isHtml): string
{
    if ($isHtml) {
        return 'html';
    }

    $body = $response['body'];
    $contentType = strtolower($response['contentType']);

    if (str_starts_with($body, "PK\x03\x04")) {
        return 'docx';
    }

    if (str_starts_with($body, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
        return 'doc';
    }

    if (str_starts_with($body, 'Rar!')) {
        return 'rar';
    }

    $pathExtension = strtolower(pathinfo((string) parse_url($response['effectiveUrl'] ?: $sourceUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
    $allowed = ['doc', 'docx', 'rtf', 'pdf', 'rar', 'zip', 'odt'];

    if (in_array($pathExtension, $allowed, true)) {
        return $pathExtension;
    }

    return match (true) {
        str_contains($contentType, 'wordprocessingml') => 'docx',
        str_contains($contentType, 'msword') => 'doc',
        str_contains($contentType, 'rtf') => 'rtf',
        str_contains($contentType, 'pdf') => 'pdf',
        str_contains($contentType, 'rar') => 'rar',
        str_contains($contentType, 'zip') => 'zip',
        default => 'bin',
    };
}

function removeOldTargetFiles(string $directory, string $key): void
{
    foreach (glob($directory . '/' . $key . '.*') ?: [] as $oldFile) {
        if (is_file($oldFile)) {
            unlink($oldFile);
        }
    }
}

function sanitizeHtmlDocument(string $html, string $title): string
{
    $html = normalizeHtmlCharset($html);
    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();

    foreach (['script', 'iframe', 'object', 'embed', 'base', 'link'] as $tagName) {
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

            if (str_starts_with($name, 'on') || $name === 'style') {
                $attributesToRemove[] = $attribute->name;
                continue;
            }

            if (in_array($name, ['href', 'src'], true)) {
                $absolute = resolveUrl(SOURCE_PAGE, $value);
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
    $content = '';

    if ($body !== null) {
        foreach ($body->childNodes as $child) {
            $content .= $dom->saveHTML($child);
        }
    }

    return '<!doctype html><html lang="uk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
        . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '</title><style>body{max-width:980px;margin:0 auto;padding:36px 24px;color:#1c0803;background:#f8f3e9;font:17px/1.6 Arial,sans-serif}h1,h2,h3{line-height:1.2}a{color:#8c5a17}img{max-width:100%;height:auto}table{max-width:100%;border-collapse:collapse}td,th{padding:8px;border:1px solid #d8c9b0}</style></head><body>'
        . $content
        . '</body></html>';
}

function resolveUrl(string $baseUrl, string $href): string
{
    if (preg_match('~^https?://~i', $href)) {
        return $href;
    }

    if (str_starts_with($href, '//')) {
        return 'https:' . $href;
    }

    $base = parse_url($baseUrl);
    $scheme = $base['scheme'] ?? 'https';
    $host = $base['host'] ?? '';

    if (str_starts_with($href, '/')) {
        return $scheme . '://' . $host . $href;
    }

    $basePath = $base['path'] ?? '/';
    $directory = rtrim(str_replace('\\', '/', dirname($basePath)), '/');
    $combined = ($directory === '' ? '' : $directory) . '/' . $href;
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

    return $scheme . '://' . $host . '/' . implode('/', $parts);
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}

/**
 * @param array<string, array{title:string,path:string,extension:string,size:int,source_label:string}> $manifest
 * @param array<int, string> $failed
 */
function writeMigrationReadme(string $documentsRoot, array $manifest, array $failed): void
{
    $lines = [
        '# Локальні документи',
        '',
        'Матеріали перенесені зі старого порталу та зберігаються на сервері нового сайту.',
        '',
    ];

    foreach ($manifest as $entry) {
        $lines[] = '- `' . $entry['path'] . '` — ' . $entry['title'];
    }

    if ($failed !== []) {
        $lines[] = '';
        $lines[] = '## Не перенесено';
        $lines[] = '';
        foreach ($failed as $message) {
            $lines[] = '- ' . $message;
        }
    }

    file_put_contents($documentsRoot . '/README.md', implode("\n", $lines) . "\n");
}
