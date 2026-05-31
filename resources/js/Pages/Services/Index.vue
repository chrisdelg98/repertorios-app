<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import MultiSelect from '@/Components/MultiSelect.vue';

const { t, locale } = useI18n();
const page = usePage();

const canWrite  = computed(() => !!page.props.auth?.can_write);
const isCreator = computed(() => !!page.props.auth?.is_creator);

const props = defineProps({
    services: Array,
});

function typeLabel(type) {
    return type === 'other' ? t('services.type_other') : type;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const datePart = dateStr.includes('T') ? dateStr.split('T')[0] : dateStr;
    const date = new Date(datePart + 'T00:00:00');
    return date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
}

// ── Filters ──────────────────────────────────────────────────────────────────
const showAll         = ref(false);       // false → only upcoming
const search          = ref('');
const selectedTypes   = ref([]);
const selectedYears   = ref([]);
const selectedMonths  = ref([]);

const todayKey = computed(() => {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    return d.toISOString().slice(0, 10); // YYYY-MM-DD
});

function yearOf(dateStr) { return dateStr.slice(0, 4); }      // YYYY
function monthOf(dateStr) { return dateStr.slice(5, 7); }     // MM

function monthName(mm) {
    const d = new Date(2000, parseInt(mm) - 1, 1);
    const label = d.toLocaleDateString(locale.value === 'es' ? 'es' : 'en', { month: 'long' });
    return label.charAt(0).toUpperCase() + label.slice(1);
}

const availableTypes = computed(() => {
    const set = new Set();
    props.services.forEach(s => set.add(s.type || 'other'));
    return [...set].sort();
});

const availableYears = computed(() => {
    const set = new Set();
    props.services.forEach(s => set.add(yearOf(s.date)));
    return [...set].sort().reverse(); // most recent first
});

const availableMonthCodes = computed(() => {
    const set = new Set();
    props.services.forEach(s => set.add(monthOf(s.date)));
    return [...set].sort();
});

const availableMonthLabels = computed(() => availableMonthCodes.value.map(monthName));

const filteredServices = computed(() => {
    const q = search.value.trim().toLowerCase();
    return props.services.filter(s => {
        if (!showAll.value && s.date < todayKey.value) return false;
        if (q && !typeLabel(s.type).toLowerCase().includes(q)) return false;
        if (selectedTypes.value.length && !selectedTypes.value.includes(s.type || 'other')) return false;
        if (selectedYears.value.length && !selectedYears.value.includes(yearOf(s.date))) return false;
        if (selectedMonths.value.length && !selectedMonths.value.includes(monthName(monthOf(s.date)))) return false;
        return true;
    });
});

const hasActiveFilters = computed(() =>
    !!(search.value || selectedTypes.value.length || selectedYears.value.length || selectedMonths.value.length)
);

function clearFilters() {
    search.value         = '';
    selectedTypes.value  = [];
    selectedYears.value  = [];
    selectedMonths.value = [];
}

// ── Kebab menu ────────────────────────────────────────────────────────────────
const openMenuId = ref(null);

function toggleMenu(id, event) {
    event?.stopPropagation();
    openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenuOnDocClick(e) {
    if (!e.target.closest('[data-card-menu]')) openMenuId.value = null;
}

onMounted(() => document.addEventListener('click', closeMenuOnDocClick));
onBeforeUnmount(() => document.removeEventListener('click', closeMenuOnDocClick));

// ── Delete confirmation modal ─────────────────────────────────────────────────
const confirmDeleteId = ref(null);
const deleting = ref(false);

function askDelete(id) {
    openMenuId.value = null;
    confirmDeleteId.value = id;
}

function cancelDelete() {
    confirmDeleteId.value = null;
}

function confirmDelete() {
    if (!confirmDeleteId.value) return;
    deleting.value = true;
    router.delete('/services/' + confirmDeleteId.value, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            confirmDeleteId.value = null;
        },
    });
}

// ── Share ─────────────────────────────────────────────────────────────────────
const showShareSheet = ref(false);
const sharing        = ref(false);
const sharingId      = ref(null);
const shareData      = ref(null);
const copied         = ref(false);

