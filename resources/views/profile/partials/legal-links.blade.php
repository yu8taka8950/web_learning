<nav aria-label="法務情報">
    <ul class="divide-y divide-stone-200 border-y border-stone-200 dark:divide-stone-800 dark:border-stone-800">
        @foreach ([
            ['route' => 'terms', 'title' => '利用規約', 'description' => 'Web Learningの利用条件を確認できます。'],
            ['route' => 'privacy', 'title' => 'プライバシーポリシー', 'description' => '利用者情報の取り扱いについて確認できます。'],
            ['route' => 'tokushoho', 'title' => '特定商取引法に基づく表記', 'description' => '料金・解約・事業者情報などを確認できます。'],
        ] as $link)
            <li>
                <a href="{{ route($link['route']) }}" class="group flex items-center justify-between gap-5 py-4 text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#3155D9]">
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-stone-900 group-hover:text-[#3155D9] dark:text-stone-100 dark:group-hover:text-blue-300">{{ $link['title'] }}</span>
                        <span class="mt-1 block text-sm leading-6 text-stone-500 dark:text-stone-400">{{ $link['description'] }}</span>
                    </span>
                    <span class="shrink-0 text-lg text-stone-400 transition group-hover:translate-x-0.5 group-hover:text-[#3155D9]" aria-hidden="true">→</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
