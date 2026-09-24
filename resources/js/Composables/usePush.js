import { computed, ref } from 'vue';

/**
 * Web Push subscription for this browser.
 *
 * Reasons this can be unavailable, which the UI has to tell apart:
 *  - the browser has no Push API at all (desktop Safari before Ventura, Brave
 *    with shields up, private windows);
 *  - iOS, where PushManager only exists once the PWA is on the home screen —
 *    an ordinary Safari tab reports "unsupported" no matter the permission;
 *  - permission already denied, which no amount of asking will reopen. The
 *    browser will never prompt again; only the site settings can undo it.
 */

const permission = ref(
    typeof window !== 'undefined' && 'Notification' in window
        ? Notification.permission
        : 'unsupported'
);

const subscribed = ref(false);
const busy       = ref(false);
const lastError  = ref(null);

function supported() {
    return typeof window !== 'undefined'
        && 'serviceWorker' in navigator
        && 'PushManager' in window
        && 'Notification' in window;
}

/** The VAPID key travels as base64url text; subscribe() wants raw bytes. */
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from([...raw].map(char => char.charCodeAt(0)));
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function post(url, body, method = 'POST') {
    const response = await fetch(url, {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (!response.ok) throw new Error(`${method} ${url} → ${response.status}`);

    return response.json();
}

export function usePush(publicKey) {
    const isSupported = computed(() => supported());
    const isDenied    = computed(() => permission.value === 'denied');
    const isGranted   = computed(() => permission.value === 'granted');
    const canAsk      = computed(() => isSupported.value && permission.value === 'default');

    /** Reflects whether THIS browser already has a subscription. */
    async function refresh() {
        if (!isSupported.value) {
            subscribed.value = false;
            return false;
        }

        permission.value = Notification.permission;

        const registration = await navigator.serviceWorker.ready;
        const existing = await registration.pushManager.getSubscription();

        subscribed.value = !!existing;

        return subscribed.value;
    }

    /**
     * Ask for permission and register the device.
     *
     * Must be called straight from a click: browsers reject a permission
     * request that is not tied to a user gesture.
     */
    async function subscribe(locale = 'es') {
        lastError.value = null;

        if (!isSupported.value) {
            lastError.value = 'unsupported';
            return false;
        }

        if (!publicKey) {
            lastError.value = 'not_configured';
            return false;
        }

        busy.value = true;

        try {
            const result = await Notification.requestPermission();
            permission.value = result;

            if (result !== 'granted') {
                lastError.value = result === 'denied' ? 'denied' : 'dismissed';
                return false;
            }

            const registration = await navigator.serviceWorker.ready;

            // An existing subscription may carry a stale key after the app's
            // VAPID pair changes; replacing it is cheaper than diagnosing it.
            const existing = await registration.pushManager.getSubscription();
            if (existing) await existing.unsubscribe();

            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(publicKey),
            });

            const raw = subscription.toJSON();

            await post('/push/subscribe', {
                endpoint: raw.endpoint,
                keys: raw.keys,
                locale,
            });

            subscribed.value = true;

            return true;
        } catch (error) {
            lastError.value = error?.message ?? 'failed';
            return false;
        } finally {
            busy.value = false;
        }
    }

    /** Stop notifications on this browser. Other devices keep theirs. */
    async function unsubscribe() {
        if (!isSupported.value) return false;

        busy.value = true;

        try {
            const registration = await navigator.serviceWorker.ready;
            const existing = await registration.pushManager.getSubscription();

            if (existing) {
                await post('/push/subscribe', { endpoint: existing.endpoint }, 'DELETE');
                await existing.unsubscribe();
            }

            subscribed.value = false;

            return true;
        } catch (error) {
            lastError.value = error?.message ?? 'failed';
            return false;
        } finally {
            busy.value = false;
        }
    }

    return {
        permission,
        subscribed,
        busy,
        lastError,
        isSupported,
        isDenied,
        isGranted,
        canAsk,
        refresh,
        subscribe,
        unsubscribe,
    };
}
