<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class PrayerTest extends TestCase
{
    private function catalogue(): array
    {
        return json_decode(file_get_contents(resource_path('content/prayers/catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_catalogue_and_all_seventeen_pages_open_with_original_content_and_local_routes(): void
    {
        $this->withoutVite();
        $catalogue = $this->catalogue();
        $this->assertCount(17, $catalogue['entries']);
        $this->assertCount(8, array_filter($catalogue['entries'], fn ($e) => $e['category'] === 'themes'));
        $this->assertCount(6, array_filter($catalogue['entries'], fn ($e) => $e['category'] === 'authors'));
        $this->assertCount(12, $catalogue['videos']);
        $index = $this->get(route('faith.prayers'))->assertOk();
        foreach ($catalogue['entries'] as $slug => $entry) {
            $index->assertSee($entry['title'])->assertSee(route('faith.prayers.show', $slug));
            $response = $this->get(route('faith.prayers.show', $slug))->assertOk()->assertSee($entry['title']);
            $response->assertDontSee('<iframe', false)->assertDontSee('oru.org.ua', false);
            if (isset($entry['page'])) {
                $blocks = json_decode(file_get_contents(resource_path('content/prayers/'.$entry['page'])), true);
                $text = implode('', array_map(fn ($b) => $b['title'] ?? $b['html'], $blocks));
                $text = preg_replace('/[\s\x{00a0}]+/u', '', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $this->assertSame($entry['source_text_fingerprint'], hash('sha256', $text), $slug);
                if (in_array($entry['category'], ['themes', 'authors'])) {
                    $response->assertSee('ritual-passage--prayer', false);
                }
                foreach ($entry['toc'] as $heading) {
                    $response->assertSee('id="'.$heading['anchor'].'"', false);
                }
            } else {
                $response->assertSee('Завантажити PDF');
            }
        }
    }

    public function test_local_files_audio_images_and_reading_markup_are_valid(): void
    {
        $this->withoutVite();
        foreach ($this->catalogue()['entries'] as $slug => $entry) {
            foreach ($entry['files'] as $file) {
                $path = public_path('assets/prayers/'.$file['path']);
                $this->assertSame($file['bytes'], filesize($path));
                $this->assertStringStartsWith($file['format'] === 'pdf' ? '%PDF' : 'AT&TFORM', file_get_contents($path, false, null, 0, 8));
                $response = $this->get(route('faith.prayers.download', [$slug, $file['format']]))->assertOk();
                $this->assertSame(realpath($path), $response->baseResponse->getFile()->getRealPath());
            }
            foreach ($entry['audio'] as $audio) {
                $this->assertSame($audio['bytes'], filesize(public_path('assets/prayers/'.$audio['path'])));
            }
            if (! isset($entry['page'])) {
                continue;
            }
            $blocks = json_decode(file_get_contents(resource_path('content/prayers/'.$entry['page'])), true);
            $html = implode('', array_column($blocks, 'html'));
            $this->assertDoesNotMatchRegularExpression('/<(?:script|iframe|style|font|form)\b|\s(?:style|onclick|bgcolor)=/i', $html);
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET);
            foreach ($document->getElementsByTagName('img') as $image) {
                $this->assertStringStartsWith('/assets/prayers/images/', $image->getAttribute('src'));
                $path = public_path(ltrim($image->getAttribute('src'), '/'));
                [$width, $height] = getimagesize($path);
                $this->assertSame((string) $width, $image->getAttribute('width'));
                $this->assertSame((string) $height, $image->getAttribute('height'));
                $this->assertSame('lazy', $image->getAttribute('loading'));
            }
            foreach ($document->getElementsByTagName('a') as $link) {
                $href = $link->getAttribute('href');
                if (str_starts_with($href, '/assets/prayers/')) {
                    $this->assertFileExists(public_path(ltrim($href, '/')));
                }
                if (str_starts_with($href, '/ridna-vira/')) {
                    $this->get($href)->assertOk();
                }
            }
        }
        $response = $this->get(route('faith.prayers.show', 'svitovyt-pashnyk'))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML($response->getContent());
        $audio = (new DOMXPath($document))->query('//audio[@preload="none"]');
        $this->assertCount(2, $audio);
    }

    public function test_explanations_remain_separate_from_prayers_and_historical_spells(): void
    {
        $this->withoutVite();
        $medical = $this->get(route('faith.prayers.show', 'likuvalni'))->assertOk();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$medical->getContent());
        $xpath = new DOMXPath($document);
        $prayers = implode(' ', array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('//div[contains(@class,"ritual-passage--prayer")]'))));
        $this->assertStringNotContainsString('Лікування молитвою чи змовлянням є допоміжним заходом', $prayers);
        $medical->assertSee('Варто сходити до лікаря');
        $this->get(route('faith.prayers.show', 'urochysti'))->assertOk()->assertSee('ritual-passage--praise', false);
        $this->get(route('faith.prayers.show', 'struktura-zamovliannia'))->assertOk()->assertDontSee('ritual-passage--prayer"', false);
        $this->get(route('faith.prayers.show', 'ukrainski-zamovliannia'))->assertOk()->assertSee('Замовляння · текст видання')->assertSee('Коментар');
    }

    public function test_unknown_pages_and_unavailable_downloads_return_404(): void
    {
        $this->withoutVite();
        $this->get('/ridna-vira/molytvy/missing')->assertNotFound();
        $this->get('/ridna-vira/molytvy/missing/zavantazhyty/pdf')->assertNotFound();
        $this->get('/ridna-vira/molytvy/rodynni/zavantazhyty/pdf')->assertNotFound();
        $this->get('/ridna-vira/molytvy/molytovnyk/zavantazhyty/exe')->assertNotFound();
    }
}
