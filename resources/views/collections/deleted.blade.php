<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('collections.index') }}" class="text-sm text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">← コレクション</a>
    </x-slot>

    <main class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14">
        @if (session('status'))
            <p role="status" class="mb-8 border border-teal-200 bg-teal-50 px-5 py-4 text-sm font-medium text-teal-900">{{ session('status') }}</p>
        @endif
        <header>
            <p class="text-[10px] font-semibold tracking-[0.18em] text-[#3155D9]">ARCHIVE</p>
            <h1 class="mt-2 font-editorial text-4xl font-semibold">削除済み</h1>
            <p class="mt-3 text-sm leading-7 text-stone-500">物理削除は行いません。必要な学習セットやコレクションを復元できます。</p>
        </header>

        <section class="mt-10" aria-labelledby="deleted-learning-sets-title">
            <h2 id="deleted-learning-sets-title" class="border-b border-stone-300 pb-4 font-editorial text-2xl font-semibold dark:border-stone-700">学習セット</h2>
            @forelse ($deletedLearningSets as $learningSet)
                <div class="flex flex-col justify-between gap-4 border-b border-stone-300 py-5 sm:flex-row sm:items-center dark:border-stone-700">
                    <div><p class="font-semibold">{{ $learningSet->title }}</p><p class="mt-1 text-xs text-stone-500">{{ $learningSet->questions_count }}問 · {{ $learningSet->deleted_at->format('Y/m/d H:i') }}削除</p></div>
                    <form method="POST" action="{{ route('learning-sets.restore', $learningSet->id) }}">@csrf<button type="submit" class="text-sm font-semibold text-[#3155D9]">復元する →</button></form>
                </div>
            @empty
                <p class="py-8 text-sm text-stone-500">削除済みの学習セットはありません。</p>
            @endforelse
        </section>

        <section class="mt-12" aria-labelledby="deleted-collections-title">
            <h2 id="deleted-collections-title" class="border-b border-stone-300 pb-4 font-editorial text-2xl font-semibold dark:border-stone-700">コレクション</h2>
            @forelse ($deletedCollections as $collection)
                <div class="flex flex-col justify-between gap-4 border-b border-stone-300 py-5 sm:flex-row sm:items-center dark:border-stone-700">
                    <div><p class="font-semibold">{{ $collection->name }}</p><p class="mt-1 text-xs text-stone-500">{{ $collection->deleted_at->format('Y/m/d H:i') }}削除</p></div>
                    <form method="POST" action="{{ route('collections.restore', $collection->id) }}">@csrf<button type="submit" class="text-sm font-semibold text-[#3155D9]">復元する →</button></form>
                </div>
            @empty
                <p class="py-8 text-sm text-stone-500">削除済みのコレクションはありません。</p>
            @endforelse
        </section>
    </main>
</x-app-layout>
