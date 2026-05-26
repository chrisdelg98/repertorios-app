<script setup>
import { ref, computed } from 'vue';
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
const newVersionName = ref('Original');

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

const addForm = useForm({ song_version_id: null, song_name: '', version_name: 'Original', notes: '' });

function selectExisting(sv) {
    selectedVersionId.value = sv.id;
    search.value = sv.display;
}

function submitAdd() {
    if (selectedVersionId.value) {
        addForm.song_version_id = selectedVersionId.value;
        addForm.song_name = '';
    } else {
        addForm.song_version_id = null;
        addForm.song_name = search.value.trim();
        addForm.version_name = newVersionName.value;
    }
    addForm.post('/services/' + props.service.id + '/songs', {
        onSuccess: () => { showAddSheet.value = false; search.value = ''; selectedVersionId.value = null; addForm.reset(); },
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
    const date = new Date(d + 'T00:00:00');
    return date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}
</script>

<template>
    <Head :title="service.type === 'other' ? t('services.type_other') : service.type" />

    <AppLayout>
        <div class="px-4 py-5 max-w-lg mx-auto">
            <!-- Service header -->
            <div class="mb-5">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <h1 class="text-lg font-semibold text-slate-900">{{ service.type === 'other' ? t('services.type_other') : service.type }}</h1>
                        <p class="text-sm text-slate-500 mt-0.5">
                            {{ formatDate(service.date) }}
                            <span v-if="service.time"> · {{ service.time.slice(0, 5) }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button
                            @click="openShare"
                            :disabled="sharing"
                            class="text-xs font-medium text-indigo-600 py-1.5 px-2.5 rounded-lg border border-indigo-200 hover:bg-indigo-50 disabled:opacity-50"
                        >
                            {{ sharing ? t('services.share_generating') : t('services.share') }}
                        </button>
                        <template v-if="can_write">
                            <button
                                @click="showDuplicateSheet = true"
                                class="text-xs font-medium text-slate-600 py-1.5 px-2.5 rounded-lg border border-slate-200 hover:bg-slate-50"
                            >
                                {{ t('services.duplicate') }}
                            </button>
                            <a
                                :href="'/services/' + service.id + '/edit'"
                                class="text-xs font-medium text-slate-600 py-1.5 px-2.5 rounded-lg border border-slate-200 hover:bg-slate-50"
                            >
                                ✏️
                            </a>
                            <button
                                @click="deleteService"
                                class="text-xs font-medium text-red-500 py-1.5 px-2.5 rounded-lg border border-red-200 hover:bg-red-50"
                            >
                                🗑
                            </button>
                        </template>
                    </div>
                </div>
                <p v-if="service.notes" class="mt-2 text-sm text-slate-500 bg-slate-50 rounded-lg px-3 py-2">
                    {{ service.notes }}
                </p>
            </div>

            <!-- Songs list -->
            <div class="space-y-1.5 mb-4">
                <div
                    v-if="!service.service_songs.length"
                    class="text-center py-8 text-slate-400 text-sm"
                >
                    🎵 {{ t('services.no_songs') }}
                </div>

                <div
                    v-for="(ss, i) in service.service_songs"
                    :key="ss.id"
                    class="flex items-center gap-3 bg-white rounded-xl px-4 py-3 border border-slate-200"
                >
                    <span class="text-xs font-bold text-slate-300 w-5 text-center shrink-0">{{ i + 1 }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-slate-900 truncate">{{ ss.song_version.song.name }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ ss.song_version.name }}
                            <span v-if="ss.song_version.key"> · {{ ss.song_version.key }}</span>
                        </p>
                    </div>
                    <button
                        v-if="can_write"
                        @click="removeSong(ss.id)"
                        class="shrink-0 w-7 h-7 flex items-center justify-center rounded-full hover:bg-red-50 text-slate-300 hover:text-red-400 transition-colors"
                        :aria-label="t('services.delete')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Add song button -->
            <button
                v-if="can_write"
                @click="showAddSheet = true"
                class="w-full py-3 border-2 border-dashed border-indigo-300 text-indigo-600 text-sm font-medium rounded-xl hover:bg-indigo-50 transition-colors"
            >
                + {{ t('services.add_song') }}
            </button>
        </div>

        <!-- Add Song Sheet -->
        <Teleport to="body">
            <div v-if="showAddSheet" class="fixed inset-0 z-50 flex flex-col justify-end">
                <div class="absolute inset-0 bg-black/40" @click="showAddSheet = false" />
                <div class="relative bg-white rounded-t-2xl px-4 pt-4 pb-8 max-h-[80vh] flex flex-col">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-semibold text-slate-900">{{ t('services.add_song') }}</h2>
                        <button @click="showAddSheet = false" class="text-slate-400 hover:text-slate-600">✕</button>
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
                            <span class="opacity-60 ml-1">· {{ sv.version_name }}</span>
                            <span v-if="sv.key" class="opacity-50 ml-1 text-xs">{{ sv.key }}</span>
                        </button>

                        <!-- New song option -->
                        <div v-if="isNewSong" class="border border-dashed border-indigo-200 rounded-lg p-3 mt-2">
                            <p class="text-xs text-indigo-600 font-medium mb-2">{{ t('services.new_song_hint') }}</p>
                            <p class="text-sm font-medium text-slate-900 mb-2">"{{ search }}"</p>
                            <div class="flex gap-2 flex-wrap">
                                <button
                                    v-for="v in ['Original', 'Live', 'Acoustic']"
                                    :key="v"
                                    type="button"
                                    @click="newVersionName = v; selectedVersionId = null"
                                    :class="[
                                        'px-2.5 py-1 text-xs font-medium rounded-md border',
                                        newVersionName === v && !selectedVersionId
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : 'border-slate-300 text-slate-600',
                                    ]"
                                >{{ v }}</button>
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
