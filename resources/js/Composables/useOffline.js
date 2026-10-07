import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * Whether there is really a connection, and how old what is on screen is.
 *
 * `navigator.onLine` is the wrong question. In the room this was built for
 * there is wifi — the sound desk's — and no internet, so the browser reports a
 * connection and requests hang until TCP gives up. The only honest answer is
 * to ask the server and give it a deadline.
 *
 * Nor is the server's timestamp enough on its own: a phone with a wrong clock
 * would read every page as stale. The timestamp says what is on screen was
 * last true; the probe says whether anything can be done about it.
 */

/** Long enough for a slow connection, short enough to beat a hanging one. */
const PROBE_TIMEOUT_MS = 3000;

/*
 * How often to ask again.
 *
 * Rarely while connected — each probe wakes the phone's radio for an answer
 * nobody is waiting for. Often while not, because the moment the connection
 * returns is the moment the banner is lying.
 */
const PROBE_WHILE_ONLINE_MS = 2 * 60 * 1000;
const PROBE_WHILE_OFFLINE_MS = 15 * 1000;

/** Excluded from the service worker's page cache, or it would answer itself. */
const PROBE_URL = '/up';

/** Must match the cacheName in vite.config.js. */
const PAGE_CACHE = 'pages-cache';

async function reachable() {
    if (typeof fetch !== 'function') return true;
    if (typeof navigator !== 'undefined' && navigator.onLine === false) return false;

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), PROBE_TIMEOUT_MS);

    try {
        const response = await fetch(PROBE_URL, {
            method: 'GET',
            cache: 'no-store',
            signal: controller.signal,
            credentials: 'omit',
        });

        return response.ok;
    } catch {
        return false;
    } finally {
        clearTimeout(timer);
    }
}

export function useOffline() {
    const page = usePage();
    const isOffline = ref(false);

    let timer = null;

    async function check() {
        isOffline.value = !(await reachable());
        schedule();
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(check, isOffline.value ? PROBE_WHILE_OFFLINE_MS : PROBE_WHILE_ONLINE_MS);
    }

    // Coming back to the app is the likeliest moment for the answer to have
    // changed, and it costs nothing to ask then.
    function onVisible() {
        if (document.visibilityState === 'visible') check();
    }

    onMounted(() => {
        check();
        window.addEventListener('online', check);
        window.addEventListener('offline', check);
        document.addEventListener('visibilitychange', onVisible);
    });

    onBeforeUnmount(() => {
        clearTimeout(timer);
        window.removeEventListener('online', check);
        window.removeEventListener('offline', check);
        document.removeEventListener('visibilitychange', onVisible);
    });

    const servedAt = computed(() => {
        const parsed = Date.parse(page.props.served_at ?? '');
        return Number.isNaN(parsed) ? null : new Date(parsed);
    });

    /** Rendered with the device's own clock, which is the one the reader trusts. */
    const servedAtLabel = computed(() => {
        if (!servedAt.value) return '';

        const sameDay = new Date().toDateString() === servedAt.value.toDateString();

        return servedAt.value.toLocaleString(undefined, sameDay
            ? { hour: 'numeric', minute: '2-digit' }
            : { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
    });

    return { isOffline, servedAtLabel };
}

/**
 * Pull a page into the service worker's cache without showing it.
 *
 * Both shapes of the same URL are fetched: the document, which is what a cold
 * launch asks for, and the Inertia payload, which is what a tap inside the app
 * asks for. Caching one and not the other leaves half the journey working.
 */
export async function cachePage(url) {
    if (typeof fetch !== 'function') return false;

    const results = await Promise.allSettled([
        fetch(url, { credentials: 'same-origin', headers: { Accept: 'text/html' } }),
        fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'text/html, application/xhtml+xml',
                'X-Inertia': 'true',
                // Without the version Inertia answers 409 to force a reload,
                // and a 409 is not cached — the in-app half of the journey
                // would be missing while the cold launch worked.
                'X-Inertia-Version': usePage().version ?? '',
            },
        }),
    ]);

    return results.every(r => r.status === 'fulfilled' && r.value.ok);
}

/**
 * Throw away every cached page.
 *
 * Cached pages belong to one band and one person. After switching bands or
 * signing out, serving them offline would show the wrong band's repertoire —
 * or someone else's — with no sign that anything was wrong.
 */
export async function forgetCachedPages() {
    if (typeof caches === 'undefined') return;

    try {
        await caches.delete(PAGE_CACHE);
    } catch {
        // Nothing to do about it, and nothing worth interrupting for.
    }
}

/**
 * When this page was last saved for offline use, or null if it was not.
 *
 * The document is the one that matters: it is what a cold launch asks for, and
 * without it nothing else in the cache can be reached. Its Date header is the
 * server's own, so it survives a device clock that is wrong.
 */
export async function pageCachedAt(url) {
    if (typeof caches === 'undefined') return null;

    try {
        const cache = await caches.open(PAGE_CACHE);
        const hit = await cache.match(new Request(url, { headers: { Accept: 'text/html' } }));

        if (!hit) return null;

        const stamp = Date.parse(hit.headers.get('date') ?? '');
        return Number.isNaN(stamp) ? new Date() : new Date(stamp);
    } catch {
        return null;
    }
}

/** Drop one page, both of its shapes, leaving the rest of the cache alone. */
export async function forgetPage(url) {
    if (typeof caches === 'undefined') return false;

    try {
        const cache = await caches.open(PAGE_CACHE);
        const keys = await cache.keys();
        const target = new URL(url, window.location.origin).pathname;

        await Promise.all(
            keys
                .filter(request => new URL(request.url).pathname === target)
                .map(request => cache.delete(request))
        );

        return true;
    } catch {
        return false;
    }
}
