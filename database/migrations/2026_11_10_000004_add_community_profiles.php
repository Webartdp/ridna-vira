<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('content_pages', 'community')) {
            Schema::table('content_pages', fn (Blueprint $table) => $table->json('community')->nullable());
        }
        $saved = DB::table('site_settings')->where('key', 'communities')->value('value');
        $locations = $saved ? json_decode($saved, true, 512, JSON_THROW_ON_ERROR) : config('communities');
        foreach ($locations as $location) {
            foreach ($location['entries'] ?? [] as $entry) {
                $slug = Str::slug($location['id'].'-'.$entry['title']);
                $path = '/hromady/'.$slug;
                if (DB::table('content_pages')->where('path', $path)->exists()) {
                    continue;
                }
                $featured = Str::contains($entry['title'], ['Полум’я Роду', 'Полум\'я Роду', 'Права', 'Росичі']);
                $excerpt = $entry['title'].' — '.$location['city'].', '.$location['region'].'. Контакти та інформація для тих, хто прагне долучитися до Рідної Віри.';
                DB::table('content_pages')->insert([
                    'path' => $path, 'section' => 'communities', 'title' => $entry['title'],
                    'excerpt' => $excerpt, 'body' => '<p>'.e($entry['title']).' — '.e($location['city']).', '.e($location['region']).'.</p><p>Щоб дізнатися про найближчі зустрічі та можливість долучитися, зверніться до представника за контактами на цій сторінці.</p>',
                    'editor_mode' => 'reading', 'is_published' => true, 'is_override' => true,
                    'community' => json_encode([...$entry, 'city' => $location['city'], 'region' => $location['region'], 'location_id' => $location['id'], 'featured' => $featured, 'position' => 100], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $navigation = DB::table('site_settings')->where('key', 'navigation')->value('value');
        if ($navigation) {
            $items = json_decode($navigation, true, 512, JSON_THROW_ON_ERROR);
            foreach ($items as &$item) {
                if (($item['url'] ?? '') === '/pro-tsentr' && ! collect($item['children'] ?? [])->contains('url', '/hromady')) {
                    $item['children'][] = ['label' => 'Громади', 'url' => '/hromady'];
                }
            }
            unset($item);
            DB::table('site_settings')->where('key', 'navigation')->update(['value' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        }
    }

    public function down(): void
    {
        // Preserve authored community pages and homepage choices if the deployment is rolled back.
    }
};
