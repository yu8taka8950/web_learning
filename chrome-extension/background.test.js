import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';
import { webcrypto } from 'node:crypto';

const backgroundSource = await readFile(new URL('./background.js', import.meta.url), 'utf8');
const baseUrlSource = await readFile(new URL('./base-url.js', import.meta.url), 'utf8');
const contentSource = await readFile(new URL('./content.js', import.meta.url), 'utf8');
const reviewSource = await readFile(new URL('./review.js', import.meta.url), 'utf8');
const manifestSource = await readFile(new URL('./manifest.json', import.meta.url), 'utf8');

test('a valid stored token enables Learning Mode without starting pairing', async () => {
    const harness = createHarness({ captureAccessToken: 'valid-token', learningMode: false }, [new Response(null, { status: 204 })]);

    const result = await harness.run('enableLearningMode()');

    assert.equal(result.success, true);
    assert.equal(result.enabled, true);
    assert.equal(result.pairingStarted, false);
    assert.equal(harness.local.captureAccessToken, 'valid-token');
    assert.equal(harness.local.learningMode, true);
    assert.equal(harness.createdTabs.length, 0);
});

test('a missing token starts pairing and stores restart-safe pending state', async () => {
    const connectUrl = 'http://192.168.16.240/web_learning/extension/connect/pairing#pairing_secret=secret';
    const harness = createHarness({}, [jsonResponse({ connect_url: connectUrl }, 201)]);

    const result = await harness.run('enableLearningMode()');

    assert.equal(result.success, true);
    assert.equal(result.enabled, false);
    assert.equal(result.pairingStarted, true);
    assert.equal(harness.local.learningMode, undefined);
    assert.equal(harness.createdTabs[0].url, 'about:blank');
    assert.equal(harness.createdTabs[0].active, false);
    assert.equal(harness.updatedTabs[0].options.url, connectUrl);
    assert.equal(harness.updatedTabs[0].options.active, false);
    assert.equal(harness.fetchRequests[0].input, 'http://192.168.16.240/web_learning/extension/pairings');
    assert.equal(harness.session.pendingPairing.pairingId.length, 64);
    assert.equal(harness.session.pendingPairing.pairingSecret.length, 64);
    assert.ok(harness.alarms.has('web-learning-pairing-claim'));
});

test('an invalid token is removed and ON starts a fresh pairing', async () => {
    const harness = createHarness(
        { captureAccessToken: 'revoked-token', learningMode: false },
        [new Response(null, { status: 401 }), jsonResponse({ connect_url: 'http://192.168.16.240/web_learning/connect' }, 201)],
    );

    const result = await harness.run('enableLearningMode()');

    assert.equal(result.pairingStarted, true);
    assert.equal(harness.local.captureAccessToken, '');
    assert.equal(harness.local.learningMode, false);
    assert.ok(harness.session.pendingPairing);
});

test('pairing claim stores the token and enables Learning Mode after the popup has gone', async () => {
    const harness = createHarness({}, [jsonResponse({ access_token: 'claimed-token' })], {
        pendingPairing: {
            pairingId: 'a'.repeat(64),
            pairingSecret: 'b'.repeat(64),
            pairingTabId: 9,
            expiresAt: Date.now() + 60_000,
        },
    });

    await harness.run('claimPendingPairing()');

    assert.equal(harness.local.captureAccessToken, 'claimed-token');
    assert.equal(harness.local.learningMode, true);
    assert.equal(harness.session.pendingPairing, undefined);
    assert.equal(harness.alarms.has('web-learning-pairing-claim'), false);
    assert.deepEqual(harness.removedTabs, [9]);
});

test('claim failure closes the pairing tab and reports the error', async () => {
    const harness = createHarness({}, [new Response(null, { status: 500 })], {
        pendingPairing: {
            pairingId: 'a'.repeat(64),
            pairingSecret: 'b'.repeat(64),
            pairingTabId: 9,
            expiresAt: Date.now() + 60_000,
        },
    });

    await harness.run('claimPendingPairing()');

    assert.deepEqual(harness.removedTabs, [9]);
    assert.equal(harness.session.pendingPairing, undefined);
    assert.equal(harness.local.learningMode, false);
    assert.match(harness.local.pairingError, /接続を完了できませんでした/);
});

