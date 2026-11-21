<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_alerts', function (Blueprint $table) {
            $table->foreignId('maker_telegram_link_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maker_telegram_link_id');
        });
    }
};
