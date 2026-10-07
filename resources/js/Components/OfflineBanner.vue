<script setup>
import { useOffline } from '@/Composables/useOffline';
import { useI18n } from 'vue-i18n';

/**
 * Says when what you are reading was last true.
 *
 * Shown only when the page came out of the cache. Stale data presented as
 * current is worse than no data: someone rehearses the wrong set and finds out
 * on stage.
 */

const { t } = useI18n();
const { isOffline, servedAtLabel } = useOffline();
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0 -translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="isOffline" class="bg-amber-50 border-b border-amber-200">
            <div class="px-4 lg:px-8 py-2 lg:max-w-3xl lg:mx-auto flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 0 1 0 12.728m-12.728 0a9 9 0 0 1 0-12.728m3.182 9.546a4.5 4.5 0 0 1 0-6.364m6.364 0a4.5 4.5 0 0 1 0 6.364M12 12h.01M3 3l18 18" />
                </svg>
                <p class="text-xs font-medium text-amber-900 min-w-0">
                    {{ t('offline.title') }}
                    <span class="font-normal text-amber-800">· {{ t('offline.since', { time: servedAtLabel }) }}</span>
                </p>
            </div>
        </div>
    </Transition>
</template>
