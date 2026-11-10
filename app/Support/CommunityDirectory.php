<?php

namespace App\Support;

use App\Models\ContentPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class CommunityDirectory
{
    public static function query(): Builder
    {
        return ContentPage::where('section', 'communities')->where('is_published', true)
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }

    public static function featured()
    {
        if (! Cms::ready()) {
            return new Collection;
        }

        return self::query()->where('community->featured', true)->orderBy('community->position')->orderBy('title')->get();
    }

    public static function refreshHomeSlots(string $html, array $islands): string
    {
        // Older saved home layouts predate these slots. Replace only the two managed elements.
        foreach (['featured-communities' => 'communities-grid', 'community-banner' => 'communities-section__background'] as $slot => $class) {
            $key = '[[live:'.$slot.']]';
            if (str_contains($html, $key) || ! isset($islands[$key])) {
                continue;
            }
            $document = Cms::document($html);
            $xpath = new \DOMXPath($document);
            $node = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]')->item(0);
            if ($node) {
                $node->parentNode->replaceChild($document->createTextNode($key), $node);
                $html = implode('', array_map(fn ($child) => $document->saveHTML($child), iterator_to_array($document->getElementById('cms-root')->childNodes)));
            }
        }

        return $html;
    }
}
