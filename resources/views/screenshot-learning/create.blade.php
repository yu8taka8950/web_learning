<x-app-layout>
    <x-slot name="header">
        <h2 class="font-editorial text-2xl font-semibold text-[#171717] dark:text-stone-100">スクリーンショットから学習</h2>
    </x-slot>

    <main x-data="{ fileName: '', fileSize: '', previewUrl: '', isDragging: false, isSubmitting: false, formatBytes(bytes) { return bytes < 1048576 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1048576).toFixed(1)} MB`; }, setFile(file) { if (!file) return; this.fileName = file.name; this.fileSize = this.formatBytes(file.size); if (this.previewUrl) URL.revokeObjectURL(this.previewUrl); this.previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : ''; }, chooseFile(event) { this.setFile(event.target.files[0]); }, dropFile(event) { this.isDragging = false; const file = event.dataTransfer.files[0]; if (!file) return; this.$refs.fileInput.files = event.dataTransfer.files; this.setFile(file); } }" class="mx-auto max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:py-8">
        <section class="grid gap-6 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)] lg:items-center">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.28em] text-[#3155D9]">IMAGE LEARNING</p>
                <h1 class="mt-3 font-editorial text-3xl font-semibold leading-tight text-[#171717] dark:text-stone-100 sm:text-4xl">スクリーンショットから学習</h1>
                <p class="mt-3 max-w-2xl text-sm leading-7 text-stone-600 dark:text-stone-300 sm:text-base">スクリーンショットをアップロードすると、Geminiが画像を解析して重要な学習候補を抽出します。日々の学びを、もっと手軽に。</p>
            </div>
            <div class="flex flex-col items-center gap-4 text-center sm:items-start sm:text-left lg:flex-row lg:items-center lg:gap-4">
                <img src="{{ Vite::asset('resources/images/icon/sample10.png') }}" alt="スクリーンショットから学習するイメージ" class="h-auto w-[160px] shrink-0 object-contain sm:w-[180px] lg:w-[145px]">
                <div class="min-w-0 flex-1 [text-wrap:pretty]">
                    <h2 class="font-editorial text-lg font-semibold leading-7 text-[#171717] dark:text-stone-100"><span class="block">画像をアップロードして、</span><span class="block">学びをはじめましょう。</span></h2>
                    <p class="mt-2 text-sm leading-6 text-stone-600 dark:text-stone-300"><span class="block">アップロードした画像をGeminiが解析し、</span><span class="block">重要な用語やポイントを抽出して、</span><span class="block">学習につなげます。</span></p>
                </div>
            </div>
        </section>

        @if (session('usage-limit'))
            @php($limitUsage = session('usage-limit'))
            <section class="mt-7 max-w-2xl border border-stone-300 bg-[#FDFCFA] p-6 dark:border-stone-700 dark:bg-stone-900">
                <h2 class="font-editorial text-xl font-semibold text-[#171717] dark:text-white">今月のスクリーンショット解析を{{ number_format($limitUsage['limit']) }}回使い切りました</h2>
                @if ($limitUsage['plan'] === 'free')
                    <p class="mt-3 text-sm leading-7 text-stone-600 dark:text-stone-300">Freeプランでは月100回まで利用できます。Plusなら月1,000回まで利用できます。</p>
                    <a href="{{ route('pricing') }}" class="mt-5 inline-flex bg-[#3155D9] px-5 py-3 text-sm font-semibold text-white">Plusで続ける</a>
                @else
                    <p class="mt-3 text-sm leading-7 text-stone-600 dark:text-stone-300">次回の利用枠は{{ now()->addMonth()->startOfMonth()->format('n月j日') }}にリセットされます。</p>
                @endif
            </section>
        @endif

        @unless (session('usage-limit'))
        <div class="mt-7 grid gap-6 lg:grid-cols-[minmax(0,1.65fr)_minmax(15rem,1fr)] lg:items-start">
            <section aria-labelledby="upload-title">
                <form method="POST" action="{{ route('screenshot-learning.analyze') }}" enctype="multipart/form-data" class="flex flex-col gap-4" @submit="isSubmitting = true">
                    @csrf
                    <div
                        class="relative flex min-h-[19rem] flex-col items-center justify-center rounded-lg border border-dashed border-[#3155D9]/40 bg-white/30 px-6 py-8 text-center transition-colors duration-150 hover:border-[#3155D9]/70 hover:bg-[#EEF3FF]/60"
                        :class="{ 'border-[#3155D9] bg-[#EEF3FF]': isDragging }"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="dropFile($event)"
                    >
                        <label for="screenshot" class="sr-only">アップロードする画像</label>
                        <input x-ref="fileInput" id="screenshot" name="screenshot" type="file" accept="image/jpeg,image/png,image/webp" required class="sr-only" @change="chooseFile($event)">
                        <div class="grid size-12 place-items-center rounded-full bg-[#EEF3FF] text-[#3155D9]" aria-hidden="true">
                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><rect x="3.5" y="4" width="17" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path stroke-linecap="round" stroke-linejoin="round" d="m4 17 4.5-4 3.5 3 2.5-2.5 5.5 5.5"/></svg>
                        </div>
                        <h2 id="upload-title" class="mt-5 font-editorial text-xl font-semibold text-[#171717] dark:text-stone-100">画像をアップロード</h2>
                        <p class="mt-2 max-w-sm text-sm leading-7 text-stone-500 dark:text-stone-400">ここに画像をドラッグ＆ドロップするか、下のボタンからファイルを選択してください。</p>
                        <button type="button" class="mt-5 inline-flex min-h-11 items-center justify-center rounded-md bg-[#3155D9] px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#2747BC] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2" @click="$refs.fileInput.click()">ファイルを選択</button>
                        <p class="mt-4 text-xs text-stone-500 dark:text-stone-400">JPG、PNG、WebP（最大10MB）に対応しています。</p>

                        <div x-cloak x-show="fileName" class="mt-5 flex w-full max-w-sm items-center gap-3 border-t border-stone-200 pt-4 text-left dark:border-stone-700">
                            <img x-show="previewUrl" :src="previewUrl" alt="選択した画像のプレビュー" class="size-12 rounded object-cover" >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-[#171717] dark:text-stone-100" x-text="fileName"></p>
                                <p class="text-xs text-stone-500 dark:text-stone-400" x-text="fileSize"></p>
                            </div>
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('screenshot')" />
                    <button type="submit" class="inline-flex min-h-11 w-fit items-center justify-center rounded-md bg-[#3155D9] px-5 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#2747BC] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" x-bind:disabled="!fileName || isSubmitting">
                        <span x-text="isSubmitting ? '画像を解析しています…' : '画像を解析する'">画像を解析する</span>
                    </button>
                </form>
            </section>

            <aside aria-labelledby="upload-tips-title" class="lg:border-s lg:border-stone-200 lg:ps-8 dark:border-stone-700">
                <h2 id="upload-tips-title" class="font-editorial text-xl font-semibold text-[#171717] dark:text-stone-100">アップロードのポイント</h2>
                <ul class="mt-5 space-y-4 text-sm leading-6 text-stone-600 dark:text-stone-300">
                    <li class="flex gap-3"><span class="font-semibold text-[#3155D9]" aria-hidden="true">✓</span><span>文字がはっきり写っている画像がおすすめ</span></li>
                    <li class="flex gap-3"><span class="font-semibold text-[#3155D9]" aria-hidden="true">✓</span><span>1枚ずつアップロード</span></li>
                    <li class="flex gap-3"><span class="font-semibold text-[#3155D9]" aria-hidden="true">✓</span><span>JPG / PNG / WebP</span></li>
                    <li class="flex gap-3"><span class="font-semibold text-[#3155D9]" aria-hidden="true">✓</span><span>最大10MB</span></li>
                </ul>
                <div class="mt-7 text-sm leading-7 text-stone-500 dark:text-stone-400">ノートのスクリーンショット、スライド、教科書のページなど、さまざまな画像から学習できます。</div>
            </aside>
        </div>
        @endunless
    </main>
</x-app-layout>
