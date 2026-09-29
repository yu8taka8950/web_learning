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
        Schema::create('usage_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_feature_usage_id')->constrained()->cascadeOnDelete();
            $table->uuid('token')->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_reservations');
    }
};
