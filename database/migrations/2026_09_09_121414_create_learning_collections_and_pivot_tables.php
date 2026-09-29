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
        Schema::create('learning_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('auto_rule', 30)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'auto_rule'], 'learning_collections_user_auto_rule_unique');
        });

        Schema::create('learning_collection_learning_set', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_set_id')->constrained()->cascadeOnDelete();
            $table->string('assigned_by', 10)->default('manual');
            $table->timestamps();

            $table->unique(['learning_collection_id', 'learning_set_id'], 'collection_learning_set_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_collection_learning_set');
        Schema::dropIfExists('learning_collections');
    }
};
