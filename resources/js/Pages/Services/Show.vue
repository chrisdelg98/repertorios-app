<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();

const props = defineProps({
    service: Object,
    song_versions: Array,
    can_write: Boolean,
});

// --- Add song sheet ---
const showAddSheet = ref(false);
const search = ref('');
const selectedVersionId = ref(null);
const filtered = computed(() => {
    if (!search.value.trim()) return props.song_versions.slice(0, 8);
    const q = search.value.toLowerCase();
    return props.song_versions.filter(sv =>
        sv.song_name.toLowerCase().includes(q) || sv.version_name.toLowerCase().includes(q)
    ).slice(0, 8);
});

const isNewSong = computed(() =>
    search.value.trim() &&
    !props.song_versions.some(sv => sv.song_name.toLowerCase() === search.value.toLowerCase())
);

const addForm = useForm({
    song_version_id: null,
    song_name: '',
    artist: '',
    version_name: 'Original',
    key: '',
    bpm: '',
    youtube_url: '',
    notes: '',
});
const showSongDetails = ref(false);

function selectExisting(sv) {
    selectedVersionId.value = sv.id;
    search.value = sv.display;
}

function closeAddSheet() {
    showAddSheet.value = false;
    search.value = '';
    selectedVersionId.value = null;
    showSongDetails.value = false;
    addForm.reset();
}

function submitAdd() {
    if (selectedVersionId.value) {
        addForm.song_version_id = selectedVersionId.value;
        addForm.song_name = '';
        addForm.artist = '';
    } else {
        addForm.song_version_id = null;
        addForm.song_name = search.value.trim();
    }
    addForm.post('/services/' + props.service.id + '/songs', {
        onSuccess: () => closeAddSheet(),
    });
}

function removeSong(serviceSongId) {
    router.delete('/services/' + props.service.id + '/songs/' + serviceSongId);
}

// --- Duplicate ---
const showDuplicateSheet = ref(false);
const dupForm = useForm({ date: '' });

function submitDuplicate() {
    dupForm.post('/services/' + props.service.id + '/duplicate', {
        onSuccess: () => { showDuplicateSheet.value = false; },
    });
}

// --- Delete ---
function deleteService() {
    if (confirm(t('services.delete_confirm'))) {
        router.delete('/services/' + props.service.id);
    }
}

// --- Share ---
const showShareSheet = ref(false);
const sharing = ref(false);
const shareData = ref(null);
const copied = ref(false);

