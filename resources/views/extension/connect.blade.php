<x-guest-layout>
    <div class="w-full max-w-lg border border-stone-200 bg-[#F7F5F0] p-8 text-[#171717] dark:border-stone-700 dark:bg-stone-900 dark:text-stone-100">
        <p class="text-xs font-semibold tracking-[0.16em] text-[#3155D9]">WEB LEARNING</p>
        <h1 class="mt-3 font-editorial text-3xl font-semibold">Chrome拡張機能を接続</h1>

        @if ($connected)
            <p class="mt-5 text-sm leading-7 text-stone-600 dark:text-stone-300">接続しました。Chrome拡張機能に戻ると、Learning Modeが自動的にONになります。</p>
        @elseif (! $authenticated)
            <p class="mt-5 text-sm leading-7 text-stone-600 dark:text-stone-300">Web Learningのログイン画面を開いています。ログイン後に自動で接続します。</p>
        @else
            <p class="mt-5 text-sm leading-7 text-stone-600 dark:text-stone-300">Chrome拡張機能に安全に接続しています。</p>
            <form id="pairingApprovalForm" method="POST" action="{{ route('extension.connect.approve', $pairingId) }}" class="mt-7">
                @csrf
                <input id="pairingSecret" type="hidden" name="pairing_secret" value="">
            </form>
        @endif

        <script>
            const pairingStorageKey = 'web-learning-pairing-secret-{{ $pairingId }}';
            const pairingSecretFromFragment = new URLSearchParams(window.location.hash.slice(1)).get('pairing_secret');

            if (pairingSecretFromFragment) {
                window.sessionStorage.setItem(pairingStorageKey, pairingSecretFromFragment);
                window.history.replaceState(null, '', window.location.pathname);
            }

            const pairingSecretInput = document.getElementById('pairingSecret');

            if (pairingSecretInput) {
                pairingSecretInput.value = window.sessionStorage.getItem(pairingStorageKey) ?? '';
            }

            @if (! $connected && ! $authenticated)
                window.location.replace('{{ route('extension.connect.authorize', $pairingId) }}');
            @endif

            const pairingApprovalForm = document.getElementById('pairingApprovalForm');

            if (pairingApprovalForm && pairingSecretInput?.value) {
                pairingApprovalForm.requestSubmit();
            }

            @if ($connected)
                window.sessionStorage.removeItem(pairingStorageKey);
            @endif
        </script>
    </div>
</x-guest-layout>
