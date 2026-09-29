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
        if (Schema::hasTable('learning_capture_terms')) {
            Schema::table('learning_capture_terms', function (Blueprint $table) {
                $table->unique(['learning_capture_id', 'normalized_term'], 'capture_term_unique');
            });

            return;
        }

        Schema::create('learning_capture_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_capture_id')->constrained()->cascadeOnDelete();
            $table->string('term', 200);
            $table->string('normalized_term', 200);
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['learning_capture_id', 'normalized_term'], 'capture_term_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_capture_terms');
    }
};
