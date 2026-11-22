<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Publications;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class PublicationController extends Controller
{
    public function __construct(private readonly Publications $publications) {}

    public function index(Request $request, string $section): View
    {
        $entries = $this->publications->entries($section);
        $category = $section === 'creativity' ? $request->string('category', strict: true)->toString() : '';
        $categories = array_values(array_unique(array_filter(array_column($entries, 'category'))));
        if ($category !== '') {
            $entries = array_values(array_filter($entries, static fn ($entry) => $entry['category'] === $category));
        }
        $page = LengthAwarePaginator::resolveCurrentPage();
        abort_if($page > max(1, (int) ceil(count($entries) / 12)), 404);

        return view('pages.publications', [
            'section' => $section, 'title' => $section === 'news' ? 'Новини' : 'Творчість',
            'categories' => $categories, 'category' => $category,
            'entries' => new LengthAwarePaginator(array_slice($entries, ($page - 1) * 12, 12), count($entries), 12, $page,
                ['path' => $request->url(), 'query' => $category === '' ? [] : ['category' => $category]]),
        ]);
    }

    public function show(string $publication, string $section): View|RedirectResponse
    {
        $entry = $this->publications->find($section, $publication);
        abort_if($entry === null, 404);
        if (! empty($entry['redirect'])) {
            return redirect($entry['redirect'], 301);
        }
        $content = file_get_contents(resource_path('content/publications/'.$entry['path']));

        return view('pages.publication', [
            'entry' => $entry, 'section' => $section, 'content' => $content,
            'sectionTitle' => $section === 'news' ? 'Новини' : 'Творчість',
        ]);
    }
}
