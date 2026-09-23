/**
 * Pulls the video id AND the start time out of a YouTube link.
 *
 * The start time is the point of this helper: a pasted link like
 * `youtu.be/abc?t=73` means "this song starts at 1:13", and dropping it —
 * which is what a bare 11-character id match does — silently loses that.
 *
 * Recognised start formats: `t=73`, `t=73s`, `t=1m13s`, `t=1h2m3s`, `start=73`.
 */
const ID_PATTERN = /(?:youtube\.com\/watch\?(?:.*&)?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/live\/|youtube\.com\/shorts\/)([\w-]{11})/;

/** `1m13s` / `73s` / `73` → seconds. Returns 0 when there is nothing usable. */
function parseTimeToken(raw) {
    if (!raw) return 0;

    const token = String(raw).trim().toLowerCase();

    if (/^\d+$/.test(token)) return parseInt(token, 10);

    const m = token.match(/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/);
    if (!m || (!m[1] && !m[2] && !m[3])) return 0;

    return (parseInt(m[1] || 0, 10) * 3600)
         + (parseInt(m[2] || 0, 10) * 60)
         + parseInt(m[3] || 0, 10);
}

/**
 * @returns {{id: string, start: number}|null}
 */
export function parseYouTube(url) {
    if (!url) return null;

    const idMatch = String(url).match(ID_PATTERN);
    if (!idMatch) return null;

    const timeMatch = String(url).match(/[?&#](?:t|start)=([\w]+)/);

    return {
        id: idMatch[1],
        start: timeMatch ? parseTimeToken(timeMatch[1]) : 0,
    };
}

/** Embed URL that honours the start time, for a plain <iframe>. */
export function youTubeEmbedUrl(url, extraParams = {}) {
    const parsed = parseYouTube(url);
    if (!parsed) return null;

    const params = new URLSearchParams(extraParams);
    if (parsed.start > 0) params.set('start', String(parsed.start));

    const query = params.toString();

    return `https://www.youtube.com/embed/${parsed.id}${query ? '?' + query : ''}`;
}

/** `73` → `1:13`, for showing the offset next to a song. */
export function formatStart(seconds) {
    if (!seconds || seconds <= 0) return '';

    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;

    return h > 0
        ? `${h}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
        : `${m}:${String(s).padStart(2, '0')}`;
}
