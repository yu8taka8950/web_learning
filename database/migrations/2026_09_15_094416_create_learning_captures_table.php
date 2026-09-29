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
        Schema::create('learning_captures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('page_title', 500);
            $table->text('source_url');
            $table->string('normalized_source_url', 2048);
            $table->string('source_host', 255)->nullable();
            $table->string('status', 32)->default('new');
            $table->timestamp('captured_at')->useCurrent();
            $table->timestamp('last_analyzed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'normalized_source_url']);
            $table->index(['user_id', 'captured_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_captures');
    }
};
