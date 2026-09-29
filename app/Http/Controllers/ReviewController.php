<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\ReviewAttempt;
use App\Services\ReviewAttemptService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function today(Request $request, ReviewAttemptService $attemptService): View|RedirectResponse
    {
        $attempt = $attemptService->startOrResume($request->user());

        if (! $attempt) {
            return view('reviews.today', ['attempt' => null]);
        }

        $questionIndex = $attemptService->firstUnansweredIndex($attempt);

        if ($questionIndex >= $attempt->total_questions) {
            return redirect()->route('reviews.result');
        }

        $questionId = $attempt->question_ids[$questionIndex] ?? null;
        abort_unless(is_int($questionId), 404);
        $question = Question::query()
            ->whereKey($questionId)
            ->whereHas('learningSet', fn (Builder $query) => $query->whereBelongsTo($request->user()))
            ->with('learningSet')
            ->firstOrFail();

        return view('reviews.today', compact('attempt', 'question', 'questionIndex'));
    }

    public function grade(Request $request, ReviewAttemptService $attemptService): RedirectResponse
    {
        $validated = $request->validate([
            'review_attempt_id' => ['required', 'integer'],
            'question_index' => ['required', 'integer', 'min:0'],
            'selected_option' => ['nullable', 'string', 'in:A,B,C,D'],
            'return_to' => ['nullable', 'string', 'in:dashboard'],
        ]);

        $attempt = ReviewAttempt::query()
            ->whereKey($validated['review_attempt_id'])
            ->whereBelongsTo($request->user())
            ->firstOrFail();
        $attempt = $attemptService->answer(
            $attempt,
            (int) $validated['question_index'],
            $validated['selected_option'] ?? null,
        );

        if ($attempt->status === 'completed') {
            return redirect()->route('reviews.result');
        }

        return ($validated['return_to'] ?? null) === 'dashboard'
            ? redirect()->route('dashboard')
            : redirect()->route('reviews.today');
    }

    public function result(Request $request): View|RedirectResponse
    {
        $attempt = ReviewAttempt::query()
            ->whereBelongsTo($request->user())
            ->where('status', 'completed')
            ->latest('id')
            ->first();

        if (! $attempt) {
            return redirect()->route('reviews.today');
        }

        $answers = $attempt->answers()->get()->keyBy('question_index');
        $questions = Question::query()
            ->whereIn('id', $attempt->question_ids)
            ->whereHas('learningSet', fn (Builder $query) => $query->whereBelongsTo($request->user()))
            ->get()
            ->keyBy('id');
        abort_unless($questions->count() === $attempt->total_questions, 404);

        $items = collect($attempt->question_ids)->map(function (int $questionId, int $questionIndex) use ($answers, $questions): array {
            $answer = $answers->get($questionIndex);
            $question = $questions->get($questionId);
            abort_unless($answer && $question, 404);

            return [
                'answer' => $answer,
                'question' => $question,
            ];
        });
        $correctCount = $answers->where('is_correct', true)->count();
        $unansweredCount = $answers->whereNull('selected_option')->count();

        return view('reviews.result', [
            'items' => $items,
            'results' => [
                'correctCount' => $correctCount,
                'incorrectCount' => $attempt->total_questions - $correctCount - $unansweredCount,
                'unansweredCount' => $unansweredCount,
                'totalCount' => $attempt->total_questions,
                'percentage' => $attempt->total_questions === 0 ? 0 : (int) round($correctCount / $attempt->total_questions * 100),
            ],
        ]);
    }
}
