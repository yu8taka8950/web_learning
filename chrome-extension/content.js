(() => {
    if (globalThis.__webLearningContentInitialized) {
        return;
    }

    globalThis.__webLearningContentInitialized = true;

    const AUTO_ANALYZE_THRESHOLD = 1000;
    const MAXIMUM_CONTENT_LENGTH = 20000;
    const MINIMUM_TEXT_LENGTH = 20;
    const TARGET_SELECTOR = 'p, h1, h2, h3, h4, li';

    let learningMode = false;
    let siteExcluded = false;
    let analyzing = false;
    let analyzedBlockIndex = 0;
    let failedAtBlockCount = null;
    const observedTexts = new Set();
    const observedElements = new WeakSet();
    const readTexts = [];

    window.addEventListener('message', (event) => {
        if (event.source !== window || event.origin !== window.location.origin || event.data?.type !== 'web-learning:reconnect-extension') {
            return;
        }

        void reconnectExtensionForCurrentPage(event.data.requestId);
    });

    async function reconnectExtensionForCurrentPage(requestId) {
        if (typeof requestId !== 'string' || !document.querySelector('[data-extension-account-mismatch]')) {
            return;
        }

        try {
            const app = await chrome.runtime.sendMessage({ action: 'getAppUrl' });
            const appUrl = new URL(app?.url ?? '');
            const appPath = appUrl.pathname.replace(/\/$/u, '');
            const isTrustedMismatchPage = window.location.origin === appUrl.origin
                && window.location.pathname.startsWith(`${appPath}/extension-quiz-drafts/`)
                && window.location.pathname.endsWith('/quiz');

            if (!isTrustedMismatchPage) {
                return;
            }

            const result = await chrome.runtime.sendMessage({ action: 'reconnectExtension' });
            sendReconnectResult(requestId, result?.success === true, result?.message ?? '');
        } catch {
            sendReconnectResult(requestId, false, 'Chrome拡張機能との通信を開始できませんでした。拡張機能を再読み込みして、もう一度お試しください。');
        }
    }

    function sendReconnectResult(requestId, success, message) {
        window.postMessage({
            type: 'web-learning:reconnect-extension-result',
            requestId,
            success,
            message,
        }, window.location.origin);
    }

    function normalizeText(text) {
        return (text ?? '').replace(/\s+/g, ' ').trim();
    }

    function getReadContent() {
        return readTexts.join('\n\n');
    }

    function getPendingAnalysis() {
        const blocks = [];
        let characterCount = 0;

        for (let index = analyzedBlockIndex; index < readTexts.length; index += 1) {
            const separatorLength = blocks.length > 0 ? 2 : 0;
            const remainingLength = MAXIMUM_CONTENT_LENGTH - characterCount - separatorLength;

            if (remainingLength <= 0) {
                break;
            }

            blocks.push(readTexts[index].slice(0, remainingLength));
            characterCount += separatorLength + blocks.at(-1).length;

            if (blocks.at(-1).length < readTexts[index].length) {
                break;
            }
        }

        return {
            content: blocks.join('\n\n'),
            snapshotBlockIndex: analyzedBlockIndex + blocks.length,
        };
    }

    function recordElement(element) {
        if (!learningMode || siteExcluded || observedElements.has(element)) {
            return;
        }

        const text = normalizeText(element.innerText);

        if (text.length < MINIMUM_TEXT_LENGTH || observedTexts.has(text)) {
            observedElements.add(element);
            return;
        }

        observedElements.add(element);
        observedTexts.add(text);
        readTexts.push(text);
        void requestAutoAnalysis();
    }

    async function requestAutoAnalysis() {
        const pendingAnalysis = getPendingAnalysis();

        if (
            !learningMode ||
            siteExcluded ||
            analyzing ||
            pendingAnalysis.content.length < AUTO_ANALYZE_THRESHOLD ||
            failedAtBlockCount === readTexts.length
        ) {
            return;
        }

        analyzing = true;

        try {
            const response = await chrome.runtime.sendMessage({
                action: 'autoAnalyze',
                title: document.title,
                url: location.href,
                content: pendingAnalysis.content,
            });

            if (!response?.success) {
                throw new Error(response?.message ?? '解析に失敗しました。');
            }

            analyzedBlockIndex = pendingAnalysis.snapshotBlockIndex;
            failedAtBlockCount = null;
            console.log('[Web Learning] 新規既読範囲の自動解析が完了しました。');
        } catch (error) {
            failedAtBlockCount = readTexts.length;
            console.error('[Web Learning] 自動解析エラー:', error);
        } finally {
            analyzing = false;
            void requestAutoAnalysis();
        }
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                recordElement(entry.target);
            }
        });
    }, { threshold: 0.2 });

    function observeElements(root = document) {
        root.querySelectorAll(TARGET_SELECTOR).forEach((element) => observer.observe(element));
    }

    function recordVisibleElements() {
        document.querySelectorAll(TARGET_SELECTOR).forEach((element) => {
            const rect = element.getBoundingClientRect();
            const visibleHeight = Math.max(0, Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0));

            if (rect.height > 0 && visibleHeight / rect.height >= 0.2) {
                recordElement(element);
            }
        });
    }

    chrome.storage.local.get(['learningMode', 'excludedHosts'], (result) => {
        learningMode = result.learningMode ?? false;
        siteExcluded = (result.excludedHosts ?? []).includes(location.hostname);

        if (learningMode && !siteExcluded) {
            recordVisibleElements();
        }
    });

    chrome.storage.onChanged.addListener((changes, areaName) => {
        if (areaName !== 'local' || (!changes.learningMode && !changes.excludedHosts)) {
            return;
        }

        if (changes.learningMode) {
            learningMode = changes.learningMode.newValue ?? false;
        }
        if (changes.excludedHosts) {
            siteExcluded = (changes.excludedHosts.newValue ?? []).includes(location.hostname);
        }

        if (learningMode && !siteExcluded) {
            recordVisibleElements();
        }
    });

    observeElements();

    new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }

                if (node.matches?.(TARGET_SELECTOR)) {
                    observer.observe(node);
                }

                observeElements(node);
            });
        });
    }).observe(document.documentElement, { childList: true, subtree: true });

    chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
        if (request.action === 'getReadContent') {
            const content = getReadContent();

            sendResponse({
                content,
                characterCount: content.length,
                blockCount: readTexts.length,
                analyzedBlockIndex,
                analyzing,
                title: document.title,
                url: location.href,
            });
            return;
        }

        if (request.action === 'clearReadContent') {
            observedTexts.clear();
            readTexts.length = 0;
            analyzing = false;
            analyzedBlockIndex = 0;
            failedAtBlockCount = null;
            sendResponse({ success: true });
        }
    });
})();
