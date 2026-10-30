<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { parseYouTube } from '@/Utils/youtube';
import { youTubeApi } from '@/Utils/youtubeApi';

/**
 * A transport bar for working a chord out by ear.
 *
 * Nothing here is for listening to a song; it is for hearing four seconds of
 * one over and over until the chord gives itself up. That is why the nudges
 * are one, two and five seconds rather than the usual ten — a chord change is
 * shorter than a lyric — and why the loop and the half speed sit on the bar
 * instead of behind a menu.
 *
 * The video is off screen by default and a button brings it back, because on a
 * worship video the guitarist's left hand often answers the question sooner
 * than the ear does.
 */

const props = defineProps({
    url: { type: String, default: '' },
});

const holder = ref(null);

let player = null;
let ticker = null;

const ready = ref(false);
const failed = ref(false);
const playing = ref(false);
const at = ref(0);
const duration = ref(0);

const RATES = [1, 0.75, 0.5];
const rate = ref(1);

const videoShown = ref(false);

// ── The loop ─────────────────────────────────────────────────────
const loopA = ref(null);
const loopB = ref(null);
const looping = ref(false);

function clock(seconds) {
    const total = Math.max(0, Math.floor(seconds || 0));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

// ── Driving it ───────────────────────────────────────────────────
function tick() {
    if (!player?.getCurrentTime) return;

    try {
        at.value = player.getCurrentTime() ?? 0;
        duration.value = player.getDuration() ?? 0;
    } catch {
        return;
    }

    // Checked on the same beat as the clock: a second timer for the loop would
    // drift against the one showing where we are.
    if (looping.value && loopA.value !== null && loopB.value !== null && at.value >= loopB.value) {
        try {
            player.seekTo(loopA.value, true);
        } catch { /* between videos */ }
    }
}

function toggle() {
    if (!player) return;

    try {
        playing.value ? player.pauseVideo() : player.playVideo();
    } catch { /* not ready yet */ }
}

/**
 * Move by a few seconds without losing where we were.
 *
 * The time is read from the player rather than from the clock above, which is
 * up to a fifth of a second stale: five nudges in a row would compound that
 * into a drift you can hear.
 */
function nudge(delta) {
    if (!player?.seekTo) return;

    try {
        const now = player.getCurrentTime() ?? 0;
        const next = Math.max(0, Math.min(now + delta, duration.value || Infinity));

        player.seekTo(next, true);
        at.value = next;
    } catch { /* not ready yet */ }
}

function cycleRate() {
    const next = RATES[(RATES.indexOf(rate.value) + 1) % RATES.length];

    rate.value = next;

    try {
        player?.setPlaybackRate(next);
    } catch { /* not ready yet */ }
}

function markA() {
    loopA.value = Math.max(0, at.value);

    // A marked past B is the usual way of saying "start the loop here
    // instead"; dropping B is kinder than refusing.
    if (loopB.value !== null && loopB.value <= loopA.value) loopB.value = null;
}

function markB() {
    loopB.value = at.value;

    // Marking only the end is the common case: you hear the chord land, then
    // ask to hear the few seconds before it again.
    if (loopA.value === null || loopA.value >= loopB.value) {
        loopA.value = Math.max(0, loopB.value - 4);
    }

    looping.value = true;
}

function clearLoop() {
    loopA.value = null;
    loopB.value = null;
    looping.value = false;
}

// ── Keys, so the mouse can stay on the lyric ─────────────────────
function onKey(event) {
    if (!ready.value) return;
    if (event.metaKey || event.ctrlKey || event.altKey) return;

    const tag = event.target?.tagName;
    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || event.target?.isContentEditable) return;

    switch (event.key) {
        case ' ':
            event.preventDefault();
            toggle();
            break;
        case 'ArrowLeft':
            event.preventDefault();
            nudge(event.shiftKey ? -5 : -2);
            break;
        case 'ArrowRight':
            event.preventDefault();
            nudge(event.shiftKey ? 5 : 2);
            break;
        case 'a':
        case 'A':
            markA();
            break;
        case 'b':
        case 'B':
            markB();
            break;
        case 'l':
        case 'L':
            if (loopA.value !== null && loopB.value !== null) looping.value = !looping.value;
            break;
    }
}

// ── Life ─────────────────────────────────────────────────────────
function build() {
    const parsed = parseYouTube(props.url);

    if (!parsed || !holder.value) return;

    player = new window.YT.Player(holder.value, {
        videoId: parsed.id,
        playerVars: {
            controls: 0,
            disablekb: 1,
            modestbranding: 1,
            rel: 0,
            playsinline: 1,
            start: parsed.start || 0,
        },
        events: {
            onReady: (event) => {
                ready.value = true;
                duration.value = event.target.getDuration() ?? 0;
                at.value = event.target.getCurrentTime() ?? 0;
            },
            onStateChange: (event) => {
                playing.value = event.data === window.YT.PlayerState.PLAYING;
            },
            onError: () => { failed.value = true; },
        },
    });
}

onMounted(async () => {
    try {
        await youTubeApi();
    } catch {
        failed.value = true;
        return;
    }

    build();

    ticker = setInterval(tick, 200);
    document.addEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    clearInterval(ticker);
    document.removeEventListener('keydown', onKey);

    try {
        player?.destroy();
    } catch { /* already gone */ }
});

