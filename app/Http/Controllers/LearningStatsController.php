<?php

namespace App\Http\Controllers;

use App\Models\Question;
use App\Models\QuizAttemptAnswer;
use App\Models\ReviewAttemptAnswer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LearningStatsController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $ownedQuestions = Question::query()
            ->whereHas('learningSet', fn (Builder $query) => $query->whereBelongsTo($user));

        $reviewTotals = (clone $ownedQuestions)
            ->toBase()
            ->selectRaw('COUNT(*) as question_count')
            ->selectRaw('COALESCE(SUM(review_count), 0) as total_review_count')
            ->selectRaw('COALESCE(SUM(correct_review_count), 0) as total_correct_review_count')
            ->first();

        $learningSetCount = $user->learningSets()->count();
        $questionCount = (int) $reviewTotals->question_count;
        $dueReviewCount = (clone $ownedQuestions)
            ->whereNotNull('next_review_at')
            ->where('next_review_at', '<=', now())
            ->count();
        $totalReviewCount = (int) $reviewTotals->total_review_count;
        $totalCorrectReviewCount = (int) $reviewTotals->total_correct_review_count;
        $reviewAccuracy = $totalReviewCount === 0
            ? 0
            : (int) round(($totalCorrectReviewCount / $totalReviewCount) * 100);

        $quizAnswers = QuizAttemptAnswer::query()
            ->whereNotNull('selected_option')
            ->whereHas('quizAttempt', fn (Builder $query) => $query
                ->whereBelongsTo($user)
                ->whereNull('extension_quiz_draft_id'));
        $reviewAnswers = ReviewAttemptAnswer::query()
            ->whereNotNull('selected_option')
            ->whereHas('reviewAttempt', fn (Builder $query) => $query->whereBelongsTo($user));
        $now = CarbonImmutable::now(config('app.timezone'));
        $todayStart = $now->startOfDay();
        $weekStart = $now->startOfWeek();
        $weekEnd = $now->endOfWeek();
        $todayStats = $this->answerSummary(clone $quizAnswers, clone $reviewAnswers, $todayStart, $now);
        $allTimeStats = $this->answerSummary(clone $quizAnswers, clone $reviewAnswers);
        $weekStats = $this->weekStats(clone $quizAnswers, clone $reviewAnswers, $weekStart, $weekEnd);

        $weakQuestions = (clone $ownedQuestions)
            ->select(['id', 'question', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_option', 'review_count', 'correct_review_count'])
            ->where('review_count', '>=', 2)
            ->orderByRaw('(1.0 * correct_review_count / review_count) ASC')
            ->orderByDesc('review_count')
            ->orderBy('id')
            ->limit(5)
            ->get();

        $recentReviewAnswers = ReviewAttemptAnswer::query()
            ->select(['id', 'review_attempt_id', 'question_id', 'is_correct', 'answered_at'])
            ->whereHas('reviewAttempt', fn (Builder $query) => $query->whereBelongsTo($user))
            ->whereHas('question.learningSet', fn (Builder $query) => $query->whereBelongsTo($user))
            ->with(['question:id,question'])
            ->latest('answered_at')
            ->limit(5)
            ->get();

        return view('learning-stats.index', compact(
            'learningSetCount',
            'questionCount',
            'dueReviewCount',
            'totalReviewCount',
            'totalCorrectReviewCount',
            'reviewAccuracy',
            'todayStats',
            'allTimeStats',
            'weekStats',
            'weakQuestions',
            'recentReviewAnswers',
        ));
    }

    /**
     * @return array{answered: int, correct: int, accuracy: int|string}
     */
    private function answerSummary(Builder $quizAnswers, Builder $reviewAnswers, ?CarbonImmutable $from = null, ?CarbonImmutable $until = null): array
    {
        $summaries = [];
        foreach ([$quizAnswers, $reviewAnswers] as $answers) {
            if ($from) {
                $answers->whereBetween('answered_at', [$from, $until ?? CarbonImmutable::now(config('app.timezone'))]);
            }
            $summaries[] = $answers
                ->selectRaw('COUNT(*) as answered, COALESCE(SUM(is_correct), 0) as correct')
                ->first();
        }
        $answered = (int) $summaries[0]->answered + (int) $summaries[1]->answered;
        $correct = (int) $summaries[0]->correct + (int) $summaries[1]->correct;

        return [
            'answered' => $answered,
            'correct' => $correct,
            'accuracy' => $answered === 0 ? '—' : (int) round(($correct / $answered) * 100),
        ];
    }

    /**
     * @return Collection<int, array{date: string, weekday: string, weekday_label: string, count: int, bar_height: int}>
     */
    private function weekStats(Builder $quizAnswers, Builder $reviewAnswers, CarbonImmutable $weekStart, CarbonImmutable $weekEnd): Collection
    {
        $timezone = config('app.timezone');
        $answerCounts = $quizAnswers
            ->whereBetween('answered_at', [$weekStart, $weekEnd])
            ->get(['id', 'answered_at'])
            ->concat($reviewAnswers->whereBetween('answered_at', [$weekStart, $weekEnd])->get(['id', 'answered_at']))
            ->countBy(fn (QuizAttemptAnswer|ReviewAttemptAnswer $answer): string => $answer->answered_at->setTimezone($timezone)->toDateString());
        $maximumCount = max(1, $answerCounts->max() ?? 0);
        $weekdays = [
            ['月', '月曜日'],
            ['火', '火曜日'],
            ['水', '水曜日'],
            ['木', '木曜日'],
            ['金', '金曜日'],
            ['土', '土曜日'],
            ['日', '日曜日'],
        ];

        return collect(range(0, 6))->map(function (int $offset) use ($weekStart, $answerCounts, $maximumCount, $weekdays): array {
            $day = $weekStart->addDays($offset);
            $count = (int) ($answerCounts[$day->toDateString()] ?? 0);

            return [
                'date' => $day->format('n/j'),
                'weekday' => $weekdays[$offset][0],
                'weekday_label' => $weekdays[$offset][1],
                'count' => $count,
                'bar_height' => (int) round(($count / $maximumCount) * 100),
            ];
        });
    }
}
