<script setup>
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAudioUpload } from '@/Composables/useAudioUpload';

const props = defineProps({
    /** The song version the track belongs to. */
    versionId: { type: [Number, String], required: true },
    /** { name, size, mime, url } or null. */
    audio: { type: Object, default: null },
});

const emit = defineEmits(['update:audio']);

const { t } = useI18n();
const { uploading, progress, error, upload, remove } = useAudioUpload();

const fileInput = ref(null);
const confirming = ref(false);

// Collapsed by default. Attaching a track is an occasional act, and the form is
// mostly used to fix a key or a note — an upload box sitting open every time
// makes the common edit feel heavier than it is.
const expanded = ref(false);

const sizeLabel = computed(() => {
    const bytes = props.audio?.size;
    if (!bytes) return '';

    return bytes >= 1024 * 1024
        ? `${(bytes / (1024 * 1024)).toFixed(1)} MB`
        : `${Math.round(bytes / 1024)} KB`;
});

const message = computed(() => {
    if (!error.value) return '';

    // Anything not named here is a network or storage failure; the console has
    // the detail, the person just needs to know it did not land.
    return {
        unsupported_type: t('songs.audio.error_type'),
        quota_exceeded: t('songs.audio.error_quota'),
        too_large: t('songs.audio.error_size'),
        storage_not_configured: t('songs.audio.error_not_configured'),
    }[error.value] ?? t('songs.audio.error_failed');
});

async function pick(event) {
    const file = event.target.files?.[0];
    if (!file) return;

    const result = await upload(props.versionId, file);

    if (result) emit('update:audio', result);

    // Let the same file be chosen again after a failure.
    if (fileInput.value) fileInput.value.value = '';
}

async function detach() {
    if (await remove(props.versionId)) {
        emit('update:audio', null);
    }
    confirming.value = false;
}
</script>

<template>
    <div class="rounded-lg border border-slate-200 overflow-hidden">
        <!-- Header: says what is in there without opening it -->
        <button
            type="button"
            @click="expanded = !expanded"
            class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left hover:bg-slate-50 transition-colors"
        >
            <span
                class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                :class="audio ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-500'"
            >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                </svg>
            </span>

            <span class="flex-1 min-w-0">
                <span class="block text-xs font-medium text-slate-700">
                    {{ t('songs.audio.label') }}
                    <span class="text-slate-500 font-normal">· {{ t('songs.audio.hint') }}</span>
                </span>
                <span
                    class="block text-2xs font-medium truncate mt-0.5"
                    :class="audio ? 'text-emerald-600' : 'text-slate-500'"
                >{{ audio ? audio.name : t('songs.audio.none') }}</span>
            </span>

            <svg
                class="w-4 h-4 text-slate-500 shrink-0 transition-transform"
                :class="expanded ? 'rotate-180' : ''"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <div v-if="expanded" class="px-3 pb-3 pt-1 border-t border-slate-100 space-y-2">
            <!-- Uploading -->
            <div v-if="uploading" class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2.5">
                <div class="flex items-center justify-between gap-2 mb-1.5">
                    <p class="text-xs font-semibold text-indigo-700">{{ t('songs.audio.uploading') }}</p>
                    <p class="text-xs font-bold text-indigo-700 tabular-nums">{{ progress }}%</p>
                </div>
                <div class="h-1.5 rounded-full bg-indigo-100 overflow-hidden">
                    <div class="h-full bg-indigo-600 transition-all duration-200" :style="{ width: progress + '%' }" />
                </div>
            </div>

            <!-- Attached -->
            <template v-else-if="audio">
                <div class="flex items-center gap-2">
                    <p class="flex-1 min-w-0 text-xs font-medium text-slate-600">{{ sizeLabel }}</p>
                    <button
                        type="button"
                        @click="confirming = true"
                        class="shrink-0 text-xs font-semibold text-slate-600 hover:text-red-600 transition-colors"
                    >{{ t('songs.audio.remove') }}</button>
                </div>

                <!-- Listen before trusting it: a wrong file is easy to attach -->
                <audio v-if="audio.url" :src="audio.url" controls preload="none" class="w-full h-9" />

                <div v-if="confirming" class="flex items-center gap-2 pt-2 border-t border-slate-100">
                    <p class="text-xs font-medium text-slate-700 flex-1">{{ t('songs.audio.remove_confirm') }}</p>
                    <button
                        type="button"
                        @click="confirming = false"
                        class="px-2.5 py-1 text-2xs font-semibold text-slate-600 rounded-md border border-slate-300"
                    >{{ t('services.form.cancel') }}</button>
                    <button
                        type="button"
                        @click="detach"
                        class="px-2.5 py-1 text-2xs font-semibold text-white bg-red-600 rounded-md"
                    >{{ t('songs.audio.remove') }}</button>
                </div>
            </template>

            <!-- Empty -->
            <template v-else>
                <button
                    type="button"
                    @click="fileInput?.click()"
                    class="w-full flex items-center justify-center gap-2 px-3 py-3 text-sm font-medium text-indigo-600 rounded-lg border-2 border-dashed border-indigo-300 hover:bg-indigo-50 transition-colors"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
                    </svg>
                    {{ t('songs.audio.choose') }}
                </button>

                <p class="text-2xs font-medium text-slate-500">{{ t('songs.audio.limits') }}</p>
            </template>

            <input
                ref="fileInput"
                type="file"
                accept="audio/mpeg,.mp3"
                class="hidden"
                @change="pick"
            />

            <p v-if="message" class="text-xs font-medium text-red-600">{{ message }}</p>
        </div>
    </div>
</template>
