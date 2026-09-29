<x-app-layout>
    <div class="py-10 sm:py-14">
        <main class="mx-auto max-w-3xl px-4 sm:px-6">
            <p class="text-sm font-semibold text-[#3155D9]">今日の復習結果</p>
            <h1 class="font-editorial mt-2 text-3xl font-semibold">{{ $results['correctCount'] }} / {{ $results['totalCount'] }}問 正解</h1>
            <section class="mt-8 border-y border-stone-300 py-7 dark:border-stone-700">
                <p class="text-xl font-semibold">正答率 {{ $results['percentage'] }}%</p>
                <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-3">
                    <div><dt class="inline font-medium">正解：</dt><dd class="inline">{{ $results['correctCount'] }}問</dd></div>
                    <div><dt class="inline font-medium">不正解：</dt><dd class="inline">{{ $results['incorrectCount'] }}問</dd></div>
                    <div><dt class="inline font-medium">未回答：</dt><dd class="inline">{{ $results['unansweredCount'] }}問</dd></div>
                </dl>
            </section>

            <section class="mt-8" aria-labelledby="review-results-title">
                <h2 id="review-results-title" class="text-lg font-semibold">問題別結果</h2>
                <ol class="mt-4 grid gap-5">
                    @foreach ($items as $item)
                        @php($answer = $item['answer'])
                        @php($question = $item['question'])
                        <li class="rounded-lg border border-stone-300 p-5 dark:border-stone-700">
                            <p class="font-semibold">問題{{ $loop->iteration }}：{{ $question->question }}</p>
                            <p class="mt-3 font-semibold {{ $answer->is_correct ? 'text-[#178C78]' : 'text-red-700 dark:text-red-300' }}">{{ $answer->is_correct ? '○ 正解' : '× 不正解' }}</p>
                            <dl class="mt-3 grid gap-2 text-sm">
                                <div><dt class="inline font-medium">あなたの回答：</dt><dd class="inline">{{ $answer->selected_option === null ? '未回答' : $answer->selected_option.'. '.$question->{'option_'.strtolower($answer->selected_option)} }}</dd></div>
                                <div><dt class="inline font-medium">正解：</dt><dd class="inline">{{ $question->correct_option }}. {{ $question->{'option_'.strtolower($question->correct_option)} }}</dd></div>
                                @if ($question->explanation)
                                    <div class="rounded-lg bg-stone-100 p-4 leading-7 dark:bg-stone-900"><dt class="font-medium">解説：</dt><dd class="mt-1">{{ $question->explanation }}</dd></div>
                                @endif
                                <div><dt class="inline font-medium">次回復習：</dt><dd class="inline">{{ $question->next_review_at->isoFormat('M月D日') }}</dd></div>
                            </dl>
                        </li>
                    @endforeach
                </ol>
            </section>

            <a href="{{ route('dashboard') }}" class="mt-8 inline-flex rounded-lg bg-[#3155D9] px-5 py-3 text-sm font-semibold text-white">Dashboardへ戻る</a>
        </main>
    </div>
</x-app-layout>
