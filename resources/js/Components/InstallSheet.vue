<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import Logo from '@/Components/Logo.vue';
import { useInstall } from '@/Composables/useInstall';

const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const { t } = useI18n();
const { platform, isIos, isIosSafari } = useInstall();

// Each platform installs differently, and showing iPhone steps to someone on
// Windows is how you get a user staring at a Share dialog wondering what
// happened. iOS never offers a prompt at all, so there these are the only path.
const STEPS = {
    ios: [
        { icon: 'share', key: 'ios_step_1' },
        { icon: 'plus',  key: 'ios_step_2' },
        { icon: 'check', key: 'ios_step_3' },
    ],
    android: [
        { icon: 'menu',  key: 'android_step_1' },
        { icon: 'plus',  key: 'android_step_2' },
        { icon: 'check', key: 'android_step_3' },
    ],
    desktop: [
        { icon: 'install', key: 'desktop_step_1' },
        { icon: 'menu',    key: 'desktop_step_2' },
        { icon: 'check',   key: 'desktop_step_3' },
    ],
};

const steps = computed(() =>
    (STEPS[platform.value] ?? STEPS.desktop).map(step => ({
        icon: step.icon,
        text: t('install.' + step.key),
    }))
);

const subtitle = computed(() => t('install.sheet_subtitle_' + (platform.value === 'unsupported' ? 'desktop' : platform.value)));

// On iOS only real Safari can install; on desktop the icon disappears once the
// app is already there, which is the usual reason the prompt never shows.
const note = computed(() => {
    if (isIos.value && !isIosSafari.value) return t('install.ios_note_wrong_browser');
    if (isIos.value) return t('install.ios_note');
    if (platform.value === 'desktop') return t('install.desktop_note');
    return '';
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm"
                @click="emit('close')"
            />
        </Transition>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-4"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-4"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 pointer-events-none"
            >
                <div class="bg-white w-full sm:max-w-md rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden pointer-events-auto max-h-[92vh] flex flex-col">
                    <!-- Gradient header -->
                    <div class="relative bg-gradient-to-br from-indigo-600 to-violet-600 px-6 pt-7 pb-6 text-white">
                        <div class="absolute -top-12 -right-12 w-40 h-40 bg-white/10 rounded-full blur-2xl pointer-events-none" />
                        <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-violet-400/30 rounded-full blur-2xl pointer-events-none" />

                        <div class="relative flex items-center gap-3">
                            <Logo :size="44" />
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-white/80 uppercase tracking-wide">
                                    {{ t('install.sheet_eyebrow') }}
                                </p>
                                <h2 class="text-lg font-bold leading-tight truncate">
                                    {{ t('install.sheet_title') }}
                                </h2>
                            </div>
                        </div>

                        <p class="relative mt-3 text-sm text-white/95 leading-relaxed">
                            {{ subtitle }}
                        </p>
                    </div>

                    <!-- Steps -->
                    <div class="px-5 py-5 overflow-y-auto flex-1">
                        <div class="space-y-2.5">
                            <div
                                v-for="(step, i) in steps"
                                :key="i"
                                class="flex items-start gap-3 rounded-xl bg-slate-50 px-3.5 py-3"
                            >
                                <span class="w-7 h-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center shrink-0 text-indigo-600">
                                    <svg v-if="step.icon === 'share'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M5 14v5a2 2 0 002 2h10a2 2 0 002-2v-5" />
                                    </svg>
                                    <svg v-else-if="step.icon === 'plus'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m-4 9h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <svg v-else-if="step.icon === 'menu'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01" />
                                    </svg>
                                    <svg v-else-if="step.icon === 'install'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v9m0 0l-3-3m3 3l3-3M4 15v3a2 2 0 002 2h12a2 2 0 002-2v-3" />
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="text-2xs font-semibold text-slate-600 uppercase tracking-wide">
                                        {{ t('install.step_n', { n: i + 1 }) }}
                                    </p>
                                    <p class="text-sm font-medium text-slate-900 mt-0.5 leading-snug">{{ step.text }}</p>
                                </div>
                            </div>
                        </div>

                        <p v-if="note" class="text-xs text-slate-600 leading-relaxed mt-4 rounded-xl bg-indigo-50 border border-indigo-100 px-3.5 py-3">
                            {{ note }}
                        </p>

                        <div class="mt-4 space-y-1.5">
                            <p class="flex items-start gap-2 text-xs font-medium text-slate-600">
                                <span class="text-indigo-600">·</span>{{ t('install.benefit_1') }}
                            </p>
                            <p class="flex items-start gap-2 text-xs font-medium text-slate-600">
                                <span class="text-indigo-600">·</span>{{ t('install.benefit_2') }}
                            </p>
                            <p class="flex items-start gap-2 text-xs font-medium text-slate-600">
                                <span class="text-indigo-600">·</span>{{ t('install.benefit_3') }}
                            </p>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-5 py-4 border-t border-slate-100">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="w-full py-3 bg-gradient-to-br from-indigo-600 to-violet-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
                        >
                            {{ t('install.got_it') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
