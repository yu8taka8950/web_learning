<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('extension_quiz_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('source_title', 500);
            $table->text('source_url');
            $table->json('selected_terms');
            $table->json('generated_questions');
            $table->timestamp('expires_at')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extension_quiz_drafts');
    }
};
