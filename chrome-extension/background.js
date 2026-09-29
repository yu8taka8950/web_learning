importScripts('base-url.js');
const PAIRING_ALARM_NAME = 'web-learning-pairing-claim';
const PAIRING_LIFETIME_MILLISECONDS = 5 * 60 * 1000;
const PAIRING_RETRY_MILLISECONDS = 2000;
const MAXIMUM_CONTENT_LENGTH = 20000;
const NOTIFICATION_CONTEXT_MAXIMUM_AGE = 7 * 24 * 60 * 60 * 1000;
const NOTIFICATION_CONTEXT_LIMIT = 100;
const inFlightAnalyses = new Map();
const openingNotifications = new Set();

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'getAppUrl' || request.action === 'getApiUrl') {
        const urlPromise = request.action === 'getAppUrl'
            ? WebLearningBaseUrl.getBaseUrl()
            : WebLearningBaseUrl.getApiUrl(request.path ?? '');
        urlPromise.then((url) => sendResponse({ url }));
        return true;
    }

    if (request.action === 'ensureContentScript') {
        ensureContentScript(request.tabId)
            .then(() => sendResponse({ success: true }))
            .catch((error) => sendResponse({ success: false, message: error.message }));
        return true;
    }

    if (request.action === 'enableLearningMode') {
        enableLearningMode().then(sendResponse).catch((error) => sendResponse({ success: false, message: error.message }));
        return true;
    }

    if (request.action === 'checkConnection') {
        checkConnection()
            .then((connected) => sendResponse({ connected }))
            .catch((error) => sendResponse({ connected: null, message: error.message }));
        return true;
    }

    if (request.action === 'disconnectExtension') {
        disconnectExtension().then(() => sendResponse({ success: true })).catch((error) => sendResponse({ success: false, message: error.message }));
        return true;
    }

    if (request.action === 'reconnectExtension') {
        isTrustedReconnectSender(sender).then((trusted) => {
            if (!trusted) {
                sendResponse({ success: false, message: 'このページからは接続を開始できません。学習候補のアカウント確認画面からもう一度お試しください。' });
                return;
            }

            startPairing(sender.tab?.id).then(() => sendResponse({ success: true })).catch((error) => sendResponse({ success: false, message: error.message }));
        });
        return true;
    }

    if (request.action !== 'autoAnalyze') {
        return;
    }

    const analysisKey = request.url ?? `${sender.tab?.id ?? 'unknown'}`;
    let analysis = inFlightAnalyses.get(analysisKey);

    if (!analysis) {
        analysis = analyzePage(request);
        inFlightAnalyses.set(analysisKey, analysis);
    }

    analysis
        .then((result) => sendResponse({ success: true, result }))
        .catch((error) => {
            console.error('[Web Learning] 自動解析エラー:', error);
            sendResponse({ success: false, message: error.message });
        })
        .finally(() => inFlightAnalyses.delete(analysisKey));

    return true;
});

chrome.tabs.onActivated.addListener(({ tabId }) => {
    void ensureContentScriptWhenLearning(tabId);
});

chrome.tabs.onUpdated.addListener((tabId, changeInfo) => {
    if (changeInfo.status === 'complete') {
        void ensureContentScriptWhenLearning(tabId);
    }

    if (changeInfo.url) {
        void focusPairingLogin(tabId, changeInfo.url);
    }
});

chrome.runtime.onInstalled.addListener(() => {
    void ensureOpenTabsWhenLearning();
    void resumePendingPairing();
});

chrome.runtime.onStartup.addListener(() => {
    void resumePendingPairing();
});

chrome.alarms.onAlarm.addListener((alarm) => {
    if (alarm.name === PAIRING_ALARM_NAME) {
        void claimPendingPairing();
    }
});

chrome.notifications.onClicked.addListener((notificationId) => {
    void openReviewFromNotification(notificationId);
});

