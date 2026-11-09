<?php

declare(strict_types=1);
use App\Support\ArticleContent;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

require dirname(__DIR__).'/vendor/autoload.php';

// Extract the reading cell, never the surrounding legacy navigation and layout.
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$document = new DOMDocument;
libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="UTF-8">'.preg_replace('/<meta\b[^>]*>/i', '', $input['html']), LIBXML_NONET);
$xpath = new DOMXPath($document);
$root = $document->getElementsByTagName('body')->item(0);
$largest = 0;
foreach ($xpath->query('//td') as $cell) {
    $length = mb_strlen(trim($cell->textContent));
    if (! empty($input['gallery'])) {
        foreach ($cell->getElementsByTagName('img') as $image) {
            if (! preg_match('~(?:^|/)(?:rozdil|lin|artic)\.gif$~i', $image->getAttribute('src'))) {
                $length += 1000;
            }
        }
    }
    if ($length > $largest) {
        $root = $cell;
        $largest = $length;
    }
}
foreach (iterator_to_array($xpath->query('.//img', $root)) as $image) {
    if (preg_match('~(?:^|/)(?:rozdil|lin|artic)\.gif$~i', $image->getAttribute('src'))) {
        $image->parentNode->removeChild($image);
    }
}
foreach (iterator_to_array($xpath->query('.//a', $root)) as $link) {
    if (preg_match('/^(До розділу|На головну|До змісту|Повернутися)$/iu', trim($link->textContent))) {
        $link->parentNode->removeChild($link);
    }
}
foreach ($xpath->query('.//a[@href] | .//img[@src]', $root) as $node) {
    $attribute = $node->tagName === 'a' ? 'href' : 'src';
    $url = UriResolver::resolve(
        new Uri($input['source']),
        new Uri($node->getAttribute($attribute)),
    );
    $node->setAttribute($attribute, (string) $url);
}
foreach (iterator_to_array($xpath->query('.//p', $root)) as $paragraph) {
    $text = trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $paragraph->textContent));
    $bold = $xpath->query('.//b | .//strong', $paragraph)->item(0);
    $boldText = $bold ? trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $bold->textContent)) : '';
    $heading = preg_match('/^(Опис богославлення|Хід обряду|Опис обряду|Підготовча робота|Література|Пісні до свята)/iu', $text)
        || ($text === mb_strtoupper($text) && preg_match('/\p{L}/u', $text) && ! preg_match('/[!?]$/u', $text));
    if ($heading && $boldText === $text && mb_strlen($text) > 8 && mb_strlen($text) < 120 && ! $paragraph->getElementsByTagName('br')->length && ! $paragraph->getElementsByTagName('img')->length) {
        $replacement = $document->createElement('h2');
        $replacement->appendChild($document->createTextNode($text));
        $paragraph->parentNode->replaceChild($replacement, $paragraph);
    }
}
$fragment = '';
foreach ($root->childNodes as $child) {
    $fragment .= $document->saveHTML($child);
}
echo (new ArticleContent((bool) ($input['gallery'] ?? false)))->body('<article>'.$fragment.'</article>', $input['title']);