test('an expired pairing closes its temporary tab', async () => {
    const harness = createHarness({}, [], {
        pendingPairing: {
            pairingId: 'a'.repeat(64),
            pairingSecret: 'b'.repeat(64),
            pairingTabId: 9,
            expiresAt: Date.now() - 1,
        },
    });

    await harness.run('claimPendingPairing()');

    assert.deepEqual(harness.removedTabs, [9]);
    assert.equal(harness.session.pendingPairing, undefined);
    assert.match(harness.local.pairingError, /有効期限が切れました/);
});

test('only the pairing tab is focused when it reaches the Web Learning login page', async () => {
    const harness = createHarness({}, [], {
        pendingPairing: { pairingTabId: 9, expiresAt: Date.now() + 60_000 },
    });

    await harness.run("focusPairingLogin(9, 'http://192.168.16.240/web_learning/login')");
    await harness.run("focusPairingLogin(8, 'http://192.168.16.240/web_learning/login')");

    assert.equal(JSON.stringify(harness.updatedTabs), JSON.stringify([{ tabId: 9, options: { active: true } }]));
});

test('account mismatch reconnection starts pairing even while an old token is valid', async () => {
    const connectUrl = 'http://192.168.16.240/web_learning/extension/connect/reconnect#pairing_secret=secret';
    const harness = createHarness({ captureAccessToken: 'old-user-token', learningMode: true }, [jsonResponse({ connect_url: connectUrl }, 201)]);

    await harness.run('startPairing()');

    assert.equal(harness.local.captureAccessToken, 'old-user-token');
    assert.equal(harness.local.learningMode, true);
    assert.equal(harness.updatedTabs[0].options.url, connectUrl);
    assert.equal(harness.updatedTabs[0].options.active, false);
    assert.ok(harness.session.pendingPairing);
});

test('a trusted mismatch page message starts pairing', async () => {
    const connectUrl = 'http://192.168.16.240/web_learning/extension/connect/reconnect#pairing_secret=secret';
    const harness = createHarness({}, [jsonResponse({ connect_url: connectUrl }, 201)]);

    const result = await harness.sendRuntimeMessage(
        { action: 'reconnectExtension' },
        { url: 'http://192.168.16.240/web_learning/extension-quiz-drafts/draft-token/quiz' },
    );

    assert.equal(result.success, true);
    assert.equal(harness.updatedTabs[0].options.url, connectUrl);
    assert.equal(harness.updatedTabs[0].options.active, false);
});

test('a repeated reconnection reopens the active pairing approval page', async () => {
    const connectUrl = 'http://192.168.16.240/web_learning/extension/connect/reconnect#pairing_secret=secret';
    const harness = createHarness({}, [], {
        pendingPairing: {
            pairingId: 'a'.repeat(64),
            pairingSecret: 'b'.repeat(64),
            connectUrl,
            expiresAt: Date.now() + 60_000,
        },
    });

    const result = await harness.sendRuntimeMessage(
        { action: 'reconnectExtension' },
        { url: 'http://192.168.16.240/web_learning/extension-quiz-drafts/draft-token/quiz' },
    );

    assert.equal(result.success, true);
    assert.equal(harness.createdTabs[0].url, 'about:blank');
    assert.equal(harness.updatedTabs[0].options.url, connectUrl);
    assert.equal(harness.fetchRequests.length, 0);
});

test('a normal page cannot start a reconnection', async () => {
    const harness = createHarness();

    const result = await harness.sendRuntimeMessage(
        { action: 'reconnectExtension' },
        { url: 'http://192.168.16.240/web_learning/dashboard' },
    );

    assert.equal(result.success, false);
    assert.equal(harness.fetchRequests.length, 0);
});

