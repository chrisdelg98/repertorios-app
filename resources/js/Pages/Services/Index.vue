<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useSwipe } from '@/Composables/useSwipe.js';

const { t } = useI18n();

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

// ── Swipe-to-delete ────────────────────────────────────────────
const swipedId = ref(null);

function makeSwipeHandlers(id) {
    const { onTouchStart, onTouchEnd } = useSwipe({
        onLeft:  () => { swipedId.value = id; },
        onRight: () => { if (swipedId.value === id) swipedId.value = null; },
    });
    return { onTouchStart, onTouchEnd };
}

function handleCardClick(id) {
    if (swipedId.value === id) {
        swipedId.value = null;
        return;
    }
    router.visit('/services/' + id);
}

function deleteService(id) {
    swipedId.value = null;
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
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-sm">{{ t('services.empty') }}</p>
            </div>

            <!-- Services list -->
            <div v-else class="space-y-2">
                <div
                    v-for="service in services.data"
                    :key="service.id"
                    class="flex items-stretch rounded-xl border border-slate-200 bg-white overflow-hidden"
                    v-bind="makeSwipeHandlers(service.id)"
                >
                    <!-- Card content (tappable) -->
                    <button
                        type="button"
                        class="flex-1 flex items-center justify-between px-4 py-3.5 text-left min-w-0 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-inset outline-none transition-colors"
                        :class="swipedId === service.id ? 'bg-slate-50' : 'hover:bg-slate-50 active:bg-slate-100'"
                        @click="handleCardClick(service.id)"
                        :aria-label="typeLabel(service.type) + ' — ' + formatDate(service.date)"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900 text-sm">{{ typeLabel(service.type) }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ formatDate(service.date) }}
                                <span v-if="service.time"> · {{ service.time.slice(0, 5) }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 ml-3">
                            <span class="text-xs text-slate-400">
                                {{ service.service_songs_count }}
                                <span class="sr-only">{{ t('nav.songs') }}</span>
                            </span>
                            <svg
                                class="w-4 h-4 transition-transform"
                                :class="swipedId === service.id ? 'rotate-180 text-red-400' : 'text-slate-300'"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </button>

                    <!-- Delete button — revealed on left swipe -->
                    <Transition
                        enter-active-class="transition-all duration-200"
                        enter-from-class="w-0 opacity-0"
                        enter-to-class="w-16 opacity-100"
                        leave-active-class="transition-all duration-150"
                        leave-from-class="w-16 opacity-100"
                        leave-to-class="w-0 opacity-0"
                    >
                        <button
                            v-if="swipedId === service.id"
                            type="button"
                            @click.stop="deleteService(service.id)"
                            class="w-16 shrink-0 bg-red-500 hover:bg-red-600 text-white flex flex-col items-center justify-center gap-1 transition-colors"
                            :aria-label="t('services.delete')"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <span class="text-[10px] font-semibold">{{ t('services.delete') }}</span>
                        </button>
                    </Transition>
                </div>
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
