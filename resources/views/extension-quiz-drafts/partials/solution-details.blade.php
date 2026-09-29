<details class="mt-8 border-y border-stone-300 dark:border-stone-700" @toggle="showCorrect = $event.target.open">
    <summary class="cursor-pointer py-4 font-semibold">正解を見る</summary>
    <div class="border-t border-stone-200 py-5 dark:border-stone-700">
        <p class="text-xs font-semibold tracking-[0.16em] text-[#178C78]">正解</p>
        <p class="mt-2 leading-7">{{ $question['correct_option'] }}. {{ $question['option_'.strtolower($question['correct_option'])] }}</p>
        <div class="mt-6 border-t border-stone-200 pt-5 dark:border-stone-700">
            @include('quiz.partials.learning-assistant', ['question' => $question, 'questionId' => null, 'enableAi' => false, 'explanationSegments' => $explanationSegments, 'explanationHeading' => '解説を見る'])
        </div>
        <div class="mt-5 border-t border-stone-200 pt-1 dark:border-stone-700">
            @include('quiz.partials.reference-details', ['references' => [['label' => $draft->source_title, 'url' => $draft->source_url]]])
        </div>
    </div>
</details>
@include('quiz.partials.selection-toolbar', ['canSave' => false])
