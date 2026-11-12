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
        foreach (array_slice($publications->entries('creativity'), 0, 4) as $entry) {
            $creative[] = [...$entry, 'url' => route('creativity.show', $entry['slug'])];
        }

        return view('home', ['materialSections' => [
            ['title' => 'Новини', 'url' => route('news.index'), 'items' => $news],
            ['title' => 'Статті', 'text_only' => true, 'url' => route('articles.index'), 'items' => $publications->articleHighlights()],
            ['title' => 'Творчість', 'url' => route('creativity.index'), 'items' => $creative],
        ]]);
    }
}
