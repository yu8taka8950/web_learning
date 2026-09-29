<x-guest-layout>
    <div class="mb-8 w-full text-center">
        <h1 class="text-2xl font-semibold tracking-[-0.02em] text-[#171717]">アカウントを作成</h1>
        <p class="mt-2 text-sm text-stone-500">読む時間を、あなたの知識に。</p>
    </div>

    <div class="flex flex-col gap-6">
        <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-5">
            @csrf

            <div>
                <x-input-label for="name" value="名前" class="text-sm font-medium text-[#171717]" />
                <x-text-input id="name" class="mt-2 block h-12 w-full rounded-lg border-stone-300 bg-white px-3.5 text-sm text-[#171717] shadow-none placeholder:text-stone-400 focus:border-[#3155D9] focus:ring-2 focus:ring-[#3155D9]/20" type="text" name="name" :value="old('name')" placeholder="名前を入力" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="email" value="メールアドレス" class="text-sm font-medium text-[#171717]" />
                <x-text-input id="email" class="mt-2 block h-12 w-full rounded-lg border-stone-300 bg-white px-3.5 text-sm text-[#171717] shadow-none placeholder:text-stone-400 focus:border-[#3155D9] focus:ring-2 focus:ring-[#3155D9]/20" type="email" name="email" :value="old('email')" placeholder="メールアドレスを入力" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password" value="パスワード" class="text-sm font-medium text-[#171717]" />
                <x-text-input id="password" class="mt-2 block h-12 w-full rounded-lg border-stone-300 bg-white px-3.5 text-sm text-[#171717] shadow-none placeholder:text-stone-400 focus:border-[#3155D9] focus:ring-2 focus:ring-[#3155D9]/20" type="password" name="password" placeholder="パスワードを入力" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="password_confirmation" value="パスワード（確認）" class="text-sm font-medium text-[#171717]" />
                <x-text-input id="password_confirmation" class="mt-2 block h-12 w-full rounded-lg border-stone-300 bg-white px-3.5 text-sm text-[#171717] shadow-none placeholder:text-stone-400 focus:border-[#3155D9] focus:ring-2 focus:ring-[#3155D9]/20" type="password" name="password_confirmation" placeholder="パスワードを再入力" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            <p class="text-center text-xs leading-6 text-stone-500">登録することで、<a href="{{ route('terms') }}" class="font-medium text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4 transition hover:text-[#2848bd] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">利用規約</a>および<a href="{{ route('privacy') }}" class="font-medium text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4 transition hover:text-[#2848bd] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">プライバシーポリシー</a>に同意したものとみなします。</p>

            <button type="submit" class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-[#3155D9] px-4 text-sm font-semibold text-white transition hover:bg-[#2848bd] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F7F5F0]">アカウントを作成</button>
        </form>

        @if (config('services.google.login_enabled'))
            <div class="flex items-center gap-3 text-xs text-stone-400" aria-hidden="true">
                <span class="h-px flex-1 bg-stone-300"></span>
                <span>または</span>
                <span class="h-px flex-1 bg-stone-300"></span>
            </div>

            <a href="{{ route('auth.google.redirect') }}" class="inline-flex h-12 w-full items-center justify-center gap-3 rounded-lg border border-stone-300 bg-white px-4 text-sm font-semibold text-[#171717] transition hover:border-stone-400 hover:bg-stone-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 focus-visible:ring-offset-[#F7F5F0]"><img src="{{ asset('images/google-g-logo.png') }}" alt="" aria-hidden="true" class="h-5 w-auto shrink-0">Googleで続ける</a>
        @endif

        <div class="flex flex-col gap-3 text-center text-sm text-stone-500"><p>すでにアカウントをお持ちの方 <a href="{{ route('login') }}" class="font-medium text-[#3155D9] underline decoration-[#3155D9]/30 underline-offset-4 transition hover:text-[#2848bd] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">ログイン</a></p><p class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-xs"><a href="{{ route('terms') }}" class="text-stone-500 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">利用規約</a><a href="{{ route('privacy') }}" class="text-stone-500 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">プライバシーポリシー</a><a href="{{ route('tokushoho') }}" class="text-stone-500 underline decoration-stone-300 underline-offset-4 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">特定商取引法に基づく表記</a></p></div>
    </div>
</x-guest-layout>
