<x-app-layout>
    <div class="py-10 sm:py-14">
        <article class="mx-auto max-w-4xl px-4 sm:px-6" aria-labelledby="result-title">
            @php
                $percentage = $results['totalCount'] === 0 ? 0 : round($results['correctCount'] / $results['totalCount'] * 100);
            @endphp

            <header>
                <p class="text-xs font-semibold tracking-[0.2em] text-[#3155D9]">LEARNING COMPLETE</p>
                <p class="mt-3 text-sm font-medium text-stone-500 dark:text-stone-400">学習完了</p>
                <h1 id="result-title" class="mt-2 max-w-3xl break-words font-editorial text-3xl font-semibold leading-tight sm:text-4xl">
                    {{ $draft->topic ?: $draft->source_title }}
                </h1>
            </header>

            <section class="mt-8 border-b border-stone-300 pb-9 dark:border-stone-700" aria-labelledby="learning-result-title" data-result-summary>
                <h2 id="learning-result-title" class="text-[11px] font-semibold tracking-[0.2em] text-[#3155D9]">LEARNING RESULT</h2>

                <div class="mt-6 flex flex-col gap-7 sm:flex-row sm:items-center sm:gap-10">
                    <div class="relative grid size-36 shrink-0 place-items-center" role="img" aria-label="正答率 {{ $percentage }}%">
                        <svg class="absolute inset-0 size-full -rotate-90" viewBox="0 0 120 120" aria-hidden="true">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="6" class="text-stone-200 dark:text-stone-700" />
                            <circle cx="60" cy="60" r="52" fill="none" stroke="currentColor" stroke-width="6" stroke-linecap="round" pathLength="100" stroke-dasharray="100" style="stroke-dashoffset: {{ 100 - $percentage }}" class="text-[#178C78]" />
                        </svg>
                        <div class="text-center">
                            <p class="font-editorial text-4xl font-semibold leading-none tracking-tight">{{ $percentage }}%</p>
                            <p class="mt-2 text-xs font-medium text-stone-500 dark:text-stone-400">正答率</p>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <p class="font-editorial text-2xl font-semibold leading-tight sm:text-3xl" aria-label="{{ $results['totalCount'] }}問中 {{ $results['correctCount'] }}問正解">
                            {{ $results['totalCount'] }}問中 <span class="text-[#178C78]">{{ $results['correctCount'] }}問正解</span>
                        </p>
                        <p class="mt-3 text-sm leading-7 text-stone-600 dark:text-stone-300">
                            @if ($percentage === 100)
                                すべて正解しました。学んだ内容をしっかり身につけています。
                            @elseif ($percentage >= 75)
                                よく理解できています。問題別の結果で、迷った箇所も振り返りましょう。
                            @elseif ($percentage < 50)
                                問題別の結果を確認して、理解を少しずつ確かなものにしましょう。
                            @else
                                学んだ内容を定着させるために、問題ごとの答えを振り返りましょう。
                            @endif
                        </p>

                        <dl class="mt-5 flex flex-wrap items-baseline gap-x-4 gap-y-2 border-t border-stone-200 pt-4 text-sm dark:border-stone-700">
                            <div class="flex items-baseline gap-1.5"><dt class="text-stone-500 dark:text-stone-400">正答率</dt><dd class="font-semibold">{{ $percentage }}%</dd></div>
                            <span class="hidden text-stone-300 sm:inline dark:text-stone-600" aria-hidden="true">/</span>
                            <div class="flex items-baseline gap-1.5"><dt class="font-medium text-[#178C78]">正解</dt><dd class="font-semibold">{{ $results['correctCount'] }}</dd></div>
                            <span class="hidden text-stone-300 sm:inline dark:text-stone-600" aria-hidden="true">/</span>
                            <div class="flex items-baseline gap-1.5"><dt class="font-medium text-[#B4534B]">不正解</dt><dd class="font-semibold">{{ $results['incorrectCount'] }}</dd></div>
                            <span class="hidden text-stone-300 sm:inline dark:text-stone-600" aria-hidden="true">/</span>
                            <div class="flex items-baseline gap-1.5"><dt class="font-medium text-stone-500 dark:text-stone-400">未回答</dt><dd class="font-semibold">{{ $results['unansweredCount'] }}</dd></div>
                        </dl>
                    </div>
                </div>
            </section>

            <section class="mt-12" aria-labelledby="question-results-title" data-question-review>
                <div class="flex items-end justify-between gap-5 border-b border-stone-300 pb-4 dark:border-stone-700">
                    <div>
                        <p class="text-[11px] font-semibold tracking-[0.18em] text-[#3155D9]">QUESTION REVIEW</p>
                        <h2 id="question-results-title" class="mt-2 font-editorial text-2xl font-semibold">問題別結果</h2>
                    </div>
                    <p class="shrink-0 text-xs text-stone-500 dark:text-stone-400">全 {{ $results['totalCount'] }}問</p>
                </div>

                <ol class="mt-5 grid gap-5">
                    @foreach ($draft->generated_questions as $index => $question)
                        @php
                            $answer = $answers->get($index);
                            $selectedOption = $answer?->selected_option;
                            $isUnanswered = $selectedOption === null;
                            $isCorrect = ! $isUnanswered && $answer->is_correct;
                            $correctOption = $question['correct_option'];
                            $statusSymbol = $isUnanswered ? '－' : ($isCorrect ? '○' : '×');
                            $statusLabel = $isUnanswered ? '未回答' : ($isCorrect ? '正解' : '不正解');
                            $statusColor = $isUnanswered ? 'text-stone-500 dark:text-stone-400' : ($isCorrect ? 'text-[#178C78]' : 'text-[#B4534B]');
                            $options = [
                                'A' => $question['option_a'],
                                'B' => $question['option_b'],
                                'C' => $question['option_c'],
                                'D' => $question['option_d'],
                            ];
                        @endphp

                        <li class="rounded-lg border border-stone-300/80 bg-transparent p-5 sm:p-7 dark:border-stone-700" x-data="{ revealed: false }">
                            <p class="text-xs font-semibold text-stone-500 dark:text-stone-400">問題{{ $index + 1 }}</p>

                            <h3 class="mt-4 flex items-start gap-3 text-base font-semibold leading-8 sm:text-lg">
                                <span class="sr-only">{{ $statusLabel }}</span>
                                <span class="shrink-0 text-lg font-semibold leading-8 {{ $statusColor }}" aria-hidden="true">{{ $statusSymbol }}</span>
                                <span class="min-w-0 break-words">{{ $question['question'] }}</span>
                            </h3>

                            <div class="mt-5 flex flex-col gap-4 border-t border-stone-200 pt-5 sm:flex-row sm:items-end sm:justify-between dark:border-stone-700">
                                <dl class="min-w-0 text-sm">
                                    <dt class="text-xs font-medium text-stone-500 dark:text-stone-400">あなたの回答</dt>
                                    <dd @class(['mt-2 break-words leading-7', 'text-stone-500 dark:text-stone-400' => $isUnanswered, 'text-[#B4534B]' => ! $isUnanswered && ! $isCorrect])>
                                        {{ $isUnanswered ? '未回答' : $selectedOption.'. '.$options[$selectedOption] }}
                                    </dd>
                                </dl>

                                <button
                                    type="button"
                                    class="inline-flex min-h-11 shrink-0 items-center justify-center self-start text-sm font-semibold text-[#3155D9] underline decoration-stone-300 underline-offset-4 transition hover:decoration-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 sm:self-auto dark:decoration-stone-600"
                                    @click="revealed = ! revealed"
                                    :aria-expanded="revealed.toString()"
                                    aria-controls="draft-solution-{{ $index }}"
                                    data-solution-reveal
                                >
                                    <span x-text="revealed ? '正解を隠す' : '正解を見る'">正解を見る</span>
                                </button>
                            </div>

                            <div id="draft-solution-{{ $index }}" class="mt-5 rounded-md border border-[#178C78]/20 bg-[#178C78]/[0.06] p-4 sm:p-5 dark:bg-[#178C78]/10" x-show="revealed" x-cloak>
                                <p class="text-xs font-semibold text-[#178C78]">正解</p>
                                <p class="mt-2 break-words text-sm font-medium leading-7">{{ $correctOption }}. {{ $options[$correctOption] }}</p>
                                @if (! empty($question['explanation']))
                                    <div class="mt-4 border-t border-[#178C78]/20 pt-4">
                                        <p class="text-xs font-medium text-stone-500 dark:text-stone-400">解説</p>
                                        <p class="mt-2 break-words text-sm leading-7 text-stone-700 dark:text-stone-200">{{ $question['explanation'] }}</p>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            <footer class="mt-10 border-t border-stone-300 pt-8 dark:border-stone-700">
                <div>
                    <h2 class="font-editorial text-xl font-semibold">この学びを、次の復習へ。</h2>
                    <p class="mt-2 text-sm leading-7 text-stone-600 dark:text-stone-300">学習セットとして保存すると、学習テーマと用語がDashboardへ追加されます。</p>
                </div>
                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <form method="POST" action="{{ route('extension-quiz-drafts.save', $draft->token) }}">
                        @csrf
                        <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-[#3155D9] px-6 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 sm:w-auto">
                            学習セットとして保存
                        </button>
                    </form>
                    <a href="{{ route('dashboard') }}" class="inline-flex min-h-12 items-center justify-center px-3 text-sm font-semibold text-stone-600 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-300">
                        ホームへ戻る
                    </a>
                </div>
            </footer>
        </article>
    </div>
</x-app-layout>