test('claiming a replacement token preserves learning state and revokes only the previous token', async () => {
    const harness = createHarness(
        { captureAccessToken: 'old-user-token', learningMode: true, excludedHosts: ['example.com'] },
        [jsonResponse({ access_token: 'new-user-token' }), new Response(null, { status: 204 })],
        {
            pendingPairing: {
                pairingId: 'a'.repeat(64),
                pairingSecret: 'b'.repeat(64),
                expiresAt: Date.now() + 60_000,
            },
        },
    );

    await harness.run('claimPendingPairing()');

    assert.equal(harness.local.captureAccessToken, 'new-user-token');
    assert.equal(harness.local.learningMode, true);
    assert.deepEqual(harness.local.excludedHosts, ['example.com']);
    assert.equal(harness.fetchRequests[1].options.method, 'DELETE');
    assert.equal(harness.fetchRequests[1].options.headers.Authorization, 'Bearer old-user-token');
});

test('claiming a replacement token returns the mismatch tab to learning candidates', async () => {
    const harness = createHarness(
        { latestAnalysis: { url: 'https://example.com/article' } },
        [jsonResponse({ access_token: 'new-user-token' })],
        {
            pendingPairing: {
                pairingId: 'a'.repeat(64),
                pairingSecret: 'b'.repeat(64),
                returnToReviewTabId: 42,
                expiresAt: Date.now() + 60_000,
            },
        },
    );

    await harness.run('claimPendingPairing()');

    assert.equal(harness.updatedTabs[0].tabId, 42);
    assert.equal(harness.updatedTabs[0].options.url, 'chrome-extension://test/review.html?source=https%3A%2F%2Fexample.com%2Farticle');
});

test('the localhost mismatch page bridges only its own reconnect request', async () => {
    const harness = createContentHarness();

    await harness.dispatchMessage({
        source: harness.window,
        origin: 'http://192.168.16.240',
        data: { type: 'web-learning:reconnect-extension', requestId: 'request-id' },
    });

    assert.deepEqual(Array.from(harness.runtimeMessages, (message) => message.action), ['getAppUrl', 'reconnectExtension']);
    assert.equal(JSON.stringify(harness.postedMessages[0].message), JSON.stringify({
        type: 'web-learning:reconnect-extension-result',
        requestId: 'request-id',
        success: true,
        message: '',
    }));
    assert.equal(harness.postedMessages[0].origin, 'http://192.168.16.240');
});

test('the mismatch bridge rejects a postMessage from another origin', async () => {
    const harness = createContentHarness();

    await harness.dispatchMessage({
        source: harness.window,
        origin: 'https://attacker.example',
        data: { type: 'web-learning:reconnect-extension', requestId: 'request-id' },
    });

    assert.equal(harness.runtimeMessages.length, 0);
    assert.equal(harness.postedMessages.length, 0);
});

test('the mismatch bridge does not run from a normal Web Learning page', async () => {
    const harness = createContentHarness({ mismatchPage: false });

    await harness.dispatchMessage({
        source: harness.window,
        origin: 'http://192.168.16.240',
        data: { type: 'web-learning:reconnect-extension', requestId: 'request-id' },
    });

    assert.equal(harness.runtimeMessages.length, 0);
    assert.equal(harness.postedMessages.length, 0);
});

test('the manifest explicitly injects the bridge on localhost', () => {
    assert.equal(manifestSource.includes('"http://localhost/*"'), true);
    assert.equal(manifestSource.includes('"storage"'), true);
    assert.equal(manifestSource.includes('"tabs"'), true);
    assert.equal(manifestSource.includes('"alarms"'), true);
});

test('the default Base URL normalizes paths and generates every API endpoint', async () => {
    const harness = createHarness({ webLearningBaseUrl: 'http://192.168.16.240/web_learning/' });

    assert.equal(await harness.run('WebLearningBaseUrl.getBaseUrl()'), 'http://192.168.16.240/web_learning');
    assert.equal(await harness.run("WebLearningBaseUrl.getApiUrl('/extension/analyze-page/')"), 'http://192.168.16.240/web_learning/extension/analyze-page');
    assert.equal(await harness.run("WebLearningBaseUrl.getApiUrl('extension/captures')"), 'http://192.168.16.240/web_learning/extension/captures');
    assert.equal(await harness.run("WebLearningBaseUrl.getApiUrl('extension/generate-quiz')"), 'http://192.168.16.240/web_learning/extension/generate-quiz');
    assert.equal(await harness.run("WebLearningBaseUrl.normalizeBaseUrl('javascript:alert(1)')"), null);
    assert.equal(await harness.run("WebLearningBaseUrl.normalizeBaseUrl('file:///tmp/web-learning')"), null);
});

