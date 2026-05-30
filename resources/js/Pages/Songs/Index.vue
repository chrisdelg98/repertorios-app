<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import Autocomplete from '@/Components/Autocomplete.vue';

const { t } = useI18n();

const props = defineProps({
    songs: Array,
    can_write: Boolean,
});

// ── Filters ──────────────────────────────────────────────────────────────────
const search           = ref('');
const selectedArtists  = ref([]);
const selectedVersions = ref([]);
const selectedKeys     = ref([]);

const availableArtists = computed(() => {
    // Distinct + normalized: trim whitespace and collapse case-insensitive duplicates.
    // First-seen capitalization wins for display.
    const map = new Map();
    props.songs.forEach(s => {
        const trimmed = (s.artist ?? '').trim();
        if (!trimmed) return;
        const key = trimmed.toLowerCase();
        if (!map.has(key)) map.set(key, trimmed);
    });
    return [...map.values()].sort((a, b) => a.localeCompare(b));
});

const availableSongNames = computed(() => {
    const map = new Map();
    props.songs.forEach(s => {
        const trimmed = (s.name ?? '').trim();
        if (!trimmed) return;
        const key = trimmed.toLowerCase();
        if (!map.has(key)) map.set(key, trimmed);
    });
    return [...map.values()].sort((a, b) => a.localeCompare(b));
});

const availableVersions = computed(() => {
    const set = new Set();
    props.songs.forEach(s => s.versions?.forEach(v => v.name && set.add(v.name)));
    return [...set].sort();
});

const availableKeys = computed(() => {
    const set = new Set();
    props.songs.forEach(s => s.versions?.forEach(v => v.key && set.add(v.key)));
    return [...set].sort();
});

const selectedArtistKeys = computed(() =>
    new Set(selectedArtists.value.map(a => a.trim().toLowerCase()))
);

