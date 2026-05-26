<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t, d } = useI18n();

const props = defineProps({
    services: Object,
});

function typeLabel(type) {
    return type === 'other' ? t('services.type_other') : type;
}

function formatDate(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
}

function deleteService(id) {
    if (confirm(t('services.delete_confirm'))) {
        router.delete('/services/' + id);
    }
}
</script>

<template>
    <Head :title="t('services.title')" />

    <AppLayout>
        <div class="px-4 py-5">
            <!-- Header -->
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-lg font-semibold text-slate-900">{{ t('services.title') }}</h1>
                <Link
                    href="/services/create"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('services.create') }}
                </Link>
            </div>

            <!-- Empty state -->
            <div v-if="!services.data.length" class="text-center py-16 text-slate-400">
                <p class="text-3xl mb-2">📅</p>
                <p class="text-sm">{{ t('services.empty') }}</p>
            </div>

            <!-- Services list -->
            <div v-else class="space-y-2">
                <Link
                    v-for="service in services.data"
                    :key="service.id"
                    :href="'/services/' + service.id"
                    class="flex items-center justify-between bg-white rounded-xl px-4 py-3.5 border border-slate-200 active:bg-slate-50 transition-colors"
                >
                    <div class="min-w-0">
                        <p class="font-medium text-slate-900 text-sm">{{ typeLabel(service.type) }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ formatDate(service.date) }}
                            <span v-if="service.time"> · {{ service.time.slice(0, 5) }}</span>
                        </p>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="text-xs text-slate-400">
                            {{ service.service_songs_count }}
                            <span class="sr-only">{{ t('nav.songs') }}</span>
                            🎵
                        </span>
                        <svg class="w-4 h-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </Link>
            </div>

            <!-- Pagination -->
            <div v-if="services.last_page > 1" class="flex justify-center gap-2 mt-6">
                <Link
                    v-for="link in services.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    :class="[
                        'px-3 py-1.5 text-xs rounded-lg border',
                        link.active
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'border-slate-200 text-slate-600',
                        !link.url && 'opacity-40 pointer-events-none',
                    ]"
                />
            </div>
        </div>
    </AppLayout>
</template>
