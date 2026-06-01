import { computed, ref } from 'vue';

const deferredPrompt = ref(null);
const isInstallable  = ref(false);
const showManualInstallHelp = ref(false);
const isInstalled    = ref(
    typeof window !== 'undefined'
        && (window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true)
);
const isIosSafari = ref(false);
const canShareInstall = ref(false);

function detectIosSafari() {
    if (typeof window === 'undefined') return false;

    const ua = window.navigator.userAgent;
    const isIos = /iPad|iPhone|iPod/.test(ua)
        || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
    const isSafari = /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);

    return isIos && isSafari;
}

if (typeof window !== 'undefined') {
    isIosSafari.value = detectIosSafari();
    canShareInstall.value = typeof window.navigator.share === 'function';

    // Pick up the event captured by the inline script in app.blade.php
    if (window.__pwaPrompt) {
        deferredPrompt.value = window.__pwaPrompt;
        isInstallable.value  = true;
    }

    // Also listen for future firings (e.g. after app update)
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt.value = e;
        isInstallable.value  = true;
    });

    window.addEventListener('appinstalled', () => {
        deferredPrompt.value = null;
        isInstallable.value  = false;
        isInstalled.value    = true;
    });
}

export function useInstall() {
    const canRequestInstall = computed(() =>
        isInstallable.value || canShareInstall.value || isIosSafari.value
    );

    async function shareInstallInstructions() {
        showManualInstallHelp.value = true;

        if (!canShareInstall.value) return false;

        try {
            await window.navigator.share({
                title: 'Repertorios App',
                text: 'Agrega Repertorios App a tu pantalla de inicio para abrirla como una app.',
                url: window.location.origin,
            });
            return true;
        } catch {
            return false;
        }
    }

    async function promptInstall() {
        showManualInstallHelp.value = false;

        const installEl = document.getElementById('pwa-install-el');
        if (installEl && !(installEl instanceof HTMLUnknownElement) && typeof installEl.install === 'function') {
            installEl.install();
            return true;
        }

        if (!deferredPrompt.value) {
            return shareInstallInstructions();
        }

        deferredPrompt.value.prompt();
        const { outcome } = await deferredPrompt.value.userChoice;
        deferredPrompt.value = null;
        isInstallable.value  = false;
        return outcome === 'accepted';
    }

    return {
        isInstallable,
        isInstalled,
        isIosSafari,
        canRequestInstall,
        showManualInstallHelp,
        promptInstall,
    };
}
