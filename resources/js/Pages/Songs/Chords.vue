<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import ChordChartReader from '@/Components/Chords/ChordChartReader.vue';
import { chartSheet, hasOwnChords, segmentLine, SLOT, transposeChord } from '@/Utils/chords';
import { decorate } from '@/Utils/lyrics';
import { downloadChartPdf } from '@/Utils/chordPdf';
import VideoScrubber from '@/Components/Chords/VideoScrubber.vue';

/**
 * Writing the chords of a song, over words that already exist.
 *
 * Editor and reader on one screen, because the question is never "can this be
 * stored" but "can a musician read it on a phone in a dark room", and that is
 * only answerable by seeing both at once. The reader is the same component the
 * player uses, so what is checked here is what will be seen there.
 */

const props = defineProps({
    song: { type: Object, required: true },
    /** Where to land on the way out, when the chart was opened from a service. */
    return_to: { type: String, default: null },
});

const { t } = useI18n();


function leave() {
    router.visit(props.return_to ?? '/songs');
}

// ── The chart being written ──────────────────────────────────────
const chords = ref({});
const songKey = ref(null);

/**
 * The song as it is sung, one entry per appearance.
 *
 * A repeat is stored as `{ ref }` with neither words nor an id of its own,
 * which is the point for the words: fixing a line in the chorus fixes all
 * three places it appears. For the chords that is only the default, so each
 * appearance also carries where its chords come from and where its own would
 * live if it were set loose.
 */
const sheet = computed(() => chartSheet(decorate(props.song?.blocks ?? []), chords.value)
    .map(entry => ({
        ...entry,
        name: entry.label
            || t('songs.lyrics.kinds.' + entry.kind) + (entry.num ? ' ' + entry.num : ''),
    })));

/** What the reader and the PDF need: words, a name, and where to look. */
const readerBlocks = computed(() => sheet.value.map(entry => ({
    id: entry.chordKey,
    kind: entry.kind,
    label: entry.name,
    text: entry.text,
})));

// ── The recording it is taken from ───────────────────────────────
// Declared above the watch below, which runs immediately and resets it: a
// const read before its own line is a crash, not a hoisted undefined.
const videoIndex = ref(0);

const videos = computed(() => props.song?.videos ?? []);
const video = computed(() => videos.value[videoIndex.value] ?? null);

watch(() => props.song, (song) => {
    chords.value = song?.chords ? JSON.parse(JSON.stringify(song.chords)) : {};
    songKey.value = song?.key ?? null;
    videoIndex.value = 0;

    // A version usually records the key it was played in. Offering it saves
    // retyping what the band already wrote down, and it is only a default:
    // the chart is not saved until somebody presses save.
    if (!songKey.value) {
        songKey.value = song?.videos?.find(v => v.key)?.key ?? null;
    }
}, { immediate: true });

function linesOf(block) {
    return String(block?.text ?? '').split('\n');
}

/**
 * Move a whole section, which is what a modulation is.
 *
 * Nobody transposes a chart one chord at a time: the last chorus goes up a
 * semitone as a block or not at all. Unlike the transposer beside the reader,
 * which is a way of looking at the chart, this rewrites what is stored — the
 * section really is in another key now, and the rest of the song is not.
 *
 * Going up spells the black notes as sharps and going down as flats, which is
 * the rule everywhere else here: a chord written Bb stays flat, and one
 * written A# stays sharp.
 */
function shiftBlock(key, semitones) {
    const from = chords.value[key];

    if (!from) return;

    const next = { ...chords.value };

    next[key] = from.map(line => (line ?? []).map(mark => ({
        pos: mark.pos,
        chord: transposeChord(mark.chord, semitones),
    })));

    chords.value = next;
}

/** Whether there is anything under this key to transpose. */
function hasChords(key) {
    return hasOwnChords(chords.value, key);
}

