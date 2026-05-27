<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t, locale } = useI18n();
const page = usePage();

const props = defineProps({
    stats: Object,
    upcoming_services: Array,
});

const auth = computed(() => page.props.auth);

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return t('dashboard.greeting_morning');
    if (h < 18) return t('dashboard.greeting_afternoon');
    return t('dashboard.greeting_evening');
});

const displayName = computed(() => {
    const name = auth.value.user?.name || auth.value.band?.name || '';
    return name.split(' ')[0];
});

const nextService = computed(() => props.upcoming_services?.[0] ?? null);
const otherUpcoming = computed(() => (props.upcoming_services ?? []).slice(1));

function formatDate(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(locale.value === 'es' ? 'es' : 'en', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    });
}

function formatShortDate(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(locale.value === 'es' ? 'es' : 'en', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

function daysFromNow(dateStr) {
    const target = new Date(dateStr + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const diff = Math.round((target - today) / (1000 * 60 * 60 * 24));
    if (diff === 0) return t('dashboard.today');
    if (diff === 1) return t('dashboard.tomorrow');
    return t('dashboard.in_days', { count: diff });
}

function typeLabel(type) {
    return type === 'other' ? t('services.type_other') : type;
}
</script>

<template>
    <Head :title="t('nav.home')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-6 lg:py-10 space-y-5 lg:space-y-6 lg:max-w-3xl lg:mx-auto">

            <!-- Greeting -->
            <div>
                <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">{{ greeting }}</p>
                <h1 class="text-xl font-bold text-slate-900 mt-1">{{ displayName }}</h1>
            </div>

            <!-- Next service hero card -->
            <Link
                v-if="nextService"
                :href="`/services/${nextService.id}`"
                class="block bg-gradient-to-br from-indigo-600 to-violet-600 rounded-2xl p-5 text-white shadow-md shadow-indigo-200"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-indigo-200 uppercase tracking-wide">
                            {{ t('dashboard.next_service') }}
                        </p>
                        <h2 class="text-lg font-bold mt-1 capitalize truncate">{{ typeLabel(nextService.type) }}</h2>
                        <p class="text-sm text-indigo-100 mt-0.5">
                            {{ formatDate(nextService.date) }}
                            <span v-if="nextService.time"> · {{ nextService.time.slice(0, 5) }}</span>
                        </p>
                    </div>
                    <span class="shrink-0 text-[10px] font-semibold bg-white/15 text-white rounded-full px-2.5 py-1 backdrop-blur-sm">
                        {{ daysFromNow(nextService.date) }}
                    </span>
                </div>

                <div class="flex items-center gap-4 mt-4 pt-4 border-t border-white/15 text-xs text-indigo-100">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                        </svg>
                        {{ t('dashboard.songs_count', { count: nextService.song_count }) }}
                    </span>
                </div>
            </Link>

            <!-- No services state -->
            <Link
                v-else
                href="/services/create"
                class="block bg-white rounded-2xl p-5 border border-dashed border-slate-300 text-center"
            >
                <div class="w-10 h-10 mx-auto bg-indigo-50 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-700">{{ t('dashboard.no_upcoming_title') }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ t('dashboard.no_upcoming_body') }}</p>
            </Link>

            <!-- More upcoming -->
            <div v-if="otherUpcoming.length">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 px-1">
                    {{ t('dashboard.upcoming') }}
                </p>
                <div class="space-y-2">
                    <Link
                        v-for="s in otherUpcoming"
                        :key="s.id"
                        :href="`/services/${s.id}`"
                        class="flex items-center justify-between bg-white border border-slate-200 rounded-xl px-4 py-3"
                    >
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-900 capitalize truncate">{{ typeLabel(s.type) }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ formatShortDate(s.date) }}<span v-if="s.time"> · {{ s.time.slice(0, 5) }}</span></p>
                        </div>
                        <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-3">
                <Link
                    href="/services"
                    class="bg-white rounded-xl p-4 border border-slate-200 flex items-center gap-3"
                >
                    <div class="w-10 h-10 bg-indigo-50 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xl font-bold text-slate-900 leading-none">{{ stats.services_total }}</p>
                        <p class="text-[11px] text-slate-500 mt-1 leading-none">{{ t('nav.services') }}</p>
                    </div>
                </Link>

                <Link
                    href="/songs"
                    class="bg-white rounded-xl p-4 border border-slate-200 flex items-center gap-3"
                >
                    <div class="w-10 h-10 bg-violet-50 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-violet-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xl font-bold text-slate-900 leading-none">{{ stats.songs }}</p>
                        <p class="text-[11px] text-slate-500 mt-1 leading-none">{{ t('nav.songs') }}</p>
                    </div>
                </Link>
            </div>

            <!-- Quick actions -->
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2 px-1">
                    {{ t('dashboard.quick_actions') }}
                </p>
                <div class="grid grid-cols-2 gap-3">
                    <Link
                        href="/services/create"
                        class="bg-white rounded-xl p-4 border border-slate-200 hover:border-indigo-300 transition-colors"
                    >
                        <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center mb-2">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-slate-900">{{ t('dashboard.new_service') }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">{{ t('dashboard.new_service_hint') }}</p>
                    </Link>

                    <Link
                        href="/songs"
                        class="bg-white rounded-xl p-4 border border-slate-200 hover:border-violet-300 transition-colors"
                    >
                        <div class="w-8 h-8 bg-violet-600 rounded-lg flex items-center justify-center mb-2">
                            <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-slate-900">{{ t('dashboard.browse_library') }}</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">{{ t('dashboard.browse_library_hint') }}</p>
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
