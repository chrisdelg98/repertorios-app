<script setup>
import { ref, computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { cachePage, pageCachedAt, forgetPage } from '@/Composables/useOffline';

/**
 * Manages one service's offline copy.
 *
 * Reached from two places — the service itself and its row in the list — so it
 * takes the URL rather than reading the current one. Whether a copy exists is
 * checked when it opens, not passed in: the caller would have to keep that
 * fresh across saving, deleting and coming back, and it is one cache lookup.
 */

const props = defineProps({
    open: { type: Boolean, default: false },
    /** Path of the service to keep, e.g. /services/12. */
    url: { type: String, default: '' },
});

const emit = defineEmits(['close', 'changed']);

const { t } = useI18n();

const savedAt = ref(null);
const saving = ref(false);
const removing = ref(false);
const failed = ref(false);

const savedLabel = computed(() => savedAt.value?.toLocaleString(undefined, {
    day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit',
}) ?? '');

watch(() => props.open, async (isOpen) => {
    if (!isOpen) return;

    failed.value = false;
    savedAt.value = await pageCachedAt(props.url);
});

async function save() {
    saving.value = true;
    failed.value = false;

    // The songs index too: the setlist links into it, and a dead link is what
    // people remember about an offline mode.
    const ok = await cachePage(props.url) && await cachePage('/songs');

    savedAt.value = await pageCachedAt(props.url);
    saving.value = false;
    failed.value = !ok;

    if (!ok) return;

    emit('changed');
    setTimeout(() => emit('close'), 900);
}

async function remove() {
    removing.value = true;

    await forgetPage(props.url);

    savedAt.value = null;
    removing.value = false;

    emit('changed');
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm" @click="emit('close')" />
        </Transition>

        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="opacity-0 translate-y-6"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-6"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 pointer-events-none"
            >
                <div class="bg-white w-full sm:max-w-sm rounded-t-3xl sm:rounded-3xl shadow-2xl pointer-events-auto px-5 pt-5 pb-6">
                    <div
                        class="w-11 h-11 rounded-xl flex items-center justify-center mb-3"
                        :class="savedAt ? 'bg-emerald-50' : 'bg-indigo-50'"
                    >
                        <svg v-if="savedAt" class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <svg v-else class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                    </div>

                    <h3 class="text-base font-bold text-slate-900">
                        {{ savedAt ? t('offline.sheet_saved_title') : t('offline.sheet_title') }}
                    </h3>

                    <p class="text-sm text-slate-600 leading-relaxed mt-1">
                        {{ savedAt ? t('offline.sheet_saved_body', { date: savedLabel }) : t('offline.sheet_body') }}
                    </p>

                    <!-- What it covers and what it does not, before pressing
                         rather than after failing to hear a track. -->
                    <ul class="mt-3 space-y-1.5">
                        <li class="flex items-start gap-2 text-xs text-slate-600">
                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ t('offline.sheet_includes') }}
                        </li>
                        <li class="flex items-start gap-2 text-xs text-slate-600">
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                            {{ t('offline.sheet_excludes') }}
                        </li>
                    </ul>

                    <p v-if="failed" class="text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mt-3">
                        {{ t('offline.prepare_failed') }}
                    </p>

                    <div class="flex gap-2.5 mt-5">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="flex-1 py-2.5 text-sm font-semibold text-slate-600 rounded-xl border border-slate-300 hover:bg-slate-50 transition-colors"
                        >{{ t('services.form.cancel') }}</button>

                        <button
                            v-if="savedAt"
                            type="button"
                            @click="remove"
                            :disabled="removing"
                            class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl active:scale-[0.98] disabled:opacity-60 transition"
                        >{{ removing ? t('offline.removing') : t('offline.remove') }}</button>

                        <button
                            v-else
                            type="button"
                            @click="save"
                            :disabled="saving"
                            class="flex-1 py-2.5 bg-gradient-to-br from-indigo-600 to-violet-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] disabled:opacity-60 transition"
                        >{{ saving ? t('offline.preparing') : t('offline.save') }}</button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
