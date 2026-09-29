(() => {
    const DEFAULT_BASE_URL = 'http://localhost/web_learning';
    const BASE_URL_STORAGE_KEY = 'webLearningBaseUrl';
    const CREDENTIAL_BASE_URL_STORAGE_KEY = 'webLearningCredentialBaseUrl';
    const ACCOUNT_SCOPED_KEYS = ['captureAccessToken', 'pairingError', 'usageLimit', 'pageAnalyses', 'latestAnalysis', 'notificationContexts'];

    function normalizeBaseUrl(value) {
        if (typeof value !== 'string' || value.trim() === '') {
            return null;
        }

        try {
            const url = new URL(value.trim());
            if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password || url.search || url.hash) {
                return null;
            }

            const path = url.pathname.replace(/\/+/gu, '/').replace(/\/$/u, '');

            return `${url.origin}${path}`;
        } catch {
            return null;
        }
    }

    async function synchronizeCredentialScope(baseUrl) {
        const { [CREDENTIAL_BASE_URL_STORAGE_KEY]: credentialBaseUrl, captureAccessToken = '' } = await chrome.storage.local.get([
            CREDENTIAL_BASE_URL_STORAGE_KEY,
            'captureAccessToken',
        ]);
        const normalizedCredentialBaseUrl = normalizeBaseUrl(credentialBaseUrl);

        if (normalizedCredentialBaseUrl !== baseUrl) {
            await chrome.storage.local.remove(ACCOUNT_SCOPED_KEYS);
            await chrome.storage.session.remove(['pendingPairing']);
            await chrome.storage.local.set({ [CREDENTIAL_BASE_URL_STORAGE_KEY]: baseUrl });

            return { changed: true, hadCredentials: Boolean(captureAccessToken || normalizedCredentialBaseUrl) };
        }

        return { changed: false, hadCredentials: false };
    }

    async function getBaseUrl() {
        const stored = await chrome.storage.local.get([BASE_URL_STORAGE_KEY]);
        const normalized = normalizeBaseUrl(stored[BASE_URL_STORAGE_KEY]) ?? DEFAULT_BASE_URL;

        if (stored[BASE_URL_STORAGE_KEY] !== normalized) {
            await chrome.storage.local.set({ [BASE_URL_STORAGE_KEY]: normalized });
        }

        await synchronizeCredentialScope(normalized);

        return normalized;
    }

    async function getApiUrl(path) {
        const baseUrl = await getBaseUrl();
        const normalizedPath = String(path).replace(/^\/+|\/+$/gu, '');

        return `${baseUrl}/${normalizedPath}`;
    }

    globalThis.WebLearningBaseUrl = {
        DEFAULT_BASE_URL,
        BASE_URL_STORAGE_KEY,
        CREDENTIAL_BASE_URL_STORAGE_KEY,
        getApiUrl,
        getBaseUrl,
        normalizeBaseUrl,
    };
})();
