<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('learning_set_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->text('question');

            $table->text('option_a');
            $table->text('option_b');
            $table->text('option_c');
            $table->text('option_d');

            $table->char('correct_option', 1);

            $table->text('explanation')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
