<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('collections.index') }}" class="text-sm text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">← コレクション</a>
    </x-slot>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
        @if (session('status'))
            <p role="status" class="mb-8 border border-teal-200 bg-teal-50 px-5 py-4 text-sm font-medium text-teal-900">{{ session('status') }}</p>
        @endif

        <header class="border-b border-stone-300 pb-8 dark:border-stone-700">
            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#3155D9]">{{ $collection->auto_rule ?: 'COLLECTION' }}</p>
            <div class="mt-3 flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                <div>
                    <h1 class="max-w-3xl break-words font-editorial text-4xl font-semibold sm:text-5xl">{{ $collection->name }}</h1>
                    <p class="mt-4 text-sm font-medium text-stone-600 dark:text-stone-300">{{ $collection->learningSets->count() }}セット · {{ $questionCount }}問</p>
                    @if ($collection->description)
                        <p class="mt-4 max-w-2xl text-sm leading-7 text-stone-500">{{ $collection->description }}</p>
                    @endif
                </div>
                @if ($questionCount > 0)
                    <div class="flex flex-wrap gap-4">
                        <a href="{{ route('study-mode.input', ['collection' => $collection->id]) }}" class="inline-flex min-h-11 items-center rounded-lg bg-[#3155D9] px-5 text-sm font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2">入力式で学ぶ →</a>
                        <a href="{{ route('study-mode.random', ['collection' => $collection->id]) }}" class="inline-flex min-h-11 items-center border-b border-[#3155D9] px-2 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">ランダムで学ぶ →</a>
                    </div>
                @endif
            </div>
        </header>

        <div class="mt-10 grid gap-12 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section aria-labelledby="collection-learning-sets-title">
                <h2 id="collection-learning-sets-title" class="text-xs font-semibold tracking-[0.16em] text-[#3155D9]">LEARNING SETS</h2>
                @if ($collection->learningSets->isEmpty())
                    <div class="py-10">
                        <p class="font-editorial text-xl font-semibold">このコレクションには学習できる問題がありません。</p>
                        <a href="{{ route('dashboard') }}" class="mt-5 inline-flex text-sm font-semibold text-[#3155D9]">Dashboardで学習セットを追加する →</a>
                    </div>
                @else
                    <ol class="mt-4 divide-y divide-stone-300 border-y border-stone-300 dark:divide-stone-700 dark:border-stone-700">
                        @foreach ($collection->learningSets as $learningSet)
                            <li class="flex flex-col justify-between gap-5 py-6 sm:flex-row sm:items-center">
                                <div class="min-w-0">
                                    <h3 class="break-words font-editorial text-xl font-semibold">{{ $learningSet->topic ?: $learningSet->title }}</h3>
                                    <p class="mt-2 text-sm text-stone-500">{{ $learningSet->questions_count }}問</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-5">
                                    <a href="{{ route('learning-sets.quiz', $learningSet) }}" class="text-sm font-semibold text-[#3155D9]">学習する →</a>
                                    <form method="POST" action="{{ route('collections.learning-sets.destroy', [$collection, $learningSet]) }}" onsubmit="return confirm('この学習セットをコレクションから外しますか？ 学習セット自体は削除されません。')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-stone-500 underline underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">コレクションから外す</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <aside class="border-t border-stone-300 pt-8 dark:border-stone-700 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0" x-data="{ confirmingDelete: false }">
                <p class="text-[10px] font-semibold tracking-[0.18em] text-[#3155D9]">SETTINGS</p>
                <h2 class="mt-2 font-editorial text-2xl font-semibold">コレクションを編集</h2>
                <form method="POST" action="{{ route('collections.update', $collection) }}" class="mt-7 grid gap-5">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="collection-name" class="text-sm font-semibold">名前</label>
                        <input id="collection-name" name="name" value="{{ old('name', $collection->name) }}" required maxlength="255" class="mt-2 block min-h-12 w-full rounded-lg border-stone-300 bg-white focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <label for="collection-description" class="text-sm font-semibold">説明</label>
                        <textarea id="collection-description" name="description" rows="4" maxlength="2000" class="mt-2 block w-full rounded-lg border-stone-300 bg-white focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">{{ old('description', $collection->description) }}</textarea>
                    </div>
                    <button type="submit" class="min-h-11 rounded-lg bg-[#3155D9] px-5 text-sm font-semibold text-white">更新する</button>
                </form>
                <button type="button" class="mt-8 text-xs font-semibold text-[#B4534B] underline underline-offset-4" @click="confirmingDelete = true">コレクションを削除</button>

                <div x-cloak x-show="confirmingDelete" @keydown.escape.window="confirmingDelete = false" class="fixed inset-0 z-50 grid place-items-center bg-stone-950/35 p-4" role="dialog" aria-modal="true" aria-labelledby="delete-collection-title">
                    <div class="w-full max-w-md rounded-xl border border-stone-200 bg-white p-6 shadow-xl dark:border-stone-700 dark:bg-stone-900" @click.outside="confirmingDelete = false">
                        <h3 id="delete-collection-title" class="font-editorial text-xl font-semibold">「{{ $collection->name }}」を削除しますか？</h3>
                        <p class="mt-3 text-sm leading-7 text-stone-500">コレクションだけが非表示になります。学習セットと学習履歴は保持されます。</p>
                        <form method="POST" action="{{ route('collections.destroy', $collection) }}" class="mt-6 flex justify-end gap-3">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="min-h-11 px-4 text-sm font-semibold text-stone-600" @click="confirmingDelete = false">キャンセル</button>
                            <button type="submit" class="min-h-11 rounded-lg bg-[#B4534B] px-5 text-sm font-semibold text-white">削除する</button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>
    </main>
</x-app-layout>
