<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import Logo from '@/Components/Logo.vue';

const { t } = useI18n();
const page = usePage();

const donateUrl = computed(() => page.props.donate?.url || null);

function openDonate() {
    if (!donateUrl.value) return;
    window.open(donateUrl.value, '_blank', 'noopener,noreferrer');
}

defineProps({
    laravelVersion: String,
    phpVersion: String,
    appName: String,
});

const features = [
    {
        title: 'landing.features.plan_title',
        body:  'landing.features.plan_body',
        icon:  'calendar',
    },
    {
        title: 'landing.features.library_title',
        body:  'landing.features.library_body',
        icon:  'music',
    },
    {
        title: 'landing.features.share_title',
        body:  'landing.features.share_body',
        icon:  'share',
    },
];

const steps = [
    { title: 'landing.steps.one_title',   body: 'landing.steps.one_body' },
    { title: 'landing.steps.two_title',   body: 'landing.steps.two_body' },
    { title: 'landing.steps.three_title', body: 'landing.steps.three_body' },
];
</script>

<template>
    <Head :title="t('landing.meta_title')" />

    <div class="min-h-screen bg-white text-slate-900">
        <!-- ── Header ─────────────────────────────────────────────────── -->
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur border-b border-slate-100">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Logo :size="32" />
                    <span class="font-semibold text-sm">{{ appName }}</span>
                </div>

                <div class="flex items-center gap-3">
                    <LanguageSwitcher />
                    <Link
                        href="/login"
                        class="text-xs font-semibold text-slate-700 hover:text-indigo-600 transition-colors"
                    >
                        {{ t('landing.nav.sign_in') }}
                    </Link>
                </div>
            </div>
        </header>

        <!-- ── Hero ───────────────────────────────────────────────────── -->
        <section class="relative overflow-hidden">
            <!-- Decorative blobs -->
            <div class="absolute inset-0 -z-10 pointer-events-none">
                <div class="absolute -top-32 -left-20 w-72 h-72 bg-indigo-200/40 rounded-full blur-3xl"></div>
                <div class="absolute -top-10 right-0 w-80 h-80 bg-violet-200/40 rounded-full blur-3xl"></div>
            </div>

            <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 pb-16 sm:pt-20 sm:pb-24 text-center">
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-full px-3 py-1 mb-5 uppercase tracking-wide">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    {{ t('landing.hero.badge') }}
                </span>

                <h1 class="text-3xl sm:text-5xl font-bold tracking-tight leading-[1.1] max-w-3xl mx-auto">
                    {{ t('landing.hero.title_pre') }}
                    <span class="bg-gradient-to-r from-indigo-600 to-violet-600 bg-clip-text text-transparent">
                        {{ t('landing.hero.title_accent') }}
                    </span>
                </h1>

                <p class="mt-5 text-base sm:text-lg text-slate-500 max-w-2xl mx-auto">
                    {{ t('landing.hero.subtitle') }}
                </p>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center gap-3">
                    <Link
                        href="/login"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-br from-indigo-600 to-violet-600 text-white text-sm font-semibold rounded-xl shadow-lg shadow-indigo-200 hover:shadow-xl active:scale-[0.98] transition"
                    >
                        {{ t('landing.hero.cta_primary') }}
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </Link>

                    <a
                        v-if="donateUrl"
                        href="#donate"
                        class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 bg-white border border-slate-200 text-slate-700 text-sm font-semibold rounded-xl hover:border-indigo-300 hover:text-indigo-600 transition"
                    >
                        <svg class="w-4 h-4 text-pink-500" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                        {{ t('landing.hero.cta_donate') }}
                    </a>
                </div>

                <!-- Mock device -->
                <div class="mt-14 sm:mt-20 relative max-w-xs mx-auto">
                    <div class="absolute inset-x-6 -bottom-4 h-8 bg-indigo-300/40 rounded-full blur-xl"></div>
                    <div class="relative bg-slate-900 rounded-[2rem] p-2 shadow-2xl">
                        <div class="bg-white rounded-[1.5rem] overflow-hidden">
                            <!-- Header -->
                            <div class="h-8 bg-white border-b border-slate-100 flex items-center gap-1.5 px-3">
                                <div class="w-4 h-4 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-md"></div>
                                <div class="text-[8px] font-semibold text-slate-900">Praise Team</div>
                            </div>
                            <!-- Service card -->
                            <div class="p-3">
                                <div class="bg-gradient-to-br from-indigo-600 to-violet-600 rounded-lg p-2.5 text-white">
                                    <p class="text-[7px] uppercase tracking-wide text-indigo-200 font-semibold">Service</p>
                                    <p class="text-xs font-bold mt-0.5">Sunday Worship</p>
                                    <p class="text-[8px] text-indigo-100 mt-0.5">Sunday · 10:00 AM</p>
                                </div>
                                <div class="mt-2 space-y-1.5">
                                    <div v-for="i in 3" :key="i" class="flex items-center gap-1.5 bg-slate-50 rounded-md px-2 py-1.5">
                                        <span class="w-3 h-3 flex items-center justify-center text-[7px] font-bold text-indigo-600 bg-indigo-100 rounded-full">{{ i }}</span>
                                        <div class="flex-1">
                                            <div class="h-1.5 bg-slate-300 rounded w-3/4"></div>
                                            <div class="h-1 bg-slate-200 rounded w-1/2 mt-1"></div>
                                        </div>
                                        <div class="text-[7px] text-indigo-500 font-bold">{{ ['G', 'D', 'Em'][i-1] }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Features ──────────────────────────────────────────────── -->
        <section class="py-16 sm:py-24 bg-slate-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6">
                <div class="text-center mb-12">
                    <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-2">{{ t('landing.features.eyebrow') }}</p>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">{{ t('landing.features.title') }}</h2>
                    <p class="text-slate-500 mt-3 max-w-xl mx-auto">{{ t('landing.features.subtitle') }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div
                        v-for="f in features"
                        :key="f.icon"
                        class="bg-white rounded-2xl p-6 border border-slate-200"
                    >
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center mb-4">
                            <svg v-if="f.icon === 'calendar'" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <svg v-else-if="f.icon === 'music'" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                            </svg>
                            <svg v-else class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-base">{{ t(f.title) }}</h3>
                        <p class="text-sm text-slate-500 mt-1.5 leading-relaxed">{{ t(f.body) }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── How it works ──────────────────────────────────────────── -->
        <section class="py-16 sm:py-24">
            <div class="max-w-4xl mx-auto px-4 sm:px-6">
                <div class="text-center mb-12">
                    <p class="text-xs font-semibold text-indigo-600 uppercase tracking-wide mb-2">{{ t('landing.steps.eyebrow') }}</p>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">{{ t('landing.steps.title') }}</h2>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="(s, i) in steps"
                        :key="i"
                        class="flex items-start gap-4 bg-slate-50 rounded-2xl p-5 border border-slate-100"
                    >
                        <div class="shrink-0 w-9 h-9 bg-white border border-indigo-200 rounded-full flex items-center justify-center text-sm font-bold text-indigo-600">
                            {{ i + 1 }}
                        </div>
                        <div class="min-w-0">
                            <h3 class="font-semibold text-base">{{ t(s.title) }}</h3>
                            <p class="text-sm text-slate-500 mt-1">{{ t(s.body) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Donate ────────────────────────────────────────────────── -->
        <section v-if="donateUrl" id="donate" class="py-16 sm:py-24 bg-slate-50">
            <div class="max-w-3xl mx-auto px-4 sm:px-6">
                <div class="relative overflow-hidden bg-gradient-to-br from-indigo-600 to-violet-700 rounded-3xl p-8 sm:p-12 text-white text-center shadow-xl shadow-indigo-200">
                    <div class="absolute -top-20 -right-20 w-60 h-60 bg-white/10 rounded-full blur-2xl"></div>
                    <div class="absolute -bottom-20 -left-20 w-60 h-60 bg-violet-400/30 rounded-full blur-2xl"></div>

                    <div class="relative">
                        <div class="w-12 h-12 bg-white/15 rounded-full mx-auto flex items-center justify-center mb-4 backdrop-blur-sm">
                            <svg class="w-6 h-6 text-pink-200" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                            </svg>
                        </div>

                        <h2 class="text-2xl sm:text-3xl font-bold">{{ t('landing.donate.title') }}</h2>
                        <p class="text-indigo-100 mt-3 max-w-md mx-auto text-sm sm:text-base">
                            {{ t('landing.donate.body') }}
                        </p>

                        <button
                            @click="openDonate"
                            class="mt-7 inline-flex items-center gap-2 px-6 py-3 bg-white text-indigo-700 text-sm font-bold rounded-xl hover:bg-indigo-50 active:scale-[0.98] transition shadow-lg cursor-pointer"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106zm14.146-14.42a3.35 3.35 0 0 0-.607-.541c-.013.076-.026.175-.041.254-.59 3.025-2.566 6.082-7.453 6.082h-2.19l-1.12 7.106c-.082.518-.526.9-1.05.9H4.155L3.39 24h4.606c.524 0 .968-.382 1.05-.9l.022-.116 1.12-7.106.072-.394c.082-.518.526-.9 1.05-.9h.66c4.286 0 7.64-1.747 8.624-6.797.39-2.073.197-3.805-1.372-5.07z"/>
                            </svg>
                            {{ t('landing.donate.cta') }}
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Final CTA ─────────────────────────────────────────────── -->
        <section class="py-16 sm:py-20">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 text-center">
                <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">{{ t('landing.final.title') }}</h2>
                <p class="text-slate-500 mt-3 max-w-xl mx-auto">{{ t('landing.final.subtitle') }}</p>
                <Link
                    href="/login"
                    class="mt-7 inline-flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-br from-indigo-600 to-violet-600 text-white text-sm font-semibold rounded-xl shadow-lg shadow-indigo-200 hover:shadow-xl active:scale-[0.98] transition"
                >
                    {{ t('landing.final.cta') }}
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                    </svg>
                </Link>
            </div>
        </section>

        <!-- ── Footer ────────────────────────────────────────────────── -->
        <footer class="border-t border-slate-100 py-8">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
                <div class="flex items-center gap-2">
                    <Logo :size="20" />
                    <span>{{ appName }} · &copy; {{ new Date().getFullYear() }}</span>
                </div>
                <p>{{ t('app.tagline') }}</p>
            </div>
        </footer>
    </div>
</template>
