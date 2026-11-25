<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('driver', 24);
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();
        });

        $now = now();
        DB::table('payment_methods')->insert([
            [
                'code' => 'wayforpay', 'driver' => 'wayforpay', 'name' => 'Онлайн карткою через WayForPay',
                'description' => null, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now,
            ],
            [
                'code' => 'cod', 'driver' => 'cod', 'name' => 'При отриманні',
                'description' => 'Післяплата Нової пошти за тарифами перевізника.',
                'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now,
            ],
        ]);

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_method_label', 120)->nullable();
            $table->text('payment_instructions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_method_label', 'payment_instructions']);
        });
        Schema::dropIfExists('payment_methods');
    }
};
