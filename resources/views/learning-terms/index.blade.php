<x-app-layout>
    <x-slot name="header">
        <h1 class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">マイ用語</h1>
    </x-slot>

    <main x-data="{ termPickerOpen: @js($selectedBox !== null && request()->has('term_picker_q')), categoryMenuOpen: false, renameCategoryOpen: @js($selectedBox !== null && $errors->has('name')), bulkMode: false, selectedTermIds: [], bulkModalOpen: false }" class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14">
        @if (session('status') || session('error'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-cloak x-show="show" x-transition role="status" aria-live="polite" class="fixed inset-x-4 top-[4.5rem] z-[60] border border-stone-200 bg-[#FDFCFA] shadow-sm dark:border-stone-700 dark:bg-stone-900 sm:left-auto sm:right-6 sm:w-[360px]">
                <div class="flex items-start gap-4 px-4 py-4">
                    <p class="min-w-0 flex-1 text-sm font-medium leading-6 text-[#171717] dark:text-stone-100">{{ session('status') ?? session('error') }}</p>
                    <button type="button" class="grid size-8 shrink-0 place-items-center text-lg text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]" aria-label="通知を閉じる" @click="show = false">×</button>
                </div>
            </div>
        @endif

        <section @if ($selectedBox) aria-labelledby="saved-terms-title" @else aria-label="マイ用語一覧" @endif>
            <div class="border-b border-stone-300 pb-7 dark:border-stone-700">
                @if ($selectedBox)
                    <a href="{{ route('learning-terms.index') }}" class="inline-flex text-sm font-semibold text-stone-500 underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">← マイ用語</a>
                    <div class="mt-6 flex flex-col justify-between gap-5 sm:flex-row sm:items-end">
                        <div class="min-w-0">
                            <h2 id="saved-terms-title" class="break-words font-editorial text-3xl font-semibold">{{ $selectedBox->name }}</h2>
                            <p class="mt-2 text-sm font-semibold text-[#178C78]">{{ number_format($selectedBoxTermCount) }}語</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-5">
                            <button type="button" @click="termPickerOpen = true" class="text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">＋ 用語を追加</button>
                            <div class="relative">
                                <button type="button" aria-label="カテゴリ操作を開く" aria-controls="category-actions-menu" :aria-expanded="categoryMenuOpen.toString()" @click="categoryMenuOpen = ! categoryMenuOpen" class="grid size-10 place-items-center text-xl font-semibold text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">⋯</button>
                                <div id="category-actions-menu" x-cloak x-show="categoryMenuOpen" x-transition @click.outside="categoryMenuOpen = false" class="absolute right-0 z-20 mt-2 w-44 border border-stone-200 bg-[#FDFCFA] py-1 shadow-sm dark:border-stone-700 dark:bg-stone-900" role="menu">
                                    <button type="button" @click="categoryMenuOpen = false; renameCategoryOpen = true; $nextTick(() => document.getElementById('box-name-edit')?.focus())" class="block min-h-10 w-full px-4 text-left text-sm font-semibold text-[#3155D9] hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#3155D9] dark:hover:bg-stone-800" role="menuitem">名前を変更</button>
                                    <form method="POST" action="{{ route('learning-term-boxes.destroy', $selectedBox) }}" onsubmit="return confirm('このカテゴリを削除しますか？用語そのものは削除されません。')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="block min-h-10 w-full px-4 text-left text-sm font-semibold text-[#B4534B] hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#3155D9] dark:hover:bg-stone-800" role="menuitem">カテゴリを削除</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if (! $selectedBox)
                <div x-data="{ creatingCategory: @js($errors->has('name')) }" @open-category-creator.window="creatingCategory = true; $nextTick(() => document.getElementById('new-box-name')?.focus())" class="border-y border-stone-200 py-4 dark:border-stone-700">
                    <div class="grid grid-cols-2 items-center gap-x-5 gap-y-3 lg:grid-cols-[auto_minmax(14rem,1fr)_auto_auto]">
                        <a href="{{ route('learning-terms.index', $selectedBox ? ['box' => $selectedBox->id] : []) }}" @class(['col-start-1 row-start-1 justify-self-start border-b-2 py-2 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]', 'border-[#3155D9] text-[#3155D9]' => $status === 'active', 'border-transparent text-stone-500 hover:text-[#171717] dark:hover:text-stone-100' => $status !== 'active'])>保存中 {{ number_format($activeTermCount) }}語</a>

                        <form method="GET" action="{{ route('learning-terms.index') }}" class="col-span-2 row-start-2 flex min-w-0 items-center gap-2 lg:col-span-1 lg:col-start-2 lg:row-start-1">
                            @if ($status === 'deleted')
                                <input type="hidden" name="status" value="deleted">
                            @elseif ($selectedBox)
                                <input type="hidden" name="box" value="{{ $selectedBox->id }}">
                            @endif
                            <div class="relative min-w-0 flex-1">
                                <label for="term-search" class="sr-only">用語を検索</label>
                                <input id="term-search" name="q" value="{{ $searchQuery }}" maxlength="100" placeholder="{{ $selectedBox ? 'このカテゴリから検索...' : '用語を検索...' }}" class="min-h-10 w-full rounded-md border-stone-300 bg-white py-2 pl-3 pr-11 text-sm focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">
                                <button type="submit" aria-label="検索を実行" class="absolute inset-y-0 right-0 grid w-11 place-items-center text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#3155D9]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                        <circle cx="11" cy="11" r="6.5"></circle>
                                        <path d="m16 16 4 4"></path>
                                    </svg>
                                </button>
                            </div>
                            @if ($searchQuery !== '')
                                <a href="{{ route('learning-terms.index', array_filter(['status' => $status === 'deleted' ? 'deleted' : null, 'box' => $selectedBox?->id])) }}" class="shrink-0 text-sm text-stone-500 underline underline-offset-4">解除</a>
                            @endif
                        </form>

                        <a href="{{ route('learning-terms.index', ['status' => 'deleted']) }}" @class(['col-start-2 row-start-1 justify-self-end border-b-2 py-2 text-sm font-semibold focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] lg:col-start-3', 'border-[#3155D9] text-[#3155D9]' => $status === 'deleted', 'border-transparent text-stone-500 hover:text-[#171717] dark:hover:text-stone-100' => $status !== 'deleted'])>削除済み</a>

                        <button type="button" aria-controls="new-category-form" :aria-expanded="creatingCategory.toString()" @click="creatingCategory = ! creatingCategory" class="col-span-2 row-start-3 justify-self-start py-2 text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] lg:col-span-1 lg:col-start-4 lg:row-start-1 lg:justify-self-end">＋ カテゴリを作る</button>
                    </div>

                    <div id="new-category-form" x-cloak x-show="creatingCategory" x-transition class="mt-4 border-t border-stone-200 pt-4 dark:border-stone-700">
                        <form method="POST" action="{{ route('learning-term-boxes.store') }}" class="flex flex-col gap-3 sm:flex-row">
                            @csrf
                            <label for="new-box-name" class="sr-only">新しいカテゴリの名前</label>
                            <input id="new-box-name" name="name" value="{{ old('name') }}" required maxlength="80" placeholder="カテゴリ名" class="min-h-10 min-w-0 flex-1 rounded-md border-stone-300 bg-white px-3 text-sm focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">
                            <button type="submit" class="min-h-10 shrink-0 rounded-md border border-stone-300 px-4 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:border-stone-700">作成</button>
                        </form>
                        <x-input-error :messages="$errors->get('name')" class="mt-3" />
                    </div>
                </div>
                @endif
            </div>

            @if ($selectedBox)
                <div x-cloak x-show="renameCategoryOpen" @keydown.escape.window="renameCategoryOpen = false" class="fixed inset-0 z-50 overflow-y-auto px-4 py-8 sm:py-14" role="dialog" aria-modal="true" aria-labelledby="rename-category-title">
                    <div class="fixed inset-0 bg-stone-950/35" aria-hidden="true" @click="renameCategoryOpen = false"></div>
                    <div class="relative mx-auto max-w-md border border-stone-300 bg-[#FDFCFA] shadow-lg dark:border-stone-700 dark:bg-stone-900">
                        <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4 dark:border-stone-700">
                            <h3 id="rename-category-title" class="font-editorial text-xl font-semibold">名前を変更</h3>
                            <button type="button" @click="renameCategoryOpen = false" class="grid size-9 place-items-center text-xl text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]" aria-label="閉じる">×</button>
                        </div>
                        <form method="POST" action="{{ route('learning-term-boxes.update', $selectedBox) }}" class="p-5">
                            @csrf
                            @method('PATCH')
                            <label for="box-name-edit" class="sr-only">カテゴリ名</label>
                            <input id="box-name-edit" name="name" value="{{ old('name', $selectedBox->name) }}" required maxlength="80" class="min-h-11 w-full rounded-md border-stone-300 bg-white px-3 text-sm focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-950">
                            <x-input-error :messages="$errors->get('name')" class="mt-3" />
                            <div class="mt-5 flex justify-end">
                                <button type="submit" class="min-h-10 rounded-md border border-stone-300 px-5 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:border-stone-700">保存</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if ($selectedBox)
                <div x-cloak x-show="termPickerOpen" @keydown.escape.window="termPickerOpen = false" class="fixed inset-0 z-50 overflow-y-auto px-4 py-8 sm:py-14" role="dialog" aria-modal="true" aria-labelledby="term-picker-title">
                    <div class="fixed inset-0 bg-stone-950/35" aria-hidden="true" @click="termPickerOpen = false"></div>
                    <div class="relative mx-auto max-w-lg border border-stone-300 bg-[#FDFCFA] shadow-lg dark:border-stone-700 dark:bg-stone-900">
                        <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4 dark:border-stone-700">
                            <h3 id="term-picker-title" class="font-editorial text-xl font-semibold">用語を追加</h3>
                            <button type="button" @click="termPickerOpen = false" class="grid size-9 place-items-center text-xl text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]" aria-label="閉じる">×</button>
                        </div>

                        <form method="GET" action="{{ route('learning-terms.index') }}" class="border-b border-stone-200 p-5 dark:border-stone-700">
                            <input type="hidden" name="box" value="{{ $selectedBox->id }}">
                            <label for="term-picker-search" class="sr-only">追加する用語を検索</label>
                            <div class="relative">
                                <input id="term-picker-search" name="term_picker_q" value="{{ $termPickerQuery }}" maxlength="100" placeholder="用語を検索..." class="min-h-11 w-full rounded-md border-stone-300 bg-white py-2 pl-3 pr-11 text-sm focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-950">
                                <button type="submit" aria-label="追加する用語を検索" class="absolute inset-y-0 right-0 grid w-11 place-items-center text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#3155D9]">
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="size-4">
                                        <circle cx="11" cy="11" r="6.5"></circle>
                                        <path d="m16 16 4 4"></path>
                                    </svg>
                                </button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('learning-term-boxes.terms.update', $selectedBox) }}" class="p-5">
                            @csrf
                            @method('PUT')
                            @if ($termPickerTerms->isEmpty())
                                <p class="py-5 text-sm text-stone-500">{{ $termPickerQuery !== '' ? '一致する用語がありません。' : '追加できる用語がありません。' }}</p>
                            @else
                                <fieldset>
                                    <legend class="sr-only">追加する用語</legend>
                                    <div class="max-h-72 divide-y divide-stone-200 overflow-y-auto border-y border-stone-200 dark:divide-stone-700 dark:border-stone-700">
                                        @foreach ($termPickerTerms as $pickerTerm)
                                            @php($isAlreadySelected = $selectedPickerTermIds->contains($pickerTerm->id))
                                            <label class="flex min-h-11 items-center gap-3 px-1 py-2.5 text-sm leading-6 {{ $isAlreadySelected ? 'text-stone-500' : 'cursor-pointer' }}">
                                                <input type="checkbox" name="term_ids[]" value="{{ $pickerTerm->id }}" @checked($isAlreadySelected) @disabled($isAlreadySelected) class="size-[18px] shrink-0 rounded border-stone-300 text-[#3155D9] focus:ring-[#3155D9]">
                                                @if ($isAlreadySelected)
                                                    <input type="hidden" name="term_ids[]" value="{{ $pickerTerm->id }}">
                                                @endif
                                                <span class="min-w-0 break-words">{{ $pickerTerm->term }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                <p class="mt-3 text-xs leading-5 text-stone-500">検索結果を最大50語表示します。所属済みの用語を外す場合は、カテゴリ内の「外す」から変更できます。</p>
                                <div class="mt-5 flex justify-end">
                                    <button type="submit" class="min-h-10 rounded-md border border-stone-300 px-5 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:border-stone-700">保存</button>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            @endif

            @if ($status === 'active' && ! $selectedBox)
                <section class="border-b border-stone-300 py-9 dark:border-stone-700" aria-labelledby="categories-title">
                    <h3 id="categories-title" class="font-editorial text-2xl font-semibold">カテゴリ</h3>

                    @if ($manualBoxes->isEmpty())
                        <p class="mt-5 text-sm leading-7 text-stone-500">まだカテゴリはありません。必要なときに自分でカテゴリを作成できます。</p>
                    @else
                        <ol class="mt-6 divide-y divide-stone-300 border-t border-stone-300 dark:divide-stone-700 dark:border-stone-700">
                            @foreach ($manualBoxes as $box)
                                <li>
                                    <a href="{{ route('learning-terms.index', ['box' => $box->id]) }}" class="block py-5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">
                                        <div class="flex items-baseline justify-between gap-5">
                                            <span class="break-words font-editorial text-xl font-semibold">{{ $box->name }}</span>
                                            <span class="shrink-0 text-sm font-semibold text-[#178C78]">{{ number_format($box->active_terms_count) }}語</span>
                                        </div>
                                        @if ($searchQuery === '' && $box->learningTerms->isNotEmpty())
                                            <p class="mt-2 truncate text-sm text-stone-500">{{ $box->learningTerms->pluck('term')->implode('、') }}</p>
                                        @endif
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                <div class="border-b border-stone-300 py-8 dark:border-stone-700">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-baseline justify-between gap-5">
                            <h3 class="font-editorial text-2xl font-semibold">すべての用語</h3>
                            <span class="shrink-0 text-sm font-semibold text-[#178C78]">{{ number_format($activeTermCount) }}語</span>
                        </div>
                        <div x-cloak x-show="! bulkMode" class="flex justify-end">
                            <button type="button" @click="bulkMode = true; selectedTermIds = []" class="text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">まとめて追加</button>
                        </div>
                        <div x-cloak x-show="bulkMode" @keydown.escape.window="bulkMode = false; selectedTermIds = []; bulkModalOpen = false" class="flex flex-wrap items-center justify-end gap-x-4 gap-y-3">
                            <span class="text-sm font-semibold text-[#178C78]"><span x-text="selectedTermIds.length">0</span>語選択</span>
                            <button type="button" @click="selectedTermIds = [...new Set([...selectedTermIds, ...@js($terms->getCollection()->pluck('id')->values())])]" class="text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">表示中をすべて選択</button>
                            <button type="button" @click="bulkMode = false; selectedTermIds = []; bulkModalOpen = false" class="text-sm font-semibold text-stone-500 underline-offset-4 hover:text-[#171717] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:hover:text-stone-100">キャンセル</button>
                            <button type="button" :disabled="selectedTermIds.length === 0" @click="bulkModalOpen = true" class="text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline disabled:cursor-not-allowed disabled:text-stone-400 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">カテゴリへ追加</button>
                        </div>
                    </div>
                </div>

                <div x-cloak x-show="bulkModalOpen" @keydown.escape.window="bulkModalOpen = false" class="fixed inset-0 z-50 overflow-y-auto px-4 py-8 sm:py-14" role="dialog" aria-modal="true" aria-labelledby="bulk-category-picker-title">
                    <div class="fixed inset-0 bg-stone-950/35" aria-hidden="true" @click="bulkModalOpen = false"></div>
                    <div class="relative mx-auto max-w-md border border-stone-300 bg-[#FDFCFA] shadow-lg dark:border-stone-700 dark:bg-stone-900">
                        <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4 dark:border-stone-700">
                            <h3 id="bulk-category-picker-title" class="font-editorial text-xl font-semibold">カテゴリへ追加</h3>
                            <button type="button" @click="bulkModalOpen = false" class="grid size-9 place-items-center text-xl text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]" aria-label="閉じる">×</button>
                        </div>
                        <form method="POST" action="{{ route('learning-terms.bulk-categories.update') }}" class="p-5">
                            @csrf
                            <p class="text-sm font-semibold text-stone-600 dark:text-stone-300"><span x-text="selectedTermIds.length">0</span>語を選択中</p>
                            <template x-for="termId in selectedTermIds" :key="termId">
                                <input type="hidden" name="term_ids[]" :value="termId">
                            </template>
                            @if ($manualCategories->isEmpty())
                                <p class="mt-5 text-sm text-stone-500">まだカテゴリがありません。</p>
                                <button type="button" @click="bulkModalOpen = false; bulkMode = false; selectedTermIds = []; $dispatch('open-category-creator')" class="mt-4 text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">＋ カテゴリを作る</button>
                            @else
                                <fieldset class="mt-4">
                                    <legend class="sr-only">追加するカテゴリ</legend>
                                    <div class="divide-y divide-stone-200 border-y border-stone-200 dark:divide-stone-700 dark:border-stone-700">
                                        @foreach ($manualCategories as $category)
                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 px-1 py-2.5 text-sm leading-6">
                                                <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" class="size-[18px] shrink-0 rounded border-stone-300 text-[#3155D9] focus:ring-[#3155D9]">
                                                <span class="min-w-0 break-words">{{ $category->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                <div class="mt-5 flex justify-end">
                                    <button type="submit" class="min-h-10 rounded-md border border-stone-300 px-5 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:border-stone-700">追加する</button>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>
            @endif

            <div class="pt-9">
                @if ($status === 'deleted')
                    <h3 class="font-editorial text-2xl font-semibold">削除済みの用語</h3>
                @endif

                @if ($terms->isEmpty())
                    <div class="py-12">
                        @if ($selectedBox)
                            <p class="font-editorial text-xl font-semibold">このカテゴリにはまだ用語がありません。</p>
                        @else
                            <p class="font-editorial text-xl font-semibold">{{ $searchQuery !== '' ? '一致する用語がありません。' : ($status === 'deleted' ? '削除済みの用語はありません。' : 'まだ保存した用語はありません。') }}</p>
                            <p class="mt-3 text-sm leading-7 text-stone-500">{{ $searchQuery !== '' ? '検索語を変えてお試しください。' : ($status === 'active' ? '問題文や解説の気になる言葉を選択すると保存できます。' : '削除した用語はここから復元できます。') }}</p>
                        @endif
                    </div>
                @else
                    <ol class="mt-5 divide-y divide-stone-300 border-t border-stone-300 dark:divide-stone-700 dark:border-stone-700">
                        @foreach ($terms as $term)
                            <li class="py-7 sm:py-8">
                                <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex min-w-0 flex-1 gap-3">
                                        @if ($status === 'active' && ! $selectedBox)
                                            <label x-cloak x-show="bulkMode" class="flex min-h-11 shrink-0 cursor-pointer items-center gap-2 py-1" aria-label="{{ $term->term }}を選択">
                                                <input type="checkbox" x-model.number="selectedTermIds" value="{{ $term->id }}" class="size-[18px] shrink-0 rounded border-stone-300 text-[#3155D9] focus:ring-[#3155D9]">
                                            </label>
                                        @endif
                                        <div class="min-w-0">
                                            <h4 class="break-words font-editorial text-2xl font-semibold">{{ $term->term }}</h4>
                                            <p class="mt-3 whitespace-pre-line break-words text-sm leading-7 text-stone-700 dark:text-stone-300">{{ $term->description }}</p>
                                        </div>
                                    </div>
                                    <div @if ($status === 'active' && ! $selectedBox) x-cloak x-show="! bulkMode" @endif class="flex shrink-0 flex-wrap items-center gap-x-5 gap-y-3">
                                        @if ($selectedBox)
                                            <a href="{{ route('learning-terms.edit', $term) }}" class="text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">編集</a>
                                            <form method="POST" action="{{ route('learning-term-boxes.terms.destroy', [$selectedBox, $term]) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-semibold text-stone-500 underline-offset-4 hover:text-[#171717] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:hover:text-stone-100">外す</button>
                                            </form>
                                        @elseif ($status === 'active')
                                            <div x-data="{ categoryOpen: false }">
                                                <button type="button" @click="categoryOpen = true" class="text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">追加</button>

                                                <div x-cloak x-show="categoryOpen" @keydown.escape.window="categoryOpen = false" class="fixed inset-0 z-50 overflow-y-auto px-4 py-8 sm:py-14" role="dialog" aria-modal="true" aria-labelledby="category-picker-title-{{ $term->id }}">
                                                    <div class="fixed inset-0 bg-stone-950/35" aria-hidden="true" @click="categoryOpen = false"></div>
                                                    <div class="relative mx-auto max-w-md border border-stone-300 bg-[#FDFCFA] shadow-lg dark:border-stone-700 dark:bg-stone-900">
                                                        <div class="flex items-center justify-between border-b border-stone-200 px-5 py-4 dark:border-stone-700">
                                                            <h5 id="category-picker-title-{{ $term->id }}" class="font-editorial text-xl font-semibold">カテゴリに追加</h5>
                                                            <button type="button" @click="categoryOpen = false" class="grid size-9 place-items-center text-xl text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]" aria-label="閉じる">×</button>
                                                        </div>

                                                        @if ($manualCategories->isEmpty())
                                                            <div class="p-5">
                                                                <p class="text-sm text-stone-500">まだカテゴリがありません。</p>
                                                                <button type="button" @click="categoryOpen = false; $dispatch('open-category-creator')" class="mt-4 text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">＋ カテゴリを作る</button>
                                                            </div>
                                                        @else
                                                            <form method="POST" action="{{ route('learning-terms.boxes.update', $term) }}" class="p-5">
                                                                @csrf
                                                                @method('PUT')
                                                                <input type="hidden" name="return_to" value="index">
                                                                @if ($selectedBox)
                                                                    <input type="hidden" name="return_box_id" value="{{ $selectedBox->id }}">
                                                                @endif
                                                                <fieldset>
                                                                    <legend class="sr-only">所属するカテゴリ</legend>
                                                                    <div class="max-h-72 divide-y divide-stone-200 overflow-y-auto border-y border-stone-200 dark:divide-stone-700 dark:border-stone-700">
                                                                        @foreach ($manualCategories as $category)
                                                                            <label class="flex min-h-11 cursor-pointer items-center gap-3 px-1 py-2.5 text-sm leading-6">
                                                                                <input type="checkbox" name="manual_box_ids[]" value="{{ $category->id }}" @checked($term->boxes->contains('id', $category->id)) class="size-[18px] shrink-0 rounded border-stone-300 text-[#3155D9] focus:ring-[#3155D9]">
                                                                                <span class="min-w-0 break-words">{{ $category->name }}</span>
                                                                            </label>
                                                                        @endforeach
                                                                    </div>
                                                                </fieldset>
                                                                <div class="mt-5 flex justify-end">
                                                                    <button type="submit" class="min-h-10 rounded-md border border-stone-300 px-5 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:border-stone-700">保存</button>
                                                                </div>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <a href="{{ route('learning-terms.edit', $term) }}" class="text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">編集</a>
                                            <form method="POST" action="{{ route('learning-terms.destroy', $term) }}" onsubmit="return confirm('この用語をマイ用語から削除しますか？')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-sm font-semibold text-[#B4534B] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">削除</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('learning-terms.restore', $term->id) }}">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="text-sm font-semibold text-[#3155D9] underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">復元</button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    @if ($terms->hasPages())
                        <div class="border-t border-stone-300 pt-6 dark:border-stone-700">{{ $terms->links() }}</div>
                    @endif
                @endif
            </div>
        </section>
    </main>
</x-app-layout>
