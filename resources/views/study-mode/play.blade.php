<x-app-layout>
    <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
        <header class="border-b border-stone-300 pb-6 dark:border-stone-700">
            <div class="flex items-center justify-between gap-5"><p class="text-xs font-semibold tracking-[0.2em] text-[#3155D9]">{{ $attempt['mode'] === 'input' ? 'INPUT MODE' : 'RANDOM MODE' }}</p><p class="text-sm font-semibold">問題 {{ $attempt['current_index'] + 1 }} / {{ count($attempt['question_ids']) }}</p></div>
            <div class="mt-4 h-1 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700"><div class="h-full bg-[#3155D9]" style="width: {{ (($attempt['current_index'] + 1) / count($attempt['question_ids'])) * 100 }}%"></div></div>
        </header>

        <section class="py-8">
            <h1 class="selection-source text-xl font-semibold leading-9 sm:text-2xl" data-selection-source data-selection-field="{{ $attempt['mode'] === 'input' ? 'input_question' : 'question' }}" data-question-id="{{ $question->id }}">{{ $displayQuestion }}</h1>

            @if ($attempt['feedback'] === null)
                <form method="POST" action="{{ route('study-mode.answer') }}" class="mt-8" x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf<input type="hidden" name="question_id" value="{{ $question->id }}"><input type="hidden" name="answer_token" value="{{ $attempt['answer_token'] }}">
                    @if ($attempt['mode'] === 'input')
                        <label for="typed_answer" class="text-sm font-semibold">回答</label>
                        <input id="typed_answer" name="typed_answer" value="{{ old('typed_answer') }}" autocomplete="off" placeholder="回答を入力してください" autofocus class="mt-3 min-h-14 w-full rounded-lg border-stone-300 bg-white px-4 focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">
                    @else
                        <fieldset><legend class="sr-only">回答を選択</legend><div class="grid gap-3">@foreach (['A' => $question->option_a, 'B' => $question->option_b, 'C' => $question->option_c, 'D' => $question->option_d] as $key => $option)<label class="flex min-h-14 cursor-pointer items-start gap-4 rounded-lg border border-stone-300 bg-white p-4 transition hover:border-[#3155D9] has-[:checked]:border-[#3155D9] has-[:checked]:bg-blue-50/60 dark:border-stone-700 dark:bg-stone-900"><input type="radio" name="selected_option" value="{{ $key }}" class="mt-1 text-[#3155D9] focus:ring-[#3155D9]"><span class="leading-7"><strong>{{ $key }}.</strong> {{ $option }}</span></label>@endforeach</div></fieldset>
                    @endif
                    <x-input-error :messages="$errors->get('typed_answer')" class="mt-3" /><x-input-error :messages="$errors->get('selected_option')" class="mt-3" />
                    <button type="submit" :disabled="submitting" class="mt-6 inline-flex min-h-12 items-center rounded-lg bg-[#3155D9] px-7 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">答え合わせ →</button>
                    <p class="mt-2 text-xs text-stone-500 dark:text-stone-400">空欄のまま進むと未回答として記録されます</p>
                </form>
            @else
                @php
                    $feedback = $attempt['feedback'];
                @endphp
                <div class="mt-8 rounded-xl border border-stone-300 bg-white p-6 dark:border-stone-700 dark:bg-stone-900">
                    <p @class(['text-sm font-semibold', 'text-[#178C78]' => $feedback['status'] === 'correct', 'text-[#B4534B]' => $feedback['status'] === 'incorrect', 'text-stone-500' => $feedback['status'] === 'unanswered'])>{{ ['correct' => '● 正解', 'incorrect' => '● 不正解', 'unanswered' => '○ 未回答'][$feedback['status']] }}</p>
                    <dl class="mt-6 grid gap-5 sm:grid-cols-2"><div><dt class="text-xs text-stone-500">あなたの回答</dt><dd class="mt-2 leading-7">@if ($feedback['status'] === 'unanswered')未回答@elseif ($attempt['mode'] === 'random'){{ $feedback['selected_option'] }}. {{ $question->{'option_'.strtolower($feedback['selected_option'])} }}@else{{ $feedback['answer'] }}@endif</dd></div><div class="border-t border-stone-200 pt-5 sm:border-s sm:border-t-0 sm:ps-6 sm:pt-0 dark:border-stone-700"><dt class="text-xs text-[#178C78]">正解</dt><dd class="mt-2 leading-7">@if ($attempt['mode'] === 'random'){{ $feedback['correct_option'] }}. @endif{{ $feedback['correct_text'] }}</dd></div></dl>
                </div>
                <form method="POST" action="{{ route('study-mode.next') }}" class="mt-6" x-data="{ submitting: false }" @submit="submitting = true">@csrf<input type="hidden" name="next_token" value="{{ $attempt['next_token'] }}"><button type="submit" :disabled="submitting" class="inline-flex min-h-12 items-center rounded-lg bg-[#3155D9] px-7 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60">{{ $attempt['current_index'] + 1 === count($attempt['question_ids']) ? '結果を見る →' : '次にすすむ →' }}</button></form>
            @endif

            @include('quiz.partials.solution-details', ['question' => $question, 'solutionOpen' => $attempt['feedback'] !== null, 'references' => [['label' => $question->learningSet->title, 'url' => $question->learningSet->source_url]]])
        </section>
    </main>
</x-app-layout>
