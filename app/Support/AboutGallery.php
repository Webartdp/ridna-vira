<?php

namespace App\Support;

use App\Models\SiteSetting;
use DOMXPath;
use Illuminate\Support\Facades\Storage;

final class AboutGallery
{
    private const DEFAULT_PHOTOS = [
        'assets/figma/about/gallery-1.png' => 'Святодійство громади Рідної Віри',
        'assets/figma/about/gallery-2.png' => 'Спільна світлина учасників громади',
        'assets/figma/about/gallery-3.png' => 'Учасники святкування Рідної Віри',
    ];

    public static function paths(): array
    {
        $paths = json_decode(SiteSetting::read('about_gallery', 'null'), true);

        return is_array($paths) ? $paths : array_keys(self::DEFAULT_PHOTOS);
    }

    public static function defaultUrl(string $path): ?string
    {
        return isset(self::DEFAULT_PHOTOS[$path]) ? asset($path) : null;
    }

    public static function photos(): array
    {
        $photos = [];
        $disk = Storage::disk('public');

        foreach (self::paths() as $path) {
            $url = self::defaultUrl($path);
            if ($url) {
                $dimensions = @getimagesize(public_path($path));
                if (! $dimensions) {
                    continue;
                }
                [$width, $height] = $dimensions;
                $visual = ['src' => $url, 'width' => $width, 'height' => $height, 'variants' => []];
            } elseif ($disk->exists($path)) {
                $url = $disk->url($path);
                $visual = ContentImages::visual($path);
            } else {
                continue;
            }

            $photos[] = ['url' => $url, 'visual' => $visual, 'alt' => self::DEFAULT_PHOTOS[$path] ?? 'Світлина Духовного центру «Рідна Віра»'];
        }

        return $photos;
    }

    public static function refreshLayout(string $html): string
    {
        $slot = '[[live:about-gallery]]';
        if (str_contains($html, $slot)) {
            return $html;
        }

        $document = Cms::document($html);
        $gallery = (new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " about-gallery ")]')->item(0);
        if (! $gallery) {
            return $html;
        }

        $gallery->parentNode->replaceChild($document->createTextNode($slot), $gallery);

        return implode('', array_map(fn ($node) => $document->saveHTML($node), iterator_to_array($document->getElementById('cms-root')->childNodes)));
    }
}
