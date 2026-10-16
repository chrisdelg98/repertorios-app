import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { decorate } from '@/Utils/lyrics';

/**
 * How someone reads a song, and how they like to read it.
 *
 * The three settings here — how big, flowing or one block at a time, and
 * whether the structure shows — belong to the person rather than to the song
 * or to the screen they happen to be on. Someone who needs large type needs it
 * in the player and in the library both, so they are remembered once and
 * shared by every surface that reads lyrics.
 */

const ZOOM_KEY = 'repertorios.lyrics.zoom';
const MODE_KEY = 'repertorios.lyrics.mode';
const RAIL_KEY = 'repertorios.lyrics.rail';

/**
 * Sizes rather than a slider.
 *
 * A slider invites fiddling and lands on values that break the line length;
 * steps can each be checked for a sensible measure. The jumps widen as they
 * climb, because at the top end a tenth of a rem stops being a difference.
 */
export const ZOOM_STEPS = [
    { label: 'S', text: 'text-base',  leading: 'leading-relaxed' },
    { label: 'M', text: 'text-lg',    leading: 'leading-relaxed' },
    { label: 'L', text: 'text-xl',    leading: 'leading-relaxed' },
    { label: 'XL', text: 'text-2xl',  leading: 'leading-snug' },
    { label: '2XL', text: 'text-3xl', leading: 'leading-snug' },
    { label: '3XL', text: 'text-4xl', leading: 'leading-tight' },
];

const DEFAULT_ZOOM = 1;

function read(key, fallback) {
    try {
        const saved = localStorage.getItem(key);

        return saved === null ? fallback : saved;
    } catch {
        // Private windows and blocked site data: the default stands.
        return fallback;
    }
}

function write(key, value) {
    try {
        localStorage.setItem(key, String(value));
    } catch {
        // Nothing to do, and nothing worth telling anyone about.
    }
}

export function useLyricsReader(blocksRef, options = {}) {
    const { t } = useI18n();

    /** The rail starts out of the way on a phone and open where there is room. */
    const wideDefault = options.railDefault
        ?? (() => {
            try {
                return window.matchMedia('(min-width: 768px)').matches ? 'full' : 'thin';
            } catch {
                return 'thin';
            }
        })();

    const zoom = ref(Math.min(
        ZOOM_STEPS.length - 1,
        Math.max(0, Number(read(ZOOM_KEY, DEFAULT_ZOOM)) || DEFAULT_ZOOM),
    ));

    const mode = ref(read(MODE_KEY, 'flow') === 'blocks' ? 'blocks' : 'flow');

    const savedRail = read(RAIL_KEY, wideDefault);

    const railState = ref(['full', 'thin', 'hidden'].includes(savedRail)
        ? savedRail
        : wideDefault);

    watch(zoom, value => write(ZOOM_KEY, value));
    watch(mode, value => write(MODE_KEY, value));
    watch(railState, value => write(RAIL_KEY, value));

    const entries = computed(() => decorate(blocksRef.value));

    /**
     * The label as it is read, in the reader's language.
     *
     * A number only appears when the song has more than one of that kind —
     * `decorate` decided that already — so "Coro" and "Verso 1" come out of
     * the same call with nothing set anywhere.
     */
    function labelOf(entry) {
        if (!entry) return '';
        if (entry.missing) return t('songs.lyrics.missing');
        if (entry.label) return entry.label;

        const name = t('songs.lyrics.kinds.' + entry.kind);

        return entry.num ? name + ' ' + entry.num : name;
    }

    /** The same label, as short as it goes, for the thin rail. */
    function shortLabelOf(entry) {
        if (!entry) return '';
        if (entry.label) return entry.label.slice(0, 3);

        return entry.roman || '';
    }

    /* ── Where the reader is ───────────────────────────────────────────── */

    const activeIndex = ref(0);

    watch(entries, (list) => {
        if (activeIndex.value > list.length - 1) activeIndex.value = Math.max(0, list.length - 1);
    });

    const active = computed(() => entries.value[activeIndex.value] ?? null);
    const canPrevBlock = computed(() => activeIndex.value > 0);
    const canNextBlock = computed(() => activeIndex.value < entries.value.length - 1);

    function goTo(index) {
        activeIndex.value = Math.min(entries.value.length - 1, Math.max(0, index));
    }

    function nextBlock() {
        if (canNextBlock.value) activeIndex.value += 1;
    }

    function prevBlock() {
        if (canPrevBlock.value) activeIndex.value -= 1;
    }

    /* ── Settings ──────────────────────────────────────────────────────── */

    const zoomStep = computed(() => ZOOM_STEPS[zoom.value] ?? ZOOM_STEPS[DEFAULT_ZOOM]);
    const canZoomIn = computed(() => zoom.value < ZOOM_STEPS.length - 1);
    const canZoomOut = computed(() => zoom.value > 0);

    function zoomIn() {
        if (canZoomIn.value) zoom.value += 1;
    }

    function zoomOut() {
        if (canZoomOut.value) zoom.value -= 1;
    }

    function toggleMode() {
        mode.value = mode.value === 'flow' ? 'blocks' : 'flow';
    }

    /**
     * Full, thin, gone — in that order, and back round.
     *
     * Thin sits between the other two on purpose: it is the state that keeps
     * what the rail is for (knowing a chorus is coming) while giving the words
     * back the width, which on a phone is the one that gets used.
     */
    function cycleRail() {
        railState.value = railState.value === 'full'
            ? 'thin'
            : (railState.value === 'thin' ? 'hidden' : 'full');
    }

    return {
        entries,
        labelOf,
        shortLabelOf,

        activeIndex,
        active,
        goTo,
        nextBlock,
        prevBlock,
        canNextBlock,
        canPrevBlock,

        zoom,
        zoomStep,
        zoomIn,
        zoomOut,
        canZoomIn,
        canZoomOut,

        mode,
        toggleMode,

        railState,
        cycleRail,
    };
}
