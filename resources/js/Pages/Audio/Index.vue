<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelect from '@/Components/MultiSelect.vue';

const props = defineProps({
    tracks: { type: Array, default: () => [] },
    usage: { type: Object, required: true },
});

const { t, locale } = useI18n();

const confirmingId = ref(null);
const deleting = ref(false);

// Searching and filtering here work the way they do in the song library: the
// list is the same material, and a screen you reach to free space is no use if
// finding one track means reading all of them.
const search = ref('');
const selectedArtists = ref([]);

const SORTS = ['size_desc', 'size_asc', 'added_desc', 'name_asc'];
const sort = ref('size_desc');
const sortOpen = ref(false);

function onSortDocClick(e) {
    if (!e.target.closest('[data-sort]')) sortOpen.value = false;
}

onMounted(() => document.addEventListener('click', onSortDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onSortDocClick));

const availableArtists = computed(() => {
    const map = new Map();

    props.tracks.forEach(track => {
        const trimmed = (track.artist ?? '').trim();
        if (!trimmed) return;
        if (!map.has(trimmed.toLowerCase())) map.set(trimmed.toLowerCase(), trimmed);
    });

    return [...map.values()].sort((a, b) => a.localeCompare(b));
});

const selectedArtistKeys = computed(() =>
    new Set(selectedArtists.value.map(a => a.trim().toLowerCase()))
);

const filteredTracks = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.tracks.filter(track => {
        if (q && !`${track.song} ${track.artist ?? ''}`.toLowerCase().includes(q)) return false;

        if (selectedArtistKeys.value.size) {
            const key = (track.artist ?? '').trim().toLowerCase();
            if (!key || !selectedArtistKeys.value.has(key)) return false;
        }

        return true;
    });
});

function byText(a, b) {
    return (a ?? '').localeCompare(b ?? '', undefined, { sensitivity: 'base' });
}

const COMPARATORS = {
    size_desc:  (a, b) => (b.size ?? 0) - (a.size ?? 0),
    size_asc:   (a, b) => (a.size ?? 0) - (b.size ?? 0),
    added_desc: (a, b) => byText(b.updated_at, a.updated_at),
    name_asc:   (a, b) => byText(a.song, b.song),
};

const sortedTracks = computed(() =>
    [...filteredTracks.value].sort(COMPARATORS[sort.value] ?? COMPARATORS.size_desc)
);

const hasActiveFilters = computed(() => !!(search.value || selectedArtists.value.length));

function clearFilters() {
    search.value = '';
    selectedArtists.value = [];
}

function formatSize(bytes) {
    if (!bytes) return '0 MB';

    return bytes >= 1024 * 1024
        ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        : `${Math.round(bytes / 1024)} KB`;
}

const usedLabel = computed(() => formatSize(props.usage.used_bytes));
const quotaLabel = computed(() => formatSize(props.usage.quota_bytes));

/** Amber before it stops anyone, red once it has. */
const barColour = computed(() => {
    if (props.usage.is_full) return 'bg-red-500';
    return props.usage.percent >= 80 ? 'bg-amber-500' : 'bg-indigo-600';
});

function formatDate(iso) {
    if (!iso) return '';

    return new Date(iso).toLocaleDateString(locale.value === 'es' ? 'es' : 'en', {
        day: 'numeric', month: 'short', year: 'numeric',
    });
}

