<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();

            // 将来ユーザー別に利用量を見るため
            // 現在のChrome拡張では未ログイン通信もあるためnullable
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // gemini / openai など
            $table->string('provider', 30);

            // gemini-3.5-flash-lite など
            $table->string('model', 100);

            // web_term_detection / screenshot / quiz_generation など
            $table->string('feature', 50);

            // AIへ送った文章量
            $table->unsignedInteger('input_characters')->default(0);

            // APIから取得できる場合に保存
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();

            // API通信成功・失敗
            $table->boolean('success')->default(false);

            // 200 / 429 / 500 など
            $table->unsignedSmallInteger('http_status')->nullable();

            // 失敗した場合だけ保存
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['provider', 'created_at']);
            $table->index(['feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
