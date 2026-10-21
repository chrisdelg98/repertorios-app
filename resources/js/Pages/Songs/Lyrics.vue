<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import LyricsKindSheet from '@/Components/Lyrics/LyricsKindSheet.vue';
import LyricsPasteSheet from '@/Components/Lyrics/LyricsPasteSheet.vue';
import LyricsPanel from '@/Components/Lyrics/LyricsPanel.vue';
import {
    KIND_ORDER, WORDLESS_KINDS, decorate, kindStyle,
    newId, tidy, totalChars,
} from '@/Utils/lyrics';

const { t } = useI18n();

const props = defineProps({
    // Where to land after saving, when the words were opened from a service
    // rather than from the library.
    return_to: { type: String, default: null },
    song: Object,
    limits: Object,
});

/**
 * Writing a song's words.
 *
 * Three ways in, one structure out. Someone can paste the whole song and then
 * correct where the blocks were cut; or build it a section at a time; or, once
 * a chorus exists, say "the chorus again" and have it appear. They are not
 * modes — all three are available at every moment, because a real session is
 * usually a paste followed by a correction followed by a bridge typed by hand.
 *
 * The repeat is the reason for all of it. A chorus written once and pointed at
 * three times is a chorus you can fix once.
 */

const blocks = ref(Array.isArray(props.song.lyrics) ? [...props.song.lyrics] : []);

const entries = computed(() => decorate(blocks.value));
const hasBlocks = computed(() => blocks.value.length > 0);

/** The sections a repeat can point at, labelled as they read. */
const repeatable = computed(() => entries.value
    .filter(entry => !entry.isRef && !entry.missing)
    .map(entry => ({
        id: entry.sourceId,
        kind: entry.kind,
        label: labelOf(entry),
        style: entry.style,
        preview: (entry.text ?? '').split('\n')[0] ?? '',
    })));

function labelOf(entry) {
    if (!entry) return '';
    if (entry.missing) return t('songs.lyrics.missing');
    if (entry.label) return entry.label;

    const name = t('songs.lyrics.kinds.' + entry.kind);

    return entry.num ? name + ' ' + entry.num : name;
}

/* ── Editing ──────────────────────────────────────────────────────────── */

const atCapacity = computed(() => blocks.value.length >= props.limits.max_blocks);

const used = computed(() => totalChars(blocks.value));
const overLength = computed(() => used.value > props.limits.max_total_chars);

function addSection(kind) {
    if (atCapacity.value) return;

    const id = newId();
    blocks.value.push({ id, kind, text: '' });

    // The caret belongs in the box that just appeared; anything else means
    // scrolling back down and tapping it.
    nextTick(() => focusBlock(id));
}

function addRepeat(sourceId) {
    if (atCapacity.value) return;

    blocks.value.push({ ref: sourceId });
}

function move(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= blocks.value.length) return;

    const next = [...blocks.value];
    [next[index], next[target]] = [next[target], next[index]];
    blocks.value = next;
}

/**
 * Removing a section takes its repeats with it.
 *
 * Leaving them would leave holes in the song pointing at nothing, and the
 * alternative — promoting one repeat into the section — would silently move
 * the words somewhere nobody put them.
 */
function remove(index) {
    const block = blocks.value[index];

    if (block?.ref) {
        blocks.value = blocks.value.filter((_, i) => i !== index);
        return;
    }

    blocks.value = blocks.value.filter((item, i) => i !== index && item.ref !== block.id);
}

/** Cuts a block in two where the caret is: "the chorus starts here". */
function splitAt(index) {
    const block = blocks.value[index];
    if (!block || block.ref) return;

    const el = textareas.value[block.id];
    const at = el?.selectionStart ?? 0;
    const text = block.text ?? '';

    if (at <= 0 || at >= text.length) return;

    const head = text.slice(0, at).trimEnd();
    const tail = text.slice(at).trimStart();

    if (!head || !tail) return;

    const id = newId();
    const next = [...blocks.value];

    next[index] = { ...block, text: head };
    next.splice(index + 1, 0, { id, kind: block.kind, text: tail });

    blocks.value = next;
    nextTick(() => focusBlock(id));
}

/** Joins a block with the one under it, for when the paste cut too finely. */
function mergeWithNext(index) {
    const block = blocks.value[index];
    const after = blocks.value[index + 1];

    if (!block || block.ref || !after || after.ref) return;

    const next = [...blocks.value];
    next[index] = { ...block, text: (block.text + '\n' + after.text).trim() };
    next.splice(index + 1, 1);

    // Repeats of the block that just disappeared would point at nothing.
    blocks.value = next.filter(item => item.ref !== after.id);
}

function canMerge(index) {
    const block = blocks.value[index];
    const after = blocks.value[index + 1];

    return !!(block && !block.ref && after && !after.ref);
}

