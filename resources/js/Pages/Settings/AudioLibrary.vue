<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    tracks: { type: Array, default: () => [] },
    usage: { type: Object, required: true },
});

const { t, locale } = useI18n();

const confirmingId = ref(null);
const deleting = ref(false);

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

    router.delete(`/settings/audio/${track.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            confirmingId.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('settings.audio.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-2xl mx-auto">
            <Link href="/settings" class="inline-flex items-center gap-1.5 text-xs text-slate-600 hover:text-slate-900 mb-4 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ t('settings.title') }}
            </Link>

            <h1 class="text-lg font-semibold text-slate-900">{{ t('settings.audio.title') }}</h1>
            <p class="text-sm text-slate-600 mt-1 leading-relaxed">{{ t('settings.audio.subtitle') }}</p>

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
                    {{ t('settings.audio.full') }}
                </p>
            </div>

            <!-- Tracks, heaviest first: freeing space starts at the top -->
            <div v-if="tracks.length" class="mt-4 space-y-2">
                <p class="text-2xs font-semibold text-slate-600 uppercase tracking-wide px-1">
                    {{ t('settings.audio.count', tracks.length, { count: tracks.length }) }}
                </p>

                <div
                    v-for="track in tracks"
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
                            :aria-label="t('settings.audio.remove')"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="confirmingId === track.id" class="flex items-center gap-2 mt-3 pt-3 border-t border-slate-100">
                        <p class="flex-1 text-xs font-medium text-slate-700 leading-snug">
                            {{ t('settings.audio.remove_confirm') }}
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
                        >{{ t('settings.audio.remove') }}</button>
                    </div>
                </div>
            </div>

            <div v-else class="mt-4 text-center py-12 bg-white rounded-xl border border-slate-200">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                </svg>
                <p class="text-sm text-slate-600">{{ t('settings.audio.empty') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
