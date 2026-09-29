<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class ReviewScheduleService
{
    /**
     * @return array{review_stage: int, next_review_at: CarbonImmutable, review_count: int, correct_review_count: int}
     */
    public function initialSchedule(?CarbonImmutable $scheduledFrom = null): array
    {
        $scheduledFrom ??= CarbonImmutable::now();

        return [
            'review_stage' => 0,
            'next_review_at' => $scheduledFrom->addDay(),
            'review_count' => 0,
            'correct_review_count' => 0,
        ];
    }

    /**
     * @return array{review_stage: int, next_review_at: CarbonImmutable}
     */
    public function scheduleAfterAnswer(int $currentStage, bool $isCorrect, ?CarbonImmutable $reviewedAt = null): array
    {
        $reviewedAt ??= CarbonImmutable::now();

        if (! $isCorrect) {
            return ['review_stage' => 0, 'next_review_at' => $reviewedAt->addDay()];
        }

        $nextStage = min($currentStage + 1, 3);
        $intervalDays = match ($currentStage) {
            0 => 3,
            1 => 7,
            default => 14,
        };

        return [
            'review_stage' => $nextStage,
            'next_review_at' => $reviewedAt->addDays($intervalDays),
        ];
    }
}
