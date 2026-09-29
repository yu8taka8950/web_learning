<details @if ($solutionOpen ?? false) open @endif @if ($solutionToggleEvent ?? false) x-on:toggle="$dispatch('solution-toggled', { open: $event.target.open })" @endif class="mt-8 border-y border-stone-300 dark:border-stone-700">
    <summary class="cursor-pointer py-4 font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">正解を見る</summary>
    <div class="border-t border-stone-200 py-5 dark:border-stone-700">
        <p class="text-xs font-semibold tracking-[0.16em] text-[#178C78]">正解</p>
        <p class="mt-2 leading-7">{{ $question->correct_option }}. {{ $question->{'option_'.strtolower($question->correct_option)} }}</p>
        <div class="mt-6 border-t border-stone-200 pt-5 dark:border-stone-700">
            @include('quiz.partials.learning-assistant', ['question' => $question, 'explanationSegments' => $explanationSegments ?? []])
        </div>
        <div class="mt-5 border-t border-stone-200 pt-1 dark:border-stone-700">
            @include('quiz.partials.reference-details', ['references' => $references])
        </div>
    </div>
</details>
@include('quiz.partials.selection-toolbar')
