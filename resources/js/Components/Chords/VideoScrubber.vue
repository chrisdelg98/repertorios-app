<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { parseYouTube } from '@/Utils/youtube';
import { youTubeApi } from '@/Utils/youtubeApi';
import { useI18n } from 'vue-i18n';

/**
 * A transport bar for working a chord out by ear.
 *
 * Nothing here is for listening to a song; it is for hearing four seconds of
 * one over and over until the chord gives itself up. That is why the nudges
 * are one, two and five seconds rather than the usual ten — a chord change is
 * shorter than a lyric — and why the loop and the half speed sit on the bar
 * instead of behind a menu.
 *
 * It drives a track or a video with one set of controls. They are not the same
 * machine — an element the page owns, against a player in somebody else's
 * iframe — so everything below asks the engine rather than reaching for a
 * player, and the rest of the file never learns which is playing.
 *
 * A video is off screen by default and a button brings it back, because on a
 * worship recording the guitarist's left hand often answers the question
 * sooner than the ear does. A track has nothing to show, so it does not offer.
 */

const { t } = useI18n();

const props = defineProps({
    /** { kind: 'audio' | 'youtube', url } — or nothing to play. */
    source: { type: Object, default: null },
    /** On a dark stage in the player, on white in the editor. */
    dark: { type: Boolean, default: false },
    /*
     * Where to put the picture, when the layout wants it somewhere else.
     *
     * The parent hands over an element and the player is built into it. The
     * alternative — rendering it here and moving the node — reloads the iframe
     * every time, which on a four-second loop is the whole point lost.
     */
    stage: { type: Object, default: null },
    /*
     * What else could be played. Shown in the bar rather than next to it:
     * which recording is being worked is part of the transport, and two boxes
     * side by side read as two unrelated things.
     */
    sources: { type: Array, default: () => [] },
    sourceIndex: { type: Number, default: 0 },
});

const emit = defineEmits(['update:sourceIndex']);

const isVideo = computed(() => props.source?.kind === 'youtube');

/*
 * Whole class strings rather than tokens joined at runtime: Tailwind reads
 * this file as source and only finds what is spelled out in it.
 */
const skin = computed(() => (props.dark
    ? {
        card: 'bg-white/5 border-white/10',
        key: 'bg-white/10 text-slate-200 hover:bg-white/20',
        time: 'text-white',
        dim: 'text-slate-500',
        rule: 'bg-white/15',
        marked: 'bg-indigo-500/30 text-indigo-100',
        fast: 'bg-amber-400/20 text-amber-200 hover:bg-amber-400/30',
        quiet: 'text-slate-400 hover:text-white',
        lit: 'bg-white text-slate-900',
        chosen: 'bg-white text-slate-900',
        rail: 'bg-white/15',
        edge: 'border-white/10',
    }
    : {
        card: 'bg-white border-slate-200 shadow-sm',
        key: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
        time: 'text-slate-900',
        dim: 'text-slate-400',
        rule: 'bg-slate-200',
        marked: 'bg-indigo-100 text-indigo-700',
        fast: 'bg-amber-100 text-amber-800 hover:bg-amber-200',
        quiet: 'text-slate-500 hover:text-slate-900',
        lit: 'bg-slate-900 text-white',
        chosen: 'bg-slate-900 text-white',
        rail: 'bg-slate-200',
        edge: 'border-slate-200',
    }));

const holder = ref(null);
const audioEl = ref(null);

let player = null;
let ticker = null;

/*
 * What the controls talk to.
 *
 * The audio element answers for itself; the YouTube player answers through an
 * API that throws while it is still coming up, which is why every call here is
 * guarded rather than every call site.
 */
