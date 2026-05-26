<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();

const props = defineProps({
    templates: Array,
});

const days = computed(() => [
    t('settings.days.0'),
    t('settings.days.1'),
    t('settings.days.2'),
    t('settings.days.3'),
    t('settings.days.4'),
    t('settings.days.5'),
    t('settings.days.6'),
]);

// ── Add ────────────────────────────────────────────────────────
const showAddSheet = ref(false);
const addForm = useForm({ name: '', day_of_week: 0, time: '' });

function submitAdd() {
    addForm.post('/settings/schedule-templates', {
        onSuccess: () => {
            showAddSheet.value = false;
            addForm.reset();
        },
    });
}

// ── Edit ───────────────────────────────────────────────────────
const showEditSheet = ref(false);
const editingId = ref(null);
const editForm = useForm({ name: '', day_of_week: 0, time: '' });

function openEdit(tpl) {
    editingId.value = tpl.id;
    editForm.name = tpl.name;
    editForm.day_of_week = tpl.day_of_week;
    editForm.time = tpl.time.slice(0, 5);
    showEditSheet.value = true;
}

function submitEdit() {
    editForm.put('/settings/schedule-templates/' + editingId.value, {
        onSuccess: () => {
            showEditSheet.value = false;
            editingId.value = null;
        },
    });
}

// ── Delete ─────────────────────────────────────────────────────
function deleteTemplate(tpl) {
    if (confirm(t('settings.templates.delete_confirm'))) {
        router.delete('/settings/schedule-templates/' + tpl.id);
    }
}
</script>

<template>
    <Head :title="t('settings.templates.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">{{ t('settings.templates.title') }}</h1>
                    <p class="text-xs text-slate-400 mt-0.5">{{ t('settings.templates.subtitle') }}</p>
                </div>
                <button
                    @click="showAddSheet = true"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 text-white text-xs font-semibold rounded-lg"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('settings.templates.add') }}
                </button>
            </div>

            <!-- Empty state -->
            <div v-if="!templates.length" class="text-center py-16 text-slate-400">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-sm">{{ t('settings.templates.empty') }}</p>
            </div>

            <!-- Templates list -->
            <div v-else class="space-y-2">
                <div
                    v-for="tpl in templates"
                    :key="tpl.id"
                    class="flex items-center justify-between bg-white rounded-xl px-4 py-3.5 border border-slate-200"
                >
                    <div>
                        <p class="font-medium text-slate-900 text-sm">{{ tpl.name }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ days[tpl.day_of_week] }} · {{ tpl.time.slice(0, 5) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            @click="openEdit(tpl)"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button
                            @click="deleteTemplate(tpl)"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add sheet -->
        <Teleport to="body">
            <div v-if="showAddSheet" class="fixed inset-0 z-40 bg-black/40" @click="showAddSheet = false; addForm.reset()" />
            <div v-if="showAddSheet" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-4 pb-8 shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-semibold text-slate-900">{{ t('settings.templates.add') }}</h2>
                        <button @click="showAddSheet = false; addForm.reset()" class="text-slate-400 text-lg leading-none">✕</button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.templates.name_label') }}</label>
                            <input
                                v-model="addForm.name"
                                type="text"
                                maxlength="20"
                                autofocus
                                :placeholder="t('settings.templates.name_placeholder')"
                                class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p class="text-xs text-slate-400 text-right mt-0.5">{{ addForm.name.length }}/20</p>
                            <p v-if="addForm.errors.name" class="text-xs text-red-600 mt-0.5">{{ addForm.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.templates.day_label') }}</label>
                            <select
                                v-model="addForm.day_of_week"
                                class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option v-for="(name, i) in days" :key="i" :value="i">{{ name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.templates.time_label') }}</label>
                            <input
                                v-model="addForm.time"
                                type="time"
                                class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p v-if="addForm.errors.time" class="text-xs text-red-600 mt-0.5">{{ addForm.errors.time }}</p>
                        </div>
                    </div>

                    <button
                        @click="submitAdd"
                        :disabled="addForm.processing || !addForm.name.trim() || !addForm.time"
                        class="mt-5 w-full py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white font-semibold rounded-xl transition-colors"
                    >
                        {{ addForm.processing ? t('settings.templates.saving') : t('settings.templates.save') }}
                    </button>
            </div>
        </Teleport>

        <!-- Edit sheet -->
        <Teleport to="body">
            <div v-if="showEditSheet" class="fixed inset-0 z-40 bg-black/40" @click="showEditSheet = false" />
            <div v-if="showEditSheet" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-4 pb-8 shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-semibold text-slate-900">{{ t('settings.templates.edit') }}</h2>
                        <button @click="showEditSheet = false" class="text-slate-400 text-lg leading-none">✕</button>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.templates.name_label') }}</label>
                            <input
                                v-model="editForm.name"
                                type="text"
                                maxlength="20"
                                :placeholder="t('settings.templates.name_placeholder')"
                                class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p class="text-xs text-slate-400 text-right mt-0.5">{{ editForm.name.length }}/20</p>
                            <p v-if="editForm.errors.name" class="text-xs text-red-600 mt-0.5">{{ editForm.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.templates.day_label') }}</label>
                            <select
                                v-model="editForm.day_of_week"
                                class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <option v-for="(name, i) in days" :key="i" :value="i">{{ name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.templates.time_label') }}</label>
                            <input
                                v-model="editForm.time"
                                type="time"
                                class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p v-if="editForm.errors.time" class="text-xs text-red-600 mt-0.5">{{ editForm.errors.time }}</p>
                        </div>
                    </div>

                    <button
                        @click="submitEdit"
                        :disabled="editForm.processing || !editForm.name.trim() || !editForm.time"
                        class="mt-5 w-full py-3 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white font-semibold rounded-xl transition-colors"
                    >
                        {{ editForm.processing ? t('settings.templates.saving') : t('settings.templates.save') }}
                    </button>
            </div>
        </Teleport>
    </AppLayout>
</template>
