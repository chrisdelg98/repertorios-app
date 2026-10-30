<script setup>
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { parseYouTube, formatStart, parsePlaylist } from '@/Utils/youtube';
import LyricsPanel from '@/Components/Lyrics/LyricsPanel.vue';
import ChordChartReader from '@/Components/Chords/ChordChartReader.vue';
import { chartSheet, transposeChord } from '@/Utils/chords';
import { decorate } from '@/Utils/lyrics';

const { t } = useI18n();

const props = defineProps({
    open:  { type: Boolean, default: false },
    songs: { type: Array,   default: () => [] }, // { name, artist, version, key, youtube_url, audio, notes, lyrics }
    /** A whole YouTube playlist, used when no setlist was built by hand. */
    playlistUrl: { type: String, default: '' },
});

const emit = defineEmits(['close']);

/**
 * A song may be playable through both sources, so it keeps both.
 *
 * Which one is reached for is settled by `preferred` below, not here: that is
 * a decision about the evening rather than about any one song.
 */
const playable = computed(() =>
    props.songs
        .map(s => {
            const parsed = parseYouTube(s.youtube_url);
            const audioUrl = s.audio?.url ?? null;
            const lyrics = Array.isArray(s.lyrics) ? s.lyrics : [];

            // Words are enough on their own. This screen is where a service is
            // run from, and a song nobody has a track or a video for is still
            // a song that gets sung — leaving it out of the queue meant the
            // running order on screen was not the running order being played.
            if (!audioUrl && !parsed && !lyrics.length) return null;

            return {
                ...s,
                _lyrics: lyrics,
                _chords: s.chords ?? {},
                _chordsKey: s.chords_key ?? null,
                _audioUrl: audioUrl,
                _videoId: parsed?.id ?? null,
                _start: parsed?.start ?? 0,
                _startLabel: parsed ? formatStart(parsed.start) : '',
            };
        })
        .filter(Boolean)
);

/**
 * Which source to reach for, across the whole setlist.
 *
 * A preference rather than a choice per song: in an evening you want the
 * tracks or you want the videos, and saying so once should hold for the rest
 * of the queue. A song that only has one of the two plays that one, whatever
 * is preferred — hence the word.
 */
const PREFERENCE_KEY = 'repertorios.playlist.source';

const preferred = ref('audio');

try {
    const saved = localStorage.getItem(PREFERENCE_KEY);
    if (saved === 'audio' || saved === 'youtube') preferred.value = saved;
} catch {
    // Private windows and blocked site data: the default stands.
}

function sourceOf(idx) {
    const song = playable.value[idx];
    if (!song) return null;

    // Nothing to play: the song is here for its words. Neither engine is
    // touched for it, so the iframe is not asked to load an empty video id.
    if (!song._audioUrl && !song._videoId) return 'none';

    return preferred.value === 'audio'
        ? (song._audioUrl ? 'audio' : 'youtube')
        : (song._videoId ? 'youtube' : 'audio');
}

/**
 * A pasted YouTube playlist stands in for the setlist when there is none.
 *
 * Its contents are not known here — reading them needs the YouTube Data API
 * and a key — so YouTube runs its own queue and the sidebar steps aside.
 */
const playlistId = computed(() => parsePlaylist(props.playlistUrl));
const usingPlaylist = computed(() => !playable.value.length && !!playlistId.value);

const currentIdx = ref(0);
let ytPlayer    = null;
const playerEl  = ref(null);
const apiReady  = ref(false);

/**
 * Whether the iframe has answered yet.
 *
 * Between opening the overlay and YouTube's player reporting ready there are a
 * few seconds of plain black, which on a slow phone connection is
 * indistinguishable from something being broken. So the wait is shown, and so
 * is a way out when it fails.
 */
const playerReady  = ref(false);
const playerFailed = ref(false);

// --- Lazy-load the YouTube IFrame API exactly once across the SPA ---
function loadYouTubeApi() {
    if (window.YT && window.YT.Player) {
        apiReady.value = true;
        return;
    }
    if (window._ytApiLoading) {
        const check = setInterval(() => {
            if (window.YT && window.YT.Player) {
                clearInterval(check);
                apiReady.value = true;
            }
        }, 80);
        return;
    }
    window._ytApiLoading = true;
    const prev = window.onYouTubeIframeAPIReady;
    window.onYouTubeIframeAPIReady = () => {
        apiReady.value = true;
        if (typeof prev === 'function') prev();
    };
    const tag = document.createElement('script');
    tag.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(tag);
}

// --- Audio engine ---
// A plain <audio> element, driven from here so the queue behaves the same
// whichever source the current song uses.
const audioEl     = ref(null);
const audioPlaying = ref(false);
const audioTime    = ref(0);
const audioLength  = ref(0);

const current = computed(() => playable.value[currentIdx.value] ?? null);
const currentSource = computed(() => sourceOf(currentIdx.value));
const isAudio = computed(() => currentSource.value === 'audio');
const currentHasAudio = computed(() => !!current.value?._audioUrl);
const currentHasVideo = computed(() => !!current.value?._videoId);

/**
 * The bar only appears when the preference can change something: if no song in
 * the setlist carries both, there is nothing to prefer.
 */
const canPrefer = computed(() =>
    playable.value.some(song => song._audioUrl && song._videoId)
);

/* ── The words ─────────────────────────────────────────────────────────── */

/**
 * One side panel, two things in it.
 *
 * The queue and the words want the same place on screen — beside the media on
 * a desktop, under it on a phone — and nobody needs both at once: you check
 * the running order between songs and read the words during one. So they share
 * the space behind a pair of tabs instead of splitting it and leaving each
 * half too narrow to use.
 */
const panelTab = ref('queue');

const lyricsPanel = ref(null);

const currentLyrics = computed(() => current.value?._lyrics ?? []);
const currentHasLyrics = computed(() => currentLyrics.value.length > 0);

/*
 * The chart, read rather than written.
 *
 * One entry per appearance, the same resolution the editor uses, so a chorus
 * sung three times shows its chords three times and one set loose to modulate
 * shows its own.
 */
