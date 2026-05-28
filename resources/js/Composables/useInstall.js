import { ref } from 'vue';

const deferredPrompt = ref(null);
const isInstallable  = ref(false);
const isInstalled    = ref(
    typeof window !== 'undefined'
        && (window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true)
);

if (typeof window !== 'undefined') {
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
    async function promptInstall() {
        const installEl = document.getElementById('pwa-install-el');
        if (installEl && !(installEl instanceof HTMLUnknownElement) && typeof installEl.install === 'function') {
            installEl.install();
            return true;
        }

        if (!deferredPrompt.value) return false;

        deferredPrompt.value.prompt();
        const { outcome } = await deferredPrompt.value.userChoice;
        deferredPrompt.value = null;
        isInstallable.value  = false;
        return outcome === 'accepted';
    }

    return { isInstallable, isInstalled, promptInstall };
}
