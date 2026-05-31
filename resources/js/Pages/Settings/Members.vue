<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t, locale } = useI18n();
const page = usePage();
const auth = computed(() => page.props.auth);

const props = defineProps({
    members:     Array,
    role_types:  Array,
    visit_stats: Object,
});

// ── Band roles (Teclado, Bajo, etc.) ───────────────────────────────────────
const roleNameById = computed(() => {
    const map = {};
    (props.role_types ?? []).forEach(r => {
        map[r.id] = locale.value === 'es' ? r.name_es : r.name_en;
    });
    return map;
});

const allRoleNames = computed(() =>
    (props.role_types ?? []).map(r => locale.value === 'es' ? r.name_es : r.name_en)
);

function idForRoleName(name) {
    const r = (props.role_types ?? []).find(r =>
        (locale.value === 'es' ? r.name_es : r.name_en) === name
    );
    return r?.id ?? null;
}

const editingRolesFor = ref(null);
const draftRoleIds    = ref([]);          // Set-like array of role IDs
const roleSearch      = ref('');

function openRolesEditor(member) {
    editingRolesFor.value = member.id;
    draftRoleIds.value    = [...(member.role_ids ?? [])];
    roleSearch.value      = '';
}

function closeRolesEditor() {
    editingRolesFor.value = null;
    draftRoleIds.value    = [];
    roleSearch.value      = '';
}

function toggleRole(id) {
    if (draftRoleIds.value.includes(id)) {
        draftRoleIds.value = draftRoleIds.value.filter(x => x !== id);
    } else {
        draftRoleIds.value = [...draftRoleIds.value, id];
    }
}

const filteredRoleTypes = computed(() => {
    const q = roleSearch.value.trim().toLowerCase();
    if (!q) return props.role_types ?? [];
    return (props.role_types ?? []).filter(r => {
        return r.name_es.toLowerCase().includes(q) || r.name_en.toLowerCase().includes(q);
    });
});

function saveRoles() {
    const memberId = editingRolesFor.value;
    router.put(`/settings/members/${memberId}/roles`, { role_ids: draftRoleIds.value }, {
        preserveScroll: true,
        onSuccess: closeRolesEditor,
    });
}

const confirmRemoveId = ref(null);
const acting          = ref(false);

function askRemove(id) { confirmRemoveId.value = id; }
function cancelRemove() { confirmRemoveId.value = null; }

function confirmRemove() {
    if (!confirmRemoveId.value) return;
    acting.value = true;
    router.delete('/settings/members/' + confirmRemoveId.value, {
        preserveScroll: true,
        onFinish: () => { acting.value = false; confirmRemoveId.value = null; },
    });
}

function promote(id) {
    router.post('/settings/members/' + id + '/promote', {}, { preserveScroll: true });
}
function demote(id) {
    router.post('/settings/members/' + id + '/demote', {}, { preserveScroll: true });
}

const showResetVisits = ref(false);
function resetVisitors() {
    router.delete('/settings/visitors', {
        preserveScroll: true,
        onFinish: () => { showResetVisits.value = false; },
    });
}

function roleLabel(m) {
    if (m.is_creator)     return t('settings.members.role_creator');
    if (m.role === 'admin') return t('settings.members.role_admin');
    return t('settings.members.role_member');
}

function roleBadgeClass(m) {
    if (m.is_creator)       return 'text-violet-700 bg-violet-50';
    if (m.role === 'admin') return 'text-indigo-700 bg-indigo-50';
    return 'text-slate-600 bg-slate-100';
}
</script>

