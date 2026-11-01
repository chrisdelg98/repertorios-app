<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import VideoScrubber from '@/Components/Chords/VideoScrubber.vue';
import ChordChartReader from '@/Components/Chords/ChordChartReader.vue';
import LyricsPanel from '@/Components/Lyrics/LyricsPanel.vue';
import PanelPicker from '@/Components/PanelPicker.vue';
import PanelIcon from '@/Components/PanelIcon.vue';
import { chartSheet, transposeChord } from '@/Utils/chords';
import { decorate } from '@/Utils/lyrics';

/**
 * The player, for the hour before the service rather than the hour of it.
 *
 * The ordinary player runs a setlist: one song after another, at the speed the
 * recording was made. Learning a song is the opposite — four seconds at half
 * speed, twenty times, with the words in front of you.
 *
 * The transport runs the full width because it governs everything below it,
 * and under it are panels: the running order, the recording, the words. Two by
 * default, because two is what fits and reads; a third is one press away for
 * when the hands on the video have to be watched while the chart is read.
 *
 * Read-only by default. Whoever may edit gets one quiet button to the chord
 * editor, which is a different screen for a different job; nothing is written
 * from here.
 */

const props = defineProps({
    songs: { type: Array, default: () => [] },
    index: { type: Number, default: 0 },
    canWrite: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'exit']);

const { t } = useI18n();

const song = computed(() => props.songs[props.index] ?? null);

// ── What there is to listen to ───────────────────────────────────
/*
 * The video comes first, and is what opens.
 *
 * A track is cleaner to hear, but a recording you can watch is the one worth
 * opening on: the panel beside it has something in it, and the hands are half
 * of what anyone is here for. The track is one press away.
 */
const sources = computed(() => {
    const list = [];

    if (song.value?.youtube_url) {
        list.push({ id: 'youtube', kind: 'youtube', url: song.value.youtube_url, label: 'YouTube' });
    }

    if (song.value?.audio?.url) {
        list.push({ id: 'audio', kind: 'audio', url: song.value.audio.url, label: t('playlist.advanced.track') });
    }

    return list;
});

const sourceIndex = ref(0);
const source = computed(() => sources.value[sourceIndex.value] ?? null);
const isVideo = computed(() => source.value?.kind === 'youtube');

/*
 * The picture lives here rather than inside the transport, and this element is
 * never unmounted or moved — only restyled. Re-parenting an iframe reloads it,
 * and a recording that restarts every time the layout changes is useless.
 */
const stage = ref(null);
const stageReady = ref(null);

onMounted(() => { stageReady.value = stage.value; });

// ── What there is to read ────────────────────────────────────────
const lyrics = computed(() => song.value?._lyrics ?? []);

const chart = computed(() => chartSheet(decorate(lyrics.value), song.value?._chords ?? {})
    .map(entry => ({
        id: entry.chordKey,
        kind: entry.kind,
        label: entry.label
            || t('songs.lyrics.kinds.' + entry.kind) + (entry.num ? ' ' + entry.num : ''),
        text: entry.text,
    })));

const hasChords = computed(() => {
    const stored = song.value?._chords ?? {};

    return chart.value.some(block => (stored[block.id] ?? []).some(line => line?.length));
});

// ── How the room is divided ──────────────────────────────────────
const showQueue = ref(true);

/*
 * The panels are slots, and a slot holds whatever it is given.
 *
 * One by default and two on request, beside the running order. What each one
 * shows is chosen in its own head, from the same list — so "video beside the
 * chart" and "the words beside the chart" are the same gesture rather than two
 * different controls.
 *
 * Three renderers exist: the recording, the chart, the words. A slot names
 * one, and no two slots name the same.
 */
const slots = ref(['media']);

const split = computed(() => slots.value.length > 1);

/** Flexbox order, so the panels rearrange without the DOM moving. */
const ORDER = ['order-1', 'order-2', 'order-3'];

/*
 * What can go in a slot. Picking a recording also changes what is playing,
 * which is why the two kinds are divided by a rule rather than mixed.
 */
