/**
 * The YouTube IFrame API, loaded once for the whole SPA.
 *
 * Two screens can want a player in one session, and the API is a global that
 * calls one global callback when it arrives. Anything that injects the script
 * a second time, or overwrites that callback, breaks whichever player asked
 * first. The flag and the chained callback are shared with the overlay's own
 * loader on purpose: either may get there first.
 */
export function youTubeApi() {
    return new Promise((resolve, reject) => {
        if (window.YT?.Player) {
            resolve(window.YT);
            return;
        }

        // Someone else is already fetching it; wait for the same arrival.
        if (window._ytApiLoading) {
            const started = Date.now();

            const check = setInterval(() => {
                if (window.YT?.Player) {
                    clearInterval(check);
                    resolve(window.YT);
                } else if (Date.now() - started > 15000) {
                    clearInterval(check);
                    reject(new Error('YouTube API timed out'));
                }
            }, 80);

            return;
        }

        window._ytApiLoading = true;

        const previous = window.onYouTubeIframeAPIReady;

        window.onYouTubeIframeAPIReady = () => {
            resolve(window.YT);
            if (typeof previous === 'function') previous();
        };

        const tag = document.createElement('script');
        tag.src = 'https://www.youtube.com/iframe_api';
        tag.onerror = () => {
            window._ytApiLoading = false;
            reject(new Error('YouTube API failed to load'));
        };

        document.head.appendChild(tag);
    });
}
