<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';
import { useInstall } from '@/composables/useInstall';
import Logo from '@/Components/Logo.vue';

const { t } = useI18n();
const { isInstallable, isInstalled, promptInstall } = useInstall();

const STORAGE_KEY = 'pwa_banner_dismissed';
const show = ref(false);
let timer = null;

function onBeforeInstallPrompt() {
    if (localStorage.getItem(STORAGE_KEY)) return;
    if (isInstalled.value) return;
    timer = setTimeout(() => { show.value = true; }, 1800);
}

onMounted(() => {
    if (isInstalled.value) return;
    if (localStorage.getItem(STORAGE_KEY)) return;

    if (isInstallable.value) {
        timer = setTimeout(() => { show.value = true; }, 2500);
    } else {
        window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    }
});

onUnmounted(() => {
    clearTimeout(timer);
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
});

function dismiss() {
    localStorage.setItem(STORAGE_KEY, '1');
    show.value = false;
}

async function install() {
    await promptInstall();
    dismiss();
}
</script>

<template>
    <!-- Hidden <install> element — activated by useInstall when the browser supports it -->
    <install id="pwa-install-el" style="display:none"></install>

    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 bg-black/30" @click="dismiss" />
        </Transition>

        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="show"
                class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl shadow-2xl overflow-hidden"
            >
                <!-- Gradient top bar -->
                <div class="h-1 bg-gradient-to-r from-indigo-500 to-violet-600" />

                <div class="px-5 pt-4 pb-8">
                    <!-- Drag handle -->
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-5" />

                    <!-- Icon + heading -->
                    <div class="flex items-center gap-4 mb-4">
                        <Logo :size="56" />
                        <div>
                            <p class="text-base font-bold text-slate-900 leading-tight">{{ t('install.banner_title') }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">{{ t('install.banner_subtitle') }}</p>
                        </div>
                    </div>

                    <!-- Benefits -->
                    <ul class="space-y-2 mb-5">
                        <li v-for="benefit in [t('install.benefit_1'), t('install.benefit_2'), t('install.benefit_3')]" :key="benefit"
                            class="flex items-center gap-2.5 text-sm text-slate-600"
                        >
                            <span class="w-5 h-5 rounded-full bg-indigo-50 flex items-center justify-center shrink-0">
                                <svg class="w-3 h-3 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            {{ benefit }}
                        </li>
                    </ul>

                    <!-- Actions -->
                    <div class="flex gap-2.5">
                        <button
                            @click="dismiss"
                            class="flex-1 py-3 text-sm font-medium text-slate-500 rounded-xl border border-slate-200 hover:bg-slate-50 transition-colors"
                        >
                            {{ t('install.later') }}
                        </button>
                        <button
                            @click="install"
                            class="flex-1 py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
                        >
                            {{ t('install.install') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
