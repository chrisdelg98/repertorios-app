import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import i18n from '@/i18n/index.js';

// Skip service worker on localhost to avoid stale chunk issues during development.
// Production builds (served from real domain) get full PWA functionality.
const isLocalhost = ['localhost', '127.0.0.1', ''].includes(window.location.hostname);

if (isLocalhost) {
    navigator.serviceWorker?.getRegistrations()
        .then(regs => regs.forEach(r => r.unregister()))
        .catch(() => {});
} else if ('serviceWorker' in navigator) {
    // Registered by hand rather than through virtual:pwa-register because the
    // worker is served from the root, not from the build directory. It claims
    // the whole site simply by living there. skipWaiting and clientsClaim are
    // set inside the worker, so a new version takes over on the next load.
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/app-sw.js', { scope: '/' })
            .then(registration => {
                console.info('[pwa] service worker registered, scope:', registration.scope);
            })
            .catch(error => {
                console.error('[pwa] service worker registration failed', error);
            });
    });
}

const appName = import.meta.env.VITE_APP_NAME || 'Repertorios';

createInertiaApp({
    title: (title) => (title ? `${title} — ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(createPinia())
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4F46E5',
    },
});
