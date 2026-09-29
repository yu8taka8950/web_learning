<?php

namespace App\Http\Controllers;

use App\Models\ExtensionQuizDraft;
use App\Models\LearningCapture;
use App\QuizGenerationException;
use App\QuizGenerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LearningCaptureController extends Controller
{
    public function index(Request $request): View
    {
        $captures = $request->user()->learningCaptures()->with(['terms' => fn ($query) => $query->orderBy('sort_order')->limit(5)])->withCount('terms')->latest('captured_at')->paginate(12);

        return view('captures.index', compact('captures'));
    }

    public function show(Request $request, LearningCapture $capture): View
    {
        $this->ensureOwnership($request, $capture);
        $capture->load(['terms' => fn ($query) => $query->orderBy('sort_order')]);

        return view('captures.show', compact('capture'));
    }

    public function generate(Request $request, LearningCapture $capture, QuizGenerationService $quizGenerationService): RedirectResponse
    {
        $this->ensureOwnership($request, $capture);
        $validated = $request->validate(['term_ids' => ['required', 'array', 'min:1', 'max:20'], 'term_ids.*' => ['integer']]);
        $terms = $capture->terms()->whereIn('id', $validated['term_ids'])->orderBy('sort_order')->get();
        if ($terms->count() !== count(array_unique($validated['term_ids']))) {
            abort(404);
        }

        try {
            $selectedTerms = $terms->map(fn ($term): array => ['term' => $term->term, 'description' => $term->explanation ?: 'この用語について学びます。'])->all();
            $generatedQuiz = $quizGenerationService->generate($capture->page_title, $capture->source_url, $selectedTerms);
            $draft = ExtensionQuizDraft::query()->create(['token' => (string) Str::uuid(), 'user_id' => $request->user()->id, 'source_type' => 'web', 'source_title' => $capture->page_title, 'subject' => $generatedQuiz['subject'], 'topic' => $generatedQuiz['topic'], 'source_url' => $capture->source_url, 'selected_terms' => $selectedTerms, 'generated_questions' => $generatedQuiz['questions'], 'expires_at' => now()->addDay()]);
            $capture->update(['status' => 'quiz_generated']);

            return redirect()->route('extension-quiz-drafts.quiz', $draft->token);
        } catch (QuizGenerationException $exception) {
            return back()->withErrors(['term_ids' => $exception->getMessage()]);
        }
    }

    public function destroy(Request $request, LearningCapture $capture): RedirectResponse
    {
        $this->ensureOwnership($request, $capture);
        $capture->delete();

        return redirect()->route('captures.index')->with('status', 'Web学習リストを削除しました。');
    }

    private function ensureOwnership(Request $request, LearningCapture $capture): void
    {
        abort_unless($capture->user_id === $request->user()->id, 404);
    }
}
