<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/** Present legacy articles as semantic content, never as legacy page layouts. */
final class ArticleContent
{
    public function cleanTitle(string $title): string
    {
        $title = $this->text(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $title = preg_replace('/\s*\(\s*(?:html?|pdf|docx?|rtf)\s*\)\s*$/iu', '', $title) ?? $title;

        return trim($title);
    }

    /** @param array<string, mixed> $entry */
    public function title(string $html, array $entry): string
    {
        $title = $this->cleanTitle((string) ($entry['title'] ?? ''));
        if ($this->meaningfulTitle($title)) {
            return $title;
        }

        if (trim($html) !== '') {
            $document = $this->document($html);
            $xpath = new DOMXPath($document);
            foreach ($xpath->query('//body//h1 | //body//h2 | //body//h3') ?: [] as $heading) {
                $candidate = $this->cleanTitle($heading->textContent);
                if ($this->meaningfulTitle($candidate)) {
                    return $candidate;
                }
            }
        }

        $sourceTitle = $this->cleanTitle((string) ($entry['source_label'] ?? ''));

        return $this->meaningfulTitle($sourceTitle) ? $sourceTitle : 'Стаття';
    }

    public function body(string $html, string $title): string
    {
        if (trim($html) === '') {
            return '';
        }
        $document = $this->document($html);
        $xpath = new DOMXPath($document);
        $root = $xpath->query('//article | //main')->item(0) ?? $document->getElementsByTagName('body')->item(0);
        if ($root === null) {
            return '';
        }
        $this->removePrintedPageNumbers($root);
        $content = $this->children($root, $title);
        // Group stray inline text from old table cells into ordinary paragraphs.
        $fragment = $this->document($content);
        $body = $fragment->getElementsByTagName('body')->item(0);
        $result = '';
        $inline = '';
        foreach ($body?->childNodes ?? [] as $node) {
            $tag = $node instanceof DOMElement ? strtolower($node->tagName) : '';
            if (in_array($tag, ['p', 'h2', 'h3', 'h4', 'ul', 'ol', 'blockquote', 'pre', 'figure', 'div'], true)) {
                if (trim(strip_tags($inline)) !== '') {
                    $result .= '<p>'.trim($inline).'</p>';
                }
                $inline = '';
                $result .= $fragment->saveHTML($node);
            } else {
                $inline .= $fragment->saveHTML($node);
            }
        }
        if (trim(strip_tags($inline)) !== '') {
            $result .= '<p>'.trim($inline).'</p>';
        }

        return trim($result);
    }

    public function excerpt(string $body): string
    {
        $text = $this->text(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', $body)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return mb_strlen($text) > 190 ? rtrim(mb_substr($text, 0, 187)).'…' : $text;
    }

    private function document(string $html): DOMDocument
    {
        $html = preg_replace('/<meta\b[^>]*>/i', '', $html) ?? $html;
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }

    private function meaningfulTitle(string $title): bool
    {
        $normalized = $this->key($title);

        return $normalized !== '' && ! in_array($normalized, [
            'ridna vira', 'ridnavira', 'рідна віра', 'статті', 'стаття', 'матеріал',
            'література', 'джерела', 'примітки', 'головна', 'на головну',
        ], true);
    }

    private function key(string $text): string
    {
        return mb_strtolower($this->text(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text));
    }

    private function text(string $text): string
    {
        return trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $text) ?? $text);
    }

