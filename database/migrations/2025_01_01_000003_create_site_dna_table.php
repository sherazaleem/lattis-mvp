<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_dna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->unique()->constrained('sites');
            $table->string('niche', 255);
            $table->string('angle', 100)->nullable(); // industry_shift, case_study, customer_pain, faq, event_guide
            $table->text('audience')->nullable();
            $table->json('format_rules')->nullable();
            $table->json('forbidden_topics')->nullable();
            $table->string('cta_style', 100)->nullable(); // soft_suggest, direct_action, newsletter, product_link
            $table->tinyInteger('ai_aggressiveness')->default(3); // 1=minimal rewrite, 5=fully original
            $table->json('seo_posture')->nullable();
            $table->json('monetisation_rules')->nullable();
            $table->text('prompt_fragment')->nullable();
            $table->integer('min_word_count')->default(400);
            $table->integer('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_dna');
    }
};
