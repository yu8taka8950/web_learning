<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Web Learning Guide</title>
    @include('layouts.partials.pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F7F5F0] font-sans text-[#171717] antialiased">
<div x-data="{ step: 1 }" class="min-h-screen overflow-x-hidden bg-[#F7F5F0] lg:grid lg:h-screen lg:grid-rows-[auto_minmax(0,1fr)_auto] lg:overflow-hidden">
    <header class="relative z-20 grid grid-cols-[1fr_auto] items-center gap-x-6 border-b border-stone-300 px-5 py-4 sm:px-8 lg:grid-cols-[1fr_minmax(15rem,22rem)_1fr] lg:px-10 lg:py-5 xl:px-14">
        <div class="flex items-center gap-3">
            <span class="grid size-9 shrink-0 place-items-center border border-[#3155D9] text-sm font-bold text-[#3155D9]">W</span>
            <span class="text-sm font-semibold tracking-tight">Web Learning</span>
            @if ($isOnboardingComplete)
                <a href="{{ route('dashboard') }}" class="hidden border-s border-stone-300 ps-4 text-xs text-stone-500 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] sm:inline">← Dashboardへ戻る</a>
            @endif
        </div>
        <div class="col-span-2 row-start-2 mt-4 flex items-center gap-4 sm:col-span-1 sm:col-start-1 sm:max-w-xs lg:col-start-2 lg:row-start-1 lg:mt-0 lg:max-w-none" role="progressbar" aria-label="オンボーディングの進捗" aria-valuemin="1" aria-valuemax="5" :aria-valuenow="step">
            <p class="shrink-0 text-sm font-semibold tabular-nums"><span x-text="step">1</span> / 5</p>
            <div class="h-px flex-1 bg-stone-300"><div class="h-px bg-[#3155D9] transition-[width] duration-200 motion-reduce:transition-none" :style="`width: ${(step / 5) * 100}%`"></div></div>
        </div>
        <div class="col-start-2 row-start-1 justify-self-end lg:col-start-3">
            @if ($isGuest)
                <a href="{{ url('/') }}" class="px-1 py-2 text-sm font-medium text-stone-600 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">トップへ戻る</a>
            @elseif ($isOnboardingComplete)
                <a href="{{ route('dashboard') }}" class="px-1 py-2 text-sm font-medium text-stone-600 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">Dashboardへ戻る</a>
            @else
                <form method="POST" action="{{ route('onboarding.complete') }}">
                    @csrf
                    <button type="submit" name="action" value="skip" class="px-1 py-2 text-sm font-medium text-stone-600 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">スキップ →</button>
                </form>
            @endif
        </div>
    </header>

    <main class="relative min-h-0">
        <div class="grid min-h-full lg:grid-cols-[56%_44%]">
            <div class="relative z-10 flex items-center px-5 py-10 sm:px-8 lg:min-h-0 lg:px-10 lg:py-6 xl:px-14 2xl:px-20">
                <div class="w-full max-w-2xl">
                    <section x-show="step === 1" x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0 translate-y-1.5" x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="text-xs font-semibold tracking-[0.22em] text-[#3155D9]">WEB LEARNING GUIDE</p>
                        <h1 class="mt-5 font-editorial text-4xl font-semibold leading-[1.3] tracking-tight sm:text-5xl xl:text-6xl">読むだけで、<br>学びがたまる。</h1>
                        <div class="mt-6 max-w-xl space-y-3 text-sm leading-7 text-stone-600 sm:text-base sm:leading-8">
                            <p>普段のWeb閲覧から、<br class="hidden sm:block">覚える価値のある知識を見つけて問題にします。</p>
                            <p>「単語帳を作る」作業をできるだけ減らし、<br class="hidden sm:block">普段の閲覧そのものを学習につなげます。</p>
                        </div>
                        <div class="mt-8 grid grid-cols-[1fr_auto_1fr] items-center gap-2 border-y border-stone-300 py-5 text-center text-xs sm:grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr] sm:text-sm">
                            <span>Web閲覧</span><span class="text-stone-400">→</span><span>知識発見</span><span class="hidden text-stone-400 sm:block">→</span><span class="hidden sm:block">問題</span><span class="hidden text-stone-400 sm:block">→</span><span class="hidden font-semibold text-[#178C78] sm:block">復習</span>
                            <span class="col-span-3 mt-2 text-stone-500 sm:hidden">→ 問題 → <strong class="text-[#178C78]">復習</strong></span>
                        </div>
                    </section>

                    <section x-cloak x-show="step === 2" x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0 translate-y-1.5" x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="text-xs font-semibold tracking-[0.2em] text-[#3155D9]">BROWSE</p>
                        <h2 class="mt-4 font-editorial text-3xl font-semibold leading-snug sm:text-5xl">いつも通りWebを見るだけ</h2>
                        <p class="mt-5 max-w-xl text-sm leading-7 text-stone-600 sm:text-base sm:leading-8">Chrome拡張の学習モードをONにして、普段どおりWebページを読みます。<br class="hidden sm:block">読んだ内容から、学習候補が自動で見つかります。</p>
                        <div class="mt-7 border border-stone-300 bg-white">
                            <div class="flex items-center justify-between border-b border-stone-300 px-4 py-3"><span class="text-sm font-semibold">Chrome拡張</span><span class="text-xs font-semibold">学習モード <strong class="ms-2 bg-[#178C78] px-3 py-1.5 text-white">ON</strong></span></div>
                            <ol class="grid gap-px bg-stone-200 sm:grid-cols-2">
                                @foreach (['Chrome拡張を開く', '学習モードをON', '普段どおりWebを見る', '学習候補の通知を受け取る'] as $index => $instruction)
                                    <li class="flex items-center gap-3 bg-white px-4 py-3 text-sm"><span class="font-editorial text-lg text-[#3155D9]">{{ $index + 1 }}.</span>{{ $instruction }}</li>
                                @endforeach
                            </ol>
                        </div>
                    </section>

                    <section x-cloak x-show="step === 3" x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0 translate-y-1.5" x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="text-xs font-semibold tracking-[0.2em] text-[#3155D9]">SELECT</p>
                        <h2 class="mt-4 font-editorial text-3xl font-semibold sm:text-5xl">覚えたいものだけ選ぶ</h2>
                        <p class="mt-5 max-w-xl text-sm leading-7 text-stone-600 sm:text-base sm:leading-8">見つかった学習候補から、<br class="hidden sm:block">自分が覚えておきたいものだけを選びます。</p>
                        <div class="mt-7 border-y border-stone-300 py-5">
                            <div class="flex items-end justify-between"><h3 class="font-editorial text-xl font-semibold">学習候補</h3><span class="text-xs text-stone-500">3件を選択</span></div>
                            <div class="mt-3 grid grid-cols-2 gap-x-6 sm:grid-cols-3">
                                @foreach ([['systemd', true], ['systemctl', true], ['daemon', false], ['unit', true], ['process', false]] as [$term, $selected])
                                    <div class="flex items-center gap-2 border-t border-stone-200 py-2.5 text-sm"><span @class(['grid size-5 place-items-center border text-xs', 'border-[#3155D9] bg-[#3155D9] text-white' => $selected, 'border-stone-400' => ! $selected])>{{ $selected ? '✓' : '' }}</span>{{ $term }}</div>
                                @endforeach
                            </div>
                            <div class="mt-4 bg-[#3155D9] px-5 py-3 text-center text-sm font-semibold text-white">選んだ言葉から問題を作る</div>
                        </div>
                    </section>

                    <section x-cloak x-show="step === 4" x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0 translate-y-1.5" x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="text-xs font-semibold tracking-[0.2em] text-[#3155D9]">GENERATE</p>
                        <h2 class="mt-4 font-editorial text-3xl font-semibold sm:text-5xl">AIが問題に変える</h2>
                        <p class="mt-4 text-sm leading-7 text-stone-600 sm:text-base">選んだ言葉をもとに、AIが4択問題と解説を作ります。</p>
                        <div class="mt-5 border-y border-stone-300 py-4">
                            <p class="font-editorial text-lg font-semibold leading-7">systemdの役割として<br>最も適切なものは？</p>
                            <div class="mt-3 grid gap-1.5 text-sm sm:grid-cols-2">
                                @foreach (['A. Linuxのサービス管理', 'B. ファイル圧縮', 'C. ユーザー削除', 'D. IPアドレス確認'] as $option)
                                    <div class="flex items-center gap-2 border border-stone-300 bg-white px-3 py-2"><span class="size-3.5 rounded-full border border-stone-400"></span>{{ $option }}</div>
                                @endforeach
                            </div>
                            <div class="mt-3 grid gap-2 text-xs sm:grid-cols-3"><span>＋ 正解を見る</span><span>＋ 解説を見る</span><span>＋ 参照サイトを見る</span></div>
                        </div>
                        <p class="mt-4 border-s-2 border-stone-300 ps-3 text-xs leading-5 text-stone-500">AI生成内容には誤りが含まれる場合があります。<br>正確な情報は公式ドキュメントや信頼できる資料も確認してください。</p>
                    </section>

                    <section x-cloak x-show="step === 5" x-transition:enter="transition ease-out duration-200 motion-reduce:transition-none" x-transition:enter-start="opacity-0 translate-y-1.5" x-transition:enter-end="opacity-100 translate-y-0">
                        <p class="text-xs font-semibold tracking-[0.2em] text-[#178C78]">REVIEW</p>
                        <h2 class="mt-4 font-editorial text-3xl font-semibold sm:text-5xl">忘れる前に、もう一度</h2>
                        <div class="mt-6 grid grid-cols-3 gap-px bg-stone-300 text-center text-sm">
                            @foreach (['3日後', '7日後', '14日後'] as $schedule)
                                <div class="bg-[#F7F5F0] px-2 py-3"><span class="block text-xs text-[#178C78]">正解</span><strong class="mt-1 block">→ {{ $schedule }}</strong></div>
                            @endforeach
                        </div>
                        <div class="mt-2 flex items-center justify-between border border-stone-300 px-4 py-3 text-sm"><span>不正解 / 未回答</span><strong>→ 翌日</strong></div>
                        <div class="mt-7 border-s-2 border-[#178C78] ps-5"><p class="font-editorial text-2xl font-semibold">では、始めましょう。</p><p class="mt-2 text-sm leading-7 text-stone-600">Webを見ながら、<br>自分だけの学びをためていきましょう。</p></div>
                        <div class="mt-6 lg:hidden">
                            @if ($isGuest)
                                <a href="{{ route('register') }}" class="block w-full bg-[#3155D9] px-6 py-4 text-center text-sm font-semibold text-white">無料ではじめる →</a>
                            @elseif ($isOnboardingComplete)
                                <a href="{{ route('dashboard') }}" class="block w-full bg-[#3155D9] px-6 py-4 text-center text-sm font-semibold text-white">Dashboardへ戻る →</a>
                            @else
                                <form method="POST" action="{{ route('onboarding.complete') }}">@csrf<button type="submit" class="w-full bg-[#3155D9] px-6 py-4 text-sm font-semibold text-white">Web Learningを始める →</button></form>
                            @endif
                        </div>
                    </section>
                </div>
            </div>

            <div class="relative h-[260px] overflow-hidden sm:h-[320px] lg:h-full lg:min-h-0">
                <div class="pointer-events-none absolute inset-y-0 left-0 z-10 hidden w-16 bg-[#F7F5F0] lg:block" style="clip-path: ellipse(100% 70% at 0% 50%)"></div>
                @foreach (range(1, 5) as $imageStep)
                    <img x-cloak x-show="step === {{ $imageStep }}" x-transition.opacity.duration.200ms src="{{ Vite::asset('resources/images/onboarding/sample'.$imageStep.'.png') }}" alt="" class="absolute inset-0 size-full object-cover">
                @endforeach
            </div>
        </div>
    </main>

    <footer class="relative z-20 border-t border-stone-300 bg-[#F7F5F0] px-5 py-4 sm:px-8 lg:px-10 xl:px-14">
        <div class="flex min-h-11 items-center justify-between gap-5">
            <button x-cloak x-show="step > 1" type="button" @click="step--" class="px-1 py-2 text-sm font-medium text-stone-600 transition hover:text-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9]">← 戻る</button>
            <span x-show="step === 1" aria-hidden="true"></span>
            <button x-show="step < 5" type="button" @click="step++" class="bg-[#3155D9] px-7 py-3 text-sm font-semibold text-white transition hover:bg-[#2747ba] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2">次へ →</button>
            <div x-cloak x-show="step === 5" class="hidden lg:block">
                @if ($isGuest)
                    <a href="{{ route('register') }}" class="inline-flex bg-[#3155D9] px-7 py-3 text-sm font-semibold text-white transition hover:bg-[#2747ba] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2">無料ではじめる →</a>
                @elseif ($isOnboardingComplete)
                    <a href="{{ route('dashboard') }}" class="inline-flex bg-[#3155D9] px-7 py-3 text-sm font-semibold text-white transition hover:bg-[#2747ba] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2">Dashboardへ戻る →</a>
                @else
                    <form method="POST" action="{{ route('onboarding.complete') }}">@csrf<button type="submit" class="bg-[#3155D9] px-7 py-3 text-sm font-semibold text-white transition hover:bg-[#2747ba] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2">Web Learningを始める →</button></form>
                @endif
            </div>
        </div>
    </footer>
</div>
</body>
</html>
