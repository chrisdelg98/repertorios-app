<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    modelValue:       { type: Array,  default: () => [] },
    label:            { type: String, required: true },
    options:          { type: Array,  default: () => [] },
    clearLabel:       { type: String, default: 'Clear' },
    emptyLabel:       { type: String, default: '—' },
    searchPlaceholder:{ type: String, default: 'Search…' },
    noResultsLabel:   { type: String, default: 'No matches' },
    searchable:       { type: Boolean, default: false },
    bold:             { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const open      = ref(false);
const rootEl    = ref(null);
const dropdownEl = ref(null);
const searchEl  = ref(null);
const query     = ref('');
const dropdownWidthPx = ref(280);
const dropdownLeftPx = ref(16);
const dropdownTopPx = ref(0);
const dropdownMaxHeightPx = ref(288);

const VIEWPORT_MARGIN = 16; // Keep the same horizontal breathing room as page padding.
const MAX_DROPDOWN_WIDTH = 350;
const MIN_DROPDOWN_WIDTH = 180;
const MAX_DROPDOWN_HEIGHT = 288;
const MIN_DROPDOWN_HEIGHT = 96;
const DROPDOWN_GAP = 6;

const dropdownStyle = computed(() => ({
    left: `${dropdownLeftPx.value}px`,
    top: `${dropdownTopPx.value}px`,
    width: `${dropdownWidthPx.value}px`,
    maxHeight: `${dropdownMaxHeightPx.value}px`,
}));

function updateDropdownLayout() {
    const rect = rootEl.value?.getBoundingClientRect();
    if (!rect) return;

    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;
    const maxWidth = Math.max(MIN_DROPDOWN_WIDTH, viewportWidth - (VIEWPORT_MARGIN * 2));
    const width = Math.min(MAX_DROPDOWN_WIDTH, maxWidth);
    const preferredLeft = rect.left + width > viewportWidth - VIEWPORT_MARGIN
        ? rect.right - width
        : rect.left;
    const maxLeft = viewportWidth - VIEWPORT_MARGIN - width;

    dropdownWidthPx.value = width;
    dropdownLeftPx.value = Math.min(
        Math.max(VIEWPORT_MARGIN, preferredLeft),
        Math.max(VIEWPORT_MARGIN, maxLeft)
    );

    const spaceBelow = viewportHeight - rect.bottom - VIEWPORT_MARGIN;
    const spaceAbove = rect.top - VIEWPORT_MARGIN;
    const shouldOpenAbove = spaceBelow < MIN_DROPDOWN_HEIGHT && spaceAbove > spaceBelow;
    const availableHeight = shouldOpenAbove ? spaceAbove - DROPDOWN_GAP : spaceBelow - DROPDOWN_GAP;
    const height = Math.min(MAX_DROPDOWN_HEIGHT, Math.max(MIN_DROPDOWN_HEIGHT, availableHeight));
    const preferredTop = shouldOpenAbove
        ? rect.top - DROPDOWN_GAP - height
        : rect.bottom + DROPDOWN_GAP;
    const maxTop = viewportHeight - VIEWPORT_MARGIN - height;

    dropdownMaxHeightPx.value = height;
    dropdownTopPx.value = Math.min(
        Math.max(VIEWPORT_MARGIN, preferredTop),
        Math.max(VIEWPORT_MARGIN, maxTop)
    );
}

function toggle() {
    open.value = !open.value;
    if (open.value) {
        nextTick(() => {
            updateDropdownLayout();
            if (props.searchable) searchEl.value?.focus();
        });
    }
}

function close() {
    open.value = false;
    query.value = '';
}

function isSelected(opt) { return props.modelValue.includes(opt); }

function toggleOption(opt) {
    const next = isSelected(opt)
        ? props.modelValue.filter(x => x !== opt)
        : [...props.modelValue, opt];
    emit('update:modelValue', next);
}

function clearAll() {
    emit('update:modelValue', []);
}

const count = computed(() => props.modelValue.length);

const triggerLabel = computed(() => {
    if (count.value === 0) return props.label;
    if (count.value === 1) return props.modelValue[0];
    return `${props.label} · ${count.value}`;
});

const filteredOptions = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.options;
    return props.options.filter(o => String(o).toLowerCase().includes(q));
});

function onDocClick(e) {
    const clickedTrigger = rootEl.value?.contains(e.target);
    const clickedDropdown = dropdownEl.value?.contains(e.target);
    if (!clickedTrigger && !clickedDropdown) close();
}

function onViewportChange() {
    if (open.value) updateDropdownLayout();
}

onMounted(() => {
    document.addEventListener('click', onDocClick);
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});
</script>

<template>
    <div ref="rootEl" class="relative">
        <button
            type="button"
            @click="toggle"
            class="inline-flex items-center gap-2 px-3.5 py-2 text-xs font-semibold rounded-lg border transition-colors min-h-10"
            :class="count > 0
                ? 'bg-indigo-50 border-indigo-300 text-indigo-700'
                : 'bg-white border-slate-300 text-slate-700 hover:border-slate-400'"
        >
            <span class="truncate max-w-[160px]">{{ triggerLabel }}</span>
            <svg
                class="w-3.5 h-3.5 transition-transform shrink-0"
                :class="open ? 'rotate-180' : ''"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 -translate-y-1"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 -translate-y-1"
            >
                <div
                    v-if="open"
                    ref="dropdownEl"
                    class="fixed z-50 overflow-hidden bg-white border border-slate-200 rounded-xl shadow-lg flex flex-col"
                    :style="dropdownStyle"
                >
                    <!-- Search input -->
                    <div v-if="searchable" class="relative border-b border-slate-100">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 110-16 8 8 0 010 16z" />
                        </svg>
                        <input
                            ref="searchEl"
                            v-model="query"
                            type="text"
                            :placeholder="searchPlaceholder"
                            class="w-full pl-8 pr-2 py-2.5 text-sm bg-transparent focus:outline-none placeholder:text-slate-500"
                        />
                    </div>

                    <!-- Clear row -->
                    <button
                        v-if="count > 0"
                        type="button"
                        @click="clearAll"
                        class="flex items-center justify-between px-3.5 py-2.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 border-b border-slate-100 transition-colors min-h-11"
                    >
                        <span>{{ clearLabel }}</span>
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>

                    <!-- Options list -->
                    <div class="overflow-y-auto flex-1">
                        <p v-if="!options.length" class="px-3.5 py-2.5 text-sm text-slate-500 italic">{{ emptyLabel }}</p>
                        <p v-else-if="!filteredOptions.length" class="px-3.5 py-2.5 text-sm text-slate-500 italic">{{ noResultsLabel }}</p>

                        <button
                            v-for="opt in filteredOptions"
                            :key="opt"
                            type="button"
                            @click="toggleOption(opt)"
                            class="w-full flex items-center gap-2.5 px-3.5 py-2.5 text-sm text-left hover:bg-slate-50 transition-colors min-h-11"
                        >
                            <span
                                class="w-4 h-4 flex items-center justify-center rounded border shrink-0 transition-colors"
                                :class="isSelected(opt)
                                    ? 'bg-indigo-600 border-indigo-600'
                                    : 'border-slate-300 bg-white'"
                            >
                                <svg v-if="isSelected(opt)" class="w-3 h-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </span>
                            <span
                                class="truncate flex-1"
                                :class="[
                                    isSelected(opt) ? 'text-slate-900' : 'text-slate-600',
                                    bold ? 'font-bold' : (isSelected(opt) ? 'font-medium' : 'font-normal'),
                                ]"
                            >{{ opt }}</span>
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
