<?php

namespace App\Support;

use App\Models\ContentPage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class BookLibrary
{
    public static function originals(): array
    {
        $books = [];
        foreach (config('faith_books.books', []) as $book) {
            $slug = $book['slug'] ?? Str::slug($book['title']);
            $books[$slug] = [...$book, 'slug' => $slug];
        }

        return $books;
    }

    public static function import(): int
    {
        $created = 0;
        foreach (self::originals() as $slug => $book) {
            $path = '/ridna-vira/knyhy/'.$slug;
            if (ContentPage::withTrashed()->where('path', $path)->exists()) {
                continue;
            }
            ContentPage::forceCreate([
                'path' => $path, 'section' => 'books', 'title' => $book['title'], 'category' => $book['category'] ?? '',
                'excerpt' => $book['description'] ?? '', 'body' => '<p>'.e($book['description'] ?? '').'</p>',
                'editor_mode' => 'reading', 'is_imported' => true, 'is_published' => true, 'is_override' => true,
                'book' => [...array_intersect_key($book, array_flip(['author', 'publisher', 'year', 'pages'])), 'pdf_enabled' => isset($book['formats']['pdf']), 'docx_enabled' => isset($book['formats']['docx'])],
            ]);
            $created++;
        }

        return $created;
    }

    public static function entries(): array
    {
        $books = self::originals();
        if (Cms::ready()) {
            foreach (ContentPage::withTrashed()->where('section', 'books')->get() as $page) {
                $slug = basename($page->path);
                if (! $page->visible()) {
                    unset($books[$slug]);

                    continue;
                }
                $original = $books[$slug] ?? [];
                $metadata = $page->book ?? [];
                $formats = [];
                foreach (['pdf', 'docx'] as $format) {
                    if (($metadata[$format.'_enabled'] ?? true) && (! empty($metadata[$format]) || isset($original['formats'][$format]))) {
                        $formats[$format] = route('faith.books.download', ['book' => $slug, 'format' => $format]);
                    }
                }
                $books[$slug] = [...$original, ...$metadata, 'slug' => $slug, 'title' => $page->title,
                    'category' => $page->category ?? '', 'description' => $page->excerpt ?? Seo::text($page->body ?? '', 500),
                    'formats' => $formats, 'image' => $page->image ? ContentImages::visual($page->image) : ($page->image_origin ? null : self::nativeCover($slug)),
                    'url' => url($page->path),
                ];
            }
        }
        foreach ($books as $slug => &$book) {
            if (! array_key_exists('image', $book)) {
                $book['image'] = self::nativeCover($slug);
            }
            if (Cms::ready() && isset($book['url'])) {
                continue;
            }
            $book['formats'] = array_combine(array_keys($book['formats'] ?? []), array_map(fn ($format) => route('faith.books.download', ['book' => $slug, 'format' => $format]), array_keys($book['formats'] ?? [])));
        }
        unset($book);

        return array_values($books);
    }

    public static function find(string $slug): array
    {
        return collect(self::entries())->firstWhere('slug', $slug) ?? abort(404);
    }

    public static function nativeCover(string $slug): ?array
    {
        foreach (['jpg', 'jpeg', 'png', 'webp', 'gif'] as $extension) {
            $relative = 'assets/books/'.$slug.'/cover.'.$extension;
            if (is_file(public_path($relative)) && $size = @getimagesize(public_path($relative))) {
                return ['src' => asset($relative), 'width' => $size[0], 'height' => $size[1]];
            }
        }

        return null;
    }

    public static function file(array $book, string $format): ?string
    {
        if (! isset($book['formats'][$format])) {
            return null;
        }
        if (! empty($book[$format])) {
            $disk = Storage::disk('public');
            $root = realpath($disk->path('books'));
            $file = realpath($disk->path($book[$format]));

            return $root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file) && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === $format ? $file : null;
        }
        foreach ([public_path('assets/books/'.$book['slug']), storage_path('app/content/books/'.$book['slug'])] as $directory) {
            $file = $directory.'/'.$book['slug'].'.'.$format;
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    public static function refreshLayout(string $html): string
    {
        foreach (['books-catalogue' => 'faith-books-list', 'books-summary' => 'faith-books-summary'] as $slot => $class) {
            $key = '[[live:'.$slot.']]';
            if (str_contains($html, $key)) {
                continue;
            }
            $document = Cms::document($html);
            $node = (new \DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]')->item(0);
            if ($node) {
                $node->parentNode->replaceChild($document->createTextNode($key), $node);
                $html = implode('', array_map(fn ($child) => $document->saveHTML($child), iterator_to_array($document->getElementById('cms-root')->childNodes)));
            }
        }

        return $html;
    }
}
