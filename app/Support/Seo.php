<?php

namespace App\Support;

use App\Models\ContentPage;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Services\ProductImages;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class Seo
{
    public static function url(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    public static function privatePath(string $path): bool
    {
        return (bool) preg_match('~^/(?:kabinet|uviyty|vyity|reiestratsiia|parol|mcp|admin|livewire|koshyk|zamovlennia|oplata|up)(?:/|$)~', $path);
    }

    public static function imageInfo(string $url): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host && $host !== parse_url(config('app.url'), PHP_URL_HOST) && $host !== request()->getHost()) {
            return [];
        }
        $path = rawurldecode((string) parse_url($url, PHP_URL_PATH));
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return [];
        }
        $storage = str_starts_with($path, '/storage/');
        $root = realpath($storage ? Storage::disk('public')->path('') : public_path());
        $file = realpath($storage ? Storage::disk('public')->path(substr($path, 9)) : public_path(ltrim($path, '/')));
        if (! $root || ! $file || ! str_starts_with($file, $root.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return [];
        }
        $info = @getimagesize($file);

        return $info ? ['width' => $info[0], 'height' => $info[1], 'type' => $info['mime']] : [];
    }

    private static function shareImage(string $file): string
    {
        $variant = ProductImages::variant($file, 1200);

        return Storage::disk('public')->url(Storage::disk('public')->exists($variant) ? $variant : $file);
    }

    public static function text(string $html, int $limit = 165): string
    {
        $html = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', $html);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(['</p>', '</h2>', '</li>', '<br>', '<br/>'], ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return Str::limit($text, $limit, '…');
    }

    public static function defaults(string $path, string $title, string $body = '', ?string $excerpt = null): array
    {
        if ($static = config('seo.pages.'.$path)) {
            return $static;
        }
        $title = self::text($title, 220);
        $description = self::text($body !== '' ? (Cms::fragment($body) ?? $body) : ($excerpt ?? ''));
        if (mb_strlen($description) < 35) {
            $section = match (true) {
                str_contains($path, '/bohy/') => 'Образ, символіка та місце в українській духовній традиції.',
                str_contains($path, '/kalendar/') => 'Зміст свята, його традиції та місце в річному Колі Сварожому.',
                str_contains($path, '/sviatyni/') => 'Історія та матеріали про священне місце духовної спадщини України.',
                str_contains($path, '/obriady/') => 'Зміст і порядок проведення обряду в традиції Рідної Віри.',
                str_contains($path, '/molytvy/') => 'Текст молитви чи слави в українській духовній традиції.',
                str_contains($path, '/kramnychka/') => 'Опис, актуальна ціна та наявність у крамниці Духовного центру.',
                default => 'Матеріал Духовного центру про українську духовну спадщину та живу традицію.',
            };
            $description = self::text($title.'. '.$section);
        }

        return [str_contains($title, 'Рідна Віра') ? $title : $title.' — Рідна Віра', $description];
    }

    public static function metadata(Request $request, string $html): array
    {
        $path = '/'.trim($request->path(), '/');
        $page = Cms::ready() ? ContentPage::where('path', $path)->first() : null;
        $product = $request->route('product');
        $product = $product instanceof Product ? $product : null;
        preg_match('~<main\b[^>]*>(.*?)</main>~s', $html, $main);
        $body = $main[1] ?? '';
        $document = Cms::document($body);
        $title = $page?->title ?? $product?->name ?? $document->getElementsByTagName('h1')->item(0)?->textContent;
        if (! $title) {
            preg_match('~<title>(.*?)</title>~s', $html, $oldTitle);
            $title = self::text($oldTitle[1] ?? 'Рідна Віра');
        }
        // Read prose, never navigation or the footer, when creating a search snippet.
        $prose = Cms::fragment($body);
        if ($prose === null) {
            $paragraphs = [];
            foreach ($document->getElementsByTagName('p') as $paragraph) {
                if (mb_strlen(trim($paragraph->textContent)) >= 40) {
                    $paragraphs[] = $paragraph->textContent;
                }
            }
            $prose = implode(' ', $paragraphs);
        }
        [$seoTitle, $description] = self::defaults($path, $title, $prose, $page?->excerpt);
        $seoTitle = $page?->seo_title ?: ($product?->seo_title ?: $seoTitle);
        $description = $page?->seo_description ?: ($product?->seo_description ?: $description);
        $query = [];
        $pagination = max(1, (int) $request->query('page', 1));
        if ($pagination > 1) {
            $query['page'] = $pagination;
            $seoTitle .= ' — Сторінка '.$pagination;
            $description .= ' Сторінка '.$pagination.'.';
        }
        $category = $request->query('category');
        if (is_string($category) && $category !== '') {
            $query['category'] = mb_substr($category, 0, 200);
        }
        $canonical = self::url($path).($query ? '?'.http_build_query($query) : '');
        $image = $page?->image ? self::shareImage($page->image) : null;
        if (! $image && $path === '/' && ! $page?->image_origin) {
            $image = '/assets/figma/home/hero.png';
        }
        if (! $image && $path === '/novyny') {
            $image = '/assets/social/news.jpg';
        }
        if (! $image && $product && ! empty($product->images)) {
            $image = $product->imageUrl($product->images[0], 1200);
        }
        if (! $image && ! $page?->image_origin) {
            foreach ($document->getElementsByTagName('img') as $node) {
                $src = $node->getAttribute('src');
                if ($src && ! preg_match('~(?:ornament|texture|logo|icon|star|\.svg(?:\?|$))~i', $src)) {
                    $image = $src;
                    break;
                }
            }
        }
        if (! $image || preg_match('~\.svg(?:\?|$)~i', $image)) {
            $image = '/icons/icon-512.png';
        }
        if (! preg_match('~^https?://~i', $image)) {
            $image = self::url($image);
        }
        $imageInfo = self::imageInfo($image);
        $type = $product ? 'product' : (preg_match('~^/(?:novyny|statti|tvorchist)/[^/]+$~D', $path) ? 'article' : 'website');
        $noindex = self::privatePath($path) || ($path === '/statti'
            ? $request->anyFilled(['q', 'author', 'search', 'category'])
            : $request->hasAny(['q', 'search', 'category']));
        $published = $page?->published_at?->toIso8601String();
        if (! $published && $type === 'article') {
            $date = $document->getElementsByTagName('time')->item(0)?->getAttribute('datetime');
            if ($date && preg_match('/^\d{4}-\d{2}-\d{2}(?:T[0-9:+.Z-]+)?$/D', $date)) {
                $published = CarbonImmutable::parse($date)->toIso8601String();
            }
        }
        $modified = $page?->is_override ? $page->updated_at?->toIso8601String() : null;
        $sectionLabel = ContentPage::sections()[$page?->section] ?? match (true) {
            str_starts_with($path, '/novyny/') => 'Новини', str_starts_with($path, '/statti/') => 'Статті', str_starts_with($path, '/tvorchist/') => 'Творчість', default => null
        };
        $video = null;
        foreach ($document->getElementsByTagName('iframe') as $iframe) {
            if (preg_match('~^https://(?:www\.)?youtube(?:-nocookie)?\.com/embed/([A-Za-z0-9_-]{11})(?:[?/]|$)~', $iframe->getAttribute('src'), $match)) {
                $video = ['url' => 'https://www.youtube-nocookie.com/embed/'.$match[1], 'width' => (int) ($iframe->getAttribute('width') ?: 760), 'height' => (int) ($iframe->getAttribute('height') ?: 428)];
                break;
            }
        }
        $logo = SiteSetting::read('logo') ? Storage::disk('public')->url(SiteSetting::read('logo')) : self::url('/assets/figma/home/logo.png');
        if (! preg_match('~^https?://~i', $logo)) {
            $logo = self::url($logo);
        }
        $logoInfo = self::imageInfo($logo);
        $org = ['@type' => 'Organization', '@id' => self::url('/').'#organization', 'name' => 'Духовний центр «Рідна Віра»', 'url' => self::url('/'), 'logo' => ['@type' => 'ImageObject', 'url' => $logo, ...Arr::only($logoInfo, ['width', 'height'])]];
        if ($socialLinks = Site::socialLinks()) {
            $org['sameAs'] = array_values(array_column($socialLinks, 'url'));
        }
        $pageType = match (true) {
            $type === 'article' => str_starts_with($path, '/novyny/') ? 'NewsArticle' : 'Article', $path === '/zviazok' => 'ContactPage', str_starts_with($path, '/pro-tsentr') => 'AboutPage', isset(config('seo.pages')[$path]) && ! in_array($path, ['/', '/umovy-kramnytsi']) => 'CollectionPage', default => 'WebPage'
        };
        $schemaPage = ['@type' => $pageType, '@id' => $canonical.'#page', 'url' => $canonical, 'name' => $seoTitle, 'description' => $description, 'inLanguage' => 'uk', 'isPartOf' => ['@id' => self::url('/').'#website'], 'primaryImageOfPage' => ['@id' => $canonical.'#image'], 'about' => ['@id' => $org['@id']]];
        if ($type === 'article') {
            unset($schemaPage['primaryImageOfPage']);
            $schemaPage['headline'] = self::text($title, 220);
            $schemaPage['image'] = [$image];
            $schemaPage['publisher'] = ['@id' => $org['@id']];
            $schemaPage['mainEntityOfPage'] = $canonical;
            if ($sectionLabel) {
                $schemaPage['articleSection'] = $sectionLabel;
            }
            if ($published) {
                $schemaPage['datePublished'] = $published;
            }
            if ($modified) {
                $schemaPage['dateModified'] = $modified;
            }
        }
        $graph = [$org, ['@type' => 'WebSite', '@id' => self::url('/').'#website', 'url' => self::url('/'), 'name' => 'Рідна Віра', 'inLanguage' => 'uk', 'publisher' => ['@id' => $org['@id']]], $schemaPage];
        $graph[] = ['@type' => 'ImageObject', '@id' => $canonical.'#image', 'url' => $image, 'contentUrl' => $image, ...Arr::only($imageInfo, ['width', 'height'])];
        if ($path !== '/') {
            $crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Головна', 'item' => self::url('/')]];
            $parts = explode('/', trim($path, '/'));
            $parent = '';
            foreach (array_slice($parts, 0, -1) as $part) {
                $parent .= '/'.$part;
                if ($parentMeta = config('seo.pages.'.$parent)) {
                    $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => $parentMeta[0], 'item' => self::url($parent)];
                }
            }
            $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => self::text($title, 220), 'item' => $canonical];
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs];
        }
        if ($product) {
            $graph[2]['mainEntity'] = ['@id' => $canonical.'#product'];
            $graph[] = ['@type' => 'Product', '@id' => $canonical.'#product', 'url' => $canonical, 'name' => $product->name, 'description' => $description, 'image' => [$image], 'sku' => $product->sku ?: (string) $product->id,
                'offers' => ['@type' => 'Offer', 'url' => $canonical, 'priceCurrency' => 'UAH', 'price' => number_format($product->price_minor / 100, 2, '.', ''), 'availability' => 'https://schema.org/'.($product->stock > 0 ? 'InStock' : 'OutOfStock')]];
        }

        return compact('seoTitle', 'description', 'canonical', 'image', 'imageInfo', 'type', 'noindex', 'graph', 'published', 'modified', 'sectionLabel', 'video');
    }
}
