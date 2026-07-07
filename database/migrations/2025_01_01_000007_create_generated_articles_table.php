<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rss_item_id')->constrained('rss_items');
            $table->foreignId('site_id')->constrained('sites');
            $table->string('author_mongo_id', 255)->nullable(); // MVP: may be a hardcoded default author id
            $table->string('mongo_id', 255)->nullable();        // MongoDB ObjectId of the full article document

            // Full state machine — see docs/MVP_ROADMAP.md. Do not shortcut transitions.
            $table->enum('status', [
                'queued', 'generating', 'generated', 'review', 'approved',
                'scheduled', 'publishing', 'published', 'failed', 'rejected', 'skipped',
            ])->default('queued');

            $table->tinyInteger('quality_score')->nullable(); // 0-100 composite
            $table->json('quality_flags')->nullable();        // FAIL reasons and HOLD reasons

            $table->integer('prompt_version')->nullable();  // which site_dna version produced this
            $table->integer('author_version')->nullable();

            $table->string('model_used', 100)->nullable();   // e.g. gpt-4.1, claude-sonnet-4-6
            $table->string('provider', 50)->nullable();      // openai, claude, ollama

            $table->integer('tokens_input')->default(0);
            $table->integer('tokens_output')->default(0);
            $table->integer('generation_ms')->default(0);

            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('reject_reason', 500)->nullable();

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_id', 255)->nullable();  // WordPress post ID / FTP file path
            $table->string('external_url', 500)->nullable();

            $table->timestamps();

            $table->index(['status', 'site_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_articles');
    }
};
