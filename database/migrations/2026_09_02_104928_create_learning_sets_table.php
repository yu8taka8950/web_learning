<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_sets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('title');

            // web / screenshot / manual
            $table->string('source_type', 20)->nullable();

            // WebページのURL
            $table->text('source_url')->nullable();

            // スクリーンショットの保存先
            $table->string('source_image_path')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_sets');
    }
};
