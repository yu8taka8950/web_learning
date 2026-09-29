<?php

use App\Services\ReviewScheduleService;
use Carbon\CarbonImmutable;

test('initial review is scheduled for the next day', function () {
    $now = CarbonImmutable::parse('2026-09-03 10:00:00', 'Asia/Tokyo');

    expect((new ReviewScheduleService)->initialSchedule($now))->toMatchArray([
        'review_stage' => 0,
        'next_review_at' => $now->addDay(),
        'review_count' => 0,
        'correct_review_count' => 0,
    ]);
});

test('correct answers advance through the fixed review intervals', function (int $stage, int $expectedStage, int $days) {
    $now = CarbonImmutable::parse('2026-09-03 10:00:00', 'Asia/Tokyo');

    expect((new ReviewScheduleService)->scheduleAfterAnswer($stage, true, $now))->toMatchArray([
        'review_stage' => $expectedStage,
        'next_review_at' => $now->addDays($days),
    ]);
})->with([
    'stage 0 advances to stage 1 after 3 days' => [0, 1, 3],
    'stage 1 advances to stage 2 after 7 days' => [1, 2, 7],
    'stage 2 advances to stage 3 after 14 days' => [2, 3, 14],
    'stage 3 remains capped after 14 days' => [3, 3, 14],
]);

test('an incorrect answer resets the schedule to tomorrow', function () {
    $now = CarbonImmutable::parse('2026-09-03 10:00:00', 'Asia/Tokyo');

    expect((new ReviewScheduleService)->scheduleAfterAnswer(3, false, $now))->toMatchArray([
        'review_stage' => 0,
        'next_review_at' => $now->addDay(),
    ]);
});