const engine = computed(() => (isVideo.value
    ? {
        time: () => safely(() => player?.getCurrentTime() ?? 0, 0),
        length: () => safely(() => player?.getDuration() ?? 0, 0),
        play: () => safely(() => player?.playVideo()),
        pause: () => safely(() => player?.pauseVideo()),
        seek: (to) => safely(() => player?.seekTo(to, true)),
        rate: (value) => safely(() => player?.setPlaybackRate(value)),
    }
    : {
        time: () => audioEl.value?.currentTime ?? 0,
        length: () => (Number.isFinite(audioEl.value?.duration) ? audioEl.value.duration : 0),
        play: () => audioEl.value?.play().catch(() => { failed.value = true; }),
        pause: () => audioEl.value?.pause(),
        seek: (to) => { if (audioEl.value) audioEl.value.currentTime = to; },
        rate: (value) => { if (audioEl.value) audioEl.value.playbackRate = value; },
    }));

function safely(run, fallback = undefined) {
    try {
        return run();
    } catch {
        return fallback;
    }
}

const ready = ref(false);
const failed = ref(false);
const playing = ref(false);
const at = ref(0);
const duration = ref(0);

const RATES = [1, 0.75, 0.5];
const rate = ref(1);

const videoShown = ref(false);

/*
 * The seek bar.
 *
 * Its absence was the hole in all of this: every control moved by a fixed
 * number of seconds, so reaching the bridge of a six-minute recording meant
 * pressing +5 fifty times. It also gives the loop somewhere to be seen — A and
 * B as marks on the line rather than two letters that light up.
 */
const rail = ref(null);
let scrubbing = false;

const progress = computed(() => (duration.value ? Math.min(1, at.value / duration.value) : 0));

/** The A–B region, as a band drawn on the rail. */
const loopBand = computed(() => {
    if (!duration.value || loopA.value === null) return null;

    const from = Math.min(1, loopA.value / duration.value);
    const to = loopB.value === null ? from : Math.min(1, loopB.value / duration.value);

    return { left: `${from * 100}%`, width: `${Math.max(0, to - from) * 100}%` };
});

function seekFromPointer(event) {
    if (!rail.value || !duration.value) return;

    const box = rail.value.getBoundingClientRect();
    const ratio = Math.min(1, Math.max(0, (event.clientX - box.left) / box.width));
    const to = ratio * duration.value;

    engine.value.seek(to);
    at.value = to;
}

function scrubStart(event) {
    if (!ready.value) return;

    scrubbing = true;
    rail.value?.setPointerCapture?.(event.pointerId);
    seekFromPointer(event);
}

function scrubMove(event) {
    if (scrubbing) seekFromPointer(event);
}

function scrubEnd(event) {
    scrubbing = false;
    rail.value?.releasePointerCapture?.(event.pointerId);
}

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
    if (!ready.value) return;

    at.value = engine.value.time();
    duration.value = engine.value.length();

    // Checked on the same beat as the clock: a second timer for the loop would
    // drift against the one showing where we are.
    if (looping.value && loopA.value !== null && loopB.value !== null && at.value >= loopB.value) {
        engine.value.seek(loopA.value);
    }
}

function toggle() {
    if (!ready.value) return;

    playing.value ? engine.value.pause() : engine.value.play();
}

/**
 * Move by a few seconds without losing where we were.
 *
 * The time is read from the player rather than from the clock above, which is
 * up to a fifth of a second stale: five nudges in a row would compound that
 * into a drift you can hear.
 */
function nudge(delta) {
    if (!ready.value) return;

    const now = engine.value.time();
    const next = Math.max(0, Math.min(now + delta, duration.value || Infinity));

    engine.value.seek(next);
    at.value = next;
}

function cycleRate() {
    const next = RATES[(RATES.indexOf(rate.value) + 1) % RATES.length];

    rate.value = next;
    engine.value.rate(next);
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
function buildPlayer() {
    const parsed = parseYouTube(props.source?.url);
    const into = props.stage ?? holder.value;

    if (!parsed || !into || player) return;

    player = new window.YT.Player(into, {
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
                engine.value.rate(rate.value);
            },
            onStateChange: (event) => {
                playing.value = event.data === window.YT.PlayerState.PLAYING;
            },
            onError: () => { failed.value = true; },
        },
    });
}

