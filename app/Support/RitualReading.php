<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/** Apply reviewed editorial annotations; never infer prayers from keyword matches at runtime. */
final class RitualReading
{
    private const LABELS = [
        'prayer' => 'Молитва',
        'praise' => 'Славлення',
        'song' => 'Пісня',
        'direction' => 'Обрядові дії',
        'oath' => 'Присяга',
    ];

    public function format(string $html, string $slug): string
    {
        $annotations = json_decode(file_get_contents(resource_path('content/rituals/typography.json')), true, 512, JSON_THROW_ON_ERROR)[$slug] ?? null;
        if ($annotations === null) {
            return $html;
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($document);
        $paragraphs = iterator_to_array($xpath->query('//body//p'));
        $units = [];
        $lines = [];
        foreach ($paragraphs as $index => $paragraph) {
            $lines[$index] = $this->lines($paragraph);
            foreach ($lines[$index] as $line) {
                $text = $this->text($line);
                if ($text !== '') {
                    $units[] = $text;
                }
            }
        }
        // A later import must be reviewed again before positional annotations can be reused.
        if (hash('sha256', implode("\n", $units)) !== $annotations['fingerprint']) {
            return $html;
        }
        $types = [];
        foreach ($annotations['ranges'] as [$kind, $start, $end]) {
            for ($index = $start; $index <= $end; $index++) {
                $types[$index] = $kind;
            }
        }
        $unit = 0;
        foreach ($paragraphs as $index => $paragraph) {
            $runs = [];
            foreach ($lines[$index] as $line) {
                $kind = $this->text($line) === '' ? 'text' : ($types[$unit++] ?? 'text');
                $last = count($runs) - 1;
                if ($last >= 0 && $runs[$last]['kind'] === $kind) {
                    $runs[$last]['html'] .= '<br>'.$line;
                } else {
                    $runs[] = ['kind' => $kind, 'html' => $line];
                }
            }
            foreach ($runs as $run) {
                if ($this->text($run['html']) === '' && ! str_contains($run['html'], '<img')) {
                    continue;
                }
                $p = $document->createElement('p');
                $fragment = new DOMDocument('1.0', 'UTF-8');
                @$fragment->loadHTML('<?xml encoding="UTF-8"><body>'.$run['html'].'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                foreach ($fragment->getElementsByTagName('body')->item(0)->childNodes as $child) {
                    $p->appendChild($document->importNode($child, true));
                }
                if (isset(self::LABELS[$run['kind']])) {
                    $block = $document->createElement('div');
                    $block->setAttribute('class', 'ritual-passage ritual-passage--'.$run['kind']);
                    $block->setAttribute('role', 'group');
                    $block->setAttribute('aria-label', self::LABELS[$run['kind']]);
                    $label = $document->createElement('p', self::LABELS[$run['kind']]);
                    $label->setAttribute('class', 'ritual-passage__label');
                    $block->appendChild($label);
                    $block->appendChild($p);
                    $paragraph->parentNode->insertBefore($block, $paragraph);
                } else {
                    $paragraph->parentNode->insertBefore($p, $paragraph);
                }
            }
            $paragraph->parentNode->removeChild($paragraph);
        }
        // Existing blockquotes include mixed stage directions; remove their blanket styling.
        foreach (iterator_to_array($xpath->query('//blockquote')) as $quote) {
            while ($quote->firstChild !== null) {
                $quote->parentNode->insertBefore($quote->firstChild, $quote);
            }
            $quote->parentNode->removeChild($quote);
        }
        foreach (array_reverse(iterator_to_array($xpath->query('//div[contains(@class, "ritual-passage--")]'))) as $block) {
            $next = $block->nextSibling;
            while ($next && $next->nodeType === XML_TEXT_NODE && trim($next->textContent) === '') {
                $next = $next->nextSibling;
            }
            if ($next instanceof DOMElement && $next->getAttribute('class') === $block->getAttribute('class')) {
                $next->removeChild($next->firstChild);
                while ($next->firstChild !== null) {
                    $block->appendChild($next->firstChild);
                }
                $next->parentNode->removeChild($next);
            }
        }
        $result = '';
        foreach ($document->getElementsByTagName('body')->item(0)->childNodes as $node) {
            $result .= $document->saveHTML($node);
        }

        return $result;
    }

    private function text(string $html): string
    {
        return trim(preg_replace('/[\s\x{00a0}]+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /** Split at BR while keeping inline markup valid on both sides. */
    private function lines(DOMNode $node): array
    {
        $lines = [''];
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $lines[array_key_last($lines)] .= htmlspecialchars($child->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } elseif ($child instanceof DOMElement && $child->tagName === 'br') {
                $lines[] = '';
            } elseif ($child instanceof DOMElement && $child->tagName === 'img') {
                $lines[array_key_last($lines)] .= $child->ownerDocument->saveHTML($child);
            } elseif ($child instanceof DOMElement) {
                $attributes = '';
                foreach ($child->attributes as $attribute) {
                    $attributes .= ' '.$attribute->name.'="'.htmlspecialchars($attribute->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"';
                }
                foreach ($this->lines($child) as $index => $inside) {
                    if ($index > 0) {
                        $lines[] = '';
                    }
                    $lines[array_key_last($lines)] .= '<'.$child->tagName.$attributes.'>'.$inside.'</'.$child->tagName.'>';
                }
            }
        }

        return $lines;
    }
}
