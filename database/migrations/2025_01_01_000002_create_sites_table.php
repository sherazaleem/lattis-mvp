<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('domain', 255)->unique();
            $table->enum('stack_type', ['wordpress', 'ftp_html']);
            $table->foreignId('cluster_id')->constrained('niche_clusters');
            $table->integer('max_posts_per_day')->default(1);
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('auto_publish')->default(true);
            $table->string('cms_api_url', 255)->nullable();
            $table->string('language', 10)->default('en');
            $table->json('deployment_state')->nullable(); // last_published_at, credential_status, health_check_status, total_published
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'cluster_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