/**
 * Set one appearance loose from its section.
 *
 * It starts as a copy of what it was already showing, so freeing a chorus in
 * order to modulate it does not first empty it. From here on it is its own:
 * chords put on the other appearances stop reaching it, and chords put on it
 * stop reaching them.
 */
function freeBlock(entry) {
    const from = chords.value[entry.chordKey] ?? [];
    const next = { ...chords.value };

    next[entry.ownKey] = from.map(line => [...(line ?? [])]);
    chords.value = next;
}

/** Tie it back, discarding whatever it was given while loose. */
function tieBlock(entry) {
    const next = { ...chords.value };

    delete next[entry.ownKey];
    chords.value = next;
}

function marksAt(key, lineIndex) {
    return chords.value[key]?.[lineIndex] ?? [];
}

/**
 * Words are the targets, not characters.
 *
 * Asking someone to hit a letter on a phone is asking them to fail; a chord
 * belongs to a syllable anyway, and the start of a word is where one lands
 * nine times out of ten.
 */
function wordsOf(line) {
    const out = [];
    const re = /\S+/g;
    let match;

    while ((match = re.exec(line)) !== null) {
        out.push({ text: match[0], pos: match.index });
    }

    return out;
}

/**
 * The line as it is written: words to tap, and the real spaces between them.
 *
 * Giving every word a margin instead spaced them evenly and turned a sung
 * phrase into a row of buttons. The gaps have to be the gaps the lyric has.
 */
/**
 * How much room to offer past the last word.
 *
 * It grows with what is already there, so an intro is as long as it needs to
 * be rather than as long as somebody guessed, and a line of words keeps a
 * short tail for its turnaround instead of a field of blanks.
 */
function tailWidth(key, lineIndex, line) {
    const furthest = marksAt(key, lineIndex)
        .filter(mark => mark.pos >= line.length)
        .reduce((most, mark) => Math.max(most, mark.pos - line.length), -1);

    return Math.max(furthest + 4, line.length ? 3 : 8);
}

/*
 * The targets of one line: the words, the real spaces between them, and room
 * past the last one.
 *
 * The turnaround after a line, the two bars before the singing comes back,
 * and the whole of an intro, which usually has no words at all: none of them
 * had anywhere to go, because every target was a word.
 */
function cellsOf(line, key, lineIndex) {
    const cells = [];
    let cursor = 0;

    for (const word of wordsOf(line)) {
        if (word.pos > cursor) {
            cells.push({ gap: true, text: line.slice(cursor, word.pos), pos: cursor });
        }

        cells.push(word);
        cursor = word.pos + word.text.length;
    }

    if (cursor < line.length) {
        cells.push({ gap: true, text: line.slice(cursor), pos: cursor });
    }

    cells.push({
        gap: true,
        tail: true,
        text: SLOT.repeat(tailWidth(key, lineIndex, line)),
        pos: line.length,
    });

    return cells;
}

/** Every chord sitting inside this word, so the whole word can show them. */
function marksInWord(blockId, lineIndex, word) {
    return marksAt(blockId, lineIndex)
        .filter(m => m.pos >= word.pos && m.pos < word.pos + word.text.length);
}

/**
 * The word cut into pieces, each carrying the chord that starts it.
 *
 * The same cut the reader makes, so a chord put on the third syllable sits
 * over the third syllable on both sides of the screen. Printing a word's
 * chords together at its left edge was easier and told a lie: it showed them
 * all at the start of the word, wherever they had been placed.
 */
function piecesOf(blockId, lineIndex, word) {
    const marks = marksInWord(blockId, lineIndex, word)
        .map(m => ({ pos: m.pos - word.pos, chord: m.chord }));

    return segmentLine(word.text, marks);
}

// ── Placing one ──────────────────────────────────────────────────
const picking = ref(null);   // { blockId, lineIndex, word }
const offset = ref(0);       // characters into that word

