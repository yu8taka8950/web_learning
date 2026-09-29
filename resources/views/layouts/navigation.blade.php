@php
    $navigationItems = [
        ['label' => 'ホーム', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'home'],
        ['label' => 'Web学習リスト', 'href' => route('captures.index'), 'active' => request()->routeIs('captures.*'), 'icon' => 'capture'],
        ['label' => 'コレクション', 'href' => route('collections.index'), 'active' => request()->routeIs('collections.*'), 'icon' => 'collection'],
        ['label' => 'マイ用語', 'href' => route('learning-terms.index'), 'active' => request()->routeIs('learning-terms.*'), 'icon' => 'book'],
        ['label' => '削除済み', 'href' => route('collections.deleted'), 'active' => request()->routeIs('collections.deleted', 'collections.restore'), 'icon' => 'trash'],
        ['label' => '今日の復習', 'href' => route('reviews.today'), 'active' => request()->routeIs('reviews.*'), 'icon' => 'refresh'],
        ['label' => 'スクリーンショットから学ぶ', 'href' => route('screenshot-learning.create'), 'active' => request()->routeIs('screenshot-learning.*'), 'icon' => 'image'],
        ['label' => '学習モード', 'href' => route('study-mode.index'), 'active' => request()->routeIs('study-mode.*'), 'icon' => 'study'],
        ['label' => '学習状況', 'href' => route('learning-stats.index'), 'active' => request()->routeIs('learning-stats.*'), 'icon' => 'chart'],
        ['label' => 'プラン', 'href' => route('pricing'), 'active' => request()->routeIs('pricing', 'billing.*'), 'icon' => 'credit-card'],
        ['label' => '使い方', 'href' => route('onboarding.show'), 'active' => request()->routeIs('onboarding.*'), 'icon' => 'guide'],
    ];
@endphp

<nav x-data="{ open: false }">
    <div class="sticky top-0 z-40 flex h-14 items-center justify-between border-b border-stone-200 bg-[#F7F5F0] px-4 dark:border-stone-800 dark:bg-[#171717] lg:hidden">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            <span class="whitespace-nowrap text-base font-semibold tracking-tight"><span class="text-[#3155D9]">web</span><span class="text-[#171717] dark:text-white"> learning</span><span class="text-[#3155D9]">.</span></span>
        </a>
        <button type="button" @click="open = ! open" :aria-expanded="open" aria-controls="mobile-navigation" class="grid size-10 place-items-center rounded-lg border border-stone-300 text-stone-600 transition hover:bg-white focus:outline-none focus:ring-2 focus:ring-[#3155D9] dark:border-stone-700 dark:text-stone-300 dark:hover:bg-stone-900">
            <span class="sr-only">メニューを開閉</span>
            <i x-show="! open" data-lucide="menu" class="size-5" aria-hidden="true"></i>
            <i x-cloak x-show="open" data-lucide="x" class="size-5" aria-hidden="true"></i>
        </button>
    </div>

    <div id="mobile-navigation" x-cloak x-show="open" x-transition class="fixed inset-x-0 top-14 z-40 max-h-[calc(100vh-3.5rem)] overflow-y-auto border-b border-stone-200 bg-[#F7F5F0] p-4 shadow-lg dark:border-stone-800 dark:bg-[#171717] lg:hidden">
        <div class="flex flex-col gap-1">
            @foreach ($navigationItems as $item)
                <a href="{{ $item['href'] }}" @class(['flex min-h-11 items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-[#3155D9]', 'bg-blue-50 text-[#3155D9] dark:bg-blue-950/40 dark:text-blue-300' => $item['active'], 'text-stone-600 hover:bg-white hover:text-stone-950 dark:text-stone-400 dark:hover:bg-stone-900 dark:hover:text-white' => ! $item['active']])>
                    @include('layouts.partials.navigation-icon', ['icon' => $item['icon']])
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>
        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-800">
            <div class="flex items-center gap-3 px-3 pb-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-indigo-100 text-sm font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
                <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ Auth::user()->name }}</p><p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</p></div>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ route('profile.edit') }}" class="flex min-h-11 items-center justify-center rounded-xl border border-gray-200 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">プロフィール</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="min-h-11 w-full rounded-xl border border-gray-200 text-sm font-medium text-gray-700 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">ログアウト</button></form>
            </div>
            <div class="mt-3 flex items-center justify-center gap-4 px-3 text-xs text-stone-500 dark:text-stone-400">
                <a href="{{ route('terms') }}" class="underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">利用規約</a>
                <a href="{{ route('privacy') }}" class="underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">プライバシーポリシー</a>
                <a href="{{ route('tokushoho') }}" class="underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">特定商取引法に基づく表記</a>
            </div>
        </div>
    </div>

    <aside class="fixed inset-y-0 start-0 z-40 hidden w-56 flex-col border-e border-stone-200 bg-[#F7F5F0] px-3 py-5 dark:border-stone-800 dark:bg-[#171717] lg:flex">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-xl px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-500">
            <span class="whitespace-nowrap text-lg font-semibold tracking-tight"><span class="text-[#3155D9]">web</span><span class="text-[#171717] dark:text-white"> learning</span><span class="text-[#3155D9]">.</span></span>
        </a>

        <div class="mt-8 flex flex-1 flex-col gap-1">
            @foreach ($navigationItems as $item)
                <a href="{{ $item['href'] }}" @class(['group flex min-h-10 items-center gap-3 rounded-lg px-3 py-2 text-[13px] font-medium transition focus:outline-none focus:ring-2 focus:ring-[#3155D9]', 'bg-blue-50 text-[#3155D9] dark:bg-blue-950/40 dark:text-blue-300' => $item['active'], 'text-stone-500 hover:bg-white hover:text-stone-950 dark:text-stone-400 dark:hover:bg-stone-900 dark:hover:text-white' => ! $item['active']])>
                    @include('layouts.partials.navigation-icon', ['icon' => $item['icon']])
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        <div class="border-t border-gray-200 pt-4 dark:border-gray-800">
            <div class="flex items-center gap-3 px-2 py-2">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-stone-200 text-xs font-bold text-stone-700 dark:bg-stone-800 dark:text-stone-300">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
                <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ Auth::user()->name }}</p><p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</p></div>
            </div>
            <div class="mt-2 grid grid-cols-2 gap-1">
                <a href="{{ route('profile.edit') }}" class="rounded-lg px-2 py-2 text-center text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">プロフィール</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="w-full rounded-lg px-2 py-2 text-xs font-medium text-gray-500 transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white">ログアウト</button></form>
            </div>
            <div class="mt-3 flex items-center justify-center gap-4 text-xs text-stone-500 dark:text-stone-400">
                <a href="{{ route('terms') }}" class="underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">利用規約</a>
                <a href="{{ route('privacy') }}" class="underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">プライバシーポリシー</a>
                <a href="{{ route('tokushoho') }}" class="underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">特定商取引法に基づく表記</a>
            </div>
        </div>
    </aside>
</nav>
