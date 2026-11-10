<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_mcp_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('token_hash', 64)->unique();
            $table->json('scopes');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
        Schema::create('site_mcp_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_mcp_token_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('content_page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 50);
            $table->string('before_version', 64)->nullable();
            $table->string('after_version', 64);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_mcp_audits');
        Schema::dropIfExists('site_mcp_tokens');
    }
};