chrome.notifications.onButtonClicked.addListener((notificationId, buttonIndex) => {
    if (buttonIndex === 0) {
        void openReviewFromNotification(notificationId);
    }
});

async function ensureOpenTabsWhenLearning() {
    const { learningMode = false } = await chrome.storage.local.get(['learningMode']);

    if (!learningMode) {
        return;
    }

    const tabs = await chrome.tabs.query({});
    await Promise.all(tabs.map((tab) => ensureContentScriptWhenLearning(tab.id)));
}

async function ensureContentScriptWhenLearning(tabId) {
    const { learningMode = false, excludedHosts = [] } = await chrome.storage.local.get(['learningMode', 'excludedHosts']);

    if (learningMode) {
        const tab = await chrome.tabs.get(tabId);
        if (!isHostExcluded(tab.url, excludedHosts)) {
            await ensureContentScript(tabId, tab.url);
        }
    }
}

async function ensureContentScript(tabId, knownUrl = null) {
    if (!tabId) {
        return;
    }

    const url = knownUrl ?? (await chrome.tabs.get(tabId)).url;

    if (!isSupportedPage(url)) {
        return;
    }

    try {
        await chrome.scripting.executeScript({
            target: { tabId },
            files: ['content.js'],
        });
    } catch (error) {
        // ページ遷移中など注入できない一時状態は次回の更新・タブ移動で再確認する。
        console.debug('[Web Learning] content.jsを注入できませんでした:', error.message);
    }
}

function isSupportedPage(url) {
    return typeof url === 'string' && (url.startsWith('http://') || url.startsWith('https://'));
}

async function analyzePage(data) {
    const { excludedHosts = [] } = await chrome.storage.local.get(['excludedHosts']);

    if (isHostExcluded(data.url, excludedHosts)) {
        return { terms: [] };
    }

    const content = (data.content ?? '').slice(0, MAXIMUM_CONTENT_LENGTH);

    if (content.length < 20) {
        throw new Error('解析対象の文章がありません。');
    }

    const accessToken = await getAccessToken();
    if (!accessToken) {
        throw new Error('先にWeb Learningと接続してください。');
    }
    const response = await fetch(await WebLearningBaseUrl.getApiUrl('extension/analyze-page'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${accessToken}`,
        },
        body: JSON.stringify({
            title: data.title,
            url: data.url,
            content,
        }),
    });

    if (!response.ok) {
        if (response.status === 401) {
            await invalidateConnection();
        }
        if (response.status === 429) {
            const limit = await response.json().catch(() => null);
            if (limit?.code === 'usage_limit_reached') {
                await chrome.storage.local.set({ usageLimit: limit });
                throw new Error(`今月のWeb解析${limit.limit}回を使い切りました。Plusなら月1,000回利用できます。`);
            }
        }
        throw new Error(`HTTP ${response.status}: ${await response.text()}`);
    }

    const result = await response.json();
    const { pageAnalyses = {} } = await chrome.storage.local.get(['pageAnalyses']);
    const previousAnalysis = pageAnalyses[data.url] ?? {
        url: data.url,
        title: data.title,
        terms: [],
        analyzedCharacters: 0,
        analysisCount: 0,
    };
    const newTerms = findNewTerms(previousAnalysis.terms, result.terms ?? []);
    const pageAnalysis = {
        ...previousAnalysis,
        url: data.url,
        title: data.title,
        terms: mergeTerms(previousAnalysis.terms, result.terms ?? []),
        analyzedCharacters: previousAnalysis.analyzedCharacters + content.length,
        analysisCount: previousAnalysis.analysisCount + 1,
        updatedAt: Date.now(),
    };

    pageAnalyses[data.url] = pageAnalysis;

    await chrome.storage.local.set({
        pageAnalyses,
        latestAnalysis: pageAnalysis,
    });

    // 同期の失敗は、通知やその場でのQuiz生成を妨げない補助機能として扱う。
    void syncCapture(pageAnalysis).catch((error) => console.warn('[Web Learning] Capture同期エラー:', error));

    const { learningMode = false } = await chrome.storage.local.get(['learningMode']);

    if (learningMode && newTerms.length > 0) {
        await createCandidateNotification(pageAnalysis, newTerms);
    }

    return result;
}

async function syncCapture(pageAnalysis) {
    const { captureAccessToken, excludedHosts = [] } = await chrome.storage.local.get(['captureAccessToken', 'excludedHosts']);
    if (isHostExcluded(pageAnalysis.url, excludedHosts)) {
        return;
    }
    if (!captureAccessToken || !pageAnalysis.terms?.length) {
        return;
    }
    const response = await fetch(await WebLearningBaseUrl.getApiUrl('extension/captures'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${captureAccessToken}` },
        body: JSON.stringify({ page_title: pageAnalysis.title, source_url: pageAnalysis.url, terms: pageAnalysis.terms.map((term) => ({ term: term.term, explanation: term.description })) }),
    });
    if (!response.ok) {
        if (response.status === 401) {
            await invalidateConnection();
        }
        throw new Error(`HTTP ${response.status}`);
    }
}

