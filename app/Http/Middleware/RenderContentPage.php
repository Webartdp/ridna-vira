<?php

namespace App\Http\Middleware;

use App\Models\ContentPage;
use App\Support\AboutGallery;
use App\Support\BookLibrary;
use App\Support\Cms;
use App\Support\CmsHtml;
use App\Support\CommunityDirectory;
use App\Support\ContentImages;
use Closure;
use Illuminate\Http\Request;

class RenderContentPage
{
    public function handle(Request $request, Closure $next)
    {
        $path = '/'.trim($request->path(), '/');
        if (! $request->isMethod('GET') || $request->is('kabinet*', 'uviyty', 'vyity', 'reiestratsiia', 'parol*', 'admin*', 'livewire*', 'kramnychka*', 'koshyk*', 'zamovlennia*', 'oplata*') || app()->bound('cms.importing') || ! Cms::ready()) {
            return $next($request);
        }
        $page = ContentPage::withTrashed()->select(['id', 'path', 'published_at', 'is_published', 'is_override', 'deleted_at'])->where('path', $path)->first();
        if (! $page) {
            return $next($request);
        }
        abort_unless($page->visible(), 404);
        if (! $page->is_override) {
            return $next($request);
        }
        $page->refresh();
        if ($page->section === 'communities') {
            return $next($request);
        }
        if ($page->editor_mode === 'reading') {
            return response()->view('cms.page', compact('page'));
        }
        $response = $next($request);
        if ($response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            $html = $response->getContent();
            if (preg_match('~<main\b[^>]*>(.*?)</main>~s', $html, $match)) {
                [, $islands] = Cms::islands($match[1]);
                $saved = $page->body ?? '';
                if ($path === '/') {
                    $saved = CommunityDirectory::refreshHomeSlots($saved, $islands);
                }
                if ($path === '/ridna-vira/knyhy') {
                    $saved = BookLibrary::refreshLayout($saved);
                }
                if ($path === '/pro-tsentr' && $page->editor_mode === 'layout') {
                    $saved = AboutGallery::refreshLayout($saved);
                }
                $body = strtr($saved, $islands);
                if ($page->editor_mode === 'fragment') {
                    $body = CmsHtml::render($body);
                    $extra = '';
                    if ($page->video_url && preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})(?:[?&#/]|$)~', $page->video_url, $video)) {
                        $extra .= '<iframe src="https://www.youtube-nocookie.com/embed/'.$video[1].'" title="Відео" width="760" height="428" loading="lazy" allowfullscreen></iframe>';
                    }
                    $body = Cms::fragment($match[1], $extra.$body, $page->title) ?? $body;
                } else {
                    $body = preg_replace_callback('~(<h1\b[^>]*>).*?(</h1>)~s', fn ($parts) => $parts[1].e($page->title).$parts[2], $body, 1);
                }
                $body = ContentImages::render($body, $page);
                // Preserve freshly rendered forms, maps and feeds instead of saving expiring tokens or stale lists.
                $html = preg_replace_callback('~(<main\b[^>]*>).*?(</main>)~s', fn ($parts) => $parts[1].$body.$parts[2], $html, 1);
                $html = preg_replace_callback('~<title>.*?</title>~s', fn () => '<title>'.e($page->title).' — Рідна Віра</title>', $html, 1);
                $response->setContent($html);
            }
        }

        return $response;
    }
}
