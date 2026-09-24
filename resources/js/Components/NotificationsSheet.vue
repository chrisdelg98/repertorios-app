<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Logo from '@/Components/Logo.vue';
import InstallSheet from '@/Components/InstallSheet.vue';
import { usePush } from '@/Composables/usePush';
import { useInstall } from '@/Composables/useInstall';

const props = defineProps({
    open: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'enabled']);

const { t, locale } = useI18n();
const page = usePage();

const publicKey = computed(() => page.props.push?.public_key ?? null);

const { isSupported, isDenied, busy, subscribe } = usePush(publicKey.value);
const { isInstalled, isIos } = useInstall();

const installSheetOpen = ref(false);

/**
 * Which of the four states this device is in. Each one needs a different thing
 * from the person, and lumping them together is how you get someone tapping a
 * button that can no longer do anything.
 */
const state = computed(() => {
    // On iOS the Push API only exists inside the installed app, so "install
    // first" is the real instruction, not an apology about support.
    if (isIos.value && !isInstalled.value) return 'needs_install';
    if (!isSupported.value) return 'unsupported';
    if (isDenied.value) return 'blocked';
    return 'ready';
});

const reasons = computed(() => [
    { icon: 'user',  text: t('push.reason_assigned') },
    { icon: 'list',  text: t('push.reason_setlist') },
    { icon: 'phone', text: t('push.reason_closed') },
]);

watch(() => props.open, (isOpen) => {
    if (!isOpen) installSheetOpen.value = false;
});

async function enable() {
    if (state.value === 'needs_install') {
        installSheetOpen.value = true;
        return;
    }

    // Called straight from the click: the browser refuses a permission
    // request that is not tied to a user gesture.
    const ok = await subscribe(locale.value);

    if (ok) {
        emit('enabled');
        emit('close');
    }
}
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
                                    {{ t('push.sheet_eyebrow') }}
                                </p>
                                <h2 class="text-lg font-bold leading-tight">
                                    {{ t('push.sheet_title') }}
                                </h2>
                            </div>
                        </div>

                        <p class="relative mt-3 text-sm text-white/95 leading-relaxed">
                            {{ t('push.sheet_subtitle') }}
                        </p>
                    </div>

                    <!-- Body -->
                    <div class="px-5 py-5 overflow-y-auto flex-1">
                        <div class="space-y-2.5">
                            <div
                                v-for="(reason, i) in reasons"
                                :key="i"
                                class="flex items-start gap-3 rounded-xl bg-slate-50 px-3.5 py-3"
                            >
                                <span class="w-7 h-7 rounded-lg bg-white border border-slate-200 flex items-center justify-center shrink-0 text-indigo-600">
                                    <svg v-if="reason.icon === 'user'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                    <svg v-else-if="reason.icon === 'list'" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                                    </svg>
                                    <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                </span>

                                <p class="text-sm font-medium text-slate-900 leading-snug flex-1 min-w-0">{{ reason.text }}</p>
                            </div>
                        </div>

                        <!-- Already denied: the browser will not ask again -->
                        <div v-if="state === 'blocked'" class="mt-4 rounded-xl bg-amber-50 border border-amber-200 px-3.5 py-3">
                            <p class="text-xs font-semibold text-amber-900">{{ t('push.blocked_title') }}</p>
                            <p class="text-xs text-amber-800 leading-relaxed mt-1">{{ t('push.blocked_body') }}</p>
                        </div>

                        <div v-else-if="state === 'needs_install'" class="mt-4 rounded-xl bg-indigo-50 border border-indigo-100 px-3.5 py-3">
                            <p class="text-xs text-slate-700 leading-relaxed">{{ t('push.ios_needs_install') }}</p>
                        </div>

                        <div v-else-if="state === 'unsupported'" class="mt-4 rounded-xl bg-slate-100 border border-slate-200 px-3.5 py-3">
                            <p class="text-xs text-slate-700 leading-relaxed">{{ t('push.unsupported') }}</p>
                        </div>

                        <p v-else class="text-xs text-slate-600 leading-relaxed mt-4">
                            {{ t('push.permission_hint') }}
                        </p>
                    </div>

                    <!-- Footer -->
                    <div class="px-5 py-4 border-t border-slate-100 space-y-2">
                        <button
                            v-if="state === 'ready' || state === 'needs_install'"
                            type="button"
                            @click="enable"
                            :disabled="busy"
                            class="w-full py-3 bg-gradient-to-br from-indigo-600 to-violet-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] disabled:opacity-60 disabled:active:scale-100 transition"
                        >
                            {{ busy
                                ? t('push.enabling')
                                : (state === 'needs_install' ? t('push.install_first') : t('push.enable')) }}
                        </button>

                        <button
                            type="button"
                            @click="emit('close')"
                            class="w-full py-2.5 text-sm font-semibold text-slate-600 rounded-xl border border-slate-300 hover:bg-slate-50 transition-colors"
                        >
                            {{ state === 'ready' ? t('push.later') : t('install.got_it') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <InstallSheet :open="installSheetOpen" @close="installSheetOpen = false" />
</template>