const currentChart = computed(() => chartSheet(decorate(currentLyrics.value), current.value?._chords ?? {})
    .map(entry => ({
        id: entry.chordKey,
        kind: entry.kind,
        label: entry.label || t('songs.lyrics.kinds.' + entry.kind) + (entry.num ? ' ' + entry.num : ''),
        text: entry.text,
    })));

const currentHasChords = computed(() => {
    const chart = current.value?._chords ?? {};

    return currentChart.value.some(block => (chart[block.id] ?? []).some(line => line?.length));
});

/**
 * How far the chart is being moved, in semitones.
 *
 * Moved here and not saved: the band's chart stays in the key it was written
 * in, and this is one singer on one morning asking for it a tone down. Where
 * the written key is known the resulting key is named, because that is the
 * question actually being asked — not "two up" but "what are we in".
 */
const chartSteps = ref(0);

const writtenKey = computed(() => current.value?._chordsKey ?? null);

const sungKey = computed(() => (
    writtenKey.value ? transposeChord(writtenKey.value, chartSteps.value) : ''
));

// A different song is a different chart: carrying a transposition across would
// put the next song in a key nobody asked for.
watch(currentIdx, () => { chartSteps.value = 0; });
const anyLyrics = computed(() => playable.value.some(song => song._lyrics?.length));

/** Whether anything is actually playing, as opposed to only being read. */
const hasMedia = computed(() => currentSource.value === 'audio' || currentSource.value === 'youtube');

function showLyrics() {
    panelTab.value = 'lyrics';
}

function showChords() {
    panelTab.value = 'chords';
}

function toggleLyrics() {
    if (!anyLyrics.value) return;

    panelTab.value = panelTab.value === 'lyrics' ? 'queue' : 'lyrics';
}

/**
 * Reaching a song with nothing to play opens its words by itself.
 *
 * The alternative is a queue sitting next to an empty stage, with the one
 * thing the song has to offer a tap away and no sign that it is there.
 */
watch([currentIdx, currentSource], () => {
    if (!hasMedia.value && currentHasLyrics.value) panelTab.value = 'lyrics';
});

function formatClock(seconds) {
    if (!seconds || !isFinite(seconds)) return '0:00';

    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);

    return `${m}:${String(s).padStart(2, '0')}`;
}

function toggleAudio() {
    const el = audioEl.value;
    if (!el) return;

    el.paused ? el.play().catch(() => {}) : el.pause();
}

function seekAudio(event) {
    const el = audioEl.value;
    if (el && isFinite(el.duration)) el.currentTime = Number(event.target.value);
}

function onAudioEnded() {
    audioPlaying.value = false;
    playNext();
}

/** Loads the track without playing it: the queue moved, nobody pressed play. */
async function cueAudio() {
    await nextTick();

    const el = audioEl.value;
    if (!el) return;

    audioTime.value = 0;
    audioLength.value = 0;
    audioPlaying.value = false;

    try { el.load(); } catch {}
}

/** Starts the audio for the song that just became current. */
async function startAudio() {
    await nextTick();

    const el = audioEl.value;
    if (!el) return;

    audioTime.value = 0;
    audioLength.value = 0;

    try {
        await el.play();
    } catch {
        // Autoplay can be refused; the person presses play and it works.
        audioPlaying.value = false;
    }
}

function buildPlayer() {
    // The YouTube player is only built for a YouTube song; an audio one has no
    // iframe to attach to.
    if (!apiReady.value || !playerEl.value) return;

    if (usingPlaylist.value) {
        if (ytPlayer) { try { ytPlayer.destroy(); } catch {} }

        playerReady.value = false;
        playerFailed.value = false;

        ytPlayer = new window.YT.Player(playerEl.value, {
            playerVars: {
                playsinline: 1,
                rel: 0,
                modestbranding: 1,
                listType: 'playlist',
                list: playlistId.value,
            },
            events: {
                onReady: () => { playerReady.value = true; },
                // A private or deleted playlist cannot be recovered from here:
                // the only useful answer is to open it on YouTube.
                onError: () => { playerFailed.value = true; },
            },
        });

        return;
    }

    if (!playable.value.length) return;
    if (sourceOf(currentIdx.value) !== 'youtube') return;
    if (ytPlayer) { try { ytPlayer.destroy(); } catch {} ytPlayer = null; }

    playerReady.value = false;
    playerFailed.value = false;

    const first = playable.value[currentIdx.value];

    ytPlayer = new window.YT.Player(playerEl.value, {
        videoId: first._videoId,
        // `start` honours the ?t= of the pasted link — a song that begins at
        // 1:13 of a longer video should not play the intro every time.
        playerVars: { playsinline: 1, rel: 0, modestbranding: 1, start: first._start || 0 },
        events: {
            onReady: () => { playerReady.value = true; },
            onStateChange: (e) => {
                // 0 = ended → advance
                if (e.data === 0) playNext();
            },
            onError: () => playNext(),
        },
    });
}

function loadAt(idx) {
    const song = playable.value[idx];
    if (!song) return;

    // Read before anything stops: whether sound was coming out a moment ago
    // is what decides whether it should keep coming out.
    const wasPlaying = autoplay.value || audioPlaying.value || (() => {
        try { return ytPlayer?.getPlayerState() === 1; } catch { return false; }
    })();

    currentIdx.value = idx;

    // A song with only words: silence both engines and leave them alone. It
    // never ends on its own either, so nothing advances until someone says so.
    if (sourceOf(idx) === 'none') {
        if (audioEl.value) audioEl.value.pause();
        if (ytPlayer) { try { ytPlayer.stopVideo(); } catch {} }
        audioPlaying.value = false;
        return;
    }

    if (sourceOf(idx) === 'audio') {
        // Stop the video before the track starts, or both play at once.
        if (ytPlayer) { try { ytPlayer.stopVideo(); } catch {} }

        // Only carry on playing if something already was. Stepping through
        // the setlist to find a song is not the same as asking to hear it.
        wasPlaying ? startAudio() : cueAudio();
        return;
    }

    if (audioEl.value) audioEl.value.pause();

    if (!ytPlayer) {
        // Coming from an audio song, the iframe may not exist yet.
        buildPlayer();
        return;
    }

    // The iframe is already up and showing a frame, so there is nothing to
    // wait for.
    playerReady.value = true;

    /*
     * `loadVideoById` plays; `cueVideoById` only loads.
     *
     * With autoplay on this always plays, which is the rehearsal. With it off
     * it plays only if sound was already coming out, so walking the setlist at
     * a service stays silent.
     */
    const playing = autoplay.value || (() => {
        try { return ytPlayer.getPlayerState() === 1; } catch { return false; }
    })();

    const load = playing ? 'loadVideoById' : 'cueVideoById';

    ytPlayer[load]({
        videoId: song._videoId,
        startSeconds: song._start || 0,
    });
}

