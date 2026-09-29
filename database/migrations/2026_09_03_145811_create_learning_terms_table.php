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
        Schema::create('learning_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_set_id')->constrained()->cascadeOnDelete();
            $table->string('term', 200);
            $table->text('description')->nullable();
            $table->string('source_type', 20)->nullable();
            $table->text('source_url')->nullable();
            $table->timestamps();

            $table->unique(['learning_set_id', 'term']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_terms');
    }
};
