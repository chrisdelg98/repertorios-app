<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import ServiceColorSwatches from '@/Components/ServiceColorSwatches.vue';
import { serviceColor, DEFAULT_SERVICE_COLOR } from '@/Constants/serviceColors';

const props = defineProps({
    modelValue: { type: String, default: DEFAULT_SERVICE_COLOR },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const open = ref(false);

const current = computed(() => serviceColor(props.modelValue));

function pick(key) {
    emit('update:modelValue', key);
    open.value = false;
}

function onDocClick(e) {
    if (!e.target.closest('[data-color-picker]')) open.value = false;
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div class="relative" data-color-picker>
        <button
            type="button"
            @click.stop="open = !open"
            class="w-full flex items-center justify-between px-3 py-2.5 text-sm rounded-lg border border-slate-300 bg-white text-left focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :aria-expanded="open"
        >
            <span class="flex items-center gap-2.5 min-w-0">
                <span class="w-5 h-5 rounded-full shrink-0" :class="current.swatch" />
                <span class="text-slate-900 font-medium truncate">{{ t('services.colors.' + current.key) }}</span>
            </span>
            <svg
                class="w-4 h-4 text-slate-600 shrink-0 transition-transform"
                :class="open ? 'rotate-180' : ''"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute left-0 right-0 top-full mt-1.5 z-30 origin-top bg-white rounded-xl border border-slate-200 shadow-lg p-3"
            >
                <ServiceColorSwatches
                    :model-value="modelValue"
                    :columns="8"
                    @update:model-value="pick"
                />
            </div>
        </Transition>
    </div>
</template>
