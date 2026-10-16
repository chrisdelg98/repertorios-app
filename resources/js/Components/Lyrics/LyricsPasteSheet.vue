<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { parsePasted, decorate, kindStyle, roman } from '@/Utils/lyrics';

const { t } = useI18n();

/**
 * Paste the whole song and let the app work out the shape.
 *
 * This is the door almost everyone comes in by: the words already exist on a
 * site somewhere, and the realistic alternative to pasting them is typing
 * three hundred of them on a phone, which means the lyrics never get added at
 * all. So the paste is where the effort goes.
 *
 * What comes out is a guess, shown before it is accepted, and every part of it
 * is editable afterwards. The point is not to be right — it is to leave as
 * little as possible to do by hand.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    /** Appending is offered only when there is something to append to. */
    hasExisting: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'apply']);

const raw = ref('');

watch(() => props.open, (open) => {
    if (open) raw.value = '';
});

const parsed = computed(() => parsePasted(raw.value));
const entries = computed(() => decorate(parsed.value.blocks));
const ready = computed(() => parsed.value.blocks.length > 0);

/** Only the findings worth a line; a zero is noise. */
const findings = computed(() => {
    const { stats, blocks } = parsed.value;
    const out = [];

    if (blocks.length) out.push(t('songs.lyrics.paste_sections', { count: blocks.length }));
    if (stats.repeats) out.push(t('songs.lyrics.paste_repeats', { count: stats.repeats }));
    if (stats.labelled) out.push(t('songs.lyrics.paste_labelled', { count: stats.labelled }));
    if (stats.chordLines) out.push(t('songs.lyrics.paste_chords', { count: stats.chordLines }));

    return out;
});

function apply(mode) {
    emit('apply', { blocks: parsed.value.blocks, mode });
}

function labelOf(entry) {
    if (entry.label) return entry.label;

    const name = t('songs.lyrics.kinds.' + entry.kind);

    return entry.num ? name + ' ' + roman(entry.num) : name;
}
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
            <div v-if="open" class="fixed inset-0 z-40 bg-black/40" @click="emit('close')" />
        </Transition>

        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="open"
                class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full sm:max-w-lg z-50 bg-white rounded-t-2xl h-[92vh] sm:h-[88vh] flex flex-col shadow-xl"
            >
                <div class="shrink-0 px-4 pt-3 pb-3 border-b border-slate-100">
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
                    <h2 class="text-base font-bold text-slate-900">{{ t('songs.lyrics.paste_title') }}</h2>
                    <p class="text-xs text-slate-600 mt-0.5 leading-relaxed">{{ t('songs.lyrics.paste_hint') }}</p>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-3 space-y-3 min-h-0">
                    <textarea
                        v-model="raw"
                        rows="10"
                        :placeholder="t('songs.lyrics.paste_placeholder')"
                        class="w-full px-3 py-3 text-base font-mono leading-relaxed border border-slate-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none resize-y"
                        autocapitalize="sentences"
                        spellcheck="false"
                    />

                    <!-- What it found, before anything is committed. -->
                    <div v-if="ready" class="space-y-2.5">
                        <ul class="space-y-1">
                            <li
                                v-for="line in findings"
                                :key="line"
                                class="flex items-start gap-2 text-xs font-medium text-slate-700"
                            >
                                <svg class="shrink-0 w-3.5 h-3.5 text-emerald-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg>
                                {{ line }}
                            </li>
                        </ul>

                        <!-- The shape it read, as the structure rail will show
                             it. Seeing "Verso · Coro · Verso · Coro" is what
                             tells you the guess was right. -->
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            <span
                                v-for="entry in entries"
                                :key="entry.key"
                                class="inline-flex items-center gap-1.5 px-2 py-1 text-2xs font-bold rounded-md border"
                                :class="[kindStyle(entry.kind).chip, entry.isRef ? 'opacity-60' : '']"
                            >
                                <span class="w-1.5 h-1.5 rounded-full" :class="kindStyle(entry.kind).dot" />
                                {{ labelOf(entry) }}
                                <svg v-if="entry.isRef" class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-4.14-3.36-7.5-7.5-7.5a7.5 7.5 0 00-5.3 2.2M4.5 12c0 4.14 3.36 7.5 7.5 7.5a7.5 7.5 0 005.3-2.2" />
                                </svg>
                            </span>
                        </div>

                        <p class="text-2xs text-slate-500 leading-relaxed pt-0.5">
                            {{ t('songs.lyrics.paste_editable') }}
                        </p>
                    </div>
                </div>

                <div class="shrink-0 px-4 py-3 border-t border-slate-100 space-y-2">
                    <button
                        type="button"
                        @click="apply('replace')"
                        :disabled="!ready"
                        class="w-full py-3 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-30 transition-colors"
                    >
                        {{ hasExisting ? t('songs.lyrics.paste_replace') : t('songs.lyrics.paste_use') }}
                    </button>

                    <button
                        v-if="hasExisting"
                        type="button"
                        @click="apply('append')"
                        :disabled="!ready"
                        class="w-full py-3 text-sm font-semibold text-indigo-700 bg-indigo-50 rounded-xl hover:bg-indigo-100 disabled:opacity-30 transition-colors"
                    >
                        {{ t('songs.lyrics.paste_append') }}
                    </button>

                    <button
                        type="button"
                        @click="emit('close')"
                        class="w-full py-2.5 text-sm font-semibold text-slate-600 hover:text-slate-900 transition-colors"
                    >
                        {{ t('songs.cancel') }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
