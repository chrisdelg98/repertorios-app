<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t, locale } = useI18n();

const props = defineProps({
    open: Boolean,
    members: {
        type: Array,
        default: () => [],
    },
    roles: {
        type: Array,
        default: () => [],
    },
    assignments: {
        type: Array,
        default: () => [],
    },
    processing: Boolean,
});

const emit = defineEmits(['close', 'add-registered', 'add-manual']);

const query = ref('');
const selectedMemberId = ref(null);
const selectedRoleId = ref(null);
const manualName = ref('');
const manualRoleId = ref(null);

const filteredMembers = computed(() => {
    const q = query.value.trim().toLowerCase();
    if (!q) return props.members;
    return props.members.filter((member) => member.name.toLowerCase().includes(q));
});

const selectedMember = computed(() =>
    props.members.find((member) => member.id === selectedMemberId.value) ?? null
);

const selectedMemberRoles = computed(() => {
    if (!selectedMember.value) return [];
    return selectedMember.value.roles?.length ? selectedMember.value.roles : props.roles;
});

function roleLabel(role) {
    if (!role) return '';
    return locale.value === 'en' ? role.name_en : role.name_es;
}

function isUserRoleAssigned(userId, roleId) {
    if (!roleId) return false;
    const normalizedRoleId = Number(roleId);
    return props.assignments.some((assignment) =>
        assignment.user_id === userId && assignment.band_role_type_id === normalizedRoleId
    );
}

function openRolePicker(member) {
    selectedMemberId.value = member.id;
    selectedRoleId.value = null;
}

function submitRegistered() {
    if (!selectedMemberId.value || !selectedRoleId.value) return;
    emit('add-registered', {
        user_id: selectedMemberId.value,
        band_role_type_id: selectedRoleId.value,
    });
}

function submitManual() {
    if (!manualName.value.trim() || !manualRoleId.value) return;
    emit('add-manual', {
        manual_name: manualName.value.trim(),
        band_role_type_id: manualRoleId.value,
    });
}

watch(() => props.open, (isOpen) => {
    if (!isOpen) return;
    query.value = '';
    selectedMemberId.value = null;
    selectedRoleId.value = null;
    manualName.value = '';
    manualRoleId.value = null;
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-40 bg-black/40" @click="emit('close')" />
        <div
            v-if="open"
            class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-4 pb-6 max-h-[80vh] flex flex-col shadow-xl"
        >
            <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
            <div class="flex items-center justify-between mb-3">
                <h2 class="font-semibold text-slate-900">{{ t('assignments.add_sheet_title') }}</h2>
                <button @click="emit('close')" class="text-slate-500 text-lg leading-none">x</button>
            </div>

            <input
                v-model="query"
                type="search"
                :placeholder="t('assignments.search_placeholder')"
                class="w-full px-3 py-2.5 text-base rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-3"
            />

            <div class="overflow-y-auto flex-1 pr-0.5">
                <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide px-1 mb-1.5">
                    {{ t('assignments.registered_members') }}
                </p>

                <div v-if="filteredMembers.length" class="space-y-1.5 mb-4">
                    <button
                        v-for="member in filteredMembers"
                        :key="member.id"
                        type="button"
                        @click="openRolePicker(member)"
                        class="w-full min-h-11 px-3 py-2 text-left rounded-lg border border-slate-200 hover:border-indigo-300 transition-colors"
                    >
                        <p class="text-sm font-medium text-slate-900">{{ member.name }}</p>
                        <p v-if="member.roles?.length" class="text-[11px] text-slate-500 mt-0.5">
                            {{ t('assignments.role_suggested', { role: roleLabel(member.roles[0]) }) }}
                        </p>
                    </button>
                </div>

                <div v-if="selectedMember" class="border border-indigo-200 bg-indigo-50/40 rounded-xl p-3 mb-4">
                    <p class="text-xs text-slate-700 mb-2">
                        {{ t('assignments.confirm_role_title', { name: selectedMember.name }) }}
                    </p>

                    <select
                        v-model="selectedRoleId"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    >
                        <option :value="null">{{ t('assignments.role_placeholder') }}</option>
                        <option
                            v-for="role in selectedMemberRoles"
                            :key="role.id"
                            :value="role.id"
                            :disabled="isUserRoleAssigned(selectedMember.id, role.id)"
                        >
                            {{ roleLabel(role) }}
                            {{ isUserRoleAssigned(selectedMember.id, role.id) ? ' (locked)' : '' }}
                        </option>
                    </select>

                    <button
                        type="button"
                        @click="submitRegistered"
                        :disabled="processing || !selectedRoleId || isUserRoleAssigned(selectedMember.id, selectedRoleId)"
                        class="w-full mt-2 py-2.5 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-40"
                    >
                        {{ t('assignments.add_button') }}
                    </button>
                </div>

                <div class="flex items-center gap-3 my-3">
                    <div class="flex-1 h-px bg-slate-200" />
                    <span class="text-[10px] font-semibold text-slate-500 uppercase tracking-wider">{{ t('services.or') }}</span>
                    <div class="flex-1 h-px bg-slate-200" />
                </div>

                <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide px-1 mb-1.5">
                    {{ t('assignments.add_manually') }}
                </p>

                <div class="border border-slate-200 rounded-xl p-3 space-y-2.5">
                    <div>
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
                            v-model="manualRoleId"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <option :value="null">{{ t('assignments.role_placeholder') }}</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">{{ roleLabel(role) }}</option>
                        </select>
                    </div>
                    <button
                        type="button"
                        @click="submitManual"
                        :disabled="processing || !manualName.trim() || !manualRoleId"
                        class="w-full py-2.5 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg disabled:opacity-40"
                    >
                        {{ t('assignments.add_button') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
