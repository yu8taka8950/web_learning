const status = document.getElementById('status');
const toggleButton = document.getElementById('toggleButton');
const pageTitle = document.getElementById('pageTitle');
const pageUrl = document.getElementById('pageUrl');
const candidateCount = document.getElementById('candidateCount');
const message = document.getElementById('message');
const emptyMessage = document.getElementById('emptyMessage');
const modeDescription = document.getElementById('modeDescription');
const appLink = document.getElementById('appLink');
const termsLink = document.getElementById('termsLink');
const privacyLink = document.getElementById('privacyLink');
const appLinkHeader = document.getElementById('appLinkHeader');
const capturesLink = document.getElementById('capturesLink');
const pricingLink = document.getElementById('pricingLink');
const disconnectButton = document.getElementById('disconnectButton');
const advancedSettings = document.getElementById('advancedSettings');
const reviewLink = document.getElementById('reviewLink');
const termsSection = document.getElementById('termsSection');
const siteModeControls = document.getElementById('siteModeControls');
const siteModeStatus = document.getElementById('siteModeStatus');
const siteHost = document.getElementById('siteHost');
const siteToggleButton = document.getElementById('siteToggleButton');

let currentPageUrl = '';
let currentHost = '';

async function initialize() {
    const { learningMode = false, captureAccessToken = '' } = await chrome.storage.local.get(['learningMode', 'captureAccessToken']);
    const connection = captureAccessToken
        ? await chrome.runtime.sendMessage({ action: 'checkConnection' })
        : { connected: false };
    const connected = connection?.connected === true;
    disconnectButton.hidden = !connected;
    advancedSettings.hidden = !connected;
    updateDisplay(connection?.connected === false ? false : learningMode);
    if (connection?.connected === null) {
        message.textContent = connection.message ?? 'Web Learningの接続を確認できませんでした。';
    }

    const { pendingPairing = null } = await chrome.storage.session.get(['pendingPairing']);
    if (pendingPairing?.expiresAt > Date.now()) {
        setPairingState(true);
    }
    await loadAppLink();
    await showUsageLimit();
    await loadCurrentPage(connection?.connected === false ? false : learningMode);
}

toggleButton.addEventListener('click', async () => {
    const { learningMode = false } = await chrome.storage.local.get(['learningMode']);
    if (learningMode) {
        await chrome.storage.local.set({ learningMode: false });
        updateDisplay(false);
        await loadCurrentPage(false);
        return;
    }

    setPairingState(true);
    message.textContent = '';
    const response = await chrome.runtime.sendMessage({ action: 'enableLearningMode' });
    if (!response?.success) {
        setPairingState(false);
        message.textContent = response?.message ?? 'Web Learningに接続できませんでした。もう一度「ONにする」を押してください。';
        return;
    }

    if (response.enabled) {
        setPairingState(false);
        updateDisplay(true);
        await loadCurrentPage(true);
        return;
    }

    message.textContent = '開いたWeb Learning画面で接続を承認してください。承認後、自動的にONになります。';
});

disconnectButton.addEventListener('click', async () => {
    const response = await chrome.runtime.sendMessage({ action: 'disconnectExtension' });
    if (!response?.success) {
        message.textContent = response?.message ?? '接続の解除に失敗しました。';
        return;
    }
    disconnectButton.hidden = true;
    advancedSettings.hidden = true;
    setPairingState(false);
    updateDisplay(false);
    await loadCurrentPage(false);
});

chrome.storage.onChanged.addListener((changes, areaName) => {
    if (areaName === 'local' && changes.captureAccessToken) {
        const connected = Boolean(changes.captureAccessToken.newValue);
        disconnectButton.hidden = !connected;
        advancedSettings.hidden = !connected;
    }

    if (areaName === 'local' && changes.learningMode) {
        const enabled = Boolean(changes.learningMode.newValue);
        setPairingState(false);
        updateDisplay(enabled);
        void loadCurrentPage(enabled);
    }

    if (areaName === 'local' && changes.pairingError?.newValue) {
        setPairingState(false);
        message.textContent = changes.pairingError.newValue;
    }
});

function setPairingState(pairing) {
    toggleButton.disabled = pairing;
    toggleButton.textContent = pairing ? '接続中…' : 'ONにする';
    toggleButton.setAttribute('aria-busy', String(pairing));
}

function updateDisplay(enabled) {
    status.textContent = enabled ? 'ON' : 'OFF';
    status.classList.toggle('is-on', enabled);
    toggleButton.classList.toggle('is-on', enabled);
    toggleButton.textContent = enabled ? 'OFFにする' : 'ONにする';
    toggleButton.setAttribute('aria-pressed', String(enabled));
    modeDescription.textContent = enabled
        ? '閲覧しながら学習候補を見つけています。'
        : 'オンにすると、閲覧した内容から学習候補を見つけます。';
    termsSection.hidden = !enabled;
}

