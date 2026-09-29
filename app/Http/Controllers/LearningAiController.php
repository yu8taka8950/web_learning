<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Services\LearningAiException;
use App\Services\LearningAiService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class LearningAiController extends Controller
{
    public function ask(Request $request, LearningAiService $learningAiService): JsonResponse
    {
        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'question' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if (preg_match('/^\s*$/u', (string) $value) === 1) {
                    $fail('質問を入力してください。');
                }
            }],
        ]);

        $question = Question::query()
            ->whereKey($validated['question_id'])
            ->whereHas('learningSet', fn (Builder $query) => $query->whereBelongsTo($request->user()))
            ->with('learningSet:id,user_id,title,source_url')
            ->firstOrFail();

        if ($learningAiService->remainingFor($request->user()) === 0) {
            return response()->json(['message' => '本日のAI質問を使い切りました。', 'remaining' => 0], 429);
        }

        $rateLimitKey = 'learning-chat:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return response()->json(['message' => '少し待ってから、もう一度質問してください。'], 429);
        }
        RateLimiter::hit($rateLimitKey, 5);

        try {
            return response()->json($learningAiService->ask($request->user(), $question, trim($validated['question'])));
        } catch (LearningAiException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getCode() ?: 500);
        }
    }
}