    private function children(DOMNode $node, string $title, bool $dataTable = false): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $this->render($child, $title, $dataTable);
        }

        return $html;
    }

    private function render(DOMNode $node, string $title, bool $dataTable): string
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return htmlspecialchars($node->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (! $node instanceof DOMElement) {
            return '';
        }
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['head', 'script', 'style', 'noscript', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button', 'select', 'textarea', 'nav', 'header', 'footer', 'link', 'meta', 'base', 'hr'], true)) {
            return '';
        }
        $text = $this->text($node->textContent);
        if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p'], true)) {
            if ($this->key($text) === $this->key($title) || preg_match('/^(?:https?:\/\/)?(?:www\.)?svit\.in\.ua\/?$/i', $text)) {
                return '';
            }
            if (in_array($this->key($text), ['ridna vira', 'рідна віра', 'на головну', 'до змісту', 'повернутися'], true)) {
                return '';
            }
        }
        if ($tag === 'table') {
            $isData = $this->isDataTable($node);
            $inside = $this->children($node, $title, $isData);

            return $isData ? '<div class="article-table"><table>'.$inside.'</table></div>' : $inside;
        }
        if (in_array($tag, ['thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption'], true)) {
            $inside = $this->children($node, $title, $dataTable);
            if (! $dataTable) {
                if (in_array($tag, ['td', 'th'], true) && ! preg_match('/<(?:p|h[2-4]|ul|ol|blockquote|div)\b/', $inside) && trim(strip_tags($inside)) !== '') {
                    return '<p>'.trim($inside).'</p>';
                }

                return $inside."\n";
            }
            $span = '';
            foreach (['colspan', 'rowspan'] as $attribute) {
                $value = (int) $node->getAttribute($attribute);
                if (in_array($tag, ['td', 'th'], true) && $value > 1 && $value < 50) {
                    $span .= ' '.$attribute.'="'.$value.'"';
                }
            }

            return '<'.$tag.$span.'>'.$inside.'</'.$tag.'>';
        }
        if ($tag === 'img') {
            $src = $this->safeUrl($node->getAttribute('src'));
            $height = (int) $node->getAttribute('height');
            if ($src === '' || ($height > 0 && $height <= 32)) {
                return '';
            }

            return '<img src="'.e($src).'" alt="'.e($node->getAttribute('alt')).'" loading="lazy" decoding="async">';
        }
        $inside = $this->children($node, $title, $dataTable);
        if (in_array($tag, ['div', 'center', 'section'], true) && ! preg_match('/<(?:p|h[2-4]|ul|ol|blockquote|div|table)\b/', $inside) && $text !== '') {
            return '<p>'.trim($inside).'</p>';
        }
        if ($tag === 'a') {
            $href = $this->safeUrl($node->getAttribute('href'));

            return $href !== '' ? '<a href="'.e($href).'">'.$inside.'</a>' : $inside;
        }
        if ($tag === 'br') {
            return '<br>';
        }
        if ($tag === 'p') {
            if ($text === '' && ! str_contains($inside, '<img')) {
                return '';
            }
            $parts = preg_split('/(?:<br>\s*){2,}/i', $inside) ?: [$inside];
            $paragraphs = [];
            foreach ($parts as $part) {
                $lines = explode('<br>', $part);
                // Prose paragraphs are often separated by a single BR in the legacy files;
                // keep consecutive verse lines together even inside a long prose article.
                if (count($lines) === 1 || preg_match('/^\s*\d+[.)]/u', strip_tags($part))) {
                    $paragraphs[] = $part;

                    continue;
                }
                $shortLines = [];
                foreach ($lines as $line) {
                    if (mb_strlen(strip_tags($line)) > 180 && ! preg_match('/^\s*\d+[.)]/u', strip_tags($line))) {
                        if ($shortLines !== []) {
                            $paragraphs[] = implode('<br>', $shortLines);
                            $shortLines = [];
                        }
                        $paragraphs[] = $line;
                    } else {
                        $shortLines[] = $line;
                    }
                }
                if ($shortLines !== []) {
                    $paragraphs[] = implode('<br>', $shortLines);
                }
            }

            return implode('', array_map(static fn (string $part): string => '<p>'.trim($part).'</p>', array_filter($paragraphs, static fn (string $part): bool => trim(strip_tags($part)) !== '' || str_contains($part, '<img'))));
        }
        if ($tag === 'blockquote' && (mb_strlen($text) > 3000 || $node->getElementsByTagName('p')->length > 4 || $node->getElementsByTagName('h1')->length + $node->getElementsByTagName('h2')->length > 0)) {
            return $inside;
        }
        $tag = match ($tag) {
            'h1' => 'h2', 'h5', 'h6' => 'h3', 'b' => 'strong', 'i' => 'em',
            default => $tag,
        };
        if (in_array($tag, ['h2', 'h3', 'h4', 'strong', 'em', 'u', 's', 'sup', 'sub', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'figure', 'figcaption'], true)) {
            return '<'.$tag.'>'.$inside.'</'.$tag.'>';
        }

        return $inside;
    }

    private function isDataTable(DOMElement $table): bool
    {
        if ($table->getElementsByTagName('table')->length > 0 || $table->getElementsByTagName('h1')->length + $table->getElementsByTagName('h2')->length > 0) {
            return false;
        }
        if ($table->getElementsByTagName('th')->length > 0) {
            return true;
        }
        $rowsWithCells = 0;
        foreach ($table->getElementsByTagName('tr') as $row) {
            $cells = 0;
            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array(strtolower($cell->tagName), ['td', 'th'], true) && $this->text($cell->textContent) !== '') {
                    if (mb_strlen($cell->textContent) > 1200) {
                        return false;
                    }
                    $cells++;
                }
            }
            if ($cells >= 2) {
                $rowsWithCells++;
            }
        }

        return $rowsWithCells >= 2;
    }

    private function removePrintedPageNumbers(DOMNode $root): void
    {
        $xpath = new DOMXPath($root->ownerDocument);
        $pages = [];
        foreach ($xpath->query('.//p', $root) ?: [] as $paragraph) {
            if (preg_match('/^\d{1,4}$/', $this->text($paragraph->textContent))) {
                $pages[] = $paragraph;
            }
        }
        // A consecutive series identifies printed pagination, not isolated numeric content.
        if (count($pages) < 3) {
            return;
        }
        for ($index = 1; $index < count($pages); $index++) {
            if ((int) $pages[$index]->textContent !== (int) $pages[$index - 1]->textContent + 1) {
                return;
            }
        }
        foreach ($pages as $page) {
            $before = $page->previousSibling;
            while ($before !== null && ! $before instanceof DOMElement) {
                $before = $before->previousSibling;
            }
            $after = $page->nextSibling;
            while ($after !== null && ! $after instanceof DOMElement) {
                $after = $after->nextSibling;
            }
            if ($before instanceof DOMElement && $after instanceof DOMElement && $before->tagName === 'p' && $after->tagName === 'p'
                && preg_match('/[\p{L},]$/u', $this->text($before->textContent)) && preg_match('/^\p{Ll}/u', $this->text($after->textContent))) {
                $before->appendChild($root->ownerDocument->createTextNode(' '));
                while ($after->firstChild !== null) {
                    $before->appendChild($after->firstChild);
                }
                $after->parentNode->removeChild($after);
            }
            $page->parentNode?->removeChild($page);
        }
    }

    private function safeUrl(string $url): string
    {
        $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/[\x00-\x20\\\\]/', $url)) {
            return '';
        }
        if (str_starts_with($url, '#') || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) {
            return $url;
        }

        return preg_match('/^(?:https?:\/\/|mailto:|tel:)/i', $url) ? $url : '';
    }
}