/** Prefer this source from here on, and apply it to what is playing. */
function prefer(source) {
    if (source === preferred.value) return;

    // The song being played may not have the newly preferred source, in which
    // case it carries on untouched and only the rest of the queue follows.
    const song = current.value;
    const affectsCurrent = !!(source === 'audio' ? song?._audioUrl : song?._videoId);

    if (affectsCurrent) {
        // Silence both engines before the swap. The one on screen is unmounted
        // by the re-render, and a detached <audio> goes on playing in most
        // browsers — the track would run under the video with nothing left to
        // stop it.
        if (audioEl.value) audioEl.value.pause();
        if (ytPlayer) { try { ytPlayer.stopVideo(); } catch {} }
    }

    preferred.value = source;

    try {
        localStorage.setItem(PREFERENCE_KEY, source);
    } catch {
        // Not worth failing the switch over.
    }

    // The audio element and the iframe swap places in the DOM, so the engines
    // are only touched once that has happened.
    if (affectsCurrent) nextTick(() => loadAt(currentIdx.value));
}

function playNext() {
    if (currentIdx.value < playable.value.length - 1) loadAt(currentIdx.value + 1);
}

function playPrev() {
    if (currentIdx.value > 0) loadAt(currentIdx.value - 1);
}

const upNext = computed(() => playable.value[currentIdx.value + 1] ?? null);

/**
 * Put the player away and leave the words.
 *
 * Standing at a service, the video is the part nobody needs: it takes the
 * height the lyrics want and offers a picture of an album cover. Hidden with
 * v-show rather than v-if, so the iframe survives — tearing it down would stop
 * whatever is playing and cost a reload to bring back.
 */
const playerHidden = ref(false);

/**
 * Whether moving through the setlist starts the sound.
 *
 * On is the behaviour everyone knows and the one that suits a rehearsal: press
 * play once and the evening runs itself. Off is for standing at a service,
 * where stepping through the songs to find the words should not announce
 * itself to the room. One press either way, and it is remembered.
 */
const AUTOPLAY_KEY = 'repertorios.playlist.autoplay';

const autoplay = ref(true);

try {
    autoplay.value = localStorage.getItem(AUTOPLAY_KEY) !== 'off';
} catch {
    // Private windows: the familiar behaviour stands.
}

function toggleAutoplay() {
    autoplay.value = !autoplay.value;

    try {
        localStorage.setItem(AUTOPLAY_KEY, autoplay.value ? 'on' : 'off');
    } catch {}
}

function togglePlayer() {
    playerHidden.value = !playerHidden.value;

    // Hiding the player while reading implies reading, so the words come
    // forward with it.
    if (playerHidden.value && anyLyrics.value) showLyrics();
}

const viewsOpen = ref(false);

/** What the panel can show. The setlist is not one of them — it is the panel. */
const panelViews = computed(() => [
    { id: 'queue', label: t('playlist.tab_queue'), empty: false },
    { id: 'lyrics', label: t('playlist.tab_lyrics'), empty: !currentHasLyrics.value },
    { id: 'chords', label: t('songs.chords.tab'), empty: !currentHasChords.value },
]);

const currentViewLabel = computed(() =>
    panelViews.value.find(v => v.id === panelTab.value)?.label ?? ''
);

function chooseView(id) {
    viewsOpen.value = false;
    panelTab.value = ['lyrics', 'chords'].includes(id) ? id : 'queue';
}

function onViewsDocClick(e) {
    if (!e.target.closest('[data-panel-views]')) viewsOpen.value = false;
}

const helpOpen = ref(false);

const shortcutRows = computed(() => [
    { label: t('playlist.play') + ' / ' + t('playlist.pause'), keys: ['Espacio', 'K'] },
    { label: t('playlist.shortcut_seek'), keys: ['←', '→'] },
    { label: t('playlist.shortcut_volume'), keys: ['↑', '↓'] },
    { label: t('playlist.shortcut_track'), keys: ['N', 'P'] },
    { label: t('playlist.shortcut_mute'), keys: ['M'] },
    { label: t('playlist.shortcut_lyrics'), keys: ['L'] },
    { label: t('playlist.shortcut_zoom'), keys: ['-', '+'] },
    { label: t('playlist.shortcut_block'), keys: ['[', ']'] },
    { label: t('playlist.close'), keys: ['Esc'] },
]);

/**
 * Whether the keys are worth mentioning at all.
 *
 * A phone with no keyboard attached should never be told about shortcuts, and
 * a tablet with one should. Pointer coarseness is the closest the browser
 * comes to answering that.
 */
const hasKeyboard = ref(true);

/** Nudge either engine, whichever is carrying the sound. */
function seekBy(seconds) {
    if (isAudio.value) {
        const el = audioEl.value;
        if (el && isFinite(el.duration)) {
            el.currentTime = Math.min(el.duration, Math.max(0, el.currentTime + seconds));
        }
        return;
    }

    try {
        ytPlayer?.seekTo(Math.max(0, ytPlayer.getCurrentTime() + seconds), true);
    } catch {}
}

function changeVolume(delta) {
    if (isAudio.value) {
        const el = audioEl.value;
        if (el) el.volume = Math.min(1, Math.max(0, el.volume + delta));
        return;
    }

    try {
        ytPlayer?.setVolume(Math.min(100, Math.max(0, ytPlayer.getVolume() + delta * 100)));
    } catch {}
}

function togglePlayback() {
    if (isAudio.value) {
        toggleAudio();
        return;
    }

    try {
        ytPlayer?.getPlayerState() === 1 ? ytPlayer.pauseVideo() : ytPlayer?.playVideo();
    } catch {}
}

