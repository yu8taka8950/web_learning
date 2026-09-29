<x-app-layout>
    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14">
        <header class="max-w-2xl">
            <p class="text-xs font-semibold tracking-[0.2em] text-[#3155D9]">STUDY MODE</p>
            <h1 class="mt-3 font-editorial text-4xl font-semibold sm:text-5xl">学習モード</h1>
            <p class="mt-5 leading-8 text-stone-600 dark:text-stone-300">いつもの4択とは違う方法で、知識を確かめる。</p>
        </header>

        @if (session('status'))
            <p role="status" class="mt-7 border border-amber-200 bg-amber-50 px-5 py-4 text-sm font-medium text-amber-900 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200">{{ session('status') }}</p>
        @endif

        @if ($questionCount === 0)
            <section class="mt-10 border-y border-stone-300 py-10 dark:border-stone-700">
                <h2 class="font-editorial text-2xl font-semibold">まだ学習できる問題がありません。</h2>
                <p class="mt-3 text-sm leading-7 text-stone-600 dark:text-stone-300">AIで問題を作成して保存すると、ここで自由に練習できます。</p>
                <div class="mt-6 flex flex-wrap gap-5 text-sm font-semibold">
                    <a href="{{ route('dashboard') }}#learning-sets" class="text-[#3155D9] underline underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">学習セットを見る</a>
                    <a href="{{ route('screenshot-learning.create') }}" class="text-[#3155D9] underline underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">スクリーンショットから学ぶ</a>
                </div>
            </section>
        @else
            <div class="mt-12 grid gap-6 lg:grid-cols-2">
                <article class="flex min-h-72 flex-col rounded-xl border border-stone-300 bg-white p-7 shadow-[0_10px_30px_rgba(23,23,23,0.035)] dark:border-stone-700 dark:bg-stone-900 sm:p-9">
                    <p class="text-[11px] font-semibold tracking-[0.2em] text-[#3155D9]">INPUT MODE</p>
                    <h2 class="mt-5 font-editorial text-3xl font-semibold">入力式で学ぶ</h2>
                    <p class="mt-4 text-sm leading-7 text-stone-600 dark:text-stone-300">選択肢を見ずに、AIが作った問題へ自分の言葉で答える。</p>
                    <a href="{{ route('study-mode.input') }}" class="mt-auto self-end pt-8 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">入力式を始める →</a>
                </article>
                <article class="flex min-h-72 flex-col rounded-xl border border-stone-300 bg-white p-7 shadow-[0_10px_30px_rgba(23,23,23,0.035)] dark:border-stone-700 dark:bg-stone-900 sm:p-9">
                    <p class="text-[11px] font-semibold tracking-[0.2em] text-[#178C78]">RANDOM MODE</p>
                    <h2 class="mt-5 font-editorial text-3xl font-semibold">ランダムで学ぶ</h2>
                    <p class="mt-4 text-sm leading-7 text-stone-600 dark:text-stone-300">保存した問題からランダムに選び、いつもの4択形式で学ぶ。</p>
                    <a href="{{ route('study-mode.random') }}" class="mt-auto self-end pt-8 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">ランダム学習 →</a>
                </article>
            </div>
            <p class="mt-6 text-right text-xs text-stone-500 dark:text-stone-400">保存済み {{ $questionCount }}問から出題できます</p>
        @endif
    </main>
</x-app-layout>
