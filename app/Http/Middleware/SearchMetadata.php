<?php

namespace App\Http\Middleware;

use App\Support\CalendarDownloads;
use App\Support\HomeImages;
use App\Support\Seo;
use App\Support\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SearchMetadata
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        $private = Seo::privatePath('/'.trim($request->path(), '/'));
        if (($private || $response->getStatusCode() >= 400) && ! $response->headers->has('X-Robots-Tag')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || $private || $request->routeIs('landing.obriadove-vbrannia') || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }
        $html = HomeImages::optimize($response->getContent(), $request->routeIs('home'));
        $html = Site::removeEncyclopediaLinksFromHtml($html);
        if ($request->routeIs('faith.holiday')) {
            $html = CalendarDownloads::rewriteLinks($html, $request->getHost());
        }
        if (! str_contains($html, '</head>')) {
            return $response;
        }
        $seo = Seo::metadata($request, $html);
        $head = view('partials.seo', compact('seo'))->render();
        $html = preg_replace_callback('~(<head\b[^>]*>)(.*?)(</head>)~is', static function (array $parts) use ($head) {
            $contents = preg_replace('~<title>.*?</title>|<meta\b[^>]*name=["\x27]description["\x27][^>]*>~is', '', $parts[2]);

            return $parts[1].$contents.$head.$parts[3];
        }, $html, 1);
        $original = $response instanceof Response ? $response->getOriginalContent() : null;
        $response->setContent($html);
        if ($response instanceof Response) {
            $response->original = $original;
        }

        return $response;
    }
}
