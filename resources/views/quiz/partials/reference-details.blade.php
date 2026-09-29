<details class="group py-4">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-sm font-semibold text-[#171717] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-100">
        <span><span class="inline-block w-5 text-[#3155D9] group-open:hidden" aria-hidden="true">＋</span><span class="hidden w-5 text-[#3155D9] group-open:inline-block" aria-hidden="true">−</span>参照サイトを見る</span>
        <span class="text-xs font-normal text-stone-500">学習元</span>
    </summary>

    <div class="flex flex-col gap-4 pt-4">
        @php
            $safeReferences = collect($references)->filter(
                fn (array $reference): bool => is_string($reference['url'] ?? null)
                    && str($reference['url'])->startsWith(['https://', 'http://']),
            );
        @endphp

        @forelse ($safeReferences as $reference)
            <div class="border-l border-stone-300 pl-4 dark:border-stone-700">
                <p class="text-xs font-semibold text-stone-500 dark:text-stone-400">参照元</p>
                <p class="mt-1 break-words text-sm font-medium text-stone-800 dark:text-stone-200">{{ $reference['label'] }}</p>
                <a href="{{ $reference['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-10 items-center rounded-sm text-sm font-semibold text-[#3155D9] underline decoration-blue-200 underline-offset-4 hover:decoration-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">
                    記事を開く <span aria-hidden="true">↗</span><span class="sr-only">（新しいタブで開きます）</span>
                </a>
            </div>
        @empty
            <p class="text-sm leading-7 text-stone-600 dark:text-stone-300">この問題には参照URLが登録されていません。</p>
        @endforelse

        <p class="border-t border-stone-200 pt-4 text-xs leading-6 text-stone-600 dark:border-stone-800 dark:text-stone-400">
            この問題・解説はAIによって生成されています。内容に誤りが含まれる場合があります。正確な情報については、公式ドキュメントや信頼できる資料もあわせて確認してください。
        </p>
    </div>
</details>