async function openShare() {
    if (shareData.value) {
        showShareSheet.value = true;
        return;
    }
    sharing.value = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const res = await fetch('/services/' + props.service.id + '/share', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        shareData.value = await res.json();
        showShareSheet.value = true;
    } finally {
        sharing.value = false;
    }
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(shareData.value.url);
    } catch {
        const el = document.createElement('textarea');
        el.value = shareData.value.url;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

function formatDate(d) {
    if (!d) return '';
    const datePart = d.includes('T') ? d.split('T')[0] : d;
    const date = new Date(datePart + 'T00:00:00');
    return date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

function typeLabel(type) {
    return type === 'other' ? t('services.type_other') : type;
}

// --- Song reorder ---
const localSongs = ref([...props.service.service_songs]);
watch(() => props.service.service_songs, (songs) => { localSongs.value = [...songs]; });

let reorderTimer = null;

function moveUp(i) {
    if (i === 0) return;
    const arr = [...localSongs.value];
    [arr[i - 1], arr[i]] = [arr[i], arr[i - 1]];
    localSongs.value = arr;
    scheduleReorder();
}

function moveDown(i) {
    if (i === localSongs.value.length - 1) return;
    const arr = [...localSongs.value];
    [arr[i], arr[i + 1]] = [arr[i + 1], arr[i]];
    localSongs.value = arr;
    scheduleReorder();
}

function scheduleReorder() {
    clearTimeout(reorderTimer);
    reorderTimer = setTimeout(() => {
        router.post(
            '/services/' + props.service.id + '/songs/reorder',
            { ids: localSongs.value.map(s => s.id) },
            { preserveScroll: true },
        );
    }, 600);
}
</script>

<template>
    <Head :title="typeLabel(service.type)" />

    <AppLayout>
        <div class="px-4 py-5 max-w-lg mx-auto">
            <!-- Service header card -->
            <div class="bg-gradient-to-br from-indigo-600 to-violet-600 rounded-2xl p-5 text-white mb-4 shadow-md shadow-indigo-200">
                <p class="text-xs font-medium text-indigo-200 uppercase tracking-wide">
                    {{ t('services.title') }}
                </p>
                <h1 class="text-xl font-bold mt-1 capitalize">{{ typeLabel(service.type) }}</h1>
                <p class="text-sm text-indigo-100 mt-1">
                    {{ formatDate(service.date) }}
                    <span v-if="service.time"> · {{ service.time.slice(0, 5) }}</span>
                </p>
                <p v-if="service.notes" class="mt-3 pt-3 border-t border-white/15 text-sm text-indigo-100">
                    {{ service.notes }}
                </p>
            </div>

            <!-- Action buttons row -->
            <div class="flex items-stretch gap-2 mb-5">
                <button
                    @click="openShare"
                    :disabled="sharing"
                    class="flex-1 flex items-center justify-center gap-1.5 py-2.5 bg-white border border-indigo-200 text-indigo-600 text-sm font-semibold rounded-xl hover:bg-indigo-50 disabled:opacity-50 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                    </svg>
                    {{ sharing ? t('services.share_generating') : t('services.share') }}
                </button>

                <template v-if="can_write">
                    <button
                        @click="showDuplicateSheet = true"
                        class="w-11 flex items-center justify-center bg-white border border-slate-200 text-slate-500 rounded-xl hover:bg-slate-50 transition-colors"
                        :aria-label="t('services.duplicate')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                    </button>
                    <a
                        :href="'/services/' + service.id + '/edit'"
                        class="w-11 flex items-center justify-center bg-white border border-slate-200 text-slate-500 rounded-xl hover:bg-slate-50 transition-colors"
                        :aria-label="t('services.edit_service')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                        </svg>
                    </a>
                    <button
                        @click="deleteService"
                        class="w-11 flex items-center justify-center bg-red-50 text-red-500 rounded-xl hover:bg-red-100 transition-colors"
                        :aria-label="t('services.delete_service')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </template>
            </div>

            <!-- Songs section heading -->
            <div class="flex items-center justify-between mb-2 px-1">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    {{ t('services.setlist') }}
                </p>
                <span v-if="localSongs.length" class="text-xs text-slate-400">{{ localSongs.length }}</span>
            </div>

            <!-- Songs list -->
            <div class="space-y-2 mb-4">
                <div
                    v-if="!localSongs.length"
                    class="text-center py-10 bg-white rounded-xl border border-slate-200 text-slate-400 text-sm"
                >
                    {{ t('services.no_songs') }}
                </div>

                <div
                    v-for="(ss, i) in localSongs"
                    :key="ss.id"
                    class="flex items-center gap-2.5 bg-white rounded-xl px-3 py-3 border border-slate-200"
                >
                    <!-- Reorder buttons -->
                    <div v-if="can_write && localSongs.length > 1" class="flex flex-col gap-0.5 shrink-0">
                        <button
                            @click="moveUp(i)"
                            :disabled="i === 0"
                            :aria-label="t('services.move_up')"
                            class="w-6 h-5 flex items-center justify-center rounded bg-slate-100 text-slate-500 hover:bg-indigo-100 hover:text-indigo-600 disabled:opacity-30 disabled:hover:bg-slate-100 disabled:hover:text-slate-500 transition-colors"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                            </svg>
                        </button>
                        <button
                            @click="moveDown(i)"
                            :disabled="i === localSongs.length - 1"
                            :aria-label="t('services.move_down')"
                            class="w-6 h-5 flex items-center justify-center rounded bg-slate-100 text-slate-500 hover:bg-indigo-100 hover:text-indigo-600 disabled:opacity-30 disabled:hover:bg-slate-100 disabled:hover:text-slate-500 transition-colors"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <span class="w-6 h-6 flex items-center justify-center text-[11px] font-bold text-indigo-600 bg-indigo-50 rounded-full shrink-0">{{ i + 1 }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-900 truncate leading-tight">{{ ss.song_version.song.name }}</p>
                        <p class="text-xs text-slate-500 mt-0.5 truncate">
                            <span v-if="ss.song_version.song.artist">{{ ss.song_version.song.artist }} · </span>{{ ss.song_version.name }}<span v-if="ss.song_version.key" class="text-indigo-500 font-medium"> · {{ ss.song_version.key }}</span>
                        </p>
                    </div>
                    <button
                        v-if="can_write"
                        @click="removeSong(ss.id)"
                        class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition-colors"
                        :aria-label="t('services.delete')"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Add song button -->
            <button
                v-if="can_write"
                @click="showAddSheet = true"
                class="w-full flex items-center justify-center gap-2 py-3.5 border-2 border-dashed border-indigo-300 text-indigo-600 text-sm font-semibold rounded-xl hover:bg-indigo-50 transition-colors"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                {{ t('services.add_song') }}
            </button>
        </div>

        <!-- Add Song Sheet -->
        <Teleport to="body">
            <div v-if="showAddSheet" class="fixed inset-0 z-50 flex flex-col justify-end">
                <div class="absolute inset-0 bg-black/40" @click="closeAddSheet" />
                <div class="relative bg-white rounded-t-2xl px-4 pt-4 pb-8 max-h-[85vh] flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-semibold text-slate-900">{{ t('services.add_song') }}</h2>
                        <button @click="closeAddSheet" class="text-slate-400 hover:text-slate-600 text-lg leading-none">✕</button>
                    </div>

                    <!-- Search input -->
                    <input
                        v-model="search"
                        type="search"
                        autofocus
                        :placeholder="t('services.song_search_placeholder')"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-3"
                        @input="selectedVersionId = null"
                    />

                    <!-- Results -->
                    <div class="overflow-y-auto flex-1 space-y-1 mb-4">
                        <button
                            v-for="sv in filtered"
                            :key="sv.id"
                            @click="selectExisting(sv)"
                            :class="[
                                'w-full text-left px-3 py-2.5 rounded-lg text-sm transition-colors',
                                selectedVersionId === sv.id
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-slate-50 hover:bg-slate-100 text-slate-800',
                            ]"
                        >
                            <span class="font-medium">{{ sv.song_name }}</span>
                            <span v-if="sv.artist" class="text-xs opacity-60 ml-1">— {{ sv.artist }}</span>
                            <span class="opacity-50 ml-1">· {{ sv.version_name }}</span>
                            <span v-if="sv.key" class="opacity-50 ml-1 text-xs">{{ sv.key }}</span>
                        </button>

                        <!-- New song panel -->
                        <div v-if="isNewSong" class="border border-dashed border-indigo-200 rounded-xl p-3 mt-2 space-y-3">
                            <p class="text-xs text-indigo-600 font-semibold">{{ t('services.new_song_hint') }}</p>

                            <!-- Artist -->
                            <div>
                                <label class="block text-[11px] font-medium text-slate-500 mb-1">{{ t('songs.form.artist') }}</label>
                                <input
                                    v-model="addForm.artist"
                                    type="text"
                                    :placeholder="t('songs.form.artist_placeholder')"
                                    maxlength="50"
                                    class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                            </div>

                            <!-- Version preset -->
                            <div>
                                <label class="block text-[11px] font-medium text-slate-500 mb-1.5">{{ t('songs.form.version') }}</label>
                                <div class="flex gap-1.5 flex-wrap">
                                    <button
                                        v-for="v in ['Original', 'Live', 'Acoustic']"
                                        :key="v"
                                        type="button"
                                        @click="addForm.version_name = v; selectedVersionId = null"
                                        :class="[
                                            'px-2.5 py-1 text-xs font-medium rounded-md border transition-colors',
                                            addForm.version_name === v && !selectedVersionId
                                                ? 'bg-indigo-600 text-white border-indigo-600'
                                                : 'border-slate-300 text-slate-600',
                                        ]"
                                    >{{ v }}</button>
                                </div>
                            </div>

                            <!-- Toggle more details -->
                            <button
                                type="button"
                                @click="showSongDetails = !showSongDetails"
                                class="flex items-center gap-1 text-xs font-medium text-indigo-500"
                            >
                                <svg class="w-3.5 h-3.5 transition-transform" :class="showSongDetails ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                                {{ showSongDetails ? t('songs.form.less_details') : t('songs.form.more_details') }}
                            </button>

                            <!-- Expandable metadata -->
                            <div v-if="showSongDetails" class="space-y-2 pt-1">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-500 mb-1">{{ t('songs.form.key') }}</label>
                                        <input
                                            v-model="addForm.key"
                                            type="text"
                                            placeholder="C, Am, Bb…"
                                            maxlength="10"
                                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-medium text-slate-500 mb-1">{{ t('songs.form.bpm') }}</label>
                                        <input
                                            v-model="addForm.bpm"
                                            type="number"
                                            min="20" max="300"
                                            placeholder="120"
                                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-medium text-slate-500 mb-1">{{ t('songs.form.youtube_url') }}</label>
                                    <input
                                        v-model="addForm.youtube_url"
                                        type="url"
                                        :placeholder="t('songs.form.youtube_url')"
                                        class="w-full px-3 py-2 text-sm rounded-lg border border-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <button
                        @click="submitAdd"
                        :disabled="addForm.processing || (!selectedVersionId && !search.trim())"
                        class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white font-semibold rounded-xl transition-colors"
                    >
                        {{ addForm.processing ? t('services.adding') : t('services.add_song') }}
                    </button>
                </div>
            </div>
        </Teleport>

        <!-- Share sheet -->
        <Teleport to="body">
            <div v-if="showShareSheet" class="fixed inset-0 z-50 flex flex-col justify-end">
                <div class="absolute inset-0 bg-black/40" @click="showShareSheet = false" />
                <div class="relative bg-white rounded-t-2xl px-4 pt-4 pb-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-semibold text-slate-900">{{ t('services.share_title') }}</h2>
                        <button @click="showShareSheet = false" class="text-slate-400 text-lg leading-none">✕</button>
                    </div>

                    <div class="flex gap-2 mb-3">
                        <input
                            :value="shareData?.url"
                            readonly
                            class="flex-1 px-3 py-2.5 text-sm rounded-lg border border-slate-200 bg-slate-50 text-slate-600 truncate min-w-0"
                        />
                        <button
                            @click="copyLink"
                            :class="[
                                'shrink-0 px-3 py-2.5 text-sm font-medium rounded-lg border transition-colors',
                                copied
                                    ? 'bg-green-50 border-green-200 text-green-600'
                                    : 'border-slate-300 text-slate-600 hover:bg-slate-50',
                            ]"
                        >
                            {{ copied ? t('services.share_copied') : t('services.share_copy') }}
                        </button>
                    </div>

                    <a
                        :href="shareData?.whatsapp_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="w-full flex items-center justify-center gap-2 py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-xl transition-colors"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        {{ t('services.share_whatsapp') }}
                    </a>
                </div>
            </div>
        </Teleport>

        <!-- Duplicate sheet -->
        <Teleport to="body">
            <div v-if="showDuplicateSheet" class="fixed inset-0 z-50 flex flex-col justify-end">
                <div class="absolute inset-0 bg-black/40" @click="showDuplicateSheet = false" />
                <div class="relative bg-white rounded-t-2xl px-4 pt-4 pb-8">
                    <h2 class="font-semibold text-slate-900 mb-4">{{ t('services.duplicate_title') }}</h2>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">{{ t('services.duplicate_date_label') }}</label>
                    <input
                        v-model="dupForm.date"
                        type="date"
                        required
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-4"
                    />
                    <button
                        @click="submitDuplicate"
                        :disabled="dupForm.processing || !dupForm.date"
                        class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white font-semibold rounded-xl transition-colors"
                    >
                        {{ t('services.duplicate') }}
                    </button>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
