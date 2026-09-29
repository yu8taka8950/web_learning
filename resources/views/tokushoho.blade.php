<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>特定商取引法に基づく表記 | Web Learning</title>
        @include('layouts.partials.pwa-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#F7F5F0] text-[#171717] antialiased dark:bg-[#171717] dark:text-stone-100">
        <header class="border-b border-stone-200 dark:border-stone-800">
            <div class="mx-auto flex max-w-4xl items-center justify-between gap-5 px-5 py-5 sm:px-8">
                <a href="{{ url('/') }}" class="text-lg font-semibold tracking-tight focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]"><span class="text-[#3155D9]">web</span><span> learning</span><span class="text-[#3155D9]">.</span></a>
                @guest
                    <a href="{{ route('login') }}" class="text-sm font-medium text-stone-600 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-300">ログイン</a>
                @else
                    <a href="{{ route('dashboard') }}" class="text-sm font-medium text-stone-600 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-300">Dashboardへ</a>
                @endguest
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-5 py-14 sm:px-8 sm:py-20">
            <article class="mx-auto max-w-3xl">
                {{-- TODO: 公開前に制定日・最終改定日を設定する。 --}}
                {{-- TODO: 公開前に正式な販売事業者名を設定 --}}
                {{-- TODO: 公開前に住所表示方針を確定 --}}
                {{-- TODO: 公開前に電話番号表示方針を確定 --}}
                {{-- TODO: 公開前に問い合わせメールを設定 --}}

                <p class="text-xs font-semibold tracking-[0.18em] text-[#3155D9]">SPECIFIED COMMERCIAL TRANSACTIONS ACT</p>
                <h1 class="mt-4 font-editorial text-3xl font-semibold tracking-tight sm:text-4xl">特定商取引法に基づく表記</h1>
                <p class="mt-5 max-w-2xl text-sm leading-7 text-stone-600 dark:text-stone-300 sm:text-base">Web Learningの有料プランに関する販売条件を記載しています。</p>

                <nav class="mt-12 border-y border-stone-300 py-6 dark:border-stone-700" aria-label="特定商取引法に基づく表記目次">
                    <h2 class="text-sm font-semibold text-[#171717] dark:text-white">目次</h2>
                    <ol class="mt-4 grid gap-x-8 gap-y-3 text-sm leading-6 sm:grid-cols-2">
                        @foreach ([['service', 'サービス名'], ['price', '販売価格 / 役務の対価'], ['additional-fees', '商品代金以外に必要な料金'], ['payment', '支払方法・支払時期'], ['delivery', 'サービス提供時期'], ['term', '契約期間・自動更新'], ['cancellation', '解約方法・解約の効力'], ['limits', '利用上限'], ['returns', '返品・返金'], ['environment', '動作環境']] as [$id, $label])
                            <li><a href="#{{ $id }}" class="text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4 transition hover:text-[#2848bd] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">{{ $label }}</a></li>
                        @endforeach
                    </ol>
                </nav>

                <div class="mt-14 space-y-14 text-sm leading-7 text-stone-700 dark:text-stone-300 sm:text-base sm:leading-8">
                    <section id="service" aria-labelledby="service-heading"><h2 id="service-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">サービス名</h2><p class="mt-5">Web Learning</p></section>

                    <section id="price" aria-labelledby="price-heading"><h2 id="price-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">販売価格 / 役務の対価</h2><dl class="mt-5 space-y-5"><div><dt class="font-semibold text-[#171717] dark:text-white">Free</dt><dd class="mt-1">0円</dd></div><div><dt class="font-semibold text-[#171717] dark:text-white">Plus</dt><dd class="mt-1">日本円：月額300円</dd><dd>米ドル：月額1.99米ドル</dd></div></dl><p class="mt-5">Plusは月額サブスクリプションであり、利用者が解約手続を行わない限り自動更新されます。</p></section>

                    <section id="additional-fees" aria-labelledby="additional-fees-heading"><h2 id="additional-fees-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">商品代金以外に必要な料金</h2><p class="mt-5">本サービスの利用に必要なインターネット接続料金、通信料金等は利用者の負担となります。</p><p class="mt-4">本サービスは、販売価格に加えて独自の追加手数料を設定していません。</p></section>

                    <section id="payment" aria-labelledby="payment-heading"><h2 id="payment-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">支払方法・支払時期</h2><dl class="mt-5 space-y-5"><div><dt class="font-semibold text-[#171717] dark:text-white">支払方法</dt><dd class="mt-1">Stripeを利用したオンライン決済</dd></div><div><dt class="font-semibold text-[#171717] dark:text-white">初回請求</dt><dd class="mt-1">Plusの申込み時に決済されます。</dd></div><div><dt class="font-semibold text-[#171717] dark:text-white">2回目以降</dt><dd class="mt-1">初回の請求を基準としたStripeの月次請求サイクルに従い、毎月請求されます。</dd></div></dl></section>

                    <section id="delivery" aria-labelledby="delivery-heading"><h2 id="delivery-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">サービス提供時期</h2><p class="mt-5">Stripeによる決済手続が正常に完了し、本サービス側でPlus契約が確認された後、Plus機能を利用できます。</p></section>

                    <section id="term" aria-labelledby="term-heading"><h2 id="term-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">契約期間・自動更新</h2><p class="mt-5">Plusは1か月単位の月額サブスクリプションです。利用者が解約手続を行わない限り、毎月自動更新されます。</p></section>

                    <section id="cancellation" aria-labelledby="cancellation-heading"><h2 id="cancellation-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">解約方法・解約の効力</h2><p class="mt-5">Web LearningからStripe Customer Portalへ移動し、Plusを解約できます。</p><p class="mt-4">解約手続後も、現在の支払済み契約期間終了まではPlusを利用でき、契約期間終了後にFreeへ移行します。</p></section>

                    <section id="limits" aria-labelledby="limits-heading"><h2 id="limits-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">利用上限</h2><div class="mt-5 grid gap-6 sm:grid-cols-2"><div><h3 class="font-semibold text-[#171717] dark:text-white">Free</h3><ul class="mt-2 list-disc space-y-1 pl-6"><li>Web解析：月100回</li><li>スクリーンショット解析：月100回</li></ul></div><div><h3 class="font-semibold text-[#171717] dark:text-white">Plus</h3><ul class="mt-2 list-disc space-y-1 pl-6"><li>Web解析：月1,000回</li><li>スクリーンショット解析：月1,000回</li></ul></div></div><p class="mt-5">Web解析とスクリーンショット解析は別々にカウントされ、利用枠は毎月1日に更新されます。Quiz生成は、これらの解析上限を追加で消費しません。</p></section>

                    <section id="returns" aria-labelledby="returns-heading"><h2 id="returns-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">返品・返金</h2><p class="mt-5">本サービスはデジタルサービスであるため、物品の返品には該当しません。</p>{{-- TODO: 公開前に返金ポリシーを確定 --}}</section>

                    <section id="environment" aria-labelledby="environment-heading"><h2 id="environment-heading" class="text-2xl font-semibold tracking-tight text-[#171717] dark:text-white">動作環境</h2><p class="mt-5">本サービスの利用には、JavaScriptが利用可能なWebブラウザおよびインターネット接続が必要です。Chrome拡張機能を利用する場合は、Chromeブラウザが必要です。</p></section>
                </div>
            </article>
        </main>

        <footer class="border-t border-stone-200 px-5 py-8 dark:border-stone-800 sm:px-8"><nav class="mx-auto flex max-w-3xl flex-wrap justify-center gap-x-5 gap-y-2 text-xs" aria-label="法務ページ"><a href="{{ route('terms') }}" class="text-stone-500 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-400">利用規約</a><a href="{{ route('privacy') }}" class="text-stone-500 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-400">プライバシーポリシー</a><a href="{{ route('tokushoho') }}" class="text-stone-500 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-400">特定商取引法に基づく表記</a></nav></footer>
    </body>
</html>
