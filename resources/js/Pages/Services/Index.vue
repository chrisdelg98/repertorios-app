<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();

const props = defineProps({
    services: Object,
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
        <div class="px-4 lg:px-8 py-5 lg:py-10 lg:max-w-6xl lg:mx-auto">
            <!-- Header -->
            <div class="flex items-center justify-between mb-4 lg:mb-6">
                <h1 class="text-lg lg:text-2xl font-semibold lg:font-bold text-slate-900">{{ t('services.title') }}</h1>
                <Link
                    href="/services/create"
                    class="flex items-center gap-1.5 px-3 lg:px-4 py-1.5 lg:py-2 bg-indigo-600 text-white text-xs lg:text-sm font-semibold rounded-lg"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('services.create') }}
                </Link>
            </div>

            <!-- Empty state -->
            <div v-if="!services.data.length" class="text-center py-16 text-slate-400">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="text-sm">{{ t('services.empty') }}</p>
            </div>

            <!-- Services list -->
            <div v-else class="space-y-2 lg:space-y-0 lg:grid lg:grid-cols-2 lg:gap-3">
                <div
                    v-for="service in services.data"
                    :key="service.id"
                    class="flex items-stretch rounded-xl border border-slate-200 bg-white"
                >
                    <!-- Card content (tappable) -->
                    <Link
                        :href="'/services/' + service.id"
                        class="flex-1 flex items-center justify-between px-4 py-3.5 text-left min-w-0 transition-colors hover:bg-slate-50 active:bg-slate-100 rounded-l-xl"
                    >
                        <div class="min-w-0">
                            <p class="font-medium text-slate-900 text-sm capitalize">{{ typeLabel(service.type) }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                {{ formatDate(service.date) }}
                                <span v-if="service.time"> · {{ service.time.slice(0, 5) }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 ml-3">
                            <span class="text-xs text-slate-400">
                                {{ service.service_songs_count }}
                                <span class="sr-only">{{ t('nav.songs') }}</span>
                            </span>
                        </div>
                    </Link>

                    <!-- Kebab menu trigger -->
                    <div class="relative flex items-stretch" data-card-menu>
                        <button
                            type="button"
                            @click="toggleMenu(service.id, $event)"
                            class="w-11 flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-50 rounded-r-xl transition-colors"
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
                                    @click.stop="openDuplicate(service.id)"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors"
                                >
                                    <svg class="w-4 h-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                    </svg>
                                    {{ t('services.duplicate') }}
                                </button>
                                <button
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

            <!-- Pagination -->
            <div v-if="services.last_page > 1" class="flex justify-center gap-2 mt-6">
                <Link
                    v-for="link in services.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    :class="[
                        'px-3 py-1.5 text-xs rounded-lg border',
                        link.active
                            ? 'bg-indigo-600 text-white border-indigo-600'
                            : 'border-slate-200 text-slate-600',
                        !link.url && 'opacity-40 pointer-events-none',
                    ]"
                />
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
