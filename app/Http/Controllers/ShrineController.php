<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

final class ShrineController extends Controller
{
    public function index(): View
    {
        $manifest = $this->manifest();
        $shrines = array_values($manifest['shrines'] ?? []);

        return view('pages.shrines', [
            'shrines' => $shrines,
            'regions' => $manifest['regions'] ?? [],
            'source' => $manifest['source'] ?? null,
            'importedAt' => $manifest['generated_at'] ?? null,
        ]);
    }

    public function show(string $shrine): View
    {
        $entry = $this->resolveShrine($shrine);
        $path = $this->absolutePath($entry);
        $content = is_file($path) ? (string) file_get_contents($path) : null;

        return view('pages.shrine', [
            'shrine' => $entry,
            'shrineSlug' => $shrine,
            'shrineTitle' => (string) ($entry['title'] ?? 'Святиня'),
            'content' => $content,
            'hasFile' => is_file($path),
        ]);
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $path = storage_path('app/content/shrines/manifest.json');

        if (!is_file($path)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        return is_array($manifest) ? $manifest : [];
    }

    /** @return array<string, mixed> */
    private function resolveShrine(string $slug): array
    {
        abort_unless(preg_match('/^[a-z0-9\-]+$/', $slug) === 1, 404);

        foreach (($this->manifest()['shrines'] ?? []) as $key => $shrine) {
            $shrineSlug = (string) ($shrine['slug'] ?? $key);

            if ($shrineSlug === $slug) {
                return $shrine;
            }
        }

        abort(404, 'Святиню не знайдено.');
    }

    /** @param array<string, mixed> $entry */
    private function absolutePath(array $entry): string
    {
        $relativePath = trim((string) ($entry['path'] ?? ''), '/\\');
        abort_unless($relativePath !== '' && !str_contains($relativePath, '..'), 404);

        $base = storage_path('app/content/shrines');
        $path = $base.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $baseRealPath = realpath($base);
        $realPath = realpath($path);

        abort_unless($baseRealPath !== false && $realPath !== false, 404, 'Матеріал святині ще не імпортовано.');
        abort_unless(str_starts_with($realPath, $baseRealPath.DIRECTORY_SEPARATOR), 404);

        return $realPath;
    }
}
