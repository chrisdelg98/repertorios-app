<script setup>
import { computed } from 'vue';
import { segmentLine } from '@/Utils/chords';

/**
 * A chart, read.
 *
 * Built for the music stand and the phone at once: every line is inline text,
 * so it wraps where the column ends and each chord stays over the syllable it
 * belongs to. A grid would keep its shape and run off the side.
 *
 * Written to be dropped into the player's panel later without changes — it
 * takes blocks and chords and nothing else.
 */

const props = defineProps({
    blocks: { type: Array, default: () => [] },
    chords: { type: [Object, Array], default: () => ({}) },
    /** Semitones to move everything by, for the service that sings it higher. */
    semitones: { type: Number, default: 0 },
    /** Reads on dark in the player, on light in the editor. */
    dark: { type: Boolean, default: false },
    size: { type: String, default: 'text-base' },
    /*
     * Two columns put a whole song on one screen, which is the difference
     * between reading a chart and scrolling one. Sections are kept whole: a
     * verse split down the middle is worse than a scroll.
     */
    columns: { type: Number, default: 1 },
});

function linesOf(block) {
    return String(block?.text ?? '').split('\n');
}

function chordsFor(blockId, lineIndex) {
    const byLine = props.chords?.[blockId];
    return Array.isArray(byLine) ? (byLine[lineIndex] ?? []) : [];
}

const hasAny = computed(() => props.blocks.some(b => (b?.text ?? '').trim()));
</script>

<template>
    <div v-if="hasAny" :class="columns > 1 ? 'columns-2 gap-10' : 'space-y-5'">
        <!-- Keyed by position, not by id: the same chorus appears three
             times in a song and all three carry the id of the one section it
             really is. -->
        <section
            v-for="(block, b) in blocks"
            :key="b"
            :class="columns > 1 ? 'break-inside-avoid mb-5' : ''"
        >
            <p
                class="text-2xs font-bold uppercase tracking-widest mb-1.5"
                :class="dark ? 'text-indigo-300' : 'text-indigo-600'"
            >{{ block.label || block.kind }}</p>

            <div
                v-for="(line, i) in linesOf(block)"
                :key="i"
                class="leading-normal pb-1.5"
                :class="size"
            >
                <!-- Empty lines keep their height: a blank between phrases is
                     part of how a chart is read. -->
                <span v-if="!line.trim() && !chordsFor(block.id, i).length">&nbsp;</span>

                <!-- A column per chord, not a chord floated above the
                     words. Absolute positioning lets two close chords land on
                     each other; a column cannot overlap its neighbour, and
                     where the chord is wider than the syllable it pushes the
                     line apart — which is how a chart is meant to read. -->
                <span
                    v-for="(piece, j) in segmentLine(line, chordsFor(block.id, i), semitones)"
                    :key="j"
                    class="inline-flex flex-col align-top"
                >
                    <!-- The gap to the right is the chord's own: two of
                         them a syllable apart met edge to edge and read as one
                         word, so D E F# arrived as DEF#. It costs width, which
                         is why the chord is set smaller than the words it sits
                         over — the words are what is being sung. -->
                    <span
                        class="h-[22px] text-[11px] font-bold leading-[14px] tracking-tight whitespace-nowrap"
                        :class="[
                            piece.chord ? 'pr-2' : '',
                            dark ? 'text-amber-300' : 'text-indigo-700',
                        ]"
                    >{{ piece.chord || '\u00A0' }}</span>
                    <span
                        class="whitespace-pre leading-snug"
                        :class="dark ? 'text-slate-100' : 'text-slate-900'"
                    >{{ piece.text }}</span>
                </span>
            </div>
        </section>
    </div>

    <p v-else class="text-sm text-center py-8" :class="dark ? 'text-slate-400' : 'text-slate-500'">
        <slot name="empty">Esta canción todavía no tiene letra.</slot>
    </p>
</template>
