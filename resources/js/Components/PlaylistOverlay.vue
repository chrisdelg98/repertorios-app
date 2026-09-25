<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { parseYouTube, formatStart } from '@/Utils/youtube';

const { t } = useI18n();

const props = defineProps({
    open:  { type: Boolean, default: false },
    songs: { type: Array,   default: () => [] }, // { name, artist, version, key, youtube_url, audio, notes }
});

const emit = defineEmits(['close']);

/**
 * A song is playable through one of two sources, and the uploaded track wins.
 *
 * It is what the band chose deliberately for rehearsing: no ads, no intro to
 * skip, and the exact arrangement they play. YouTube stays as the fallback for
 * everything that has no track yet.
 */
const playable = computed(() =>
    props.songs
        .map(s => {
            if (s.audio?.url) {
                return { ...s, _source: 'audio', _audioUrl: s.audio.url, _start: 0, _startLabel: '' };
            }

            const parsed = parseYouTube(s.youtube_url);

            return parsed
                ? {
                    ...s,
                    _source: 'youtube',
                    _videoId: parsed.id,
                    _start: parsed.start,
                    _startLabel: formatStart(parsed.start),
                }
                : null;
        })
        .filter(Boolean)
);

const currentIdx = ref(0);
let ytPlayer    = null;
const playerEl  = ref(null);
const apiReady  = ref(false);

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
const isAudio = computed(() => current.value?._source === 'audio');

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
    if (!apiReady.value || !playerEl.value || !playable.value.length) return;
    if (playable.value[currentIdx.value]?._source !== 'youtube') return;
    if (ytPlayer) { try { ytPlayer.destroy(); } catch {} ytPlayer = null; }

    const first = playable.value[currentIdx.value];

    ytPlayer = new window.YT.Player(playerEl.value, {
        videoId: first._videoId,
        // `start` honours the ?t= of the pasted link — a song that begins at
        // 1:13 of a longer video should not play the intro every time.
        playerVars: { playsinline: 1, rel: 0, modestbranding: 1, start: first._start || 0 },
        events: {
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

    if (song._source === 'audio') {
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

    ytPlayer.loadVideoById({
        videoId: song._videoId,
        startSeconds: song._start || 0,
    });
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

        // The API is loaded even when the first song is a track: the queue can
        // reach a YouTube song later, and loading it then would stall playback.
        loadYouTubeApi();

        if (playable.value[0]?._source === 'audio') startAudio();

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
                        <p class="text-sm font-bold text-white truncate">
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
                <div v-if="!playable.length" class="flex-1 flex items-center justify-center px-6 text-center">
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
                    <div class="lg:flex-1 bg-black flex items-center justify-center relative">
                        <div class="w-full aspect-video max-h-full" :class="isAudio ? 'invisible absolute inset-0' : ''">
                            <div ref="playerEl" class="w-full h-full" />
                        </div>

                        <!-- Track player: no video to show, so the song itself is the screen -->
                        <div v-if="isAudio" class="w-full px-6 py-10 sm:py-16 flex flex-col items-center text-center">
                            <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-indigo-600 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-900/40 mb-5">
                                <svg class="w-11 h-11 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                                </svg>
                            </div>

                            <p class="text-lg font-bold text-white leading-tight">{{ current?.name }}</p>
                            <p v-if="current?.artist" class="text-sm font-medium text-slate-300 mt-1">{{ current.artist }}</p>

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

                            <div class="w-full max-w-md mt-7">
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
                                class="mt-5 w-16 h-16 rounded-full bg-white text-slate-900 flex items-center justify-center shadow-lg active:scale-95 transition"
                                :aria-label="audioPlaying ? t('playlist.pause') : t('playlist.play')"
                            >
                                <svg v-if="audioPlaying" class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M6 4h4v16H6zM14 4h4v16h-4z" />
                                </svg>
                                <svg v-else class="w-7 h-7 ml-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Queue -->
                    <div class="lg:w-96 lg:border-l lg:border-white/10 flex flex-col min-h-0">
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
