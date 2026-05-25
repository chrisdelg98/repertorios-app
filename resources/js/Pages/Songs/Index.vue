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

const showAddForm = ref(false);

const form = useForm({
    name: '',
    version_name: 'Original',
    key: '',
    bpm: '',
    notes: '',
    youtube_url: '',
});

const PRESET_VERSIONS = ['Original', 'Live', 'Acoustic'];

function submit() {
    form.post('/songs', {
        onSuccess: () => { showAddForm.value = false; form.reset(); form.version_name = 'Original'; },
    });
}

function deleteSong(id) {
    if (confirm(t('songs.delete_confirm'))) {
        router.delete('/songs/' + id);
    }
}
</script>

<template>
    <Head :title="t('songs.title')" />

    <AppLayout>
        <div class="px-4 py-5">
            <!-- Header -->
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-lg font-semibold text-slate-900">{{ t('songs.library') }}</h1>
                <button
                    v-if="can_write"
                    @click="showAddForm = !showAddForm"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('songs.create') }}
                </button>
            </div>

            <!-- Add form (inline) -->
            <div v-if="showAddForm && can_write" class="bg-white rounded-2xl border border-indigo-200 p-4 mb-4 space-y-3">
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
                    <label class="block text-xs font-medium text-slate-600">{{ t('songs.form.version') }}</label>
                    <div class="flex gap-2 flex-wrap">
                        <button
                            v-for="v in PRESET_VERSIONS"
                            :key="v"
                            type="button"
                            @click="form.version_name = v"
                            :class="[
                                'px-2.5 py-1 text-xs font-medium rounded-md border transition-colors',
                                form.version_name === v
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'border-slate-300 text-slate-600',
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

                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-slate-600">{{ t('songs.form.key') }}</label>
                        <input
                            v-model="form.key"
                            type="text"
                            placeholder="C, Am, Bb…"
                            maxlength="10"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                    <div class="space-y-1">
                        <label class="block text-xs font-medium text-slate-600">{{ t('songs.form.bpm') }}</label>
                        <input
                            v-model="form.bpm"
                            type="number"
                            min="20" max="300"
                            placeholder="120"
                            class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <input
                    v-model="form.youtube_url"
                    type="url"
                    :placeholder="t('songs.form.youtube_url')"
                    class="w-full px-3 py-2 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                />

                <div class="flex gap-2 pt-1">
                    <button
                        type="button"
                        @click="showAddForm = false; form.reset(); form.version_name = 'Original'"
                        class="flex-1 py-2 text-sm font-medium text-slate-600 rounded-lg border border-slate-300"
                    >
                        {{ t('songs.form.cancel') }}
                    </button>
                    <button
                        @click="submit"
                        :disabled="form.processing"
                        class="flex-1 py-2 bg-indigo-600 disabled:opacity-50 text-white text-sm font-semibold rounded-lg"
                    >
                        {{ form.processing ? t('songs.form.saving') : t('songs.form.save') }}
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="!songs.length" class="text-center py-16 text-slate-400">
                <p class="text-3xl mb-2">🎵</p>
                <p class="text-sm">{{ t('songs.empty') }}</p>
            </div>

            <!-- Songs list -->
            <div v-else class="space-y-2">
                <div
                    v-for="song in songs"
                    :key="song.id"
                    class="bg-white rounded-xl border border-slate-200 px-4 py-3"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900 text-sm">{{ song.name }}</p>
                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                <span
                                    v-for="v in song.versions"
                                    :key="v.id"
                                    class="inline-flex items-center gap-1 text-xs text-slate-500 bg-slate-100 rounded-md px-2 py-0.5"
                                >
                                    {{ v.name }}
                                    <span v-if="v.key" class="text-indigo-500 font-medium">{{ v.key }}</span>
                                </span>
                            </div>
                        </div>
                        <button
                            v-if="can_write"
                            @click="deleteSong(song.id)"
                            class="shrink-0 w-7 h-7 flex items-center justify-center rounded-full hover:bg-red-50 text-slate-300 hover:text-red-400 transition-colors"
                            :aria-label="t('songs.delete')"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
