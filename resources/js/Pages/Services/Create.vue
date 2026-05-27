<script setup>
import { ref, computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t, locale } = useI18n();

const props = defineProps({
    service: Object,
    templates: { type: Array, default: () => [] },
});

const isEdit = !!props.service;

const form = useForm({
    date: props.service?.date?.slice(0, 10) ?? today(),
    time: props.service?.time?.slice(0, 5) ?? '',
    type: props.service?.type ?? 'other',
    notes: props.service?.notes ?? '',
});

function today() {
    return new Date().toISOString().slice(0, 10);
}

// ── Type selector ──────────────────────────────────────────────
const showSheet = ref(false);
const search = ref('');
const customName = ref(
    props.service && props.service.type !== 'other' && !props.templates.find(t => t.name === props.service.type)
        ? props.service.type
        : ''
);

// What's currently selected: { kind: 'other'|'custom'|'template', template? }
const selection = ref(resolveInitialSelection());

function resolveInitialSelection() {
    if (!props.service || props.service.type === 'other') return { kind: 'other' };
    const tpl = props.templates.find(t => t.name === props.service.type);
    if (tpl) return { kind: 'template', template: tpl };
    return { kind: 'custom' };
}

const selectionLabel = computed(() => {
    if (selection.value.kind === 'other') return t('services.type_other');
    if (selection.value.kind === 'custom') return customName.value || t('services.type_custom');
    return selection.value.template.name;
});

const filteredTemplates = computed(() => {
    const q = search.value.toLowerCase();
    if (!q) return props.templates;
    return props.templates.filter(t => t.name.toLowerCase().includes(q));
});

function selectOther() {
    selection.value = { kind: 'other' };
    form.type = 'other';
    showSheet.value = false;
    search.value = '';
}

function selectCustom() {
    selection.value = { kind: 'custom' };
    form.type = customName.value || 'other';
    showSheet.value = false;
    search.value = '';
}

function selectTemplate(tpl) {
    selection.value = { kind: 'template', template: tpl };
    form.type = tpl.name;
    applyTemplate(tpl);
    showSheet.value = false;
    search.value = '';
}

watch(customName, (val) => {
    if (selection.value.kind === 'custom') {
        form.type = val.slice(0, 20) || 'other';
    }
});

// ── Smart date from template ───────────────────────────────────
function applyTemplate(tpl) {
    const [h, m] = tpl.time.split(':').map(Number);
    const now = new Date();
    const targetToday = new Date(now);
    targetToday.setHours(h, m, 0, 0);

    let daysUntil = tpl.day_of_week - now.getDay();

    if (daysUntil < 0) {
        daysUntil += 7;
    } else if (daysUntil === 0 && now >= targetToday) {
        daysUntil = 7; // time already passed today → next week
    }

    const next = new Date(now);
    next.setDate(next.getDate() + daysUntil);

    form.date = next.toISOString().slice(0, 10);
    form.time = tpl.time.slice(0, 5);
}

// ── Submit ──────────────────────────────────────────────────────
function submit() {
    if (isEdit) {
        form.put('/services/' + props.service.id);
    } else {
        form.post('/services');
    }
}
</script>

