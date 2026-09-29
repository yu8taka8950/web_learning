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
        Schema::create('term_explanations', function (Blueprint $table) {
            $table->id();
            $table->string('normalized_term', 100);
            $table->string('display_term', 100);
            $table->string('subject_key', 100);
            $table->string('subject_label', 100);
            $table->string('topic_label')->nullable();
            $table->text('explanation');
            $table->string('provider', 30)->nullable();
            $table->string('model', 100)->nullable();
            $table->timestamps();

            $table->unique(['subject_key', 'normalized_term']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('term_explanations');
    }
};