// Another version of the song means another video in the same player.
watch(() => props.url, (next) => {
    const parsed = parseYouTube(next);

    clearLoop();
    at.value = 0;
    playing.value = false;

    if (!parsed || !player?.cueVideoById) return;

    try {
        player.cueVideoById({ videoId: parsed.id, startSeconds: parsed.start || 0 });
    } catch { /* still coming up */ }
});
</script>

<template>
    <div class="relative bg-white rounded-xl border border-slate-200 shadow-sm px-3 py-2">
        <div class="flex items-center gap-1 overflow-x-auto">
            <button
                type="button"
                @click="toggle"
                :disabled="!ready"
                class="w-9 h-9 shrink-0 flex items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-40 transition-colors"
                :title="playing ? 'Pausar (espacio)' : 'Reproducir (espacio)'"
            >
                <svg v-if="playing" class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6 5h4v14H6zM14 5h4v14h-4z" />
                </svg>
                <svg v-else class="w-4 h-4 ml-0.5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M7 4l12 8-12 8z" />
                </svg>
            </button>

            <!-- The number is the label: at this size an icon meaning "two
                 seconds back" is a worse icon than "−2". -->
            <div class="flex items-center gap-0.5 shrink-0 ml-1">
                <button
                    v-for="d in [-5, -2, -1]"
                    :key="d"
                    type="button"
                    @click="nudge(d)"
                    :disabled="!ready"
                    class="w-9 h-9 flex items-center justify-center rounded-lg bg-slate-100 text-slate-700 text-xs font-bold tabular-nums hover:bg-slate-200 disabled:opacity-40 transition-colors"
                    :title="`${-d} segundos atrás`"
                >−{{ -d }}</button>
            </div>

            <div class="flex items-center gap-0.5 shrink-0">
                <button
                    v-for="d in [1, 2, 5]"
                    :key="d"
                    type="button"
                    @click="nudge(d)"
                    :disabled="!ready"
                    class="w-9 h-9 flex items-center justify-center rounded-lg bg-slate-100 text-slate-700 text-xs font-bold tabular-nums hover:bg-slate-200 disabled:opacity-40 transition-colors"
                    :title="`${d} segundos adelante`"
                >+{{ d }}</button>
            </div>

            <span class="shrink-0 ml-2 text-xs font-semibold text-slate-900 tabular-nums">
                {{ clock(at) }}<span class="text-slate-400">/{{ clock(duration) }}</span>
            </span>

            <div class="shrink-0 w-px h-6 bg-slate-200 mx-2"></div>

            <!-- Half speed is where a dense chord separates into notes. -->
            <button
                type="button"
                @click="cycleRate"
                :disabled="!ready"
                class="h-9 px-2.5 shrink-0 flex items-center justify-center rounded-lg text-xs font-bold tabular-nums transition-colors disabled:opacity-40"
                :class="rate === 1
                    ? 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                    : 'bg-amber-100 text-amber-800 hover:bg-amber-200'"
                title="Velocidad"
            >{{ rate }}×</button>

            <div class="flex items-center gap-0.5 shrink-0 ml-1">
                <button
                    type="button"
                    @click="markA"
                    :disabled="!ready"
                    class="w-9 h-9 flex items-center justify-center rounded-lg text-xs font-bold transition-colors disabled:opacity-40"
                    :class="loopA !== null ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    title="Marcar el inicio del bucle (A)"
                >A</button>

                <button
                    type="button"
                    @click="markB"
                    :disabled="!ready"
                    class="w-9 h-9 flex items-center justify-center rounded-lg text-xs font-bold transition-colors disabled:opacity-40"
                    :class="loopB !== null ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    title="Marcar el final del bucle (B)"
                >B</button>

                <button
                    type="button"
                    @click="looping = !looping"
                    :disabled="loopA === null || loopB === null"
                    class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors disabled:opacity-40"
                    :class="looping ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                    title="Repetir A–B (L)"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 9h12a4 4 0 010 8H8m-4-8l3-3M4 9l3 3" />
                    </svg>
                </button>
            </div>

            <button
                v-if="loopA !== null || loopB !== null"
                type="button"
                @click="clearLoop"
                class="shrink-0 ml-1.5 text-2xs font-semibold text-slate-500 hover:text-slate-900 tabular-nums whitespace-nowrap"
                title="Quitar el bucle"
            >{{ clock(loopA ?? 0) }}–{{ loopB === null ? '…' : clock(loopB) }} ✕</button>

            <button
                type="button"
                @click="videoShown = !videoShown"
                class="w-9 h-9 shrink-0 ml-auto flex items-center justify-center rounded-lg transition-colors"
                :class="videoShown ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                title="Ver el video"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.5-2.5v9L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" />
                </svg>
            </button>
        </div>

        <p v-if="failed" class="text-2xs text-amber-700 mt-1.5">
            No se pudo cargar el video de YouTube.
        </p>

        <!-- One container, two places. Moving the node between them would tear
             the player down and lose the position mid-loop, so only the
             wrapper's classes change: off screen, or a panel under the bar. -->
        <div
            :class="videoShown
                ? 'mt-2 w-full sm:w-72 aspect-video rounded-lg overflow-hidden bg-black'
                : 'absolute -left-[9999px] top-0 w-72 h-40 overflow-hidden'"
        >
            <div ref="holder" class="w-full h-full"></div>
        </div>
    </div>
</template>
