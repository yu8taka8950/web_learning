<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

class LearningAiService
{
    public const DAILY_LIMIT = 3;

    public function remainingFor(User $user): int
    {
        $used = AiUsageLog::query()
            ->whereBelongsTo($user)
            ->where('feature', 'learning_chat')
            ->where('success', true)
            ->whereBetween('created_at', [now()->copy()->startOfDay(), now()->copy()->endOfDay()])
            ->count();

        return max(0, self::DAILY_LIMIT - $used);
    }

    /** @return array{answer: string, remaining: int} */
    public function ask(User $user, Question $question, string $userQuestion): array
    {
        $apiKey = config('services.gemini.key');
        $model = (string) config('services.gemini.model');

        if (! is_string($apiKey) || $apiKey === '') {
            throw new LearningAiException('Gemini APIキーが設定されていません。', 500);
        }

        $prompt = $this->prompt($question, $userQuestion);
        $inputCharacters = mb_strlen($prompt);

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $apiKey,
                'Content-Type' => 'application/json',
            ])->acceptJson()->connectTimeout(10)->timeout(30)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['temperature' => 0.3, 'maxOutputTokens' => 1200],
                ]);

            $usage = $response->json('usageMetadata', []);
            if ($response->failed()) {
                $this->recordUsage($user, $model, $inputCharacters, is_array($usage) ? $usage : [], false, $response->status());
                throw new LearningAiException('AI回答の生成に失敗しました。時間をおいて再度お試しください。', 502);
            }

            $answer = trim((string) $response->json('candidates.0.content.parts.0.text'));
            if ($answer === '') {
                $this->recordUsage($user, $model, $inputCharacters, is_array($usage) ? $usage : [], false, $response->status());
                throw new LearningAiException('AI回答を取得できませんでした。時間をおいて再度お試しください。', 502);
            }

            $this->recordUsage($user, $model, $inputCharacters, is_array($usage) ? $usage : [], true, $response->status());

            return ['answer' => $answer, 'remaining' => $this->remainingFor($user)];
        } catch (LearningAiException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->recordUsage($user, $model, $inputCharacters, [], false, null);
            throw new LearningAiException('AI回答の生成中にエラーが発生しました。時間をおいて再度お試しください。', 500, $exception);
        }
    }

    private function prompt(Question $question, string $userQuestion): string
    {
        $learningSet = $question->learningSet;
        $context = [
            '現在の問題' => $question->question,
            '入力式の問題' => $question->input_question,
            '解説' => $question->explanation,
            '学習セット' => $learningSet->title,
            '参照URL' => $learningSet->source_url,
        ];
        $contextText = collect($context)
            ->filter(fn (?string $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value, string $label): string => "{$label}：\n{$value}")
            ->implode("\n\n");

        return <<<PROMPT
あなたはWeb Learningの学習補助AIです。現在学習中の問題・解説を参考に、ユーザーの質問へ初心者向けに回答してください。

【ルール】
・日本語で、400〜800文字程度を目安に簡潔に答える
・専門用語は必要なら短く説明する
・具体例またはコマンド例を1つ程度示す
・分からない場合は断定しない
・架空のURLを作らない
・参照元にない事実を断定しすぎない
・学習内容から大きく逸脱する質問には、現在の学習内容に関連する質問を促す

【学習コンテキスト】
{$contextText}

【ユーザーの質問】
{$userQuestion}
PROMPT;
    }

    /** @param array<string, mixed> $usage */
    private function recordUsage(User $user, string $model, int $inputCharacters, array $usage, bool $success, ?int $httpStatus): void
    {
        try {
            AiUsageLog::query()->create([
                'user_id' => $user->id, 'provider' => 'gemini', 'model' => $model, 'feature' => 'learning_chat',
                'input_characters' => $inputCharacters, 'input_tokens' => $usage['promptTokenCount'] ?? null,
                'output_tokens' => $usage['candidatesTokenCount'] ?? null, 'total_tokens' => $usage['totalTokenCount'] ?? null,
                'success' => $success, 'http_status' => $httpStatus,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
