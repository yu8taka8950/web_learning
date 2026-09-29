<x-app-layout>
    <x-slot name="header"><div><h1 class="text-2xl font-semibold text-[#171717] dark:text-white">Web学習リスト</h1><p class="mt-3 text-sm text-stone-600 dark:text-stone-300">Webを読んでいる間に見つかった学習候補を、あとから確認・問題作成できます。</p></div></x-slot>
    <div class="mx-auto max-w-4xl px-4 py-10 sm:px-6">
        @if ($captures->isEmpty())
            <div class="border-y border-stone-300 py-12 text-center"><p class="text-lg font-semibold text-[#171717] dark:text-white">Web学習リストはまだありません。</p><p class="mt-3 text-sm text-stone-600 dark:text-stone-300">Chrome拡張機能のLearning ModeをONにして、いつも通りWebを読んでみましょう。</p></div>
        @else
            <div class="border-t border-stone-300">
                @foreach ($captures as $capture)
                    <article class="border-b border-stone-300 py-8"><div class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between"><div class="min-w-0"><h2 class="text-lg font-semibold leading-7 text-[#171717] dark:text-white">{{ $capture->page_title }}</h2><p class="mt-2 text-sm text-stone-500">{{ $capture->source_host }} ・ {{ $capture->captured_at->format('n月j日 H:i') }}</p><p class="mt-5 text-sm font-semibold text-[#178C78]">{{ $capture->terms_count }}件の学習候補 @if($capture->status === 'quiz_generated')・問題を作成済み@endif</p><div class="mt-3 flex flex-wrap gap-x-4 gap-y-2 text-sm text-stone-600 dark:text-stone-300">@foreach($capture->terms as $term)<span>{{ $term->term }}</span>@endforeach @if($capture->terms_count > $capture->terms->count())<span class="text-stone-400">+{{ $capture->terms_count - $capture->terms->count() }}件</span>@endif</div></div><div class="flex shrink-0 flex-col items-start gap-2 sm:items-end"><a href="{{ route('captures.show', $capture) }}" class="text-sm font-semibold text-[#3155D9]">候補を見る →</a><a href="{{ $capture->source_url }}" target="_blank" rel="noopener noreferrer" class="text-xs text-stone-500 underline underline-offset-4">元ページを開く ↗</a></div></div></article>
                @endforeach
            </div>
            <div class="mt-6">{{ $captures->links() }}</div>
        @endif
    </div>
</x-app-layout>
