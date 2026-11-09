<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Publications;
use Illuminate\Contracts\View\View;

final class HomeController extends Controller
{
    public function __invoke(Publications $publications): View
    {
        $news = array_map(static fn ($entry) => [...$entry, 'url' => route('news.show', $entry['slug'])], array_slice($publications->entries('news'), 0, 4));
        $creative = [];
        foreach ($publications->catalogue()['featured_creativity'] as $slug) {
            $entry = $publications->find('creativity', $slug);
            if ($entry !== null) {
                $creative[] = [...$entry, 'url' => route('creativity.show', $slug)];
            }
        }

        return view('home', ['materialSections' => [
            ['title' => 'Новини', 'url' => route('news.index'), 'items' => $news],
            ['title' => 'Статті', 'url' => route('articles.index'), 'items' => $publications->articleHighlights()],
            ['title' => 'Творчість', 'url' => route('creativity.index'), 'items' => $creative],
        ]]);
    }
}
