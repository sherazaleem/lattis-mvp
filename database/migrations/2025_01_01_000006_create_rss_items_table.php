<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('rss_sources');
            $table->string('url', 500);
            $table->string('title', 500);
            $table->longText('body_html')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('fetched_at');
            $table->char('content_hash', 64)->unique(); // SHA-256(url + title) — non-negotiable dedup key
            $table->integer('source_word_count')->default(0);
            $table->boolean('is_processed')->default(false);
            $table->boolean('is_duplicate')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['is_processed', 'is_duplicate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_items');
    }
};