const contents = computed(() => [
    { id: 'video', group: 'media', label: t('playlist.advanced.video'), ready: !!song.value?.youtube_url },
    { id: 'audio', group: 'media', label: t('playlist.advanced.track'), ready: !!song.value?.audio?.url },
    { id: 'lyrics', group: 'text', label: t('playlist.tab_lyrics'), ready: lyrics.value.length > 0 },
    { id: 'chords', group: 'text', label: t('songs.chords.tab'), ready: hasChords.value },
]);

/** The media slot shows whichever recording is loaded, by its own name. */
function shownIn(index) {
    const held = index < 0 ? null : slots.value[index];

    return held === 'media' ? (isVideo.value ? 'video' : 'audio') : held;
}

function pick(index, id) {
    if (index < 0) return;

    const wanted = id === 'video' || id === 'audio' ? 'media' : id;

    if (wanted === 'media') {
        const kind = id === 'video' ? 'youtube' : 'audio';
        const n = sources.value.findIndex(item => item.kind === kind);

        if (n >= 0) sourceIndex.value = n;
    }

    const next = [...slots.value];
    const elsewhere = next.indexOf(wanted);

    // Already open in another slot: the two trade places rather than one
    // vanishing, so every slot keeps something in it.
    if (elsewhere !== -1 && elsewhere !== index) next[elsewhere] = next[index];

    next[index] = wanted;
    slots.value = next;
}

function toggleSplit() {
    if (split.value) {
        slots.value = slots.value.slice(0, 1);
        return;
    }

    // The third gets whatever is not already up.
    const spare = ['media', 'chords', 'lyrics'].find(id => !slots.value.includes(id));

    slots.value = [...slots.value, spare ?? 'lyrics'];
}

/*
 * Where a panel sits, and whether it sits anywhere.
 *
 * The recording is never unmounted and never re-parented: an iframe moved in
 * the DOM reloads, and a four-second loop that restarts is the point lost. Out
 * of play it goes off screen and keeps running.
 */
function panelClass(id) {
    const index = slots.value.indexOf(id);

    if (index === -1) {
        return id === 'media'
            ? 'absolute -left-[9999px] top-0 w-[32rem] h-72'
            : 'hidden';
    }

    const edge = index < slots.value.length - 1 ? 'border-r border-white/10' : '';

    return `${ORDER[index]} flex-1 min-w-0 flex flex-col min-h-0 ${edge}`;
}

// ── How it is read ───────────────────────────────────────────────
/** One column or two: a whole song at once, or a bigger one to scroll. */
const columns = ref(1);

const CHART_SIZES = ['text-sm', 'text-base', 'text-lg', 'text-xl'];
const sizeStep = ref(1);

const chartSize = computed(() => CHART_SIZES[sizeStep.value]);

function resize(by) {
    sizeStep.value = Math.min(CHART_SIZES.length - 1, Math.max(0, sizeStep.value + by));
}

// ── Moved for one rehearsal, never written down ──────────────────
const steps = ref(0);

const writtenKey = computed(() => song.value?._chordsKey ?? null);

const sungKey = computed(() => (
    writtenKey.value ? transposeChord(writtenKey.value, steps.value) : ''
));

/*
 * A different song is a different recording: the source picked for the last
 * one may not exist on this one, and a transposition certainly does not carry.
 */
watch(() => props.index, () => {
    sourceIndex.value = 0;
    steps.value = 0;
});

function edit() {
    if (!song.value?.song_id) return;

    router.visit(
        `/songs/${song.value.song_id}/chords?from=${encodeURIComponent(window.location.pathname + window.location.search)}`
    );
}
</script>