<template>
    <Head :title="t('settings.members.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-2xl mx-auto">

            <Link href="/settings" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-600 mb-4 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ t('settings.title') }}
            </Link>

            <h1 class="text-lg font-semibold text-slate-900 mb-5">{{ t('settings.members.title') }}</h1>

            <!-- Members list -->
            <div class="space-y-2 mb-6">
                <div
                    v-for="member in members"
                    :key="member.id"
                    class="bg-white rounded-xl border border-slate-200 px-4 py-3"
                >
                    <!-- Top row: avatar + name + role badges + actions -->
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-indigo-100 flex items-center justify-center text-sm font-bold text-indigo-600 shrink-0">
                            {{ member.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <p class="text-sm font-medium text-slate-900 truncate">{{ member.name }}</p>
                                <span :class="['text-[10px] font-semibold px-1.5 py-0.5 rounded-md shrink-0', roleBadgeClass(member)]">{{ roleLabel(member) }}</span>
                                <span v-if="member.is_you" class="text-[10px] font-semibold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded-md shrink-0">{{ t('settings.members.you') }}</span>
                            </div>
                            <p class="text-xs text-slate-500 truncate">{{ member.email }}</p>
                        </div>

                        <!-- Actions: only creator (you) acts on others -->
                        <template v-if="!member.is_you && !member.is_creator">
                            <button
                                v-if="member.role === 'member'"
                                @click="promote(member.id)"
                                class="px-2.5 py-1.5 text-[11px] font-semibold rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 transition-colors"
                                :title="t('settings.members.promote')"
                            >
                                {{ t('settings.members.promote') }}
                            </button>
                            <button
                                v-else
                                @click="demote(member.id)"
                                class="px-2.5 py-1.5 text-[11px] font-semibold rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition-colors"
                                :title="t('settings.members.demote')"
                            >
                                {{ t('settings.members.demote') }}
                            </button>
                            <button
                                @click="askRemove(member.id)"
                                class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 text-red-400 hover:bg-red-100 hover:text-red-600 transition-colors shrink-0"
                                :title="t('settings.members.remove')"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </template>
                    </div>

                    <!-- Band roles row -->
                    <div class="mt-2.5 pt-2.5 border-t border-slate-100 flex items-center gap-2 flex-wrap">
                        <!-- Display current roles as pills -->
                        <template v-if="member.role_ids?.length">
                            <span
                                v-for="rid in member.role_ids"
                                :key="rid"
                                class="text-[11px] font-medium text-violet-700 bg-violet-50 border border-violet-100 rounded-md px-2 py-0.5"
                            >
                                {{ roleNameById[rid] }}
                            </span>
                        </template>
                        <span v-else class="text-[11px] text-slate-400 italic">{{ t('settings.members.no_band_roles') }}</span>

                        <!-- Edit roles button: any admin (creator + delegated) can edit, including themselves -->
                        <button
                            v-if="auth.access === 'admin'"
                            @click="openRolesEditor(member)"
                            class="ml-auto text-[11px] font-semibold text-indigo-600 hover:text-indigo-700"
                        >
                            {{ member.role_ids?.length ? t('settings.members.edit_roles') : t('settings.members.add_roles') }}
                        </button>
                    </div>
                </div>

                <p v-if="!members.length" class="text-sm text-slate-500 text-center py-8">{{ t('settings.members.empty') }}</p>
            </div>

            <!-- Visitor stats -->
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-4 mb-4">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">{{ t('settings.members.visitors_title') }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ t('settings.members.visitors_hint') }}</p>
                    </div>
                    <button
                        v-if="visit_stats.total > 0"
                        @click="showResetVisits = true"
                        class="text-[11px] font-semibold text-slate-500 hover:text-red-600 transition-colors shrink-0"
                    >
                        {{ t('settings.members.visitors_reset') }}
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-3 mt-3">
                    <div class="bg-indigo-50 rounded-xl px-3 py-2.5">
                        <p class="text-[10px] font-semibold text-indigo-500 uppercase tracking-wide">{{ t('settings.members.visitors_total') }}</p>
                        <p class="text-xl font-bold text-indigo-700 mt-0.5">{{ visit_stats.total }}</p>
                    </div>
                    <div class="bg-slate-50 rounded-xl px-3 py-2.5">
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">{{ t('settings.members.visitors_30d') }}</p>
                        <p class="text-xl font-bold text-slate-800 mt-0.5">{{ visit_stats.last_30_days }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Remove confirmation -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="confirmRemoveId" class="fixed inset-0 z-40 bg-black/40" @click="cancelRemove" />
            </Transition>
            <Transition enter-active-class="transition duration-250 ease-out" enter-from-class="translate-y-full" enter-to-class="translate-y-0" leave-active-class="transition duration-200 ease-in" leave-from-class="translate-y-0" leave-to-class="translate-y-full">
                <div v-if="confirmRemoveId" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-3 pb-8 shadow-xl">
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
                    <div class="flex items-center gap-3 mb-2">
                        <div class="w-10 h-10 bg-red-50 rounded-full flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <h2 class="text-base font-semibold text-slate-900">{{ t('settings.members.remove_title') }}</h2>
                    </div>
                    <p class="text-sm text-slate-500 mb-4">{{ t('settings.members.remove_confirm') }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="cancelRemove" class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300">{{ t('settings.members.cancel') }}</button>
                        <button type="button" @click="confirmRemove" :disabled="acting" class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors">{{ acting ? t('settings.members.deleting') : t('settings.members.remove') }}</button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Edit band roles bottom sheet -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="editingRolesFor" class="fixed inset-0 z-40 bg-black/40" @click="closeRolesEditor" />
            </Transition>
            <Transition enter-active-class="transition duration-250 ease-out" enter-from-class="translate-y-full" enter-to-class="translate-y-0" leave-active-class="transition duration-200 ease-in" leave-from-class="translate-y-0" leave-to-class="translate-y-full">
                <div v-if="editingRolesFor" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl pt-4 pb-8 max-h-[85vh] flex flex-col shadow-xl">
                    <!-- Header -->
                    <div class="px-4">
                        <div class="flex items-center justify-between mb-1">
                            <h2 class="text-base font-semibold text-slate-900">{{ t('settings.members.roles_title') }}</h2>
                            <button @click="closeRolesEditor" class="text-slate-400 hover:text-slate-600 text-lg leading-none">✕</button>
                        </div>
                        <p class="text-xs text-slate-500 mb-3">{{ t('settings.members.roles_hint') }}</p>

                        <!-- Search input -->
                        <div class="relative mb-3">
                            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 110-16 8 8 0 010 16z" />
                            </svg>
                            <input
                                v-model="roleSearch"
                                type="text"
                                :placeholder="t('settings.members.search_role')"
                                class="w-full pl-9 pr-9 py-2.5 text-base rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <button
                                v-if="roleSearch"
                                @click="roleSearch = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-100"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide mb-1.5">
                            {{ roleSearch.trim() ? t('settings.members.search_results') : t('settings.members.roles_available') }}
                            <span class="text-slate-400 font-medium normal-case tracking-normal ml-1">· {{ draftRoleIds.length }} {{ t('settings.members.selected') }}</span>
                        </p>
                    </div>

                    <!-- Scrollable roles list -->
                    <div class="flex-1 overflow-y-auto px-4 min-h-0">
                        <div v-if="!filteredRoleTypes.length" class="text-center py-8 text-sm text-slate-400 italic">
                            {{ t('settings.members.no_role_matches') }}
                        </div>
                        <div v-else class="space-y-1">
                            <button
                                v-for="r in filteredRoleTypes"
                                :key="r.id"
                                type="button"
                                @click="toggleRole(r.id)"
                                :class="[
                                    'w-full flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-left transition-colors',
                                    draftRoleIds.includes(r.id)
                                        ? 'bg-indigo-50 hover:bg-indigo-100'
                                        : 'hover:bg-slate-50',
                                ]"
                            >
                                <span
                                    class="w-5 h-5 rounded-md border flex items-center justify-center shrink-0 transition-colors"
                                    :class="draftRoleIds.includes(r.id)
                                        ? 'bg-indigo-600 border-indigo-600'
                                        : 'border-slate-300 bg-white'"
                                >
                                    <svg v-if="draftRoleIds.includes(r.id)" class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                </span>
                                <span class="text-sm font-medium flex-1"
                                    :class="draftRoleIds.includes(r.id) ? 'text-indigo-700' : 'text-slate-800'">
                                    {{ locale === 'es' ? r.name_es : r.name_en }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="px-4 pt-3 border-t border-slate-100 flex gap-2 shrink-0">
                        <button type="button" @click="closeRolesEditor" class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300">{{ t('settings.members.cancel') }}</button>
                        <button type="button" @click="saveRoles" class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-colors">{{ t('settings.members.save_roles') }}</button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Reset visitors confirmation -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showResetVisits" class="fixed inset-0 z-40 bg-black/40" @click="showResetVisits = false" />
            </Transition>
            <Transition enter-active-class="transition duration-250 ease-out" enter-from-class="translate-y-full" enter-to-class="translate-y-0" leave-active-class="transition duration-200 ease-in" leave-from-class="translate-y-0" leave-to-class="translate-y-full">
                <div v-if="showResetVisits" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-3 pb-8 shadow-xl">
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
                    <h2 class="text-base font-semibold text-slate-900 mb-1">{{ t('settings.members.visitors_reset_title') }}</h2>
                    <p class="text-sm text-slate-500 mb-4">{{ t('settings.members.visitors_reset_confirm') }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="showResetVisits = false" class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300">{{ t('settings.members.cancel') }}</button>
                        <button type="button" @click="resetVisitors" class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white text-sm font-semibold rounded-xl transition-colors">{{ t('settings.members.visitors_reset') }}</button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