/*
 * The YouTube API is fetched only when a video is actually asked for.
 *
 * A band that works from tracks never loads it, and a page that does not need
 * a third-party script should not go and get one.
 */
async function openVideo() {
    try {
        await youTubeApi();
    } catch {
        failed.value = true;
        return;
    }

    buildPlayer();
}

/*
 * A stage given by the parent arrives as its element is mounted, which can be
 * after this component is. Waiting for it beats guessing the order.
 */
watch(() => props.stage, (into) => {
    if (into && isVideo.value && !player) openVideo();
});

onMounted(() => {
    if (isVideo.value) openVideo();

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

// Another source is another recording: the loop marked on the last one is
// seconds into something else.
watch(() => props.source, (next, previous) => {
    if (next?.url === previous?.url) return;

    clearLoop();
    at.value = 0;
    duration.value = 0;
    playing.value = false;
    failed.value = false;

    safely(() => (isVideo.value ? player?.pauseVideo() : audioEl.value?.pause()));

    if (!isVideo.value) {
        ready.value = false;
        return;
    }

    ready.value = !!player;

    if (!player) {
        openVideo();
        return;
    }

    const parsed = parseYouTube(next?.url);

    if (parsed) {
        safely(() => player.cueVideoById({ videoId: parsed.id, startSeconds: parsed.start || 0 }));
    }
});
</script>

<template>
    <div class="relative rounded-xl border px-2 py-1.5" :class="skin.card">
        <div class="flex items-center gap-1.5">
            <!-- Which recording, first: everything to the right acts on it. -->
            <div v-if="sources.length > 1" class="flex items-center gap-0.5 shrink-0 pr-1.5 mr-0.5 border-r" :class="skin.edge">
                <button
                    v-for="(item, n) in sources"
                    :key="item.id"
                    type="button"
                    @click="emit('update:sourceIndex', n)"
                    class="h-8 px-2 flex items-center gap-1.5 text-2xs font-semibold rounded-lg transition-colors"
                    :class="n === sourceIndex ? skin.chosen : skin.key"
                    :title="item.label"
                >
                    <svg v-if="item.kind === 'audio'" class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18V5l10-2v13" />
                        <circle cx="6.5" cy="18" r="2.5" />
                        <circle cx="16.5" cy="16" r="2.5" />
                    </svg>
                    <svg v-else class="w-3 h-3 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M7 4l12 8-12 8z" />
                    </svg>
                    <span class="hidden sm:inline">{{ item.label }}</span>
                </button>
            </div>

            <button
                type="button"
                @click="toggle"
                :disabled="!ready"
                class="w-9 h-9 shrink-0 flex items-center justify-center rounded-lg bg-indigo-600 text-white hover:bg-indigo-500 disabled:opacity-40 transition-colors"
                :title="playing ? t('songs.chords.scrub.pause') : t('songs.chords.scrub.play')"
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
            <div class="flex items-center gap-0.5 shrink-0">
                <button
                    v-for="d in [-5, -2, -1, 1, 2, 5]"
                    :key="d"
                    type="button"
                    @click="nudge(d)"
                    :disabled="!ready"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold tabular-nums disabled:opacity-40 transition-colors"
                    :class="[skin.key, d === 1 ? 'ml-1' : '']"
                    :title="d < 0
                        ? t('songs.chords.scrub.back', { n: -d })
                        : t('songs.chords.scrub.forward', { n: d })"
                >{{ d < 0 ? '−' : '+' }}{{ Math.abs(d) }}</button>
            </div>

            <span class="shrink-0 ml-1.5 text-xs font-semibold tabular-nums" :class="skin.time">
                {{ clock(at) }}<span :class="skin.dim">/{{ clock(duration) }}</span>
            </span>

            <!-- The line itself. Nudges move by seconds; this is for crossing
                 the song. -->
            <div
                ref="rail"
                class="relative flex-1 min-w-[6rem] h-9 mx-1 flex items-center touch-none select-none"
                :class="ready ? 'cursor-pointer' : 'cursor-default opacity-50'"
                @pointerdown="scrubStart"
                @pointermove="scrubMove"
                @pointerup="scrubEnd"
                @pointercancel="scrubEnd"
            >
                <div class="relative w-full h-1.5 rounded-full overflow-hidden" :class="skin.rail">
                    <div
                        v-if="loopBand"
                        class="absolute inset-y-0 bg-indigo-400/35"
                        :style="loopBand"
                    ></div>
                    <div
                        class="absolute inset-y-0 left-0 bg-indigo-500 rounded-full"
                        :style="{ width: `${progress * 100}%` }"
                    ></div>
                </div>

                <span
                    class="absolute w-3 h-3 -ml-1.5 rounded-full bg-white shadow ring-2 ring-indigo-500 pointer-events-none"
                    :style="{ left: `${progress * 100}%` }"
                ></span>
            </div>

            <!-- Half speed is where a dense chord separates into notes. -->
            <button
                type="button"
                @click="cycleRate"
                :disabled="!ready"
                class="h-8 px-2 shrink-0 flex items-center justify-center rounded-lg text-xs font-bold tabular-nums transition-colors disabled:opacity-40"
                :class="rate === 1 ? skin.key : skin.fast"
                :title="t('songs.chords.scrub.speed')"
            >{{ rate }}×</button>

            <div class="flex items-center gap-0.5 shrink-0 ml-0.5">
                <button
                    type="button"
                    @click="markA"
                    :disabled="!ready"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-colors disabled:opacity-40"
                    :class="loopA !== null ? skin.marked : skin.key"
                    :title="t('songs.chords.scrub.loop_a')"
                >A</button>

                <button
                    type="button"
                    @click="markB"
                    :disabled="!ready"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold transition-colors disabled:opacity-40"
                    :class="loopB !== null ? skin.marked : skin.key"
                    :title="t('songs.chords.scrub.loop_b')"
                >B</button>

                <button
                    type="button"
                    @click="looping = !looping"
                    :disabled="loopA === null || loopB === null"
                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors disabled:opacity-40"
                    :class="looping ? 'bg-indigo-600 text-white' : skin.key"
                    :title="t('songs.chords.scrub.loop')"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 9h12a4 4 0 010 8H8m-4-8l3-3M4 9l3 3" />
                    </svg>
                </button>

                <button
                    v-if="loopA !== null || loopB !== null"
                    type="button"
                    @click="clearLoop"
                    class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                    :class="skin.key"
                    :title="t('songs.chords.scrub.loop_clear')"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <button
                v-if="isVideo && !stage"
                type="button"
                @click="videoShown = !videoShown"
                class="w-8 h-8 shrink-0 ml-0.5 flex items-center justify-center rounded-lg transition-colors"
                :class="videoShown ? skin.lit : skin.key"
                :title="t('songs.chords.scrub.video')"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.5-2.5v9L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z" />
                </svg>
            </button>
        </div>

        <p v-if="failed" class="text-2xs mt-1.5" :class="dark ? 'text-amber-300' : 'text-amber-700'">
            {{ t('songs.chords.source_failed') }}
        </p>

        <!-- A track has nothing to show. It still needs an element to be
             played from, and the browser's own controls would be a second set
             beside the one above. -->
        <audio
            v-if="!isVideo"
            ref="audioEl"
            :src="source?.url ?? ''"
            preload="metadata"
            class="hidden"
            @loadedmetadata="ready = true; duration = engine.length(); engine.rate(rate)"
            @play="playing = true"
            @pause="playing = false"
            @error="failed = true"
        ></audio>

        <!-- One container, two places. Moving the node between them would tear
             the player down and lose the position mid-loop, so only the
             wrapper's classes change: off screen, or a panel under the bar. -->
        <div
            v-show="isVideo && !stage"
            :class="videoShown
                ? 'mt-2 w-full sm:w-72 aspect-video rounded-lg overflow-hidden bg-black'
                : 'absolute -left-[9999px] top-0 w-72 h-40 overflow-hidden'"
        >
            <div ref="holder" class="w-full h-full"></div>
        </div>
    </div>
</template>
