<x-app-layout>
    @php
        $isJpy = $currency === 'jpy';
        $price = $isJpy ? '月額300円' : '月額1.99米ドル';
        $buttonLabel = $isJpy ? '月額300円でPlusに申し込む' : '月額$1.99でPlusに申し込む';
    @endphp

    <main class="mx-auto max-w-4xl px-5 py-12 sm:px-8 sm:py-16">
        <div class="mx-auto max-w-3xl">
            <a href="{{ route('pricing') }}" class="inline-flex text-sm font-medium text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4 transition hover:text-[#2848bd] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">← 料金プランに戻る</a>

            @if ($isPlus)
                <section class="mt-8 border-y border-stone-300 py-10 dark:border-stone-700">
                    <p class="text-xs font-semibold tracking-[0.18em] text-[#3155D9]">PLUS PLAN</p>
                    <h1 class="mt-4 font-editorial text-3xl font-semibold tracking-tight text-[#171717] dark:text-white sm:text-4xl">現在Plusをご利用中です</h1>
                    <p class="mt-5 text-sm leading-7 text-stone-600 dark:text-stone-300 sm:text-base">重複した申込みは作成されません。契約内容の確認・解約はStripe Customer Portalから行えます。</p>
                    <form method="POST" action="{{ route('billing.portal') }}" class="mt-8">@csrf<button class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#3155D9] px-5 py-3 text-sm font-semibold text-white transition hover:bg-[#2848c1] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus:ring-offset-2">Customer Portalを開く</button></form>
                </section>
            @else
                <header>
                    <p class="text-xs font-semibold tracking-[0.18em] text-[#3155D9]">PLUS PLAN</p>
                    <h1 class="mt-4 font-editorial text-3xl font-semibold tracking-tight text-[#171717] dark:text-white sm:text-4xl">Plusのお申し込み内容を確認</h1>
                    <p class="mt-5 text-sm leading-7 text-stone-600 dark:text-stone-300 sm:text-base">下記の契約条件を確認後、Stripeの決済画面へ進みます。</p>
                </header>

                <section class="mt-10 border-y border-stone-300 py-8 dark:border-stone-700" aria-labelledby="plan-heading">
                    <h2 id="plan-heading" class="text-xl font-semibold text-[#171717] dark:text-white">Web Learning Plus</h2>
                    <p class="mt-3 text-3xl font-semibold tracking-tight text-[#171717] dark:text-white">{{ $price }}</p>
                    <ul class="mt-7 grid gap-3 text-sm leading-6 text-stone-700 dark:text-stone-300 sm:grid-cols-2"><li>月額サブスクリプション</li><li>解約されるまで自動更新</li><li>Web解析：月1,000回</li><li>スクリーンショット解析：月1,000回</li><li class="sm:col-span-2">Web解析とスクリーンショット解析は別カウント</li></ul>
                </section>

                <div class="mt-10 space-y-8 text-sm leading-7 text-stone-700 dark:text-stone-300 sm:text-base">
                    <section><h2 class="text-xl font-semibold text-[#171717] dark:text-white">支払条件</h2><dl class="mt-4 space-y-3"><div><dt class="font-semibold text-[#171717] dark:text-white">支払方法</dt><dd>Stripeによるオンライン決済</dd></div><div><dt class="font-semibold text-[#171717] dark:text-white">初回請求</dt><dd>申込み時</dd></div><div><dt class="font-semibold text-[#171717] dark:text-white">2回目以降</dt><dd>毎月のStripe請求サイクルに従って自動請求</dd></div></dl></section>
                    <section><h2 class="text-xl font-semibold text-[#171717] dark:text-white">サービス提供時期</h2><p class="mt-3">決済完了後、Plus契約が確認され次第利用可能です。</p></section>
                    <section><h2 class="text-xl font-semibold text-[#171717] dark:text-white">契約期間・自動更新</h2><p class="mt-3">契約期間は1か月単位です。利用者が解約手続を行わない限り、毎月自動更新されます。</p></section>
                    <section><h2 class="text-xl font-semibold text-[#171717] dark:text-white">解約</h2><p class="mt-3">いつでもWeb LearningからStripe Customer Portalへ移動して解約手続を行えます。解約後も現在の契約期間終了まではPlusを利用でき、その後Freeへ移行します。</p></section>
                </div>

                <form method="POST" action="{{ route('billing.checkout') }}" class="mt-12 border-t border-stone-300 pt-8 dark:border-stone-700" x-data="{ recurring: false, terms: false }">
                    @csrf
                    <input type="hidden" name="currency" value="{{ $currency }}">
                    {{-- TODO: 公開前に返金条件を表示 --}}
                    <fieldset class="space-y-4"><legend class="text-sm font-semibold text-[#171717] dark:text-white">申込み前の確認</legend><label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-stone-700 dark:text-stone-300"><input type="checkbox" name="accepted_recurring" value="1" x-model="recurring" class="mt-1 rounded border-stone-400 text-[#3155D9] focus:ring-[#3155D9]" aria-describedby="recurring-error"><span>月額料金・自動更新・解約条件を確認しました</span></label>@error('accepted_recurring')<p id="recurring-error" class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror<label class="flex cursor-pointer items-start gap-3 text-sm leading-6 text-stone-700 dark:text-stone-300"><input type="checkbox" name="accepted_terms" value="1" x-model="terms" class="mt-1 rounded border-stone-400 text-[#3155D9] focus:ring-[#3155D9]" aria-describedby="terms-error"><span><a href="{{ route('terms') }}" class="text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4">利用規約</a>・<a href="{{ route('privacy') }}" class="text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4">プライバシーポリシー</a>・<a href="{{ route('tokushoho') }}" class="text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4">特定商取引法に基づく表記</a>を確認しました</span></label>@error('accepted_terms')<p id="terms-error" class="text-sm text-red-600" role="alert">{{ $message }}</p>@enderror</fieldset>
                    <button type="submit" :disabled="!recurring || !terms" class="mt-8 inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#3155D9] px-5 py-3.5 text-sm font-semibold text-white transition hover:bg-[#2848c1] focus:outline-none focus:ring-2 focus:ring-[#3155D9] focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">{{ $buttonLabel }}</button>
                    <p class="mt-3 text-center text-xs text-stone-500 dark:text-stone-400">Stripeの安全な決済画面へ移動します。</p>
                </form>
            @endif
        </div>
    </main>
</x-app-layout>