<template>
    <div class="flex-1 min-h-0 flex flex-col bg-slate-900">
        <!-- Head: which song, how the room is divided, and the way back -->
        <div class="flex items-center gap-2 px-4 py-2 border-b border-white/10 shrink-0">
            <div class="flex-1 min-w-0">
                <p class="text-[10px] font-semibold text-indigo-300 uppercase tracking-widest truncate">
                    {{ t('playlist.advanced.title') }}
                </p>
                <p class="text-sm font-bold text-white truncate">
                    {{ song?.name ?? '—' }}
                    <span v-if="song?.artist" class="font-normal text-slate-300"> · {{ song.artist }}</span>
                </p>
            </div>

            <div class="shrink-0 flex items-center gap-0.5">
                <button
                    type="button"
                    @click="showQueue = !showQueue"
                    class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors"
                    :class="showQueue ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                    :aria-label="t('playlist.advanced.queue')"
                    :title="t('playlist.advanced.queue')"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" />
                    </svg>
                </button>

                <button
                    type="button"
                    @click="toggleSplit"
                    class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors"
                    :class="split ? 'bg-white/10 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                    :aria-label="split ? t('playlist.advanced.join') : t('playlist.advanced.split')"
                    :title="split ? t('playlist.advanced.join') : t('playlist.advanced.split')"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="5" width="18" height="14" rx="2" />
                        <path stroke-linecap="round" d="M12 5v14" />
                    </svg>
                </button>

                <!-- Quiet on purpose: reading is what this screen is for, and
                     writing is somewhere else. -->
                <button
                    v-if="canWrite && song?.song_id"
                    type="button"
                    @click="edit"
                    class="w-9 h-9 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors"
                    :aria-label="t('playlist.advanced.edit')"
                    :title="t('playlist.advanced.edit')"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                    </svg>
                </button>

                <button
                    type="button"
                    @click="emit('exit')"
                    class="ml-1 w-9 h-9 flex items-center justify-center rounded-lg bg-white/10 hover:bg-white/20 text-white transition-colors"
                    :aria-label="t('playlist.advanced.back')"
                    :title="t('playlist.advanced.back')"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- The transport, the full width of what it governs -->
        <div class="shrink-0 px-3 py-2 border-b border-white/10">
            <!-- The sources are chosen in the panel that shows them, so the
                 transport is only transport. -->
            <VideoScrubber
                v-if="source && stageReady"
                :source="source"
                :stage="stageReady"
                dark
            />

            <p v-else-if="!source" class="text-2xs text-slate-400 py-2">
                {{ t('playlist.advanced.nothing_to_play') }}
            </p>
        </div>

        <!-- The panels -->
        <div class="relative flex-1 min-h-0 flex">
            <!-- The running order, so a rehearsal can move on without
                 leaving the controls behind. -->
            <div v-if="showQueue" class="w-56 xl:w-64 shrink-0 overflow-y-auto border-r border-white/10 px-2 py-2 space-y-0.5">
                <button
                    v-for="(s, i) in songs"
                    :key="i"
                    type="button"
                    @click="emit('select', i)"
                    class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-left transition-colors"
                    :class="i === index ? 'bg-indigo-600' : 'hover:bg-white/5'"
                >
                    <span
                        class="w-4 shrink-0 text-2xs font-bold tabular-nums"
                        :class="i === index ? 'text-white' : 'text-slate-500'"
                    >{{ i + 1 }}</span>

                    <span class="flex-1 min-w-0">
                        <span class="block text-xs font-semibold truncate" :class="i === index ? 'text-white' : 'text-slate-200'">
                            {{ s.name }}
                        </span>
                        <span v-if="s.artist" class="block text-2xs truncate" :class="i === index ? 'text-indigo-200' : 'text-slate-500'">
                            {{ s.artist }}
                        </span>
                    </span>

                    <!-- What the song carries, so the running order says where
                         the work still is. -->
                    <span class="shrink-0 flex items-center gap-1" :class="i === index ? 'text-indigo-200' : 'text-slate-600'">
                        <svg v-if="s._chords && Object.keys(s._chords).length" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 18V5l10-2v13" />
                            <circle cx="6.5" cy="18" r="2.5" />
                            <circle cx="16.5" cy="16" r="2.5" />
                        </svg>
                        <svg v-if="s._lyrics?.length" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 10h16M4 14h12M4 18h7" />
                        </svg>
                    </span>
                </button>
            </div>

            <!-- The recording. Always mounted, only ever restyled: an
                 iframe moved in the DOM reloads. -->
            <div :class="panelClass('media')">
                <div class="shrink-0 flex items-center px-3 py-1.5 border-b border-white/10">
                    <PanelPicker
                        :value="shownIn(slots.indexOf('media'))"
                        :options="contents"
                        @select="pick(slots.indexOf('media'), $event)"
                    />
                </div>

                <div class="flex-1 min-h-0 flex items-center justify-center p-4 bg-black/20">
                    <!-- A track has no picture; saying so beats a black box. -->
                    <div v-show="!isVideo" class="text-center">
                        <PanelIcon name="audio" class="w-10 h-10 text-slate-600 mx-auto mb-2" />
                        <p class="text-2xs text-slate-500">{{ t('playlist.advanced.track_only') }}</p>
                    </div>

                    <div v-show="isVideo" class="w-full max-w-3xl aspect-video rounded-lg overflow-hidden bg-black">
                        <div ref="stage" class="w-full h-full"></div>
                    </div>
                </div>
            </div>

            <!-- The chart -->
            <div :class="panelClass('chords')">
                <div class="flex items-center gap-1 px-3 py-1.5 border-b border-white/10 shrink-0">
                    <PanelPicker
                        :value="shownIn(slots.indexOf('chords'))"
                        :options="contents"
                        @select="pick(slots.indexOf('chords'), $event)"
                    />

                    <div class="ml-auto flex items-center gap-0.5">
                        <button
                            type="button"
                            @click="columns = columns === 1 ? 2 : 1"
                            class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors"
                            :class="columns > 1 ? 'bg-white/15 text-white' : 'text-slate-400 hover:text-white hover:bg-white/10'"
                            :aria-label="t('playlist.advanced.columns')"
                            :title="t('playlist.advanced.columns')"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="7" height="16" rx="1" />
                                <rect x="14" y="4" width="7" height="16" rx="1" />
                            </svg>
                        </button>

                        <button
                            type="button"
                            @click="resize(-1)"
                            :disabled="sizeStep === 0"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold text-slate-300 hover:text-white hover:bg-white/10 disabled:opacity-30 transition-colors"
                            :aria-label="t('songs.lyrics.zoom_out')"
                        >A−</button>
                        <button
                            type="button"
                            @click="resize(1)"
                            :disabled="sizeStep === 3"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-bold text-slate-300 hover:text-white hover:bg-white/10 disabled:opacity-30 transition-colors"
                            :aria-label="t('songs.lyrics.zoom_in')"
                        >A+</button>

                        <span class="w-px h-5 bg-white/10 mx-1"></span>

                        <!-- Moved for this rehearsal only. What the band wrote
                             down is not touched from here. -->
                        <button
                            type="button"
                            @click="steps -= 1"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition-colors"
                            :aria-label="t('songs.chords.down')"
                        >−</button>

                        <button
                            type="button"
                            @click="steps = 0"
                            :disabled="!steps"
                            class="min-w-[2.75rem] h-8 px-1 flex items-center justify-center rounded-lg text-2xs font-bold tabular-nums transition-colors"
                            :class="steps ? 'text-white bg-white/10 hover:bg-white/20' : 'text-slate-400'"
                            :title="t('songs.chords.reset')"
                        >{{ sungKey || (steps > 0 ? '+' + steps : steps) }}</button>

                        <button
                            type="button"
                            @click="steps += 1"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-300 hover:text-white hover:bg-white/10 transition-colors"
                            :aria-label="t('songs.chords.up')"
                        >+</button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto px-5 py-4">
                    <ChordChartReader
                        :blocks="chart"
                        :chords="song?._chords ?? {}"
                        :semitones="steps"
                        :columns="columns"
                        :size="chartSize"
                        dark
                    />
                </div>
            </div>

            <!-- The words -->
            <div :class="panelClass('lyrics')">
                <div class="flex items-center px-3 py-1.5 border-b border-white/10 shrink-0">
                    <PanelPicker
                        :value="shownIn(slots.indexOf('lyrics'))"
                        :options="contents"
                        @select="pick(slots.indexOf('lyrics'), $event)"
                    />
                </div>

                <div class="flex-1 min-h-0">
                    <LyricsPanel :blocks="lyrics" :columns="columns" dark />
                </div>
            </div>
        </div>
    </div>
</template>