async function getAccessToken() {
    const { captureAccessToken = '' } = await chrome.storage.local.get(['captureAccessToken']);
    return captureAccessToken;
}

async function enableLearningMode() {
    const accessToken = await getAccessToken();

    if (accessToken) {
        const connection = await checkConnection();
        if (connection) {
            await chrome.storage.local.set({ learningMode: true, pairingError: '' });
            await ensureOpenTabsWhenLearning();
            return { success: true, enabled: true, pairingStarted: false };
        }
    }

    await startPairing();

    return { success: true, enabled: false, pairingStarted: true };
}

async function startPairing(returnToReviewTabId = null) {
    const { pendingPairing = null } = await chrome.storage.session.get(['pendingPairing']);
    if (pendingPairing?.expiresAt > Date.now()) {
        if (!Number.isInteger(pendingPairing.pairingTabId) && typeof pendingPairing.connectUrl === 'string') {
            const pairingTab = await chrome.tabs.create({ url: 'about:blank', active: false });
            await chrome.storage.session.set({ pendingPairing: { ...pendingPairing, pairingTabId: pairingTab.id } });
            await chrome.tabs.update(pairingTab.id, { url: pendingPairing.connectUrl, active: false });
        }
        await chrome.alarms.create(PAIRING_ALARM_NAME, { when: Date.now() + PAIRING_RETRY_MILLISECONDS });
        return;
    }

    const pairingId = crypto.getRandomValues(new Uint8Array(32)).reduce((value, byte) => value + byte.toString(16).padStart(2, '0'), '');
    const pairingSecret = crypto.getRandomValues(new Uint8Array(32)).reduce((value, byte) => value + byte.toString(16).padStart(2, '0'), '');
    let response;

    try {
        response = await fetch(await WebLearningBaseUrl.getApiUrl('extension/pairings'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ pairing_id: pairingId, pairing_secret: pairingSecret }),
        });
    } catch {
        throw new Error('Web Learningに接続できませんでした。Web Learningを開けることを確認して、もう一度「ONにする」を押してください。');
    }

    if (!response.ok) {
        throw new Error(pairingStartErrorMessage(response.status));
    }

    const { connect_url: connectUrl } = await response.json();
    const pairingTab = await chrome.tabs.create({ url: 'about:blank', active: false });
    await chrome.storage.session.set({
        pendingPairing: {
            pairingId,
            pairingSecret,
            connectUrl,
            pairingTabId: pairingTab.id,
            returnToReviewTabId,
            expiresAt: Date.now() + PAIRING_LIFETIME_MILLISECONDS,
        },
    });
    await chrome.storage.local.set({ pairingError: '' });
    await chrome.tabs.update(pairingTab.id, { url: connectUrl, active: false });
    await chrome.alarms.create(PAIRING_ALARM_NAME, { when: Date.now() + PAIRING_RETRY_MILLISECONDS });
}

