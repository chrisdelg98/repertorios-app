/* eslint-env serviceworker */
/**
 * Push handling, imported by the service worker that vite-plugin-pwa generates.
 *
 * It lives in its own file, outside the build, so the generated worker keeps
 * its precaching and auto-update exactly as they are — see `workbox.importScripts`
 * in vite.config.js. Editing this file does not require a rebuild, but it does
 * change the worker's bytes, so browsers pick it up on the next update check.
 */

self.addEventListener('push', (event) => {
    let data = {};

    try {
        data = event.data ? event.data.json() : {};
    } catch {
        // A payload we cannot read is still worth surfacing rather than dropping.
        data = { body: event.data ? event.data.text() : '' };
    }

    const title = data.title || 'Repertorios';

    const options = {
        body: data.body || '',
        icon: data.icon || '/icons/icon-192x192.png',
        badge: data.badge || '/icons/icon-192x192.png',
        // Same tag replaces an earlier notification about the same thing
        // instead of stacking three copies of it.
        tag: data.tag || undefined,
        renotify: Boolean(data.tag),
        data: {
            url: data.url || '/dashboard',
            bandId: data.band_id ?? null,
        },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const target = event.notification.data?.url || '/dashboard';
    const absolute = new URL(target, self.location.origin).href;

    event.waitUntil((async () => {
        const clientList = await self.clients.matchAll({
            type: 'window',
            includeUncontrolled: true,
        });

        // Reuse a window that is already open — opening a second copy of an
        // installed PWA is disorienting.
        for (const client of clientList) {
            if (new URL(client.url).origin !== self.location.origin) continue;

            if ('navigate' in client) {
                const navigated = await client.navigate(absolute);
                return (navigated || client).focus();
            }

            return client.focus();
        }

        return self.clients.openWindow(absolute);
    })());
});
