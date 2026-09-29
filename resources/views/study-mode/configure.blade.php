<x-app-layout>
    <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
        <a href="{{ route('study-mode.index') }}" class="text-sm text-stone-500 hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">← 学習モード</a>
        <header class="mt-7 border-b border-stone-300 pb-7 dark:border-stone-700">
            <p @class(['text-xs font-semibold tracking-[0.2em]', 'text-[#3155D9]' => $mode === 'input', 'text-[#178C78]' => $mode === 'random'])>{{ $mode === 'input' ? 'INPUT MODE' : 'RANDOM MODE' }}</p>
            <h1 class="mt-3 font-editorial text-4xl font-semibold">{{ $mode === 'input' ? '入力式で学ぶ' : 'ランダムで学ぶ' }}</h1>
            <p class="mt-4 text-sm leading-7 text-stone-600 dark:text-stone-300">{{ $mode === 'input' ? '選択肢を見ずに、自分の言葉で答えます。' : '保存した問題から、ランダムに4択で出題します。' }}</p>
        </header>

        @if (session('status'))<p class="mt-6 border-s-2 border-[#B4534B] ps-4 text-sm text-[#B4534B]">{{ session('status') }}</p>@endif

        @if ($questionCount === 0)
            <section class="mt-9 border-y border-stone-300 py-9 dark:border-stone-700"><h2 class="font-editorial text-2xl font-semibold">まだ学習できる問題がありません。</h2><a href="{{ route('dashboard') }}#learning-sets" class="mt-5 inline-block text-sm font-semibold text-[#3155D9] underline underline-offset-4">学習セットを見る</a></section>
        @else
            <form method="POST" action="{{ route('study-mode.start', $mode) }}" class="mt-9 grid gap-9" x-data="{ scope: '{{ old('scope', $selectedCollectionId ? 'collection' : 'all') }}', submitting: false }" @submit="submitting = true">
                @csrf
                <fieldset>
                    <legend class="font-semibold">対象</legend>
                    <div class="mt-4 grid gap-3">
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-stone-300 bg-white px-4 dark:border-stone-700 dark:bg-stone-900"><input type="radio" name="scope" value="all" x-model="scope" class="text-[#3155D9] focus:ring-[#3155D9]">すべての問題</label>
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-stone-300 bg-white px-4 dark:border-stone-700 dark:bg-stone-900"><input type="radio" name="scope" value="collection" x-model="scope" class="text-[#3155D9] focus:ring-[#3155D9]">コレクションから選ぶ</label>
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-stone-300 bg-white px-4 dark:border-stone-700 dark:bg-stone-900"><input type="radio" name="scope" value="learning_set" x-model="scope" class="text-[#3155D9] focus:ring-[#3155D9]">学習セットから選ぶ</label>
                    </div>
                    <div class="mt-4" x-show="scope === 'collection'" x-cloak>
                        <label for="collection_id" class="text-sm font-medium">コレクション</label>
                        <select id="collection_id" name="collection_id" class="mt-2 min-h-12 w-full rounded-lg border-stone-300 bg-white focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">
                            @foreach ($collections as $collection)<option value="{{ $collection->id }}" @selected((int) old('collection_id', $selectedCollectionId) === $collection->id)>{{ $collection->name }}（{{ $collection->questions_count }}問・{{ $collection->learning_sets_count }}セット）</option>@endforeach
                        </select>
                    </div>
                    <div class="mt-4" x-show="scope === 'learning_set'" x-cloak>
                        <label for="learning_set_id" class="text-sm font-medium">学習セット</label>
                        <select id="learning_set_id" name="learning_set_id" class="mt-2 min-h-12 w-full rounded-lg border-stone-300 bg-white focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">
                            @foreach ($learningSets as $learningSet)<option value="{{ $learningSet->id }}" @selected((int) old('learning_set_id') === $learningSet->id)>{{ $learningSet->title }}（{{ $learningSet->questions_count }}問）</option>@endforeach
                        </select>
                    </div>
                    <x-input-error :messages="$errors->get('scope')" class="mt-2" /><x-input-error :messages="$errors->get('collection_id')" class="mt-2" /><x-input-error :messages="$errors->get('learning_set_id')" class="mt-2" />
                </fieldset>
                <fieldset>
                    <legend class="font-semibold">問題数</legend>
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        @foreach ([5, 10, 20] as $count)<label class="flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-lg border border-stone-300 bg-white text-sm dark:border-stone-700 dark:bg-stone-900"><input type="radio" name="question_count" value="{{ $count }}" class="text-[#3155D9] focus:ring-[#3155D9]" @checked((int) old('question_count', 10) === $count)>{{ $count }}問</label>@endforeach
                    </div>
                    <x-input-error :messages="$errors->get('question_count')" class="mt-2" />
                </fieldset>
                <button type="submit" :disabled="submitting" class="inline-flex min-h-12 items-center justify-center rounded-lg bg-[#3155D9] px-7 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">{{ $mode === 'input' ? '入力式を始める →' : 'ランダム学習を始める →' }}</button>
            </form>
        @endif
    </main>
</x-app-layout>
