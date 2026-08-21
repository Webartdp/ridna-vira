<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require_once dirname(__DIR__).'/vendor/autoload.php';

/**
 * One-time migration of books from the legacy portal.
 * Public pages link to static files under public/assets/books.
 */

const SOURCE_PAGE = 'https://svit.in.ua/kny/Pashnyk.htm';
const USER_AGENT = 'Mozilla/5.0 (compatible; RidnaViraBookMigration/1.1; +https://ridnavira.com.ua)';

$projectRoot = dirname(__DIR__);
$booksRoot = $projectRoot.'/public/assets/books';
$reportPath = $projectRoot.'/storage/app/content/books-import.json';
$config = require $projectRoot.'/config/faith_books.php';
$books = $config['books'] ?? [];

try {
    ensureDirectory($booksRoot);
    ensureDirectory(dirname($reportPath));

    $downloadedBooks = 0;
    $downloadedFiles = 0;
    $failed = [];
    $reportBooks = [];

    foreach ($books as $book) {
        $title = (string) ($book['title'] ?? 'Без назви');
        $slug = bookSlug($book);
        $bookDirectory = $booksRoot.'/'.$slug;
        $bookFailed = false;
        $entry = [
            'title' => $title,
            'slug' => $slug,
            'cover' => null,
            'formats' => [],
            'errors' => [],
        ];

        ensureDirectory($bookDirectory);

        if (!empty($book['cover'])) {
            try {
                $cover = downloadCover((string) $book['cover'], $bookDirectory, $projectRoot);
                $entry['cover'] = $cover;
                $downloadedFiles++;
                echo "[OK] {$title}: обкладинка -> {$cover['path']}\n";
            } catch (Throwable $exception) {
                $bookFailed = true;
                $message = "{$title}: обкладинка: {$exception->getMessage()}";
                $entry['errors'][] = $message;
                $failed[] = $message;
                fwrite(STDERR, "[FAIL] {$message}\n");
            }
        }

        foreach (($book['formats'] ?? []) as $format => $url) {
            $format = strtolower((string) $format);

            if (!in_array($format, ['pdf', 'docx'], true)) {
                $bookFailed = true;
                $message = "{$title}: непідтримуваний формат {$format}";
                $entry['errors'][] = $message;
                $failed[] = $message;
                fwrite(STDERR, "[FAIL] {$message}\n");
                continue;
            }

            try {
                $file = downloadFormat((string) $url, $bookDirectory, $slug, $format, $projectRoot);
                $entry['formats'][$format] = $file;
                $downloadedFiles++;
                echo "[OK] {$title}: {$format} -> {$file['path']}\n";
            } catch (Throwable $exception) {
                $bookFailed = true;
                $message = "{$title}: {$format}: {$exception->getMessage()}";
                $entry['errors'][] = $message;
                $failed[] = $message;
                fwrite(STDERR, "[FAIL] {$message}\n");
            }
        }

        if (!$bookFailed) {
            $downloadedBooks++;
        }

        $reportBooks[$slug] = $entry;
    }

    $report = [
        'generated_at' => date(DATE_ATOM),
        'source' => $config['source'] ?? SOURCE_PAGE,
        'storage' => 'public/assets/books',
        'books_total' => count($books),
        'books_imported' => $downloadedBooks,
        'files_imported' => $downloadedFiles,
        'failed' => $failed,
        'books' => $reportBooks,
    ];

    file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

    echo "\nКниг у списку: ".count($books)."\n";
    echo "Книг повністю перенесено: {$downloadedBooks}\n";
    echo "Файлів перенесено: {$downloadedFiles}\n";
    echo "Файли: public/assets/books\n";
    echo "Звіт: storage/app/content/books-import.json\n";

    if ($failed !== []) {
        fwrite(STDERR, "\nНе перенесено ".count($failed)." файл(ів):\n- ".implode("\n- ", $failed)."\n");
        exit(2);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, '[FATAL] '.$exception->getMessage()."\n");
    exit(1);
}

/** @param array<string, mixed> $book */
function bookSlug(array $book): string
{
    $slug = (string) ($book['slug'] ?? '');

    if ($slug !== '') {
        return $slug;
    }

    $title = (string) ($book['title'] ?? 'knyha');
    $slug = Str::slug($title);

    return $slug !== '' ? $slug : 'knyha-'.substr(sha1($title), 0, 8);
}

