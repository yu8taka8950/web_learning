<div x-data="selectionToolbar(@js(['storeUrl' => route('learning-terms.store'), 'canSave' => $canSave ?? true, 'selectionScope' => $selectionScope ?? null]))" x-init="init()" @keydown.escape.window="hide()">
    <div x-cloak x-show="visible" data-selection-toolbar :style="`left: ${position.left}px; top: ${position.top}px`" class="fixed z-[90] flex max-w-[calc(100vw-1rem)] items-center gap-1 rounded-md border border-stone-200 bg-[#FDFCFA] p-1 shadow-lg dark:border-stone-700 dark:bg-stone-900">
        @if (($canSave ?? true) === true)
            <button type="button" @click="saveSelectedTerm()" :disabled="saving || saved" :aria-busy="saving" aria-label="用語を保存" title="用語を保存" class="min-h-9 rounded px-2 text-sm font-semibold text-stone-700 hover:bg-stone-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] disabled:cursor-not-allowed disabled:opacity-60 dark:text-stone-200 dark:hover:bg-stone-800"><span x-text="saving ? '保存中…' : (saved ? '保存済み' : '保存')">保存</span></button>
        @endif
        <button type="button" @click="openChatGpt()" class="min-h-9 rounded px-2 text-sm font-semibold text-[#3155D9] hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3155D9] dark:hover:bg-blue-950/30">ChatGPTに聞く ↗</button>
    </div>
    <div x-cloak x-show="toast" x-transition role="status" aria-live="polite" class="fixed inset-x-4 top-[4.5rem] z-[91] border border-stone-200 bg-[#FDFCFA] p-4 shadow-sm dark:border-stone-700 dark:bg-stone-900 sm:left-auto sm:right-6 sm:w-[360px]"><p class="text-sm font-medium" x-text="toast"></p></div>
</div>