/*
 * A chord is a note and a set of attributes, so it is picked that way.
 *
 * Seventeen root buttons spelled every note three times — C, C#, Db — when the
 * accidental is no more a part of the note than the seventh is. Seven letters
 * and a flat-or-sharp say the same twelve notes in half the space, and leave
 * room for the attributes that were missing: the sixths, the ninths, and a
 * bass note, which a chart cannot do without. G/B is G with B underneath it,
 * and before this there was no way to write one.
 */
const LETTERS = ['C', 'D', 'E', 'F', 'G', 'A', 'B'];

const ACCIDENTALS = [
    { id: '', label: '♮' },
    { id: '#', label: '♯' },
    { id: 'b', label: '♭' },
];

/*
 * Stored as it is written, which is also how it is read back and transposed.
 * Commonest first: the ones a worship chart is mostly made of, then the
 * colour, then the ones that turn up in a turnaround.
 */
const QUALITIES = [
    '', 'm', '7', 'm7', 'maj7', 'sus4', 'sus2', '5',
    '6', 'm6', 'add9', '9', 'm9', 'maj9', '11', '13',
    '7sus4', 'dim', 'dim7', 'm7b5', 'aug', '7b9',
];

/** Both spellings of the five black notes, for a key that has a name. */
const KEY_OPTIONS = [
    'C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#',
    'Gb', 'G', 'G#', 'Ab', 'A', 'A#', 'Bb', 'B',
];

const accidental = ref('');
const quality = ref('');
const bassLetter = ref('');
const bassAcc = ref('');
const bassOpen = ref(false);

const bassName = computed(() => (bassLetter.value ? bassLetter.value + bassAcc.value : ''));

/** What tapping a given letter would write. */
function chordFrom(letter) {
    return letter + accidental.value + quality.value
        + (bassName.value ? '/' + bassName.value : '');
}

/**
 * What the cursor is sitting on: a chord to change, or empty ground to fill.
 *
 * Deciding this once, from the cursor, is what makes a run of passing chords
 * possible. Fixing it when the sheet opened meant a word could hold exactly
 * one chord forever: the second tap moved the first instead of joining it.
 */
const editing = computed(() => {
    if (!picking.value) return null;

    const { blockId, lineIndex, word } = picking.value;

    return marksAt(blockId, lineIndex).find(m => m.pos === word.pos + offset.value) ?? null;
});

/** The letter under the cursor, so the row can show which one it is. */
const editingLetter = computed(() => {
    const chord = editing.value?.chord;
    return chord ? (/^[A-G]/.exec(chord)?.[0] ?? null) : null;
});

/** The chord sitting this many characters into the cell, for the preview. */
function chordAtOffset(k) {
    if (!picking.value) return '';

    const { blockId, lineIndex, word } = picking.value;

    return marksAt(blockId, lineIndex).find(m => m.pos === word.pos + k)?.chord ?? '';
}

/*
 * Moving onto a chord adopts all of its attributes, so tapping a letter
 * replaces it in kind. Moving onto empty ground keeps whatever was last
 * chosen, because a run of passing chords is usually a run of the same kind
 * and resetting between each one is work nobody asked for.
 */
