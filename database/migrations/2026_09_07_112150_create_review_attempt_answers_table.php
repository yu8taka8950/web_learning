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
        Schema::create('review_attempt_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('question_index');
            $table->char('selected_option', 1)->nullable();
            $table->boolean('is_correct');
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->unique(['review_attempt_id', 'question_index']);
            $table->unique(['review_attempt_id', 'question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_attempt_answers');
    }
};