function toggleMute() {
    if (isAudio.value) {
        const el = audioEl.value;
        if (el) el.muted = !el.muted;
        return;
    }

    try {
        ytPlayer?.isMuted() ? ytPlayer.unMute() : ytPlayer?.mute();
    } catch {}
}

/**
 * The keys work without clicking anything first.
 *
 * Listening on the document rather than on a focusable wrapper: asking someone
 * to click the player before space works is the thing that makes people decide
 * shortcuts are broken. The one place it cannot reach is inside the YouTube
 * iframe, which keeps its own keys once it has focus.
 */
function onKeydown(e) {
    if (!props.open) return;

    const tag = e.target?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || e.target?.isContentEditable) return;
    if (e.metaKey || e.ctrlKey || e.altKey) return;

    const handled = {
        Space:      () => togglePlayback(),
        ArrowRight: () => (e.shiftKey ? playNext() : seekBy(5)),
        ArrowLeft:  () => (e.shiftKey ? playPrev() : seekBy(-5)),
        ArrowUp:    () => changeVolume(0.1),
        ArrowDown:  () => changeVolume(-0.1),
        KeyN:       () => playNext(),
        KeyP:       () => playPrev(),
        KeyM:       () => toggleMute(),
        KeyK:       () => togglePlayback(),
        KeyL:       () => toggleLyrics(),
        Minus:        () => lyricsPanel.value?.zoomOut(),
        Equal:        () => lyricsPanel.value?.zoomIn(),
        BracketLeft:  () => lyricsPanel.value?.prevBlock(),
        BracketRight: () => lyricsPanel.value?.nextBlock(),
        Escape:     () => (helpOpen.value ? (helpOpen.value = false) : close()),
        Slash:      () => (helpOpen.value = !helpOpen.value),
    }[e.code];

    if (!handled) return;

    // Space scrolls the page and the arrows move it sideways; both would
    // fight whatever the key was meant to do.
    e.preventDefault();
    handled();
}

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
    document.addEventListener('click', onViewsDocClick);

    try {
        hasKeyboard.value = !window.matchMedia('(pointer: coarse)').matches
            || window.matchMedia('(any-hover: hover)').matches;
    } catch {
        hasKeyboard.value = true;
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.removeEventListener('click', onViewsDocClick);
});

function close() {
    emit('close');
}

// Open / close lifecycle
watch(() => props.open, (isOpen) => {
    if (isOpen) {
        currentIdx.value = 0;
        playerReady.value = false;
        playerFailed.value = false;

        // The API is loaded even when the first song is a track: the queue can
        // reach a YouTube song later, and loading it then would stall playback.
        loadYouTubeApi();

        /*
         * Nothing plays until someone asks.
         *
         * Two people open this screen: one rehearsing at home, who presses
         * play, and one standing at a service who only wants the words.
         * Sound starting by itself serves the first and embarrasses the second,
         * and the second is the one who cannot stop it quickly.
         */

        return;
    }

    if (ytPlayer) {
        try { ytPlayer.destroy(); } catch {}
        ytPlayer = null;
    }

    if (audioEl.value) audioEl.value.pause();
    audioPlaying.value = false;
}, { immediate: true });

// Build player when API ready + DOM mounted (open state)
watch([apiReady, () => props.open], ([ready, isOpen]) => {
    if (ready && isOpen) {
        // wait one tick so playerEl exists
        requestAnimationFrame(() => buildPlayer());
    }
});

onBeforeUnmount(() => {
    if (ytPlayer) { try { ytPlayer.destroy(); } catch {} }
});

