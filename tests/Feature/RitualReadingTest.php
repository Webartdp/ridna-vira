<?php

namespace Tests\Feature;

use App\Support\RitualReading;
use DOMDocument;
use DOMXPath;
use Tests\TestCase;

class RitualReadingTest extends TestCase
{
    public function test_all_sixteen_reviewed_rituals_keep_every_word_image_and_heading_anchor(): void
    {
        $annotations = json_decode(file_get_contents(resource_path('content/rituals/typography.json')), true);
        $this->assertCount(16, $annotations);
        foreach (array_keys($annotations) as $slug) {
            $original = file_get_contents(resource_path('content/rituals/pages/'.$slug.'.html'));
            $formatted = (new RitualReading)->format($original, $slug);
            $this->assertStringContainsString('ritual-passage--prayer', $formatted, $slug);
            $before = $this->document($original);
            $after = $this->document($formatted);
            $xpath = new DOMXPath($after);
            foreach (iterator_to_array($xpath->query('//p[@class="ritual-passage__label"]')) as $label) {
                $label->parentNode->removeChild($label);
            }
            $this->assertSame(
                preg_replace('/[\s\x{00a0}]+/u', '', $before->getElementsByTagName('body')->item(0)->textContent),
                preg_replace('/[\s\x{00a0}]+/u', '', $after->getElementsByTagName('body')->item(0)->textContent),
                $slug,
            );
            $this->assertSame($before->getElementsByTagName('img')->length, $after->getElementsByTagName('img')->length, $slug);
            $ids = static function (DOMDocument $document): array {
                $result = [];
                foreach ((new DOMXPath($document))->query('//*[@id]') as $node) {
                    $result[] = $node->getAttribute('id');
                }

                return $result;
            };
            $this->assertSame($ids($before), $ids($after), $slug);
        }
    }

    public function test_mixed_paragraphs_separate_stage_directions_from_prayer(): void
    {
        $source = file_get_contents(resource_path('content/rituals/pages/bohoslavlennia.html'));
        $document = $this->document((new RitualReading)->format($source, 'bohoslavlennia'));
        $xpath = new DOMXPath($document);
        $prayers = implode(' ', array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('//div[contains(@class,"ritual-passage--prayer")]'))));
        $directions = implode(' ', array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('//div[contains(@class,"ritual-passage--direction")]'))));
        $this->assertStringContainsString('Кличу Землю-Землицю', $prayers);
        $this->assertStringContainsString('Боже Правий, Боже Явий, Боже Навий!', $prayers);
        $this->assertStringNotContainsString('(торкнутися Землі)', $prayers);
        $this->assertStringContainsString('(торкнутися Землі)', $directions);
        $this->assertStringContainsString('Повторюйте за мною:', $directions);
        $this->assertStringNotContainsString('На острові Хортиця є святилище-обсерваторія', $prayers);
    }

    public function test_unreviewed_changed_text_and_research_articles_keep_their_original_content(): void
    {
        $reading = new RitualReading;
        $source = file_get_contents(resource_path('content/rituals/pages/bohoslavlennia.html'));
        $changed = str_replace('Кличу Землю-Землицю', 'Новий текст звернення', $source);
        $this->assertSame($changed, $reading->format($changed, 'bohoslavlennia'));
        $article = '<p>Дослідження про молитви та народні звичаї.</p>';
        $this->assertSame($article, $reading->format($article, 'dytyna-zakarpattia'));
    }

    private function document(string $html): DOMDocument
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);

        return $document;
    }
}
