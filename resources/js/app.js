import Alpine from 'alpinejs';
import { registerPwaInstall, registerServiceWorker } from './pwa';
import {
    ArrowRight,
    BadgeCheck,
    BookOpen,
    Bookmark,
    Braces,
    ChartNoAxesColumnIncreasing,
    CircleHelp,
    Cloud,
    Code2,
    createIcons,
    Database,
    EllipsisVertical,
    Folder,
    House,
    Image,
    List,
    Menu,
    Network,
    RefreshCw,
    Search,
    Server,
    ShieldCheck,
    Sparkles,
    Terminal,
    Trash2,
    X,
} from 'lucide';

const dashboardIcons = {
    ArrowRight,
    BadgeCheck,
    BookOpen,
    Bookmark,
    Braces,
    ChartNoAxesColumnIncreasing,
    CircleHelp,
    Cloud,
    Code2,
    Database,
    EllipsisVertical,
    Folder,
    House,
    Image,
    List,
    Menu,
    Network,
    RefreshCw,
    Search,
    Server,
    ShieldCheck,
    Sparkles,
    Terminal,
    Trash2,
    X,
};

window.Alpine = Alpine;

export function normalizeSelectedText(text) {
    return text.replace(/\s+/gu, ' ').trim();
}

export function buildSelectedTextPrompt(text) {
    return `${normalizeSelectedText(text)}について初心者にもわかりやすく説明してください。`;
}

export function buildChatGptUrl(prompt) {
    return `https://chatgpt.com/?q=${encodeURIComponent(prompt)}`;
}

async function copyToClipboard(text) {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    const copied = document.execCommand('copy');
    textarea.remove();
    if (!copied) throw new Error('copy failed');
}

function clamp(value, minimum, maximum) {
    return Math.min(Math.max(value, minimum), maximum);
}

Alpine.data('selectionToolbar', (context = {}) => ({
    visible: false,
    selectedText: '',
    selectedQuestionId: null,
    selectedSourceField: '',
    selectedSourceKey: '',
    saving: false,
    saved: false,
    position: { left: 8, top: 8 },
    toast: '',
    toastTimer: null,
    canSave: context.canSave !== false,
    selectionScope: context.selectionScope ?? null,
    init() {
        const update = () => window.setTimeout(() => this.readSelection(), 0);
        const closeOnScroll = () => this.hide();
        document.addEventListener('mouseup', update);
        document.addEventListener('touchend', update, { passive: true });
        document.addEventListener('selectionchange', update);
        window.addEventListener('scroll', closeOnScroll, { passive: true });
        document.addEventListener('scroll', closeOnScroll, { passive: true, capture: true });
        document.addEventListener('pointerdown', (event) => {
            if (!event.target.closest('[data-selection-toolbar]')) this.hide();
        }, true);
    },
    readSelection() {
        const selection = window.getSelection();
        const text = normalizeSelectedText(selection?.toString() ?? '');
        const selectionElement = (node) => node?.nodeType === 1 ? node : node?.parentElement;
        const anchor = selectionElement(selection?.anchorNode);
        const focus = selectionElement(selection?.focusNode);
        const anchorSource = anchor?.closest('[data-selection-source]');
        const focusSource = focus?.closest('[data-selection-source]');
        const sourceField = anchorSource?.dataset.selectionField;
        const questionId = Number(anchorSource?.dataset.questionId);
        const hasQuestionId = Number.isInteger(questionId) && questionId > 0;
        const selectionScope = this.selectionScope === null || anchorSource?.closest(this.selectionScope);
        if (selection?.isCollapsed || !text || text.length > 100 || !anchorSource || anchorSource !== focusSource || !selectionScope || !['question', 'input_question', 'explanation'].includes(sourceField) || (this.canSave && !hasQuestionId)) {
            this.hide();
            return;
        }
        const range = selection.getRangeAt(0);
        const rect = range.getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) {
            this.hide();
            return;
        }
        this.selectedText = text;
        this.selectedQuestionId = questionId;
        this.selectedSourceField = sourceField;
        const sourceKey = `${questionId}:${sourceField}:${text}`;
        if (this.selectedSourceKey !== sourceKey) this.saved = false;
        this.selectedSourceKey = sourceKey;
        this.position = {
            left: clamp(rect.left + (rect.width / 2) - 120, 8, Math.max(8, window.innerWidth - 248)),
            top: clamp(rect.top - 48, 8, window.innerHeight - 48),
        };
        this.visible = true;
    },
    hide() {
        this.visible = false;
    },
    notify(message) {
        this.toast = message;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = ''; }, 3500);
    },
    async copyPrompt(prompt, successMessage) {
        try {
            await copyToClipboard(prompt);
            this.notify(successMessage);
        } catch (_) {
            this.notify('質問文をコピーできませんでした。');
        }
    },
    async saveSelectedTerm() {
        if (!this.canSave || this.saving || this.saved || !this.selectedQuestionId || !this.selectedSourceField) return;

        this.saving = true;
        try {
            const response = await fetch(context.storeUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    question_id: this.selectedQuestionId,
                    selected_text: this.selectedText,
                    source_field: this.selectedSourceField,
                }),
            });
            const data = await response.json();
            if (!response.ok) {
                this.notify('用語を保存できませんでした。');
                return;
            }

            this.saved = true;
            this.notify(data.created ? '用語を保存しました。' : '保存済みです。');
        } catch (_) {
            this.notify('用語を保存できませんでした。');
        } finally {
            this.saving = false;
        }
    },
    openChatGpt() {
        const prompt = buildSelectedTextPrompt(this.selectedText);

        window.open(buildChatGptUrl(prompt), '_blank', 'noopener,noreferrer');
        this.copyPrompt(prompt, '質問文をコピーしてChatGPTを開きました。');
    },
}));

