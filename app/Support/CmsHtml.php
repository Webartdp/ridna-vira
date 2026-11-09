<?php

namespace App\Support;

use App\Filament\Blocks\ReadingPassage;
use DOMDocument;
use DOMElement;

final class CmsHtml
{
    public static function forEditor(string $html): string
    {
        if (! str_contains($html, 'ritual-passage--')) {
            return $html;
        }
        $document = Cms::document($html);
        $xpath = new \DOMXPath($document);
        foreach (iterator_to_array($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," ritual-passage ")]')) as $node) {
            if (! preg_match('/ritual-passage--([a-z]+)/', $node->getAttribute('class'), $match) || ! isset(ReadingPassage::labels()[$match[1]])) {
                continue;
            }
            foreach (iterator_to_array($node->getElementsByTagName('p')) as $paragraph) {
                if (str_contains($paragraph->getAttribute('class'), 'ritual-passage__label')) {
                    $paragraph->parentNode->removeChild($paragraph);
                }
            }
            $source = implode('', array_map(fn ($child) => $document->saveHTML($child), iterator_to_array($node->childNodes)));
            $text = trim(html_entity_decode(strip_tags(preg_replace('~<br\s*/?>|</p>~i', "\n", $source)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $block = $document->createElement('div');
            $block->setAttribute('data-type', 'customBlock');
            $block->setAttribute('data-id', 'reading-passage');
            $block->setAttribute('data-config', json_encode(['kind' => $match[1], 'text' => $text, 'html' => $source], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            $node->parentNode->replaceChild($block, $node);
        }

        return implode('', array_map(fn ($node) => $document->saveHTML($node), iterator_to_array($document->getElementById('cms-root')->childNodes)));
    }

    public static function render(string $html): string
    {
        if (str_contains($html, 'customBlock')) {
            $document = Cms::document($html);
            foreach (iterator_to_array($document->getElementsByTagName('div')) as $node) {
                if ($node->getAttribute('data-type') !== 'customBlock' || $node->getAttribute('data-id') !== 'reading-passage') {
                    continue;
                }
                $config = json_decode($node->getAttribute('data-config'), true);
                $replacement = Cms::document(ReadingPassage::toHtml(is_array($config) ? $config : [], []));
                $node->parentNode?->replaceChild($document->importNode($replacement->getElementById('cms-root')->firstChild, true), $node);
            }
            $html = implode('', array_map(fn ($node) => $document->saveHTML($node), iterator_to_array($document->getElementById('cms-root')->childNodes)));
        }

        return self::clean($html);
    }

    public static function clean(string $html, bool $layout = false): string
    {
        if ($html === '') {
            return '';
        }
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div id="cms-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        foreach (iterator_to_array($dom->getElementsByTagName('*')) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }
            if (in_array(strtolower($element->tagName), ['script', 'style', 'iframe', 'object', 'embed', 'base', 'meta', 'link', 'form', 'input', 'button', 'textarea', 'select', 'svg', 'math'], true)) {
                $element->parentNode?->removeChild($element);

                continue;
            }
            foreach (iterator_to_array($element->attributes) as $attribute) {
                $name = strtolower($attribute->name);
                $value = html_entity_decode(trim($attribute->value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (str_starts_with($name, 'on') || in_array($name, ['srcdoc', 'style', 'formaction', 'action', 'xmlns'], true)
                    || (in_array($name, ['href', 'src', 'poster', 'xlink:href'], true) && ! preg_match('~^(https?://|/(?!/)|#|mailto:|tel:)~i', $value))
                    || $name === 'srcset') {
                    $element->removeAttribute($attribute->name);
                }
            }
            if ($element->tagName === 'img') {
                $element->setAttribute('loading', 'lazy');
                $element->setAttribute('decoding', 'async');
            }
            if ($element->tagName === 'a' && $element->getAttribute('target') === '_blank') {
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }
        $root = $dom->getElementById('cms-root');
        $result = '';
        foreach ($root?->childNodes ?? [] as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }
}
