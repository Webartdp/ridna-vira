<?php

declare(strict_types=1);

namespace App\Support;

final class Publications
{
    private ?array $catalogue = null;

    private ?array $articleAuthors = null;

    public function catalogue(): array
    {
        return $this->catalogue ??= json_decode(file_get_contents(resource_path('content/publications/catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function entries(string $section): array
    {
        $entries = Cms::merge($section, array_values($this->catalogue()[$section] ?? []), '');
        usort($entries, static fn ($left, $right) => strcmp($right['date'] ?? '', $left['date'] ?? ''));

        return $entries;
    }

    public function find(string $section, string $slug): ?array
    {
        foreach ($this->entries($section) as $entry) {
            if ($entry['slug'] === $slug) {
                return $entry;
            }
        }

        return null;
    }

    public function articleAuthor(array $article): string
    {
        $this->articleAuthors ??= json_decode(file_get_contents(resource_path('content/publications/article-authors.json')), true, flags: JSON_THROW_ON_ERROR);
        $source = rawurldecode((string) parse_url($article['source_url'] ?? '', PHP_URL_PATH));

        return $article['author'] ?? $this->articleAuthors[$source] ?? '';
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
                if (! empty($entry['source_url']) && ! empty($article['source_url']) && $canonical($entry['source_url']) === $canonical($article['source_url'])) {
                    $relative = (string) ($entry['path'] ?? '');
                    if ($relative !== '' && ! str_contains($relative, '..') && is_file(storage_path('app/content/articles/'.$relative))) {
                        $slug = (string) ($entry['slug'] ?? $key);
                        break;
                    }
                }
            }
            $items[] = [...$article, 'author' => $this->articleAuthor($article), 'url' => route('articles.show', $slug)];
        }

        return array_slice($items, 0, 4);
    }
}
