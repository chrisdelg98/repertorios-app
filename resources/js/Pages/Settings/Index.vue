<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();

const sections = [
    {
        href: '/settings/schedule-templates',
        icon: 'calendar',
        title: () => t('settings.templates.title'),
        subtitle: () => t('settings.templates.subtitle'),
    },
    {
        href: '/settings/profile',
        icon: 'user',
        title: () => t('settings.profile.title'),
        subtitle: () => t('settings.profile.subtitle'),
    },
    {
        href: '/settings/band',
        icon: 'band',
        title: () => t('settings.band.title'),
        subtitle: () => t('settings.band.subtitle'),
    },
    {
        href: '/settings/members',
        icon: 'members',
        title: () => t('settings.members.title'),
        subtitle: () => t('settings.members.subtitle'),
    },
];
</script>

<template>
    <Head :title="t('settings.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-2xl mx-auto">
            <h1 class="text-lg font-semibold text-slate-900 mb-5">{{ t('settings.title') }}</h1>

            <div class="space-y-2">
                <Link
                    v-for="s in sections"
                    :key="s.href"
                    :href="s.href"
                    class="flex items-center gap-4 bg-white rounded-xl px-4 py-3.5 border border-slate-200 hover:bg-slate-50 active:bg-slate-100 transition-colors"
                >
                    <!-- Icon -->
                    <div class="w-9 h-9 bg-indigo-50 rounded-lg flex items-center justify-center shrink-0">
                        <svg v-if="s.icon === 'user'" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <svg v-else-if="s.icon === 'band'" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                        </svg>
                        <svg v-else-if="s.icon === 'members'" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg v-else class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 text-sm">{{ s.title() }}</p>
                        <p class="text-xs text-slate-400 mt-0.5 truncate">{{ s.subtitle() }}</p>
                    </div>

                    <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
