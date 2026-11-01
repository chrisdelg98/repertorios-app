<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useLyricsReader } from '@/Composables/useLyricsReader';
import LyricsStructureRail from '@/Components/Lyrics/LyricsStructureRail.vue';

const { t } = useI18n();

/**
 * Reading a song's words, wherever that happens.
 *
 * One component for the player and for the library because the thing being
 * designed is not a panel in a screen, it is how a song is read: how big, in
 * one flow or a block at a time, with the structure in sight or out of the
 * way. Those answers should not change because of which door you came in by,
 * and they are remembered across both.
 *
 * It owns nothing about the song and nothing about playback. The surface
 * around it keeps its own controls and passes them in through the slot, so the
 * bar reads as one row of controls rather than two stacked ones.
 */
const props = defineProps({
    blocks: { type: Array, default: () => [] },
    /** The player is black; the library is white. Everything else follows. */
    dark: { type: Boolean, default: false },
    /** Shown in the empty state, when there is somewhere to go and write them. */
    writeUrl: { type: String, default: '' },
    /*
     * Two columns put a whole song on one screen. Only the flowing mode takes
     * them: reading one block at a time is already one thing at a time.
     */
    columns: { type: Number, default: 1 },
});

const blocksRef = computed(() => props.blocks ?? []);

const {
    entries, labelOf, shortLabelOf,
    activeIndex, active, goTo, nextBlock, prevBlock, canNextBlock, canPrevBlock,
    zoomStep, zoomIn, zoomOut, canZoomIn, canZoomOut,
    mode, toggleMode,
    railState, cycleRail,
} = useLyricsReader(blocksRef);

defineExpose({ nextBlock, prevBlock, zoomIn, zoomOut, toggleMode, cycleRail, goTo });

const hasLyrics = computed(() => entries.value.length > 0);
const inBlocks = computed(() => mode.value === 'blocks');

/* ── Flow mode: the rail scrolls the body ─────────────────────────────── */

const scroller = ref(null);
const sectionEls = ref([]);

function setSectionEl(el, index) {
    if (el) sectionEls.value[index] = el;
}

/**
 * Tapping the rail moves the words, in whichever mode is on.
 *
 * In block mode that is simply which block shows. In flow mode it is a scroll,
 * and the block still becomes "active" so the rail keeps marking where you
 * are — the rail is a map, and a map with no pin is half a map.
 */
