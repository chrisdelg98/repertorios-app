<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

/**
 * What a song carries, in a list rather than a row.
 *
 * A row of icons has a limit nobody announces: the card is as wide as the
 * card, and the fifth button is the one that breaks it. The words and the
 * chords were already four and five. A list has no such limit — whatever is
 * written over a song next is another line in it.
 *
 * Opening, editing and deleting stay outside. Those are done to a song as a
 * row in a library; these are done to what is inside it.
 */

const props = defineProps({
    song: { type: Object, required: true },
    canWrite: { type: Boolean, default: false },
});

const { t } = useI18n();

const open = ref(false);
const root = ref(null);

/** Nothing to offer is a button nobody should have to find out is empty. */
const hasItems = computed(() => props.canWrite);

function onDocClick(event) {
    if (!root.value?.contains(event.target)) open.value = false;
}

function onKey(event) {
    if (event.key === 'Escape') open.value = false;
}

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <div v-if="hasItems" ref="root" class="relative shrink-0">
        <button
            type="button"
            @click.stop="open = !open"
            class="w-9 h-9 lg:w-10 lg:h-10 flex items-center justify-center rounded-lg bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
            :aria-label="t('services.actions')"
            :aria-expanded="open"
        >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
            </svg>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute right-0 top-full mt-1 w-56 z-20 origin-top-right bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden py-1"
            >
                <Link
                    :href="'/songs/' + song.id + '/lyrics'"
                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                >
                    <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 10h16M4 14h12M4 18h7" />
                    </svg>
                    {{ song.has_lyrics ? t('songs.lyrics.edit') : t('songs.lyrics.add') }}
                </Link>

                <!-- Only where there are words to put them over, which is also
                     the honest answer to why it is missing. -->
                <Link
                    v-if="song.has_lyrics"
                    :href="'/songs/' + song.id + '/chords'"
                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                >
                    <svg class="w-4 h-4 text-indigo-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18V5l10-2v13" />
                        <circle cx="6.5" cy="18" r="2.5" />
                        <circle cx="16.5" cy="16" r="2.5" />
                    </svg>
                    {{ song.has_chords ? t('songs.chords.open') : t('songs.chords.add') }}
                </Link>
            </div>
        </Transition>
    </div>
</template>
