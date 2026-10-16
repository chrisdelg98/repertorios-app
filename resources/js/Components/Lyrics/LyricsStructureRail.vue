<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

/**
 * The shape of the song, down one side.
 *
 * Its reason to exist is what happens at large type: three lines fill the
 * screen and the song becomes a tunnel, with no way to know that the chorus is
 * next or that there are two verses still to come. The rail answers that
 * without costing the words any size.
 *
 * Three widths rather than a switch. Thin is the one that earns its keep on a
 * phone: a column of coloured dots still says "chorus, verse, chorus, bridge"
 * at a glance, and the numeral says which verse, in two characters instead of
 * eight. Full is for a tablet held upright, where there is room for both.
 */
const props = defineProps({
    entries: { type: Array, required: true },
    labelOf: { type: Function, required: true },
    shortLabelOf: { type: Function, required: true },
    activeIndex: { type: Number, default: 0 },
    state: { type: String, default: 'full' },  // full · thin · hidden
    dark: { type: Boolean, default: false },
    /** Highlights the row being read. Off in flow mode, where nothing is. */
    follow: { type: Boolean, default: true },
});

const emit = defineEmits(['select']);

const isFull = computed(() => props.state === 'full');

const shell = computed(() => props.dark
    ? 'border-white/10'
    : 'border-slate-200');

function rowClass(index) {
    const active = props.follow && index === props.activeIndex;

    if (props.dark) {
        return active
            ? 'bg-white/10 text-white'
            : 'text-slate-400 hover:bg-white/5 hover:text-slate-200';
    }

    return active
        ? 'bg-slate-100 text-slate-900'
        : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700';
}
</script>

<template>
    <!-- Full: the structure as a list you can read and tap. -->
    <nav
        v-if="isFull"
        class="shrink-0 w-36 sm:w-40 lg:w-44 border-r overflow-y-auto py-2"
        :class="shell"
        :aria-label="t('songs.lyrics.structure')"
    >
        <button
            v-for="(entry, index) in entries"
            :key="entry.key"
            type="button"
            @click="emit('select', index)"
            class="w-full flex items-center gap-2.5 px-3 py-2 text-left rounded-lg transition-colors"
            :class="rowClass(index)"
            :aria-current="follow && index === activeIndex ? 'true' : undefined"
        >
            <span
                class="shrink-0 w-2 h-2 rounded-full"
                :class="dark ? entry.style.dotDark : entry.style.dot"
            />

            <span class="flex-1 min-w-0 text-xs font-semibold truncate">
                {{ labelOf(entry) }}
            </span>

            <!-- A repeat is marked rather than hidden: the structure is the
                 point here, and "the chorus again" is part of it. -->
            <svg
                v-if="entry.isRef"
                class="shrink-0 w-3 h-3 opacity-50"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
                :aria-label="t('songs.lyrics.repeat')"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-4.14-3.36-7.5-7.5-7.5a7.5 7.5 0 00-5.3 2.2M4.5 12c0 4.14 3.36 7.5 7.5 7.5a7.5 7.5 0 005.3-2.2" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.5 3.5v3.2h3.2M17.5 20.5v-3.2h-3.2" />
            </svg>
        </button>
    </nav>

    <!-- Thin: dots and numerals. Still the whole structure, at a sixth of the
         width — which on a phone is the difference between having it and not. -->
    <nav
        v-else-if="state === 'thin'"
        class="shrink-0 w-10 sm:w-11 border-r overflow-y-auto py-2 flex flex-col items-center gap-0.5"
        :class="shell"
        :aria-label="t('songs.lyrics.structure')"
    >
        <button
            v-for="(entry, index) in entries"
            :key="entry.key"
            type="button"
            @click="emit('select', index)"
            class="shrink-0 w-8 py-1.5 flex flex-col items-center gap-1 rounded-lg transition-colors"
            :class="rowClass(index)"
            :title="labelOf(entry)"
            :aria-label="labelOf(entry)"
            :aria-current="follow && index === activeIndex ? 'true' : undefined"
        >
            <span
                class="rounded-full transition-all"
                :class="[
                    dark ? entry.style.dotDark : entry.style.dot,
                    entry.isRef ? 'w-1.5 h-1.5 opacity-60' : 'w-2.5 h-2.5',
                ]"
            />
            <span
                v-if="shortLabelOf(entry)"
                class="text-2xs font-bold leading-none tabular-nums"
            >{{ shortLabelOf(entry) }}</span>
        </button>
    </nav>
</template>
