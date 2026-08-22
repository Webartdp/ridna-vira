<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

final class ShrineController extends Controller
{
    private const SHRINE_ALIASES = [
        'kamiani-mogili' => 'kamiana-mogila',
    ];

    public function index(): View
    {
        $manifest = $this->manifest();
        $shrines = $this->visibleShrines($manifest['shrines'] ?? []);

        return view('pages.shrines', [
            'shrines' => $shrines,
            'regions' => $manifest['regions'] ?? [],
            'source' => $manifest['source'] ?? null,
            'importedAt' => $manifest['generated_at'] ?? null,
        ]);
    }

    public function show(string $shrine): View|RedirectResponse
    {
        if (isset(self::SHRINE_ALIASES[$shrine])) {
            return redirect()->route('faith.shrines.show', ['shrine' => self::SHRINE_ALIASES[$shrine]], 301);
        }

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

        return is_array($manifest) ? $this->normalizeManifest($manifest) : [];
    }

    /** @param mixed $shrines */
    /** @return array<int, array<string, mixed>> */
    private function visibleShrines($shrines): array
    {
        if (!is_array($shrines)) {
            return [];
        }

        $availableSlugs = [];
        foreach ($shrines as $key => $shrine) {
            if (!is_array($shrine)) {
                continue;
            }

            $slug = (string) ($shrine['slug'] ?? $key);
            if ($slug !== '') {
                $availableSlugs[$slug] = true;
            }
        }

        $visible = [];
        foreach ($shrines as $key => $shrine) {
            if (!is_array($shrine)) {
                continue;
            }

            $slug = (string) ($shrine['slug'] ?? $key);
            $canonicalSlug = self::SHRINE_ALIASES[$slug] ?? null;

            if ($canonicalSlug !== null && isset($availableSlugs[$canonicalSlug])) {
                continue;
            }

            $visible[] = $shrine;
        }

        return $visible;
    }

    /** @param array<string, mixed> $manifest */
    /** @return array<string, mixed> */
    private function normalizeManifest(array $manifest): array
    {
        $regions = [];

        foreach (($manifest['regions'] ?? []) as $region) {
            $regionName = self::normalizeRegionName((string) $region);
            if ($regionName !== '') {
                $regions[$regionName] = $regionName;
            }
        }

        if (isset($manifest['shrines']) && is_array($manifest['shrines'])) {
            foreach ($manifest['shrines'] as $key => $shrine) {
                if (!is_array($shrine)) {
                    continue;
                }

                $regionName = self::normalizeRegionName((string) ($shrine['region'] ?? ''));
                if ($regionName !== '') {
                    $shrine['region'] = $regionName;
                    $regions[$regionName] = $regionName;
                } else {
                    unset($shrine['region']);
                }

                $manifest['shrines'][$key] = $shrine;
            }
        }

        if ($regions !== []) {
            $manifest['regions'] = array_values($regions);
        }

        return $manifest;
    }

    private static function normalizeRegionName(string $region): string
    {
        $region = trim(preg_replace('/\s+/u', ' ', $region) ?? $region);
        $region = preg_replace('/^святині\s+/iu', '', $region) ?? $region;

        return trim($region, " \t\n\r\0\x0B:-—–");
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
