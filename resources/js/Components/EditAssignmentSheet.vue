<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    open: Boolean,
    assignment: {
        type: Object,
        default: null,
    },
    roles: {
        type: Array,
        default: () => [],
    },
    processing: Boolean,
});

const emit = defineEmits(['close', 'save', 'delete']);

const manualName = ref('');
const roleId = ref(null);

function roleLabel(role) {
    if (!role) return '';
    return locale.value === 'en' ? role.name_en : role.name_es;
}

const isManual = computed(() => !!props.assignment?.is_manual);

function submitSave() {
    if (!props.assignment?.id || !roleId.value) return;
    if (isManual.value && !manualName.value.trim()) return;
    emit('save', {
        id: props.assignment.id,
        band_role_type_id: Number(roleId.value),
        manual_name: isManual.value ? manualName.value.trim() : null,
    });
}

function submitDelete() {
    if (!props.assignment?.id) return;
    emit('delete', props.assignment);
}

watch(() => props.assignment, (assignment) => {
    if (!assignment) return;
    manualName.value = assignment.manual_name ?? '';
    roleId.value = assignment.band_role_type_id ?? null;
}, { immediate: true });
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-40 bg-black/40" @click="emit('close')" />
        <div
            v-if="open && assignment"
            class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-4 pb-6 shadow-xl"
        >
            <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-900">{{ t('assignments.edit_title') }}</h2>
                <button @click="emit('close')" class="text-slate-500 text-lg leading-none">x</button>
            </div>

            <div class="space-y-3">
                <div v-if="isManual">
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">{{ t('assignments.manual_name_label') }}</label>
                    <input
                        v-model="manualName"
                        type="text"
                        maxlength="50"
                        class="w-full px-3 py-2.5 text-base rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>
                <div>
                    <label class="block text-[11px] font-medium text-slate-500 mb-1">{{ t('assignments.role_label') }}</label>
                    <select
                        v-model="roleId"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option :value="null">{{ t('assignments.role_placeholder') }}</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">{{ roleLabel(role) }}</option>
                    </select>
                </div>
            </div>

            <div class="flex gap-2 mt-4">
                <button
                    type="button"
                    @click="submitSave"
                    :disabled="processing || !roleId || (isManual && !manualName.trim())"
                    class="flex-1 py-2.5 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl disabled:opacity-40"
                >
                    {{ t('assignments.save_button') }}
                </button>
                <button
                    type="button"
                    @click="submitDelete"
                    :disabled="processing"
                    class="flex-1 py-2.5 text-sm font-semibold bg-red-50 hover:bg-red-100 text-red-600 rounded-xl border border-red-100 disabled:opacity-40"
                >
                    {{ t('services.delete') }}
                </button>
            </div>
        </div>
    </Teleport>
</template>
