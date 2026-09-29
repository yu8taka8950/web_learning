<x-app-layout>
    <main class="mx-auto max-w-2xl px-4 py-12 sm:px-6 sm:py-16" data-extension-account-mismatch>
        <section aria-labelledby="account-mismatch-title" class="border-y border-stone-300 py-8 dark:border-stone-700 sm:py-10">
            <p class="text-xs font-semibold tracking-[0.16em] text-[#3155D9]">CHROME EXTENSION</p>
            <h1 id="account-mismatch-title" class="mt-3 font-editorial text-3xl font-semibold leading-tight">Web Learningのアカウントが変更されました</h1>
            <p class="mt-5 max-w-xl text-sm leading-7 text-stone-600 dark:text-stone-300">
                現在ログイン中の
            </p>
            <p class="mt-1 max-w-xl break-all text-base font-semibold leading-7 text-stone-900 dark:text-stone-100" data-testid="current-account-email">
                {{ $currentUserEmail }}
            </p>
            <p class="mt-1 max-w-xl text-sm leading-7 text-stone-600 dark:text-stone-300">
                で学習を続けますか？
            </p>

            <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:items-center">
                <button id="reconnectExtension" type="button" data-testid="reconnect-extension" class="inline-flex min-h-11 items-center justify-center bg-[#3155D9] px-5 text-sm font-semibold text-white transition hover:bg-[#2847B8] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2">
                    このアカウントで続ける
                </button>
                <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center justify-center px-4 text-sm font-semibold text-stone-600 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] dark:text-stone-300">ホームへ戻る</a>
            </div>

            <p id="reconnectStatus" class="mt-5 text-sm leading-6 text-stone-600 dark:text-stone-300" role="status" aria-live="polite"></p>
        </section>
    </main>

    <script>
        (() => {
            const button = document.getElementById('reconnectExtension');
            const status = document.getElementById('reconnectStatus');
            let requestId = null;
            let responseTimer = null;

            button.addEventListener('click', () => {
                requestId = window.crypto.randomUUID();
                button.disabled = true;
                status.textContent = 'アカウントを切り替える準備をしています…';

                window.postMessage({ type: 'web-learning:reconnect-extension', requestId }, window.location.origin);

                window.clearTimeout(responseTimer);
                responseTimer = window.setTimeout(() => {
                    button.disabled = false;
                    status.textContent = 'Chrome拡張機能を確認できませんでした。拡張機能を再読み込みして、もう一度お試しください。';
                }, 5000);
            });

            window.addEventListener('message', (event) => {
                if (event.source !== window || event.origin !== window.location.origin || event.data?.type !== 'web-learning:reconnect-extension-result' || event.data?.requestId !== requestId) {
                    return;
                }

                window.clearTimeout(responseTimer);
                button.disabled = false;
                status.textContent = event.data.success
                    ? '表示された画面で続行すると、このアカウントで学習を続けられます。学習候補から、もう一度問題を作成してください。'
                    : (event.data.message || 'アカウントを切り替える準備を開始できませんでした。Chrome拡張機能を再読み込みして、もう一度お試しください。');
            });
        })();
    </script>
</x-app-layout>
