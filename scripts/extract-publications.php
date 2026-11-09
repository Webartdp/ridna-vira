<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$document = new DOMDocument('1.0', 'UTF-8');
libxml_use_internal_errors(true);
$document->loadHTML('<?xml encoding="UTF-8">'.preg_replace('/<meta\b[^>]*>/i', '', $input['html']), LIBXML_NONET);
$xpath = new DOMXPath($document);
$plain = static fn ($node) => trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $node->textContent));
$entries = [];
if ($input['mode'] === 'news') {
    $current = null;
    foreach ($xpath->query('//p | //iframe') as $node) {
        $text = $plain($node);
        $class = $node instanceof DOMElement ? $node->getAttribute('class') : '';
        $compact = preg_replace('/\s+/u', '', $text);
        if ($class === 'cale' && preg_match('/(\d{1,2})\.(\d{1,2})\.\d{4}\((\d{4})\)/u', $compact, $match)) {
            if ($current !== null) {
                $entries[] = $current;
            }
            $current = ['date' => sprintf('%04d-%02d-%02d', $match[3], $match[2], $match[1]), 'source_date' => $text, 'location' => '', 'title' => '', 'html' => '', 'videos' => []];

            continue;
        }
        if ($current === null) {
            continue;
        }
        if ($node->nodeName === 'iframe') {
            if (preg_match('~youtube\.com/embed/([a-zA-Z0-9_-]{11})~', $node->getAttribute('src'), $match)) {
                $current['videos'][] = $match[1];
            }

            continue;
        }
        if ($class === 'cale' && $text !== '') {
            $current['location'] = $text;

            continue;
        }
        if (str_contains($class, 'zagolovok') && $current['title'] === '' && $text !== '') {
            $current['title'] = $text;
            foreach ($node->getElementsByTagName('img') as $image) {
                $current['html'] .= '<p>'.$document->saveHTML($image).'</p>'."\n";
            }
        }
        if ($text === '* * *' || str_contains($text, 'Архів новин') || $class === 'rubrika') {
            continue;
        }
        if ($text !== '' || $node->getElementsByTagName('img')->length) {
            $current['html'] .= $document->saveHTML($node)."\n";
        }
    }
    if ($current !== null) {
        $entries[] = $current;
    }
} else {
    $category = '';
    $author = '';
    $seen = [];
    foreach ($xpath->query('//p') as $node) {
        $text = $plain($node);
        if ($node->getAttribute('class') === 'rubrika') {
            $category = trim(str_replace('►', '', $text));
            $author = '';
        }
        if ($node->getAttribute('class') === 'zagolovokL' && $text !== '' && $node->getElementsByTagName('a')->length === 0) {
            $author = $text;
        }
        foreach ($node->getElementsByTagName('a') as $link) {
            $href = $link->getAttribute('href');
            if (! preg_match('~^(tvor|stat)/.+\.htm$~i', $href) || isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;
            $label = trim(preg_replace('/^[-–]\s*/u', '', $plain($link)));
            if (in_array($label, ['ІІ', 'ІІІ'], true)) {
                $label = 'Ігри '.$label;
            }
            $title = $author !== '' ? $author.' — '.$label : $label;
            if ($label === 'Колекція') {
                $title = $category.' — колекція';
            }
            $entries[] = ['source' => $href, 'title' => $title, 'author' => $author, 'category' => $category];
        }
    }
}
echo json_encode($entries, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
