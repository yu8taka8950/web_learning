<?php

namespace App\Http\Controllers;

use App\Models\LearningSet;
use App\Services\LearningCollectionAutoAssignService;
use App\Services\ReviewScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class QuestionController extends Controller
{
    public function create(Request $request, LearningSet $learningSet): View
    {
        $this->ensureOwnership($request, $learningSet);

        return view('questions.create', ['learningSet' => $learningSet]);
    }

    public function store(Request $request, LearningSet $learningSet, ReviewScheduleService $reviewScheduleService, LearningCollectionAutoAssignService $autoAssignService): RedirectResponse
    {
        $this->ensureOwnership($request, $learningSet);

        $validated = $request->validate([
            'question' => ['required', 'string'],
            'option_a' => ['required', 'string'],
            'option_b' => ['required', 'string'],
            'option_c' => ['required', 'string'],
            'option_d' => ['required', 'string'],
            'correct_option' => ['required', 'in:A,B,C,D'],
            'explanation' => ['nullable', 'string'],
        ]);

        $learningSet->questions()->create([
            ...$validated,
            ...$reviewScheduleService->initialSchedule(),
        ]);

        try {
            $autoAssignService->assign($learningSet);
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('learning-sets.quiz', $learningSet)
            ->with('status', '問題を追加しました。');
    }

    private function ensureOwnership(Request $request, LearningSet $learningSet): void
    {
        abort_unless($learningSet->user()->is($request->user()), 404);
    }
}
