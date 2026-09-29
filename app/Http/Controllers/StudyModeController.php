<?php

namespace App\Http\Controllers;

use App\Models\LearningCollection;
use App\Models\LearningSet;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Normalizer;

class StudyModeController extends Controller
{
    private const SESSION_KEY = 'study_mode_attempt';

    public function index(Request $request): View
    {
        $questionCount = $this->ownedQuestions($request->user())->count();

        return view('study-mode.index', compact('questionCount'));
    }

    public function configure(Request $request, string $mode): View
    {
        $this->ensureMode($mode);

        $learningSets = $request->user()->learningSets()
            ->whereHas('questions')
            ->withCount('questions')
            ->orderBy('title')
            ->get();
        $questionCount = $learningSets->sum('questions_count');
        $collections = $request->user()->learningCollections()
            ->with(['learningSets' => fn ($query) => $query->whereHas('questions')->withCount('questions')])
            ->orderBy('name')
            ->get()
            ->each(function (LearningCollection $collection): void {
                $collection->setAttribute('learning_sets_count', $collection->learningSets->count());
                $collection->setAttribute('questions_count', $collection->learningSets->sum('questions_count'));
            })
            ->filter(fn (LearningCollection $collection): bool => $collection->questions_count > 0)
            ->values();
        $selectedCollectionId = null;

        if ($request->filled('collection')) {
            $selectedCollectionId = $request->user()->learningCollections()
                ->whereKey($request->integer('collection'))
                ->whereHas('learningSets.questions')
                ->firstOrFail()
                ->id;
        }

        return view('study-mode.configure', compact('mode', 'learningSets', 'collections', 'questionCount', 'selectedCollectionId'));
    }

    public function start(Request $request, string $mode): RedirectResponse
    {
        $this->ensureMode($mode);

        $validated = $request->validate([
            'scope' => ['required', Rule::in(['all', 'collection', 'learning_set'])],
            'collection_id' => ['nullable', 'integer', 'required_if:scope,collection'],
            'learning_set_id' => ['nullable', 'integer', 'required_if:scope,learning_set'],
            'question_count' => ['required', 'integer', Rule::in([5, 10, 20])],
        ]);

        $questionQuery = $this->ownedQuestions($request->user());
        $learningSetId = null;
        $collectionId = null;

        if ($validated['scope'] === 'collection') {
            $collectionId = (int) $validated['collection_id'];
            $collection = $request->user()->learningCollections()
                ->whereKey($collectionId)
                ->whereHas('learningSets.questions')
                ->firstOrFail();
            $questionQuery->whereHas(
                'learningSet.collections',
                fn (Builder $query) => $query->whereKey($collection)
            );
        }

        if ($validated['scope'] === 'learning_set') {
            $learningSetId = (int) $validated['learning_set_id'];
            $learningSet = $request->user()->learningSets()
                ->whereKey($learningSetId)
                ->whereHas('questions')
                ->firstOrFail();
            $questionQuery->where('learning_set_id', $learningSet->id);
        }

        $questionIds = $questionQuery
            ->inRandomOrder()
            ->limit((int) $validated['question_count'])
            ->pluck('id')
            ->map(fn (int $questionId): int => $questionId)
            ->all();

        if ($questionIds === []) {
            return redirect()->route("study-mode.{$mode}")->with('status', 'まだ学習できる問題がありません。');
        }

        $request->session()->put(self::SESSION_KEY, [
            'mode' => $mode,
            'question_ids' => $questionIds,
            'current_index' => 0,
            'answers' => [],
            'feedback' => null,
            'answer_token' => Str::random(40),
            'next_token' => null,
            'settings' => [
                'scope' => $validated['scope'],
                'learning_set_id' => $learningSetId,
                'collection_id' => $collectionId,
                'question_count' => (int) $validated['question_count'],
            ],
        ]);

        return redirect()->route('study-mode.play');
    }

    public function play(Request $request): View|RedirectResponse
    {
        $attempt = $this->attempt($request);

        if ($attempt['current_index'] >= count($attempt['question_ids'])) {
            return redirect()->route('study-mode.result');
        }

        $question = $this->ownedQuestions($request->user())
            ->with('learningSet:id,title,source_url')
            ->find($attempt['question_ids'][$attempt['current_index']]);

        if (! $question) {
            return $this->handleUnavailableQuestion($request, $attempt['question_ids'][$attempt['current_index']]);
        }

        $displayQuestion = $this->displayQuestion($question, $attempt['mode']);

        return view('study-mode.play', compact('attempt', 'question', 'displayQuestion'));
    }

