/**
 * Moves the built service worker to the document root.
 *
 * A worker can only control pages inside the directory it is served from, and
 * this app's pages live at / while the build output goes to /build/. Serving
 * the worker from the root is the one arrangement that no cache, proxy or
 * missing server header can undo — unlike asking for a wider scope through
 * the Service-Worker-Allowed header.
 *
 * Because it no longer sits next to the files it caches, every precached URL
 * has to be absolute. `manifestTransforms` in vite.config.js handles the ones
 * workbox globs, but not the web manifest: the plugin passes that through
 * `additionalManifestEntries`, and workbox deliberately leaves those out of
 * its transforms. A relative entry there fails the worker's install outright
 * (bad-precaching-response), which leaves the app with no worker at all — so
 * anything still relative is fixed here, and a leftover is a hard error.
 *
 * Runs after `vite build`, because the worker does not exist until then.
 */
import { copyFileSync, existsSync, readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const FILENAME = 'app-sw.js';
const BASE = '/build/';

const from = resolve(process.cwd(), 'public/build', FILENAME);
const to = resolve(process.cwd(), 'public', FILENAME);

if (!existsSync(from)) {
    console.error(`[pwa] service worker not found at ${from}`);
    process.exit(1);
}

copyFileSync(from, to);

// Matches the precache entries only: {url:"...",revision:...}
const PRECACHE_ENTRY = /\{url:"([^"]+)",revision:/g;

let rewritten = 0;

const source = readFileSync(to, 'utf8').replace(PRECACHE_ENTRY, (match, url) => {
    if (url.startsWith('/') || url.startsWith('http')) return match;

    rewritten++;

    return match.replace(`"${url}"`, `"${BASE}${url}"`);
});

writeFileSync(to, source);

const leftovers = [...source.matchAll(PRECACHE_ENTRY)]
    .map(([, url]) => url)
    .filter(url => !url.startsWith('/') && !url.startsWith('http'));

if (leftovers.length) {
    console.error(`[pwa] precache entries are still relative: ${leftovers.join(', ')}`);
    process.exit(1);
}

console.log(
    `[pwa] service worker copied to public/${FILENAME}`
    + (rewritten ? ` (${rewritten} precache URL${rewritten === 1 ? '' : 's'} made absolute)` : '')
);
