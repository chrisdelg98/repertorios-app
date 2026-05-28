import { ref } from 'vue';

const deferredPrompt = ref(null);
const isInstallable  = ref(false);
const isInstalled    = ref(false);

let bootstrapped = false;

export function useInstall() {
    if (!bootstrapped && typeof window !== 'undefined') {
        bootstrapped = true;

        isInstalled.value = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;

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

    async function promptInstall() {
        // Try native <install> element first (progressive enhancement)
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
