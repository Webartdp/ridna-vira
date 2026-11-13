<?php

namespace App\Support;

final class DocumentContent
{
    /**
     * Legacy pages were built with layout tables, FONT/CENTER tags and inline
     * presentational attributes. Convert that markup into a clean fragment
     * which can be rendered inside the site's document template.
     */
    public static function clean(string $html, bool $legacyOnly = false): string
    {
        if (trim($html) === '') {
            return '';
        }

        $dom = Cms::document($html);

        $xpath = new \DOMXPath($dom);
        $root = $dom->getElementById('cms-root');

        // The old portal puts navigation, a duplicate title and the document in one table.
        $legacyTable = $xpath->query('.//table[.//a[normalize-space(.) = "До розділу"] and .//h1 and .//blockquote]', $root)->item(0);
        $body = $legacyTable ? $xpath->query('.//blockquote[not(ancestor::blockquote)]', $legacyTable)->item(0) : null;
        if (! $body && $legacyOnly) {
            return $html;
        }
        if ($body) {
            $fragment = $dom->createDocumentFragment();
            while ($body->firstChild) {
                $fragment->appendChild($body->firstChild);
            }
            $legacyTable->parentNode->replaceChild($fragment, $legacyTable);
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
            if (! $table->parentNode) {
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
            $nodes = array_reverse(iterator_to_array($root->getElementsByTagName($tagName)));
            foreach ($nodes as $node) {
                if (! $node->parentNode) {
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
            if (! $element instanceof \DOMElement) {
                continue;
            }

            $allowed = match (strtolower($element->tagName)) {
                'a' => ['href', 'title'],
                'td', 'th' => ['colspan', 'rowspan'],
                'table' => ['class'],
                default => [],
            };

            foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
                if (! in_array(strtolower($attribute->name), $allowed, true)) {
                    $element->removeAttribute($attribute->name);
                }
            }

            if (strtolower($element->tagName) === 'a') {
                $href = trim($element->getAttribute('href'));
                if ($href !== '' && ! preg_match('#^(https?://|mailto:|tel:|/(?!/)|#)#i', $href)) {
                    $element->removeAttribute('href');
                }
            }
        }

        if ($body) {
            foreach (iterator_to_array($root->getElementsByTagName('p')) as $paragraph) {
                $text = trim(preg_replace('/\s+/u', ' ', $paragraph->textContent) ?? '');
                if ($text !== '' && $paragraph->getElementsByTagName('b')->length > 0
                    && preg_match('/^(?:(?:РОЗДІЛ )?\d+\s*\.\s+\p{Lu}|Про |"Про |Зразок |Зразки )/u', $text)
                    && (mb_strtoupper($text) === $text || ! preg_match('/^\d/u', $text))) {
                    $heading = $dom->createElement('h3');
                    while ($paragraph->firstChild) {
                        $heading->appendChild($paragraph->firstChild);
                    }
                    $paragraph->parentNode->replaceChild($heading, $paragraph);
                }
            }
            foreach (iterator_to_array($root->getElementsByTagName('h2')) as $heading) {
                $replacement = $dom->createElement('h3');
                while ($heading->firstChild) {
                    $replacement->appendChild($heading->firstChild);
                }
                $heading->parentNode->replaceChild($replacement, $heading);
            }
        }

        // Drop empty visual leftovers from old markup.
        foreach (['div', 'span', 'p', 'b', 'strong'] as $tagName) {
            $nodes = array_reverse(iterator_to_array($root->getElementsByTagName($tagName)));
            foreach ($nodes as $node) {
                if (! $node->parentNode) {
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
