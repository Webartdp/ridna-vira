<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ArticleController extends Controller
{
    public function index(): View
    {
        $manifest = $this->manifest();
        $articles = array_values($manifest['articles'] ?? []);

        usort($articles, static fn (array $left, array $right): int => strcmp($left['title'] ?? '', $right['title'] ?? ''));

        return view('pages.articles', [
            'articles' => $articles,
            'source' => $manifest['source'] ?? null,
            'importedAt' => $manifest['generated_at'] ?? null,
        ]);
    }

    public function show(string $article): View
    {
        $entry = $this->resolveArticle($article);
        $path = $this->absolutePath($entry);
        $extension = strtolower((string) ($entry['extension'] ?? pathinfo($path, PATHINFO_EXTENSION)));
        $content = null;

        if ($extension === 'html') {
            $content = (string) file_get_contents($path);
        }

        return view('pages.article', [
            'article' => $entry,
            'articleSlug' => $article,
            'articleTitle' => (string) ($entry['title'] ?? 'Стаття'),
            'content' => $content,
            'extension' => strtoupper($extension),
            'size' => is_file($path) ? (filesize($path) ?: 0) : 0,
            'hasFile' => is_file($path),
        ]);
    }

    public function download(string $article): BinaryFileResponse
    {
        $entry = $this->resolveArticle($article);
        $path = $this->absolutePath($entry);
        $extension = strtolower((string) ($entry['extension'] ?? pathinfo($path, PATHINFO_EXTENSION)));
        $title = (string) ($entry['title'] ?? $article);
        $fileName = $this->downloadFileName($title, $article, $extension);

        return response()->download($path, $fileName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $path = storage_path('app/content/articles/manifest.json');

        if (!is_file($path)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        return is_array($manifest) ? $manifest : [];
    }

    /** @return array<string, mixed> */
    private function resolveArticle(string $slug): array
    {
        abort_unless(preg_match('/^[a-z0-9\-]+$/', $slug) === 1, 404);

        $articles = $this->manifest()['articles'] ?? [];

        foreach ($articles as $key => $article) {
            $articleSlug = (string) ($article['slug'] ?? $key);

            if ($articleSlug === $slug) {
                return $article;
            }
        }

        abort(404, 'Статтю не знайдено.');
    }

    /** @param array<string, mixed> $entry */
    private function absolutePath(array $entry): string
    {
        $relativePath = trim((string) ($entry['path'] ?? ''), '/\\');
        abort_unless($relativePath !== '' && !str_contains($relativePath, '..'), 404);

        $base = storage_path('app/content/articles');
        $path = $base.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $baseRealPath = realpath($base);
        $realPath = realpath($path);

        abort_unless($baseRealPath !== false && $realPath !== false, 404, 'Файл статті ще не імпортовано.');
        abort_unless(str_starts_with($realPath, $baseRealPath.DIRECTORY_SEPARATOR), 404);

        return $realPath;
    }

    private function downloadFileName(string $title, string $fallback, string $extension): string
    {
        $name = trim((string) preg_replace('/[^\p{L}\p{N}._ -]+/u', '-', $title));
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return ($name !== '' ? $name : $fallback).'.'.$extension;
    }
}
