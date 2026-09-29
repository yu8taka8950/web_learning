<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">アカウントを削除</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">この操作は取り消せません。学習データとスクリーンショットが削除されます。Plusをご利用中の場合、契約も終了します。</p>
    </header>

    @if (session('google-delete-confirmed-at') !== null)
        <form method="post" action="{{ route('profile.destroy') }}">@csrf @method('delete')<x-danger-button>アカウントを完全に削除</x-danger-button></form>
    @else
        <a href="{{ route('profile.delete.google.redirect') }}" class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">Googleで本人確認して削除</a>
    @endif

    <x-input-error :messages="$errors->get('account')" class="mt-2" />
</section>