async function openShare(serviceId) {
    openMenuId.value = null;
    sharingId.value  = serviceId;
    sharing.value    = true;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const res  = await fetch('/services/' + serviceId + '/share', {
            method:  'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        });
        shareData.value      = await res.json();
        showShareSheet.value = true;
    } finally {
        sharing.value   = false;
        sharingId.value = null;
    }
}

function closeShare() {
    showShareSheet.value = false;
    shareData.value      = null;
    copied.value         = false;
}

async function toggleAllowJoin() {
    if (!shareData.value?.token) return;
    const next = !shareData.value.allow_join;
    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const res  = await fetch('/shared-links/' + shareData.value.token, {
            method:  'PUT',
            headers: {
                'X-CSRF-TOKEN':  csrf,
                'Content-Type':  'application/json',
                'Accept':        'application/json',
            },
            body: JSON.stringify({ allow_join: next }),
        });
        const data = await res.json();
        shareData.value = { ...shareData.value, allow_join: !!data.allow_join };
    } catch {}
}

async function copyLink() {
    try {
        await navigator.clipboard.writeText(shareData.value.url);
    } catch {
        const el = document.createElement('textarea');
        el.value = shareData.value.url;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2000);
}

// ── Duplicate sheet ───────────────────────────────────────────────────────────
const duplicateId = ref(null);
const dupForm = useForm({ date: '' });

function openDuplicate(id) {
    openMenuId.value = null;
    duplicateId.value = id;
    dupForm.date = '';
}

function closeDuplicate() {
    duplicateId.value = null;
    dupForm.reset();
}

function submitDuplicate() {
    dupForm.post('/services/' + duplicateId.value + '/duplicate', {
        onSuccess: closeDuplicate,
    });
}
</script>