Alpine.data('learningAssistant', (context) => ({
    open: false,
    loading: false,
    question: '',
    error: '',
    remaining: context.remaining,
    messages: [],
    toast: '',
    toastTimer: null,
    activeTerm: '',
    activeDescription: '',
    termPosition: { left: 8, top: 8 },
    termCloseTimer: null,
    notify(message) {
        this.toast = message;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toast = ''; }, 3500);
    },
    openTerm(element, term, description) {
        this.cancelTermClose();
        const rect = element.getBoundingClientRect();
        this.activeTerm = term;
        this.activeDescription = description;
        this.termPosition = { left: clamp(rect.left, 8, Math.max(8, window.innerWidth - 328)), top: clamp(rect.bottom + 8, 8, Math.max(8, window.innerHeight - 120)) };
    },
    toggleTerm(element, term, description) {
        if (this.activeTerm === term) {
            this.closeTerm();
            return;
        }
        this.openTerm(element, term, description);
    },
    isTermOpen(term) {
        return this.activeTerm === term;
    },
    scheduleTermClose() {
        this.cancelTermClose();
        this.termCloseTimer = setTimeout(() => this.closeTerm(), 140);
    },
    cancelTermClose() {
        clearTimeout(this.termCloseTimer);
    },
    closeTerm() {
        this.cancelTermClose();
        this.activeTerm = '';
        this.activeDescription = '';
    },
    close() {
        this.open = false;
        this.error = '';
    },
    closeAll() {
        this.close();
        this.closeTerm();
    },
    async ask() {
        if (this.loading || this.question.trim() === '') return;
        this.loading = true;
        this.error = '';
        const askedQuestion = this.question.trim();
        try {
            const response = await fetch('/learning-ai/ask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ question_id: context.questionId, question: askedQuestion }),
            });
            const data = await response.json();
            if (!response.ok) {
                this.error = data.message || 'AI回答の生成に失敗しました。';
                if (typeof data.remaining === 'number') this.remaining = data.remaining;
                return;
            }
            this.messages.push({ id: Date.now(), role: 'あなた', text: askedQuestion }, { id: Date.now() + 1, role: 'AI', text: data.answer });
            this.question = '';
            this.remaining = data.remaining;
        } catch (_) {
            this.error = '通信に失敗しました。時間をおいて再度お試しください。';
        } finally {
            this.loading = false;
        }
    },
}));

registerPwaInstall(Alpine);
Alpine.start();
registerServiceWorker();
createIcons({ icons: dashboardIcons });
