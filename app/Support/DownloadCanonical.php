<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ContentPage;

final class DownloadCanonical
{
    public static function header(string $downloadPath, ?string $readingPath = null): string
    {
        if ($readingPath !== null) {
            $page = Cms::ready() ? ContentPage::withTrashed()->where('path', $readingPath)->first() : null;
            if ($page === null || $page->visible()) {
                $downloadPath = $readingPath;
            }
        }

        return '<'.Seo::url($downloadPath).'>; rel="canonical"';
    }
}
