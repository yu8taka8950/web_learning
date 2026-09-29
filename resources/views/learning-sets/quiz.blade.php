<x-app-layout>
    <div class="py-10 sm:py-14"><main @class(['mx-auto px-4 sm:px-6', 'max-w-4xl' => isset($results), 'max-w-3xl' => ! isset($results)])>
        @if ($questions->isEmpty())
            <p class="border-l-2 border-[#3155D9] pl-5">まだ問題がありません。</p><a href="{{ route('learning-sets.questions.create', $learningSet) }}" class="mt-6 inline-block text-[#3155D9] underline">問題を追加する</a>
        @elseif (isset($results))
            @php
                $percentage = $results['totalCount'] === 0 ? 0 : round($results['correctCount'] / $results['totalCount'] * 100);
            @endphp
            <header>
                <p class="text-xs font-semibold tracking-[0.2em] text-[#3155D9]">LEARNING COMPLETE</p>
                <p class="mt-3 text-sm font-medium text-stone-500 dark:text-stone-400">学習完了</p>
                <h1 class="mt-2 max-w-3xl font-editorial text-3xl font-semibold leading-tight sm:text-4xl">{{ $learningSet->topic ?: $learningSet->title }}</h1>
            </header>

            <section class="mt-8 border-b border-stone-300 pb-8 dark:border-stone-700" aria-labelledby="learning-result-title">
                <div class="grid gap-6 sm:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)]">
                    <div>
                        <h2 id="learning-result-title" class="text-[11px] font-semibold tracking-[0.2em] text-[#3155D9]">LEARNING RESULT</h2>
                        <p class="mt-4 font-editorial text-5xl font-semibold leading-none tracking-tight sm:text-6xl">{{ $percentage }}%</p>
                        <p class="mt-2 text-sm text-stone-500 dark:text-stone-400">正答率 {{ $percentage }}%</p>
                    </div>
                    <div class="flex flex-col justify-end sm:pb-1">
                        <p class="font-editorial text-xl font-semibold"><strong class="text-[#178C78]">{{ $results['correctCount'] }}</strong> / {{ $results['totalCount'] }}問 正解</p>
                        <p class="sr-only">{{ $results['totalCount'] }}問中{{ $results['correctCount'] }}問正解</p>
                        <p class="mt-3 max-w-xl text-sm leading-7 text-stone-600 dark:text-stone-300">
                            @if ($percentage === 100)
                                すべて正解しました。内容をよく理解できています。
                            @elseif ($percentage >= 75)
                                よく理解できています。間違えた問題も確認しておきましょう。
                            @elseif ($percentage < 50)
                                結果を確認して、もう一度取り組んでみましょう。
                            @else
                                問題別の結果を確認し、理解を確かなものにしましょう。
                            @endif
                        </p>
                    </div>
                </div>
                <dl class="mt-7 flex flex-wrap gap-x-8 gap-y-3 border-t border-stone-200 pt-5 text-sm dark:border-stone-700">
                    <div class="flex items-baseline gap-2"><dt class="font-medium text-[#178C78]">正解</dt><dd class="font-editorial text-xl font-semibold">{{ $results['correctCount'] }}</dd><span class="sr-only">正解：{{ $results['correctCount'] }}問</span></div>
                    <div class="flex items-baseline gap-2"><dt class="font-medium text-[#B4534B]">不正解</dt><dd class="font-editorial text-xl font-semibold">{{ $results['incorrectCount'] }}</dd><span class="sr-only">不正解：{{ $results['incorrectCount'] }}問</span></div>
                    <div class="flex items-baseline gap-2"><dt class="font-medium text-stone-500 dark:text-stone-400">未回答</dt><dd class="font-editorial text-xl font-semibold">{{ $results['unansweredCount'] }}</dd><span class="sr-only">未回答：{{ $results['unansweredCount'] }}問</span></div>
                </dl>
            </section>

            <section class="mt-12" aria-labelledby="question-results-title">
                <div class="flex items-end justify-between gap-5 border-b border-stone-300 pb-4 dark:border-stone-700">
                    <div><p class="text-[11px] font-semibold tracking-[0.18em] text-[#3155D9]">QUESTION REVIEW</p><h2 id="question-results-title" class="mt-2 font-editorial text-2xl font-semibold">問題別結果</h2></div>
                    <p class="text-xs text-stone-500 dark:text-stone-400">全 {{ $results['totalCount'] }}問</p>
                </div>
                <ol class="mt-5 grid gap-5">
                    @foreach ($questions as $questionIndex => $question)
                        @php
                            $answer = $answers->get($questionIndex);
                            $isUnanswered = $answer?->selected_option === null;
                            $isCorrect = ! $isUnanswered && $answer->is_correct;
                            $selectedOption = $answer?->selected_option;
                        @endphp
                        <li @class(['rounded-xl border bg-white p-5 shadow-[0_8px_24px_rgba(23,23,23,0.025)] sm:p-7 dark:bg-stone-900', 'border-stone-200 dark:border-stone-700' => $isCorrect, 'border-red-200 dark:border-red-900/70' => ! $isCorrect && ! $isUnanswered, 'border-stone-300 dark:border-stone-600' => $isUnanswered])>
                            <div class="flex items-center justify-between gap-4">
                                <p class="text-[11px] font-semibold tracking-[0.16em] text-stone-500 dark:text-stone-400">問題 {{ str_pad((string) ($questionIndex + 1), 2, '0', STR_PAD_LEFT) }}</p>
                                @if ($isUnanswered)
                                    <p class="text-xs font-semibold text-stone-500 dark:text-stone-400"><span aria-hidden="true">○</span> 未回答</p>
                                @elseif ($isCorrect)
                                    <p class="text-xs font-semibold text-[#178C78]"><span aria-hidden="true">●</span> 正解</p>
                                @else
                                    <p class="text-xs font-semibold text-[#B4534B]"><span aria-hidden="true">●</span> 不正解</p>
                                @endif
                            </div>
                            <h3 class="mt-5 text-base font-semibold leading-8 sm:text-lg">{{ $question->question }}</h3>
                            <dl class="mt-6 grid gap-5 border-t border-stone-200 pt-5 text-sm sm:grid-cols-2 dark:border-stone-700">
                                <div>
                                    <dt class="text-xs font-medium text-stone-500 dark:text-stone-400">あなたの回答</dt>
                                    <dd @class(['mt-2 leading-7', 'text-stone-500 dark:text-stone-400' => $isUnanswered, 'text-[#B4534B]' => ! $isUnanswered && ! $isCorrect])>{{ $isUnanswered ? '未回答' : $selectedOption.'. '.$question->{'option_'.strtolower($selectedOption)} }}</dd>
                                    <span class="sr-only">あなたの回答：{{ $isUnanswered ? '未回答' : $selectedOption.'. '.$question->{'option_'.strtolower($selectedOption)} }}</span>
                                </div>
                                <div class="border-t border-stone-200 pt-5 sm:border-s sm:border-t-0 sm:ps-6 sm:pt-0 dark:border-stone-700">
                                    <dt class="text-xs font-medium text-[#178C78]">正解</dt>
                                    <dd class="mt-2 leading-7">{{ $question->correct_option }}. {{ $question->{'option_'.strtolower($question->correct_option)} }}</dd>
                                </div>
                            </dl>
                        </li>
                    @endforeach
                </ol>
            </section>

            <footer class="mt-9 flex flex-col gap-4 border-t border-stone-300 pt-7 sm:flex-row sm:items-center dark:border-stone-700">
                <form method="POST" action="{{ route('learning-sets.quiz.restart', $learningSet) }}">@csrf<button class="inline-flex min-h-12 w-full items-center justify-center rounded-lg bg-[#3155D9] px-6 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 sm:w-auto">もう一度学習する →</button></form>
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-12 items-center justify-center px-3 text-sm font-semibold text-stone-600 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-300">Dashboardへ戻る</a>
            </footer>
        @else
            @php($question = $questions[$index])
            <header class="border-b border-stone-300 pb-6 dark:border-stone-700"><p class="text-sm text-stone-500">保存した学習セット</p><h1 class="font-editorial mt-2 text-2xl font-semibold">{{ $learningSet->topic ?: $learningSet->title }}</h1><div class="mt-6 flex justify-between gap-4 text-sm font-semibold"><span>問題 {{ $index + 1 }} / {{ $attempt->total_questions }}</span><span>{{ $attempt->answers()->count() }}問完了</span></div><div class="mt-3 h-1.5 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700"><div class="h-full rounded-full bg-[#3155D9]" style="width: {{ (($index + 1) / $attempt->total_questions) * 100 }}%"></div></div></header>
            <form method="POST" action="{{ route('learning-sets.quiz.grade', $learningSet) }}" class="py-8" x-data="{ solutionOpen: false }" @solution-toggled="solutionOpen = $event.detail.open">
                @csrf<input type="hidden" name="question_index" value="{{ $index }}"><h2 class="selection-source text-xl font-semibold leading-9" data-selection-source data-selection-field="question" data-question-id="{{ $question->id }}">{{ $question->question }}</h2>
                <div class="mt-7 grid gap-3">@foreach (['A' => $question->option_a, 'B' => $question->option_b, 'C' => $question->option_c, 'D' => $question->option_d] as $key => $option)<label :class="solutionOpen && '{{ $key }}' === '{{ $question->correct_option }}' ? 'border-[#3155D9] bg-blue-50/60 text-[#3155D9] dark:border-[#6F8BFF] dark:bg-blue-950/30 dark:text-[#8EA3FF]' : ''" class="flex min-h-14 cursor-pointer items-start gap-4 rounded-lg border border-stone-300 bg-white p-4 transition hover:border-[#3155D9] has-[:checked]:border-[#3155D9] has-[:checked]:bg-blue-50/60 dark:border-stone-700 dark:bg-stone-950 dark:has-[:checked]:bg-blue-950/30"><input type="radio" name="selected_option" value="{{ $key }}" class="mt-1 text-[#3155D9] focus:ring-[#3155D9]"><span class="leading-7"><strong>{{ $key }}.</strong> {{ $option }}</span></label>@endforeach</div>
                <x-input-error :messages="$errors->get('selected_option')" class="mt-4" />
                <button type="submit" class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-[#3155D9] px-6 py-2 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus:ring-2 focus:ring-[#3155D9] focus:ring-offset-2">{{ $index + 1 === $attempt->total_questions ? '結果を見る →' : '次にすすむ →' }}</button>
                <p class="mt-2 text-xs text-stone-500 dark:text-stone-400">選択せずに進むと未回答として記録されます</p>
                @include('quiz.partials.solution-details', ['question' => $question, 'solutionToggleEvent' => true, 'references' => [['label' => $learningSet->title, 'url' => $learningSet->source_url]]])
            </form>
            <footer class="border-t border-stone-300 pt-5 text-center dark:border-stone-700"><a href="{{ route('dashboard') }}" class="text-sm font-semibold underline underline-offset-4">あとで続ける</a><p class="mt-2 text-xs text-stone-500">回答した進捗は自動保存されます</p></footer>
        @endif
    </main></div>
</x-app-layout>