watch(editing, (mark) => {
    if (!mark) return;

    const match = /^([A-G])(#|b)?([^/]*)(?:\/([A-G])(#|b)?)?$/.exec(mark.chord);

    if (!match) return;

    accidental.value = match[2] ?? '';
    quality.value = match[3] ?? '';
    bassLetter.value = match[4] ?? '';
    bassAcc.value = match[4] ? (match[5] ?? '') : '';
    bassOpen.value = bassOpen.value || !!match[4];
});

function openPicker(blockId, lineIndex, word) {
    picking.value = { blockId, lineIndex, word };

    // Land on the first chord already here, so touching a chorded word still
    // edits it rather than quietly starting a second one beside it.
    const existing = marksInWord(blockId, lineIndex, word)[0] ?? null;

    offset.value = existing ? existing.pos - word.pos : 0;
}

function place(letter) {
    if (!picking.value) return;

    const { blockId, lineIndex, word } = picking.value;
    const pos = word.pos + offset.value;
    const next = { ...chords.value };

    const block = (next[blockId] ?? []).map(line => [...(line ?? [])]);

    while (block.length <= lineIndex) block.push([]);

    // Only whatever was on this exact spot: everything else on the word is
    // somebody else's chord and has every right to stay.
    block[lineIndex] = block[lineIndex].filter(m => m.pos !== pos);
    block[lineIndex].push({ pos, chord: chordFrom(letter) });
    block[lineIndex].sort((a, b) => a.pos - b.pos);

    next[blockId] = block;
    chords.value = next;

    /*
     * The sheet stays open and the cursor steps on.
     *
     * A passing run is three chords a syllable apart, and closing after each
     * one made writing them nine taps instead of three. Closing is a tap
     * outside, which is where a bottom sheet is closed anyway.
     *
     * Past the words the room grows as it is used: an intro is written one
     * chord at a time and should not stop because a guess ran out.
     */
    if (word.tail && offset.value >= word.text.length - 1) {
        picking.value = { ...picking.value, word: { ...word, text: word.text + SLOT } };
    }

    if (offset.value < picking.value.word.text.length - 1) offset.value += 1;
}

/** Remove only what is under the cursor, and stay open for the next one. */
function clearHere() {
    if (!picking.value || !editing.value) return;

    const { blockId, lineIndex } = picking.value;
    const pos = editing.value.pos;
    const next = { ...chords.value };
    const block = (next[blockId] ?? []).map(line => [...(line ?? [])]);

    if (!block[lineIndex]) return;

    block[lineIndex] = block[lineIndex].filter(m => m.pos !== pos);
    next[blockId] = block;
    chords.value = next;
}

// ── Reading it back ──────────────────────────────────────────────
const semitones = ref(0);

const form = useForm({ chords: {}, key: null });

function save() {
    form.chords = chords.value;
    form.key = songKey.value;
    form.put(`/songs/${props.song.id}/chords`, { preserveScroll: true });
}

// ── Taking it off the screen ─────────────────────────────────────
const exporting = ref(false);

/**
 * The sheet as it reads right now, transposition included.
 *
 * Exporting the stored key instead would hand back a chart nobody asked for:
 * what is wanted on paper is what is on the screen.
 */
async function exportPdf() {
    if (exporting.value) return;

    exporting.value = true;

    try {
        await downloadChartPdf({
            name: props.song.name,
            artist: props.song.artist,
            keyName: songKey.value,
            blocks: readerBlocks.value,
            chords: chords.value,
            semitones: semitones.value,
        });
    } finally {
        exporting.value = false;
    }
}

// Counted on the chart as read, repeats included: that is how many a musician
// has in front of them.
const chordCount = computed(() => sheet.value.reduce(
    (total, entry) => total + (chords.value[entry.chordKey] ?? [])
        .reduce((n, line) => n + (line?.length ?? 0), 0),
    0,
));
</script>

<template>
    <Head :title="song.name + ' · ' + t('songs.chords.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-4 lg:py-8 lg:max-w-5xl lg:mx-auto pb-24">
            <div class="flex items-start gap-3 mb-4">
                <button
                    type="button"
                    @click="leave"
                    class="shrink-0 w-9 h-9 -ml-1 flex items-center justify-center rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors"
                    :aria-label="t('songs.cancel')"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                    </svg>
                </button>

                <div class="flex-1 min-w-0">
                    <p class="text-2xs font-semibold text-indigo-600 uppercase tracking-widest">{{ t('songs.chords.title') }}</p>
                    <h1 class="text-lg lg:text-2xl font-semibold lg:font-bold text-slate-900 truncate">{{ song.name }}</h1>
                    <p v-if="song.artist" class="text-xs font-medium text-slate-600 truncate">{{ song.artist }}</p>
                </div>

                <div class="shrink-0 flex items-center gap-2">
                    <button
                        type="button"
                        @click="exportPdf"
                        :disabled="exporting || !chordCount"
                        class="inline-flex items-center gap-1.5 h-9 px-3 text-xs font-semibold text-slate-600 bg-white border border-slate-200 hover:border-slate-300 rounded-lg disabled:opacity-40 transition-colors"
                        :title="t('songs.chords.pdf_hint')"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
                        </svg>
                        <span class="hidden sm:inline">{{ exporting ? t('songs.chords.exporting') : 'PDF' }}</span>
                    </button>

                    <button
                        type="button"
                        @click="save"
                        :disabled="form.processing"
                        class="h-9 px-4 text-xs font-semibold text-white bg-slate-900 hover:bg-slate-800 rounded-lg disabled:opacity-50 transition-colors"
                    >{{ form.processing ? t('songs.chords.saving') : t('songs.chords.save') }}</button>
                </div>
            </div>

            <!-- A chord is a position in the words, so without them there is
                 nothing here to position. Said plainly, with the way out. -->
            <div v-if="!sheet.length" class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-4">
                <p class="text-sm text-amber-900">{{ t('songs.chords.needs_lyrics') }}</p>

                <Link
                    :href="`/songs/${song.id}/lyrics`"
                    class="inline-block mt-2 text-xs font-semibold text-amber-900 underline underline-offset-2"
                >{{ t('songs.chords.write_lyrics') }}</Link>
            </div>

            <template v-else>
            <!-- Between the song and the chart, because it is used while
                 looking at both. -->
            <div v-if="videos.length" class="sticky top-0 z-20 -mx-4 px-4 lg:-mx-2 lg:px-2 pt-1 pb-3 bg-slate-50 space-y-2">
                <div v-if="videos.length > 1" class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="(v, n) in videos"
                        :key="v.id"
                        type="button"
                        @click="videoIndex = n"
                        class="px-2.5 py-1 text-2xs font-semibold rounded-lg border transition-colors"
                        :class="n === videoIndex
                            ? 'bg-slate-900 border-slate-900 text-white'
                            : 'bg-white border-slate-300 text-slate-600 hover:border-slate-400'"
                    >{{ v.name }}<span v-if="v.key" class="opacity-60"> · {{ v.key }}</span></button>
                </div>

                <VideoScrubber :url="video?.url ?? ''" />
            </div>

            <p v-else class="mb-4 text-2xs text-slate-500">
                {{ t('songs.chords.no_video') }}
            </p>

            <div class="grid lg:grid-cols-2 gap-4">
                <!-- Writing -->
                <section class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h2 class="text-sm font-semibold text-slate-900">{{ t('songs.chords.place') }}</h2>
                        <p class="text-2xs text-slate-500">{{ t('songs.chords.count', { n: chordCount }) }}</p>
                    </div>

                    <div class="space-y-5 text-[15px]">
                        <div v-for="block in sheet" :key="block.key">
                            <div class="flex flex-wrap items-center gap-2 mb-2 mt-1">
                                <p class="text-2xs font-bold text-indigo-600 uppercase tracking-widest">
                                    {{ block.name }}
                                </p>

                                <!-- Which appearance this is, and whether it
                                     is reading along with the others. A chorus
                                     set loose has to say so, or a modulation
                                     looks like a mistake. -->
                                <button
                                    v-if="block.occurrence > 1 && !block.freed"
                                    type="button"
                                    @click="freeBlock(block)"
                                    class="px-1.5 py-0.5 rounded bg-indigo-50 text-2xs font-semibold text-indigo-600 hover:bg-indigo-100 transition-colors"
                                    :title="t('songs.chords.free_hint')"
                                >{{ t('songs.chords.free') }}</button>

                                <button
                                    v-else-if="block.occurrence > 1"
                                    type="button"
                                    @click="tieBlock(block)"
                                    class="px-1.5 py-0.5 rounded bg-amber-50 text-2xs font-semibold text-amber-700 hover:bg-amber-100 transition-colors"
                                    :title="t('songs.chords.tie_hint')"
                                >{{ t('songs.chords.tie') }}</button>

                                <!-- Transposing the section itself, not the
                                     view of it: a modulation belongs to the
                                     song, so it is written down. -->
                                <div v-if="hasChords(block.chordKey)" class="ml-auto flex items-center gap-1">
                                    <span class="text-2xs text-slate-400">{{ t('songs.chords.transpose') }}</span>

                                    <button
                                        type="button"
                                        @click="shiftBlock(block.chordKey, -1)"
                                        class="w-6 h-6 flex items-center justify-center rounded-md bg-slate-100 text-slate-600 hover:bg-indigo-100 hover:text-indigo-700 transition-colors"
                                        :title="t(block.freed || block.occurrence === 1 && !block.isRef
                                            ? 'songs.chords.section_down'
                                            : 'songs.chords.section_down_all')"
                                    >−</button>

                                    <button
                                        type="button"
                                        @click="shiftBlock(block.chordKey, 1)"
                                        class="w-6 h-6 flex items-center justify-center rounded-md bg-slate-100 text-slate-600 hover:bg-indigo-100 hover:text-indigo-700 transition-colors"
                                        :title="t(block.freed || block.occurrence === 1 && !block.isRef
                                            ? 'songs.chords.section_up'
                                            : 'songs.chords.section_up_all')"
                                    >+</button>
                                </div>
                            </div>

                            <div v-for="(line, i) in linesOf(block)" :key="i" class="mb-2.5">
                                <!-- The spaces are targets too, and so are
                                     the slots past the last word: a passing
                                     chord often lands between two words, and
                                     a turnaround after all of them. -->
                                <button
                                    v-for="(cell, c) in cellsOf(line, block.chordKey, i)"
                                    :key="c"
                                    type="button"
                                    @click="openPicker(block.chordKey, i, cell)"
                                    class="inline-flex align-top rounded-sm transition-colors"
                                    :class="[
                                        cell.gap ? '' : 'px-0.5 -mx-0.5',
                                        marksInWord(block.chordKey, i, cell).length
                                            ? 'bg-indigo-50 hover:bg-indigo-100'
                                            : 'hover:bg-slate-100',
                                    ]"
                                >
                                    <span
                                        v-for="(piece, p) in piecesOf(block.chordKey, i, cell)"
                                        :key="p"
                                        class="inline-flex flex-col align-top"
                                    >
                                        <!-- Same gap as the reader, for the
                                             same reason: without it two close
                                             chords read as one. -->
                                        <span
                                            class="h-[22px] text-[11px] font-bold leading-[14px] tracking-tight text-indigo-700 whitespace-nowrap text-left"
                                            :class="piece.chord ? 'pr-2' : ''"
                                        >{{ piece.chord || '\u00A0' }}</span>
                                        <span
                                            class="whitespace-pre leading-snug"
                                            :class="cell.gap ? 'text-slate-300' : 'text-slate-700'"
                                        >{{ piece.text }}</span>
                                    </span>

                                    <!-- Nothing there yet, and nothing in the
                                         words to suggest it could be. -->
                                    <span
                                        v-if="cell.tail && !marksInWord(block.chordKey, i, cell).length"
                                        class="h-[22px] text-[11px] font-bold leading-[14px] text-slate-300"
                                    >+</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Reading -->
                <section class="bg-slate-900 rounded-xl px-4 py-4">
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <h2 class="text-sm font-semibold text-white">{{ t('songs.chords.reader') }}</h2>

                        <div class="flex items-center gap-2">
                            <!-- The key is what makes the transposer mean
                                 something: without it the chart can only say
                                 "two semitones up from whatever this is". -->
                            <select
                                v-model="songKey"
                                class="h-7 pl-2 pr-6 text-2xs font-bold rounded-lg bg-white/10 border border-white/15 text-slate-200 focus:outline-none focus:ring-1 focus:ring-indigo-400"
                                :title="t('songs.chords.written_key_hint')"
                            >
                                <option :value="null" class="text-slate-900">{{ t('songs.chords.written_key') }}</option>
                                <option v-for="k in KEY_OPTIONS" :key="k" :value="k" class="text-slate-900">{{ k }}</option>
                            </select>

                            <div class="flex items-center gap-1">
                            <button
                                type="button"
                                @click="semitones--"
                                class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-300 hover:bg-white/10"
                            >−</button>
                            <span class="w-10 text-center text-2xs font-bold text-slate-300 tabular-nums">
                                {{ semitones > 0 ? '+' : '' }}{{ semitones }}
                            </span>
                            <button
                                type="button"
                                @click="semitones++"
                                class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-300 hover:bg-white/10"
                            >+</button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <ChordChartReader :blocks="readerBlocks" :chords="chords" :semitones="semitones" dark />
                    </div>
                </section>
            </div>
            </template>
        </div>

        <!-- Which chord goes on that word -->
        <Teleport to="body">
            <div v-if="picking" class="fixed inset-0 z-50 bg-slate-900/50 flex items-end sm:items-center justify-center p-0 sm:p-4" @click="picking = null">
                <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-4 shadow-2xl" @click.stop>
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <h3 class="text-sm font-bold text-slate-900">{{ t('songs.chords.which') }}</h3>
                        <div class="flex items-center gap-3">
                            <button
                                v-if="editing"
                                type="button"
                                @click="clearHere"
                                class="text-xs font-semibold text-red-600 hover:text-red-700"
                            >{{ t('songs.chords.remove') }}</button>
                            <button type="button" @click="picking = null" class="text-xs font-semibold text-slate-900 hover:text-slate-600">{{ t('songs.chords.done') }}</button>
                        </div>
                    </div>

                    <!-- The cell with everything already on it, and a bar
                         under the spot being worked. Spelling the position out
                         in words could not show a run of three chords a
                         syllable apart, which is the thing this is for. -->
                    <div class="bg-slate-50 rounded-xl px-2 py-3 mb-3">
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="offset = Math.max(0, offset - 1)"
                                :disabled="offset === 0"
                                class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-600 disabled:opacity-30"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>

                            <div class="flex-1 min-w-0 overflow-x-auto">
                                <div class="inline-flex items-end justify-center min-w-full">
                                    <span
                                        v-for="(letter, k) in [...picking.word.text]"
                                        :key="k"
                                        @click="offset = k"
                                        class="inline-flex flex-col items-center shrink-0 px-px cursor-pointer"
                                    >
                                        <span class="h-4 text-2xs font-bold leading-4 text-indigo-600 whitespace-nowrap">{{ chordAtOffset(k) }}</span>
                                        <span
                                            class="text-lg font-semibold leading-tight"
                                            :class="k === offset ? 'text-indigo-700' : 'text-slate-400'"
                                        >{{ /\s/.test(letter) ? '·' : letter }}</span>
                                        <span
                                            class="h-1 w-full mt-0.5 rounded-full"
                                            :class="k === offset ? 'bg-indigo-600' : 'bg-transparent'"
                                        ></span>
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="offset = Math.min(picking.word.text.length - 1, offset + 1)"
                                :disabled="offset >= picking.word.text.length - 1"
                                class="w-8 h-8 shrink-0 flex items-center justify-center rounded-lg bg-white border border-slate-200 text-slate-600 disabled:opacity-30"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>

                        <p class="text-2xs text-slate-500 text-center mt-2">
                            <span v-if="editing">{{ t('songs.chords.editing', { chord: editing.chord }) }}</span>
                            <span v-else>{{ t('songs.chords.where') }}</span>
                        </p>
                    </div>

                    <!-- Attributes first, note last. The note is the tap that
                         writes, so everything that modifies it has to be
                         settled before the hand gets there. -->
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-2xs font-semibold text-slate-500 uppercase tracking-wide shrink-0">{{ t('songs.chords.accidental') }}</span>

                        <div class="flex gap-1">
                            <button
                                v-for="a in ACCIDENTALS"
                                :key="a.id"
                                type="button"
                                @click="accidental = a.id"
                                class="w-9 h-7 flex items-center justify-center text-sm font-bold rounded-lg border transition-colors"
                                :class="accidental === a.id
                                    ? 'bg-indigo-600 border-indigo-600 text-white'
                                    : 'bg-white border-slate-300 text-slate-600 hover:border-indigo-300'"
                            >{{ a.label }}</button>
                        </div>

                        <button
                            type="button"
                            @click="bassOpen = !bassOpen"
                            class="ml-auto h-7 px-2.5 text-2xs font-semibold rounded-lg border transition-colors"
                            :class="bassName
                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                : 'bg-white border-slate-300 text-slate-500 hover:border-indigo-300'"
                        >{{ t('songs.chords.bass') }} {{ bassName ? '/' + bassName : '—' }}</button>
                    </div>

                    <!-- The bass is a minority of chords, so it stays folded
                         away until asked for. -->
                    <div v-if="bassOpen" class="flex flex-wrap items-center gap-1 mb-2 px-2 py-2 bg-slate-50 rounded-lg">
                        <button
                            type="button"
                            @click="bassLetter = ''; bassAcc = ''"
                            class="w-8 h-7 flex items-center justify-center text-xs font-bold rounded-lg border transition-colors"
                            :class="!bassLetter
                                ? 'bg-slate-700 border-slate-700 text-white'
                                : 'bg-white border-slate-300 text-slate-500'"
                        >—</button>

                        <button
                            v-for="l in LETTERS"
                            :key="l"
                            type="button"
                            @click="bassLetter = l"
                            class="w-8 h-7 flex items-center justify-center text-xs font-bold rounded-lg border transition-colors"
                            :class="bassLetter === l
                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                : 'bg-white border-slate-300 text-slate-600 hover:border-indigo-300'"
                        >{{ l }}</button>

                        <span class="w-px h-5 bg-slate-300 mx-0.5"></span>

                        <button
                            v-for="a in ACCIDENTALS"
                            :key="a.id"
                            type="button"
                            @click="bassAcc = a.id"
                            :disabled="!bassLetter"
                            class="w-8 h-7 flex items-center justify-center text-xs font-bold rounded-lg border transition-colors disabled:opacity-30"
                            :class="bassLetter && bassAcc === a.id
                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                : 'bg-white border-slate-300 text-slate-600'"
                        >{{ a.label }}</button>
                    </div>

                    <div class="flex flex-wrap gap-1 mb-3">
                        <button
                            v-for="q in QUALITIES"
                            :key="q"
                            type="button"
                            @click="quality = q"
                            class="px-2 py-1 text-xs font-semibold rounded-lg border transition-colors"
                            :class="quality === q
                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                : 'bg-white border-slate-300 text-slate-600 hover:border-indigo-300'"
                        >{{ q || t('songs.chords.major') }}</button>
                    </div>

                    <!-- Seven notes, once each. Every button says exactly what
                         it will write, so the attributes above can be sticky
                         without anybody being surprised by them. -->
                    <div class="grid grid-cols-7 gap-1">
                        <button
                            v-for="letter in LETTERS"
                            :key="letter"
                            type="button"
                            @click="place(letter)"
                            class="py-2 flex flex-col items-center justify-center rounded-lg border transition-colors"
                            :class="editingLetter === letter
                                ? 'bg-indigo-600 border-indigo-600 text-white'
                                : 'bg-slate-100 border-transparent text-slate-900 hover:bg-indigo-100'"
                        >
                            <span class="text-sm font-bold leading-none">{{ letter }}{{ accidental }}</span>
                            <span
                                v-if="quality || bassName"
                                class="text-[9px] font-semibold leading-none mt-0.5 opacity-70"
                            >{{ quality }}{{ bassName ? '/' + bassName : '' }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
