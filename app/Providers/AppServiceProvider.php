<?php

namespace App\Providers;

use App\Models\Question;
use App\Services\LearningAiService;
use App\Services\LearningTermMatcher;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer(['learning-sets.quiz', 'reviews.today', 'study-mode.play'], function ($view): void {
            $user = auth()->user();
            $question = $view->getData()['question'] ?? null;

            if ($user !== null) {
                $view->with('learningAiRemaining', app(LearningAiService::class)->remainingFor($user));
            }

            if ($user !== null && $question instanceof Question) {
                $learningSet = $question->learningSet()->first(['id', 'subject', 'topic', 'title']);
                if ($learningSet !== null) {
                    $correctOption = strtolower((string) $question->correct_option);
                    $correctTerm = in_array($correctOption, ['a', 'b', 'c', 'd'], true)
                        ? $question->{'option_'.$correctOption}
                        : null;
                    $view->with('explanationSegments', app(LearningTermMatcher::class)->segmentsFor(
                        $user,
                        $learningSet,
                        $question->explanation,
                        is_string($correctTerm) ? [$correctTerm] : [],
                    ));
                }
            }
        });
    }
}
