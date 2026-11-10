<?php

use App\Support\BookLibrary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('content_pages', 'book')) {
            Schema::table('content_pages', fn (Blueprint $table) => $table->json('book')->nullable());
        }
        BookLibrary::import();
    }

    public function down(): void
    {
        // Keep authored books and their metadata on code rollback.
    }
};
