<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { parseYouTube, formatStart, parsePlaylist } from '@/Utils/youtube';

const { t } = useI18n();

const props = defineProps({
    open:  { type: Boolean, default: false },
    songs: { type: Array,   default: () => [] }, // { name, artist, version, key, youtube_url, audio, notes }
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

            if (!audioUrl && !parsed) return null;

            return {
                ...s,
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

    currentIdx.value = idx;

    if (sourceOf(idx) === 'audio') {
        // Stop the video before the track starts, or both play at once.
        if (ytPlayer) { try { ytPlayer.stopVideo(); } catch {} }
        startAudio();
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

    ytPlayer.loadVideoById({
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

        if (sourceOf(0) === 'audio') startAudio();

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
                <div class="flex items-center justify-between px-4 py-3 border-b border-white/10 shrink-0">
                    <div class="min-w-0">
                        <p class="text-2xs font-semibold text-indigo-300 uppercase tracking-widest">{{ t('playlist.title') }}</p>
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
                        @click="close"
                        class="shrink-0 w-9 h-9 flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors"
                        :aria-label="t('playlist.close')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

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
                    <div class="shrink-0 lg:flex-1 bg-black flex flex-col min-h-0">
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
                        <div class="flex-1 flex flex-col items-center justify-center min-h-0 relative">
                            <div class="w-full aspect-video max-h-full" :class="isAudio ? 'invisible absolute inset-0' : 'relative'">
                                <div ref="playerEl" class="w-full h-full" />

                                <div
                                    v-if="!isAudio && !playerReady"
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

                            <!-- Track player: no video to show, so the song itself is the screen -->
                            <div v-if="isAudio" class="w-full max-w-lg mx-auto px-6 py-5 sm:py-8 flex flex-col items-center text-center">
                                <div class="w-16 h-16 sm:w-24 sm:h-24 rounded-2xl sm:rounded-3xl bg-gradient-to-br from-indigo-600 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-900/40 mb-3 sm:mb-5">
                                    <svg class="w-8 h-8 sm:w-11 sm:h-11 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                                    </svg>
                                </div>

                                <p class="text-base sm:text-lg font-bold text-white leading-tight line-clamp-2">{{ current?.name }}</p>
                                <p v-if="current?.artist" class="text-xs sm:text-sm font-medium text-slate-300 mt-0.5 sm:mt-1 truncate max-w-full">{{ current.artist }}</p>

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

                                <div class="w-full max-w-md mt-4 sm:mt-7">
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

                                <button
                                    type="button"
                                    @click="toggleAudio"
                                    class="mt-4 sm:mt-5 w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-white text-slate-900 flex items-center justify-center shadow-lg active:scale-95 transition"
                                    :aria-label="audioPlaying ? t('playlist.pause') : t('playlist.play')"
                                >
                                    <svg v-if="audioPlaying" class="w-6 h-6 sm:w-7 sm:h-7" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M6 4h4v16H6zM14 4h4v16h-4z" />
                                    </svg>
                                    <svg v-else class="w-6 h-6 sm:w-7 sm:h-7 ml-1" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Queue. Hidden for an external playlist: its contents
                         are YouTube's to know, and its own controls navigate it. -->
                    <div v-if="!usingPlaylist" class="flex-1 lg:flex-none lg:w-96 lg:border-l lg:border-white/10 flex flex-col min-h-0">
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
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
