<x-app-layout>
    <div class="py-10 sm:py-14">
        <main class="mx-auto max-w-3xl px-4 sm:px-6" aria-labelledby="review-title">
            @if (! $attempt)
                <section class="rounded-lg border border-stone-300 bg-white p-6 dark:border-stone-700 dark:bg-stone-950">
                    <h1 id="review-title" class="font-editorial text-2xl font-semibold">今日の復習はありません</h1>
                    <p class="mt-3 text-stone-600 dark:text-stone-400">次の復習予定日まで、新しい学習を進めましょう。</p>
                    <a href="{{ route('dashboard') }}" class="mt-6 inline-flex rounded-lg bg-[#3155D9] px-5 py-3 text-sm font-semibold text-white">Dashboardへ戻る</a>
                </section>
            @else
                <header class="border-b border-stone-300 pb-6 dark:border-stone-700">
                    <p class="text-sm text-stone-500">今日の復習</p>
                    <h1 id="review-title" class="font-editorial mt-2 text-2xl font-semibold">問題 {{ $questionIndex + 1 }} / {{ $attempt->total_questions }}</h1>
                    <div class="mt-6 flex justify-between gap-4 text-sm font-semibold"><span>問題 {{ $questionIndex + 1 }} / {{ $attempt->total_questions }}</span><span>復習済み {{ $questionIndex }}問</span></div>
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700"><div class="h-full rounded-full bg-[#3155D9]" style="width: {{ (($questionIndex + 1) / $attempt->total_questions) * 100 }}%"></div></div>
                </header>

                <form method="POST" action="{{ route('reviews.grade') }}" class="py-8">
                    @csrf
                    <input type="hidden" name="review_attempt_id" value="{{ $attempt->id }}">
                    <input type="hidden" name="question_index" value="{{ $questionIndex }}">
                    <h2 class="selection-source break-words text-xl font-semibold leading-9" data-selection-source data-selection-field="question" data-question-id="{{ $question->id }}">{{ $question->question }}</h2>
                    <div class="mt-7 grid gap-3">
                        @foreach (['A' => $question->option_a, 'B' => $question->option_b, 'C' => $question->option_c, 'D' => $question->option_d] as $key => $option)
                            <label class="flex min-h-14 cursor-pointer items-start gap-4 rounded-lg border border-stone-300 bg-white p-4 transition hover:border-[#3155D9] has-[:checked]:border-[#3155D9] has-[:checked]:bg-blue-50/60 dark:border-stone-700 dark:bg-stone-950 dark:has-[:checked]:bg-blue-950/30"><input type="radio" name="selected_option" value="{{ $key }}" class="mt-1 text-[#3155D9] focus:ring-[#3155D9]"><span class="leading-7"><strong>{{ $key }}.</strong> {{ $option }}</span></label>
                        @endforeach
                    </div>
                    <x-input-error :messages="$errors->get('selected_option')" class="mt-4" />
                    <button class="mt-6 inline-flex min-h-11 items-center rounded-lg bg-[#3155D9] px-6 py-2 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus:ring-2 focus:ring-[#3155D9] focus:ring-offset-2">
                        {{ $questionIndex + 1 === $attempt->total_questions ? '結果を見る →' : '次にすすむ →' }}
                    </button>
                    <p class="mt-2 text-xs text-stone-500 dark:text-stone-400">選択せずに進むと未回答として記録されます</p>

                    @include('quiz.partials.solution-details', ['question' => $question, 'references' => [['label' => $question->learningSet->topic ?: $question->learningSet->title, 'url' => $question->learningSet->source_url]]])
                </form>

                <footer class="border-t border-stone-300 pt-5 text-center dark:border-stone-700"><a href="{{ route('dashboard') }}" class="text-sm font-semibold underline underline-offset-4">あとで続ける</a><p class="mt-2 text-xs text-stone-500">回答した進捗は自動保存されます</p></footer>
            @endif
        </main>
    </div>
</x-app-layout>