/* ── Textareas ────────────────────────────────────────────────────────── */

const textareas = ref({});

/** Grows with its content: a lyric is twenty lines, not three plus a scrollbar. */
function grow(el) {
    if (!el) return;

    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

function bindTextarea(el, id) {
    if (!el) {
        delete textareas.value[id];
        return;
    }

    textareas.value[id] = el;
    nextTick(() => grow(el));
}

function focusBlock(id) {
    const el = textareas.value[id];
    if (!el) return;

    el.focus();
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/* ── Sheets ───────────────────────────────────────────────────────────── */

const kindSheetIndex = ref(null);
const kindSheetOpen = computed(() => kindSheetIndex.value !== null);
const kindSheetBlock = computed(() => blocks.value[kindSheetIndex.value] ?? null);

function openKindSheet(index) {
    if (blocks.value[index]?.ref) return;

    kindSheetIndex.value = index;
}

function applyKind({ kind, label }) {
    const index = kindSheetIndex.value;
    if (index === null) return;

    const next = [...blocks.value];
    const block = { ...next[index], kind };

    if (label) block.label = label;
    else delete block.label;

    next[index] = block;
    blocks.value = next;
    kindSheetIndex.value = null;
}

const pasteOpen = ref(false);

function applyPaste({ blocks: pasted, mode }) {
    blocks.value = mode === 'append'
        ? [...blocks.value, ...pasted].slice(0, props.limits.max_blocks)
        : pasted.slice(0, props.limits.max_blocks);

    pasteOpen.value = false;
}

/* ── Preview ──────────────────────────────────────────────────────────── */

const previewing = ref(false);

/* ── Saving ───────────────────────────────────────────────────────────── */

const form = useForm({ blocks: [], return_to: props.return_to ?? null });

const initial = JSON.stringify(blocks.value);
const dirty = computed(() => JSON.stringify(blocks.value) !== initial);

function save() {
    // Trailing blanks and runs of empty lines go on the way out, so what gets
    // stored is what gets read.
    form.blocks = blocks.value.map(block => (block.ref
        ? { ref: block.ref }
        : { ...block, text: tidy(block.text ?? '') }));

    form.put('/songs/' + props.song.id + '/lyrics', { preserveScroll: true });
}

function leave() {
    if (dirty.value && !window.confirm(t('songs.lyrics.leave_confirm'))) return;

    // The same place saving would land: leaving and finishing should not
    // put you in two different rooms.
    router.visit(props.return_to ?? '/songs');
}

/** The browser's own guard, for a closed tab rather than a tapped back arrow. */
function guard(event) {
    if (!dirty.value) return;

    event.preventDefault();
    event.returnValue = '';
}

onMounted(() => window.addEventListener('beforeunload', guard));
onBeforeUnmount(() => window.removeEventListener('beforeunload', guard));
</script>

<template>
    <Head :title="song.name + ' · ' + t('songs.lyrics.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-4 lg:py-8 lg:max-w-3xl lg:mx-auto pb-40">
            <!-- Header -->
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
                    <p class="text-2xs font-semibold text-indigo-600 uppercase tracking-widest">{{ t('songs.lyrics.title') }}</p>
                    <h1 class="text-lg lg:text-2xl font-semibold lg:font-bold text-slate-900 truncate">{{ song.name }}</h1>
                    <p v-if="song.artist" class="text-xs font-medium text-slate-600 truncate">{{ song.artist }}</p>
                </div>

                <button
                    v-if="hasBlocks"
                    type="button"
                    @click="previewing = !previewing"
                    class="shrink-0 h-9 px-3 flex items-center gap-1.5 text-xs font-semibold rounded-lg border transition-colors"
                    :class="previewing
                        ? 'bg-indigo-600 text-white border-indigo-600'
                        : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 010-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3.01 9.96 7.18a1 1 0 010 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3.01-9.96-7.18z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="hidden sm:inline">{{ t('songs.lyrics.preview') }}</span>
                </button>
            </div>

            <!-- Preview: the same reader the player uses, so what is checked
                 here is what will be seen there. -->
            <div v-if="previewing" class="bg-white border border-slate-200 rounded-2xl overflow-hidden h-[70vh] shadow-sm">
                <LyricsPanel :blocks="blocks" />
            </div>

            <template v-else>
                <!-- Empty: the two ways to start, the likelier one first. -->
                <div v-if="!hasBlocks" class="bg-white border border-slate-200 rounded-2xl px-5 py-8 text-center shadow-sm">
                    <svg class="w-10 h-10 text-slate-300 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 10h16M4 14h12M4 18h7" />
                    </svg>
                    <h2 class="text-sm font-bold text-slate-900">{{ t('songs.lyrics.empty_title') }}</h2>
                    <p class="text-xs text-slate-600 mt-1 leading-relaxed max-w-xs mx-auto">{{ t('songs.lyrics.empty_hint') }}</p>

                    <button
                        type="button"
                        @click="pasteOpen = true"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 mt-5 px-5 py-3 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition-colors"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 4h2a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2m2-1h4a1 1 0 011 1v1a1 1 0 01-1 1h-4a1 1 0 01-1-1V4a1 1 0 011-1z" />
                        </svg>
                        {{ t('songs.lyrics.paste_cta') }}
                    </button>

                    <p class="text-2xs text-slate-500 mt-3">{{ t('songs.lyrics.or_build') }}</p>
                </div>

                <!-- The blocks -->
                <div v-else class="space-y-2.5">
                    <div
                        v-for="(entry, index) in entries"
                        :key="entry.key"
                        class="bg-white border rounded-2xl shadow-sm overflow-hidden"
                        :class="entry.missing ? 'border-rose-300' : 'border-slate-200'"
                    >
                        <!-- Card header: what it is, and where it goes. -->
                        <div class="flex items-center gap-1.5 px-2.5 py-2 border-b border-slate-100">
                            <button
                                type="button"
                                @click="openKindSheet(index)"
                                :disabled="entry.isRef"
                                class="flex items-center gap-1.5 px-2 py-1 text-2xs font-bold rounded-md border transition-colors disabled:cursor-default"
                                :class="entry.style.chip"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="entry.style.dot" />
                                {{ labelOf(entry) }}
                                <svg v-if="!entry.isRef" class="w-2.5 h-2.5 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            <!-- A repeat says so, and says it is not a copy. -->
                            <span
                                v-if="entry.isRef"
                                class="inline-flex items-center gap-1 text-2xs font-semibold text-slate-500"
                            >
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-4.14-3.36-7.5-7.5-7.5a7.5 7.5 0 00-5.3 2.2M4.5 12c0 4.14 3.36 7.5 7.5 7.5a7.5 7.5 0 005.3-2.2" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 3.5v3.2h3.2M17.5 20.5v-3.2h-3.2" />
                                </svg>
                                {{ t('songs.lyrics.repeat') }}
                            </span>

                            <div class="flex-1" />

                            <button
                                type="button"
                                @click="move(index, -1)"
                                :disabled="index === 0"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 disabled:opacity-25 transition-colors"
                                :aria-label="t('songs.lyrics.move_up')"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="move(index, 1)"
                                :disabled="index === entries.length - 1"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 disabled:opacity-25 transition-colors"
                                :aria-label="t('songs.lyrics.move_down')"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>
                            <button
                                type="button"
                                @click="remove(index)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                :aria-label="t('songs.delete')"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <!-- A repeat shows a line of what it points at, enough
                             to recognise it, and is not editable here: it has
                             no words of its own to edit. -->
                        <div v-if="entry.isRef" class="px-3.5 py-2.5">
                            <p class="text-xs text-slate-500 italic truncate border-l-2 pl-2.5" :class="entry.style.rule">
                                {{ entry.text.split('\n')[0] || t('songs.lyrics.wordless') }}
                            </p>
                        </div>

                        <div v-else-if="entry.missing" class="px-3.5 py-3">
                            <p class="text-xs font-medium text-rose-700">{{ t('songs.lyrics.missing_hint') }}</p>
                        </div>

                        <template v-else>
                            <textarea
                                :ref="el => bindTextarea(el, entry.sourceId)"
                                :value="blocks[index].text"
                                @input="blocks[index].text = $event.target.value; grow($event.target)"
                                rows="2"
                                :maxlength="limits.max_block_chars"
                                :placeholder="WORDLESS_KINDS.includes(entry.kind)
                                    ? t('songs.lyrics.wordless_placeholder')
                                    : t('songs.lyrics.block_placeholder')"
                                class="w-full px-3.5 py-3 text-base leading-relaxed text-slate-800 border-0 focus:ring-0 outline-none resize-none block"
                                autocapitalize="sentences"
                                spellcheck="false"
                            />

                            <!-- The three things done to a block while writing,
                                 as words rather than icons: they are rare
                                 enough that a glyph would need explaining. -->
                            <div class="flex items-center gap-1 px-2 pb-2 -mt-1">
                                <button
                                    type="button"
                                    @click="addRepeat(entry.sourceId)"
                                    :disabled="atCapacity"
                                    class="px-2 py-1.5 text-2xs font-semibold text-indigo-600 rounded-md hover:bg-indigo-50 disabled:opacity-30 transition-colors"
                                >{{ t('songs.lyrics.repeat_this') }}</button>
                                <button
                                    type="button"
                                    @click="splitAt(index)"
                                    :disabled="atCapacity"
                                    class="px-2 py-1.5 text-2xs font-semibold text-slate-600 rounded-md hover:bg-slate-100 disabled:opacity-30 transition-colors"
                                >{{ t('songs.lyrics.split') }}</button>
                                <button
                                    type="button"
                                    @click="mergeWithNext(index)"
                                    :disabled="!canMerge(index)"
                                    class="px-2 py-1.5 text-2xs font-semibold text-slate-600 rounded-md hover:bg-slate-100 disabled:opacity-30 transition-colors"
                                >{{ t('songs.lyrics.merge') }}</button>
                            </div>
                        </template>
                    </div>

                    <!-- Paste again, for a song being assembled from two places. -->
                    <button
                        type="button"
                        @click="pasteOpen = true"
                        class="w-full py-3 text-xs font-semibold text-slate-600 bg-white border border-dashed border-slate-300 rounded-2xl hover:border-slate-400 hover:text-slate-900 transition-colors"
                    >
                        {{ t('songs.lyrics.paste_cta') }}
                    </button>
                </div>
            </template>

            <p v-if="form.errors.blocks" class="mt-3 text-xs font-semibold text-rose-600">{{ form.errors.blocks }}</p>
        </div>

        <!-- The bar that does the adding. Fixed, because the alternative is
             scrolling to the bottom of a long song to add a bridge. -->
        <div
            v-if="!previewing"
            class="fixed bottom-0 left-0 right-0 lg:left-64 z-30 bg-white/95 backdrop-blur border-t border-slate-200"
        >
            <div class="lg:max-w-3xl lg:mx-auto px-3 py-2 space-y-2">
                <!-- Repeat: the shortcut this whole editor exists for. Only
                     here once there is something to repeat. -->
                <div v-if="repeatable.length" class="flex items-center gap-1.5">
                    <span class="shrink-0 text-2xs font-bold text-slate-500 uppercase tracking-wide">{{ t('songs.lyrics.repeat_label') }}</span>
                    <div class="flex-1 min-w-0 flex gap-1.5 overflow-x-auto no-scrollbar">
                        <button
                            v-for="section in repeatable"
                            :key="section.id"
                            type="button"
                            @click="addRepeat(section.id)"
                            :disabled="atCapacity"
                            class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1.5 text-2xs font-bold rounded-lg border disabled:opacity-30 transition-colors"
                            :class="section.style.chip"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="section.style.dot" />
                            {{ section.label }}
                        </button>
                    </div>
                </div>

                <!-- New sections. The four a song is usually made of, then the
                     rest behind the same sheet that relabels a block. -->
                <div class="flex items-center gap-1.5">
                    <span class="shrink-0 text-2xs font-bold text-slate-500 uppercase tracking-wide">{{ t('songs.lyrics.add_label') }}</span>
                    <div class="flex-1 min-w-0 flex gap-1.5 overflow-x-auto no-scrollbar">
                        <button
                            v-for="kind in KIND_ORDER"
                            :key="kind"
                            type="button"
                            @click="addSection(kind)"
                            :disabled="atCapacity"
                            class="shrink-0 inline-flex items-center gap-1.5 px-2.5 py-1.5 text-2xs font-bold text-slate-700 bg-white border border-slate-200 rounded-lg hover:border-slate-300 disabled:opacity-30 transition-colors"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="kindStyle(kind).dot" />
                            {{ t('songs.lyrics.kinds.' + kind) }}
                        </button>
                    </div>
                </div>

                <!-- Save, with the length meter beside it so a song that is
                     about to be refused says so before the button is pressed. -->
                <div class="flex items-center gap-2 pt-0.5">
                    <p
                        class="shrink-0 text-2xs font-semibold tabular-nums"
                        :class="overLength ? 'text-rose-600' : 'text-slate-400'"
                    >
                        {{ Math.round(used / 1000) }}k / {{ Math.round(limits.max_total_chars / 1000) }}k
                    </p>

                    <button
                        type="button"
                        @click="save"
                        :disabled="form.processing || overLength || !dirty"
                        class="flex-1 py-3 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-30 transition-colors"
                    >
                        {{ form.processing ? t('songs.form.saving') : t('songs.lyrics.save') }}
                    </button>
                </div>
            </div>
        </div>

        <LyricsKindSheet
            :open="kindSheetOpen"
            :kind="kindSheetBlock?.kind ?? 'verse'"
            :label="kindSheetBlock?.label ?? ''"
            @close="kindSheetIndex = null"
            @select="applyKind"
        />

        <LyricsPasteSheet
            :open="pasteOpen"
            :has-existing="hasBlocks"
            @close="pasteOpen = false"
            @apply="applyPaste"
        />
    </AppLayout>
</template>