<template>
    <Head :title="isEdit ? t('services.edit') : t('services.create')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-3xl mx-auto">
            <h1 class="text-lg font-semibold text-slate-900 mb-5">
                {{ isEdit ? t('services.edit') : t('services.create') }}
            </h1>

            <form @submit.prevent="submit" class="space-y-4">

                <!-- Type selector trigger -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-slate-600">{{ t('services.form.type') }}</label>
                    <button
                        type="button"
                        @click="showSheet = true"
                        class="w-full flex items-center justify-between px-3 py-2.5 text-sm rounded-lg border border-slate-300 bg-white text-left focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <span :class="selection.kind === 'other' ? 'text-slate-400' : 'text-slate-900 font-medium'">
                            {{ selectionLabel }}
                        </span>
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l4-4 4 4m0 6l-4 4-4-4" />
                        </svg>
                    </button>

                    <!-- Custom name input (only when Personalizado) -->
                    <div v-if="selection.kind === 'custom'" class="mt-1">
                        <input
                            v-model="customName"
                            type="text"
                            maxlength="20"
                            autofocus
                            :placeholder="t('services.custom_name_placeholder')"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p class="text-xs text-slate-400 text-right mt-0.5">{{ customName.length }}/20</p>
                    </div>
                </div>

                <!-- Date -->
                <div class="space-y-1.5">
                    <label for="date" class="block text-xs font-medium text-slate-600">{{ t('services.form.date') }}</label>
                    <input
                        id="date"
                        v-model="form.date"
                        type="date"
                        required
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="form.errors.date" class="text-xs text-red-600">{{ form.errors.date }}</p>
                </div>

                <!-- Time -->
                <div class="space-y-1.5">
                    <label for="time" class="block text-xs font-medium text-slate-600">{{ t('services.form.time') }}</label>
                    <input
                        id="time"
                        v-model="form.time"
                        type="time"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <!-- Notes -->
                <div class="space-y-1.5">
                    <label for="notes" class="block text-xs font-medium text-slate-600">{{ t('services.form.notes') }}</label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    />
                </div>

                <div class="flex gap-3 pt-2">
                    <a
                        href="/services"
                        class="flex-1 py-2.5 text-sm font-semibold text-center rounded-lg border border-slate-300 text-slate-700"
                    >
                        {{ t('services.form.cancel') }}
                    </a>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
                    >
                        {{ form.processing ? t('services.form.saving') : t('services.form.save') }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Type selector bottom sheet -->
        <Teleport to="body">
            <div v-if="showSheet" class="fixed inset-0 z-40 bg-black/40" @click="showSheet = false; search = ''" />
            <div v-if="showSheet" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-4 pb-8 max-h-[75vh] flex flex-col shadow-xl">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="font-semibold text-slate-900">{{ t('services.form.type') }}</h2>
                        <button @click="showSheet = false; search = ''" class="text-slate-400 text-lg leading-none">✕</button>
                    </div>

                    <!-- Search -->
                    <input
                        v-model="search"
                        type="search"
                        :placeholder="t('services.type_search')"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-3"
                    />

                    <!-- List -->
                    <div class="overflow-y-auto flex-1 space-y-1 pb-2">

                        <!-- Otro (always first, pre-selected) -->
                        <button
                            v-if="!search"
                            type="button"
                            @click="selectOther"
                            :class="[
                                'w-full text-left px-4 py-3 rounded-xl text-sm font-medium transition-colors',
                                selection.kind === 'other'
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-slate-50 hover:bg-slate-100 text-slate-700',
                            ]"
                        >
                            {{ t('services.type_other') }}
                        </button>

                        <!-- Personalizado (always second, shows text input inline) -->
                        <button
                            v-if="!search"
                            type="button"
                            @click="selectCustom"
                            :class="[
                                'w-full text-left px-4 py-3 rounded-xl text-sm font-medium transition-colors',
                                selection.kind === 'custom'
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-slate-50 hover:bg-slate-100 text-slate-700',
                            ]"
                        >
                            {{ t('services.type_custom') }}
                            <span class="text-xs opacity-60 ml-1">{{ t('services.custom_name_hint') }}</span>
                        </button>

                        <!-- Divider -->
                        <div v-if="!search && templates.length" class="px-1 py-1">
                            <div class="h-px bg-slate-200" />
                        </div>

                        <!-- Templates -->
                        <button
                            v-for="tpl in filteredTemplates"
                            :key="tpl.id"
                            type="button"
                            @click="selectTemplate(tpl)"
                            :class="[
                                'w-full text-left px-4 py-3 rounded-xl text-sm font-medium transition-colors',
                                selection.kind === 'template' && selection.template.id === tpl.id
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-slate-50 hover:bg-slate-100 text-slate-900',
                            ]"
                        >
                            {{ tpl.name }}
                            <span
                                v-if="selection.kind !== 'template' || selection.template.id !== tpl.id"
                                class="text-xs text-slate-400 ml-2"
                            >
                                {{ tpl.time.slice(0, 5) }}
                            </span>
                        </button>

                        <p v-if="search && !filteredTemplates.length" class="text-sm text-slate-400 text-center py-4">
                            {{ t('services.no_type_results') }}
                        </p>
                    </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
