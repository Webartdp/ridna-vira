<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $setting = DB::table('site_settings')->where('key', 'communities');
        $saved = $setting->value('value');
        if ($saved === null) {
            return;
        }

        $locations = json_decode($saved, true, 512, JSON_THROW_ON_ERROR);
        $defaults = array_column(config('communities'), null, 'id');
        $labels = array_column(config('community_map.circles'), 'label', 'id');

        foreach ($locations as &$location) {
            $id = $location['id'];
            $oldCircle = $id === 'bezliudivka' ? 'sicheslavske' : 'zahidne';
            $circle = $defaults[$id]['circle'] ?? 'unassigned';
            if ($location['circle'] === $oldCircle) {
                $location['circle'] = $circle;
                $location['circleLabel'] = $labels[$circle];
            }
            foreach ($location['entries'] as &$entry) {
                if (($entry['circle'] ?? null) === $oldCircle) {
                    $entry['circle'] = $circle;
                }
            }
            unset($entry);
        }
        unset($location);

        $existingIds = array_column($locations, 'id');
        foreach (['pidlissia', 'cherkasy', 'korostyshiv'] as $id) {
            if (! in_array($id, $existingIds, true)) {
                $locations[] = $defaults[$id];
            }
        }
        $setting->update(['value' => json_encode(array_values($locations), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }

    public function down(): void
    {
        // Preserve editorial changes made to the map after migration.
    }
};
