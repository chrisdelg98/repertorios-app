import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: null,
            // The app lives at / but the build output sits under /build/, and a
            // worker may only control pages inside its own directory. Rather
            // than asking the server for a wider scope through a header — which
            // any cache or proxy in between can drop — the finished worker is
            // copied to the document root, where '/' is simply where it lives.
            scope: '/',
            filename: 'app-sw.js',
            includeAssets: ['favicon.ico', 'icons/*.png'],
            manifest: {
                name: 'Repertorios App',
                short_name: 'Repertorios App',
                description: 'Worship band repertoire platform',
                lang: 'es',
                theme_color: '#4F46E5',
                background_color: '#ffffff',
                display: 'standalone',
                orientation: 'portrait',
                scope: '/',
                start_url: '/',
                icons: [
                    {
                        src: '/icons/icon-192x192.png?v=2',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/icon-512x512.png?v=2',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/maskable-192x192.png?v=2',
                        sizes: '192x192',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                    {
                        src: '/icons/maskable-512x512.png?v=2',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            workbox: {
                // Push handling lives in public/push-sw.js so the generated
                // worker keeps its precaching and auto-update untouched.
                importScripts: ['/push-sw.js'],
                // The worker is served from the root, so nothing inside it can
                // be relative to /build/ any more: the runtime goes inline
                // instead of a sibling chunk, and precache URLs get the prefix
                // they would otherwise have inherited from their location.
                inlineWorkboxRuntime: true,
                // Every precached URL must be absolute, because the worker no
                // longer sits next to the files it caches. This runs last, so
                // it also catches manifest.webmanifest, which the plugin adds
                // after modifyURLPrefix has already been applied.
                manifestTransforms: [
                    (entries) => ({
                        manifest: entries.map((entry) => (
                            entry.url.startsWith('/')
                                ? entry
                                : { ...entry, url: `/build/${entry.url}` }
                        )),
                        warnings: [],
                    }),
                ],
                clientsClaim: true,
                skipWaiting: true,
                cleanupOutdatedCaches: true,
                navigateFallback: null,
                globPatterns: ['**/*.{js,css,ico,png,svg}'],
                runtimeCaching: [
                    {
                        urlPattern: /^https:\/\/fonts\.bunny\.net\/.*/i,
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'bunny-fonts-cache',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 365,
                            },
                            cacheableResponse: { statuses: [0, 200] },
                        },
                    },
                ],
            },
            devOptions: {
                enabled: false,
            },
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
});
