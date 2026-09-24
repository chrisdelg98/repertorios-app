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

/**
 * Rejects if a promise takes too long.
 *
 * Every step of subscribing can stall without ever rejecting: the worker that
 * is never registered, and pushManager.subscribe(), which talks to the push
 * service (FCM and friends) and can sit there for minutes on a bad network.
 * A stalled step used to leave the button reading "Enabling..." forever.
 */
function withTimeout(promise, ms, label) {
    let timer;

    return Promise.race([
        Promise.resolve(promise).finally(() => clearTimeout(timer)),
        new Promise((_, reject) => {
            timer = setTimeout(() => reject(new Error(label)), ms);
        }),
    ]);
}

/**
 * The active service worker registration.
 *
 * `navigator.serviceWorker.ready` never settles when nothing is registered —
 * it does not reject, it hangs — so it must never be awaited on its own. This
 * app unregisters its worker on localhost by design (see app.js), which is
 * exactly the case that used to leave the button spinning forever.
 */
async function activeRegistration() {
    const existing = await navigator.serviceWorker.getRegistration();

    if (existing?.active) return existing;

    // A registration that exists but is not active yet WILL become active, so
    // wait properly. A phone cold-starting an installed app takes far longer
    // than a desktop, and cutting it short reported "no service worker" to
    // people whose worker was simply still booting.
    // With no registration at all, allow only a short grace for the one
    // app.js kicks off, then give up.
    return withTimeout(
        navigator.serviceWorker.ready,
        existing ? 25000 : 8000,
        'no_service_worker'
    );
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

    /**
     * Reconciles this browser's subscription with the server's record of it.
     *
     * A browser subscription and a row in our database are two separate things,
     * and they drift: the browser can hold one whose registration never reached
     * us — a failed request, a closed tab — and then the app cheerfully reports
     * "on" while the server has nobody to send to. Asking the browser alone is
     * asking the wrong side.
     *
     * So whatever the browser has is re-sent. The endpoint is unique, so this
     * is an upsert and costs one row either way, and it quietly repairs any
     * device that got out of step.
     */
    async function refresh(locale = 'es') {
        if (!isSupported.value) {
            subscribed.value = false;
            return false;
        }

        permission.value = Notification.permission;

        try {
            const registration = await activeRegistration();
            const existing = await registration.pushManager.getSubscription();

            if (!existing) {
                subscribed.value = false;
                return false;
            }

            const raw = existing.toJSON();

            await withTimeout(post('/push/subscribe', {
                endpoint: raw.endpoint,
                keys: raw.keys,
                locale,
            }), 15000, 'server_unreachable');

            subscribed.value = true;
        } catch (error) {
            // The browser may still hold a subscription, but if the server does
            // not know about it, nothing will ever arrive — so say it is off.
            console.error('[push] could not confirm subscription with the server', error);
            subscribed.value = false;
        }

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

            const registration = await activeRegistration();

            // An existing subscription may carry a stale key after the app's
            // VAPID pair changes; replacing it is cheaper than diagnosing it.
            const existing = await registration.pushManager.getSubscription();
            if (existing) await existing.unsubscribe();

            const subscription = await withTimeout(
                registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(publicKey),
                }),
                15000,
                'push_service_unreachable'
            );

            const raw = subscription.toJSON();

            await withTimeout(post('/push/subscribe', {
                endpoint: raw.endpoint,
                keys: raw.keys,
                locale,
            }), 15000, 'server_unreachable');

            subscribed.value = true;

            return true;
        } catch (error) {
            // Kept verbatim in the console: the label alone rarely says enough
            // when someone reports "it just spins".
            console.error('[push] subscribe failed', error);

            lastError.value = ['no_service_worker', 'push_service_unreachable', 'server_unreachable']
                .includes(error?.message)
                ? error.message
                : 'failed';

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
            const registration = await activeRegistration();
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
