<?php

namespace App\Http\Controllers;

use App\ExtensionAccessTokenAuthenticator;
use App\Models\ExtensionQuizDraft;
use App\Models\LearningSet;
use App\Models\QuizAttempt;
use App\QuizGenerationException;
use App\QuizGenerationService;
use App\Services\LearningCollectionAutoAssignService;
use App\Services\LearningTermMatcher;
use App\Services\QuizAttemptService;
use App\Services\ReviewScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ExtensionQuizController extends Controller
{
    public function generate(Request $request, QuizGenerationService $quizGenerationService, ExtensionAccessTokenAuthenticator $authenticator): JsonResponse
    {
        $accessToken = $authenticator->authenticate($request);

        if ($accessToken === null) {
            return response()->json(['message' => '認証に失敗しました。'], 401);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:500'],
            'url' => ['required', 'url', 'max:5000'],
            'terms' => ['required', 'array', 'min:1', 'max:20'],
            'terms.*.term' => ['required', 'string', 'max:200'],
            'terms.*.description' => ['required', 'string', 'max:2000'],
        ], [
            'terms.min' => '問題を作る用語を1件以上選択してください。',
            'terms.max' => '一度に問題を作れる用語は20件までです。',
        ]);

        try {
            $generatedQuiz = $quizGenerationService->generate($validated['title'], $validated['url'], $validated['terms'], $accessToken->user_id);

            $draft = ExtensionQuizDraft::query()->create([
                'token' => (string) Str::uuid(),
                'user_id' => $accessToken->user_id,
                'source_type' => 'web',
                'source_title' => $validated['title'],
                'subject' => $generatedQuiz['subject'],
                'topic' => $generatedQuiz['topic'],
                'source_url' => $validated['url'],
                'selected_terms' => $validated['terms'],
                'generated_questions' => $generatedQuiz['questions'],
                'expires_at' => now()->addDay(),
            ]);

            return response()->json([
                'draft_token' => $draft->token,
                'preview_url' => route('extension-quiz-drafts.quiz', $draft->token),
            ]);
        } catch (QuizGenerationException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->httpStatus);
        }
    }

    public function quiz(Request $request, string $token, QuizAttemptService $attemptService, LearningTermMatcher $termMatcher): View|RedirectResponse
    {
        if ($this->hasAccountMismatch($request, $token)) {
            return view('extension-quiz-drafts.account-mismatch', [
                'currentUserEmail' => $request->user()->email,
            ]);
        }

        $draft = $this->activeDraft($token);
        $draft = $this->claimDraftForUser($request, $draft);
        $attempt = $request->user()->quizAttempts()->where('extension_quiz_draft_id', $draft->id)->where('status', 'in_progress')->first();
        if (! $attempt && $request->user()->quizAttempts()->where('extension_quiz_draft_id', $draft->id)->where('status', 'completed')->exists()) {
            return redirect()->route('extension-quiz-drafts.result', $token);
        }
        $attempt ??= $attemptService->forDraft($request->user(), $draft);

        return $this->draftQuestionView($request, $draft, $attempt, $attemptService, $termMatcher);
    }

    public function grade(Request $request, string $token, QuizAttemptService $attemptService): RedirectResponse
    {
        $validated = $request->validate([
            'question_index' => ['required', 'integer', 'min:0'],
            'selected_option' => ['nullable', 'string', 'in:A,B,C,D'],
            'return_to' => ['nullable', 'string', 'in:dashboard'],
        ]);

        $draft = $this->activeDraft($token);
        $draft = $this->claimDraftForUser($request, $draft);
        $questionIndex = (int) $validated['question_index'];
        $attempt = $request->user()->quizAttempts()
            ->where('extension_quiz_draft_id', $draft->id)
            ->whereHas('answers', fn ($query) => $query->where('question_index', $questionIndex))
            ->latest('id')
            ->first()
            ?? $attemptService->forDraft($request->user(), $draft);
        $question = $draft->generated_questions[$questionIndex] ?? null;
        abort_unless(is_array($question), 404);

        $attempt = $attemptService->answer($attempt, $questionIndex, $validated['selected_option'] ?? null, null, $question['correct_option']);

        if ($attempt->status === 'completed') {
            return redirect()->route('extension-quiz-drafts.result', $token);
        }

        return ($validated['return_to'] ?? null) === 'dashboard'
            ? redirect()->route('dashboard')
            : redirect()->route('extension-quiz-drafts.quiz', $token);
    }

    public function result(Request $request, string $token): View|RedirectResponse
    {
        $draft = $this->activeDraft($token);
        $attempt = $request->user()->quizAttempts()->where('extension_quiz_draft_id', $draft->id)->where('status', 'completed')->latest('id')->first();
        if (! $attempt) {
            return redirect()->route('extension-quiz-drafts.quiz', $token);
        }

        $answers = $attempt->answers()->get()->keyBy('question_index');
        $correctCount = $answers->where('is_correct', true)->count();
        $unansweredCount = $answers->whereNull('selected_option')->count();

        return view('extension-quiz-drafts.result', [
            'draft' => $draft,
            'answers' => $answers,
            'results' => [
                'correctCount' => $correctCount,
                'incorrectCount' => $attempt->total_questions - $correctCount - $unansweredCount,
                'unansweredCount' => $unansweredCount,
                'totalCount' => $attempt->total_questions,
            ],
        ]);
    }

    public function save(Request $request, string $token, ReviewScheduleService $reviewScheduleService, LearningCollectionAutoAssignService $autoAssignService): RedirectResponse
    {
        $draftForAttempt = $this->activeDraft($token);
        abort_unless($request->user()->quizAttempts()->where('extension_quiz_draft_id', $draftForAttempt->id)->where('status', 'completed')->exists(), 404);

        $learningSet = DB::transaction(function () use ($request, $reviewScheduleService, $token): LearningSet {
            $draft = ExtensionQuizDraft::query()
                ->where('token', $token)
                ->whereNull('claimed_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($draft->user_id !== null && $draft->user_id !== $request->user()->id, 404);

            $sourceLabel = $draft->source_type === 'screenshot' ? 'スクリーンショット学習' : 'Web学習';
            $learningSet = $request->user()->learningSets()->create([
                'title' => Str::limit($draft->source_title, 252 - mb_strlen($sourceLabel), '').' - '.$sourceLabel,
                'subject' => $draft->subject,
                'topic' => $draft->topic,
                'source_type' => $draft->source_type,
                'source_url' => $draft->source_url,
                'source_image_path' => $draft->source_image_path,
            ]);

            foreach ($draft->generated_questions as $question) {
                $learningSet->questions()->create([
                    'question' => $question['question'],
                    'input_question' => $question['input_question'] ?? null,
                    'option_a' => $question['option_a'],
                    'option_b' => $question['option_b'],
                    'option_c' => $question['option_c'],
                    'option_d' => $question['option_d'],
                    'correct_option' => $question['correct_option'],
                    'explanation' => $question['explanation'],
                    ...$reviewScheduleService->initialSchedule(),
                ]);
            }

            $learningTerms = collect($draft->selected_terms)
                ->filter(fn (mixed $selectedTerm): bool => is_array($selectedTerm) && is_string($selectedTerm['term'] ?? null))
                ->map(function (array $selectedTerm) use ($learningSet): array {
                    $description = $selectedTerm['description'] ?? null;

                    return [
                        'term' => Str::limit(trim($selectedTerm['term']), 200, ''),
                        'description' => is_string($description) && trim($description) !== '' ? trim($description) : null,
                        'source_type' => $learningSet->source_type,
                        'source_url' => $learningSet->source_url,
                    ];
                })
                ->filter(fn (array $selectedTerm): bool => $selectedTerm['term'] !== '')
                ->unique(fn (array $selectedTerm): string => Str::lower($selectedTerm['term']))
                ->values()
                ->all();

            $learningSet->learningTerms()->createMany($learningTerms);

            $draft->update(['claimed_at' => now()]);

            return $learningSet;
        });

        try {
            $autoAssignService->assign($learningSet);
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('dashboard')
            ->with('status', '学習セットを保存しました。');
    }

    private function activeDraft(string $token): ExtensionQuizDraft
    {
        return ExtensionQuizDraft::query()
            ->where('token', $token)
            ->where(function ($query): void {
                $query->whereNull('user_id')->orWhere('user_id', auth()->id());
            })
            ->whereJsonLength('generated_questions', '>', 0)
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();
    }

    private function hasAccountMismatch(Request $request, string $token): bool
    {
        return ExtensionQuizDraft::query()
            ->where('token', $token)
            ->where('source_type', 'web')
            ->whereNotNull('user_id')
            ->where('user_id', '!=', $request->user()->id)
            ->whereJsonLength('generated_questions', '>', 0)
            ->whereNull('claimed_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function claimDraftForUser(Request $request, ExtensionQuizDraft $draft): ExtensionQuizDraft
    {
        return DB::transaction(function () use ($request, $draft): ExtensionQuizDraft {
            $lockedDraft = ExtensionQuizDraft::query()->whereKey($draft)->lockForUpdate()->firstOrFail();
            abort_if($lockedDraft->user_id !== null && $lockedDraft->user_id !== $request->user()->id, 404);

            if ($lockedDraft->user_id === null) {
                $lockedDraft->update(['user_id' => $request->user()->id]);
            }

            return $lockedDraft;
        });
    }

    private function draftQuestionView(Request $request, ExtensionQuizDraft $draft, QuizAttempt $attempt, QuizAttemptService $attemptService, LearningTermMatcher $termMatcher): View
    {
        $index = $attemptService->firstUnansweredIndex($attempt);
        $question = $draft->generated_questions[$index];
        $learningSetContext = new LearningSet([
            'title' => $draft->source_title,
            'subject' => $draft->subject,
            'topic' => $draft->topic,
        ]);
        $correctOption = strtolower((string) ($question['correct_option'] ?? ''));
        $correctTerm = in_array($correctOption, ['a', 'b', 'c', 'd'], true)
            ? ($question['option_'.$correctOption] ?? null)
            : null;

        return view('extension-quiz-drafts.quiz', [
            'draft' => $draft,
            'attempt' => $attempt,
            'question' => $question,
            'questionIndex' => $index,
            'explanationSegments' => $termMatcher->segmentsFor(
                $request->user(),
                $learningSetContext,
                $question['explanation'] ?? '',
                is_string($correctTerm) ? [$correctTerm] : [],
            ),
        ]);
    }
}
