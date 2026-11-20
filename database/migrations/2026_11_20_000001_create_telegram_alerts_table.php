<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('event_key', 160)->unique();
            $table->text('message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['sent_at', 'failed_at', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_alerts');
    }
};