async function focusPairingLogin(tabId, url) {
    const { pendingPairing = null } = await chrome.storage.session.get(['pendingPairing']);

    if (pendingPairing?.pairingTabId !== tabId || !await isLoginUrl(url)) {
        return;
    }

    try {
        await chrome.tabs.update(tabId, { active: true });
    } catch (error) {
        console.debug('[Web Learning] ログイン用タブを表示できませんでした:', error.message);
    }
}

async function isLoginUrl(url) {
    try {
        const appUrl = new URL(await WebLearningBaseUrl.getBaseUrl());
        const pageUrl = new URL(url);
        const appPath = appUrl.pathname.replace(/\/$/u, '');

        return pageUrl.origin === appUrl.origin && pageUrl.pathname === `${appPath}/login`;
    } catch {
        return false;
    }
}

async function isTrustedReconnectSender(sender) {
    try {
        const appUrl = new URL(await WebLearningBaseUrl.getBaseUrl());
        const senderUrl = new URL(sender.url ?? '');
        const appPath = appUrl.pathname.replace(/\/$/u, '');

        return senderUrl.origin === appUrl.origin
            && senderUrl.pathname.startsWith(`${appPath}/extension-quiz-drafts/`)
            && senderUrl.pathname.endsWith('/quiz');
    } catch {
        return false;
    }
}

async function resumePendingPairing() {
    const { pendingPairing = null } = await chrome.storage.session.get(['pendingPairing']);
    if (!pendingPairing) {
        return;
    }

    if (pendingPairing.expiresAt <= Date.now()) {
        await clearPendingPairing('接続の有効期限が切れました。もう一度「ONにする」を押してください。');
        return;
    }

    await chrome.alarms.create(PAIRING_ALARM_NAME, { when: Date.now() + PAIRING_RETRY_MILLISECONDS });
}

async function claimPendingPairing() {
    const { pendingPairing = null } = await chrome.storage.session.get(['pendingPairing']);
    if (!pendingPairing) {
        return;
    }

    if (pendingPairing.expiresAt <= Date.now()) {
        await clearPendingPairing('接続の有効期限が切れました。もう一度「ONにする」を押してください。');
        return;
    }

    let response;
    try {
        response = await fetch(await WebLearningBaseUrl.getApiUrl(`extension/pairings/${pendingPairing.pairingId}/claim`), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ pairing_secret: pendingPairing.pairingSecret }),
        });
    } catch {
        await chrome.alarms.create(PAIRING_ALARM_NAME, { when: Date.now() + PAIRING_RETRY_MILLISECONDS });
        return;
    }

    if (response.ok) {
        const { access_token: accessToken } = await response.json();
        const previousAccessToken = await getAccessToken();
        await chrome.storage.local.set({ captureAccessToken: accessToken, learningMode: true, pairingError: '' });
        if (previousAccessToken && previousAccessToken !== accessToken) {
            await clearAccountScopedData();
        }
        await closePairingTab(pendingPairing.pairingTabId);
        await chrome.storage.session.remove(['pendingPairing']);
        await chrome.alarms.clear(PAIRING_ALARM_NAME);
        if (previousAccessToken && previousAccessToken !== accessToken) {
            await revokeAccessToken(previousAccessToken);
        }
        await ensureOpenTabsWhenLearning();
        await returnToLearningCandidates(pendingPairing.returnToReviewTabId);
        return;
    }

    if (response.status === 404) {
        await chrome.alarms.create(PAIRING_ALARM_NAME, { when: Date.now() + PAIRING_RETRY_MILLISECONDS });
        return;
    }

    await clearPendingPairing('Web Learningとの接続を完了できませんでした。しばらくしてから、もう一度「ONにする」を押してください。');
}

