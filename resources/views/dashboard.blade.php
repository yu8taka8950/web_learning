<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 py-2 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-stone-500" data-dashboard-today>
                <span class="font-semibold tracking-[0.14em] text-[#171717] dark:text-stone-100">TODAY</span>
                <span>{{ $dashboardDate }}</span>
                <span class="hidden text-stone-300 sm:inline" aria-hidden="true">｜</span>
                <span>学習中 <strong class="font-semibold text-[#171717] dark:text-stone-100">{{ $dashboardLearningItemCount }}</strong>セット</span>
                <span class="hidden text-stone-300 sm:inline" aria-hidden="true">｜</span>
                <span>今日の復習 <strong class="font-semibold text-[#178C78]">{{ $dashboardDueReviewCount }}</strong>問</span>
            </div>
            <div class="flex w-full flex-col gap-4 sm:w-auto sm:items-end">
                <form method="GET" action="{{ route('dashboard') }}" class="flex w-full max-w-sm items-center gap-2 border-b border-stone-300 focus-within:border-[#3155D9] dark:border-stone-700" role="search" x-data @submit="if ($event.currentTarget.elements.q.value.trim() === '') { $event.preventDefault(); }">
                <label for="dashboard-search" class="sr-only">学習セットを検索</label>
                <i data-lucide="search" class="size-4 shrink-0 text-stone-400" aria-hidden="true"></i>
                <input id="dashboard-search" name="q" value="{{ $searchQuery }}" placeholder="学習セットを検索" maxlength="100" class="min-h-10 min-w-0 flex-1 border-0 bg-transparent px-1 text-sm focus:ring-0">
                @if ($searchQuery !== '')
                    <a href="{{ route('dashboard') }}" aria-label="検索をクリア" class="px-2 text-sm text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">クリア</a>
                @endif
                <button type="submit" class="grid min-h-10 min-w-10 place-items-center text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]" aria-label="検索"><i data-lucide="arrow-right" class="size-4" aria-hidden="true"></i></button>
                </form>
            </div>
        </div>
    </x-slot>

    @php
        if ($quickLearningAttempt && $quickLearningQuestion && $quickLearningIndex !== null) {
            $quickIsReview = $quickLearningType === 'review';
            $quickIsDraft = ! $quickIsReview && $quickLearningAttempt->extension_quiz_draft_id !== null;
            $quickCorrectOption = data_get($quickLearningQuestion, 'correct_option');
            $quickOptions = collect(['A', 'B', 'C', 'D'])->mapWithKeys(
                fn (string $option): array => [$option => data_get($quickLearningQuestion, 'option_'.strtolower($option))],
            );
            $quickFormAction = match (true) {
                $quickIsReview => route('reviews.grade'),
                $quickIsDraft => route('extension-quiz-drafts.grade', $quickLearningAttempt->extensionQuizDraft->token),
                default => route('learning-sets.quiz.grade', $quickLearningAttempt->learningSet),
            };
            $quickReference = match (true) {
                $quickIsReview => [
                    'label' => $quickLearningQuestion->learningSet->topic ?: $quickLearningQuestion->learningSet->title,
                    'url' => $quickLearningQuestion->learningSet->source_url,
                ],
                $quickIsDraft => [
                    'label' => $quickLearningAttempt->extensionQuizDraft->source_title,
                    'url' => $quickLearningAttempt->extensionQuizDraft->source_url,
                ],
                default => [
                    'label' => $quickLearningAttempt->learningSet->title,
                    'url' => $quickLearningAttempt->learningSet->source_url,
                ],
            };
        }
    @endphp

    <div class="lg:h-[calc(100vh-69px)] lg:overflow-hidden">
        @if (session('status'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4500)" x-cloak x-show="show" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-y-1.5 opacity-0" x-transition:enter-end="translate-y-0 opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0 opacity-100" x-transition:leave-end="-translate-y-1.5 opacity-0" role="status" aria-live="polite" class="fixed inset-x-4 top-[4.5rem] z-[60] w-auto border border-stone-200 bg-[#FDFCFA] shadow-sm dark:border-stone-700 dark:bg-stone-900 sm:left-auto sm:right-6 sm:w-[360px]">
                <div class="flex items-start gap-4 px-4 py-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium leading-6 text-[#171717] dark:text-stone-100">{{ session('status') }}</p>

                        @if (session('status') === '学習セットを削除しました。')
                            <a href="{{ route('collections.deleted') }}" class="mt-3 inline-flex text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">削除済みを見る →</a>
                        @endif
                    </div>
                    <button type="button" class="-me-1 -mt-1 grid size-8 shrink-0 place-items-center text-lg leading-none text-stone-500 transition hover:text-[#171717] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-400 dark:hover:text-white" aria-label="通知を閉じる" @click="show = false"><span aria-hidden="true">×</span></button>
                </div>
            </div>
        @endif

        <div class="mx-auto grid min-h-0 min-w-0 max-w-[1500px] grid-cols-1 lg:h-full lg:grid-cols-[minmax(0,1.85fr)_minmax(320px,1fr)]">
            <main
    id="learning-sets"
    class="min-h-0 min-w-0 lg:overflow-y-auto lg:overscroll-contain" aria-labelledby="continue-learning-title">
                <div class="px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
                    <div class="flex items-center justify-between gap-5 border-b border-stone-300 pb-5 dark:border-stone-700">
                        <div>
                            <h1 id="continue-learning-title" class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">{{ $searchQuery === '' ? '学習を始める・続ける' : 'SEARCH RESULTS' }}</h1>
                            @if ($searchQuery !== '')
                                <p class="mt-2 text-sm text-stone-500">「{{ $searchQuery }}」の検索結果</p>
                            @endif
                        </div>
                        <span class="text-sm text-stone-500">{{ $dashboardLearningItems->count() }}件</span>
                    </div>

                    @if ($dashboardLearningItems->isEmpty())
                        @if ($showFirstLearningEmptyState)
                            <div class="py-10 sm:py-14">
                                <div class="flex items-center gap-3 text-[#3155D9]"><i data-lucide="book-open" class="size-5" aria-hidden="true"></i><p class="font-editorial text-xl font-semibold text-[#171717] dark:text-stone-100">学習を始めよう</p></div>
                                <div class="mt-5 max-w-2xl text-sm leading-7 text-stone-500">
                                    <p>Web Learningは、<br class="sm:hidden">普段見ているWebページから学習候補を見つけます。</p>
                                    <p class="mt-3">Chrome右上のWeb Learning拡張機能を開いて、<br class="sm:hidden">Learning ModeをONにしてみましょう。</p>
                                </div>
                                <ol class="mt-6 grid max-w-2xl gap-3 text-sm text-[#171717] dark:text-stone-200 sm:grid-cols-2">
                                    <li class="flex items-start gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-[#3155D9] text-xs font-semibold text-white">1</span><span>Chrome右上のWeb Learningアイコンを開く</span></li>
                                    <li class="flex items-start gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-[#3155D9] text-xs font-semibold text-white">2</span><span>Learning ModeをON</span></li>
                                    <li class="flex items-start gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-[#3155D9] text-xs font-semibold text-white">3</span><span>いつも通りWebページを読む</span></li>
                                    <li class="flex items-start gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-[#3155D9] text-xs font-semibold text-white">4</span><span>学習候補が見つかったら問題を作る</span></li>
                                </ol>
                                <div class="mt-7 flex flex-wrap items-center gap-x-6 gap-y-4">
                                    <a href="{{ route('onboarding.show') }}" class="inline-flex items-center gap-2 border-b border-[#3155D9] pb-1 text-sm font-semibold text-[#3155D9]">使い方を見る <span aria-hidden="true">→</span></a>
                                    <span class="text-sm text-stone-400">または</span>
                                    <a href="{{ route('screenshot-learning.create') }}" class="inline-flex items-center gap-2 border-b border-stone-400 pb-1 text-sm font-semibold text-stone-700 dark:text-stone-300">スクリーンショットから学ぶ <span aria-hidden="true">→</span></a>
                                </div>
                            </div>
                        @elseif ($searchQuery === '')
                            <div class="py-14">
                                <p class="font-editorial text-xl font-semibold text-[#171717] dark:text-stone-100">進行中の学習はありません</p>
                                <p class="mt-3 text-sm leading-7 text-stone-500">学習セットを選ぶと、ここからすぐに再開できます。</p>
                                <a href="{{ route('learning-sets.create') }}" class="mt-6 inline-flex border-b border-[#3155D9] pb-1 text-sm font-semibold text-[#3155D9]">学習を追加する →</a>
                            </div>
                            @else
                                <div class="py-14">
                                <p class="font-editorial text-xl font-semibold text-[#171717] dark:text-stone-100">該当する学習セットがありません。</p>
                                <a href="{{ route('dashboard') }}" class="mt-6 inline-flex border-b border-[#3155D9] pb-1 text-sm font-semibold text-[#3155D9]">検索をクリア</a>
                                </div>
                            @endif
                    @else
                        @php
                            $learningSetIconService = app(\App\Services\LearningSetIconService::class);
                        @endphp
                        <div class="mt-2" data-learning-timeline>
                            @foreach ($dashboardLearningItems as $learningItem)
                                @php
                                    $completedCount = $learningItem['completed_count'];
                                    $progress = $learningItem['total_questions'] > 0 ? min(100, round(($completedCount / $learningItem['total_questions']) * 100)) : 0;
                                    $status = $learningItem['status'];
                                    $ctaLabel = $learningItem['cta_label'];
                                    $learningSetIcon = $learningSetIconService->for($learningItem['category'], $learningItem['topic'], $learningItem['title']);
                                @endphp
                                <article class="group relative border-b border-stone-300 py-6 dark:border-stone-700 sm:py-7" data-learning-item data-topic="{{ $learningItem['display_topic'] }}" data-title="{{ $learningItem['title'] }}" data-resume-url="{{ $learningItem['resume_url'] }}" data-completed="{{ $completedCount }}" data-total="{{ $learningItem['total_questions'] }}" data-status="{{ $status }}" data-cta-label="{{ $ctaLabel }}" x-data="{ menuOpen: false, collectionDialog: false, deleteDialog: false }">
                                    <div class="flex flex-col justify-between gap-5 sm:flex-row sm:items-center">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-3">
                                                <span class="grid size-9 shrink-0 place-items-center border border-stone-200 bg-white text-[#3155D9] dark:border-stone-700 dark:bg-stone-900" aria-hidden="true"><i data-lucide="{{ $learningSetIcon }}" class="size-[18px]"></i></span>
                                                <div class="flex min-w-0 flex-wrap items-center gap-x-4 gap-y-1">
                                                <p @class(['text-[10px] font-semibold tracking-[0.18em]', 'text-[#3155D9]' => $learningItem['category'] === 'LINUX', 'text-[#178C78]' => $learningItem['category'] === 'CLOUD', 'text-blue-700 dark:text-blue-300' => $learningItem['category'] === 'WEB', 'text-stone-700 dark:text-stone-300' => in_array($learningItem['category'], ['PROGRAMMING', 'DATABASE'], true), 'text-stone-500' => in_array($learningItem['category'], ['SERVER / NETWORK', 'LEARNING'], true)])>{{ $learningItem['category'] }}</p>
                                                <p class="text-xs font-semibold text-stone-500">{{ $status }}</p>
                                                </div>
                                            </div>
                                            <h2 class="mt-3 line-clamp-2 break-words font-editorial text-xl font-semibold leading-8 text-[#171717] dark:text-stone-100">{{ $learningItem['display_topic'] }}</h2>
                                            <div class="mt-3 flex items-center gap-3 text-sm font-medium text-stone-600 dark:text-stone-400"><span>{{ $completedCount }} / {{ $learningItem['total_questions'] }}問 完了</span><span class="h-px flex-1 bg-stone-200 dark:bg-stone-800" aria-hidden="true"></span></div>
                                            <div class="mt-2 h-1 w-full max-w-xl overflow-hidden bg-stone-200 dark:bg-stone-800" role="progressbar" aria-label="{{ $learningItem['display_topic'] }}の進捗" aria-valuenow="{{ $completedCount }}" aria-valuemin="0" aria-valuemax="{{ $learningItem['total_questions'] }}">
                                                <div @class(['h-full', 'bg-[#B8B4AA]' => $completedCount === 0, 'bg-[#3155D9]' => $completedCount > 0]) style="width: {{ $progress }}%"></div>
                                            </div>
                                        </div>
                                        <div class="relative flex shrink-0 items-center gap-4">
                                            <a href="{{ $learningItem['resume_url'] }}" class="text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">{{ $ctaLabel }}</a>
                                            @if ($learningItem['learning_set_id'])
                                                <button type="button" class="grid size-10 place-items-center text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]" @click="menuOpen = ! menuOpen" :aria-expanded="menuOpen" aria-controls="learning-set-menu-{{ $learningItem['learning_set_id'] }}" aria-label="{{ $learningItem['title'] }}のメニュー"><i data-lucide="ellipsis-vertical" class="size-5" aria-hidden="true"></i></button>
                                                <div id="learning-set-menu-{{ $learningItem['learning_set_id'] }}" x-cloak x-show="menuOpen" @click.outside="menuOpen = false" @keydown.escape.window="menuOpen = false" class="absolute right-0 top-11 z-30 w-52 rounded-lg border border-stone-200 bg-white py-2 text-sm shadow-lg dark:border-stone-700 dark:bg-stone-900">
                                                    <a href="{{ $learningItem['resume_url'] }}" class="block px-4 py-2 hover:bg-stone-50 dark:hover:bg-stone-800">学習する</a>
                                                    <button type="button" class="block w-full px-4 py-2 text-left hover:bg-stone-50 dark:hover:bg-stone-800" @click="menuOpen = false; collectionDialog = true">コレクションに追加</button>
                                                    <button type="button" class="block w-full px-4 py-2 text-left text-[#B4534B] hover:bg-stone-50 dark:hover:bg-stone-800" @click="menuOpen = false; deleteDialog = true">削除</button>
                                                </div>

                                                <div x-cloak x-show="collectionDialog" @keydown.escape.window="collectionDialog = false" class="fixed inset-0 z-50 grid place-items-center bg-stone-950/35 p-4" role="dialog" aria-modal="true" aria-labelledby="collection-dialog-title-{{ $learningItem['learning_set_id'] }}">
                                                    <div class="w-full max-w-md rounded-xl border border-stone-200 bg-white p-6 shadow-xl dark:border-stone-700 dark:bg-stone-900" @click.outside="collectionDialog = false">
                                                        <h3 id="collection-dialog-title-{{ $learningItem['learning_set_id'] }}" class="font-editorial text-xl font-semibold">コレクションに追加</h3>
                                                        <p class="mt-2 text-sm text-stone-500">{{ $learningItem['title'] }}</p>
                                                        <form method="POST" action="{{ route('learning-sets.collections.update', $learningItem['learning_set_id']) }}" class="mt-6">
                                                            @csrf
                                                            @method('PUT')
                                                            <fieldset class="grid max-h-60 gap-3 overflow-y-auto"><legend class="sr-only">追加するコレクション</legend>
                                                                @forelse ($availableCollections as $collection)
                                                                    <label class="flex min-h-11 items-center gap-3 border-b border-stone-200 text-sm dark:border-stone-700"><input type="checkbox" name="collection_ids[]" value="{{ $collection->id }}" @checked(in_array($collection->id, $learningItem['collection_ids'], true)) class="rounded border-stone-300 text-[#3155D9] focus:ring-[#3155D9]">{{ $collection->name }}</label>
                                                                @empty
                                                                    <p class="text-sm text-stone-500">まだコレクションがありません。</p>
                                                                @endforelse
                                                            </fieldset>
                                                            <a href="{{ route('collections.index') }}" class="mt-5 inline-flex text-xs font-semibold text-[#3155D9]">＋ 新しいコレクション</a>
                                                            <div class="mt-6 flex justify-end gap-3"><button type="button" class="min-h-11 px-4 text-sm font-semibold text-stone-600" @click="collectionDialog = false">キャンセル</button><button type="submit" class="min-h-11 rounded-lg bg-[#3155D9] px-5 text-sm font-semibold text-white">保存</button></div>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div x-cloak x-show="deleteDialog" @keydown.escape.window="deleteDialog = false" class="fixed inset-0 z-50 grid place-items-center bg-stone-950/35 p-4" role="dialog" aria-modal="true" aria-labelledby="delete-learning-set-title-{{ $learningItem['learning_set_id'] }}">
                                                    <div class="w-full max-w-md rounded-xl border border-stone-200 bg-white p-6 shadow-xl dark:border-stone-700 dark:bg-stone-900" @click.outside="deleteDialog = false">
                                                        <h3 id="delete-learning-set-title-{{ $learningItem['learning_set_id'] }}" class="font-editorial text-xl font-semibold">「{{ $learningItem['title'] }}」を削除しますか？</h3>
                                                        <p class="mt-3 text-sm leading-7 text-stone-500">Dashboardや復習、学習モードから表示されなくなります。学習履歴は保持されます。</p>
                                                        <form method="POST" action="{{ route('learning-sets.destroy', $learningItem['learning_set_id']) }}" class="mt-6 flex justify-end gap-3">@csrf @method('DELETE')<button type="button" class="min-h-11 px-4 text-sm font-semibold text-stone-600" @click="deleteDialog = false">キャンセル</button><button type="submit" class="min-h-11 rounded-lg bg-[#B4534B] px-5 text-sm font-semibold text-white">削除する</button></form>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                </div>
            </main>

            <aside class="min-h-0 border-t border-stone-300 bg-white/45 dark:border-stone-700 dark:bg-stone-950/20 lg:overflow-y-auto lg:border-l lg:border-t-0" aria-labelledby="quick-learning-title">
                <div class="p-5 sm:p-7 lg:p-8">
                    <p class="text-xs font-semibold tracking-[0.16em] text-[#3155D9]">ONE QUESTION</p>
                    <h2 id="quick-learning-title" class="mt-2 font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">クイック学習</h2>
                    @if ($quickLearningAttempt && $quickLearningQuestion && $quickLearningIndex !== null)
                        @php
                            $quickCompletedCount = $quickLearningAttempt->answers_count;
                            $quickProgress = $quickLearningAttempt->total_questions > 0 ? min(100, round(($quickCompletedCount / $quickLearningAttempt->total_questions) * 100)) : 0;
                        @endphp
                        <div class="mt-7 border-y border-stone-300 py-5 dark:border-stone-700">
                            <p @class(['text-sm font-semibold', 'text-[#178C78]' => $quickLearningType === 'review', 'text-[#3155D9]' => $quickLearningType !== 'review'])>続きから：{{ $quickLearningType === 'review' ? '今日の復習' : '通常学習' }}</p>
                            <div class="mt-3 flex items-center justify-between gap-4 text-sm text-stone-500"><span>問題 {{ $quickLearningIndex + 1 }} / {{ $quickLearningAttempt->total_questions }}</span><span>{{ $quickCompletedCount }} / {{ $quickLearningAttempt->total_questions }}問 完了</span></div>
                            <p class="mt-2 text-xs text-stone-500">残り{{ $quickLearningAttempt->total_questions - $quickCompletedCount }}問</p>
                            <div class="mt-3 h-1.5 overflow-hidden bg-stone-200 dark:bg-stone-800"><div @class(['h-full', 'bg-[#178C78]' => $quickLearningType === 'review', 'bg-[#3155D9]' => $quickLearningType !== 'review']) style="width: {{ $quickProgress }}%"></div></div>
                        </div>
                        <form method="POST" action="{{ $quickFormAction }}" class="mt-7" data-quick-learning-content x-data="{ showCorrect: false, submitting: false }" @submit="submitting = true">
                            @csrf
                            @if ($quickIsReview)
                                <input type="hidden" name="review_attempt_id" value="{{ $quickLearningAttempt->id }}">
                            @endif
                            <input type="hidden" name="question_index" value="{{ $quickLearningIndex }}">
                            <input type="hidden" name="return_to" value="dashboard">

                            <h3 class="selection-source text-base font-semibold leading-8 text-stone-900 dark:text-stone-100" data-selection-source data-selection-field="question" @if (data_get($quickLearningQuestion, 'id')) data-question-id="{{ data_get($quickLearningQuestion, 'id') }}" @endif>{{ data_get($quickLearningQuestion, 'question') }}</h3>
                            <div class="mt-6 grid gap-3">
                                @foreach ($quickOptions as $option => $optionText)
                                    <label class="flex min-h-12 cursor-pointer items-start gap-3 border border-stone-300 bg-white px-4 py-3 text-sm transition hover:border-[#3155D9] has-[:checked]:border-[#3155D9] has-[:checked]:bg-blue-50/60 dark:border-stone-700 dark:bg-stone-950 dark:has-[:checked]:bg-blue-950/30">
                                        <input type="radio" name="selected_option" value="{{ $option }}" class="mt-1 text-[#3155D9] focus:ring-[#3155D9]">
                                        <span class="selection-source leading-6 transition-colors" data-selection-source data-selection-field="question" @if (data_get($quickLearningQuestion, 'id')) data-question-id="{{ data_get($quickLearningQuestion, 'id') }}" @endif @if ($option === $quickCorrectOption) :class="showCorrect ? 'text-[#3155D9]' : ''" @endif><strong>{{ $option }}.</strong> {{ $optionText }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('selected_option')" class="mt-4" />

                            <button type="submit" class="mt-6 inline-flex min-h-11 w-full items-center justify-between bg-[#3155D9] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus:ring-2 focus:ring-[#3155D9] focus:ring-offset-2 disabled:cursor-wait disabled:opacity-60" :disabled="submitting">
                                <span>{{ $quickLearningIndex + 1 === $quickLearningAttempt->total_questions ? '結果を見る' : '次にすすむ' }}</span>
                                <span>→</span>
                            </button>
                            <p class="mt-2 text-xs text-stone-500 dark:text-stone-400">選択せずに進むと未回答として記録されます</p>

                            <div class="mt-7 divide-y divide-stone-300 border-y border-stone-300 text-sm dark:divide-stone-700 dark:border-stone-700">
                                <details class="py-4" @toggle="showCorrect = $event.target.open"><summary class="cursor-pointer font-semibold">正解を見る</summary><p class="mt-3 leading-7"><span class="font-medium">正解：</span>{{ $quickCorrectOption }}. {{ $quickOptions[$quickCorrectOption] }}</p></details>
                                <details class="py-4"><summary class="cursor-pointer font-semibold">解説を見る</summary><p class="selection-source mt-3 leading-7" data-selection-source data-selection-field="explanation" @if (data_get($quickLearningQuestion, 'id')) data-question-id="{{ data_get($quickLearningQuestion, 'id') }}" @endif>{{ data_get($quickLearningQuestion, 'explanation') }}</p></details>
                                @include('quiz.partials.reference-details', ['references' => [$quickReference]])
                            </div>
                            @include('quiz.partials.selection-toolbar', ['canSave' => ! $quickIsDraft, 'selectionScope' => '[data-quick-learning-content]'])
                        </form>
                    @elseif ($quickLearningType === 'due_review')
                        <div class="mt-7 border-y border-stone-300 py-7 dark:border-stone-700">
                            <p class="text-sm font-semibold text-[#178C78]">今日の復習</p>
                            <p class="mt-3 font-editorial text-xl font-semibold text-[#171717] dark:text-stone-100">{{ number_format($dueReviewCount) }}問あります</p>
                            <p class="mt-3 text-sm leading-7 text-stone-500">忘れる前に、今日の復習を進めましょう。</p>
                        </div>
                        <a href="{{ route('reviews.today') }}" class="mt-7 inline-flex min-h-11 w-full items-center justify-between bg-[#178C78] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#127563] focus:outline-none focus:ring-2 focus:ring-[#178C78] focus:ring-offset-2"><span>復習を始める</span><span>→</span></a>
                    @elseif ($quickLearningType === 'random' && $quickLearningQuestion)
                        @php
                            $randomCorrectOption = $quickLearningQuestion->correct_option;
                            $randomOptions = collect(['A', 'B', 'C', 'D'])->mapWithKeys(
                                fn (string $option): array => [$option => $quickLearningQuestion->{'option_'.strtolower($option)}],
                            );
                        @endphp
                        <div class="mt-7 border-y border-stone-300 py-5 dark:border-stone-700">
                            <p class="text-sm font-semibold text-[#3155D9]">今日の1問</p>
                            <p class="mt-2 text-xs text-stone-500">保存した問題からランダムに出題</p>
                        </div>
                        <div class="mt-7" data-quick-learning-content x-data="{ showCorrect: false }">
                            <h3 class="selection-source text-base font-semibold leading-8 text-stone-900 dark:text-stone-100" data-selection-source data-selection-field="question" data-question-id="{{ $quickLearningQuestion->id }}">{{ $quickLearningQuestion->question }}</h3>
                            <div class="mt-6 grid gap-3">
                                @foreach ($randomOptions as $option => $optionText)
                                    <label class="flex min-h-12 cursor-pointer items-start gap-3 border border-stone-300 bg-white px-4 py-3 text-sm transition hover:border-[#3155D9] has-[:checked]:border-[#3155D9] has-[:checked]:bg-blue-50/60 dark:border-stone-700 dark:bg-stone-950 dark:has-[:checked]:bg-blue-950/30">
                                        <input type="radio" name="quick_random_option" value="{{ $option }}" class="mt-1 text-[#3155D9] focus:ring-[#3155D9]">
                                        <span class="selection-source leading-6 transition-colors" data-selection-source data-selection-field="question" data-question-id="{{ $quickLearningQuestion->id }}" @if ($option === $randomCorrectOption) :class="showCorrect ? 'text-[#3155D9]' : ''" @endif><strong>{{ $option }}.</strong> {{ $optionText }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <a href="{{ route('dashboard') }}" class="mt-6 inline-flex min-h-11 w-full items-center justify-between bg-[#3155D9] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus:ring-2 focus:ring-[#3155D9] focus:ring-offset-2">
                                <span>次の1問</span>
                                <span>→</span>
                            </a>

                            <div class="mt-7 divide-y divide-stone-300 border-y border-stone-300 text-sm dark:divide-stone-700 dark:border-stone-700">
                                <details class="py-4" @toggle="showCorrect = $event.target.open"><summary class="cursor-pointer font-semibold">正解を見る</summary><p class="mt-3 leading-7"><span class="font-medium">正解：</span>{{ $randomCorrectOption }}. {{ $randomOptions[$randomCorrectOption] }}</p></details>
                                <details class="py-4"><summary class="cursor-pointer font-semibold">解説を見る</summary><p class="selection-source mt-3 leading-7" data-selection-source data-selection-field="explanation" data-question-id="{{ $quickLearningQuestion->id }}">{{ $quickLearningQuestion->explanation }}</p></details>
                                @include('quiz.partials.reference-details', ['references' => [['label' => $quickLearningQuestion->learningSet->title, 'url' => $quickLearningQuestion->learningSet->source_url]]])
                            </div>
                            @include('quiz.partials.selection-toolbar', ['selectionScope' => '[data-quick-learning-content]'])
                        </div>
                    @else
                        <div class="mt-7 border-y border-stone-300 py-7 dark:border-stone-700"><p class="font-editorial text-xl font-semibold">まだ問題がありません</p><p class="mt-3 text-sm leading-7 text-stone-500">Webを見るか、スクリーンショットから学習を始めると、ここにクイック学習が表示されます。</p></div>
                        <div class="mt-7 flex flex-wrap items-center gap-x-5 gap-y-3 text-sm font-semibold">
                            <a href="{{ route('screenshot-learning.create') }}" class="inline-flex items-center gap-2 text-[#3155D9]">スクリーンショットから学ぶ <span aria-hidden="true">→</span></a>
                            <span class="text-stone-400">または</span>
                            <a href="{{ route('onboarding.show') }}" class="inline-flex items-center gap-2 text-[#3155D9]">使い方を見る <span aria-hidden="true">→</span></a>
                        </div>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
