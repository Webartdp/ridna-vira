<?php

namespace App\Support;

use App\Models\ContentPage;
use App\Services\ProductImages;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;

final class ContentImages
{
    public static function prepare(array $data): array
    {
        if (isset($data['image_origin'])) {
            $data['body'] = self::strip((string) ($data['body'] ?? ''), $data['image_origin'], ($data['editor_mode'] ?? '') === 'layout');

            return $data;
        }
        $section = $data['section'] ?? 'pages';
        $slug = basename($data['path'] ?? '');
        $html = (string) ($data['body'] ?? '');
        $slot = $section === 'gods' ? 'god' : 'content';
        $source = null;
        if ($section === 'gods') {
            foreach (['portrait.webp', 'portrait.png', 'portrait.svg'] as $file) {
                if (is_file(public_path('assets/gods/'.$slug.'/'.$file))) {
                    $source = '/assets/gods/'.$slug.'/'.$file;
                    break;
                }
            }
        } elseif ($section === 'books') {
            $source = BookLibrary::nativeCover($slug)['src'] ?? null;
        } elseif (in_array($section, ['news', 'creativity', 'articles'], true)) {
            $catalogue = json_decode(file_get_contents(resource_path('content/publications/catalogue.json')), true);
            $source = $catalogue[$section][$slug]['image']['src'] ?? null;
        } elseif ($section === 'shrines') {
            $manifest = storage_path('app/content/shrines/manifest.json');
            $entries = is_file($manifest) ? (json_decode(file_get_contents($manifest), true)['shrines'] ?? []) : [];
            foreach ($entries as $key => $entry) {
                if (($entry['slug'] ?? $key) !== $slug) {
                    continue;
                }
                foreach ($entry['assets'] ?? [] as $asset) {
                    if (preg_match('~\.(?:jpe?g|png|webp|gif|svg)(?:\?.*)?$~i', $asset['path'] ?? '')) {
                        $source = $asset['path'];
                        break;
                    }
                }
                break;
            }
        }
        if ($section === 'pages' && str_contains($html, 'hero-home')) {
            $source = '/assets/figma/home/hero.png';
            $slot = 'hero';
        }
        $document = Cms::document($section === 'pages' ? $html : (Cms::fragment($html) ?? $html));
        $first = null;
        foreach ($document->getElementsByTagName('img') as $image) {
            $src = $image->getAttribute('src');
            if ($src !== '' && ! preg_match('~(?:ornament|texture|logo|icon|star|footer-line)~i', $src)) {
                $first = $src;
                break;
            }
        }
        $source ??= $first;
        if (! $source) {
            return $data;
        }
        $managed = $data['image'] ?? self::import($source);
        if (! $managed) {
            return $data;
        }
        $data['image'] = $managed;
        $data['image_origin'] = ['source' => $source, 'body_source' => $first && self::same($first, $source) ? $first : null, 'slot' => $slot];
        $data['body'] = self::strip($html, $data['image_origin'], ($data['editor_mode'] ?? '') === 'layout');

        return $data;
    }

