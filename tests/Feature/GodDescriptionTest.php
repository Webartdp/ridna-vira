<?php

namespace Tests\Feature;

use App\Support\GodDescriptions;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

class GodDescriptionTest extends TestCase
{
    private string $fixtureStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->fixtureStorage = storage_path('framework/testing/gods-'.bin2hex(random_bytes(6)));
        mkdir($this->fixtureStorage.'/app/content/gods/pages', 0777, true);
        $this->app->useStoragePath($this->fixtureStorage);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->fixtureStorage);
        parent::tearDown();
    }

    public function test_every_catalogue_entry_has_an_individual_description_and_primary_sources(): void
    {
        $entries = (new GodDescriptions)->all();
        $slugs = array_column(config('faith_gods.gods'), 'slug');
        $this->assertEqualsCanonicalizing($slugs, array_keys($entries));
        $this->assertCount(count($entries), array_unique(array_column($entries, 'lead')));
        $hosts = [];
        foreach ($entries as $entry) {
            $this->assertNotEmpty($entry['summary']);
            $this->assertNotEmpty($entry['lead']);
            $this->assertGreaterThanOrEqual(2, count($entry['sections']));
            foreach ($entry['sections'] as $section) {
                $this->assertNotEmpty($section['title']);
                $this->assertNotEmpty($section['paragraphs']);
            }
            $this->assertNotEmpty($entry['sources']);
            foreach ($entry['sources'] as $source) {
                $this->assertNotFalse(filter_var($source['url'], FILTER_VALIDATE_URL));
                $host = parse_url($source['url'], PHP_URL_HOST);
                $this->assertContains($host, ['svit.in.ua', 'www.svit.in.ua', 'yaro.dp.ua', 'www.oru.org.ua']);
                $hosts[] = $host;
            }
        }
        $this->assertContains('yaro.dp.ua', $hosts);
        $this->assertContains('www.oru.org.ua', $hosts);
    }

    public function test_all_existing_god_routes_open_with_new_descriptions_and_source_links(): void
    {
        $descriptions = (new GodDescriptions)->all();
        foreach (config('faith_gods.gods') as $god) {
            $description = $descriptions[$god['slug']];
            $response = $this->get(route('faith.gods.show', $god['slug']));
            $response->assertOk()->assertSee($description['lead'])->assertSee('Джерела опису');
            foreach ($description['sources'] as $source) {
                $response->assertSee(e($source['url']), false);
            }
        }
        $this->get(route('faith.gods'))->assertOk()->assertSee($descriptions['goryn']['summary']);
    }

    public function test_new_description_takes_precedence_over_an_existing_import_without_changing_it(): void
    {
        $root = $this->fixtureStorage.'/app/content/gods/';
        $old = '<p>OLD GENERIC EDITORIAL</p>';
        file_put_contents($root.'pages/perun.html', $old);
        $manifest = json_encode(['gods' => ['perun' => ['slug' => 'perun', 'title' => 'Перун', 'path' => 'pages/perun.html']]]);
        file_put_contents($root.'manifest.json', $manifest);
        $this->get(route('faith.gods.show', 'perun'))->assertOk()
            ->assertSee('Гроза, що змінює світ')
            ->assertDontSee('OLD GENERIC EDITORIAL');
        $this->assertSame($old, file_get_contents($root.'pages/perun.html'));
        $this->assertSame($manifest, file_get_contents($root.'manifest.json'));
    }

    public function test_an_additional_imported_entry_can_still_use_its_archived_text(): void
    {
        $root = $this->fixtureStorage.'/app/content/gods/';
        file_put_contents($root.'pages/additional.html', '<p>Додатковий матеріал архіву</p>');
        file_put_contents($root.'manifest.json', json_encode(['gods' => [
            'additional' => ['slug' => 'additional', 'title' => 'Додатковий образ', 'path' => 'pages/additional.html'],
        ]]));
        $this->get(route('faith.gods.show', 'additional'))->assertOk()->assertSee('Додатковий матеріал архіву');
    }
}
