<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    open:  { type: Boolean, default: false },
    songs: { type: Array,   default: () => [] }, // { name, artist, version, key, youtube_url }
});

const emit = defineEmits(['close']);

function extractId(url) {
    if (!url) return null;
    const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]{11})/);
    return m ? m[1] : null;
}

const playable = computed(() =>
    props.songs
        .map(s => ({ ...s, _videoId: extractId(s.youtube_url) }))
        .filter(s => s._videoId)
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

function buildPlayer() {
    if (!apiReady.value || !playerEl.value || !playable.value.length) return;
    if (ytPlayer) { try { ytPlayer.destroy(); } catch {} ytPlayer = null; }

    ytPlayer = new window.YT.Player(playerEl.value, {
        videoId: playable.value[currentIdx.value]._videoId,
        playerVars: { playsinline: 1, rel: 0, modestbranding: 1 },
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
    if (!ytPlayer || !playable.value[idx]) return;
    currentIdx.value = idx;
    ytPlayer.loadVideoById(playable.value[idx]._videoId);
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
        loadYouTubeApi();
    } else if (ytPlayer) {
        try { ytPlayer.destroy(); } catch {}
        ytPlayer = null;
    }
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

const current = computed(() => playable.value[currentIdx.value]);
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
                        <p class="text-[10px] font-semibold text-indigo-300 uppercase tracking-widest">{{ t('playlist.title') }}</p>
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
                    <!-- Player -->
                    <div class="lg:flex-1 bg-black flex items-center justify-center">
                        <div class="w-full aspect-video max-h-full">
                            <div ref="playerEl" class="w-full h-full" />
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
                                    'w-full flex items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-colors',
                                    i === currentIdx
                                        ? 'bg-indigo-600/80 text-white'
                                        : 'text-slate-200 hover:bg-white/10',
                                ]"
                            >
                                <!-- Number / play indicator -->
                                <span class="shrink-0 w-6 flex items-center justify-center">
                                    <svg v-if="i === currentIdx" class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z" />
                                    </svg>
                                    <span v-else class="text-[11px] font-bold text-slate-400">{{ i + 1 }}</span>
                                </span>

                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium truncate">{{ s.name }}</p>
                                    <p class="text-[11px] opacity-70 truncate">
                                        <span v-if="s.artist">{{ s.artist }} · </span>{{ s.version
                                        }}<span v-if="s.key"> · {{ s.key }}</span>
                                    </p>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
