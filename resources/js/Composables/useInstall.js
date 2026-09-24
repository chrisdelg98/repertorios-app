import { computed, ref } from 'vue';

const deferredPrompt = ref(null);
const isInstallable  = ref(false);
const isInstalled    = ref(
    typeof window !== 'undefined'
        && (window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true)
);

const isIos       = ref(false);
const isIosSafari = ref(false);
const isAndroid   = ref(false);
const isChromium  = ref(false);

function detect() {
    if (typeof window === 'undefined') return;

    const ua = window.navigator.userAgent;

    isIos.value = /iPad|iPhone|iPod/.test(ua)
        || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);

    // Every iOS browser is Safari underneath, but only real Safari offers
    // "Add to Home Screen" — worth telling the user apart.
    isIosSafari.value = isIos.value
        && /Safari/.test(ua)
        && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);

    isAndroid.value = /Android/.test(ua);

    isChromium.value = /Chrome|Chromium|Edg/.test(ua) && !/OPR|SamsungBrowser/.test(ua);
}

if (typeof window !== 'undefined') {
    detect();

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
    /**
     * Which set of manual instructions applies. Browsers that genuinely cannot
     * install a PWA (desktop Firefox, for one) get 'unsupported' so we offer
     * nothing rather than steps that lead nowhere.
     */
    const platform = computed(() => {
        if (isIos.value) return 'ios';
        if (isAndroid.value) return isChromium.value ? 'android' : 'unsupported';
        if (isChromium.value) return 'desktop';
        return 'unsupported';
    });

    /**
     * Whether offering to install leads anywhere.
     *
     * Everywhere except iOS this means the browser actually handed us a prompt.
     * Offering manual steps when it did not is worse than offering nothing: the
     * usual reason there is no prompt is that the app is ALREADY installed, and
     * then the steps are busywork for something already done. iOS never fires a
     * prompt at all, so there the steps are the only path.
     */
    const canRequestInstall = computed(() =>
        isInstallable.value || (isIos.value && !isInstalled.value)
    );

    /**
     * Fires the browser's own install prompt.
     *
     * Returns false when there is no prompt to fire — the caller is expected to
     * show the manual instructions then. It deliberately does NOT fall back to
     * navigator.share(): that opens the OS "share a link" sheet, which has
     * nothing to do with installing and is baffling on desktop.
     */
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

    return {
        isInstallable,
        isInstalled,
        isIos,
        isIosSafari,
        isAndroid,
        platform,
        canRequestInstall,
        promptInstall,
    };
}
