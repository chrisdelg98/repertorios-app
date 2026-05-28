<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue:  { type: String, default: '' },
    suggestions: { type: Array,  default: () => [] },
    placeholder: { type: String, default: '' },
    maxLength:   { type: [Number, String], default: null },
    maxResults:  { type: Number, default: 8 },
});

const emit = defineEmits(['update:modelValue']);

const open    = ref(false);
const rootEl  = ref(null);
const inputEl = ref(null);

const localValue = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v),
});

const filteredSuggestions = computed(() => {
    const q = (localValue.value ?? '').trim().toLowerCase();
    if (!q) return props.suggestions.slice(0, props.maxResults);
    return props.suggestions
        .filter(s => {
            const lower = s.toLowerCase();
            return lower.includes(q) && lower !== q;
        })
        .slice(0, props.maxResults);
});

function pick(s) {
    localValue.value = s;
    open.value = false;
    inputEl.value?.blur();
}

function onFocus() { open.value = true; }

function onBlur() {
    const trimmed = (localValue.value ?? '').trim();
    if (trimmed !== localValue.value) {
        localValue.value = trimmed;
    }
}

function onDocClick(e) {
    if (rootEl.value && !rootEl.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div ref="rootEl" class="relative">
        <input
            ref="inputEl"
            v-model="localValue"
            type="text"
            :placeholder="placeholder"
            :maxlength="maxLength || undefined"
            autocomplete="off"
            @focus="onFocus"
            @input="open = true"
            @blur="onBlur"
            class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        />

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <div
                v-if="open && filteredSuggestions.length"
                class="absolute z-30 mt-1 w-full max-h-56 overflow-y-auto bg-white border border-slate-200 rounded-lg shadow-lg"
            >
                <button
                    v-for="s in filteredSuggestions"
                    :key="s"
                    type="button"
                    @mousedown.prevent="pick(s)"
                    class="w-full text-left px-3 py-2 text-sm text-slate-700 hover:bg-indigo-50 hover:text-indigo-700 transition-colors"
                >
                    {{ s }}
                </button>
            </div>
        </Transition>
    </div>
</template>