test('the vm12 default Base URL remains localhost', async () => {
    const harness = createHarness();

    assert.equal(await harness.run('WebLearningBaseUrl.DEFAULT_BASE_URL'), 'http://localhost/web_learning');
});

test('review quiz generation requests its URL from the shared Base URL manager', () => {
    assert.equal(reviewSource.includes('http://localhost/web_learning'), false);
    assert.equal(reviewSource.includes("action: 'getApiUrl'"), true);
});

test('changing Base URL clears old server credentials and account state', async () => {
    const harness = createHarness({
        webLearningBaseUrl: 'http://192.168.16.240/web_learning',
        webLearningCredentialBaseUrl: 'http://192.168.16.240/web_learning',
        captureAccessToken: 'old-server-token',
        pairingError: 'old error',
        usageLimit: { code: 'usage_limit_reached' },
        pageAnalyses: { page: { terms: [] } },
    }, [], { pendingPairing: { pairingId: 'a'.repeat(64) } });

    await harness.chrome.storage.local.set({ webLearningBaseUrl: 'https://example.com/' });
    await harness.run('WebLearningBaseUrl.getBaseUrl()');

    assert.equal(harness.local.captureAccessToken, undefined);
    assert.equal(harness.local.pageAnalyses, undefined);
    assert.equal(harness.local.webLearningCredentialBaseUrl, 'https://example.com');
    assert.equal(harness.session.pendingPairing, undefined);
});

test('turning Learning Mode off keeps the stored access token and excluded hosts', async () => {
    const harness = createHarness({ captureAccessToken: 'valid-token', learningMode: true, excludedHosts: ['example.com'] });

    await harness.chrome.storage.local.set({ learningMode: false });

    assert.equal(harness.local.captureAccessToken, 'valid-token');
    assert.deepEqual(harness.local.excludedHosts, ['example.com']);
});

test('explicit disconnect revokes connection state and removes account-scoped analysis data', async () => {
    const harness = createHarness({
        captureAccessToken: 'valid-token',
        learningMode: true,
        excludedHosts: ['example.com'],
        pageAnalyses: { page: { terms: [{ term: 'DNS' }] } },
    }, [new Response(null, { status: 204 })]);

    await harness.run('disconnectExtension()');

    assert.equal(harness.local.captureAccessToken, '');
    assert.equal(harness.local.learningMode, false);
    assert.deepEqual(harness.local.excludedHosts, ['example.com']);
    assert.equal(harness.local.pageAnalyses, undefined);
});

test('popup has no primary connection section and keeps disconnect in detailed settings', async () => {
    const popupHtml = await readFile(new URL('./popup.html', import.meta.url), 'utf8');
    const popupScript = await readFile(new URL('./popup.js', import.meta.url), 'utf8');
    const popupCss = await readFile(new URL('./popup.css', import.meta.url), 'utf8');

    assert.equal(popupHtml.includes('Web Learningとの接続</h1>'), false);
    assert.equal(popupHtml.includes('Web Learningと接続する'), false);
    assert.equal(popupHtml.includes('<summary>詳細設定</summary>'), true);
    assert.equal(popupHtml.includes('Web Learningとの接続を解除'), true);
    assert.equal(popupScript.includes('siteModeControls.hidden = !learningMode'), true);
    assert.equal(popupCss.includes('html {\n    width: 360px;'), true);
    assert.equal(popupCss.includes('min-width: 360px;'), true);
    assert.equal(popupCss.includes('-webkit-line-clamp: 2;'), true);
    assert.equal(popupCss.includes('.site-mode-controls .text-button'), true);
});

