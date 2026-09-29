@php
    $assistantQuestionId = $questionId ?? (is_object($question) ? $question->id : null);
    $assistantEnabled = $enableAi ?? true;
    $explanationHeading = $explanationHeading ?? '解説';
    $explanationText = is_array($question) ? ($question['explanation'] ?? '') : ($question->explanation ?? '');
    $assistantContext = ['questionId' => $assistantQuestionId, 'remaining' => $learningAiRemaining ?? 3, 'enableAi' => $assistantEnabled];
@endphp

<div x-data="learningAssistant(@js($assistantContext))" @keydown.escape.window="closeAll()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="font-semibold">{{ $explanationHeading }}</h3>
        @if ($assistantEnabled)
            <button type="button" @click="open = true" class="inline-flex min-h-9 items-center rounded-md border border-stone-300 px-3 text-sm font-semibold text-[#3155D9] transition hover:border-[#3155D9] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:border-stone-700">AIに質問する</button>
        @endif
    </div>
    <p class="selection-source mt-3 leading-8" data-selection-source data-selection-field="explanation" @if ($assistantQuestionId) data-question-id="{{ $assistantQuestionId }}" @endif>
        @foreach ($explanationSegments ?? [['type' => 'text', 'text' => $explanationText]] as $segment)
            @if ($segment['type'] === 'term')
                <button type="button" @mouseenter="openTerm($el, @js($segment['term']), @js($segment['description']))" @mouseleave="scheduleTermClose()" @focus="openTerm($el, @js($segment['term']), @js($segment['description']))" @click.stop="toggleTerm($el, @js($segment['term']), @js($segment['description']))" :aria-expanded="activeTerm === @js($segment['term'])" aria-haspopup="dialog" aria-controls="learning-term-popover" class="term-trigger rounded-sm border-b border-dotted border-stone-500 text-inherit focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:border-stone-400">{{ $segment['text'] }}</button>
            @else
                {{ $segment['text'] }}
            @endif
        @endforeach
    </p>

    <div x-cloak x-show="activeTerm" x-transition id="learning-term-popover" role="dialog" @mouseenter="cancelTermClose()" @mouseleave="scheduleTermClose()" @click.outside="closeTerm()" :style="`left: ${termPosition.left}px; top: ${termPosition.top}px`" class="fixed z-[75] w-[min(20rem,calc(100vw-1rem))] rounded-lg border border-stone-200 bg-[#FDFCFA] p-4 shadow-lg dark:border-stone-700 dark:bg-stone-900">
        <p class="font-semibold" x-text="activeTerm"></p><p class="mt-2 text-sm leading-6 text-stone-700 dark:text-stone-300" x-text="activeDescription"></p>
    </div>

    @if ($assistantEnabled)
    <div x-cloak x-show="open" x-transition class="fixed inset-0 z-[70] flex items-end bg-black/25 p-4 sm:items-center sm:justify-center" @click.self="close()">
        <div role="dialog" aria-modal="true" aria-labelledby="learning-ai-dialog-title" class="max-h-[calc(100vh-2rem)] w-full max-w-xl overflow-y-auto rounded-xl bg-[#FDFCFA] p-5 shadow-xl dark:bg-stone-900 sm:p-7">
            <div class="flex items-start justify-between gap-5"><div><h2 id="learning-ai-dialog-title" class="font-editorial text-2xl font-semibold">AIに質問する</h2><p class="mt-2 text-sm text-stone-600 dark:text-stone-300">現在の問題と解説をもとに、学習に関する質問へ答えます。</p></div><button type="button" @click="close()" class="grid size-10 place-items-center rounded-lg text-stone-600 hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:text-stone-300 dark:hover:bg-stone-800" aria-label="閉じる">×</button></div>
            <template x-if="remaining > 0"><div class="mt-6"><p class="text-sm font-semibold" x-text="`本日の残り ${remaining} / 3回`">本日の残り {{ $learningAiRemaining ?? 3 }} / 3回</p><label for="learning-ai-question" class="mt-5 block text-sm font-semibold">質問</label><textarea id="learning-ai-question" x-model="question" maxlength="500" rows="4" :disabled="loading" placeholder="例：systemctlとの違いは？" class="mt-2 block w-full rounded-lg border-stone-300 bg-white px-4 py-3 leading-7 focus:border-[#3155D9] focus:ring-[#3155D9] disabled:opacity-60 dark:border-stone-700 dark:bg-stone-950"></textarea><p class="mt-1 text-end text-xs text-stone-500"><span x-text="question.length"></span> / 500</p><p x-cloak x-show="error" class="mt-3 text-sm text-red-700 dark:text-red-300" x-text="error"></p><button type="button" @click="ask()" :disabled="loading || question.trim() === ''" class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-[#3155D9] px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"><span x-text="loading ? '回答を生成しています...' : '質問する →'">質問する →</span></button></div></template>
            <template x-if="remaining === 0"><div class="mt-6 rounded-lg border border-stone-200 p-4 dark:border-stone-700"><p class="font-semibold">本日のAI質問を使い切りました。</p></div></template>
            <template x-if="messages.length"><div class="mt-7 border-t border-stone-200 pt-5 dark:border-stone-700"><template x-for="message in messages" :key="message.id"><div class="mb-5"><p class="text-xs font-semibold text-stone-500" x-text="message.role"></p><p class="mt-2 whitespace-pre-wrap leading-7" x-text="message.text"></p></div></template><p class="text-xs leading-6 text-stone-500 dark:text-stone-400">AIによる回答です。内容に誤りが含まれる場合があります。重要な情報は公式資料も確認してください。</p></div></template>
        </div>
    </div>
    @endif
    <div x-cloak x-show="toast" x-transition role="status" aria-live="polite" class="fixed inset-x-4 top-[4.5rem] z-[80] border border-stone-200 bg-[#FDFCFA] p-4 shadow-sm dark:border-stone-700 dark:bg-stone-900 sm:left-auto sm:right-6 sm:w-[360px]"><p class="text-sm font-medium" x-text="toast"></p></div>
</div>
