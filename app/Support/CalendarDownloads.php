<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

final class CalendarDownloads
{
    private const MANIFEST = 'calendar-download-aliases.json';

    private const DIRECTORY = 'assets/holidays';

    public static function rebuild(): int
    {
        $root = public_path(self::DIRECTORY);
        $groups = [];
        foreach (is_dir($root) ? File::allFiles($root) : [] as $file) {
            $format = strtolower($file->getExtension());
            if ($file->isLink() || ! in_array($format, ['pdf', 'doc', 'docx', 'djvu', 'rtf'], true)) {
                continue;
            }
            $path = '/'.self::DIRECTORY.'/'.str_replace('\\', '/', $file->getRelativePathname());
            $groups[$format.':'.$file->getSize()][$path] = $file->getPathname();
        }

        // Give an identical copy already in the library precedence over the imported copies.
        $preferred = [];
        if ($groups !== []) {
            foreach (BookLibrary::entries() as $book) {
                foreach (['pdf', 'docx'] as $format) {
                    $file = empty($book[$format]) ? BookLibrary::file($book, $format) : null;
                    if ($file && isset($groups[$format.':'.filesize($file)]) && ($hash = hash_file('sha256', $file))) {
                        $preferred[$format.':'.$hash] ??= route('faith.books.download', [
                            'book' => $book['slug'], 'format' => $format,
                        ], absolute: false);
                    }
                }
            }
        }

        $aliases = [];
        foreach ($groups as $key => $files) {
            $format = explode(':', $key, 2)[0];
            ksort($files, SORT_STRING);
            foreach ($files as $path => $file) {
                $hash = hash_file('sha256', $file);
                if ($hash === false) {
                    continue;
                }
                $canonical = $preferred[$format.':'.$hash] ??= $path;
                if ($canonical !== $path) {
                    $aliases[$path] = $canonical;
                }
            }
        }

        $manifest = Storage::disk('local')->path(self::MANIFEST);
        File::ensureDirectoryExists(dirname($manifest));
        File::replace($manifest, json_encode($aliases, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return count($aliases);
    }

    public static function rewriteLinks(string $html, string $host): string
    {
        if (! str_contains($html, '/'.self::DIRECTORY.'/')) {
            return $html;
        }
        $aliases = json_decode(Storage::disk('local')->get(self::MANIFEST) ?? '', true);
        if (! is_array($aliases) || $aliases === []) {
            return $html;
        }

        $books = null;

        return preg_replace_callback('~(<a\b[^>]*?\s+href\s*=\s*)(["\x27])([^"\x27]+)\2~i', function (array $link) use ($aliases, $host, &$books) {
            $parts = parse_url(html_entity_decode($link[3], ENT_QUOTES | ENT_HTML5));
            if ($parts === false || (isset($parts['host']) && strcasecmp($parts['host'], $host) !== 0)) {
                return $link[0];
            }
            $path = rawurldecode($parts['path'] ?? '');
            $target = $aliases[$path] ?? null;
            if ($target === null) {
                return $link[0];
            }
            if (str_starts_with($target, '/'.self::DIRECTORY.'/') && ! is_file(public_path(ltrim($target, '/')))) {
                return $link[0];
            }
            if (preg_match('~^/ridna-vira/knyhy/([a-z0-9-]+)/zavantazhyty/(pdf|docx)$~D', $target, $bookRoute)) {
                $books ??= array_column(BookLibrary::entries(), null, 'slug');
                $book = $books[$bookRoute[1]] ?? [];
                $format = $bookRoute[2];
                if (! empty($book[$format]) || BookLibrary::file($book, $format) === null) {
                    return $link[0];
                }
            }
            $target = implode('/', array_map(rawurlencode(...), explode('/', $target)));
            $suffix = (isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');

            return $link[1].$link[2].e($target.$suffix).$link[2];
        }, $html);
    }
}