async function jumpTo(index) {
    goTo(index);

    if (inBlocks.value) return;

    await nextTick();

    sectionEls.value[index]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/** Coming back to flow from blocks, land where you were reading. */
watch(mode, async (value) => {
    if (value !== 'flow') return;

    await nextTick();
    sectionEls.value[activeIndex.value]?.scrollIntoView({ block: 'start' });
});

/* ── Appearance ───────────────────────────────────────────────────────── */

const bar = computed(() => props.dark
    ? 'border-white/10 bg-white/[0.03]'
    : 'border-slate-200 bg-slate-50/80');

const button = computed(() => props.dark
    ? 'text-slate-300 hover:text-white hover:bg-white/10 disabled:opacity-25'
    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-200/70 disabled:opacity-30');

const words = computed(() => props.dark ? 'text-slate-100' : 'text-slate-800');
const muted = computed(() => props.dark ? 'text-slate-400' : 'text-slate-500');

const railIcon = computed(() => railState.value === 'full'
    ? 'M3.75 6h16.5M3.75 12h16.5M3.75 18h16.5'
    : (railState.value === 'thin' ? 'M3.75 6h1.5M3.75 12h1.5M3.75 18h1.5' : 'M8.25 6h12M8.25 12h12M8.25 18h12'));
</script>

<template>
    <div class="flex flex-col min-h-0 h-full">
        <!-- Controls. One row, and the order is deliberate: what the structure
             does, then how it is read, then how big — left to right, least
             often touched to most. -->
        <div
            class="shrink-0 flex items-center gap-1 px-2 py-1.5 border-b"
            :class="bar"
        >
            <!-- Structure: full, thin, gone. -->
            <button
                type="button"
                @click="cycleRail"
                :disabled="!hasLyrics"
                class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors"
                :class="button"
                :aria-label="t('songs.lyrics.toggle_structure')"
                :title="t('songs.lyrics.toggle_structure')"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" :d="railIcon" />
                </svg>
            </button>

            <!-- Flow or one block at a time. -->
            <button
                type="button"
                @click="toggleMode"
                :disabled="!hasLyrics"
                class="h-9 px-2.5 flex items-center gap-1.5 rounded-lg transition-colors text-2xs font-bold"
                :class="button"
                :aria-label="t('songs.lyrics.toggle_mode')"
            >
                <svg v-if="inBlocks" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h10" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <rect x="4" y="5" width="16" height="6" rx="1.5" />
                    <rect x="4" y="14" width="16" height="5" rx="1.5" opacity="0.4" />
                </svg>
                <span class="hidden sm:inline uppercase tracking-wide">
                    {{ inBlocks ? t('songs.lyrics.mode_blocks') : t('songs.lyrics.mode_flow') }}
                </span>
            </button>

            <!-- Whatever the surrounding screen needs here: in the player,
                 moving between songs. -->
            <slot name="controls" />

            <div class="flex-1" />

            <!-- Size. The step is shown because two taps without a readout is
                 how you end up pressing a disabled button and wondering. -->
            <div class="flex items-center gap-0.5">
                <button
                    type="button"
                    @click="zoomOut"
                    :disabled="!canZoomOut || !hasLyrics"
                    class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors"
                    :class="button"
                    :aria-label="t('songs.lyrics.zoom_out')"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" d="M5 12h14" />
                    </svg>
                </button>

                <span
                    class="w-8 text-center text-2xs font-bold tabular-nums"
                    :class="muted"
                >{{ zoomStep.label }}</span>

                <button
                    type="button"
                    @click="zoomIn"
                    :disabled="!canZoomIn || !hasLyrics"
                    class="w-9 h-9 flex items-center justify-center rounded-lg transition-colors"
                    :class="button"
                    :aria-label="t('songs.lyrics.zoom_in')"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Empty -->
        <div v-if="!hasLyrics" class="flex-1 flex items-center justify-center px-6 py-10 text-center">
            <div>
                <svg
                    class="w-10 h-10 mx-auto mb-3"
                    :class="muted"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 10h16M4 14h12M4 18h7" />
                </svg>
                <p class="text-sm font-medium" :class="muted">{{ t('songs.lyrics.none') }}</p>
                <a
                    v-if="writeUrl"
                    :href="writeUrl"
                    class="inline-flex items-center gap-1.5 mt-3 px-3 py-2 text-xs font-semibold rounded-lg transition-colors"
                    :class="dark ? 'bg-white/10 text-white hover:bg-white/20' : 'bg-indigo-600 text-white hover:bg-indigo-700'"
                >
                    {{ t('songs.lyrics.add') }}
                </a>
            </div>
        </div>

        <!-- The words, with the structure beside them. -->
        <div v-else class="flex-1 flex min-h-0">
            <LyricsStructureRail
                :entries="entries"
                :label-of="labelOf"
                :short-label-of="shortLabelOf"
                :active-index="activeIndex"
                :state="railState"
                :dark="dark"
                @select="jumpTo"
            />

            <!-- Flow: the whole song, scrolling. -->
            <div
                v-if="!inBlocks"
                ref="scroller"
                class="flex-1 min-w-0 overflow-y-auto px-4 sm:px-6 py-5"
                :class="columns > 1 ? 'columns-2 gap-10' : 'space-y-6'"
            >
                <section
                    v-for="(entry, index) in entries"
                    :key="entry.key"
                    :ref="el => setSectionEl(el, index)"
                    class="scroll-mt-4"
                    :class="columns > 1 ? 'break-inside-avoid mb-6' : ''"
                >
                    <!-- The label is a marker, not a heading: it is set small
                         and coloured so it reads as an aside, because the thing
                         being read is underneath it. -->
                    <p
                        class="flex items-center gap-1.5 text-2xs font-bold uppercase tracking-widest mb-2"
                        :class="dark ? entry.style.textDark : entry.style.text"
                    >
                        <span
                            class="w-1.5 h-1.5 rounded-full"
                            :class="dark ? entry.style.dotDark : entry.style.dot"
                        />
                        {{ labelOf(entry) }}
                        <svg
                            v-if="entry.isRef"
                            class="w-3 h-3 opacity-60"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-4.14-3.36-7.5-7.5-7.5a7.5 7.5 0 00-5.3 2.2M4.5 12c0 4.14 3.36 7.5 7.5 7.5a7.5 7.5 0 005.3-2.2" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 3.5v3.2h3.2M17.5 20.5v-3.2h-3.2" />
                        </svg>
                    </p>

                    <!-- A repeat shows its words in full. Mid-song nobody wants
                         a pointer to the chorus; they want the chorus. -->
                    <p
                        v-if="entry.text"
                        class="whitespace-pre-wrap border-l-2 pl-3.5 font-medium"
                        :class="[
                            words,
                            zoomStep.text,
                            zoomStep.leading,
                            dark ? entry.style.ruleDark : entry.style.rule,
                        ]"
                    >{{ entry.text }}</p>

                    <!-- An Intro or an Instrumental has nothing to sing, and
                         saying so beats an empty space that reads as a bug. -->
                    <p
                        v-else
                        class="border-l-2 pl-3.5 text-xs italic"
                        :class="[muted, dark ? entry.style.ruleDark : entry.style.rule]"
                    >{{ t('songs.lyrics.wordless') }}</p>
                </section>
            </div>

            <!-- Blocks: one at a time, which is how a song is actually read
                 standing up with a guitar on. -->
            <div v-else-if="active" class="flex-1 min-w-0 flex flex-col min-h-0">
                <div class="flex-1 overflow-y-auto px-4 sm:px-6 py-5">
                    <p
                        class="flex items-center gap-1.5 text-2xs font-bold uppercase tracking-widest mb-3"
                        :class="dark ? active.style.textDark : active.style.text"
                    >
                        <span
                            class="w-1.5 h-1.5 rounded-full"
                            :class="dark ? active.style.dotDark : active.style.dot"
                        />
                        {{ labelOf(active) }}
                    </p>

                    <p
                        v-if="active.text"
                        class="whitespace-pre-wrap font-medium"
                        :class="[words, zoomStep.text, zoomStep.leading]"
                    >{{ active.text }}</p>
                    <p v-else class="text-sm italic" :class="muted">{{ t('songs.lyrics.wordless') }}</p>
                </div>

                <!-- Wide targets along the bottom, where a thumb already is. -->
                <div
                    class="shrink-0 flex items-stretch gap-1 px-2 py-2 border-t"
                    :class="bar"
                >
                    <button
                        type="button"
                        @click="prevBlock"
                        :disabled="!canPrevBlock"
                        class="flex-1 h-11 flex items-center justify-center rounded-xl transition-colors"
                        :class="button"
                        :aria-label="t('songs.lyrics.prev_block')"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M15.5 4.5v15l-10-7.5 10-7.5z" />
                        </svg>
                    </button>

                    <span
                        class="shrink-0 self-center px-3 text-xs font-bold tabular-nums"
                        :class="muted"
                    >{{ activeIndex + 1 }} / {{ entries.length }}</span>

                    <button
                        type="button"
                        @click="nextBlock"
                        :disabled="!canNextBlock"
                        class="flex-1 h-11 flex items-center justify-center rounded-xl transition-colors"
                        :class="button"
                        :aria-label="t('songs.lyrics.next_block')"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8.5 4.5v15l10-7.5-10-7.5z" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