/** @return array{path:string,url:string,size:int,content_type:string,source:string} */
function downloadCover(string $url, string $directory, string $projectRoot): array
{
    $response = fetchUrl($url);
    rejectHtmlResponse($response, $url);

    $extension = detectImageExtension($response, $url);
    $destination = $directory.'/cover.'.$extension;

    writeBinaryFile($destination, $response['body']);
    removeOtherCoverFiles($directory, basename($destination));

    return fileReport($destination, $response, $url, $projectRoot);
}

/** @return array{path:string,url:string,size:int,content_type:string,source:string} */
function downloadFormat(string $url, string $directory, string $slug, string $format, string $projectRoot): array
{
    $response = fetchUrl($url);
    rejectHtmlResponse($response, $url);

    $destination = $directory.'/'.$slug.'.'.$format;
    writeBinaryFile($destination, $response['body']);

    return fileReport($destination, $response, $url, $projectRoot);
}

/** @return array{body:string, contentType:string, effectiveUrl:string} */
function fetchUrl(string $url): array
{
    if (!preg_match('~^https?://~i', $url)) {
        throw new RuntimeException('Непідтримувана адреса джерела.');
    }

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
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/octet-stream,*/*;q=0.8',
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

/** @param array{body:string, contentType:string, effectiveUrl:string} $response */
function rejectHtmlResponse(array $response, string $url): void
{
    $contentType = strtolower($response['contentType']);
    $sample = ltrim(substr($response['body'], 0, 1024));

    if (str_contains($contentType, 'text/html') || preg_match('/^(?:<!doctype\s+html|<html\b|<head\b|<body\b)/i', $sample) === 1) {
        throw new RuntimeException("замість файлу отримано HTML-сторінку: {$url}");
    }
}

/** @param array{body:string, contentType:string, effectiveUrl:string} $response */
function detectImageExtension(array $response, string $sourceUrl): string
{
    $body = $response['body'];
    $contentType = strtolower($response['contentType']);
    $pathExtension = strtolower(pathinfo((string) parse_url($response['effectiveUrl'] ?: $sourceUrl, PHP_URL_PATH), PATHINFO_EXTENSION));

    if (str_starts_with($body, "\xFF\xD8\xFF")) {
        return 'jpg';
    }

    if (str_starts_with($body, "\x89PNG\r\n\x1A\n")) {
        return 'png';
    }

    if (str_starts_with($body, 'GIF87a') || str_starts_with($body, 'GIF89a')) {
        return 'gif';
    }

    if (str_starts_with($body, 'RIFF') && substr($body, 8, 4) === 'WEBP') {
        return 'webp';
    }

    if (in_array($pathExtension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        return $pathExtension === 'jpeg' ? 'jpg' : $pathExtension;
    }

    return match (true) {
        str_contains($contentType, 'jpeg') => 'jpg',
        str_contains($contentType, 'png') => 'png',
        str_contains($contentType, 'webp') => 'webp',
        str_contains($contentType, 'gif') => 'gif',
        default => 'jpg',
    };
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

function removeOtherCoverFiles(string $directory, string $keepFileName): void
{
    foreach (glob($directory.'/cover.*') ?: [] as $path) {
        if (is_file($path) && basename($path) !== $keepFileName) {
            unlink($path);
        }
    }
}

/**
 * @param array{body:string, contentType:string, effectiveUrl:string} $response
 * @return array{path:string,url:string,size:int,content_type:string,source:string}
 */
function fileReport(string $path, array $response, string $sourceUrl, string $projectRoot): array
{
    $relativePath = relativePath($projectRoot, $path);

    return [
        'path' => $relativePath,
        'url' => '/'.preg_replace('~^public/~', '', $relativePath),
        'size' => filesize($path) ?: 0,
        'content_type' => $response['contentType'],
        'source' => $sourceUrl,
    ];
}

function relativePath(string $projectRoot, string $path): string
{
    return str_replace('\\', '/', ltrim(str_replace($projectRoot, '', $path), '/\\'));
}

function ensureDirectory(string $directory): void
{
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException("Не вдалося створити каталог {$directory}");
    }
}
