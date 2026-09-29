<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">学習セットを作成</h2>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('learning-sets.store') }}" class="flex flex-col gap-6 bg-white p-6 shadow-sm sm:rounded-lg dark:bg-gray-800">
                @csrf

                <div>
                    <x-input-label for="title" value="タイトル" />
                    <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('title')" />
                </div>

                <div class="flex gap-3">
                    <x-primary-button>作成する</x-primary-button>
                    <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">キャンセル</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
