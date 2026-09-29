<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">問題を追加: {{ $learningSet->title }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('learning-sets.questions.store', $learningSet) }}" class="flex flex-col gap-6 bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                @csrf

                <div>
                    <x-input-label for="question" value="問題文" />
                    <textarea id="question" name="question" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>{{ old('question') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('question')" />
                </div>

                @foreach (['a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D'] as $suffix => $label)
                    <div>
                        <x-input-label :for="'option_'.$suffix" :value="'選択肢 '.$label" />
                        <x-text-input :id="'option_'.$suffix" :name="'option_'.$suffix" type="text" class="mt-1 block w-full" :value="old('option_'.$suffix)" required />
                        <x-input-error class="mt-2" :messages="$errors->get('option_'.$suffix)" />
                    </div>
                @endforeach

                <div>
                    <x-input-label for="correct_option" value="正解" />
                    <select id="correct_option" name="correct_option" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" required>
                        <option value="">選択してください</option>
                        @foreach (['A', 'B', 'C', 'D'] as $option)
                            <option value="{{ $option }}" @selected(old('correct_option') === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('correct_option')" />
                </div>

                <div>
                    <x-input-label for="explanation" value="解説（任意）" />
                    <textarea id="explanation" name="explanation" rows="4" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ old('explanation') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('explanation')" />
                </div>

                <div class="flex gap-3">
                    <x-primary-button>問題を追加する</x-primary-button>
                    <a href="{{ route('learning-sets.quiz', $learningSet) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
