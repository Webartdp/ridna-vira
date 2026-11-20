<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

return new class extends Migration
{
    public function up(): void
    {
        $pages = [
            'publichna-oferta' => ['Публічна оферта', 'Умови купівлі-продажу товарів у крамниці «Рідна Віра».'],
            'oplata-i-dostavka' => ['Оплата й доставка', 'Способи оплати та умови доставки товарів крамниці «Рідна Віра».'],
            'obmin-povernennia-ta-harantiia' => ['Обмін, повернення та гарантія', 'Порядок звернення щодо обміну, повернення та якості товару.'],
            'polityka-konfidentsiinosti-kramnytsi' => ['Політика конфіденційності магазину', 'Обробка персональних даних покупців і майстрів у крамниці «Рідна Віра».'],
            'umovy-spivpratsi-z-maistramy' => ['Умови співпраці з майстрами', 'Публікація виробів, ціни та виплати в крамниці «Рідна Віра».'],
            'rekvizyty-kramnytsi' => ['Реквізити продавця', 'Відомості про продавця та контакти з питань замовлень крамниці «Рідна Віра».'],
        ];

        $now = now();
        foreach ($pages as $slug => [$title, $excerpt]) {
            DB::table('content_pages')->insertOrIgnore([
                'path' => '/'.$slug,
                'section' => 'pages',
                'title' => $title,
                'excerpt' => $excerpt,
                'body' => File::get(database_path('data/shop-policies-2026-11-20/'.$slug.'.html')),
                'editor_mode' => 'reading',
                'is_published' => false,
                'is_override' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Preserve policy drafts and edits after a rollback.
    }
};
