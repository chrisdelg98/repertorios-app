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
const confirmDeleteId = ref(null);
const deleting = ref(false);

function askDelete(tpl) {
    confirmDeleteId.value = tpl.id;
}

function cancelDelete() {
    confirmDeleteId.value = null;
}

function confirmDelete() {
    if (!confirmDeleteId.value) return;
    deleting.value = true;
    router.delete('/settings/schedule-templates/' + confirmDeleteId.value, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            confirmDeleteId.value = null;
        },
    });
}
</script>

<template>
    <Head :title="t('settings.templates.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h1 class="text-lg font-semibold text-slate-900">{{ t('settings.templates.title') }}</h1>
                    <p class="text-xs text-slate-500 mt-0.5">{{ t('settings.templates.subtitle') }}</p>
                </div>
                <button
                    @click="showAddSheet = true"
                    class="flex items-center gap-1.5 px-3 lg:px-4 py-1.5 lg:py-2 bg-indigo-600 text-white text-xs lg:text-sm font-semibold rounded-lg"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('settings.templates.add') }}
                </button>
            </div>

            <!-- Empty state -->
            <div v-if="!templates.length" class="text-center py-16 text-slate-500">
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
                        <p class="text-xs text-slate-500 mt-0.5">
                            {{ days[tpl.day_of_week] }} · {{ tpl.time.slice(0, 5) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button
                            @click="openEdit(tpl)"
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-slate-100 text-slate-500 hover:bg-indigo-50 hover:text-indigo-600 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125" />
                            </svg>
                        </button>
                        <button
                            @click="askDelete(tpl)"
                            class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete confirmation bottom sheet -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="confirmDeleteId"
                    class="fixed inset-0 z-40 bg-black/40"
                    @click="cancelDelete"
                />
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
                    v-if="confirmDeleteId"
                    class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-3 pb-8 shadow-xl"
                >
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />

                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-red-50 rounded-full flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h2 class="text-base font-semibold text-slate-900">{{ t('settings.templates.delete_title') }}</h2>
                    </div>

                    <p class="text-sm text-slate-500 mb-4">{{ t('settings.templates.delete_confirm') }}</p>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="cancelDelete"
                            class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300"
                        >
                            {{ t('settings.templates.cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="confirmDelete"
                            :disabled="deleting"
                            class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors"
                        >
                            {{ deleting ? t('settings.templates.deleting') : t('settings.templates.delete') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Add sheet -->
        <Teleport to="body">
            <div v-if="showAddSheet" class="fixed inset-0 z-40 bg-black/40" @click="showAddSheet = false; addForm.reset()" />
            <div v-if="showAddSheet" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-4 pb-8 shadow-xl">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-semibold text-slate-900">{{ t('settings.templates.add') }}</h2>
                        <button @click="showAddSheet = false; addForm.reset()" class="text-slate-500 text-lg leading-none">✕</button>
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
                            <p class="text-xs text-slate-500 text-right mt-0.5">{{ addForm.name.length }}/20</p>
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
                        <button @click="showEditSheet = false" class="text-slate-500 text-lg leading-none">✕</button>
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
                            <p class="text-xs text-slate-500 text-right mt-0.5">{{ editForm.name.length }}/20</p>
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
