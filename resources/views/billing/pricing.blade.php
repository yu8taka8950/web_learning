<x-app-layout>
    @php
        $isPlus = $webUsage['plan'] === 'plus';
        $webUsagePercent = min(100, $webUsage['used'] / $webUsage['limit'] * 100);
        $screenshotUsagePercent = min(100, $screenshotUsage['used'] / $screenshotUsage['limit'] * 100);
    @endphp

    <div class="relative overflow-hidden" x-data="{ currency: 'jpy' }">
        <div class="pointer-events-none absolute inset-x-0 top-0 h-72 bg-gradient-to-b from-white via-stone-50/80 to-transparent dark:from-stone-950/20"></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-10 px-4 py-10 sm:px-6 sm:py-14 lg:px-10">
            <header class="flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
                <div class="max-w-2xl">
                    <p class="text-xs font-semibold tracking-[0.2em] text-[#3155D9]">PLAN</p>
                    <h1 class="mt-3 font-editorial text-4xl font-semibold tracking-tight text-[#171717] dark:text-white sm:text-5xl">料金プラン</h1>
                    <p class="mt-5 max-w-xl text-sm leading-7 text-stone-600 dark:text-stone-300 sm:text-base">
                        無料ではじめて、必要になったらPlusへ。<br class="hidden sm:block">
                        Web解析とスクリーンショット解析を、あなたの学習量に合わせて選べます。
                    </p>
                </div>

                <div class="inline-flex w-fit items-center gap-2 rounded-full border border-stone-200 bg-white/80 px-3.5 py-2 text-xs font-medium text-stone-600 shadow-sm dark:border-stone-700 dark:bg-stone-900/80 dark:text-stone-300">
                    <span class="h-2 w-2 rounded-full {{ $isPlus ? 'bg-[#3155D9]' : 'bg-stone-400' }}"></span>
                    現在のプラン: <span class="font-semibold text-stone-900 dark:text-white">{{ $isPlus ? 'Plus' : 'Free' }}</span>
                </div>
            </header>

            @if (session('status'))
                <p class="rounded-xl border border-stone-200 bg-white px-4 py-3 text-sm text-stone-600 shadow-sm dark:border-stone-700 dark:bg-stone-900 dark:text-stone-300">{{ session('status') }}</p>
            @endif

            <div class="grid items-stretch gap-6 lg:grid-cols-2">
                <section class="flex flex-col rounded-2xl border border-stone-200 bg-white p-7 shadow-[0_8px_30px_rgba(28,25,23,0.04)] dark:border-stone-700 dark:bg-stone-900 sm:p-8">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-400">はじめての方へ</p>
                            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Free</h2>
                        </div>
                        @if (! $isPlus)
                            <span class="rounded-full bg-stone-100 px-3 py-1.5 text-xs font-semibold text-stone-600 dark:bg-stone-800 dark:text-stone-300">現在のプラン</span>
                        @endif
                        <p class="mt-4 text-center text-xs leading-5 text-stone-500 dark:text-stone-400">月額サブスクリプションは自動更新されます。いつでも解約手続が可能です。解約後も現在の契約期間終了まではPlusをご利用いただけます。</p>
                    </div>
                    <p class="mt-5 text-sm leading-6 text-stone-500 dark:text-stone-400">まずはWeb Learningの基本機能を気軽に試せます。</p>
                    <p class="mt-7 text-4xl font-semibold tracking-tight text-stone-900 dark:text-white">¥0 <span class="text-sm font-medium text-stone-400">/ 月</span></p>

                    <div class="mt-8 border-t border-stone-100 pt-6 dark:border-stone-800">
                        <p class="text-xs font-semibold tracking-wide text-stone-400">含まれる機能</p>
                        <ul class="mt-4 flex flex-col gap-4 text-sm text-stone-700 dark:text-stone-300">
                            <li class="flex items-start gap-3"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-stone-100 text-xs text-stone-500 dark:bg-stone-800">✓</span><span>Web解析 <strong class="font-semibold text-stone-900 dark:text-white">月100回</strong></span></li>
                            <li class="flex items-start gap-3"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-stone-100 text-xs text-stone-500 dark:bg-stone-800">✓</span><span>スクリーンショット解析 <strong class="font-semibold text-stone-900 dark:text-white">月100回</strong></span></li>
                        </ul>
                    </div>
                    <div class="mt-auto pt-8">
                        <div class="rounded-xl bg-stone-50 px-4 py-3 text-xs leading-5 text-stone-500 dark:bg-stone-800/70 dark:text-stone-400">学習を始めるために必要な機能を、無料で使えます。</div>
                    </div>
                </section>

                <section class="relative flex flex-col overflow-hidden rounded-2xl border border-[#3155D9]/40 bg-gradient-to-br from-blue-50 via-white to-white p-7 shadow-[0_12px_36px_rgba(49,85,217,0.12)] dark:border-blue-400/40 dark:from-blue-950/50 dark:via-stone-900 dark:to-stone-900 sm:p-8">
                    <div class="absolute right-0 top-0 h-32 w-32 translate-x-8 -translate-y-8 rounded-full bg-blue-200/30 blur-2xl dark:bg-blue-500/10"></div>
                    <div class="relative flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#3155D9]">もっと学びたい方へ</p>
                            <h2 class="mt-3 text-2xl font-semibold tracking-tight text-stone-900 dark:text-white">Plus</h2>
                        </div>
                        <span class="rounded-full bg-[#3155D9] px-3 py-1.5 text-xs font-semibold text-white">おすすめ</span>
                    </div>
                    <p class="relative mt-5 text-sm leading-6 text-stone-600 dark:text-stone-300">解析の回数を気にせず、学習のペースを広げられます。</p>

                    <div class="relative mt-6 flex flex-wrap items-center justify-between gap-4">
                        <p class="text-4xl font-semibold tracking-tight text-stone-900 dark:text-white"><span x-text="currency === 'jpy' ? '¥300 / 月' : '$1.99 / month'">¥300 / 月</span></p>
                        <div class="inline-flex rounded-full border border-blue-200 bg-white/80 p-1 shadow-sm dark:border-blue-900 dark:bg-stone-900/70">
                            <button type="button" @click="currency = 'jpy'" :class="currency === 'jpy' ? 'bg-[#3155D9] text-white shadow-sm' : 'text-stone-500 hover:text-stone-800 dark:text-stone-400 dark:hover:text-stone-200'" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition" aria-label="日本円で表示">JPY ¥</button>
                            <button type="button" @click="currency = 'usd'" :class="currency === 'usd' ? 'bg-[#3155D9] text-white shadow-sm' : 'text-stone-500 hover:text-stone-800 dark:text-stone-400 dark:hover:text-stone-200'" class="rounded-full px-3.5 py-1.5 text-xs font-semibold transition" aria-label="米ドルで表示">USD $</button>
                        </div>
                    </div>

                    <div class="relative mt-8 border-t border-blue-100 pt-6 dark:border-blue-900/60">
                        <p class="text-xs font-semibold tracking-wide text-blue-700/70 dark:text-blue-300/80">Plusでできること</p>
                        <ul class="mt-4 flex flex-col gap-4 text-sm text-stone-700 dark:text-stone-300">
                            <li class="flex items-start gap-3"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs text-[#3155D9] dark:bg-blue-900/60 dark:text-blue-300">✓</span><span>Web解析 <strong class="font-semibold text-stone-900 dark:text-white">月1,000回</strong></span></li>
                            <li class="flex items-start gap-3"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-blue-100 text-xs text-[#3155D9] dark:bg-blue-900/60 dark:text-blue-300">✓</span><span>スクリーンショット解析 <strong class="font-semibold text-stone-900 dark:text-white">月1,000回</strong></span></li>
                        </ul>
                        <div class="mt-5 flex flex-wrap gap-2 text-xs font-medium text-[#3155D9] dark:text-blue-300">
                            <span class="rounded-full bg-blue-100/70 px-3 py-1.5 dark:bg-blue-900/50">たっぷり使える</span>
                            <span class="rounded-full bg-blue-100/70 px-3 py-1.5 dark:bg-blue-900/50">解析ごとにカウント</span>
                            <span class="rounded-full bg-blue-100/70 px-3 py-1.5 dark:bg-blue-900/50">学習量が増えても安心</span>
                        </div>
                    </div>

                    <div class="relative mt-auto pt-8">
                        @if ($isPlus)
                            <form method="POST" action="{{ route('billing.portal') }}">
                                @csrf
                                <button class="inline-flex w-full items-center justify-center rounded-xl bg-[#3155D9] px-5 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2848c1] focus:outline-none focus:ring-2 focus:ring-[#3155D9] focus:ring-offset-2">プランを管理</button>
                            </form>
                        @else
                            <form method="GET" action="{{ route('billing.confirm') }}">
                                <input type="hidden" name="currency" :value="currency">
                                <button class="inline-flex w-full items-center justify-center rounded-xl bg-[#3155D9] px-5 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#2848c1] focus:outline-none focus:ring-2 focus:ring-[#3155D9] focus:ring-offset-2">申込み内容を確認する <span class="ml-2" aria-hidden="true">→</span></button>
                            </form>
                            <p class="mt-3 text-center text-xs leading-5 text-stone-500 dark:text-stone-400">月額サブスクリプション・自動更新です。申込み前に契約内容を確認できます。</p>
                        @endif
                    </div>
                </section>
            </div>

            <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-[0_8px_30px_rgba(28,25,23,0.04)] dark:border-stone-700 dark:bg-stone-900 sm:p-8">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] text-[#3155D9]">MONTHLY USAGE</p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight text-stone-900 dark:text-white">今月の利用状況</h2>
                    </div>
                    <p class="text-xs text-stone-400">毎月1日にリセット</p>
                </div>
                <div class="mt-7 grid gap-6 md:grid-cols-2">
                    @foreach ([['label' => 'Web解析', 'usage' => $webUsage, 'percent' => $webUsagePercent], ['label' => 'スクリーンショット解析', 'usage' => $screenshotUsage, 'percent' => $screenshotUsagePercent]] as $item)
                        @php($usagePercent = $item['percent'])
                        <div class="rounded-xl border border-stone-100 bg-stone-50/70 p-5 dark:border-stone-800 dark:bg-stone-800/50">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-stone-700 dark:text-stone-300">{{ $item['label'] }}</span>
                                <span class="font-semibold tabular-nums text-stone-900 dark:text-white">{{ number_format($item['usage']['used']) }} / {{ number_format($item['usage']['limit']) }}</span>
                            </div>
                            <div class="mt-4 h-2.5 overflow-hidden rounded-full bg-stone-200 dark:bg-stone-700" role="progressbar" aria-label="{{ $item['label'] }}の利用状況" aria-valuenow="{{ $item['usage']['used'] }}" aria-valuemin="0" aria-valuemax="{{ $item['usage']['limit'] }}">
                                <div @class(['h-full rounded-full transition-all', 'bg-[#3155D9]' => $usagePercent < 80, 'bg-amber-500' => $usagePercent >= 80 && $usagePercent < 90, 'bg-red-500' => $usagePercent >= 90]) style="width: {{ $usagePercent }}%"></div>
                            </div>
                            @if ($item['usage']['warning'])
                                <p class="mt-3 text-xs text-stone-500 dark:text-stone-400">今月の{{ $item['label'] }}はあと{{ number_format($item['usage']['remaining']) }}回です。</p>
                            @else
                                <p class="mt-3 text-xs text-stone-400 dark:text-stone-500">利用上限 {{ number_format($item['usage']['limit']) }}回 / 月</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>

            <p class="text-center text-xs leading-6 text-stone-400 dark:text-stone-500">Freeでもすぐに使い始められます。Plusはいつでも開始できます。表示回数は月ごとの上限です。</p>
            <p class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-center text-xs"><a href="{{ route('terms') }}" class="text-stone-500 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-400">利用規約</a><a href="{{ route('privacy') }}" class="text-stone-500 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-400">プライバシーポリシー</a><a href="{{ route('tokushoho') }}" class="text-stone-500 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-400">特定商取引法に基づく表記</a></p>
        </div>
    </div>
</x-app-layout>
