<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_sources', function (Blueprint $table) {
            $table->id();
            $table->string('feed_url', 500)->unique();
            $table->foreignId('cluster_id')->nullable()->constrained('niche_clusters');
            $table->foreignId('site_id')->nullable()->constrained('sites');
            $table->integer('fetch_frequency_minutes')->default(60);
            $table->tinyInteger('priority')->default(5); // 1=highest, 10=lowest
            $table->string('language', 10)->default('en');
            $table->boolean('is_active')->default(true);
            $table->enum('status', ['active', 'errored'])->default('active');
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_sources');
    }
};
