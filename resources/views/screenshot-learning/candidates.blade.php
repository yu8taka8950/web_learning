<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">学習候補を選択</h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('screenshot-learning.generate', $draft->token) }}" class="flex flex-col gap-6">
                @csrf
                <section class="flex flex-col gap-2 rounded-xl bg-white p-5 shadow-sm dark:bg-gray-800 sm:p-6">
                    <p class="text-sm font-medium text-indigo-700 dark:text-indigo-300">Geminiの解析結果</p>
                    <h1 class="break-words text-xl font-semibold leading-relaxed text-gray-900 dark:text-gray-100">{{ $draft->source_title }}</h1>
                    <p class="text-gray-600 dark:text-gray-300">問題にしたい候補を1〜20件選んでください。</p>
                    <x-input-error :messages="$errors->get('candidates')" />
                </section>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($draft->selected_terms as $index => $candidate)
                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/20">
                            <input type="checkbox" name="candidates[]" value="{{ $index }}" @checked(old('candidates') === null || in_array((string) $index, old('candidates', []), true)) class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="min-w-0">
                                <span class="block break-words font-semibold text-gray-900 dark:text-gray-100">{{ $candidate['term'] }}</span>
                                <span class="mt-1 block break-words leading-7 text-gray-600 dark:text-gray-300">{{ $candidate['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <x-primary-button class="w-fit">選択した候補から問題を作る</x-primary-button>
            </form>
        </div>
    </div>
</x-app-layout>
