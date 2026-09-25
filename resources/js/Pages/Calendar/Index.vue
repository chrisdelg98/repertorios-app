<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import CalendarEntrySheet from '@/Components/CalendarEntrySheet.vue';
import { serviceColor } from '@/Constants/serviceColors';

const props = defineProps({
    month: { type: String, required: true },     // YYYY-MM
    from: { type: String, required: true },      // first cell of the grid
    to: { type: String, required: true },
    entries: { type: Array, default: () => [] },
    can_write: { type: Boolean, default: false },
});

const { t, locale } = useI18n();

const selectedDate = ref(todayKey());
const sheetOpen = ref(false);
const editing = ref(null);

function todayKey() {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
}

function parseKey(key) {
    const [y, m, d] = key.split('-').map(Number);
    return new Date(y, m - 1, d);
}

function keyOf(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/** Every cell of the grid, six weeks at most, Sunday to Saturday. */
const days = computed(() => {
    const start = parseKey(props.from);
    const end = parseKey(props.to);
    const out = [];

    for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
        const key = keyOf(d);

        out.push({
            key,
            day: d.getDate(),
            inMonth: key.startsWith(props.month),
            isToday: key === todayKey(),
            entries: props.entries.filter(e => e.date.startsWith(key)),
        });
    }

    return out;
});

const weekdays = computed(() => {
    // Built from a known Sunday so the names follow the app's language.
    const base = new Date(2026, 0, 4); // a Sunday
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(base);
        d.setDate(base.getDate() + i);
        return d.toLocaleDateString(locale.value === 'es' ? 'es' : 'en', { weekday: 'narrow' });
    });
});

const monthLabel = computed(() => {
    const label = parseKey(props.month + '-01')
        .toLocaleDateString(locale.value === 'es' ? 'es' : 'en', { month: 'long', year: 'numeric' });

    return label.charAt(0).toUpperCase() + label.slice(1);
});

const selectedEntries = computed(() =>
    props.entries.filter(e => e.date.startsWith(selectedDate.value))
);

const selectedLabel = computed(() =>
    parseKey(selectedDate.value).toLocaleDateString(locale.value === 'es' ? 'es' : 'en', {
        weekday: 'long', day: 'numeric', month: 'long',
    })
);

function shiftMonth(delta) {
    const [y, m] = props.month.split('-').map(Number);
    const d = new Date(y, m - 1 + delta, 1);

    router.get('/calendar', { month: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}` }, {
        preserveState: false,
        preserveScroll: true,
    });
}

/** Services keep their own colour; the rest read by kind. */
function dotClass(entry) {
    if (entry.kind === 'service') return serviceColor(entry.color).swatch;

    return {
        rehearsal: 'bg-sky-500',
        meeting: 'bg-amber-500',
        other: 'bg-slate-400',
    }[entry.kind] ?? 'bg-slate-400';
}

function openNew() {
    editing.value = null;
    sheetOpen.value = true;
}

function openEdit(entry) {
    // A service is edited where it lives, with its setlist and its team.
    if (entry.kind === 'service') {
        router.visit(`/services/${entry.id}`);
        return;
    }

    editing.value = entry;
    sheetOpen.value = true;
}
</script>

<template>
    <Head :title="t('calendar.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-3xl mx-auto">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h1 class="text-lg font-semibold text-slate-900">{{ t('calendar.title') }}</h1>

                <button
                    v-if="can_write"
                    type="button"
                    @click="openNew"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gradient-to-br from-indigo-600 to-violet-600 rounded-lg shadow-sm shadow-indigo-200 active:scale-[0.98] transition"
                >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('calendar.add') }}
                </button>
            </div>

            <!-- Month -->
            <div class="bg-white rounded-2xl border border-slate-200 p-3 lg:p-4">
                <div class="flex items-center justify-between mb-3">
                    <button
                        type="button"
                        @click="shiftMonth(-1)"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        :aria-label="t('calendar.previous_month')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <p class="text-sm font-semibold text-slate-900">{{ monthLabel }}</p>

                    <button
                        type="button"
                        @click="shiftMonth(1)"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100 transition-colors"
                        :aria-label="t('calendar.next_month')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <div class="grid grid-cols-7 gap-1 mb-1">
                    <p
                        v-for="(name, i) in weekdays"
                        :key="i"
                        class="text-2xs font-semibold text-slate-500 uppercase text-center py-1"
                    >{{ name }}</p>
                </div>

                <div class="grid grid-cols-7 gap-1">
                    <button
                        v-for="day in days"
                        :key="day.key"
                        type="button"
                        @click="selectedDate = day.key"
                        class="aspect-square rounded-lg flex flex-col items-center justify-center gap-1 transition-colors"
                        :class="[
                            selectedDate === day.key
                                ? 'bg-indigo-600 text-white'
                                : day.inMonth ? 'text-slate-900 hover:bg-slate-100' : 'text-slate-300 hover:bg-slate-50',
                            day.isToday && selectedDate !== day.key ? 'ring-1 ring-indigo-400' : '',
                        ]"
                    >
                        <span class="text-xs font-semibold leading-none">{{ day.day }}</span>

                        <!-- Three dots at most: past that the day list is the answer -->
                        <span v-if="day.entries.length" class="flex gap-0.5 h-1.5">
                            <span
                                v-for="entry in day.entries.slice(0, 3)"
                                :key="entry.id"
                                class="w-1.5 h-1.5 rounded-full"
                                :class="selectedDate === day.key ? 'bg-white/90' : dotClass(entry)"
                            />
                        </span>
                        <span v-else class="h-1.5" />
                    </button>
                </div>
            </div>

            <!-- The selected day -->
            <div class="mt-4">
                <p class="text-2xs font-semibold text-slate-600 uppercase tracking-wide px-1 mb-2">
                    {{ selectedLabel }}
                </p>

                <div v-if="selectedEntries.length" class="space-y-2">
                    <button
                        v-for="entry in selectedEntries"
                        :key="entry.id"
                        type="button"
                        @click="openEdit(entry)"
                        class="w-full flex items-center gap-3 bg-white rounded-xl border border-slate-200 px-3 py-3 text-left hover:border-slate-300 transition-colors"
                    >
                        <span class="w-1.5 h-10 rounded-full shrink-0" :class="dotClass(entry)" />

                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate leading-tight">{{ entry.name }}</p>
                            <p class="text-xs font-medium text-slate-600 mt-0.5 truncate">
                                {{ t('calendar.kind_' + entry.kind) }}
                                <span v-if="entry.time"> · {{ entry.time }}<span v-if="entry.end_time">–{{ entry.end_time }}</span></span>
                                <span v-if="entry.kind === 'service' && entry.songs"> · {{ t('dashboard.songs_count', entry.songs, { count: entry.songs }) }}</span>
                            </p>
                        </div>

                        <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <button
                    v-else-if="can_write"
                    type="button"
                    @click="openNew"
                    class="w-full py-6 text-sm font-medium text-slate-600 rounded-xl border-2 border-dashed border-slate-200 hover:border-indigo-300 hover:text-indigo-600 transition-colors"
                >
                    {{ t('calendar.empty_day') }}
                </button>

                <p v-else class="text-sm text-slate-600 text-center py-6">{{ t('calendar.empty_day_readonly') }}</p>
            </div>
        </div>

        <CalendarEntrySheet
            :open="sheetOpen"
            :entry="editing"
            :date="selectedDate"
            @close="sheetOpen = false"
        />
    </AppLayout>
</template>
