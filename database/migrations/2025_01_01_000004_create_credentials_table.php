<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained('sites');
            $table->enum('adapter_type', ['wordpress', 'ftp_html']);
            $table->string('host', 255)->nullable();
            $table->integer('port')->nullable();
            $table->text('username'); // encrypted via Crypt::encryptString()
            $table->text('secret');   // encrypted via Crypt::encryptString()
            $table->enum('credential_status', ['active', 'failed', 'unverified'])->default('unverified');
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
