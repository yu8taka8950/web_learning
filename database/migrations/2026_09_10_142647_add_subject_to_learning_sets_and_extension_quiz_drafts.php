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
        Schema::table('learning_sets', function (Blueprint $table) {
            $table->string('subject', 100)->nullable()->after('topic');
        });

        Schema::table('extension_quiz_drafts', function (Blueprint $table) {
            $table->string('subject', 100)->nullable()->after('topic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learning_sets', function (Blueprint $table) {
            $table->dropColumn('subject');
        });

        Schema::table('extension_quiz_drafts', function (Blueprint $table) {
            $table->dropColumn('subject');
        });
    }
};