async function closePairingTab(tabId) {
    if (!Number.isInteger(tabId)) {
        return;
    }

    try {
        await chrome.tabs.remove(tabId);
    } catch (error) {
        console.debug('[Web Learning] Pairing用タブを閉じられませんでした:', error.message);
    }
}

async function returnToLearningCandidates(tabId) {
    if (!Number.isInteger(tabId)) {
        return;
    }

    const { latestAnalysis = null } = await chrome.storage.local.get(['latestAnalysis']);
    if (typeof latestAnalysis?.url !== 'string') {
        return;
    }

    try {
        await chrome.tabs.update(tabId, {
            url: `${chrome.runtime.getURL('review.html')}?source=${encodeURIComponent(latestAnalysis.url)}`,
        });
    } catch (error) {
        console.debug('[Web Learning] 学習候補へ戻せませんでした:', error.message);
    }
}

async function clearPendingPairing(message) {
    const { pendingPairing = null } = await chrome.storage.session.get(['pendingPairing']);
    await closePairingTab(pendingPairing?.pairingTabId);
    await chrome.storage.session.remove(['pendingPairing']);
    await chrome.alarms.clear(PAIRING_ALARM_NAME);
    await chrome.storage.local.set({ learningMode: false, pairingError: message });
}

function pairingStartErrorMessage(status) {
    if (status === 429) {
        return '接続操作が続いています。少し待ってから、もう一度「ONにする」を押してください。';
    }

    if (status >= 500) {
        return 'Web Learningを一時的に利用できません。しばらくしてから、もう一度「ONにする」を押してください。';
    }

    return 'Web Learningとの接続を開始できませんでした。拡張機能を再読み込みして、もう一度「ONにする」を押してください。';
}

async function disconnectExtension() {
    const accessToken = await getAccessToken();
    if (accessToken) {
        const revoked = await revokeAccessToken(accessToken);
        if (!revoked) {
            throw new Error('接続の解除に失敗しました。');
        }
    }
    const { pendingPairing = null } = await chrome.storage.session.get(['pendingPairing']);
    await closePairingTab(pendingPairing?.pairingTabId);
    await chrome.storage.session.remove(['pendingPairing']);
    await chrome.alarms.clear(PAIRING_ALARM_NAME);
    await chrome.storage.local.set({ captureAccessToken: '', learningMode: false, pairingError: '' });
    await clearAccountScopedData();
}

async function revokeAccessToken(accessToken) {
    try {
        const response = await fetch(await WebLearningBaseUrl.getApiUrl('extension/connection'), { method: 'DELETE', headers: { Authorization: `Bearer ${accessToken}` } });

        return response.ok || response.status === 401;
    } catch {
        return false;
    }
}

async function checkConnection() {
    const accessToken = await getAccessToken();
    if (!accessToken) {
        return false;
    }

    let response;
    try {
        response = await fetch(await WebLearningBaseUrl.getApiUrl('extension/connection'), {
            headers: { Authorization: `Bearer ${accessToken}` },
        });
    } catch {
        throw new Error('Web Learningに接続できません。しばらくしてから、もう一度お試しください。');
    }

    if (response.ok) {
        return true;
    }

    if (response.status === 401) {
        await invalidateConnection();
        return false;
    }

    throw new Error('Web Learningに接続できません。しばらくしてから、もう一度お試しください。');
}

async function invalidateConnection() {
    const { pendingPairing = null } = await chrome.storage.session.get(['pendingPairing']);
    await closePairingTab(pendingPairing?.pairingTabId);
    await chrome.storage.local.set({ captureAccessToken: '', learningMode: false });
    await clearAccountScopedData();
}

async function clearAccountScopedData() {
    await chrome.storage.local.remove(['pageAnalyses', 'latestAnalysis', 'notificationContexts', 'usageLimit']);
    await chrome.storage.session.remove(['pendingPairing']);
}

