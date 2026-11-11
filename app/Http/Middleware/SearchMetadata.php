<?php

namespace App\Http\Middleware;

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
        if (! $request->isMethod('GET') || $response->getStatusCode() !== 200 || $private || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }
        $html = HomeImages::optimize($response->getContent(), $request->routeIs('home'));
        $html = Site::removeEncyclopediaLinksFromHtml($html);
        if (! str_contains($html, '</head>')) {
            return $response;
        }
        $seo = Seo::metadata($request, $html);
        $html = preg_replace('~<title>.*?</title>|<meta\b[^>]*name=["\x27]description["\x27][^>]*>~is', '', $html);
        $head = view('partials.seo', compact('seo'))->render();
        $original = $response instanceof Response ? $response->getOriginalContent() : null;
        $response->setContent(str_replace('</head>', $head.'</head>', $html));
        if ($response instanceof Response) {
            $response->original = $original;
        }

        return $response;
    }
}
