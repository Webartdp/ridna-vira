<?php

declare(strict_types=1);
use App\Support\RitualReading;

require dirname(__DIR__).'/vendor/autoload.php';

$document = new DOMDocument('1.0', 'UTF-8');
libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="UTF-8"><body>'.stream_get_contents(STDIN).'</body>', LIBXML_NONET);
$reader = new RitualReading;
$blocks = [];
$walk = function (DOMNode $node) use (&$walk, &$blocks, $reader): void {
    foreach ($node->childNodes as $child) {
        if ($child instanceof DOMElement && $child->tagName === 'p') {
            $blocks[] = ['type' => 'paragraph', 'lines' => $reader->lines($child)];
        } elseif ($child instanceof DOMElement && in_array($child->tagName, ['h2', 'h3', 'h4'], true)) {
            $blocks[] = ['type' => 'heading', 'title' => trim($child->textContent)];
        } elseif ($child instanceof DOMElement && in_array($child->tagName, ['ul', 'ol', 'table', 'figure', 'pre'], true)) {
            $blocks[] = ['type' => 'prose', 'html' => $child->ownerDocument->saveHTML($child)];
        } elseif ($child instanceof DOMElement) {
            $walk($child);
        } elseif ($child->nodeType === XML_TEXT_NODE && trim($child->textContent) !== '') {
            $blocks[] = ['type' => 'paragraph', 'lines' => [htmlspecialchars($child->textContent, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')]];
        }
    }
};
$walk($document->getElementsByTagName('body')->item(0));
echo json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
