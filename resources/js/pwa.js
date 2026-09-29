export function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const manifest = document.querySelector('link[rel="manifest"]');
    if (!manifest?.href) {
        return;
    }

    const manifestUrl = new URL(manifest.href);
    const serviceWorkerUrl = new URL('sw.js', manifestUrl);
    const scopeUrl = new URL('./', manifestUrl);

    const register = () => navigator.serviceWorker.register(serviceWorkerUrl, {
        scope: scopeUrl.pathname,
    }).catch((error) => {
        console.error('Service Worker registration failed.', error);
    });

    if (document.readyState === 'complete') {
        register();

        return;
    }

    window.addEventListener('load', register, { once: true });
}

export function registerPwaInstall(Alpine) {
    Alpine.data('pwaInstall', () => ({
        canInstall: false,
        installPrompt: null,
        isIos: false,
        isStandalone: false,
        init() {
            this.isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            this.isIos = /iPad|iPhone|iPod/u.test(window.navigator.userAgent) && !window.MSStream;

            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                this.installPrompt = event;
                this.canInstall = true;
            });

            window.addEventListener('appinstalled', () => {
                this.canInstall = false;
                this.installPrompt = null;
                this.isStandalone = true;
            });
        },
        async install() {
            if (!this.installPrompt) {
                return;
            }

            await this.installPrompt.prompt();
            const { outcome } = await this.installPrompt.userChoice;

            if (outcome === 'accepted') {
                this.canInstall = false;
                this.installPrompt = null;
            }
        },
    }));
}
