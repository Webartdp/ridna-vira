<?php

namespace App\Support;

use App\Models\ContentPage;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteSetting;
use App\Services\ProductImages;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class Seo
{
    public static function url(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }

    public static function structuredData(array $graph): string
    {
        return json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR
        );
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
        $html = preg_replace('~</(?:p|div|h[1-6]|li|dd|dt|td|th|blockquote)\s*>|<br\s*/?>~i', ' ', $html);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return Str::limit($text, $limit, '…', preserveWords: true);
    }

    private static function productSummary(string $description): string
    {
        $limit = 165;
        if (mb_strwidth($description) > $limit
            && preg_match('/^(.+[.!?…][»”"]?)(?= )/u', mb_strimwidth($description, 0, $limit + 1), $match)
            && mb_strlen($match[1]) >= 35) {
            $description = $match[1];
        }

        // Normalize all-caps Ukrainian words while preserving short abbreviations and model codes.
        $description = preg_replace_callback(
            '/(?<![\p{L}\p{N}_-])(?:[А-ЯІЇЄҐ]{5,}|АБО|БЕЗ|БІЛЯ|ВІД|ДЛЯ|ДО|ЗА|ЗІ|ІЗ|МІЖ|НА|НАД|ПІД|ПРИ|ПРО|ТА|ЦЕ|ЦЕЙ|ЦЯ|ЩО|ЯК)(?![\p{L}\p{N}_-])/u',
            fn (array $word) => mb_strtolower($word[0]),
            $description,
        );
        $description = preg_replace_callback('/(?:^|[.!?…][»”"]? )\p{Ll}/u', fn (array $start) => mb_strtoupper($start[0]), $description);

        return Str::limit($description, $limit, '…', preserveWords: true);
    }

    public static function defaults(string $path, string $title, string $body = '', ?string $excerpt = null): array
    {
        if ($static = config('seo.pages.'.$path)) {
            return $static;
        }
        $title = self::text($title, 220);
        $description = self::text($excerpt ?? '');
        if (mb_strlen($description) < 35 && $body !== '') {
            $description = self::text(Cms::fragment($body) ?? $body);
        }
        if (mb_strlen($description) < 35) {
            $section = match (true) {
                str_contains($path, '/bohy/') => 'Образ, символіка та місце в українській духовній традиції.',
                str_contains($path, '/kalendar/') => 'Зміст свята, його традиції та місце в річному Колі Сварожому.',
                str_contains($path, '/sviatyni/') => 'Історія та матеріали про священне місце духовної спадщини України.',
                str_contains($path, '/obriady/') => 'Зміст і порядок проведення обряду в традиції Рідної Віри.',
                str_contains($path, '/molytvy/') => 'Текст молитви чи слави в українській духовній традиції.',
                str_starts_with($path, '/dokumenti/') => 'Документ Духовного центру «Рідна Віра» для читання та завантаження.',
                str_starts_with($path, '/statti/') => 'Стаття з бібліотеки Духовного центру «Рідна Віра».',
                str_contains($path, '/kramnychka/') => 'Опис, актуальна ціна та наявність у крамниці Духовного центру.',
                default => 'Матеріал Духовного центру про українську духовну спадщину та живу традицію.',
            };
            $description = self::text($title.'. '.$section);
        }

        return [str_contains($title, 'Рідна Віра') ? $title : $title.' — Рідна Віра', $description];
    }

    /** @return list<string> */
    public static function productImages(Product $product): array
    {
        $disk = Storage::disk('public');
        $images = [];
        foreach ($product->images ?? [] as $photo) {
            $files = [ProductImages::variant($photo, 1200), $photo, ProductImages::variant($photo, 800), ProductImages::variant($photo, 400)];
            foreach ($files as $file) {
                if (! $disk->exists($file)) {
                    continue;
                }
                $url = $disk->url($file);
                $images[] = preg_match('~^https?://~i', $url) ? $url : self::url($url);
                break;
            }
        }

        return array_values(array_unique($images));
    }

    public static function metadata(Request $request, string $html): array
    {
        $path = '/'.trim($request->path(), '/');
        $product = $request->route('product');
        $product = $product instanceof Product ? $product : null;
        $shopCatalogue = $request->routeIs('shop.index', 'shop.category');
        $browseCategory = $request->routeIs('shop.category') ? $request->route('category') : null;
        $browseCategory = $browseCategory instanceof ProductCategory ? $browseCategory : null;
        $page = ! $product && Cms::ready() ? ContentPage::where('path', $path)->first() : null;
        preg_match('~<main\b[^>]*>(.*?)</main>~s', $html, $main);
        $body = $main[1] ?? '';
        $document = Cms::document($body);
        $heading = $document->getElementsByTagName('h1')->item(0)?->textContent;
        $title = $product?->name ?? $heading ?? $page?->title;
        $legacyDocumentTitle = $request->routeIs('documents.download') && ($page?->title === 'Документ' || trim($heading ?? '') === 'Документ');
        if ($legacyDocumentTitle && trim($title ?? '') === 'Документ') {
            $documentTitle = (new \DOMXPath($document))->query('//header[contains(concat(" ", normalize-space(@class), " "), " document-sheet__header ")]//h2')->item(0)?->textContent;
            $title = $documentTitle ?: $title;
        }
        if (! $title) {
            preg_match('~<title>(.*?)</title>~s', $html, $oldTitle);
            $title = self::text($oldTitle[1] ?? 'Рідна Віра');
        }
        $productCategory = $product?->category;
        $productDescription = $product ? self::text(CmsHtml::clean($product->description ?? ''), 5000) : null;
        if ($product) {
            $summary = self::productSummary($productDescription);
            if (mb_strlen($summary) < 35) {
                $details = $product->name.($productCategory ? ' · '.$productCategory->name : '');
                $summary = self::text($summary !== ''
                    ? rtrim($summary, '.!?').'. '.$details
                    : $details.'. '.Money::display($product->price_minor).'. '.$product->availabilityLabel().'.');
            }
            $seoTitle = $product->seo_title ?: self::text($product->name, 90).' — Крамниця «Рідна Віра»';
            $description = $product->seo_description ?: $summary;
        } elseif ($browseCategory) {
            $seoTitle = ShopCatalogueSeo::title($browseCategory);
            $description = ShopCatalogueSeo::description($browseCategory);
        } else {
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
            [$seoTitle, $description] = self::defaults($path, $title, $prose, $page?->is_override ? $page->excerpt : null);
            if (! $legacyDocumentTitle || ! in_array($page?->seo_title, ['Документ', 'Документ — Рідна Віра'], true)) {
                $seoTitle = $page?->seo_title ?: $seoTitle;
            }
            $storedDescription = $page?->seo_description;
            $legacyDescription = $legacyDocumentTitle && preg_match('/^Документ\. Матеріал Духовного центру|До розділу/u', $storedDescription ?? '');
            if (! $legacyDescription) {
                $description = $storedDescription ?: $description;
            }
        }
        $query = [];
        $pagination = $request->routeIs('articles.index', 'news.index', 'creativity.index', 'shop.index', 'shop.category')
            ? LengthAwarePaginator::resolveCurrentPage()
            : 1;
        if ($pagination > 1) {
            $query['page'] = $pagination;
            $seoTitle .= ' — Сторінка '.$pagination;
            $description = rtrim($description).(preg_match('~[.!?…]$~u', $description) ? ' ' : '. ').'Сторінка '.$pagination.'.';
        }
        if ($request->routeIs('creativity.index')) {
            $category = $request->query('category');
            if (is_string($category) && $category !== '') {
                $query['category'] = mb_substr($category, 0, 200);
            }
        }
        $canonical = self::url($path).($query ? '?'.http_build_query($query) : '');
        $productImages = $product ? self::productImages($product) : [];
        $image = $page?->image ? self::shareImage($page->image) : ($productImages[0] ?? null);
        if (! $image && $path === '/' && ! $page?->image_origin) {
            $image = '/assets/figma/home/hero.png';
        }
        if (! $image && $path === '/novyny') {
            $image = '/assets/social/news.jpg';
        }
        if (! $image && ! $product && ! $page?->image_origin) {
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
        $noindex = self::privatePath($path)
            || ($request->routeIs('articles.index') && $request->anyFilled(['q', 'author']))
            || ($request->routeIs('creativity.index') && $request->filled('category'));
        if ($shopCatalogue) {
            $noindex = $request->filled('category') || $request->anyFilled(ShopCatalogueSeo::FILTERS);
        }
        $published = $page?->published_at?->toIso8601String();
        if (! $published && $type === 'article') {
            $date = $document->getElementsByTagName('time')->item(0)?->getAttribute('datetime');
            if ($date && preg_match('/^\d{4}-\d{2}-\d{2}(?:T[0-9:+.Z-]+)?$/D', $date)) {
                try {
                    $published = CarbonImmutable::parse($date)->toIso8601String();
                } catch (InvalidFormatException) {
                    // An invalid legacy date should not make the public page unavailable.
                }
            }
        }
        $modified = $page?->is_override && (! $page->published_at || $page->updated_at?->gte($page->published_at))
            ? $page->updated_at?->toIso8601String()
            : null;
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
            $product !== null => 'ItemPage',
            $shopCatalogue => 'CollectionPage',
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
            $author = (new \DOMXPath($document))->query('//*[@data-article-author]')->item(0)?->textContent;
            if ($author = self::text($author ?? '', 220)) {
                $schemaPage['author'] = ['@type' => 'Person', 'name' => $author];
            }
            if ($published) {
                $schemaPage['datePublished'] = $published;
            }
            if ($modified) {
                $schemaPage['dateModified'] = $modified;
            }
        }
        if ($product) {
            $schemaPage['about'] = $schemaPage['mainEntity'] = ['@id' => $canonical.'#product'];
        } elseif ($shopCatalogue && ! $noindex && ($itemList = ShopCatalogueSeo::itemList($document))) {
            $schemaPage['mainEntity'] = $itemList;
        }
        $graph = [$org, ['@type' => 'WebSite', '@id' => self::url('/').'#website', 'url' => self::url('/'), 'name' => 'Рідна Віра', 'inLanguage' => 'uk', 'publisher' => ['@id' => $org['@id']]], $schemaPage];
        $graph[] = ['@type' => 'ImageObject', '@id' => $canonical.'#image', 'url' => $image, 'contentUrl' => $image, ...Arr::only($imageInfo, ['width', 'height'])];
        if ($path !== '/') {
            $crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Головна', 'item' => self::url('/')]];
            if ($product || $browseCategory) {
                $trail = $product ? $product->breadcrumbTrail() : ShopCatalogueSeo::categoryTrail($browseCategory);
                foreach (array_slice($trail, 1, -1) as $crumb) {
                    $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => $crumb['name'], 'item' => self::url($crumb['path'])];
                }
            } elseif ($request->routeIs('documents.download')) {
                $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => 'Управління', 'item' => self::url('/pro-tsentr/upravlinnia')];
            } else {
                $parts = explode('/', trim($path, '/'));
                $parent = '';
                foreach (array_slice($parts, 0, -1) as $part) {
                    $parent .= '/'.$part;
                    if ($parentMeta = config('seo.pages.'.$parent)) {
                        $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => $parentMeta[0], 'item' => self::url($parent)];
                    }
                }
            }
            $crumbs[] = ['@type' => 'ListItem', 'position' => count($crumbs) + 1, 'name' => self::text($title, 220), 'item' => $canonical];
            $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs];
        }
        $offer = null;
        if ($product) {
            $availability = match (true) {
                $product->made_to_order => 'MadeToOrder',
                $product->purchaseLimit() > 0 => 'InStock',
                default => 'OutOfStock',
            };
            $offer = [
                '@type' => 'Offer',
                'url' => $canonical,
                'priceCurrency' => 'UAH',
                'price' => Money::decimal($product->price_minor),
                'availability' => 'https://schema.org/'.$availability,
            ];
            $productSchema = [
                '@type' => 'Product',
                '@id' => $canonical.'#product',
                'url' => $canonical,
                'mainEntityOfPage' => ['@id' => $canonical.'#page'],
                'name' => $product->name,
                'description' => $productDescription ?: $summary,
                'offers' => $offer,
            ];
            if (filled($product->sku)) {
                $productSchema['sku'] = $product->sku;
            }
            if ($productImages) {
                $productSchema['image'] = $productImages;
            }
            if ($productCategory) {
                $productSchema['category'] = $productCategory->parent
                    ? $productCategory->parent->name.' > '.$productCategory->name
                    : $productCategory->name;
            }
            $graph[] = $productSchema;
        }

        return compact('seoTitle', 'description', 'canonical', 'image', 'imageInfo', 'type', 'noindex', 'graph', 'published', 'modified', 'sectionLabel', 'video', 'offer');
    }
}
