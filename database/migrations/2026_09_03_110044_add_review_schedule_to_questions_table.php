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
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedTinyInteger('review_stage')->default(0)->after('explanation');
            $table->timestamp('next_review_at')->nullable()->index()->after('review_stage');
            $table->timestamp('last_reviewed_at')->nullable()->after('next_review_at');
            $table->unsignedInteger('review_count')->default(0)->after('last_reviewed_at');
            $table->unsignedInteger('correct_review_count')->default(0)->after('review_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn([
                'review_stage',
                'next_review_at',
                'last_reviewed_at',
                'review_count',
                'correct_review_count',
            ]);
        });
    }
};
