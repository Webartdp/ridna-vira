<?php

namespace App\Support;

final class EditableLayout
{
    public static function fields(string $html): array
    {
        $document = Cms::document($html);
        $xpath = new \DOMXPath($document);
        $fields = [];
        foreach ($xpath->query('//text()[normalize-space(.) != ""]') as $node) {
            $value = trim($node->textContent);
            if (str_contains($value, '[[live:')) {
                continue;
            }
            $fields[] = ['key' => $node->getNodePath(), 'type' => 'text', 'label' => mb_substr($value, 0, 100), 'value' => $value];
        }
        foreach ($xpath->query('//img/@src | //img/@alt | //a/@href') as $node) {
            $fields[] = ['key' => $node->getNodePath(), 'type' => 'attribute', 'label' => match ($node->nodeName) {
                'src' => 'Зображення', 'alt' => 'Опис зображення', default => 'Посилання'
            }, 'value' => $node->value];
        }

        return $fields;
    }

    public static function apply(string $html, array $fields): string
    {
        $document = Cms::document($html);
        $xpath = new \DOMXPath($document);
        foreach ($fields as $field) {
            if (! is_string($field['key'] ?? null) || ! array_key_exists('value', $field) || (! is_string($field['value']) && $field['value'] !== null)) {
                continue;
            }
            $nodes = @$xpath->query($field['key']);
            $node = $nodes ? $nodes->item(0) : null;
            if ($node instanceof \DOMText || $node instanceof \DOMAttr) {
                $node->nodeValue = (string) $field['value'];
            }
        }

        return implode('', array_map(fn ($node) => $document->saveHTML($node), iterator_to_array($document->getElementById('cms-root')->childNodes)));
    }
}
