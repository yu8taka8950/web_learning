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
        Schema::create('learning_term_boxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('name_key', 100);
            $table->string('kind', 10);
            $table->string('auto_rule', 120)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'name_key'], 'learning_term_boxes_user_name_key_unique');
            $table->unique(['user_id', 'auto_rule'], 'learning_term_boxes_user_auto_rule_unique');
            $table->index(['user_id', 'kind', 'deleted_at'], 'learning_term_boxes_user_kind_deleted_index');
        });

        Schema::create('learning_term_learning_term_box', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_term_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_term_box_id')->constrained()->cascadeOnDelete();
            $table->string('assigned_by', 10)->default('manual');
            $table->timestamps();

            $table->unique(['learning_term_id', 'learning_term_box_id'], 'learning_term_box_term_unique');
            $table->index(['learning_term_id', 'assigned_by'], 'learning_term_box_term_assignment_index');
        });

        Schema::table('learning_terms', function (Blueprint $table) {
            $table->timestamp('auto_box_disabled_at')->nullable()->after('source_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_terms', function (Blueprint $table) {
            $table->dropColumn('auto_box_disabled_at');
        });

        Schema::dropIfExists('learning_term_learning_term_box');
        Schema::dropIfExists('learning_term_boxes');
    }
};
