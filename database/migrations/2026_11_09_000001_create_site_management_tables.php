<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('section')->default('pages')->index();
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('editor_mode')->default('reading');
            $table->string('image')->nullable();
            $table->string('video_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_override')->default(false);
            $table->boolean('is_imported')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_page_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->longText('snapshot');
            $table->timestamps();
        });
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });
        Schema::create('media_assets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('file');
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();
            $table->longText('description')->nullable();
            $table->json('images')->nullable();
            $table->unsignedInteger('price_minor');
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('access_token', 64)->unique();
            $table->uuid('checkout_token')->unique();
            $table->string('customer_name');
            $table->string('email');
            $table->string('phone');
            $table->string('delivery_method');
            $table->text('delivery_address');
            $table->text('comment')->nullable();
            $table->unsignedInteger('total_minor');
            $table->string('currency', 3)->default('UAH');
            $table->string('payment_status')->default('pending')->index();
            $table->string('fulfilment_status')->default('new')->index();
            $table->string('tracking_number')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamp('reserved_until')->index();
            $table->timestamp('stock_released_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('gateway_status')->nullable();
            $table->timestamps();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku')->nullable();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('price_minor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['order_items', 'orders', 'products', 'product_categories', 'media_assets', 'content_revisions', 'content_pages', 'site_settings'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
