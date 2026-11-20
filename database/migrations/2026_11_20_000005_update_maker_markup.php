<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->applyMarkup(138, [
            'додає 30% до цієї ціни' => 'додає 38% до цієї ціни',
            'ціна виробу для покупця становить 1 300 грн' => 'ціна виробу для покупця становить 1 380 грн',
        ]);
    }

    public function down(): void
    {
        $this->applyMarkup(130, [
            'додає 38% до цієї ціни' => 'додає 30% до цієї ціни',
            'ціна виробу для покупця становить 1 380 грн' => 'ціна виробу для покупця становить 1 300 грн',
        ]);
    }

    private function applyMarkup(int $multiplier, array $replacements): void
    {
        DB::transaction(function () use ($multiplier, $replacements) {
            DB::table('products')->whereNotNull('maker_price_minor')->update([
                'price_minor' => DB::raw("ROUND(maker_price_minor * {$multiplier} / 100.0)"),
            ]);

            $terms = DB::table('content_pages')->where('path', '/umovy-spivpratsi-z-maistramy');
            $body = $terms->lockForUpdate()->value('body');
            if (is_string($body) && ($updatedBody = strtr($body, $replacements)) !== $body) {
                $terms->update(['body' => $updatedBody, 'updated_at' => now()]);
            }
        });
    }
};
