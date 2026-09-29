<x-app-layout>
    <x-slot name="header">
        <h1 class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">学習状況</h1>
    </x-slot>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14 lg:grid lg:grid-cols-[minmax(0,7fr)_minmax(15rem,3fr)] lg:gap-x-0">
        <section aria-labelledby="today-title" class="border-b border-stone-300 pb-9 lg:col-start-1 lg:row-start-1 lg:pe-10 dark:border-stone-700">
            <div class="flex items-baseline gap-3">
                <h2 id="today-title" class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">今日</h2>
                <p class="text-xs text-stone-500 dark:text-stone-400">{{ now(config('app.timezone'))->format('n月j日') }}</p>
            </div>
            <dl class="mt-6 grid grid-cols-3 gap-x-5">
                <div><dt class="text-[13px] text-stone-500 dark:text-stone-400">解いた問題</dt><dd class="mt-1 font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">{{ number_format($todayStats['answered']) }}問</dd></div>
                <div><dt class="text-[13px] text-stone-500 dark:text-stone-400">正解数</dt><dd class="mt-1 font-editorial text-2xl font-semibold text-[#178C78]">{{ number_format($todayStats['correct']) }}問</dd></div>
                <div><dt class="text-[13px] text-stone-500 dark:text-stone-400">正答率</dt><dd class="mt-1 font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">{{ $todayStats['accuracy'] }}{{ is_int($todayStats['accuracy']) ? '%' : '' }}</dd></div>
            </dl>
            <div class="mt-7 flex flex-wrap items-center justify-between gap-x-5 gap-y-3 border-t border-stone-200 pt-5 dark:border-stone-700">
                <p class="text-sm text-stone-500 dark:text-stone-400">復習する問題 <span class="ms-2 font-editorial text-xl font-semibold text-[#171717] dark:text-stone-100">{{ number_format($dueReviewCount) }}問</span></p>
                @if ($dueReviewCount > 0)
                    <a href="{{ route('reviews.today') }}" class="inline-flex min-h-11 shrink-0 items-center text-sm font-semibold text-[#3155D9] underline decoration-stone-300 underline-offset-4 transition hover:decoration-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 dark:decoration-stone-600">復習を始める →</a>
                @endif
            </div>
        </section>

        <section aria-labelledby="week-title" class="border-b border-stone-300 py-9 lg:col-start-2 lg:row-start-1 lg:border-s lg:py-0 lg:ps-10 dark:border-stone-700">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="week-title" class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">今週</h2>
                <p class="text-right text-[11px] text-stone-500 dark:text-stone-400">{{ $weekStats->first()['date'] }}〜{{ $weekStats->last()['date'] }}</p>
            </div>
            <div class="mt-6 grid grid-cols-7 gap-1" data-weekly-days>
                @foreach ($weekStats as $day)
                    @php
                        $circleClass = match (true) {
                            $day['count'] === 0 => 'bg-stone-200 dark:bg-stone-700',
                            $day['count'] <= 3 => 'bg-[#BFD0FF]',
                            $day['count'] <= 7 => 'bg-[#78BDAE]',
                            default => 'bg-[#3155D9]',
                        };
                        $isToday = $day['date'] === now(config('app.timezone'))->format('n/j');
                    @endphp
                    <div @class(['flex min-w-0 flex-col items-center gap-1.5 rounded-sm border border-transparent py-2 text-center transition-colors duration-150 hover:border-[#3155D9]/45 hover:bg-[#EEF3FF]', 'bg-[#EEF3FF] text-[#3155D9] dark:bg-blue-950/30' => $isToday]) role="img" aria-label="{{ $day['weekday_label'] }} {{ $day['count'] }}問">
                        <p class="text-[11px] font-medium text-stone-600 dark:text-stone-300">{{ $day['weekday'] }}</p>
                        <p class="text-[10px] tabular-nums text-stone-500 dark:text-stone-400">{{ $day['date'] }}</p>
                        <span class="mt-1 size-3.5 shrink-0 rounded-full {{ $circleClass }}" aria-hidden="true"></span>
                        <p class="text-[10px] font-medium tabular-nums text-[#171717] dark:text-stone-100">{{ $day['count'] }}問</p>
                    </div>
                @endforeach
            </div>
            @if ($weekStats->sum('count') === 0)
                <p class="mt-5 text-xs text-stone-500 dark:text-stone-400">今週の学習記録はまだありません。</p>
            @endif
            <p class="mt-7 text-xs leading-6 text-stone-500 dark:text-stone-400"><span class="text-[#178C78]">今週もコツコツと。</span><br>継続することで、理解は確実に深まります。</p>
        </section>

        <section aria-labelledby="history-title" class="border-b border-stone-300 py-9 lg:col-start-2 lg:row-start-2 lg:border-s lg:ps-10 dark:border-stone-700">
            <h2 id="history-title" class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">これまで</h2>
            <dl class="mt-5 grid grid-cols-2 gap-x-5 gap-y-5">
                <div><dt class="text-[12px] text-stone-500 dark:text-stone-400">解いた問題</dt><dd class="mt-1 font-editorial text-xl font-semibold text-[#171717] dark:text-stone-100">{{ number_format($allTimeStats['answered']) }}問</dd></div>
                <div><dt class="text-[12px] text-stone-500 dark:text-stone-400">正解数</dt><dd class="mt-1 font-editorial text-xl font-semibold text-[#178C78]">{{ number_format($allTimeStats['correct']) }}問</dd></div>
                <div><dt class="text-[12px] text-stone-500 dark:text-stone-400">正答率</dt><dd class="mt-1 font-editorial text-xl font-semibold text-[#171717] dark:text-stone-100">{{ $allTimeStats['accuracy'] }}{{ is_int($allTimeStats['accuracy']) ? '%' : '' }}</dd></div>
            </dl>
            <dl class="mt-6 grid grid-cols-2 gap-x-5 border-t border-stone-200 pt-5 text-sm dark:border-stone-700">
                <div><dt class="text-[12px] text-stone-500 dark:text-stone-400">学習セット</dt><dd class="mt-1 font-medium text-stone-700 dark:text-stone-300">{{ number_format($learningSetCount) }}</dd></div>
                <div><dt class="text-[12px] text-stone-500 dark:text-stone-400">保存した問題</dt><dd class="mt-1 font-medium text-stone-700 dark:text-stone-300">{{ number_format($questionCount) }}問</dd></div>
            </dl>
        </section>

        <section aria-labelledby="weak-questions-title" class="border-b border-stone-300 py-9 lg:col-start-1 lg:row-start-2 lg:pe-10 dark:border-stone-700">
            <div><h2 id="weak-questions-title" class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">要復習</h2><p class="mt-2 text-sm text-stone-500 dark:text-stone-400">もう一度取り組みたい問題です。</p></div>
            <div class="mt-6">
                @forelse ($weakQuestions as $question)
                    @php
                        $accuracy = (int) round(($question->correct_review_count / $question->review_count) * 100);
                        $reviewMarker = $accuracy <= 25 ? '×' : '△';
                        $reviewMarkerClass = $accuracy <= 25 ? 'text-[#B4534B]' : 'text-stone-500 dark:text-stone-400';
                        $correctAnswer = match ($question->correct_option) {
                            'A' => $question->option_a,
                            'B' => $question->option_b,
                            'C' => $question->option_c,
                            'D' => $question->option_d,
                            default => null,
                        };
                    @endphp
                    <article x-data="{ revealed: false }" class="border-t border-stone-200 py-5 first:border-t-0 first:pt-0 dark:border-stone-700">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 shrink-0 text-lg font-semibold {{ $reviewMarkerClass }}" aria-hidden="true">{{ $reviewMarker }}</span>
                            <div class="min-w-0 flex-1">
                                <h3 class="line-clamp-2 break-words text-base font-semibold leading-7 text-[#171717] dark:text-stone-100">{{ $question->question }}</h3>
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-x-5 gap-y-3 text-sm">
                                    <p class="text-stone-500 dark:text-stone-400">正答率 <span class="font-semibold text-[#171717] dark:text-stone-100">{{ $accuracy }}%</span></p>
                                    <button type="button" @click="revealed = ! revealed" :aria-expanded="revealed.toString()" aria-controls="weak-question-answer-{{ $question->id }}" class="text-sm font-semibold text-[#3155D9] underline decoration-stone-300 underline-offset-4 transition hover:decoration-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 dark:decoration-stone-600"><span x-text="revealed ? '正解を隠す' : '正解を見る'">正解を見る</span></button>
                                </div>
                                <div id="weak-question-answer-{{ $question->id }}" class="mt-4 border-s-2 border-[#178C78]/40 ps-4 text-sm" x-cloak x-show="revealed"><p class="text-[#178C78]">正解</p><p class="mt-1 break-words font-medium leading-7 text-[#171717] dark:text-stone-100">{{ $correctAnswer ?? '—' }}</p></div>
                            </div>
                        </div>
                    </article>
                @empty
                    <p class="text-sm text-stone-500 dark:text-stone-400">現在は要復習の問題はありません。</p>
                @endforelse
            </div>
        </section>

        <section aria-labelledby="recent-reviews-title" class="border-t border-stone-300 py-9 lg:col-span-2 lg:col-start-1 lg:row-start-3 lg:pt-9 dark:border-stone-700">
            <h2 id="recent-reviews-title" class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">最近の復習</h2>
            <p class="mt-2 text-sm text-stone-500 dark:text-stone-400">直近に復習した問題の履歴です。</p>
            @if ($recentReviewAnswers->isEmpty())
                <p class="mt-5 text-sm text-stone-500 dark:text-stone-400">まだ復習記録はありません。</p>
            @else
                <ol class="mt-5 divide-y divide-stone-200 border-y border-stone-200 dark:divide-stone-700 dark:border-stone-700">
                    @foreach ($recentReviewAnswers as $answer)
                        <li class="grid grid-cols-[3rem_1rem_minmax(0,1fr)] items-start gap-3 py-4 text-sm">
                            <time datetime="{{ $answer->answered_at->toDateString() }}" class="text-stone-500 dark:text-stone-400">{{ $answer->answered_at->setTimezone(config('app.timezone'))->format('n/j') }}</time>
                            <span @class(['font-semibold', 'text-[#178C78]' => $answer->is_correct, 'text-[#B4534B]' => ! $answer->is_correct]) aria-hidden="true">{{ $answer->is_correct ? '○' : '×' }}</span>
                            <span class="sr-only">{{ $answer->is_correct ? '正解' : '不正解または未回答' }}</span>
                            <p class="line-clamp-2 min-w-0 break-words leading-6 text-[#171717] dark:text-stone-100">{{ $answer->question?->question }}</p>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </main>
</x-app-layout>
