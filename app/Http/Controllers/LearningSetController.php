<?php

namespace App\Http\Controllers;

use App\Models\LearningSet;
use App\Models\Question;
use App\Models\QuizAttempt;
use App\Models\User;
use App\Services\LearningTopicClassifier;
use App\Services\QuizAttemptService;
use App\Services\ReviewAttemptService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LearningSetController extends Controller
{
    public function __construct(private LearningTopicClassifier $classifier) {}

    public function index(Request $request, QuizAttemptService $quizAttemptService, ReviewAttemptService $reviewAttemptService): View|RedirectResponse
    {
        if ($request->user()->onboarding_completed_at === null) {
            return redirect()->route('onboarding.show');
        }

        $inProgressAttempts = $request->user()->quizAttempts()
            ->where('status', 'in_progress')
            ->where(function ($query): void {
                $query->where(function ($learningSetQuery): void {
                    $learningSetQuery->whereNotNull('learning_set_id')->whereHas('learningSet');
                })->orWhere(function ($draftAttemptQuery): void {
                    $draftAttemptQuery->whereNull('learning_set_id')
                        ->whereHas('extensionQuizDraft', fn ($draftQuery) => $draftQuery
                            ->whereNull('claimed_at')
                            ->where('expires_at', '>', now()));
                });
            })
            ->with(['learningSet:id,user_id,title,topic,source_url', 'learningSet.collections:id,name', 'extensionQuizDraft:id,token,source_title,topic,source_url,generated_questions,expires_at,claimed_at'])
            ->withCount('answers')
            ->latest('updated_at')
            ->limit(20)
            ->get();
        $newLearningSets = $request->user()->learningSets()
            ->whereHas('questions')
            ->whereDoesntHave('quizAttempts')
            ->withCount('questions')
            ->with('collections:id,name')
            ->latest('created_at')
            ->limit(20)
            ->get();
        $regularDashboardLearningItems = $this->dashboardLearningItems($inProgressAttempts, $newLearningSets);
        $dashboardLearningItemCount = $regularDashboardLearningItems->count();
        $searchQuery = Str::limit(trim((string) $request->query('q')), 100, '');
        $user = $request->user();
        $hasActiveLearningSet = $user->learningSets()->exists();
        $hasAnyLearningSet = $user->learningSets()->withTrashed()->exists();
        $hasLearningHistory = $user->quizAttempts()->exists()
            || $user->reviewAttempts()->exists()
            || $user->learningCaptures()->exists()
            || $user->extensionQuizDrafts()->exists();
        $showFirstLearningEmptyState = $searchQuery === ''
            && ! $hasActiveLearningSet
            && ! $hasAnyLearningSet
            && ! $hasLearningHistory;
        $dashboardLearningItems = $searchQuery === ''
            ? $regularDashboardLearningItems
            : $this->searchLearningItems($user, $searchQuery);
        $dashboardDueReviewCount = Question::query()->dueForUser($user)->count();
        $dashboardDate = now()->format('n月j日');
        $availableCollections = $user->learningCollections()->orderBy('name')->get(['id', 'name']);
        $inProgressReviewAttempt = $user->reviewAttempts()
            ->where('status', 'in_progress')
            ->withCount('answers')
            ->latest('updated_at')
            ->first();

        if ($inProgressReviewAttempt) {
            $reviewIndex = $reviewAttemptService->firstUnansweredIndex($inProgressReviewAttempt);
            $reviewQuestionId = $inProgressReviewAttempt->question_ids[$reviewIndex] ?? null;
            $reviewQuestionIsActive = is_int($reviewQuestionId) && Question::query()
                ->whereKey($reviewQuestionId)
                ->whereHas('learningSet', fn ($query) => $query->whereBelongsTo($user))
                ->exists();

            if (! $reviewQuestionIsActive) {
                $inProgressReviewAttempt = null;
            }
        }
        $latestQuizAttempt = $inProgressAttempts->first();

        $quickLearningType = null;
        $quickLearningAttempt = null;
        $dueReviewCount = $dashboardDueReviewCount;

        if ($latestQuizAttempt && $inProgressReviewAttempt) {
            if ($latestQuizAttempt->updated_at->gte($inProgressReviewAttempt->updated_at)) {
                $quickLearningType = 'quiz';
                $quickLearningAttempt = $latestQuizAttempt;
            } else {
                $quickLearningType = 'review';
                $quickLearningAttempt = $inProgressReviewAttempt;
            }
        } elseif ($latestQuizAttempt) {
            $quickLearningType = 'quiz';
            $quickLearningAttempt = $latestQuizAttempt;
        } elseif ($inProgressReviewAttempt) {
            $quickLearningType = 'review';
            $quickLearningAttempt = $inProgressReviewAttempt;
        } else {
            $quickLearningType = $dueReviewCount > 0 ? 'due_review' : 'random';
        }

        $quickLearningQuestion = null;
        $quickLearningIndex = null;

        if ($quickLearningType === 'review' && $quickLearningAttempt) {
            $quickLearningIndex = $reviewAttemptService->firstUnansweredIndex($quickLearningAttempt);
            $questionId = $quickLearningAttempt->question_ids[$quickLearningIndex] ?? null;

            if ($questionId) {
                $quickLearningQuestion = Question::query()
                    ->whereKey($questionId)
                    ->whereHas('learningSet', fn ($query) => $query->whereBelongsTo($user))
                    ->with('learningSet:id,title,topic,source_url')
                    ->first();
            }
        } elseif ($quickLearningType === 'quiz' && $quickLearningAttempt) {
            $quickLearningIndex = $quizAttemptService->firstUnansweredIndex($quickLearningAttempt);

            if ($quickLearningAttempt->learningSet) {
                $quickLearningQuestion = $quickLearningAttempt->learningSet
                    ->questions()
                    ->orderBy('id')
                    ->skip($quickLearningIndex)
                    ->first();
            } else {
                $quickLearningQuestion = $quickLearningAttempt->extensionQuizDraft?->generated_questions[$quickLearningIndex] ?? null;
            }
        } elseif ($quickLearningType === 'random') {
            $randomQuestionQuery = Question::query()
                ->whereHas('learningSet', fn ($query) => $query->whereBelongsTo($user))
                ->with('learningSet:id,title,topic,source_url');
            $previousQuestionId = (int) $request->session()->get('quick_random_question_id', 0);

            $quickLearningQuestion = (clone $randomQuestionQuery)
                ->when($previousQuestionId > 0, fn ($query) => $query->where('id', '!=', $previousQuestionId))
                ->inRandomOrder()
                ->first();

            if (! $quickLearningQuestion && $previousQuestionId > 0) {
                $quickLearningQuestion = $randomQuestionQuery->whereKey($previousQuestionId)->first();
            }

            if ($quickLearningQuestion) {
                $request->session()->put('quick_random_question_id', $quickLearningQuestion->id);
            }
        }

        return view('dashboard', compact(
            'dashboardLearningItems',
            'dashboardLearningItemCount',
            'availableCollections',
            'searchQuery',
            'dashboardDueReviewCount',
            'dashboardDate',
            'dueReviewCount',
            'inProgressAttempts',
            'inProgressReviewAttempt',
            'quickLearningType',
            'quickLearningAttempt',
            'quickLearningQuestion',
            'quickLearningIndex',
            'showFirstLearningEmptyState',
        ));
    }

    public function create(): View
    {
        return view('learning-sets.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->learningSets()->create([
            'title' => $validated['title'],
            'source_type' => 'manual',
        ]);

        return redirect()->route('dashboard')->with('status', '学習セットを作成しました。');
    }

    public function quiz(Request $request, LearningSet $learningSet, QuizAttemptService $attemptService): View
    {
        $this->ensureOwnership($request, $learningSet);
        $questions = $learningSet->questions()->orderBy('id')->get();
        if ($questions->isEmpty()) {
            return view('learning-sets.quiz', ['learningSet' => $learningSet, 'questions' => $questions, 'attempt' => null]);
        }

        $inProgressAttempt = $request->user()->quizAttempts()->where('learning_set_id', $learningSet->id)->where('status', 'in_progress')->first();
        $completedAttempt = $request->user()->quizAttempts()->where('learning_set_id', $learningSet->id)->where('status', 'completed')->latest('id')->first();
        if (! $inProgressAttempt && $completedAttempt) {
            $answers = $completedAttempt->answers()->get()->keyBy('question_index');
            $correctCount = $answers->where('is_correct', true)->count();
            $unansweredCount = $answers->whereNull('selected_option')->count();

            return view('learning-sets.quiz', [
                'learningSet' => $learningSet,
                'questions' => $questions,
                'attempt' => $completedAttempt,
                'answers' => $answers,
                'results' => [
                    'correctCount' => $correctCount,
                    'incorrectCount' => $completedAttempt->total_questions - $correctCount - $unansweredCount,
                    'unansweredCount' => $unansweredCount,
                    'totalCount' => $completedAttempt->total_questions,
                ],
            ]);
        }

        $attempt = $inProgressAttempt ?? $attemptService->forLearningSet($request->user(), $learningSet);
        $index = $attemptService->firstUnansweredIndex($attempt);

        $question = $questions->get($index);

        return view('learning-sets.quiz', compact('learningSet', 'questions', 'attempt', 'index', 'question'));
    }

    public function grade(Request $request, LearningSet $learningSet, QuizAttemptService $attemptService): RedirectResponse
    {
        $this->ensureOwnership($request, $learningSet);

        $validated = $request->validate([
            'question_index' => ['required', 'integer', 'min:0'],
            'selected_option' => ['nullable', 'string', 'in:A,B,C,D'],
            'return_to' => ['nullable', 'string', 'in:dashboard'],
        ]);

        $questionIndex = (int) $validated['question_index'];
        $attempt = $request->user()->quizAttempts()
            ->where('learning_set_id', $learningSet->id)
            ->whereHas('answers', fn ($query) => $query->where('question_index', $questionIndex))
            ->latest('id')
            ->first()
            ?? $attemptService->forLearningSet($request->user(), $learningSet);
        $question = $learningSet->questions()->orderBy('id')->skip($questionIndex)->firstOrFail();
        $attempt = $attemptService->answer($attempt, $questionIndex, $validated['selected_option'] ?? null, $question->id, $question->correct_option);

        if (($validated['return_to'] ?? null) === 'dashboard' && $attempt->status === 'in_progress') {
            return redirect()->route('dashboard');
        }

        return redirect()->route('learning-sets.quiz', $learningSet);
    }

    public function restart(Request $request, LearningSet $learningSet): RedirectResponse
    {
        $this->ensureOwnership($request, $learningSet);
        abort_unless($learningSet->questions()->exists(), 404);
        $request->user()->quizAttempts()->create([
            'learning_set_id' => $learningSet->id,
            'current_question_index' => 0,
            'total_questions' => $learningSet->questions()->count(),
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return redirect()->route('learning-sets.quiz', $learningSet);
    }

    public function destroy(Request $request, LearningSet $learningSet): RedirectResponse
    {
        $this->ensureOwnership($request, $learningSet);

        DB::transaction(fn () => $learningSet->delete());
        $request->session()->forget('study_mode_attempt');

        return redirect()->route('dashboard')->with('status', '学習セットを削除しました。');
    }

    public function restore(Request $request, int $learningSet): RedirectResponse
    {
        $deletedLearningSet = LearningSet::onlyTrashed()
            ->whereKey($learningSet)
            ->whereBelongsTo($request->user())
            ->firstOrFail();

        DB::transaction(fn () => $deletedLearningSet->restore());

        return redirect()->route('dashboard')->with('status', '学習セットを復元しました。');
    }

    private function ensureOwnership(Request $request, LearningSet $learningSet): void
    {
        abort_unless($learningSet->user()->is($request->user()), 404);
    }

    /**
     * @param  Collection<int, QuizAttempt>  $inProgressAttempts
     * @param  Collection<int, LearningSet>  $newLearningSets
     * @return Collection<int, array{learning_set_id: ?int, collection_ids: array<int, int>, topic: string, display_topic: string, title: string, category: string, resume_url: string, completed_count: int, total_questions: int, status: string, cta_label: string, last_activity_at: Carbon}>
     */
    private function dashboardLearningItems(Collection $inProgressAttempts, Collection $newLearningSets): Collection
    {
        $attemptItems = $inProgressAttempts->map(function (QuizAttempt $attempt): array {
            $subject = $attempt->learningSet ?: $attempt->extensionQuizDraft;

            $topic = $subject->topic ?: ($subject->title ?? $subject->source_title);

            return [
                'learning_set_id' => $attempt->learning_set_id,
                'collection_ids' => $attempt->learningSet?->collections->pluck('id')->all() ?? [],
                'topic' => $topic,
                'display_topic' => $this->displayTopic($topic),
                'title' => $subject->title ?? $subject->source_title,
                'category' => $this->classifier->category($topic.' '.($subject->title ?? $subject->source_title)),
                'resume_url' => $attempt->learning_set_id
                    ? route('learning-sets.quiz', $attempt->learning_set_id)
                    : route('extension-quiz-drafts.quiz', $attempt->extensionQuizDraft->token),
                'completed_count' => $attempt->answers_count,
                'total_questions' => $attempt->total_questions,
                'status' => $attempt->answers_count === 0 ? 'これから学ぶ' : '途中から再開',
                'cta_label' => $attempt->answers_count === 0 ? '学習を始める →' : ($attempt->answers_count + 1).'問目から続ける →',
                'last_activity_at' => $attempt->updated_at,
            ];
        });

        $newItems = $newLearningSets->map(function (LearningSet $learningSet): array {
            $topic = $learningSet->topic ?: $learningSet->title;

            return [
                'learning_set_id' => $learningSet->id,
                'collection_ids' => $learningSet->collections->pluck('id')->all(),
                'topic' => $topic,
                'display_topic' => $this->displayTopic($topic),
                'title' => $learningSet->title,
                'category' => $this->classifier->category($topic.' '.$learningSet->title),
                'resume_url' => route('learning-sets.quiz', $learningSet),
                'completed_count' => 0,
                'total_questions' => $learningSet->questions_count,
                'status' => 'これから学ぶ',
                'cta_label' => '学習を始める →',
                'last_activity_at' => $learningSet->created_at,
            ];
        });

        return $attemptItems
            ->concat($newItems)
            ->sortByDesc('last_activity_at')
            ->take(20)
            ->values();
    }

    private function displayTopic(string $topic): string
    {
        return Str::of($topic)->trim()->replaceEnd('について学ぶ', '')->trim()->toString();
    }

    /**
     * @return Collection<int, array{learning_set_id: int, collection_ids: array<int, int>, topic: string, display_topic: string, title: string, category: string, resume_url: string, completed_count: int, total_questions: int, status: string, cta_label: string, last_activity_at: Carbon}>
     */
    private function searchLearningItems(User $user, string $searchQuery): Collection
    {
        $needle = Str::lower($searchQuery);

        return $user->learningSets()
            ->withCount('questions')
            ->with([
                'collections:id,name',
                'quizAttempts' => fn ($query) => $query->withCount('answers')->latest('updated_at'),
            ])
            ->latest('updated_at')
            ->get()
            ->filter(function (LearningSet $learningSet) use ($needle): bool {
                $category = $this->classifier->category(($learningSet->topic ?? '').' '.$learningSet->title);
                $haystack = Str::lower(implode(' ', [
                    $learningSet->title,
                    $learningSet->topic,
                    $category,
                    $learningSet->collections->pluck('name')->implode(' '),
                ]));

                return Str::contains($haystack, $needle);
            })
            ->take(50)
            ->map(function (LearningSet $learningSet): array {
                $attempt = $learningSet->quizAttempts->firstWhere('status', 'in_progress')
                    ?? $learningSet->quizAttempts->firstWhere('status', 'completed');
                $completed = $attempt?->status === 'completed';
                $completedCount = $completed ? $learningSet->questions_count : ($attempt?->answers_count ?? 0);
                $topic = $learningSet->topic ?: $learningSet->title;

                return [
                    'learning_set_id' => $learningSet->id,
                    'collection_ids' => $learningSet->collections->pluck('id')->all(),
                    'topic' => $topic,
                    'display_topic' => $this->displayTopic($topic),
                    'title' => $learningSet->title,
                    'category' => $this->classifier->category($topic.' '.$learningSet->title),
                    'resume_url' => route('learning-sets.quiz', $learningSet),
                    'completed_count' => $completedCount,
                    'total_questions' => $learningSet->questions_count,
                    'status' => $completed ? '完了済み' : ($completedCount > 0 ? '途中から再開' : 'これから学ぶ'),
                    'cta_label' => $completed ? 'もう一度学ぶ →' : ($completedCount > 0 ? ($completedCount + 1).'問目から続ける →' : '学習を始める →'),
                    'last_activity_at' => $attempt?->updated_at ?? $learningSet->created_at,
                ];
            })
            ->values();
    }
}