</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 bg-slate-900/95 backdrop-blur-sm flex flex-col">
                <!-- Header -->
                <!-- The label is one line and the title is one line, both
                     truncating. Before, the label wrapped onto two on a phone
                     and pushed the song's name into the buttons. -->
                <div class="flex items-center gap-2 px-3 sm:px-4 py-2.5 border-b border-white/10 shrink-0">
                    <div class="flex-1 min-w-0">
                        <p class="text-[10px] sm:text-2xs font-semibold text-indigo-300 uppercase tracking-widest truncate">{{ t('playlist.title') }}</p>
                        <!-- An external playlist has no track of ours to name,
                             and "no video" reads as a failure rather than as
                             what it is. -->
                        <p v-if="usingPlaylist" class="text-sm font-bold text-white truncate flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M21.6 7.2a2.5 2.5 0 00-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.4A2.5 2.5 0 002.4 7.2 26 26 0 002 12a26 26 0 00.4 4.8 2.5 2.5 0 001.8 1.8C5.7 19 12 19 12 19s6.3 0 7.8-.4a2.5 2.5 0 001.8-1.8A26 26 0 0022 12a26 26 0 00-.4-4.8zM10 15V9l5 3z" />
                            </svg>
                            {{ t('playlist.external_title') }}
                        </p>
                        <p v-else class="text-sm font-bold text-white truncate">
                            {{ current ? current.name : t('playlist.empty_title') }}
                            <span v-if="current?.artist" class="font-normal text-slate-300"> · {{ current.artist }}</span>
                        </p>
                    </div>
                    <button
                        v-if="!usingPlaylist"
                        type="button"
                        @click="toggleAutoplay"
                        class="shrink-0 h-9 px-2 sm:px-2.5 flex items-center gap-1 sm:gap-1.5 rounded-lg text-2xs font-bold uppercase tracking-wide transition-colors"
                        :class="autoplay ? 'text-slate-400 hover:text-white hover:bg-white/10' : 'bg-amber-400/15 text-amber-300'"
                        :aria-pressed="!autoplay"
                        :title="autoplay ? t('playlist.autoplay_on') : t('playlist.autoplay_off')"
                    >
                        <svg v-if="autoplay" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                        <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5" />
                        </svg>
                        {{ t('playlist.autoplay_short') }}
                    </button>

                    <button
                        v-if="hasKeyboard"
                        type="button"
                        @click="helpOpen = true"
                        class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors mr-1"
                        :aria-label="t('playlist.shortcuts_title')"
                        :title="t('playlist.shortcuts_title')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                    </button>

                    <button
                        @click="close"
                        class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors"
                        :aria-label="t('playlist.close')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- What the keys do. Only reachable where there are keys. -->
                <Transition
                    enter-active-class="transition duration-150 ease-out"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition duration-100 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="helpOpen"
                        class="absolute inset-0 z-20 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-6"
                        @click="helpOpen = false"
                    >
                        <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-sm p-5 shadow-2xl" @click.stop>
                            <div class="flex items-center justify-between gap-3 mb-4">
                                <h3 class="text-sm font-bold text-white">{{ t('playlist.shortcuts_title') }}</h3>
                                <button
                                    type="button"
                                    @click="helpOpen = false"
                                    class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <dl class="space-y-2.5">
                                <div v-for="row in shortcutRows" :key="row.label" class="flex items-center justify-between gap-4">
                                    <dt class="text-xs text-slate-300">{{ row.label }}</dt>
                                    <dd class="flex items-center gap-1 shrink-0">
                                        <kbd
                                            v-for="key in row.keys"
                                            :key="key"
                                            class="px-1.5 py-0.5 text-2xs font-semibold text-slate-200 bg-white/10 border border-white/15 rounded"
                                        >{{ key }}</kbd>
                                    </dd>
                                </div>
                            </dl>

                            <p class="text-2xs text-slate-500 mt-4 leading-relaxed">{{ t('playlist.shortcuts_note') }}</p>
                        </div>
                    </div>
                </Transition>

                <!-- Empty state: no playable videos -->
                <div v-if="!playable.length && !usingPlaylist" class="flex-1 flex items-center justify-center px-6 text-center">
                    <div>
                        <svg class="w-12 h-12 text-slate-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 010 .656l-5.603 3.113a.375.375 0 01-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <p class="text-sm text-slate-300">{{ t('playlist.no_videos') }}</p>
                    </div>
                </div>

                <!-- Player + queue -->
                <div v-else class="flex-1 flex flex-col lg:flex-row min-h-0">
                    <!-- Player. The iframe stays mounted even while a track is
                         playing: destroying and rebuilding it on every switch
                         costs a reload of the YouTube API each time. -->
                    <div
                        v-show="!playerHidden"
                        class="shrink-0 bg-black flex flex-col min-h-0"
                        :class="hasMedia ? 'lg:flex-1' : ''"
                    >
                        <!-- The source bar keeps its place whatever plays below
                             it. It states a preference for the whole setlist,
                             so it does not belong to the current song and must
                             not move when the media under it changes size. -->
                        <div
                            v-if="canPrefer"
                            class="shrink-0 flex items-center justify-center gap-2 sm:gap-2.5 px-3 py-2.5 border-b border-white/5"
                        >
                            <span class="hidden sm:inline text-2xs font-semibold text-slate-500 uppercase tracking-widest">{{ t('playlist.prefer') }}</span>

                            <div class="flex items-center gap-1 p-1 bg-white/10 rounded-xl">
                                <button
                                    type="button"
                                    @click="prefer('audio')"
                                    :disabled="!currentHasAudio"
                                    class="flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-lg transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                    :class="isAudio ? 'bg-white text-slate-900' : 'text-slate-300 enabled:hover:text-white'"
                                >
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                                    </svg>
                                    {{ t('playlist.source_track') }}
                                </button>
                                <button
                                    type="button"
                                    @click="prefer('youtube')"
                                    :disabled="!currentHasVideo"
                                    class="flex items-center gap-1.5 px-3 py-2 text-xs font-bold rounded-lg transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                    :class="!isAudio ? 'bg-white text-slate-900' : 'text-slate-300 enabled:hover:text-white'"
                                >
                                    <svg class="w-3.5 h-3.5" :class="!isAudio ? 'text-red-600' : ''" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M21.6 7.2a2.5 2.5 0 00-1.8-1.8C18.3 5 12 5 12 5s-6.3 0-7.8.4A2.5 2.5 0 002.4 7.2 26 26 0 002 12a26 26 0 00.4 4.8 2.5 2.5 0 001.8 1.8C5.7 19 12 19 12 19s6.3 0 7.8-.4a2.5 2.5 0 001.8-1.8A26 26 0 0022 12a26 26 0 00-.4-4.8zM10 15V9l5 3z" />
                                    </svg>
                                    {{ t('playlist.source_youtube') }}
                                </button>
                            </div>
                        </div>

                        <!-- The media, centred in whatever height is left. -->
                        <div class="flex-1 flex flex-col items-center justify-center min-h-0 relative overflow-hidden">
                            <!-- The black, lit rather than patterned.
                                 Apple does this with light and nothing else: a
                                 wide, almost-not-there wash from above, a
                                 cooler one answering from below, and a slow
                                 darkening at the corners so the centre is the
                                 brightest place without anything being drawn
                                 there. No texture — a pattern is something to
                                 look at, and nothing here should compete with
                                 a title and a progress bar.

                                 Only while a track is playing; over a video it
                                 would be a film on the lens. -->
                            <div
                                v-if="isAudio"
                                class="pointer-events-none absolute inset-0"
                                style="background:
                                    radial-gradient(120% 80% at 50% -20%, rgba(129,140,248,0.16), rgba(129,140,248,0) 55%),
                                    radial-gradient(100% 70% at 50% 120%, rgba(167,139,250,0.10), rgba(167,139,250,0) 55%),
                                    radial-gradient(130% 90% at 50% 50%, rgba(0,0,0,0) 40%, rgba(0,0,0,0.55) 100%)"
                            />
                            <div
                                class="w-full aspect-video max-h-full"
                                :class="hasMedia && !isAudio ? 'relative' : 'invisible absolute inset-0'"
                            >
                                <div ref="playerEl" class="w-full h-full" />

                                <div
                                    v-if="hasMedia && !isAudio && !playerReady"
                                    class="absolute inset-0 bg-black flex flex-col items-center justify-center gap-3 px-6 text-center"
                                >
                                    <template v-if="playerFailed">
                                        <svg class="w-9 h-9 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                        </svg>
                                        <p class="text-sm font-semibold text-slate-200">{{ t('playlist.failed') }}</p>
                                        <a
                                            v-if="usingPlaylist"
                                            :href="playlistUrl"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-white/10 hover:bg-white/20 rounded-lg transition-colors"
                                        >
                                            {{ t('playlist.open_in_youtube') }}
                                        </a>
                                    </template>
                                    <template v-else>
                                        <span class="w-8 h-8 rounded-full border-2 border-white/20 border-t-white animate-spin" />
                                        <p class="text-xs font-medium text-slate-400">
                                            {{ usingPlaylist ? t('playlist.loading_playlist') : t('playlist.loading') }}
                                        </p>
                                    </template>
                                </div>
                            </div>

                            <p v-if="usingPlaylist" class="text-xs font-medium text-slate-400 mt-3 px-4 text-center">
                                {{ t('playlist.external_hint') }}
                            </p>

                            <!-- Nothing to play, so nothing to look at: the
                                 song says its name and stands aside. The void
                                 that a hidden player leaves behind reads as a
                                 failure, and the height belongs to the words. -->
                            <div v-if="!hasMedia" class="relative w-full max-w-sm mx-auto px-6 py-5 sm:py-7 text-center">
                                <p class="text-base sm:text-xl font-bold text-white leading-tight line-clamp-2 tracking-tight">{{ current?.name }}</p>
                                <p v-if="current?.artist" class="text-xs sm:text-sm font-medium text-slate-400 mt-1 truncate">{{ current.artist }}</p>
                                <p class="inline-flex items-center gap-1.5 mt-3 px-2.5 py-1 text-2xs font-bold text-slate-400 uppercase tracking-widest bg-white/5 rounded-full">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 10h16M4 14h12M4 18h7" />
                                    </svg>
                                    {{ t('playlist.lyrics_only') }}
                                </p>
                            </div>

                            <!-- Track player.
                                 There is no video to look at, so the screen has
                                 to carry the song by itself. A single soft glow
                                 behind the artwork gives the black somewhere to
                                 come from without becoming something to look at,
                                 and the tile shrinks: at ninety-six pixels it was
                                 the loudest thing on a screen whose subject is a
                                 title and a progress bar. -->
                            <div v-if="isAudio" class="relative w-full max-w-sm sm:max-w-md mx-auto px-6 py-6 sm:py-10 flex flex-col items-center text-center">
                                <div class="relative w-14 h-14 sm:w-20 sm:h-20 rounded-2xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-xl shadow-indigo-950/60 ring-1 ring-white/15 mb-4 sm:mb-6">
                                    <svg class="w-7 h-7 sm:w-10 sm:h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19.952 1.651a.75.75 0 0 1 .298.599V16.303a3 3 0 0 1-2.176 2.884l-1.32.377a2.553 2.553 0 1 1-1.403-4.909l2.311-.66a1.5 1.5 0 0 0 1.088-1.442V6.994l-9 2.572v9.737a3 3 0 0 1-2.176 2.884l-1.32.377a2.553 2.553 0 1 1-1.402-4.909l2.31-.66a1.5 1.5 0 0 0 1.088-1.442V5.25a.75.75 0 0 1 .544-.721l10.5-3a.75.75 0 0 1 .658.122Z" />
                                    </svg>
                                </div>

                                <p class="relative text-base sm:text-xl font-bold text-white leading-tight line-clamp-2 tracking-tight">{{ current?.name }}</p>
                                <p v-if="current?.artist" class="relative text-xs sm:text-sm font-medium text-slate-400 mt-1 truncate max-w-full">{{ current.artist }}</p>

                                <audio
                                    ref="audioEl"
                                    :src="current?._audioUrl"
                                    preload="auto"
                                    class="hidden"
                                    @play="audioPlaying = true"
                                    @pause="audioPlaying = false"
                                    @timeupdate="audioTime = audioEl?.currentTime ?? 0"
                                    @loadedmetadata="audioLength = audioEl?.duration ?? 0"
                                    @ended="onAudioEnded"
                                />

                                <div class="relative w-full mt-5 sm:mt-8">
                                    <input
                                        type="range"
                                        min="0"
                                        :max="audioLength || 0"
                                        :value="audioTime"
                                        step="0.5"
                                        @input="seekAudio"
                                        class="w-full accent-indigo-500 cursor-pointer"
                                        :aria-label="t('playlist.seek')"
                                    />
                                    <div class="flex justify-between text-2xs font-medium text-slate-400 tabular-nums mt-1">
                                        <span>{{ formatClock(audioTime) }}</span>
                                        <span>{{ formatClock(audioLength) }}</span>
                                    </div>
                                </div>

                                <div class="relative mt-5 sm:mt-7 flex items-center justify-center gap-5 sm:gap-7">
                                <button
                                    type="button"
                                    @click="playPrev"
                                    :disabled="currentIdx === 0"
                                    class="w-11 h-11 rounded-full flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 disabled:opacity-25 disabled:hover:bg-transparent transition"
                                    :aria-label="t('playlist.prev')"
                                >
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M6 4h2v16H6V4zm12 0v16l-10-8 10-8z" />
                                    </svg>
                                </button>

                                <button
                                    type="button"
                                    @click="toggleAudio"
                                    class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-white text-slate-900 flex items-center justify-center shadow-xl shadow-black/40 hover:scale-105 active:scale-95 transition"
                                    :aria-label="audioPlaying ? t('playlist.pause') : t('playlist.play')"
                                >
                                    <svg v-if="audioPlaying" class="w-6 h-6 sm:w-7 sm:h-7" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M6 4h4v16H6zM14 4h4v16h-4z" />
                                    </svg>
                                    <svg v-else class="w-6 h-6 sm:w-7 sm:h-7 ml-1" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                </button>

                                <button
                                    type="button"
                                    @click="playNext"
                                    :disabled="currentIdx >= playable.length - 1"
                                    class="w-11 h-11 rounded-full flex items-center justify-center text-white/80 hover:text-white hover:bg-white/10 disabled:opacity-25 disabled:hover:bg-transparent transition"
                                    :aria-label="t('playlist.next')"
                                >
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M16 4h2v16h-2V4zM6 4l10 8-10 8V4z" />
                                    </svg>
                                </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- The side panel: the running order, or the words.
                         Hidden for an external playlist, whose contents are
                         YouTube's to know and whose controls navigate it. -->
                    <!-- Wider than the video deserves on a desktop. The
                         lyrics are what someone stands and reads from; the
                         video is what plays while they do. -->
                    <div
                        v-if="!usingPlaylist"
                        class="flex-1 flex flex-col min-h-0"
                        :class="playerHidden
                            ? 'lg:max-w-3xl lg:mx-auto lg:w-full'
                            : 'lg:flex-none lg:w-[28rem] xl:w-[34rem] lg:border-l lg:border-white/10'"
                    >
                        <!-- Tabs, and only where there is a second thing to
                             show: a band with no lyrics saved should find this
                             screen exactly as it was. -->
                        <!-- The setlist is the panel; everything else is a
                             view laid over it.
                             Two tabs split the attention evenly between a list
                             people open this screen for and a reading mode
                             they ask for sometimes — and two tabs become four
                             the moment charts arrive. A menu names what you
                             are looking at and keeps the rest out of sight. -->
                        <div
                            v-if="anyLyrics"
                            class="shrink-0 flex items-center justify-between gap-2 px-3 py-2 border-b border-white/10"
                            data-panel-views
                        >
                            <p class="text-2xs font-bold text-slate-400 uppercase tracking-widest truncate">
                                {{ currentViewLabel }}
                            </p>

                            <div class="relative shrink-0">
                                <button
                                    type="button"
                                    @click.stop="viewsOpen = !viewsOpen"
                                    class="flex items-center gap-1.5 px-2.5 h-8 rounded-lg text-xs font-bold transition-colors"
                                    :class="panelTab === 'queue'
                                        ? 'text-slate-300 hover:text-white hover:bg-white/10'
                                        : 'bg-white text-slate-900'"
                                    :aria-expanded="viewsOpen"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 10h16M4 14h12M4 18h7" />
                                    </svg>
                                    {{ t('playlist.views') }}
                                    <svg
                                        class="w-3 h-3 transition-transform"
                                        :class="viewsOpen ? 'rotate-180' : ''"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <Transition
                                    enter-active-class="transition duration-150 ease-out"
                                    enter-from-class="opacity-0 scale-95"
                                    enter-to-class="opacity-100 scale-100"
                                    leave-active-class="transition duration-100 ease-in"
                                    leave-from-class="opacity-100 scale-100"
                                    leave-to-class="opacity-0 scale-95"
                                >
                                    <div
                                        v-if="viewsOpen"
                                        class="absolute right-0 top-full mt-1 w-48 z-30 origin-top-right bg-slate-800 border border-white/10 rounded-xl shadow-2xl overflow-hidden py-1"
                                    >
                                        <button
                                            v-for="view in panelViews"
                                            :key="view.id"
                                            type="button"
                                            @click.stop="chooseView(view.id)"
                                            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left text-xs font-semibold transition-colors"
                                            :class="panelTab === view.id ? 'text-white bg-white/10' : 'text-slate-300 hover:bg-white/5'"
                                        >
                                            <span class="flex-1 truncate">{{ view.label }}</span>

                                            <!-- A dot rather than a disabled
                                                 row: the view still opens, it
                                                 just has nothing for this one
                                                 song. -->
                                            <span v-if="view.empty" class="w-1.5 h-1.5 rounded-full bg-amber-400 shrink-0" />
                                            <svg
                                                v-else-if="panelTab === view.id"
                                                class="w-3.5 h-3.5 shrink-0"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                            </svg>
                                        </button>
                                    </div>
                                </Transition>
                            </div>
                        </div>

                        <!-- The words, with their own size, mode and structure
                             rail. The song controls go in its bar rather than
                             above it, so there is one row of controls here. -->
                        <div v-if="panelTab === 'lyrics'" class="flex-1 min-h-0 flex flex-col">
                            <div class="flex-1 min-h-0 flex flex-col">
                            <LyricsPanel ref="lyricsPanel" :blocks="currentLyrics" dark>
                                <template #controls>
                                    <!-- Putting the player away belongs with
                                         the words, not in a header already
                                         three buttons deep on a phone. -->
                                    <button
                                        type="button"
                                        @click="togglePlayer"
                                        class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                        :class="playerHidden ? 'bg-white text-slate-900' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                                        :aria-pressed="playerHidden"
                                        :title="t('playlist.lyrics_only')"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 10h16M4 14h12M4 18h7" />
                                        </svg>
                                    </button>

                                    <div class="flex items-center gap-0.5 ml-1 pl-1.5 border-l border-white/10">
                                        <button
                                            type="button"
                                            @click="playPrev"
                                            :disabled="currentIdx === 0"
                                            class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 disabled:opacity-25 transition-colors"
                                            :aria-label="t('playlist.prev')"
                                        >
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M6 4h2v16H6V4zm12 0v16l-10-8 10-8z" />
                                            </svg>
                                        </button>
                                        <span class="text-2xs font-bold text-slate-400 tabular-nums px-0.5">{{ currentIdx + 1 }}/{{ playable.length }}</span>
                                        <button
                                            type="button"
                                            @click="playNext"
                                            :disabled="currentIdx >= playable.length - 1"
                                            class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 disabled:opacity-25 transition-colors"
                                            :aria-label="t('playlist.next')"
                                        >
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M16 4h2v16h-2V4zM6 4l10 8-10 8V4z" />
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </LyricsPanel>
                            </div>

                            <!-- What follows, under the words rather than
                                 above them.
                                 It sat at the top with its own prev and next,
                                 repeating the title already in the header and
                                 the controls already in the row below — four
                                 bars of chrome before a single line of lyric.
                                 One line, at the end, which is where anyone
                                 finishing a song is looking anyway. -->
                            <button
                                v-if="upNext"
                                type="button"
                                @click="playNext"
                                class="shrink-0 flex items-center gap-2 px-4 py-2.5 border-t border-white/10 bg-white/5 hover:bg-white/10 text-left transition-colors"
                            >
                                <span class="flex-1 min-w-0 text-2xs font-medium text-slate-400 truncate">
                                    {{ t('playlist.up_next', { name: upNext.name }) }}
                                </span>
                                <svg class="w-4 h-4 shrink-0 text-slate-400" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 4h2v16h-2V4zM6 4l10 8-10 8V4z" />
                                </svg>
                            </button>
                        </div>

                        <!-- The chart, read on a stand in a dark room. The
                             same reader the editor previews with, so what was
                             written is what is seen here. -->
                        <div v-else-if="panelTab === 'chords'" class="flex-1 min-h-0 flex flex-col">
                            <div class="flex items-center gap-2 px-4 py-2 border-b border-white/10 shrink-0">
                                <button
                                    type="button"
                                    @click="togglePlayer"
                                    class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg transition-colors"
                                    :class="playerHidden ? 'bg-white text-slate-900' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                                    :aria-pressed="playerHidden"
                                    :title="t('playlist.lyrics_only')"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 10h16M4 14h12M4 18h7" />
                                    </svg>
                                </button>

                                <!-- Moved, never saved: the band's chart stays
                                     in the key it was written in. -->
                                <div class="flex items-center gap-0.5 ml-1 pl-1.5 border-l border-white/10">
                                    <button
                                        type="button"
                                        @click="chartSteps -= 1"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition-colors"
                                        :aria-label="t('songs.chords.down')"
                                    >−</button>

                                    <button
                                        type="button"
                                        @click="chartSteps = 0"
                                        :disabled="!chartSteps"
                                        class="min-w-[3rem] h-8 px-1 flex items-center justify-center rounded-lg text-2xs font-bold tabular-nums transition-colors"
                                        :class="chartSteps
                                            ? 'text-white bg-white/10 hover:bg-white/20'
                                            : 'text-slate-400'"
                                        :title="t('songs.chords.reset')"
                                    >{{ sungKey || (chartSteps > 0 ? '+' + chartSteps : chartSteps) }}</button>

                                    <button
                                        type="button"
                                        @click="chartSteps += 1"
                                        class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition-colors"
                                        :aria-label="t('songs.chords.up')"
                                    >+</button>
                                </div>

                                <div class="flex items-center gap-0.5 ml-auto">
                                    <button
                                        type="button"
                                        @click="playPrev"
                                        :disabled="currentIdx === 0"
                                        class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 disabled:opacity-25 transition-colors"
                                        :aria-label="t('playlist.prev')"
                                    >
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M6 4h2v16H6V4zm12 0v16l-10-8 10-8z" />
                                        </svg>
                                    </button>
                                    <span class="text-2xs font-bold text-slate-400 tabular-nums px-0.5">{{ currentIdx + 1 }}/{{ playable.length }}</span>
                                    <button
                                        type="button"
                                        @click="playNext"
                                        :disabled="currentIdx >= playable.length - 1"
                                        class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 disabled:opacity-25 transition-colors"
                                        :aria-label="t('playlist.next')"
                                    >
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M16 4h2v16h-2V4zM6 4l10 8-10 8V4z" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="flex-1 min-h-0 overflow-y-auto px-4 py-4">
                                <ChordChartReader
                                    :blocks="currentChart"
                                    :chords="current?._chords ?? {}"
                                    :semitones="chartSteps"
                                    dark
                                />
                            </div>
                        </div>

                        <template v-else>
                        <!-- Controls -->
                        <div class="flex items-center gap-2 px-4 py-2.5 border-b border-white/10 shrink-0">
                            <button
                                @click="playPrev"
                                :disabled="currentIdx === 0"
                                class="w-9 h-9 flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-30 text-white transition-colors"
                                :aria-label="t('playlist.prev')"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M6 4h2v16H6V4zm12 0v16l-10-8 10-8z" />
                                </svg>
                            </button>
                            <button
                                @click="playNext"
                                :disabled="currentIdx >= playable.length - 1"
                                class="w-9 h-9 flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 disabled:opacity-30 text-white transition-colors"
                                :aria-label="t('playlist.next')"
                            >
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M16 4h2v16h-2V4zM6 4l10 8-10 8V4z" />
                                </svg>
                            </button>
                            <p class="text-xs text-slate-300 ml-auto">{{ currentIdx + 1 }} / {{ playable.length }}</p>
                        </div>

                        <!-- Queue list -->
                        <div class="overflow-y-auto flex-1 px-2 py-2 space-y-1">
                            <button
                                v-for="(s, i) in playable"
                                :key="i"
                                type="button"
                                @click="loadAt(i)"
                                :class="[
                                    'w-full flex items-start gap-3 rounded-lg px-3 py-2.5 text-left transition-colors',
                                    i === currentIdx
                                        ? 'bg-indigo-600/80 text-white'
                                        : 'text-slate-200 hover:bg-white/10',
                                ]"
                            >
                                <!-- Number / play indicator -->
                                <span class="shrink-0 w-6 flex items-center justify-center pt-0.5">
                                    <svg v-if="i === currentIdx" class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                    <span v-else class="text-xs font-bold text-slate-400">{{ i + 1 }}</span>
                                </span>

                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate">{{ s.name }}</p>
                                    <p class="text-xs font-medium opacity-80 truncate">
                                        <span v-if="s.artist">{{ s.artist }} · </span>{{ s.version
                                        }}<span v-if="s.key"> · {{ s.key }}</span>
                                    </p>

                                    <!-- Whatever the team needs to know about this song, on
                                         every row rather than only the one playing: the point
                                         of the queue is to see how the set goes before it
                                         starts. Set apart with a rule so it reads as an
                                         annotation and not as more metadata. -->
                                    <div
                                        v-if="s.notes || s._startLabel"
                                        class="mt-1.5 border-l-2 pl-2.5 py-0.5 space-y-0.5"
                                        :class="i === currentIdx ? 'border-white/50' : 'border-indigo-400/50'"
                                    >
                                        <p
                                            v-if="s._startLabel"
                                            class="text-2xs font-semibold uppercase tracking-wide"
                                            :class="i === currentIdx ? 'text-white/80' : 'text-indigo-300'"
                                        >{{ t('services.starts_at', { time: s._startLabel }) }}</p>
                                        <p
                                            v-if="s.notes"
                                            class="text-xs leading-relaxed whitespace-pre-wrap"
                                            :class="i === currentIdx ? 'text-white/90' : 'text-slate-300'"
                                        >{{ s.notes }}</p>
                                    </div>
                                </div>
                            </button>
                        </div>
                        </template>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
