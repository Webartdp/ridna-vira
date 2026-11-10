<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_pages', function (Blueprint $table) {
            $table->json('image_origin')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_pages', fn (Blueprint $table) => $table->dropColumn(['image_origin', 'seo_title', 'seo_description']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['seo_title', 'seo_description']));
    }
};
