<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DocumentDownloadController extends Controller
{
    public function show(string $document): View
    {
        $entry = $this->resolveEntry($document);
        $relativePath = $this->normalizeRelativePath((string) ($entry['path'] ?? ''));
        $extension = strtolower((string) ($entry['extension'] ?? pathinfo($relativePath, PATHINFO_EXTENSION)));
        $absolutePath = $this->resolveAbsolutePath($relativePath);
        $title = trim((string) ($entry['title'] ?? $document));

        $editablePath = resource_path('content/documents/'.$document.'.html');
        $content = null;
        $contentSource = null;

        if (is_file($editablePath)) {
            $content = $this->normalizeDocumentHtml((string) file_get_contents($editablePath));
            $contentSource = 'editable';
        } elseif ($extension === 'html' && is_file($absolutePath)) {
            $content = $this->normalizeDocumentHtml((string) file_get_contents($absolutePath));
            $contentSource = 'imported';
        }

        return view('pages.document', [
            'documentKey' => $document,
            'documentTitle' => $title,
            'extension' => strtoupper($extension ?: 'FILE'),
            'size' => (int) ($entry['size'] ?? (is_file($absolutePath) ? filesize($absolutePath) : 0)),
            'content' => $content,
            'contentSource' => $contentSource,
            'hasFile' => is_file($absolutePath),
        ]);
    }

    /**
     * @return BinaryFileResponse|Response
     */
    public function download(string $document): BinaryFileResponse|Response
    {
        $entry = $this->resolveEntry($document);
        $relativePath = $this->normalizeRelativePath((string) ($entry['path'] ?? ''));
        $extension = strtolower((string) ($entry['extension'] ?? pathinfo($relativePath, PATHINFO_EXTENSION)));
        $title = trim((string) ($entry['title'] ?? $document));
        $absolutePath = $this->resolveAbsolutePath($relativePath);

        abort_unless(is_file($absolutePath), 404, 'Файл документа відсутній.');

        $safeTitle = preg_replace('/[^\pL\pN._ -]+/u', '-', $title) ?: $document;
        $downloadName = trim($safeTitle) . ($extension !== '' ? '.' . $extension : '');

        return response()->download($absolutePath, $downloadName, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function resolveEntry(string $document): array
    {
        $manifestPath = public_path('assets/documents/manifest.json');

        abort_unless(is_file($manifestPath), 404, 'Сховище документів ще не сформоване.');

        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        abort_unless(is_array($manifest) && isset($manifest[$document]) && is_array($manifest[$document]), 404, 'Документ не знайдено.');

        return $manifest[$document];
    }

    private function normalizeRelativePath(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/');
        $relativePath = preg_replace('#^public/#', '', $relativePath) ?: $relativePath;

        abort_unless(
            $relativePath !== ''
            && !str_contains($relativePath, '..')
            && str_starts_with($relativePath, 'assets/documents/'),
            404,
            'Некоректний шлях документа.'
        );

        return $relativePath;
    }

    private function resolveAbsolutePath(string $relativePath): string
    {
        return public_path($relativePath);
    }

    /**
     * Legacy pages were built with layout tables, FONT/CENTER tags and inline
     * presentational attributes. Convert that markup into a clean fragment
     * which can be rendered inside the site's document template.
     */
    private function normalizeDocumentHtml(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="rv-document-root">'.$html.'</div>',
            LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        $root = $dom->getElementById('rv-document-root');

        if (!$root) {
            return strip_tags($html, '<p><br><strong><b><em><i><u><h1><h2><h3><h4><ol><ul><li><table><thead><tbody><tr><th><td><blockquote><a>');
        }

        foreach (['script', 'style', 'link', 'meta', 'base', 'iframe', 'object', 'embed', 'form', 'input', 'button', 'noscript'] as $tagName) {
            $nodes = iterator_to_array($root->getElementsByTagName($tagName));
            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Old pages often contain navigation/logo images which are irrelevant
        // inside an article. Keep the document purely textual and printable.
        foreach (iterator_to_array($root->getElementsByTagName('img')) as $image) {
            $image->parentNode?->removeChild($image);
        }

        // Work from the deepest tables outwards. One-column tables are layout
        // containers and are unwrapped. Real multi-column tables are preserved.
        $tables = iterator_to_array($root->getElementsByTagName('table'));
        $tables = array_reverse($tables);

        foreach ($tables as $table) {
            if (!$table->parentNode) {
                continue;
            }

            $rows = iterator_to_array($table->getElementsByTagName('tr'));
            $maxCells = 0;

            foreach ($rows as $row) {
                $cellCount = 0;
                foreach ($row->childNodes as $child) {
                    if ($child instanceof \DOMElement && in_array(strtolower($child->tagName), ['td', 'th'], true)) {
                        $cellCount++;
                    }
                }
                $maxCells = max($maxCells, $cellCount);
            }

            if ($maxCells <= 1) {
                $fragment = $dom->createDocumentFragment();
                $cells = iterator_to_array($table->getElementsByTagName('td'));

                if ($cells === []) {
                    $cells = iterator_to_array($table->getElementsByTagName('th'));
                }

                foreach ($cells as $cell) {
                    foreach (iterator_to_array($cell->childNodes) as $child) {
                        $fragment->appendChild($child->cloneNode(true));
                    }
                }

                $table->parentNode->replaceChild($fragment, $table);
                continue;
            }

            $table->setAttribute('class', 'document-data-table');
        }

        // Replace obsolete wrappers without carrying their old styles.
        foreach (['font', 'center'] as $tagName) {
            $nodes = iterator_to_array($root->getElementsByTagName($tagName));
            foreach ($nodes as $node) {
                if (!$node->parentNode) {
                    continue;
                }

                $fragment = $dom->createDocumentFragment();
                foreach (iterator_to_array($node->childNodes) as $child) {
                    $fragment->appendChild($child->cloneNode(true));
                }
                $node->parentNode->replaceChild($fragment, $node);
            }
        }

        // Remove imported layout/presentation attributes. Preserve only the
        // few semantic attributes required by real document tables and links.
        foreach ($xpath->query('.//*', $root) ?: [] as $element) {
            if (!$element instanceof \DOMElement) {
                continue;
            }

            $allowed = match (strtolower($element->tagName)) {
                'a' => ['href', 'title'],
                'td', 'th' => ['colspan', 'rowspan'],
                'table' => ['class'],
                default => [],
            };

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                if (!in_array(strtolower($attribute->name), $allowed, true)) {
                    $element->removeAttribute($attribute->name);
                }
            }

            if (strtolower($element->tagName) === 'a') {
                $href = trim($element->getAttribute('href'));
                if ($href !== '' && !preg_match('#^(https?://|mailto:|tel:|/)#i', $href)) {
                    $element->removeAttribute('href');
                }
            }
        }

        // Drop empty visual leftovers from old markup.
        foreach (['div', 'span', 'p', 'b', 'strong'] as $tagName) {
            $nodes = array_reverse(iterator_to_array($root->getElementsByTagName($tagName)));
            foreach ($nodes as $node) {
                if (!$node->parentNode) {
                    continue;
                }

                $text = trim(preg_replace('/\x{00A0}/u', ' ', (string) $node->textContent) ?? '');
                if ($text === '' && $node->getElementsByTagName('*')->length === 0) {
                    $node->parentNode->removeChild($node);
                }
            }
        }

        $content = '';
        foreach ($root->childNodes as $child) {
            $content .= $dom->saveHTML($child);
        }

        return trim($content);
    }
}
