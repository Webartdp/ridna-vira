<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Cms;
use App\Support\GodDescriptions;
use Illuminate\Contracts\View\View;

final class GodController extends Controller
{
    public function __construct(private readonly GodDescriptions $descriptions) {}

    public function index(): View
    {
        $manifest = $this->manifest();
        $gods = array_values($manifest['gods'] ?? config('faith_gods.gods', []));
        foreach ($gods as &$god) {
            $slug = (string) ($god['slug'] ?? '');
            $description = $this->descriptions->find($slug);
            if ($description !== null) {
                $god['summary'] = $description['summary'];
            }
        }
        unset($god);
        $gods = Cms::merge('gods', $gods, '/ridna-vira/bohy');

        return view('pages.gods', [
            'gods' => $gods,
            'source' => $manifest['source'] ?? config('faith_gods.source'),
            'importedAt' => $manifest['generated_at'] ?? null,
        ]);
    }

    public function show(string $god): View
    {
        $entry = $this->resolveGod($god);
        $description = $this->descriptions->find($god);
        $path = $this->absolutePath($entry);
        $content = $description === null && $path !== null && is_file($path) ? (string) file_get_contents($path) : null;

        return view('pages.god', [
            'god' => $entry,
            'godSlug' => $god,
            'godTitle' => (string) ($entry['title'] ?? 'Бог'),
            'content' => $content,
            'description' => $description,
            'hasFile' => $path !== null && is_file($path),
        ]);
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $path = storage_path('app/content/gods/manifest.json');

        if (! is_file($path)) {
            return [];
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        return is_array($manifest) ? $manifest : [];
    }

    /** @return array<string, mixed> */
    private function resolveGod(string $slug): array
    {
        abort_unless(preg_match('/^[a-z0-9\-]+$/', $slug) === 1, 404);

        foreach (($this->manifest()['gods'] ?? config('faith_gods.gods', [])) as $key => $god) {
            if (! is_array($god)) {
                continue;
            }

            $godSlug = (string) ($god['slug'] ?? $key);

            if ($godSlug === $slug) {
                return $god;
            }
        }

        abort(404, 'Матеріал про Бога не знайдено.');
    }

    /** @param array<string, mixed> $entry */
    private function absolutePath(array $entry): ?string
    {
        $relativePath = trim((string) ($entry['path'] ?? ''), '/\\');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        $base = storage_path('app/content/gods');
        $path = $base.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $baseRealPath = realpath($base);
        $realPath = realpath($path);

        if ($baseRealPath === false || $realPath === false) {
            return null;
        }

        return str_starts_with($realPath, $baseRealPath.DIRECTORY_SEPARATOR) ? $realPath : null;
    }
}
