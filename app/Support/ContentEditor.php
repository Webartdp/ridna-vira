<?php

namespace App\Support;

final class ContentEditor
{
    public static function prepare(array $data): array
    {
        if (($data['path'] ?? '') === '/pro-tsentr' && ($data['editor_mode'] ?? '') === 'layout') {
            $data['body'] = AboutGallery::refreshLayout($data['body'] ?? '');
        }
        $data = ContentImages::prepare($data);
        if (($data['section'] ?? 'pages') === 'pages' || ($data['editor_mode'] ?? 'reading') !== 'layout') {
            return $data;
        }
        $html = (string) ($data['body'] ?? '');
        $body = Cms::fragment($html);
        if ($body === null) {
            $document = Cms::document($html);
            $article = $document->getElementsByTagName('article')->item(0);
            if ($article) {
                foreach (iterator_to_array($article->childNodes) as $node) {
                    if ($node instanceof \DOMElement && in_array($node->tagName, ['header', 'footer', 'nav'], true)) {
                        $article->removeChild($node);
                    }
                }
                $body = implode('', array_map(fn ($child) => $document->saveHTML($child), iterator_to_array($article->childNodes)));
            } else {
                // A legacy record without a known wrapper remains editable without showing navigation as content.
                $document = Cms::document($html);
                foreach (iterator_to_array((new \DOMXPath($document))->query('//nav | //header | //footer | //h1')) as $node) {
                    $node->parentNode?->removeChild($node);
                }
                $body = implode('', array_map(fn ($node) => $document->saveHTML($node), iterator_to_array($document->getElementById('cms-root')->childNodes)));
            }
        }
        $data['body'] = ($data['section'] ?? '') === 'documents' ? CmsHtml::clean(DocumentContent::clean($body, legacyOnly: true)) : CmsHtml::clean($body);
        $data['editor_mode'] = 'fragment';
        if (! ($data['is_override'] ?? false)) {
            $data['excerpt'] = (new ArticleContent)->excerpt($data['body']);
        }

        return $data;
    }
}
