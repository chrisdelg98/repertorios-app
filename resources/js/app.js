import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { registerSW } from 'virtual:pwa-register';
import i18n from '@/i18n/index.js';

// Skip service worker on localhost to avoid stale chunk issues during development.
// Production builds (served from real domain) get full PWA functionality.
const isLocalhost = ['localhost', '127.0.0.1', ''].includes(window.location.hostname);

if (isLocalhost) {
    navigator.serviceWorker?.getRegistrations()
        .then(regs => regs.forEach(r => r.unregister()))
        .catch(() => {});
} else {
    registerSW({ immediate: true });
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
