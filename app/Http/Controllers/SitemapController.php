<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\Product;
use App\Support\Cms;
use App\Support\Seo;
use App\Support\SiteCalendar;
use Illuminate\Support\Facades\Schema;

class SitemapController extends Controller
{
    public function __invoke()
    {
        $paths = array_fill_keys(array_keys(config('seo.pages')), null);
        unset($paths['/umovy-kramnytsi']); // Exists only after site:import or manual creation.
        foreach (config('faith_gods.gods') as $god) {
            $paths['/ridna-vira/bohy/'.$god['slug']] = null;
        }
        foreach (SiteCalendar::data()['months'] as $holidays) {
            foreach ($holidays as $holiday) {
                $paths['/ridna-vira/kalendar/'.$holiday['slug']] = null;
            }
        }
        $catalogue = json_decode(file_get_contents(resource_path('content/publications/catalogue.json')), true);
        foreach (['news' => '/novyny/', 'articles' => '/statti/', 'creativity' => '/tvorchist/'] as $section => $prefix) {
            foreach ($catalogue[$section] ?? [] as $slug => $entry) {
                if (empty($entry['redirect']) && is_file(resource_path('content/publications/'.($entry['path'] ?? '')))) {
                    $paths[$prefix.($entry['slug'] ?? $slug)] = null;
                }
            }
        }
        foreach (['rituals' => '/ridna-vira/obriady/', 'prayers' => '/ridna-vira/molytvy/'] as $section => $prefix) {
            $data = json_decode(file_get_contents(resource_path('content/'.$section.'/catalogue.json')), true);
            foreach ($data['entries'] ?? [] as $slug => $entry) {
                $paths[$prefix.($entry['slug'] ?? $slug)] = null;
            }
        }
        foreach (['articles' => '/statti/', 'shrines' => '/ridna-vira/sviatyni/'] as $section => $prefix) {
            $file = storage_path('app/content/'.$section.'/manifest.json');
            $data = is_file($file) ? json_decode(file_get_contents($file), true) : [];
            foreach ($data[$section] ?? [] as $slug => $entry) {
                $slug = $entry['slug'] ?? $slug;
                if ($section === 'articles') {
                    $base = realpath(! empty($entry['bundled']) ? resource_path('content/publications') : storage_path('app/content/articles'));
                    $file = $base ? realpath($base.DIRECTORY_SEPARATOR.($entry['path'] ?? '')) : false;
                    if (! $file || ! is_file($file) || ! str_starts_with($file, $base.DIRECTORY_SEPARATOR)) {
                        continue;
                    }
                }
                if (preg_match('/^[a-z0-9-]+$/D', (string) $slug)) {
                    $paths[$prefix.$slug] = null;
                }
            }
        }
        $file = public_path('assets/documents/manifest.json');
        foreach ((is_file($file) ? json_decode(file_get_contents($file), true) : []) as $slug => $entry) {
            $paths['/dokumenti/'.$slug] = null;
        }
        if (Cms::ready()) {
            foreach (ContentPage::withTrashed()->get(['id', 'path', 'is_override', 'is_published', 'published_at', 'deleted_at', 'updated_at']) as $page) {
                if (! $page->visible()) {
                    unset($paths[$page->path]);

                    continue;
                }
                if ($page->is_override || array_key_exists($page->path, $paths)) {
                    $paths[$page->path] = $page->is_override ? $page->updated_at->toAtomString() : ($paths[$page->path] ?? null);
                }
            }
        }
        if (Schema::hasTable('products')) {
            foreach (Product::where('is_active', true)->get(['slug', 'updated_at']) as $product) {
                $paths['/kramnychka/'.$product->slug] = $product->updated_at->toAtomString();
            }
        }
        unset($paths['/ridna-vira/sviatyni/kamiani-mogili']);
        ksort($paths);
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $root = $xml->appendChild($xml->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset'));
        foreach ($paths as $path => $modified) {
            if (Seo::privatePath($path) || str_contains($path, '?')) {
                continue;
            }
            $node = $root->appendChild($xml->createElement('url'));
            $node->appendChild($xml->createElement('loc'))->appendChild($xml->createTextNode(Seo::url($path)));
            if ($modified) {
                $node->appendChild($xml->createElement('lastmod', $modified));
            }
        }

        // No scheduled export or stale CDN copy: every request reflects current publication state.
        return response($xml->saveXML(), 200)->header('Content-Type', 'application/xml; charset=UTF-8')->header('Cache-Control', 'public, max-age=0, must-revalidate');
    }
}
