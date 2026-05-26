<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    service: Object,
    expired: Boolean,
});

const serviceTitle = computed(() => {
    if (!props.service) return '';
    return props.service.type === 'other' ? t('services.type_other') : props.service.type;
});

function formatDate(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}
</script>

<template>
    <Head :title="serviceTitle || t('public.expired_title')" />

    <div class="min-h-screen bg-slate-50 flex flex-col">

        <!-- Expired state -->
        <div v-if="expired" class="flex-1 flex flex-col items-center justify-center px-6 text-center">
            <svg class="w-12 h-12 text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <h1 class="text-lg font-semibold text-slate-700 mb-2">{{ t('public.expired_title') }}</h1>
            <p class="text-sm text-slate-400">{{ t('public.expired_body') }}</p>
        </div>

        <!-- Service view -->
        <template v-else-if="service">
            <!-- Header -->
            <div class="bg-indigo-600 px-5 pt-10 pb-6 text-white">
                <h1 class="text-xl font-bold leading-tight">{{ serviceTitle }}</h1>
                <p class="text-sm text-indigo-200 mt-1">
                    {{ formatDate(service.date) }}
                    <span v-if="service.time"> · {{ service.time.slice(0, 5) }}</span>
                </p>
            </div>

            <!-- Notes -->
            <div v-if="service.notes" class="bg-white border-b border-slate-100 px-5 py-3">
                <p class="text-sm text-slate-600">{{ service.notes }}</p>
            </div>

            <!-- Songs -->
            <div class="flex-1 px-4 py-5">
                <div v-if="!service.songs.length" class="text-center py-12 text-sm text-slate-400">
                    {{ t('public.no_songs') }}
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="(song, i) in service.songs"
                        :key="i"
                        class="flex items-center gap-3 bg-white rounded-xl px-4 py-3.5 border border-slate-200"
                    >
                        <span class="text-xs font-bold text-slate-300 w-5 text-center shrink-0">{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ song.name }}</p>
                            <p class="text-xs text-slate-400 mt-0.5">
                                {{ song.version }}
                                <span v-if="song.key"> · {{ song.key }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center py-6 text-xs text-slate-300">
                {{ t('public.powered_by') }}
            </div>
        </template>
    </div>
</template>