<template>
    <Head :title="t('services.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 lg:max-w-3xl lg:mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-4 lg:mb-6">
                <h1 class="text-lg lg:text-2xl font-semibold lg:font-bold text-slate-900">{{ t('services.title') }}</h1>
                <Link
                    v-if="canWrite"
                    href="/services/create"
                    class="flex items-center gap-1.5 px-3 lg:px-4 py-1.5 lg:py-2 bg-indigo-600 text-white text-xs lg:text-sm font-semibold rounded-lg"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('services.create') }}
                </Link>
            </div>

            <!-- Filters -->
            <div v-if="services.length" class="mb-3 lg:mb-4 space-y-2">
                <!-- Toggle: Upcoming vs All -->
                <div class="inline-flex bg-slate-100 rounded-xl p-1">
                    <button
                        type="button"
                        @click="showAll = false"
                        :class="[
                            'px-3 py-1.5 text-xs font-semibold rounded-lg transition-all',
                            !showAll ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700',
                        ]"
                    >
                        {{ t('services.filter_upcoming') }}
                    </button>
                    <button
                        type="button"
                        @click="showAll = true"
                        :class="[
                            'px-3 py-1.5 text-xs font-semibold rounded-lg transition-all',
                            showAll ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-700',
                        ]"
                    >
                        {{ t('services.filter_all') }}
                    </button>
                </div>

                <!-- Detail filters: only when showing all -->
                <template v-if="showAll">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 110-16 8 8 0 010 16z" />
                        </svg>
                        <input
                            v-model="search"
                            type="text"
                            :placeholder="t('services.filter_search')"
                            class="w-full pl-9 pr-9 py-2.5 text-sm rounded-xl border border-slate-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <button
                            v-if="search"
                            @click="search = ''"
                            class="absolute right-2.5 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-100"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <MultiSelect
                            v-if="availableTypes.length"
                            v-model="selectedTypes"
                            :label="t('services.filter_types')"
                            :options="availableTypes"
                            :clear-label="t('services.filter_clear')"
                        />
                        <MultiSelect
                            v-if="availableYears.length"
                            v-model="selectedYears"
                            :label="t('services.filter_year')"
                            :options="availableYears"
                            :clear-label="t('services.filter_clear')"
                        />
                        <MultiSelect
                            v-if="availableMonthLabels.length"
                            v-model="selectedMonths"
                            :label="t('services.filter_months')"
                            :options="availableMonthLabels"
                            :clear-label="t('services.filter_clear')"
                        />
                    </div>

                    <div v-if="hasActiveFilters" class="flex items-center justify-between text-[11px] text-slate-500 pt-1">
                        <span>{{ t('services.filter_results', { shown: filteredServices.length, total: services.length }) }}</span>
                        <button
                            @click="clearFilters"
                            class="text-indigo-600 font-semibold hover:text-indigo-700"
                        >{{ t('services.filter_clear') }}</button>
                    </div>
                </template>
            </div>

            <!-- Empty state: no services at all -->
            <div v-if="!services.length" class="text-center py-16 text-slate-500">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-sm">{{ t('services.empty') }}</p>
            </div>

            <!-- Empty state: no matches -->
            <div v-else-if="!filteredServices.length" class="text-center py-16 text-slate-500">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 110-16 8 8 0 010 16z" />
                </svg>
                <p class="text-sm">{{ showAll ? t('services.filter_no_results') : t('services.filter_no_upcoming') }}</p>
                <button
                    v-if="!showAll"
                    @click="showAll = true"
                    class="mt-3 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
                >{{ t('services.filter_show_all') }}</button>
            </div>

            <!-- Services list -->
            <div v-else class="space-y-2 lg:space-y-0 lg:grid lg:grid-cols-2 lg:gap-3">
                <div
                    v-for="service in filteredServices"
                    :key="service.id"
                    class="group flex items-stretch rounded-xl border border-slate-200 bg-white hover:border-slate-300 hover:shadow-sm transition"
                >
                    <!-- Card content (tappable) -->
                    <Link
                        :href="'/services/' + service.id"
                        class="flex-1 flex items-center gap-3 px-3 py-3 text-left min-w-0 rounded-l-xl active:bg-slate-50 transition-colors"
                    >
                        <!-- Calendar anchor -->
                        <div class="w-10 h-10 bg-indigo-50 group-hover:bg-indigo-100 rounded-lg flex items-center justify-center shrink-0 transition-colors">
                            <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-slate-900 text-sm capitalize truncate">{{ typeLabel(service.type) }}</p>
                            <p class="text-xs text-slate-500 mt-0.5 truncate">
                                {{ formatDate(service.date) }}
                                <span v-if="service.time"> · {{ service.time.slice(0, 5) }}</span>
                            </p>
                        </div>

                        <!-- Songs count badge -->
                        <div class="flex items-center gap-1 text-[11px] font-semibold text-slate-600 bg-slate-100 group-hover:bg-slate-200 rounded-md px-2 py-1 shrink-0 transition-colors">
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                            </svg>
                            <span>{{ service.service_songs_count }}</span>
                            <span class="sr-only">{{ t('nav.songs') }}</span>
                        </div>
                    </Link>

                    <!-- Kebab menu trigger -->
                    <div class="relative flex items-stretch" data-card-menu>
                        <button
                            type="button"
                            @click="toggleMenu(service.id, $event)"
                            class="w-11 flex items-center justify-center text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-r-xl transition-colors"
                            :aria-label="t('services.actions')"
                            :aria-expanded="openMenuId === service.id"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                            </svg>
                        </button>

                        <!-- Dropdown -->
                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 scale-95"
                            enter-to-class="opacity-100 scale-100"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100 scale-100"
                            leave-to-class="opacity-0 scale-95"
                        >
                            <div
                                v-if="openMenuId === service.id"
                                class="absolute right-2 top-12 z-20 w-44 origin-top-right bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden"
                            >
                                <button
                                    type="button"
                                    @click.stop="openShare(service.id)"
                                    :disabled="sharing && sharingId === service.id"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors disabled:opacity-50"
                                >
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                                    </svg>
                                    {{ sharing && sharingId === service.id ? t('services.share_generating') : t('services.share') }}
                                </button>
                                <button
                                    v-if="canWrite"
                                    type="button"
                                    @click.stop="openDuplicate(service.id)"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors border-t border-slate-100"
                                >
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    {{ t('services.duplicate') }}
                                </button>
                                <button
                                    v-if="isCreator"
                                    type="button"
                                    @click.stop="askDelete(service.id)"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors border-t border-slate-100"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    {{ t('services.delete') }}
                                </button>
                            </div>
                        </Transition>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Delete confirmation bottom sheet ─────────────────────── -->
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
                        <h2 class="text-base font-semibold text-slate-900">{{ t('services.delete_title') }}</h2>
                    </div>

                    <p class="text-sm text-slate-500 mb-4">{{ t('services.delete_confirm') }}</p>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="cancelDelete"
                            class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300"
                        >
                            {{ t('services.cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="confirmDelete"
                            :disabled="deleting"
                            class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors"
                        >
                            {{ deleting ? t('services.deleting') : t('services.delete') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Share bottom sheet ──────────────────────────────────── -->
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
                    v-if="showShareSheet"
                    class="fixed inset-0 z-40 bg-black/40"
                    @click="closeShare"
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
                    v-if="showShareSheet"
                    class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-4 pb-8 shadow-xl"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-semibold text-slate-900">{{ t('services.share_title') }}</h2>
                        <button @click="closeShare" class="text-slate-500 text-lg leading-none">✕</button>
                    </div>

                    <div class="flex gap-2 mb-3">
                        <input
                            :value="shareData?.url"
                            readonly
                            class="flex-1 px-3 py-2.5 text-sm rounded-lg border border-slate-200 bg-slate-50 text-slate-600 truncate min-w-0"
                        />
                        <button
                            @click="copyLink"
                            :class="[
                                'shrink-0 px-3 py-2.5 text-sm font-medium rounded-lg border transition-colors',
                                copied
                                    ? 'bg-green-50 border-green-200 text-green-600'
                                    : 'border-slate-300 text-slate-600 hover:bg-slate-50',
                            ]"
                        >
                            {{ copied ? t('services.share_copied') : t('services.share_copy') }}
                        </button>
                    </div>

                    <!-- Allow join toggle -->
                    <button
                        type="button"
                        @click="toggleAllowJoin"
                        class="w-full flex items-center gap-3 px-3.5 py-3 mb-3 rounded-xl border transition-colors text-left"
                        :class="shareData?.allow_join
                            ? 'bg-indigo-50 border-indigo-200'
                            : 'bg-slate-50 border-slate-200 hover:border-slate-300'"
                    >
                        <span
                            class="relative w-9 h-5 rounded-full shrink-0 transition-colors"
                            :class="shareData?.allow_join ? 'bg-indigo-600' : 'bg-slate-300'"
                        >
                            <span
                                class="absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform"
                                :class="shareData?.allow_join ? 'translate-x-4' : 'translate-x-0'"
                            />
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-xs font-semibold" :class="shareData?.allow_join ? 'text-indigo-700' : 'text-slate-700'">
                                {{ t('services.share_allow_join_title') }}
                            </span>
                            <span class="block text-[11px] mt-0.5" :class="shareData?.allow_join ? 'text-indigo-600' : 'text-slate-500'">
                                {{ t('services.share_allow_join_hint') }}
                            </span>
                        </span>
                    </button>

                    <a
                        :href="shareData?.whatsapp_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="w-full flex items-center justify-center gap-2 py-3 bg-green-500 hover:bg-green-600 text-white font-semibold rounded-xl transition-colors"
                    >
                        <svg class="w-5 h-5 shrink-0" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        {{ t('services.share_whatsapp') }}
                    </a>
                </div>
            </Transition>
        </Teleport>

        <!-- ── Duplicate bottom sheet ──────────────────────────────── -->
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
                    v-if="duplicateId"
                    class="fixed inset-0 z-40 bg-black/40"
                    @click="closeDuplicate"
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
                    v-if="duplicateId"
                    class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-3 pb-8 shadow-xl"
                >
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
                    <h2 class="text-base font-semibold text-slate-900 mb-4">{{ t('services.duplicate_title') }}</h2>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">{{ t('services.duplicate_date_label') }}</label>
                    <input
                        v-model="dupForm.date"
                        type="date"
                        required
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-4"
                    />
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="closeDuplicate"
                            class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300"
                        >
                            {{ t('services.cancel') }}
                        </button>
                        <button
                            @click="submitDuplicate"
                            :disabled="dupForm.processing || !dupForm.date"
                            class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl transition-colors"
                        >
                            {{ t('services.duplicate') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