function remove(track) {
    deleting.value = true;

    router.delete(`/audio/${track.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            confirmingId.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('audio_library.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 lg:max-w-3xl lg:mx-auto">
            <h1 class="text-lg lg:text-2xl font-semibold lg:font-bold text-slate-900">{{ t('audio_library.title') }}</h1>
            <p class="text-sm text-slate-600 mt-1 leading-relaxed">{{ t('audio_library.subtitle') }}</p>

            <!-- How much room is left, before the list rather than after it -->
            <div class="mt-5 bg-white rounded-xl border border-slate-200 px-4 py-3.5">
                <div class="flex items-baseline justify-between gap-2 mb-2">
                    <p class="text-sm font-semibold text-slate-900">
                        {{ usedLabel }}
                        <span class="text-slate-500 font-medium">/ {{ quotaLabel }}</span>
                    </p>
                    <p class="text-xs font-semibold" :class="usage.is_full ? 'text-red-600' : 'text-slate-600'">
                        {{ usage.percent }}%
                    </p>
                </div>

                <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full transition-all duration-300" :class="barColour" :style="{ width: usage.percent + '%' }" />
                </div>

                <p v-if="usage.is_full" class="text-xs font-medium text-red-600 mt-2 leading-relaxed">
                    {{ t('audio_library.full') }}
                </p>
            </div>

            <!-- Filters, as in the song library: this is the same material,
                 and a screen you open to free space is no use if finding one
                 track means reading all of them. -->
            <div v-if="tracks.length" class="mt-4 space-y-2">
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-600 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 110-16 8 8 0 010 16z" />
                    </svg>
                    <input
                        :value="search"
                        @input="search = $event.target.value"
                        type="text"
                        :placeholder="t('songs.search_placeholder')"
                        class="w-full pl-9 pr-9 py-2.5 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <button
                        v-if="search"
                        @click="search = ''"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-slate-600 hover:text-slate-900 rounded-md hover:bg-slate-100 transition-colors"
                        :aria-label="t('songs.filter_clear')"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <MultiSelect
                        v-if="availableArtists.length"
                        v-model="selectedArtists"
                        :label="t('songs.filter_artists')"
                        :options="availableArtists"
                        :clear-label="t('songs.filter_clear')"
                        :search-placeholder="t('songs.filter_search_artist')"
                        :no-results-label="t('songs.filter_no_results_short')"
                        searchable
                    />

                    <div class="relative lg:ml-auto" data-sort>
                        <button
                            type="button"
                            @click.stop="sortOpen = !sortOpen"
                            class="inline-flex items-center gap-2 px-3.5 py-2 min-h-10 text-xs font-semibold text-slate-700 bg-white rounded-lg border border-slate-200 hover:border-slate-300 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M6 12h12M10 17h4" />
                            </svg>
                            <span class="truncate max-w-[160px]">{{ t('audio_library.sort.' + sort) }}</span>
                            <svg
                                class="w-3 h-3 text-slate-500 transition-transform"
                                :class="sortOpen ? 'rotate-180' : ''"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
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
                                v-if="sortOpen"
                                class="absolute right-0 top-full mt-1 w-56 origin-top-right bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden z-30 py-1"
                            >
                                <button
                                    v-for="option in SORTS"
                                    :key="option"
                                    type="button"
                                    @click.stop="sort = option; sortOpen = false"
                                    class="w-full flex items-center gap-2 px-3 py-2 text-xs font-medium text-left hover:bg-slate-50 transition-colors"
                                    :class="sort === option ? 'text-indigo-600' : 'text-slate-700'"
                                >
                                    <span class="flex-1">{{ t('audio_library.sort.' + option) }}</span>
                                    <svg
                                        v-if="sort === option"
                                        class="w-3.5 h-3.5 shrink-0"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>

                <div v-if="hasActiveFilters" class="flex items-center justify-between text-xs text-slate-600 pt-1">
                    <span>{{ t('songs.filter_results', { shown: sortedTracks.length, total: tracks.length }) }}</span>
                    <button @click="clearFilters" class="text-indigo-600 font-semibold hover:text-indigo-700">
                        {{ t('songs.filter_clear') }}
                    </button>
                </div>

                <p v-else class="text-2xs font-semibold text-slate-600 uppercase tracking-wide px-1 pt-1">
                    {{ t('audio_library.count', tracks.length, { count: tracks.length }) }}
                </p>

                <div
                    v-for="track in sortedTracks"
                    :key="track.id"
                    class="bg-white rounded-xl border border-slate-200 px-4 py-3"
                >
                    <div class="flex items-start gap-3">
                        <span class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
                            <svg class="w-[18px] h-[18px] text-indigo-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                            </svg>
                        </span>

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate leading-tight">{{ track.song }}</p>
                            <p class="text-xs font-medium text-slate-600 mt-0.5 truncate">
                                <span v-if="track.artist">{{ track.artist }} · </span>{{ track.version }}
                            </p>
                            <p class="text-2xs font-medium text-slate-500 mt-1 truncate">
                                {{ formatSize(track.size) }} · {{ formatDate(track.updated_at) }}
                            </p>
                        </div>

                        <button
                            type="button"
                            @click="confirmingId = track.id"
                            class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-500 hover:bg-red-100 hover:text-red-600 transition-colors"
                            :aria-label="t('audio_library.remove')"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="confirmingId === track.id" class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100">
                        <p class="flex-1 text-xs font-medium text-slate-700 leading-snug">
                            {{ t('audio_library.remove_confirm') }}
                        </p>
                        <button
                            type="button"
                            @click="confirmingId = null"
                            class="shrink-0 px-2.5 py-1.5 text-2xs font-semibold text-slate-600 rounded-lg border border-slate-300"
                        >{{ t('services.form.cancel') }}</button>
                        <button
                            type="button"
                            @click="remove(track)"
                            :disabled="deleting"
                            class="shrink-0 px-2.5 py-1.5 text-2xs font-semibold text-white bg-red-600 rounded-lg disabled:opacity-50"
                        >{{ t('audio_library.remove') }}</button>
                    </div>
                </div>
            </div>

            <div v-if="tracks.length && !sortedTracks.length" class="mt-4 text-center py-12 bg-white rounded-xl border border-slate-200">
                <p class="text-sm text-slate-600">{{ t('songs.filter_no_results') }}</p>
                <button @click="clearFilters" class="mt-3 text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                    {{ t('songs.filter_clear') }}
                </button>
            </div>

            <div v-else-if="!tracks.length" class="mt-4 text-center py-12 bg-white rounded-xl border border-slate-200">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                </svg>
                <p class="text-sm text-slate-600">{{ t('audio_library.empty') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
