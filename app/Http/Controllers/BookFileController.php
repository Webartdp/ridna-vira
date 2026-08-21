<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class BookFileController extends Controller
{
    public function cover(string $book): BinaryFileResponse
    {
        $this->resolveBook($book);

        $path = $this->findFirstFile($this->bookDirectory($book), [
            'cover.jpg',
            'cover.jpeg',
            'cover.png',
            'cover.webp',
            'cover.gif',
        ]);

        if ($path === null) {
            $path = public_path('assets/figma/faith/books.png');
        }

        abort_unless(is_file($path), 404, 'Обкладинку книги ще не імпортовано.');

        return response()->file($path, [
            'Content-Type' => $this->mimeType($path),
            'Cache-Control' => 'public, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function download(string $book, string $format): BinaryFileResponse
    {
        $entry = $this->resolveBook($book);
        abort_unless(in_array($format, ['pdf', 'docx'], true), 404);

        $path = $this->bookDirectory($book).DIRECTORY_SEPARATOR.$book.'.'.$format;
        abort_unless(is_file($path), 404, 'Файл книги ще не імпортовано.');

        $title = (string) ($entry['title'] ?? $book);
        $fileName = $this->downloadFileName($title, $book, $format);

        return response()->download($path, $fileName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function resolveBook(string $slug): array
    {
        abort_unless(preg_match('/^[a-z0-9\-]+$/', $slug) === 1, 404);

        foreach (config('faith_books.books', []) as $book) {
            $title = (string) ($book['title'] ?? '');
            $bookSlug = (string) ($book['slug'] ?? Str::slug($title));

            if ($bookSlug === $slug) {
                return $book;
            }
        }

        abort(404, 'Книгу не знайдено.');
    }

    private function bookDirectory(string $slug): string
    {
        return storage_path('app/content/books/'.$slug);
    }

    /** @param array<int, string> $fileNames */
    private function findFirstFile(string $directory, array $fileNames): ?string
    {
        foreach ($fileNames as $fileName) {
            $path = $directory.DIRECTORY_SEPARATOR.$fileName;

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    private function downloadFileName(string $title, string $fallback, string $format): string
    {
        $name = trim((string) preg_replace('/[^\p{L}\p{N}._ -]+/u', '-', $title));
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return ($name !== '' ? $name : $fallback).'.'.$format;
    }
}
