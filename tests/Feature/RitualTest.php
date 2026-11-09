<?php

namespace Tests\Feature;

use App\Support\RitualReading;
use DOMDocument;
use Tests\TestCase;

class RitualTest extends TestCase
{
    private function catalogue(): array
    {
        return json_decode(file_get_contents(resource_path('content/rituals/catalogue.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_catalogue_contains_all_rituals_literature_contacts_and_videos(): void
    {
        $this->withoutVite();
        $catalogue = $this->catalogue();
        $this->assertCount(33, $catalogue['entries']);
        $this->assertCount(17, array_filter($catalogue['entries'], fn ($entry) => $entry['category'] === 'literature'));
        $this->assertCount(6, $catalogue['guides']);
        $this->assertCount(5, $catalogue['videos']);
        $response = $this->get(route('faith.rituals'))->assertOk()->assertDontSee('Розділ у розробці');
        foreach ($catalogue['entries'] as $slug => $entry) {
            $response->assertSee($entry['title'])->assertSee(route('faith.rituals.show', $slug));
        }
        $response->assertSee('tel:+380686439328', false)->assertSee('mailto:jaromirdp@gmail.com', false);
    }

    public function test_every_page_opens_locally_and_preserves_reading_content(): void
    {
        $this->withoutVite();
        foreach ($this->catalogue()['entries'] as $slug => $entry) {
            $response = $this->get(route('faith.rituals.show', $slug))->assertOk()->assertSee($entry['title']);
            if (isset($entry['page'])) {
                $content = file_get_contents(resource_path('content/rituals/'.$entry['page']));
                $this->assertGreaterThan(500, mb_strlen(strip_tags($content)));
                $this->assertDoesNotMatchRegularExpression('/<(?:script|iframe|style|font|form|table)\b|\s(?:style|onclick|bgcolor)=/i', $content);
                $this->assertStringNotContainsString('До розділу', $content);
                $response->assertSee((new RitualReading)->format($content, $slug), false);
            } else {
                $this->assertNotEmpty($entry['files']);
                $response->assertSee('Завантажити PDF');
            }
        }
    }

    public function test_local_images_links_and_documents_are_complete(): void
    {
        $this->withoutVite();
        foreach ($this->catalogue()['entries'] as $slug => $entry) {
            $formats = array_column($entry['files'], 'format');
            $this->assertSame($formats, array_values(array_unique($formats)), $slug);
            foreach ($entry['files'] as $file) {
                $path = public_path('assets/rituals/'.$file['path']);
                $this->assertSame($file['bytes'], filesize($path));
                $this->assertStringStartsWith($file['format'] === 'pdf' ? '%PDF' : 'PK', file_get_contents($path, false, null, 0, 4));
                $response = $this->get(route('faith.rituals.download', [$slug, $file['format']]))->assertOk();
                $this->assertSame(realpath($path), $response->baseResponse->getFile()->getRealPath());
            }
            if (! isset($entry['page'])) {
                continue;
            }
            $document = new DOMDocument;
            @$document->loadHTML('<?xml encoding="UTF-8">'.file_get_contents(resource_path('content/rituals/'.$entry['page'])));
            foreach ($document->getElementsByTagName('img') as $image) {
                $this->assertStringStartsWith('/assets/rituals/images/', $image->getAttribute('src'));
                $path = public_path(ltrim($image->getAttribute('src'), '/'));
                $this->assertFileExists($path);
                [$width, $height] = getimagesize($path);
                $this->assertSame((string) $width, $image->getAttribute('width'));
                $this->assertSame((string) $height, $image->getAttribute('height'));
                $this->assertSame('lazy', $image->getAttribute('loading'));
            }
            foreach ($document->getElementsByTagName('a') as $link) {
                $href = $link->getAttribute('href');
                if (str_starts_with($href, '/assets/rituals/')) {
                    $this->assertFileExists(public_path(ltrim($href, '/')));
                }
                if (str_starts_with($href, '/ridna-vira/obriady/')) {
                    $this->get(explode('#', $href)[0])->assertOk();
                }
            }
        }
    }

    public function test_unknown_rituals_and_unavailable_formats_return_404(): void
    {
        $this->withoutVite();
        $this->get('/ridna-vira/obriady/missing')->assertNotFound();
        $this->get('/ridna-vira/obriady/missing/zavantazhyty/pdf')->assertNotFound();
        $this->get('/ridna-vira/obriady/bohoslavlennia/zavantazhyty/exe')->assertNotFound();
        $this->get('/ridna-vira/obriady/osviachennia-pomeshkannia/zavantazhyty/pdf')->assertNotFound();
    }
}
