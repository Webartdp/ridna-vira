<?php

declare(strict_types=1);

namespace App\Support;

final class Publications
{
    private ?array $catalogue = null;

    public function catalogue(): array
    {
        return $this->catalogue ??= json_decode(file_get_contents(resource_path('content/publications/catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function entries(string $section): array
    {
        $entries = array_values($this->catalogue()[$section] ?? []);
        if ($section === 'news') {
            usort($entries, static fn ($left, $right) => strcmp($right['date'], $left['date']));
        }

        return $entries;
    }

    public function find(string $section, string $slug): ?array
    {
        return $this->catalogue()[$section][$slug] ?? null;
    }

    public function articleHighlights(): array
    {
        $path = storage_path('app/content/articles/manifest.json');
        $imported = is_file($path) ? (json_decode(file_get_contents($path), true)['articles'] ?? []) : [];
        $canonical = static fn (string $url) => rawurldecode((string) parse_url($url, PHP_URL_PATH));
        $items = [];
        foreach ($this->entries('articles') as $article) {
            $slug = $article['slug'];
            foreach ($imported as $key => $entry) {
                if (! empty($entry['source_url']) && $canonical($entry['source_url']) === $canonical($article['source_url'])) {
                    $relative = (string) ($entry['path'] ?? '');
                    if ($relative !== '' && ! str_contains($relative, '..') && is_file(storage_path('app/content/articles/'.$relative))) {
                        $slug = (string) ($entry['slug'] ?? $key);
                        break;
                    }
                }
            }
            $items[] = [...$article, 'url' => route('articles.show', $slug)];
        }

        return $items;
    }
}
