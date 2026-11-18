<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('maker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('maker_price_minor')->nullable();
            $table->timestamp('maker_paid_at')->nullable();
            $table->unsignedInteger('maker_paid_minor')->nullable();
            $table->string('maker_payout_reference', 190)->nullable();
            $table->foreignId('maker_paid_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('maker_id');
            $table->dropConstrainedForeignId('maker_paid_by');
            $table->dropColumn(['maker_price_minor', 'maker_paid_at', 'maker_paid_minor', 'maker_payout_reference']);
        });
    }
};