    public static function same(string $left, string $right): bool
    {
        $path = fn ($url) => '/'.ltrim(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/');
        if ($path($left) === $path($right)) {
            return true;
        }
        $identity = fn ($url) => preg_replace('~[-_](?:320|400|640|800|960|1200)$~', '', pathinfo($path($url), PATHINFO_FILENAME));

        return dirname($path($left)) === dirname($path($right)) && $identity($left) === $identity($right);
    }

    public static function import(string $source): ?string
    {
        $host = parse_url($source, PHP_URL_HOST);
        if ($host && ! in_array($host, [parse_url(config('app.url'), PHP_URL_HOST), request()->getHost(), 'ridnavira.com.ua', 'www.ridnavira.com.ua'], true)) {
            return null;
        }
        $path = rawurldecode((string) parse_url($source, PHP_URL_PATH));
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }
        $disk = Storage::disk('public');
        if (str_starts_with($path, '/storage/')) {
            $relative = substr($path, 9);

            return $disk->exists($relative) ? $relative : null;
        }
        $relative = ltrim($path, '/');
        if (! str_starts_with($relative, 'assets/')) {
            return null;
        }
        $file = realpath(public_path($relative));
        $root = realpath(public_path('assets'));
        if (! $file || ! $root || ! is_file($file) || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR)) {
            return null;
        }
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true)) {
            return null;
        }
        if ($extension !== 'svg' && ! @getimagesize($file)) {
            return null;
        }
        $target = 'content/covers/'.hash('sha256', $relative.filemtime($file).filesize($file)).'.'.$extension;
        if (! $disk->exists($target)) {
            $disk->put($target, file_get_contents($file));
        }

        return $target;
    }

    public static function strip(string $html, array $origin, bool $marker = false): string
    {
        if (empty($origin['body_source'])) {
            return $html;
        }
        $document = Cms::document($html);
        foreach (iterator_to_array($document->getElementsByTagName('img')) as $image) {
            if (! self::same($image->getAttribute('src'), $origin['body_source'])) {
                continue;
            }
            $node = $image->parentNode instanceof DOMElement && $image->parentNode->tagName === 'picture' ? $image->parentNode : $image;
            if ($marker) {
                $placeholder = $document->createElement('span');
                $placeholder->setAttribute('data-cms-primary-image', '');
                $node->parentNode->replaceChild($placeholder, $node);
            } else {
                $node->parentNode->removeChild($node);
            }
            break;
        }

        return self::html($document);
    }

    public static function visual(?string $file): ?array
    {
        if (! $file) {
            return null;
        }
        $disk = Storage::disk('public');
        $srcset = [];
        $width = 800;
        $height = 600;
        foreach ([400, 800, 1200] as $size) {
            $variant = ProductImages::variant($file, $size);
            if ($disk->exists($variant) && $info = @getimagesize($disk->path($variant))) {
                $srcset[$info[0]] = ['src' => $disk->url($variant), 'width' => $info[0]];
            }
        }
        $source = ProductImages::variant($file, 800);
        if (! $disk->exists($source)) {
            $source = $file;
        }
        if ($disk->exists($source) && $info = @getimagesize($disk->path($source))) {
            [$width, $height] = $info;
        }

        return ['src' => $disk->url($source), 'width' => $width, 'height' => $height, 'variants' => array_values($srcset)];
    }

    public static function render(string $html, ContentPage $page): string
    {
        if (! $page->image && ! $page->image_origin) {
            return $html;
        }
        $origin = $page->image_origin ?? [];
        $document = Cms::document(self::strip($html, $origin, true));
        $xpath = new DOMXPath($document);
        $slot = $origin['slot'] ?? ($page->section === 'gods' ? 'god' : 'content');
        $visual = self::visual($page->image);
        if ($slot === 'hero') {
            $hero = $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," hero-home ")]')->item(0);
            if ($hero) {
                $hero->setAttribute('style', $visual ? 'background-image:url("'.str_replace(['"', "\n", "\r"], '', $visual['src']).'")' : 'background-image:none');
            }

            return self::html($document);
        }
        $placeholder = $xpath->query('//*[@data-cms-primary-image]')->item(0);
        $figure = $slot === 'god' ? $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," god-reading-figure ")]')->item(0) : null;
        if ($figure) {
            while ($figure->firstChild) {
                $figure->removeChild($figure->firstChild);
            }
        }
        if (! $visual) {
            $placeholder?->parentNode?->removeChild($placeholder);

            return self::html($document);
        }
        $image = $document->createElement('img');
        foreach (['src' => $visual['src'], 'alt' => $page->title, 'width' => $visual['width'], 'height' => $visual['height'], 'decoding' => 'async'] as $key => $value) {
            $image->setAttribute($key, (string) $value);
        }
        if ($visual['variants']) {
            $image->setAttribute('srcset', implode(', ', array_map(fn ($variant) => $variant['src'].' '.$variant['width'].'w', $visual['variants'])));
            $image->setAttribute('sizes', '(min-width: 900px) 800px, 94vw');
        }
        if ($figure) {
            $figure->appendChild($image);
        } elseif ($placeholder) {
            $placeholder->parentNode->replaceChild($image, $placeholder);
        } else {
            $image->setAttribute('style', 'display:block;max-width:100%;height:auto;margin:0 auto 24px');
            $container = $xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," article-prose ") or contains(concat(" ",normalize-space(@class)," ")," shrine-reading-content ") or contains(concat(" ",normalize-space(@class)," ")," holiday-sheet__content ") or contains(concat(" ",normalize-space(@class)," ")," document-sheet__content ")]')->item(0);
            if ($container) {
                $container->parentNode->insertBefore($image, $container);
            }
        }

        return self::html($document);
    }

    private static function html(\DOMDocument $document): string
    {
        return implode('', array_map(fn ($node) => $document->saveHTML($node), iterator_to_array($document->getElementById('cms-root')->childNodes)));
    }
}
