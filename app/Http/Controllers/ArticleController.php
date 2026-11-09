<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ArticleContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ArticleController extends Controller
{
    public function __construct(private readonly ArticleContent $articleContent) {}

    public function index(Request $request): View
    {
        $manifest = $this->manifest();
        $articles = [];
        foreach ($manifest['articles'] ?? [] as $key => $entry) {
            $slug = (string) ($entry['slug'] ?? $key);
            $path = $this->existingPath($entry);
            if ($path === null || preg_match('/^[a-z0-9\-]+$/', $slug) !== 1) {
                continue;
            }
            $presented = $this->present($entry, $path);
            $articles[] = [...$entry, 'slug' => $slug, 'title' => $presented['title'], 'excerpt' => $presented['excerpt']];
        }

        usort($articles, static fn (array $left, array $right): int => strcmp($left['title'] ?? '', $right['title'] ?? ''));

        $page = max(1, (int) $request->query('page', 1));

        return view('pages.articles', ['articles' => new LengthAwarePaginator(
            array_slice($articles, ($page - 1) * 18, 18), count($articles), 18, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        )]);
    }

    public function show(string $article): View
    {
        $entry = $this->resolveArticle($article);
        $path = $this->absolutePath($entry);
        $extension = strtolower((string) ($entry['extension'] ?? pathinfo($path, PATHINFO_EXTENSION)));
        $presented = $this->present($entry, $path);

        return view('pages.article', [
            'article' => $entry,
            'articleSlug' => $article,
            'articleTitle' => $presented['title'],
            'content' => $presented['content'],
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
        $title = $this->present($entry, $path)['title'];
        $fileName = $this->downloadFileName($title, $article, $extension);

        return response()->download($path, $fileName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $path = storage_path('app/content/articles/manifest.json');

        if (! is_file($path)) {
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
        $path = $this->existingPath($entry);
        abort_unless($path !== null, 404, 'Файл статті ще не імпортовано.');

        return $path;
    }

    /** @param array<string, mixed> $entry */
    private function existingPath(array $entry): ?string
    {
        $relativePath = trim((string) ($entry['path'] ?? ''), '/\\');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $base = storage_path('app/content/articles');
        $path = $base.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $baseRealPath = realpath($base);
        $realPath = realpath($path);

        if ($baseRealPath === false || $realPath === false || ! is_file($realPath) || ! str_starts_with($realPath, $baseRealPath.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return $realPath;
    }

    /** @param array<string, mixed> $entry
     * @return array{title: string, content: ?string, excerpt: string}
     */
    private function present(array $entry, string $path): array
    {
        $key = 'article-presentation-v3-'.hash('sha256', $path.filemtime($path).filesize($path).serialize($entry));

        return Cache::rememberForever($key, function () use ($entry, $path): array {
            $isHtml = strtolower((string) ($entry['extension'] ?? pathinfo($path, PATHINFO_EXTENSION))) === 'html';
            $html = $isHtml ? (string) file_get_contents($path) : '';
            $title = $this->articleContent->title($html, $entry);
            $content = $isHtml ? $this->articleContent->body($html, $title) : null;

            return ['title' => $title, 'content' => $content, 'excerpt' => $content !== null ? $this->articleContent->excerpt($content) : 'Матеріал доступний для завантаження.'];
        });
    }

    private function downloadFileName(string $title, string $fallback, string $extension): string
    {
        $name = trim((string) preg_replace('/[^\p{L}\p{N}._ -]+/u', '-', $title));
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return ($name !== '' ? $name : $fallback).'.'.$extension;
    }
}
