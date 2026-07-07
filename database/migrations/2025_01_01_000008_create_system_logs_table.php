<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_type', 100);       // e.g. FetchRssFeedJob, GenerateArticleJob, PublishArticleJob
            $table->string('entity_type', 100)->nullable(); // e.g. Site, RssSource, GeneratedArticle
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('status', 50);          // success, failed, retried, skipped, etc.
            $table->text('message')->nullable();
            $table->json('payload')->nullable();   // full context — this table is the foundation
                                                     // for every future alert and (eventually) the
                                                     // AI Project Manager. Always log real detail.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['job_type', 'status']);
            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_logs');
    }
};
