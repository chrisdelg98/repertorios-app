<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import PanelIcon from '@/Components/PanelIcon.vue';

/**
 * What a panel is showing, chosen from everything a panel could show.
 *
 * One list rather than a row of tabs plus a stray button beside it: the
 * recording and the words are alternatives for the same space, and a control
 * that says so is a control that stays the same size when a fifth thing is
 * added to it.
 *
 * The rule line between the recordings and the readings is doing real work —
 * picking a recording also changes what is playing, and picking a reading does
 * not. They are two kinds of choice wearing one shape.
 */

const props = defineProps({
    /** The id currently shown in this panel. */
    value: { type: String, default: '' },
    /** [{ id, label, group: 'media' | 'text', ready }] */
    options: { type: Array, default: () => [] },
});

const emit = defineEmits(['select']);

const open = ref(false);
const root = ref(null);

const current = computed(() => props.options.find(o => o.id === props.value) ?? null);

const groups = computed(() => ['media', 'text']
    .map(group => props.options.filter(o => o.group === group))
    .filter(list => list.length));

function onDocClick(event) {
    if (!root.value?.contains(event.target)) open.value = false;
}

function onKey(event) {
    if (event.key === 'Escape') open.value = false;
}

onMounted(() => {
    document.addEventListener('click', onDocClick);
    document.addEventListener('keydown', onKey);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocClick);
    document.removeEventListener('keydown', onKey);
});

function choose(option) {
    open.value = false;

    if (option.id !== props.value) emit('select', option.id);
}
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            @click.stop="open = !open"
            class="h-8 pl-2.5 pr-2 flex items-center gap-1.5 text-2xs font-semibold rounded-lg bg-white/10 text-white hover:bg-white/20 transition-colors"
            :aria-expanded="open"
        >
            <PanelIcon :name="current?.id" class="w-3.5 h-3.5 shrink-0" />
            {{ current?.label ?? '—' }}
            <svg
                class="w-3 h-3 opacity-60 transition-transform"
                :class="open ? 'rotate-180' : ''"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
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
                class="absolute left-0 top-full mt-1 w-44 z-30 origin-top-left bg-slate-800 rounded-xl border border-white/10 shadow-xl overflow-hidden py-1"
            >
                <div
                    v-for="(list, g) in groups"
                    :key="g"
                    :class="g > 0 ? 'border-t border-white/10 mt-1 pt-1' : ''"
                >
                    <button
                        v-for="option in list"
                        :key="option.id"
                        type="button"
                        :disabled="!option.ready"
                        @click.stop="choose(option)"
                        class="w-full flex items-center gap-2.5 px-3 py-2 text-left text-xs transition-colors"
                        :class="option.id === value
                            ? 'bg-indigo-600 text-white'
                            : option.ready
                                ? 'text-slate-200 hover:bg-white/5'
                                : 'text-slate-600 cursor-not-allowed'"
                    >
                        <PanelIcon :name="option.id" class="w-3.5 h-3.5 shrink-0" />
                        {{ option.label }}
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
