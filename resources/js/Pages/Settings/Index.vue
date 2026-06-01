<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useInstall } from '@/composables/useInstall';

const { t } = useI18n();
const page = usePage();
const { isInstalled, canRequestInstall, showManualInstallHelp, promptInstall } = useInstall();

const donateUrl = computed(() => page.props.donate?.url || null);
const isAdmin   = computed(() => page.props.auth?.access === 'admin');
const isCreator = computed(() => !!page.props.auth?.is_creator);

function openDonate() {
    if (!donateUrl.value) return;
    window.open(donateUrl.value, '_blank', 'noopener,noreferrer');
}

const sections = computed(() => {
    const list = [];
    // Templates: admin (creator + delegated)
    if (isAdmin.value) list.push({
        href: '/settings/schedule-templates',
        icon: 'calendar',
        title: () => t('settings.templates.title'),
        subtitle: () => t('settings.templates.subtitle'),
    });
    // Profile: any registered user
    list.push({
        href: '/settings/profile',
        icon: 'user',
        title: () => t('settings.profile.title'),
        subtitle: () => t('settings.profile.subtitle'),
    });
    // Band + Administrators: creator only
    if (isCreator.value) list.push({
        href: '/settings/band',
        icon: 'band',
        title: () => t('settings.band.title'),
        subtitle: () => t('settings.band.subtitle'),
    });
    if (isCreator.value) list.push({
        href: '/settings/members',
        icon: 'members',
        title: () => t('settings.members.title'),
        subtitle: () => t('settings.members.subtitle'),
    });
    return list;
});
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
                        <p class="text-xs text-slate-500 mt-0.5 truncate">{{ s.subtitle() }}</p>
                    </div>

                    <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>

                <!-- Install app tile (hidden only when already installed) -->
                <button
                    v-if="!isInstalled"
                    @click="promptInstall"
                    :disabled="!canRequestInstall"
                    class="w-full flex items-center gap-4 bg-gradient-to-r from-indigo-50 to-violet-50 rounded-xl px-4 py-3.5 border border-indigo-100 hover:from-indigo-100 hover:to-violet-100 active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed disabled:active:scale-100 transition"
                    :title="canRequestInstall ? '' : t('install.tile_not_available')"
                >
                    <div class="w-9 h-9 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-indigo-200">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0 text-left">
                        <p class="font-medium text-indigo-700 text-sm">{{ t('install.tile_title') }}</p>
                        <p class="text-xs text-indigo-400 mt-0.5 truncate">{{ t('install.tile_subtitle') }}</p>
                    </div>

                    <svg class="w-4 h-4 text-indigo-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>

                <div
                    v-if="!isInstalled && showManualInstallHelp"
                    class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-700"
                >
                    <p class="font-semibold">{{ t('install.ios_help_title') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-indigo-500">{{ t('install.ios_help_body') }}</p>
                </div>

                <!-- Already installed badge -->
                <div
                    v-if="isInstalled"
                    class="flex items-center gap-4 bg-green-50 rounded-xl px-4 py-3.5 border border-green-100"
                >
                    <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-green-700 text-sm">{{ t('install.installed_title') }}</p>
                        <p class="text-xs text-green-500 mt-0.5">{{ t('install.installed_subtitle') }}</p>
                    </div>
                </div>

                <!-- Donate tile -->
                <button
                    v-if="donateUrl"
                    @click="openDonate"
                    class="w-full flex items-center gap-4 bg-gradient-to-r from-pink-50 to-rose-50 rounded-xl px-4 py-3.5 border border-pink-100 hover:from-pink-100 hover:to-rose-100 active:scale-[0.99] transition"
                >
                    <div class="w-9 h-9 bg-gradient-to-br from-pink-500 to-rose-500 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-pink-200">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0 text-left">
                        <p class="font-medium text-rose-700 text-sm">{{ t('donate.tile_title') }}</p>
                        <p class="text-xs text-rose-400 mt-0.5 truncate">{{ t('donate.tile_subtitle') }}</p>
                    </div>

                    <svg class="w-4 h-4 text-rose-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>
        </div>
    </AppLayout>
</template>