function createHarness(localState = {}, fetchResponses = [], sessionState = {}) {
    const local = { webLearningBaseUrl: 'http://192.168.16.240/web_learning', webLearningCredentialBaseUrl: 'http://192.168.16.240/web_learning', ...localState };
    const session = { ...sessionState };
    const alarms = new Map();
    const createdTabs = [];
    const updatedTabs = [];
    const removedTabs = [];
    let nextTabId = 1;
    const fetchRequests = [];
    let runtimeMessageListener;
    const event = { addListener() {} };
    const chrome = {
        runtime: {
            getURL(path) { return `chrome-extension://test/${path}`; },
            onMessage: { addListener(listener) { runtimeMessageListener = listener; } },
            onInstalled: event,
            onStartup: event,
        },
        tabs: {
            onActivated: event,
            onUpdated: event,
            async query() { return []; },
            async create(options) {
                const tab = { id: nextTabId++, ...options };
                createdTabs.push(tab);
                return tab;
            },
            async update(tabId, options) { updatedTabs.push({ tabId, options }); return options; },
            async remove(tabId) { removedTabs.push(tabId); },
        },
        alarms: {
            onAlarm: event,
            async create(name, options) { alarms.set(name, options); },
            async clear(name) { return alarms.delete(name); },
        },
        notifications: { onClicked: event, onButtonClicked: event },
        storage: {
            local: storageArea(local),
            session: storageArea(session),
        },
        scripting: { async executeScript() {} },
    };
    const responses = [...fetchResponses];
    const context = vm.createContext({
        chrome,
        console,
        crypto: webcrypto,
        Date,
        Error,
        fetch: async (input, options = {}) => {
            fetchRequests.push({ input, options });
            const response = responses.shift();
            assert.ok(response, 'Unexpected fetch call');
            return response;
        },
        Map,
        Promise,
        Set,
        URL,
        importScripts(...sources) {
            for (const source of sources) {
                assert.equal(source, 'base-url.js');
                vm.runInContext(baseUrlSource, context);
            }
        },
    });
    vm.runInContext(backgroundSource, context);

    return {
        alarms,
        chrome,
        createdTabs,
        updatedTabs,
        fetchRequests,
        local,
        removedTabs,
        session,
        run: (source) => vm.runInContext(source, context),
        sendRuntimeMessage: async (request, sender) => new Promise((resolve) => {
            runtimeMessageListener(request, sender, resolve);
        }),
    };
}

function createContentHarness({ mismatchPage = true } = {}) {
    const messageListeners = [];
    const postedMessages = [];
    const runtimeMessages = [];
    const window = {
        location: {
            origin: 'http://192.168.16.240',
            pathname: mismatchPage ? '/web_learning/extension-quiz-drafts/draft-token/quiz' : '/web_learning/dashboard',
            href: 'http://192.168.16.240/web_learning/extension-quiz-drafts/draft-token/quiz',
        },
        addEventListener(type, listener) {
            if (type === 'message') {
                messageListeners.push(listener);
            }
        },
        postMessage(message, origin) {
            postedMessages.push({ message, origin });
        },
    };
    const chrome = {
        runtime: {
            async sendMessage(message) {
                runtimeMessages.push(message);

                if (message.action === 'getAppUrl') {
                    return { url: 'http://192.168.16.240/web_learning' };
                }

                return { success: true };
            },
            onMessage: { addListener() {} },
        },
        storage: {
            local: { get(_keys, callback) { callback({ learningMode: false, excludedHosts: [] }); } },
            onChanged: { addListener() {} },
        },
    };
    const document = {
        title: 'Web Learning',
        documentElement: {},
        querySelector(selector) {
            return mismatchPage && selector === '[data-extension-account-mismatch]' ? {} : null;
        },
        querySelectorAll() { return []; },
    };
    const context = vm.createContext({
        chrome,
        console,
        document,
        IntersectionObserver: class { observe() {} },
        MutationObserver: class { observe() {} },
        Node: { ELEMENT_NODE: 1 },
        location: { ...window.location, hostname: '192.168.16.240' },
        Set,
        URL,
        WeakSet,
        window,
    });
    vm.runInContext(contentSource, context);

    return {
        postedMessages,
        runtimeMessages,
        window,
        async dispatchMessage(event) {
            messageListeners.forEach((listener) => listener(event));
            await new Promise((resolve) => setImmediate(resolve));
        },
    };
}

function storageArea(state) {
    return {
        async get(keys) {
            return Object.fromEntries(keys.map((key) => [key, state[key]]));
        },
        async set(values) {
            Object.assign(state, values);
        },
        async remove(keys) {
            keys.forEach((key) => delete state[key]);
        },
    };
}

function jsonResponse(body, status = 200) {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}
