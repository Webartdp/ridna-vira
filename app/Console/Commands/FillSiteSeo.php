<?php

namespace App\Console\Commands;

use App\Models\ContentPage;
use App\Models\Product;
use App\Support\Seo;
use Illuminate\Console\Command;

class FillSiteSeo extends Command
{
    protected $signature = 'site:seo';

    protected $description = 'Заповнити порожні SEO-поля сторінок і товарів, зберігши редакторські значення';

    public function handle(): int
    {
        $count = 0;
        foreach ([ContentPage::class, Product::class] as $model) {
            $model::withoutTimestamps(function () use ($model, &$count) {
                $model::chunkById(100, function ($records) use (&$count) {
                    foreach ($records as $record) {
                        $isPage = $record instanceof ContentPage;
                        [$title, $description] = Seo::defaults($isPage ? $record->path : '/kramnychka/'.$record->slug, $isPage ? $record->title : $record->name, $isPage ? ($record->body ?? '') : ($record->description ?? ''), $isPage ? $record->excerpt : null);
                        if (! $record->seo_title) {
                            $record->seo_title = $title;
                        }
                        if (! $record->seo_description) {
                            $record->seo_description = $description;
                        }
                        // Metadata backfill must not activate a CMS override, rewrite prose, or create revisions.
                        if ($record->isDirty()) {
                            $record->saveQuietly();
                            $count++;
                        }
                    }
                });
            });
        }
        $this->info('Оновлено SEO: '.$count.'. Наявні SEO-поля збережено.');

        return self::SUCCESS;
    }
}
