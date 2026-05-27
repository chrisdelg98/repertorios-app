<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();

const props = defineProps({
    songs: Array,
    can_write: Boolean,
});

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

            <!-- Empty state -->
            <div v-if="!songs.length" class="text-center py-16 text-slate-400">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                </svg>
                <p class="text-sm">{{ t('songs.empty') }}</p>
            </div>

            <!-- Songs list -->
            <div v-else class="space-y-2 lg:space-y-0 lg:grid lg:grid-cols-2 xl:grid-cols-3 lg:gap-3">
                <div
                    v-for="song in songs"
                    :key="song.id"
                    class="bg-white rounded-xl border border-slate-200 px-4 py-3.5"
                >
                    <div class="flex items-start gap-3">
                        <!-- Song info -->
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-slate-900 text-sm leading-tight">{{ song.name }}</p>
                            <p v-if="song.artist" class="text-xs text-slate-500 mt-0.5">{{ song.artist }}</p>
                            <div class="flex flex-wrap gap-1.5 mt-2">
                                <span
                                    v-for="v in song.versions"
                                    :key="v.id"
                                    class="inline-flex items-center gap-1 text-xs text-slate-600 bg-slate-100 rounded-md px-2 py-0.5 font-medium"
                                >
                                    {{ v.name }}
                                    <span v-if="v.key" class="text-indigo-500">· {{ v.key }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div v-if="can_write" class="flex items-center gap-1 shrink-0 mt-0.5">
                            <!-- Edit -->
                            <button
                                @click="openEdit(song)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                                :aria-label="t('songs.edit')"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                                </svg>
                            </button>
                            <!-- Delete -->
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
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    autofocus
                                    :placeholder="t('songs.form.name')"
                                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <p v-if="form.errors.name" class="text-xs text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-600">{{ t('songs.form.artist') }}</label>
                                <input
                                    v-model="form.artist"
                                    type="text"
                                    :placeholder="t('songs.form.artist_placeholder')"
                                    maxlength="50"
                                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
                                <input
                                    v-model="editForm.name"
                                    type="text"
                                    required
                                    autofocus
                                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                />
                                <p v-if="editForm.errors.name" class="text-xs text-red-600">{{ editForm.errors.name }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-xs font-medium text-slate-600">{{ t('songs.form.artist') }}</label>
                                <input
                                    v-model="editForm.artist"
                                    type="text"
                                    :placeholder="t('songs.form.artist_placeholder')"
                                    maxlength="50"
                                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
                                        <span v-if="v.bpm" class="text-xs text-slate-400">{{ v.bpm }} bpm</span>
                                    </div>
                                    <svg
                                        class="w-4 h-4 text-slate-400 transition-transform shrink-0"
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