async function loadAppLink() {
    try {
        const response = await chrome.runtime.sendMessage({ action: 'getAppUrl' });

        if (response?.url) {
            appLink.href = response.url;
            appLinkHeader.href = response.url;
            termsLink.href = `${response.url}/terms`;
            privacyLink.href = `${response.url}/privacy`;
            capturesLink.href = `${response.url}/captures`;
            pricingLink.href = `${response.url}/pricing`;
        }
    } catch (error) {
        console.debug('[Web Learning] アプリリンクを取得できませんでした:', error.message);
    }
}

async function showUsageLimit() {
    const { usageLimit = null } = await chrome.storage.local.get(['usageLimit']);

    if (usageLimit?.code === 'usage_limit_reached') {
        message.textContent = `今月のWeb解析${usageLimit.limit}回を使い切りました。Plusなら月1,000回利用できます。`;
        pricingLink.hidden = false;
    }
}

async function loadCurrentPage(learningMode) {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    if (!tab) {
        message.textContent = 'ページ情報を取得できませんでした。';
        return;
    }

    pageTitle.textContent = tab.title ?? 'タイトルなし';
    pageUrl.textContent = getHostname(tab.url ?? '');
    currentPageUrl = tab.url ?? '';
    currentHost = getHostname(currentPageUrl);
    await updateSiteModeDisplay();

    if (!isSupportedPage(currentPageUrl)) {
        message.textContent = 'このページでは学習できません。';
        siteModeControls.hidden = true;
        return;
    }

    if (learningMode) {
        const injection = await chrome.runtime.sendMessage({ action: 'ensureContentScript', tabId: tab.id });

        if (!injection?.success) {
            message.textContent = 'このページで学習機能を開始できませんでした。';
            return;
        }
    }

    try {
        const response = await chrome.tabs.sendMessage(tab.id, { action: 'getReadContent' });
        pageTitle.textContent = response.title ?? pageTitle.textContent;
        pageUrl.textContent = getHostname(response.url ?? currentPageUrl);
        currentPageUrl = response.url ?? currentPageUrl;
        currentHost = getHostname(currentPageUrl);
        message.textContent = response.analyzing ? '新しく読んだ範囲を解析中です。' : '';
    } catch (error) {
        console.error('[Web Learning] 既読情報の取得エラー:', error);
        message.textContent = learningMode ? 'このページで学習機能を開始できませんでした。' : '';
    }

    await displayPageAnalysis();
}

async function updateSiteModeDisplay() {
    if (!currentHost) {
        siteModeControls.hidden = true;
        return;
    }

    const { excludedHosts = [] } = await chrome.storage.local.get(['excludedHosts']);
    const excluded = excludedHosts.includes(currentHost);
    const { learningMode = false } = await chrome.storage.local.get(['learningMode']);
    siteModeControls.hidden = !learningMode;
    siteHost.textContent = `${currentHost}：${excluded ? '学習対象外' : '学習対象'}`;
    siteModeStatus.textContent = excluded ? 'このサイトでは学習しません。' : 'このサイトでは学習します。';
    siteToggleButton.textContent = excluded ? 'このサイトで学習する' : 'このサイトでは学習しない';
    siteToggleButton.setAttribute('aria-pressed', String(excluded));
}

siteToggleButton.addEventListener('click', async () => {
    if (!currentHost) {
        return;
    }

    const { excludedHosts = [] } = await chrome.storage.local.get(['excludedHosts']);
    const excluded = excludedHosts.includes(currentHost);
    const nextHosts = excluded
        ? excludedHosts.filter((host) => host !== currentHost)
        : [...new Set([...excludedHosts, currentHost])];

    await chrome.storage.local.set({ excludedHosts: nextHosts });
    await updateSiteModeDisplay();
    message.textContent = excluded ? 'このサイトを学習対象に戻しました。' : 'このサイトを学習対象外にしました。';
});

function isSupportedPage(url) {
    return url.startsWith('http://') || url.startsWith('https://');
}

async function displayPageAnalysis() {
    const { pageAnalyses = {} } = await chrome.storage.local.get(['pageAnalyses']);
    const pageAnalysis = pageAnalyses[currentPageUrl];
    const termList = pageAnalysis?.terms ?? [];

    candidateCount.textContent = formatNumber(termList.length);
    reviewLink.hidden = termList.length === 0;
    reviewLink.href = `${chrome.runtime.getURL('review.html')}?source=${encodeURIComponent(currentPageUrl)}`;
    emptyMessage.hidden = termList.length > 0;
}

function getHostname(url) {
    try {
        return new URL(url).hostname;
    } catch {
        return '';
    }
}

function formatNumber(value) {
    return new Intl.NumberFormat('ja-JP').format(value);
}

void initialize();
