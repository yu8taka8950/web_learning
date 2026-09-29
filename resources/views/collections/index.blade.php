<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <p class="text-[10px] font-semibold tracking-[0.18em] text-[#3155D9]">COLLECTIONS</p>
                <h1 class="mt-1 font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">コレクション</h1>
            </div>
            <a href="{{ route('collections.deleted') }}" class="text-sm text-stone-500 underline underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">削除済み</a>
        </div>
    </x-slot>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
        @if (session('status'))
            <p role="status" class="mb-8 border border-teal-200 bg-teal-50 px-5 py-4 text-sm font-medium text-teal-900 dark:border-teal-900 dark:bg-teal-950/40 dark:text-teal-200">{{ session('status') }}</p>
        @endif

        <div class="grid gap-12 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section aria-labelledby="collection-list-title">
                <div class="flex flex-wrap items-end justify-between gap-4 border-b border-stone-300 pb-5 dark:border-stone-700">
                    <div>
                        <h2 id="collection-list-title" class="font-editorial text-3xl font-semibold">学びを、テーマごとにまとめる。</h2>
                        <p class="mt-3 text-sm leading-7 text-stone-500">関連する学習セットを集めて、好きなテーマから学べます。</p>
                    </div>
                    <form method="POST" action="{{ route('collections.auto-organize') }}">
                        @csrf
                        <button type="submit" class="text-sm font-semibold text-[#3155D9] underline underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">既存の学習セットを自動整理 →</button>
                    </form>
                </div>

                @if ($collections->isEmpty())
                    <div class="py-14">
                        <p class="font-editorial text-xl font-semibold">まだコレクションがありません。</p>
                        <p class="mt-3 text-sm leading-7 text-stone-500">学習セットが増えると、テーマごとに整理できます。</p>
                    </div>
                @else
                    <ol class="divide-y divide-stone-300 dark:divide-stone-700">
                        @foreach ($collections as $collection)
                            <li class="py-8">
                                <div class="flex flex-col justify-between gap-6 sm:flex-row sm:items-end">
                                    <div class="min-w-0">
                                        @if ($collection->auto_rule)
                                            <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#178C78]">AUTO · {{ $collection->auto_rule }}</p>
                                        @endif
                                        <h3 class="mt-2 break-words font-editorial text-3xl font-semibold">{{ $collection->name }}</h3>
                                        <p class="mt-3 text-sm font-medium text-stone-600 dark:text-stone-300">{{ $collection->learning_sets_count }}セット · {{ $collection->questions_count }}問</p>
                                        @if ($collection->description)
                                            <p class="mt-3 max-w-2xl text-sm leading-7 text-stone-500">{{ $collection->description }}</p>
                                        @endif
                                    </div>
                                    <a href="{{ route('collections.show', $collection) }}" class="shrink-0 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">コレクションを見る →</a>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <aside class="border-t border-stone-300 pt-8 dark:border-stone-700 lg:border-l lg:border-t-0 lg:pl-8 lg:pt-0" aria-labelledby="new-collection-title">
                <p class="text-[10px] font-semibold tracking-[0.18em] text-[#3155D9]">NEW COLLECTION</p>
                <h2 id="new-collection-title" class="mt-2 font-editorial text-2xl font-semibold">新しいコレクション</h2>
                <form method="POST" action="{{ route('collections.store') }}" class="mt-7 grid gap-6">
                    @csrf
                    <div>
                        <label for="collection-name" class="text-sm font-semibold">名前</label>
                        <input id="collection-name" name="name" value="{{ old('name') }}" required maxlength="255" class="mt-2 block min-h-12 w-full rounded-lg border-stone-300 bg-white focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <label for="collection-description" class="text-sm font-semibold">説明 <span class="font-normal text-stone-400">任意</span></label>
                        <textarea id="collection-description" name="description" rows="4" maxlength="2000" class="mt-2 block w-full rounded-lg border-stone-300 bg-white focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                    <button type="submit" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-[#3155D9] px-6 text-sm font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2">作成する →</button>
                </form>
            </aside>
        </div>
    </main>
</x-app-layout>
