<script setup>
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { KIND_ORDER, kindStyle } from '@/Utils/lyrics';

const { t } = useI18n();

/**
 * What this block is.
 *
 * A sheet rather than a dropdown: nine options at thumb height, each a target
 * worth aiming at, on the screen where someone is already typing with one
 * hand. A `<select>` would be two taps and a system picker that hides the
 * colours, which are the thing that makes the list readable at a glance.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    kind: { type: String, default: 'verse' },
    label: { type: String, default: '' },
});

const emit = defineEmits(['close', 'select']);

const freeLabel = ref('');

watch(() => props.open, (open) => {
    if (open) freeLabel.value = props.label ?? '';
});

function choose(kind) {
    // A free label only belongs to the kind that exists to carry one; picking
    // anything else means the label was not what was wanted after all.
    emit('select', {
        kind,
        label: kind === 'custom' ? freeLabel.value.trim() : '',
    });
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
            <div v-if="open" class="fixed inset-0 z-40 bg-black/40" @click="emit('close')" />
        </Transition>

        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="open"
                class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-xl z-50 bg-white rounded-t-2xl max-h-[85vh] flex flex-col shadow-xl"
            >
                <div class="px-4 pt-3 pb-3 border-b border-slate-100">
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
                    <h2 class="text-base font-bold text-slate-900">{{ t('songs.lyrics.kind_title') }}</h2>
                    <p class="text-xs text-slate-600 mt-0.5">{{ t('songs.lyrics.kind_hint') }}</p>
                </div>

                <div class="flex-1 overflow-y-auto px-3 py-3">
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="option in KIND_ORDER"
                            :key="option"
                            type="button"
                            @click="choose(option)"
                            class="flex items-center gap-2.5 px-3 py-3 rounded-xl border text-left transition-colors"
                            :class="option === kind
                                ? kindStyle(option).chip
                                : 'bg-white border-slate-200 text-slate-700 hover:border-slate-300'"
                        >
                            <span class="shrink-0 w-2.5 h-2.5 rounded-full" :class="kindStyle(option).dot" />
                            <span class="text-sm font-semibold truncate">{{ t('songs.lyrics.kinds.' + option) }}</span>
                        </button>
                    </div>

                    <!-- The free label, right where picking "Otra" leaves you. -->
                    <div class="mt-3">
                        <label class="block text-2xs font-semibold text-slate-600 uppercase tracking-wide mb-1.5">
                            {{ t('songs.lyrics.free_label') }}
                        </label>
                        <div class="flex gap-2">
                            <input
                                v-model="freeLabel"
                                type="text"
                                maxlength="40"
                                :placeholder="t('songs.lyrics.free_label_placeholder')"
                                class="flex-1 min-w-0 px-3 py-2.5 text-base border border-slate-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 outline-none"
                                @keydown.enter.prevent="choose('custom')"
                            />
                            <button
                                type="button"
                                @click="choose('custom')"
                                :disabled="!freeLabel.trim()"
                                class="shrink-0 px-4 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 disabled:opacity-30 transition-colors"
                            >
                                {{ t('songs.lyrics.use') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 px-4 py-3 border-t border-slate-100">
                    <button
                        type="button"
                        @click="emit('close')"
                        class="w-full py-3 text-sm font-semibold text-slate-600 bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors"
                    >
                        {{ t('songs.cancel') }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
