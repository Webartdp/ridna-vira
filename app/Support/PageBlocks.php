<?php

namespace App\Support;

use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Storage;

final class PageBlocks
{
    public static function fields(string $html): array
    {
        $document = Cms::document($html);
        $xpath = new DOMXPath($document);
        $fields = [];
        $covered = [];
        foreach ($xpath->query('//div | //section | //article | //header | //p | //h2 | //h3 | //h4 | //img | //a') as $node) {
            if ($xpath->query('ancestor::nav | ancestor::form', $node)->length || str_contains($node->textContent, '[[live:')) {
                continue;
            }
            $skip = false;
            for ($parent = $node->parentNode; $parent; $parent = $parent->parentNode) {
                if (in_array($parent, $covered, true)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }
            $kind = null;
            $value = '';
            $label = '';
            if (in_array($node->tagName, ['div', 'section', 'article', 'header'], true)) {
                $children = array_values(array_filter(iterator_to_array($node->childNodes), fn ($child) => $child instanceof DOMElement));
                $flow = ['p', 'h2', 'h3', 'h4', 'h5', 'ul', 'ol', 'blockquote', 'figure', 'table'];
                if (! $children || ! array_filter($children, fn ($child) => $child->tagName === 'p') || array_filter($children, fn ($child) => ! in_array($child->tagName, $flow, true))) {
                    continue;
                }
                $kind = 'rich';
                $value = implode('', array_map(fn ($child) => $document->saveHTML($child), iterator_to_array($node->childNodes)));
                $covered[] = $node;
                $label = 'Текст: '.mb_substr(trim($node->textContent), 0, 85);
            } elseif ($node->tagName === 'p') {
                $kind = 'paragraph';
                $value = $document->saveHTML($node);
                $covered[] = $node;
                $label = 'Текст: '.mb_substr(trim($node->textContent), 0, 85);
            } elseif (in_array($node->tagName, ['h2', 'h3', 'h4'], true)) {
                $kind = 'heading';
                $value = trim($node->textContent);
                $label = 'Заголовок: '.$value;
                $covered[] = $node;
            } elseif ($node->tagName === 'a') {
                $kind = 'link';
                $value = $node->getAttribute('href');
                $label = 'Посилання: '.mb_substr(trim($node->textContent), 0, 85);
            } elseif ($node->tagName === 'img') {
                $value = $node->getAttribute('src');
                if (preg_match('~(?:ornament|texture|star|footer-line)~i', $value)) {
                    continue;
                }
                $kind = 'image';
                $label = 'Зображення: '.($node->getAttribute('alt') ?: basename(parse_url($value, PHP_URL_PATH) ?: $value));
            }
            if ($kind && ($kind === 'image' || trim(strip_tags($value)) !== '' || str_contains($value, '<img'))) {
                $fields[] = ['key' => $node->getNodePath(), 'kind' => $kind, 'label' => $label, 'value' => $value];
            }
        }

        return $fields;
    }

    public static function apply(string $html, array $fields): string
    {
        $document = Cms::document($html);
        $xpath = new DOMXPath($document);
        $targets = [];
        foreach ($fields as $field) {
            $nodes = is_string($field['key'] ?? null) ? @$xpath->query($field['key']) : false;
            $targets[] = [$field, $nodes ? $nodes->item(0) : null];
        }
        foreach ($targets as [$field, $node]) {
            if (! $node instanceof DOMElement) {
                continue;
            }
            $value = is_string($field['value'] ?? null) ? $field['value'] : '';
            $kind = $field['kind'] ?? '';
            if ($kind === 'heading') {
                $node->textContent = $value;
            } elseif (in_array($kind, ['image', 'link'], true)) {
                if ($kind === 'image' && is_string($field['replacement'] ?? null) && $field['replacement'] !== '') {
                    $value = Storage::disk('public')->url($field['replacement']);
                }
                $node->setAttribute($kind === 'image' ? 'src' : 'href', $value);
            } elseif (in_array($kind, ['rich', 'paragraph'], true)) {
                $source = Cms::document(CmsHtml::clean($value));
                $children = iterator_to_array($source->getElementById('cms-root')->childNodes);
                if ($kind === 'paragraph') {
                    foreach ($children as $index => $child) {
                        $copy = $document->importNode($child, true);
                        if ($index === 0 && $copy instanceof DOMElement && $copy->tagName === 'p') {
                            foreach ($node->attributes as $attribute) {
                                $copy->setAttribute($attribute->name, $attribute->value);
                            }
                        }
                        $node->parentNode->insertBefore($copy, $node);
                    }
                    $node->parentNode->removeChild($node);
                } else {
                    while ($node->firstChild) {
                        $node->removeChild($node->firstChild);
                    }
                    foreach ($children as $child) {
                        $node->appendChild($document->importNode($child, true));
                    }
                }
            }
        }

        return implode('', array_map(fn ($node) => $document->saveHTML($node), iterator_to_array($document->getElementById('cms-root')->childNodes)));
    }
}