    public function answer(Request $request): RedirectResponse
    {
        $attempt = $this->attempt($request);
        $questionId = $attempt['question_ids'][$attempt['current_index']] ?? null;
        abort_unless(is_int($questionId), 409);

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'answer_token' => ['required', 'string'],
            'typed_answer' => ['nullable', 'string', 'max:1000'],
            'selected_option' => ['nullable', 'string', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        abort_unless((int) $validated['question_id'] === $questionId, 409);

        if ($attempt['feedback'] !== null || ! hash_equals((string) $attempt['answer_token'], $validated['answer_token'])) {
            return redirect()->route('study-mode.play');
        }

        $question = $this->ownedQuestions($request->user())->find($questionId);

        if (! $question) {
            return $this->handleUnavailableQuestion($request, $questionId);
        }
        $correctOptionText = $this->correctOptionText($question);

        if ($attempt['mode'] === 'input') {
            $submittedAnswer = $validated['typed_answer'] ?? null;
            $normalizedAnswer = $this->normalizeAnswer($submittedAnswer);
            $status = $normalizedAnswer === ''
                ? 'unanswered'
                : ($normalizedAnswer === $this->normalizeAnswer($correctOptionText) ? 'correct' : 'incorrect');
            $answer = $status === 'unanswered' ? null : trim((string) $submittedAnswer);
            $selectedOption = null;
        } else {
            $selectedOption = $validated['selected_option'] ?? null;
            $status = $selectedOption === null
                ? 'unanswered'
                : ($selectedOption === $question->correct_option ? 'correct' : 'incorrect');
            $answer = $selectedOption;
        }

        $feedback = [
            'question_id' => $question->id,
            'status' => $status,
            'answer' => $answer,
            'selected_option' => $selectedOption,
            'correct_option' => $question->correct_option,
            'correct_text' => $correctOptionText,
        ];
        $attempt['answers'][$attempt['current_index']] = $feedback;
        $attempt['feedback'] = $feedback;
        $attempt['answer_token'] = null;
        $attempt['next_token'] = Str::random(40);
        $request->session()->put(self::SESSION_KEY, $attempt);

        return redirect()->route('study-mode.play');
    }

    public function next(Request $request): RedirectResponse
    {
        $attempt = $this->attempt($request);
        $validated = $request->validate(['next_token' => ['required', 'string']]);

        if ($attempt['feedback'] === null || ! hash_equals((string) $attempt['next_token'], $validated['next_token'])) {
            return redirect()->route('study-mode.play');
        }

        $attempt['current_index']++;
        $attempt['feedback'] = null;
        $attempt['answer_token'] = Str::random(40);
        $attempt['next_token'] = null;
        $request->session()->put(self::SESSION_KEY, $attempt);

        return redirect()->route(
            $attempt['current_index'] >= count($attempt['question_ids']) ? 'study-mode.result' : 'study-mode.play'
        );
    }

    public function result(Request $request): View|RedirectResponse
    {
        $attempt = $this->attempt($request);

        if ($attempt['current_index'] < count($attempt['question_ids'])) {
            return redirect()->route('study-mode.play');
        }

        $questions = $this->ownedQuestions($request->user())
            ->whereKey($attempt['question_ids'])
            ->get()
            ->keyBy('id');
        if ($questions->count() !== count($attempt['question_ids'])) {
            $ownedIncludingDeletedCount = LearningSet::withTrashed()
                ->whereBelongsTo($request->user())
                ->whereHas('questions', fn (Builder $query) => $query->whereKey($attempt['question_ids']))
                ->withCount(['questions' => fn (Builder $query) => $query->whereKey($attempt['question_ids'])])
                ->get()
                ->sum('questions_count');
            abort_unless($ownedIncludingDeletedCount === count($attempt['question_ids']), 404);

            return $this->interruptUnavailableAttempt($request);
        }

        $results = collect($attempt['question_ids'])->map(function (int $questionId, int $index) use ($attempt, $questions): array {
            $question = $questions->get($questionId);

            return [
                'question' => $question,
                'answer' => $attempt['answers'][$index],
                'display_question' => $this->displayQuestion($question, $attempt['mode']),
            ];
        });
        $correctCount = $results->where('answer.status', 'correct')->count();
        $unansweredCount = $results->where('answer.status', 'unanswered')->count();
        $summary = [
            'correctCount' => $correctCount,
            'incorrectCount' => $results->count() - $correctCount - $unansweredCount,
            'unansweredCount' => $unansweredCount,
            'totalCount' => $results->count(),
            'percentage' => $results->isEmpty() ? 0 : (int) round($correctCount / $results->count() * 100),
        ];

        return view('study-mode.result', compact('attempt', 'results', 'summary'));
    }

    private function ensureMode(string $mode): void
    {
        abort_unless(in_array($mode, ['input', 'random'], true), 404);
    }

    private function ownedQuestions(User $user): Builder
    {
        return Question::query()->whereHas(
            'learningSet',
            fn (Builder $query) => $query->whereBelongsTo($user)
        );
    }

    private function correctOptionText(Question $question): string
    {
        $attribute = 'option_'.strtolower($question->correct_option);

        return (string) $question->{$attribute};
    }

    private function displayQuestion(Question $question, string $mode): string
    {
        return $mode === 'input' ? ($question->input_question ?: $question->question) : $question->question;
    }

    private function interruptUnavailableAttempt(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('study-mode.index')
            ->with('status', 'この学習セットは削除されたため、学習を続けられません。');
    }

    private function handleUnavailableQuestion(Request $request, int $questionId): RedirectResponse
    {
        $belongsToUser = LearningSet::withTrashed()
            ->whereBelongsTo($request->user())
            ->whereHas('questions', fn (Builder $query) => $query->whereKey($questionId))
            ->exists();
        abort_unless($belongsToUser, 404);

        return $this->interruptUnavailableAttempt($request);
    }

    private function normalizeAnswer(?string $answer): string
    {
        $normalized = Normalizer::normalize($answer ?? '', Normalizer::FORM_KC) ?: '';
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? '';

        return mb_strtolower($normalized);
    }

    /**
     * @return array{mode: string, question_ids: array<int, int>, current_index: int, answers: array<int, array<string, mixed>>, feedback: ?array<string, mixed>, answer_token: ?string, next_token: ?string, settings: array<string, mixed>}
     */
    private function attempt(Request $request): array
    {
        $attempt = $request->session()->get(self::SESSION_KEY);

        if (! is_array($attempt)) {
            abort(404);
        }

        return $attempt;
    }
}