const filteredSongs = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.songs.filter(song => {
        if (q) {
            const hay = `${song.name} ${song.artist ?? ''}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        if (selectedArtistKeys.value.size) {
            const artistKey = (song.artist ?? '').trim().toLowerCase();
            if (!artistKey || !selectedArtistKeys.value.has(artistKey)) return false;
        }
        if (selectedVersions.value.length) {
            const has = song.versions?.some(v => selectedVersions.value.includes(v.name));
            if (!has) return false;
        }
        if (selectedKeys.value.length) {
            const has = song.versions?.some(v => selectedKeys.value.includes(v.key));
            if (!has) return false;
        }
        return true;
    });
});

const hasActiveFilters = computed(() =>
    !!(search.value
        || selectedArtists.value.length
        || selectedVersions.value.length
        || selectedKeys.value.length)
);

function clearFilters() {
    search.value           = '';
    selectedArtists.value  = [];
    selectedVersions.value = [];
    selectedKeys.value     = [];
}

// ── Add sheet ─────────────────────────────────────────────────────────────────
const showAddForm = ref(false);

const form = useForm({
    name: '',
    artist: '',
    version_name: 'Original',
    key: '',
    bpm: '',
    notes: '',
    youtube_url: '',
});

const PRESET_VERSIONS = ['Original', 'Live', 'Acoustic'];

function submit() {
    form.name   = form.name.trim();
    form.artist = form.artist.trim();
    form.post('/songs', {
        onSuccess: () => {
            showAddForm.value = false;
            form.reset();
            form.version_name = 'Original';
        },
    });
}

function cancelAdd() {
    showAddForm.value = false;
    form.reset();
    form.version_name = 'Original';
}

// ── Edit sheet ────────────────────────────────────────────────────────────────
const editingSong = ref(null);
const expandedVersions = ref({});

const editForm = useForm({
    name: '',
    artist: '',
    versions: [],
});

function openEdit(song) {
    editingSong.value = song;
    editForm.name    = song.name;
    editForm.artist  = song.artist ?? '';
    editForm.versions = (song.versions ?? []).map(v => ({
        id:          v.id,
        name:        v.name,
        key:         v.key ?? '',
        bpm:         v.bpm ?? '',
        notes:       v.notes ?? '',
        youtube_url: v.youtube_url ?? '',
    }));
    expandedVersions.value = {};
    if (editForm.versions.length === 1) {
        expandedVersions.value[editForm.versions[0].id] = true;
    }
}

function closeEdit() {
    editingSong.value = null;
    expandedVersions.value = {};
    editForm.reset();
}

function toggleVersion(id) {
    expandedVersions.value[id] = !expandedVersions.value[id];
}

function submitEdit() {
    editForm.name   = editForm.name.trim();
    editForm.artist = editForm.artist.trim();
    editForm.put('/songs/' + editingSong.value.id, {
        onSuccess: closeEdit,
    });
}

// ── Delete ────────────────────────────────────────────────────────────────────
const confirmDeleteId = ref(null);
const deleting = ref(false);

function askDelete(id) {
    confirmDeleteId.value = id;
}

function cancelDelete() {
    confirmDeleteId.value = null;
}

function confirmDelete() {
    if (!confirmDeleteId.value) return;
    deleting.value = true;
    router.delete('/songs/' + confirmDeleteId.value, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            confirmDeleteId.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('songs.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 lg:max-w-3xl lg:mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-4 lg:mb-6">
                <h1 class="text-base lg:text-2xl font-semibold lg:font-bold text-slate-900">{{ t('songs.library') }}</h1>
                <button
                    v-if="can_write"
                    @click="showAddForm = true"
                    class="flex items-center gap-1.5 px-3 lg:px-4 py-1.5 lg:py-2 bg-indigo-600 text-white text-xs lg:text-sm font-semibold rounded-lg"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('songs.create') }}
                </button>
            </div>

            <!-- Filters -->
            <div v-if="songs.length" class="mb-3 lg:mb-4 space-y-2">
                <!-- Search -->
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 110-16 8 8 0 010 16z" />
                    </svg>
                    <input
                        v-model="search"
                        type="text"
                        :placeholder="t('songs.search_placeholder')"
                        class="w-full pl-9 pr-9 py-2.5 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <button
                        v-if="search"
                        @click="search = ''"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-slate-500 hover:text-slate-600 rounded-md hover:bg-slate-100 transition-colors"
                        :aria-label="t('songs.filter_clear')"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Filter dropdowns -->
                <div class="flex flex-wrap items-center gap-1.5">
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
                    <MultiSelect
                        v-if="availableVersions.length"
                        v-model="selectedVersions"
                        :label="t('songs.filter_versions')"
                        :options="availableVersions"
                        :clear-label="t('songs.filter_clear')"
                    />
                    <MultiSelect
                        v-if="availableKeys.length"
                        v-model="selectedKeys"
                        :label="t('songs.filter_keys')"
                        :options="availableKeys"
                        :clear-label="t('songs.filter_clear')"
                        bold
                    />
                </div>

                <!-- Results summary -->
                <div v-if="hasActiveFilters" class="flex items-center justify-between text-[11px] text-slate-500 pt-1">
                    <span>{{ t('songs.filter_results', { shown: filteredSongs.length, total: songs.length }) }}</span>
                    <button
                        @click="clearFilters"
                        class="text-indigo-600 font-semibold hover:text-indigo-700"
                    >{{ t('songs.filter_clear') }}</button>
                </div>
            </div>

            <!-- Empty state: no songs at all -->
            <div v-if="!songs.length" class="text-center py-16 text-slate-500">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                </svg>
                <p class="text-sm">{{ t('songs.empty') }}</p>
            </div>

            <!-- Empty state: no matches -->
            <div v-else-if="!filteredSongs.length" class="text-center py-16 text-slate-500">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 110-16 8 8 0 010 16z" />
                </svg>
                <p class="text-sm">{{ t('songs.filter_no_results') }}</p>
                <button
                    @click="clearFilters"
                    class="mt-3 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
                >{{ t('songs.filter_clear') }}</button>
            </div>

            <!-- Songs list -->
            <div v-else class="space-y-2 lg:space-y-0 lg:grid lg:grid-cols-2 xl:grid-cols-3 lg:gap-3">
                <div
                    v-for="song in filteredSongs"
                    :key="song.id"
                    class="group bg-white rounded-xl border border-slate-200 hover:border-slate-300 hover:shadow-sm transition flex flex-col"
                >
                    <!-- Header: title + artist (uses full width on lg+) -->
                    <div class="px-4 pt-3.5 pb-2 lg:pt-4 lg:pb-3 flex items-start gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-slate-900 text-sm leading-snug line-clamp-2">{{ song.name }}</p>
                            <p v-if="song.artist" class="text-xs text-slate-500 mt-0.5 truncate">{{ song.artist }}</p>
                        </div>

                        <!-- Mobile actions stay inline -->
                        <div v-if="can_write" class="lg:hidden flex items-center gap-1 shrink-0">
                            <button
                                @click="openEdit(song)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                                :aria-label="t('songs.edit')"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                            </button>
                            <button
                                @click="askDelete(song.id)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition-colors"
                                :aria-label="t('songs.delete')"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Versions row -->
                    <div v-if="song.versions?.length" class="px-4 pb-3 lg:pb-3 flex flex-wrap gap-1.5">
                        <span
                            v-for="v in song.versions"
                            :key="v.id"
                            class="inline-flex items-center gap-1 text-[11px] text-slate-600 bg-slate-100 rounded-md px-2 py-0.5 font-medium"
                        >
                            {{ v.name }}
                            <span v-if="v.key" class="text-indigo-700 font-bold">· {{ v.key }}</span>
                        </span>
                    </div>

                    <!-- Desktop footer: divider + actions aligned right -->
                    <div v-if="can_write" class="hidden lg:flex items-center justify-end gap-1 mt-auto px-4 py-2.5 border-t border-slate-100">
                        <button
                            @click="openEdit(song)"
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                            :aria-label="t('songs.edit')"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                            </svg>
                        </button>
                        <button
                            @click="askDelete(song.id)"
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition-colors"
                            :aria-label="t('songs.delete')"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add bottom sheet -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showAddForm && can_write"
                    class="fixed inset-0 z-40 bg-black/40"
                    @click="cancelAdd"
                />
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
                    v-if="showAddForm && can_write"
                    class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl max-h-[88vh] flex flex-col shadow-xl"
                >
                    <!-- Header (sticky) -->
                    <div class="px-4 pt-3 pb-2 border-b border-slate-100">
                        <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
                        <h2 class="text-base font-semibold text-slate-900">{{ t('songs.create') }}</h2>
                    </div>

                    <!-- Scrollable body -->
                    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                        <!-- Song-level fields -->
                        <div class="space-y-3">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-600">{{ t('songs.form.name') }}</label>
                                <Autocomplete
                                    v-model="form.name"
                                    :suggestions="availableSongNames"
                                    :placeholder="t('songs.form.name')"
                                />
                                <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-600">{{ t('songs.form.artist') }}</label>
                                <Autocomplete
                                    v-model="form.artist"
                                    :suggestions="availableArtists"
                                    :placeholder="t('songs.form.artist_placeholder')"
                                    :max-length="50"
                                />
                            </div>
                        </div>

                        <!-- Version section (matches edit-sheet card anatomy) -->
                        <div class="space-y-2">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                {{ t('songs.form.version') }}
                            </p>

                            <div class="border border-slate-200 rounded-xl overflow-hidden">
                                <div class="px-3 py-2.5 bg-slate-50 space-y-2">
                                    <div class="flex gap-1.5 flex-wrap">
                                        <button
                                            v-for="v in PRESET_VERSIONS"
                                            :key="v"
                                            type="button"
                                            @click="form.version_name = v"
                                            :class="[
                                                'px-2.5 py-1 text-xs font-medium rounded-md border transition-colors',
                                                form.version_name === v
                                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                                    : 'border-slate-300 text-slate-600 bg-white',
                                            ]"
                                        >{{ v }}</button>
                                    </div>
                                    <input
                                        v-model="form.version_name"
                                        type="text"
                                        :placeholder="t('songs.form.version_hint')"
                                        class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                    <p v-if="form.errors.version_name" class="text-xs text-red-600">{{ form.errors.version_name }}</p>
                                </div>

                                <div class="p-3 space-y-3 bg-white">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="space-y-1.5">
                                            <label class="block text-[11px] font-medium text-slate-600">{{ t('songs.form.key') }}</label>
                                            <input
                                                v-model="form.key"
                                                type="text"
                                                placeholder="C, Am, Bb…"
                                                maxlength="10"
                                                class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-[11px] font-medium text-slate-600">{{ t('songs.form.bpm') }}</label>
                                            <input
                                                v-model="form.bpm"
                                                type="number"
                                                min="20" max="300"
                                                placeholder="120"
                                                class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            />
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="block text-[11px] font-medium text-slate-600">{{ t('songs.form.youtube_url') }}</label>
                                        <input
                                            v-model="form.youtube_url"
                                            type="url"
                                            placeholder="https://youtube.com/…"
                                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="block text-[11px] font-medium text-slate-600">{{ t('songs.form.notes') }}</label>
                                        <textarea
                                            v-model="form.notes"
                                            rows="2"
                                            maxlength="1000"
                                            :placeholder="t('songs.form.notes_placeholder')"
                                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer (sticky) -->
                    <div class="px-4 py-3 border-t border-slate-100 flex gap-2">
                        <button
                            type="button"
                            @click="cancelAdd"
                            class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300"
                        >
                            {{ t('songs.form.cancel') }}
                        </button>
                        <button
                            @click="submit"
                            :disabled="form.processing"
                            class="flex-1 py-2.5 bg-indigo-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl"
                        >
                            {{ form.processing ? t('songs.form.saving') : t('songs.form.save') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Delete confirmation bottom sheet -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="confirmDeleteId"
                    class="fixed inset-0 z-40 bg-black/40"
                    @click="cancelDelete"
                />
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
                    v-if="confirmDeleteId"
                    class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-3 pb-8 shadow-xl"
                >
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />

                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-red-50 rounded-full flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h2 class="text-base font-semibold text-slate-900">{{ t('songs.delete_title') }}</h2>
                    </div>

                    <p class="text-sm text-slate-500 mb-4">{{ t('songs.delete_confirm') }}</p>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="cancelDelete"
                            class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300"
                        >
                            {{ t('songs.cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="confirmDelete"
                            :disabled="deleting"
                            class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors"
                        >
                            {{ deleting ? t('songs.deleting') : t('songs.delete') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Edit bottom sheet -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="editingSong"
                    class="fixed inset-0 z-40 bg-black/40"
                    @click="closeEdit"
                />
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
                    v-if="editingSong"
                    class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl max-h-[88vh] flex flex-col shadow-xl"
                >
                    <!-- Header (sticky) -->
                    <div class="px-4 pt-3 pb-2 border-b border-slate-100">
                        <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
                        <h2 class="text-base font-semibold text-slate-900">{{ t('songs.edit_title') }}</h2>
                    </div>

                    <!-- Scrollable body -->
                    <div class="flex-1 overflow-y-auto px-4 py-4 space-y-4">
                        <!-- Song-level fields -->
                        <div class="space-y-3">
                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-600">{{ t('songs.form.name') }}</label>
                                <Autocomplete
                                    v-model="editForm.name"
                                    :suggestions="availableSongNames"
                                    :placeholder="t('songs.form.name')"
                                />
                                <p v-if="editForm.errors.name" class="text-xs text-red-600">{{ editForm.errors.name }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-600">{{ t('songs.form.artist') }}</label>
                                <Autocomplete
                                    v-model="editForm.artist"
                                    :suggestions="availableArtists"
                                    :placeholder="t('songs.form.artist_placeholder')"
                                    :max-length="50"
                                />
                            </div>
                        </div>

                        <!-- Versions -->
                        <div v-if="editForm.versions.length" class="space-y-2">
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                {{ t('songs.versions') }}
                            </p>

                            <div
                                v-for="(v, idx) in editForm.versions"
                                :key="v.id"
                                class="border border-slate-200 rounded-xl overflow-hidden"
                            >
                                <!-- Version header -->
                                <button
                                    type="button"
                                    @click="toggleVersion(v.id)"
                                    class="w-full flex items-center justify-between px-3 py-2.5 bg-slate-50 hover:bg-slate-100 transition-colors"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-sm font-medium text-slate-800 truncate">{{ v.name || t('songs.form.version') }}</span>
                                        <span v-if="v.key" class="text-xs text-indigo-600 font-semibold">{{ v.key }}</span>
                                        <span v-if="v.bpm" class="text-xs text-slate-500">{{ v.bpm }} bpm</span>
                                    </div>
                                    <svg
                                        class="w-4 h-4 text-slate-500 transition-transform shrink-0"
                                        :class="expandedVersions[v.id] ? 'rotate-180' : ''"
                                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <!-- Version fields -->
                                <div v-if="expandedVersions[v.id]" class="p-3 space-y-3 bg-white">
                                    <div class="space-y-1.5">
                                        <label class="block text-[11px] font-medium text-slate-600">{{ t('songs.form.version') }}</label>
                                        <input
                                            v-model="v.name"
                                            type="text"
                                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>

                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="space-y-1.5">
                                            <label class="block text-[11px] font-medium text-slate-600">{{ t('songs.form.key') }}</label>
                                            <input
                                                v-model="v.key"
                                                type="text"
                                                placeholder="C, Am, Bb…"
                                                maxlength="10"
                                                class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            />
                                        </div>
                                        <div class="space-y-1.5">
                                            <label class="block text-[11px] font-medium text-slate-600">{{ t('songs.form.bpm') }}</label>
                                            <input
                                                v-model="v.bpm"
                                                type="number"
                                                min="20" max="300"
                                                placeholder="120"
                                                class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                            />
                                        </div>
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="block text-[11px] font-medium text-slate-600">{{ t('songs.form.youtube_url') }}</label>
                                        <input
                                            v-model="v.youtube_url"
                                            type="url"
                                            placeholder="https://youtube.com/…"
                                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>

                                    <div class="space-y-1.5">
                                        <label class="block text-[11px] font-medium text-slate-600">{{ t('songs.form.notes') }}</label>
                                        <textarea
                                            v-model="v.notes"
                                            rows="2"
                                            maxlength="1000"
                                            :placeholder="t('songs.form.notes_placeholder')"
                                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer (sticky) -->
                    <div class="px-4 py-3 border-t border-slate-100 flex gap-2">
                        <button
                            type="button"
                            @click="closeEdit"
                            class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300"
                        >
                            {{ t('songs.form.cancel') }}
                        </button>
                        <button
                            @click="submitEdit"
                            :disabled="editForm.processing"
                            class="flex-1 py-2.5 bg-indigo-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl"
                        >
                            {{ editForm.processing ? t('songs.form.saving') : t('songs.form.save') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
