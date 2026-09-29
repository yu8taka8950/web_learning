<x-app-layout>
    <x-slot name="header">
        <h1 class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">用語を編集</h1>
    </x-slot>

    <main class="mx-auto max-w-2xl px-4 py-10 sm:px-6 sm:py-14">
        @php($learningSet = $learningTerm->learningSetIncludingDeleted)

        @if (session('status') || session('error'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-cloak x-show="show" x-transition role="status" aria-live="polite" class="fixed inset-x-4 top-[4.5rem] z-[60] border border-stone-200 bg-[#FDFCFA] shadow-sm dark:border-stone-700 dark:bg-stone-900 sm:left-auto sm:right-6 sm:w-[360px]">
                <div class="flex items-start gap-4 px-4 py-4">
                    <p class="min-w-0 flex-1 text-sm font-medium leading-6 text-[#171717] dark:text-stone-100">{{ session('status') ?? session('error') }}</p>
                    <button type="button" class="grid size-8 shrink-0 place-items-center text-lg text-stone-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]" aria-label="通知を閉じる" @click="show = false">×</button>
                </div>
            </div>
        @endif

        <div class="border-b border-stone-300 pb-6 dark:border-stone-700">
            <h2 class="font-editorial text-3xl font-semibold">自分に分かりやすい言葉で残す。</h2>
            <p class="mt-3 break-words text-sm leading-7 text-stone-500">元の学習セット：{{ $learningSet->title }}@if ($learningSet->trashed())（削除済み）@endif</p>
        </div>

        <form method="POST" action="{{ route('learning-terms.update', $learningTerm) }}" class="mt-8 grid gap-7">
            @csrf
            @method('PATCH')

            <div>
                <label for="term" class="text-sm font-semibold">用語</label>
                <input id="term" name="term" value="{{ old('term', $learningTerm->term) }}" required maxlength="100" autofocus class="mt-2 block min-h-12 w-full rounded-md border-stone-300 bg-white px-4 focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">
                <x-input-error :messages="$errors->get('term')" class="mt-2" />
            </div>

            <div>
                <label for="description" class="text-sm font-semibold">説明</label>
                <textarea id="description" name="description" required maxlength="500" rows="7" class="mt-2 block w-full rounded-md border-stone-300 bg-white px-4 py-3 leading-7 focus:border-[#3155D9] focus:ring-[#3155D9] dark:border-stone-700 dark:bg-stone-900">{{ old('description', $learningTerm->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" class="mt-2" />
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-stone-300 pt-6 dark:border-stone-700 sm:flex-row sm:items-center sm:justify-between">
                <a href="{{ route('learning-terms.index') }}" class="inline-flex min-h-11 items-center justify-center text-sm font-semibold text-stone-500 underline underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">一覧へ戻る</a>
                <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md bg-[#3155D9] px-6 text-sm font-semibold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2">保存する</button>
            </div>
        </form>

        <section class="mt-10 border-t border-stone-300 pt-8 dark:border-stone-700" aria-labelledby="manual-boxes-title">
            <h2 id="manual-boxes-title" class="font-editorial text-2xl font-semibold">カテゴリ</h2>
            <p class="mt-3 text-sm leading-7 text-stone-500">この用語を、目的に合わせて複数のカテゴリへ追加できます。</p>

            <form method="POST" action="{{ route('learning-terms.boxes.update', $learningTerm) }}" class="mt-5 grid gap-5">
                @csrf
                @method('PUT')

                @if ($manualBoxes->isEmpty())
                    <p class="text-sm text-stone-500">追加できるカテゴリはまだありません。マイ用語の一覧から作成できます。</p>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($manualBoxes as $box)
                            <label class="flex min-h-11 items-center gap-3 border-b border-stone-200 py-2 text-sm dark:border-stone-800">
                                <input type="checkbox" name="manual_box_ids[]" value="{{ $box->id }}" @checked($selectedManualBoxIds->contains($box->id)) class="rounded border-stone-300 text-[#3155D9] focus:ring-[#3155D9]">
                                <span class="break-words">{{ $box->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('learning-terms.index') }}" class="inline-flex min-h-11 items-center justify-center text-sm font-semibold text-stone-500 underline underline-offset-4">＋ カテゴリを作る</a>
                    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-md border border-stone-300 px-5 text-sm font-semibold text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:border-stone-700">カテゴリへの追加を保存</button>
                </div>
            </form>
        </section>
    </main>
</x-app-layout>
