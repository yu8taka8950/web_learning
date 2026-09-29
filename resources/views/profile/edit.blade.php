<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">プロフィール</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">アカウント、学習プラン、接続設定を管理できます。</p>
        </div>
    </x-slot>

    <div class="bg-[#F7F5F0] py-10 dark:bg-stone-950 sm:py-14">
        <div class="mx-auto max-w-6xl space-y-6 px-5 sm:px-8 lg:px-10">
            <header class="max-w-3xl">
                <p class="text-xs font-semibold tracking-[0.18em] text-[#3155D9]">ACCOUNT SETTINGS</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-tight text-stone-900 dark:text-white sm:text-4xl">プロフィール</h1>
                <p class="mt-4 text-sm leading-7 text-stone-600 dark:text-stone-300 sm:text-base">登録情報やログイン方法を確認し、Web Learningの利用環境を整えられます。</p>
            </header>

            <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:p-8" aria-labelledby="account-information-heading">
                <div class="max-w-3xl">
                    <h2 id="account-information-heading" class="text-lg font-semibold text-stone-900 dark:text-white">アカウント情報</h2>
                    <p class="mt-2 text-sm leading-6 text-stone-500 dark:text-stone-400">表示名とメールアドレスを更新できます。</p>
                    <div class="mt-6">
                    @include('profile.partials.update-profile-information-form')
                    </div>
                </div>
            </section>

            <div class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:p-8" aria-labelledby="plan-heading">
                    <h2 id="plan-heading" class="text-lg font-semibold text-stone-900 dark:text-white">サブスクリプション</h2>
                    <p class="mt-2 text-sm text-stone-500 dark:text-stone-400">現在のプランと月間の利用状況です。</p>
                    <p class="mt-6 text-xl font-semibold text-stone-900 dark:text-white">{{ $webUsage['plan'] === 'plus' ? 'Plus' : 'Free' }}</p>
                    <div class="mt-6 space-y-5 text-sm text-stone-700 dark:text-stone-200">
                        <div><div class="flex justify-between gap-4"><span>Web解析</span><span>{{ $webUsage['used'] }} / {{ number_format($webUsage['limit']) }}</span></div><div class="mt-2 h-1.5 rounded-full bg-stone-200 dark:bg-stone-700"><div class="h-1.5 rounded-full bg-[#3155D9]" style="width: {{ min(100, $webUsage['used'] / $webUsage['limit'] * 100) }}%"></div></div></div>
                        <div><div class="flex justify-between gap-4"><span>スクリーンショット解析</span><span>{{ $screenshotUsage['used'] }} / {{ number_format($screenshotUsage['limit']) }}</span></div><div class="mt-2 h-1.5 rounded-full bg-stone-200 dark:bg-stone-700"><div class="h-1.5 rounded-full bg-[#3155D9]" style="width: {{ min(100, $screenshotUsage['used'] / $screenshotUsage['limit'] * 100) }}%"></div></div></div>
                    </div>
                    @if ($webUsage['plan'] === 'plus')
                        <form method="POST" action="{{ route('billing.portal') }}" class="mt-7">@csrf <button class="text-sm font-semibold text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">プランを管理 →</button></form>
                    @else
                        <a href="{{ route('pricing') }}" class="mt-7 inline-block text-sm font-semibold text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">Plusを見る →</a>
                    @endif
                </section>

                <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:p-8" aria-labelledby="extension-heading">
                    <h2 id="extension-heading" class="text-lg font-semibold text-stone-900 dark:text-white">Chrome拡張機能の接続</h2>
                    <p class="mt-2 text-sm leading-6 text-stone-500 dark:text-stone-400">ブラウザで見つけた学習候補をWeb Learningへ保存できます。</p>
                    <div class="mt-6 space-y-3 text-sm leading-6 text-stone-600 dark:text-stone-300">
                        <p>PopupでLearning ModeをONにすると、必要な場合だけ安全な接続確認画面が開きます。</p>
                        <p>接続状態やトークンはプロフィールから管理できます。</p>
                    </div>
                </section>

                <section x-data="pwaInstall" class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:p-8" aria-labelledby="pwa-install-heading">
                    <h2 id="pwa-install-heading" class="text-lg font-semibold text-stone-900 dark:text-white">Web Learningをアプリとして使う</h2>
                    <p class="mt-2 text-sm leading-6 text-stone-500 dark:text-stone-400">Web Learningをホーム画面やアプリ一覧からすぐに開けます。</p>

                    <div class="mt-6" x-cloak x-show="isStandalone">
                        <p class="text-sm font-semibold text-[#178C78]">アプリとして利用中</p>
                    </div>

                    <div class="mt-6" x-cloak x-show="canInstall && !isStandalone">
                        <button type="button" class="inline-flex items-center justify-center bg-[#3155D9] px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 dark:focus-visible:ring-offset-stone-900" x-on:click="install">アプリをインストール</button>
                    </div>

                    <p class="mt-6 text-sm leading-6 text-stone-600 dark:text-stone-300" x-cloak x-show="isIos && !isStandalone">iPhone / iPadではSafariの共有ボタンから「ホーム画面に追加」を選択してください。</p>
                    <p class="mt-6 text-sm leading-6 text-stone-600 dark:text-stone-300" x-cloak x-show="!isIos && !isStandalone && !canInstall">このブラウザでインストール可能になると、ここにボタンが表示されます。</p>
                </section>
            </div>

            @if ($user->password !== null)
                <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:p-8" aria-labelledby="password-heading">
                    <div class="max-w-3xl">
                        <h2 id="password-heading" class="text-lg font-semibold text-stone-900 dark:text-white">ログイン情報 / パスワード変更</h2>
                        <p class="mt-2 text-sm leading-6 text-stone-500 dark:text-stone-400">ログインに使うパスワードを変更できます。</p>
                        <div class="mt-6">
                        @include('profile.partials.update-password-form')
                        </div>
                    </div>
                </section>
            @endif

            @if ($user->password !== null)
                <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:p-8" aria-labelledby="delete-heading">
                    <div class="max-w-3xl">
                        <h2 id="delete-heading" class="text-lg font-semibold text-stone-900 dark:text-white">アカウント削除</h2>
                        <p class="mt-2 text-sm leading-6 text-stone-500 dark:text-stone-400">削除前に内容を確認してください。削除操作は取り消せません。</p>
                        <div class="mt-6">
                        @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                </section>
            @else
                <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:p-8" aria-labelledby="delete-heading">
                    <div class="max-w-3xl">
                        <h2 id="delete-heading" class="text-lg font-semibold text-stone-900 dark:text-white">アカウント削除</h2>
                        <p class="mt-2 text-sm leading-6 text-stone-500 dark:text-stone-400">Googleで本人確認したうえで、アカウントと関連データを削除できます。</p>
                        <div class="mt-6">
                        @include('profile.partials.delete-google-user-form')
                        </div>
                    </div>
                </section>
            @endif

            <section class="rounded-2xl border border-stone-200 bg-white p-6 shadow-sm dark:border-stone-800 dark:bg-stone-900 sm:p-8" aria-labelledby="legal-heading">
                <div class="max-w-3xl">
                    <h2 id="legal-heading" class="text-lg font-semibold text-stone-900 dark:text-white">ご利用にあたって</h2>
                    <p class="mt-2 text-sm leading-6 text-stone-500 dark:text-stone-400">Web Learningの利用条件や情報の取り扱い、料金に関する情報を確認できます。</p>
                    <div class="mt-6">
                        @include('profile.partials.legal-links')
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
