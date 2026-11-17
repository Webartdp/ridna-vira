<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('maker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('maker_price_minor')->nullable();
            $table->boolean('made_to_order')->default(false);
        });
        Schema::table('order_items', fn (Blueprint $table) => $table->boolean('made_to_order')->default(false));
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('made_to_order'));
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maker_id');
            $table->dropColumn(['maker_price_minor', 'made_to_order']);
        });
    }
};