function findNewTerms(existingTerms, incomingTerms) {
    const knownTerms = new Set(existingTerms.map((item) => normalizeTerm(item?.term)));
    const newTerms = [];

    incomingTerms.forEach((item) => {
        const term = (item?.term ?? '').trim();
        const normalizedTerm = normalizeTerm(term);

        if (!term || knownTerms.has(normalizedTerm)) {
            return;
        }

        knownTerms.add(normalizedTerm);
        newTerms.push({
            ...item,
            term,
            description: (item?.description ?? '').trim(),
        });
    });

    return newTerms;
}

function mergeTerms(existingTerms, incomingTerms) {
    const mergedTerms = [];
    const knownTerms = new Set();

    [...existingTerms, ...incomingTerms].forEach((item) => {
        const term = (item?.term ?? '').trim();
        const normalizedTerm = normalizeTerm(term);

        if (!term || knownTerms.has(normalizedTerm)) {
            return;
        }

        knownTerms.add(normalizedTerm);
        mergedTerms.push({
            ...item,
            term,
            description: (item?.description ?? '').trim(),
        });
    });

    return mergedTerms;
}

function normalizeTerm(term) {
    return (term ?? '').trim().toLocaleLowerCase();
}

async function createCandidateNotification(pageAnalysis, newTerms) {
    const { excludedHosts = [] } = await chrome.storage.local.get(['excludedHosts']);
    if (isHostExcluded(pageAnalysis.url, excludedHosts)) {
        return;
    }
    const notificationId = `web-learning-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    const { notificationContexts = {} } = await chrome.storage.local.get(['notificationContexts']);
    const now = Date.now();
    const activeContexts = Object.entries(notificationContexts)
        .filter(([, context]) => now - context.createdAt < NOTIFICATION_CONTEXT_MAXIMUM_AGE)
        .sort(([, firstContext], [, secondContext]) => secondContext.createdAt - firstContext.createdAt)
        .slice(0, NOTIFICATION_CONTEXT_LIMIT - 1);

    const nextNotificationContexts = Object.fromEntries(activeContexts);
    nextNotificationContexts[notificationId] = {
        url: pageAnalysis.url,
        title: pageAnalysis.title,
        createdAt: now,
    };

    await chrome.storage.local.set({ notificationContexts: nextNotificationContexts });

    const termNames = newTerms.slice(0, 3).map((item) => item.term);
    const suffix = newTerms.length > termNames.length ? ' など' : '';
    const message = `新しい学習候補を${newTerms.length}件見つけました\n${termNames.join(' / ')}${suffix}`;

    await chrome.notifications.create(notificationId, {
        type: 'basic',
        iconUrl: chrome.runtime.getURL('icons/web-learning-notification.png'),
        title: 'Web Learning',
        message,
        buttons: [
            {
                title: '学習候補を見る',
            },
        ],
    });
}

function isHostExcluded(url, excludedHosts = []) {
    try {
        return excludedHosts.includes(new URL(url).hostname);
    } catch {
        return false;
    }
}

async function openReviewFromNotification(notificationId) {
    if (openingNotifications.has(notificationId)) {
        return;
    }

    openingNotifications.add(notificationId);

    try {
        const { notificationContexts = {} } = await chrome.storage.local.get(['notificationContexts']);
        const context = notificationContexts[notificationId];

        if (!context?.url) {
            console.warn('Web Learning: notification context not found', notificationId);
            return;
        }

        try {
            await chrome.tabs.create({
                url: `${chrome.runtime.getURL('review.html')}?source=${encodeURIComponent(context.url)}`,
            });
        } catch (error) {
            console.error('Web Learning: failed to open review page from notification', {
                notificationId,
                error,
            });
            return;
        }

        delete notificationContexts[notificationId];
        await chrome.storage.local.set({ notificationContexts });
        await chrome.notifications.clear(notificationId);
    } finally {
        openingNotifications.delete(notificationId);
    }
}
