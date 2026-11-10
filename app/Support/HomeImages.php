<?php

namespace App\Support;

final class HomeImages
{
    public static function manifest(): array
    {
        static $manifest;

        return $manifest ??= json_decode(file_get_contents(resource_path('content/home-images.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function optimize(string $html, bool $home = false): string
    {
        $ornaments = 0;
        $html = preg_replace_callback('~<img\b[^>]*>~i', function ($match) use ($home, &$ornaments) {
            $document = Cms::document($match[0]);
            $image = $document->getElementsByTagName('img')->item(0);
            if (! $image) {
                return $match[0];
            }
            $source = $image->getAttribute('src');
            $host = parse_url($source, PHP_URL_HOST);
            if ($host && ! in_array($host, [request()->getHost(), parse_url(config('app.url'), PHP_URL_HOST)], true)) {
                return $match[0];
            }
            $path = parse_url($source, PHP_URL_PATH);
            if (! str_starts_with($path ?? '', '/assets/figma/home/')) {
                return $match[0];
            }
            $name = basename($path);
            $entry = self::manifest()[$name] ?? null;
            if (! $entry) {
                return $match[0];
            }
            $variants = $entry['variants'];
            $default = collect($variants)->first(fn ($variant) => $variant['width'] >= 640) ?? end($variants);
            $image->setAttribute('src', asset($default['src']));
            $image->setAttribute('srcset', implode(', ', array_map(fn ($variant) => asset($variant['src']).' '.$variant['width'].'w', $variants)));
            $image->setAttribute('sizes', match (true) {
                str_starts_with($name, 'holiday-') => '128px', str_starts_with($name, 'category-') => '147px',
                $name === 'logo.png' => '(min-width: 1200px) 260px, 180px', $name === 'basket.png' => '60px',
                $name === 'wheel.png' => '(min-width: 1200px) 514px, (min-width: 768px) 440px, 280px',
                $name === 'ornament.png' => '(min-width: 768px) 408px, 80vw',
                str_starts_with($name, 'partner-') => '240px', default => '(min-width: 1000px) 500px, 90vw',
            });
            $image->setAttribute('width', (string) $entry['width']);
            $image->setAttribute('height', (string) $entry['height']);
            $eager = in_array($name, ['logo.png', 'basket.png'], true) || ($home && $name === 'ornament.png' && ++$ornaments === 1);
            if (! $image->hasAttribute('loading')) {
                $image->setAttribute('loading', $eager ? 'eager' : 'lazy');
            }
            $image->setAttribute('decoding', 'async');

            return $document->saveHTML($image);
        }, $html);

        if ($home && preg_match('~<section\b[^>]*class="[^"]*hero-home[^>]*>~i', $html, $hero)) {
            $image = '/assets/figma/home/hero.png';
            if (preg_match('~background-image\s*:\s*url\(["\x27]?(.*?)["\x27]?\)~i', html_entity_decode($hero[0], ENT_QUOTES), $custom)) {
                $image = $custom[1];
            } elseif (str_contains($hero[0], 'background-image:')) {
                return $html;
            }
            $html = str_replace('</head>', '<link rel="preload" as="image" href="'.e(asset($image)).'" fetchpriority="high"></head>', $html);
        }

        return $html;
    }
}
