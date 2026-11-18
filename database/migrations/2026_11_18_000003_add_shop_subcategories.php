<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
        });

        $catalogue = [
            'cholovichyi-odiah' => ['Чоловічий одяг', ['vyshyvanky' => 'Вишиванки та сорочки', 'shtany' => 'Штани та шаровари', 'zhylety' => 'Жилети та свити', 'holovni-ubory' => 'Головні убори']],
            'zhinochyi-odiah' => ['Жіночий одяг', ['vyshyvanky' => 'Вишиванки та сорочки', 'sukni' => 'Сукні', 'spidnytsi' => 'Спідниці, плахти та запаски', 'holovni-ubory' => 'Хустки та головні убори']],
            'zhinochi-oberehy' => ['Жіночі обереги', ['pidvisky' => 'Підвіски-обереги', 'braslety' => 'Браслети-обереги', 'vyshyti' => 'Вишиті й ткані обереги']],
            'cholovichi-oberehy' => ['Чоловічі обереги', ['pidvisky' => 'Підвіски-обереги', 'braslety' => 'Браслети-обереги', 'vyshyti' => 'Вишиті й ткані обереги']],
            'oberehy-dlia-domu' => ['Обереги для дому', ['motanky' => 'Ляльки-мотанки', 'dereviani' => 'Дерев’яні обереги', 'pidvisni' => 'Підвісні обереги']],
            'prykrasy' => ['Прикраси', ['braslety' => 'Браслети, зокрема мідні', 'namysto' => 'Намиста й гердани', 'serezhky' => 'Сережки', 'kabluchky' => 'Каблучки', 'pidvisky' => 'Кулони та підвіски']],
            'poiasy-ta-kraiky' => ['Пояси та крайки', ['kraiky' => 'Крайки', 'tkani' => 'Ткані й плетені пояси', 'shkiriani' => 'Шкіряні пояси']],
            'dlia-kapyshcha' => ['Для капища', ['obrazy' => 'Дерев’яні образи Богів', 'obriadovi-vyroby' => 'Обрядові вироби', 'posud' => 'Обрядовий посуд']],
            'dlia-krasnoho-kuta' => ['Для красного кута', ['obrazy' => 'Образи Богів і дерев’яні панно', 'rushnyky' => 'Рушники', 'pidstavky' => 'Підставки та полички', 'svichnyky' => 'Свічники']],
        ];

        $categories = DB::table('product_categories');
        foreach ($catalogue as $slug => [$name, $children]) {
            $categories->insertOrIgnore(['slug' => $slug, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            $parent = (clone $categories)->where('slug', $slug)->value('id');
            foreach ($children as $suffix => $child) {
                $categories->insertOrIgnore([
                    'slug' => $slug.'-'.$suffix, 'name' => $child, 'parent_id' => $parent,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('product_categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_id'));
    }
};
