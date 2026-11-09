<?php

namespace App\Support;

use App\Models\ContentPage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class Cms
{
    public static function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument;
        $old = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="cms-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($old);

        return $document;
    }

    public static function fragment(string $html, ?string $replacement = null, ?string $title = null): ?string
    {
        $document = self::document($html);
        $xpath = new \DOMXPath($document);
        $node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " article-prose ") or contains(concat(" ", normalize-space(@class), " "), " god-reading-content ")]')->item(0);
        if (! $node) {
            return null;
        }
        if ($replacement === null) {
            return implode('', array_map(fn ($child) => $document->saveHTML($child), iterator_to_array($node->childNodes)));
        }
        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $source = self::document($replacement);
        foreach (iterator_to_array($source->getElementById('cms-root')->childNodes) as $child) {
            $node->appendChild($document->importNode($child, true));
        }
        if ($title && $heading = $document->getElementsByTagName('h1')->item(0)) {
            $heading->textContent = $title;
        }

        return implode('', array_map(fn ($child) => $document->saveHTML($child), iterator_to_array($document->getElementById('cms-root')->childNodes)));
    }

    public static function ready(): bool
    {
        $request = request();
        if (! $request->attributes->has('cms.ready')) {
            $request->attributes->set('cms.ready', Schema::hasTable('content_pages'));
        }

        return $request->attributes->get('cms.ready');
    }

    public static function islands(string $html): array
    {
        $islands = [];
        $html = preg_replace_callback('~<!-- cms-live:([a-z0-9-]+) -->(.*?)<!-- /cms-live:\1 -->~s', function ($match) use (&$islands) {
            $key = '[[live:'.$match[1].']]';
            $islands[$key] = $match[2];

            return $key;
        }, $html);
        $index = 0;
        $html = preg_replace_callback('~<form\b[^>]*>.*?</form>|<script\b[^>]*>.*?</script>~si', function ($match) use (&$islands, &$index) {
            $key = '[[live:component-'.($index++).']]';
            $islands[$key] = $match[0];

            return $key;
        }, $html);

        return [$html, $islands];
    }

    public static function merge(string $section, array $entries, string $prefix): array
    {
        $bySlug = [];
        foreach ($entries as $key => $entry) {
            $slug = $entry['slug'] ?? $key;
            $bySlug[$slug] = [...$entry, 'slug' => $slug];
        }
        if (! self::ready()) {
            return array_values($bySlug);
        }
        foreach (ContentPage::publicOverrides()->where('section', $section)->get() as $page) {
            $slug = basename($page->path);
            if (! $page->visible()) {
                unset($bySlug[$slug]);

                continue;
            }
            if (! $page->is_override) {
                continue;
            }
            $image = $page->image ? ['src' => Storage::disk('public')->url($page->image), 'width' => 800, 'height' => 600] : ($bySlug[$slug]['image'] ?? null);
            if (! $image && $page->video_url && preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})(?:[?&#/]|$)~', $page->video_url, $video)) {
                $image = ['src' => 'https://i.ytimg.com/vi/'.$video[1].'/hqdefault.jpg', 'width' => 480, 'height' => 360];
            }
            $bySlug[$slug] = [...($bySlug[$slug] ?? []), 'slug' => $slug, 'title' => $page->title, 'excerpt' => $page->excerpt ?? '', 'summary' => $page->excerpt ?? '', 'date' => ($page->published_at ?? $page->created_at)->format('Y-m-d'), 'image' => $image, 'url' => url($page->path), 'category' => $page->category ?? $bySlug[$slug]['category'] ?? match ($section) {
                'prayers' => 'themes', 'rituals' => 'life', default => 'Матеріали'
            }, 'cms' => true];
        }

        return array_values($bySlug);
    }
}
