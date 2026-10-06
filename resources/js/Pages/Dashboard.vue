<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { serviceColor } from '@/Constants/serviceColors';
import NotificationsSheet from '@/Components/NotificationsSheet.vue';

/**
 * The first screen, built around one question.
 *
 * It is not "when is Sunday" but "what do I have to play", so the hero carries
 * the roles, the song count and the top of the setlist rather than a date.
 *
 * Services and calendar entries are one agenda. As two blocks the reader had
 * to interleave them to work out what came next; a band lives one week at a
 * time, not one entity at a time.
 *
 * The shortcuts are a strip on purpose. Every destination they offer is also
 * in the sidebar, in the bottom bar or under the floating button, so spending
 * a third of the page repeating them cost the best space for the least new
 * information.
 */

const { t, locale } = useI18n();
const page = usePage();

const props = defineProps({
    next_service: { type: Object, default: null },
    agenda: { type: Object, default: () => ({ services: [], entries: [] }) },
    stats: Object,
});

const auth = computed(() => page.props.auth);
const canWrite = computed(() => !!auth.value?.can_write);
const isCreator = computed(() => !!auth.value?.is_creator);
const audioEnabled = computed(() => !!page.props.audio?.enabled);
const isSessionMember = computed(() => auth.value?.access === 'member' && !auth.value?.user);

// --- Notifications prompt ---
// Two ways out, and they mean different things. Closing it (backdrop) is not
// an answer, so it comes back next time the dashboard loads. Pressing
// "Not now" IS an answer, and buys silence until tomorrow — long enough not
// to nag, short enough that someone who changes their mind is not stranded.
const SNOOZE_KEY = 'push_prompt_snoozed_until';
const pushSheetOpen = ref(false);
let pushTimer = null;

function today() {
    return new Date().toISOString().slice(0, 10); // YYYY-MM-DD, local enough
}

function snoozedToday() {
    try {
        return localStorage.getItem(SNOOZE_KEY) === today();
    } catch {
        return false; // private mode: better to ask than to stay silent forever
    }
}

function shouldAskAboutPush() {
    if (!auth.value?.user) return false;            // guests have no account to notify
    if (auth.value?.show_welcome) return false;     // never two modals at once
    if (typeof window === 'undefined') return false;
    if (!('Notification' in window)) return false;
    if (Notification.permission === 'granted') return false;  // already sorted
    if (Notification.permission === 'denied') return false;   // asking cannot help

    return !snoozedToday();
}

/** Backdrop: no answer given, so it will ask again next time. */
function closePushPrompt() {
    pushSheetOpen.value = false;
}

/** "Not now": an answer. Quiet until tomorrow. */
function snoozePushPrompt() {
    pushSheetOpen.value = false;
    try { localStorage.setItem(SNOOZE_KEY, today()); } catch { /* private mode */ }
}

onMounted(() => {
    if (shouldAskAboutPush()) {
        pushTimer = setTimeout(() => { pushSheetOpen.value = true; }, 2500);
    }
});

onBeforeUnmount(() => clearTimeout(pushTimer));

const greeting = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return t('dashboard.greeting_morning');
    if (h < 18) return t('dashboard.greeting_afternoon');
    return t('dashboard.greeting_evening');
});

const displayName = computed(() => {
    const name = auth.value.user?.name || auth.value.band?.name || '';
    return name.split(' ')[0];
});

function asDate(dateStr) {
    return new Date(dateStr + 'T00:00:00');
}

function formatLongDate(dateStr) {
    return asDate(dateStr).toLocaleDateString(locale.value === 'es' ? 'es' : 'en', {
        weekday: 'long', month: 'long', day: 'numeric',
    });
}

function dayNumber(dateStr) {
    return asDate(dateStr).getDate();
}

function weekdayShort(dateStr) {
    return asDate(dateStr)
        .toLocaleDateString(locale.value === 'es' ? 'es' : 'en', { weekday: 'short' })
        .replace('.', '')
        .slice(0, 3);
}

function monthShort(dateStr) {
    return asDate(dateStr)
        .toLocaleDateString(locale.value === 'es' ? 'es' : 'en', { month: 'short' })
        .replace('.', '');
}

function daysFromNow(dateStr) {
    const target = asDate(dateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const diff = Math.round((target - today) / (1000 * 60 * 60 * 24));

    if (diff === 0) return t('dashboard.today');
    if (diff === 1) return t('dashboard.tomorrow');
    return t('dashboard.in_days', { count: diff });
}

function typeLabel(type) {
    return type === 'other' ? t('services.type_other') : type;
}

function roleLabel(role) {
    if (!role) return '';
    return locale.value === 'en' ? role.name_en : role.name_es;
}

const myRolesText = computed(() =>
    (props.next_service?.my_roles ?? []).map(roleLabel).filter(Boolean).join(', ')
);

/** Whether there is anything at all to put under this heading. */
const hasAgenda = computed(() =>
    !!(props.agenda?.services?.length || props.agenda?.entries?.length)
);

/** The rule only earns its place when it has something on both sides. */
const showAgendaRule = computed(() =>
    !!(props.agenda?.services?.length && props.agenda?.entries?.length)
);

/** A service keeps its own colour; everything else reads by kind. */
function entryAccent(entry) {
    if (entry.kind === 'service') return serviceColor(entry.color).swatch;

    return {
        rehearsal: 'bg-sky-500',
        meeting: 'bg-amber-500',
        other: 'bg-slate-400',
    }[entry.kind] ?? 'bg-slate-400';
}

function entryHref(entry) {
    return entry.kind === 'service'
        ? `/services/${entry.id}`
        : `/calendar?month=${entry.date.slice(0, 7)}`;
}

/**
 * Two counts, and the size of the team is not one of them.
 *
 * It barely moves, and knowing there are eight of you suggests nothing to do
 * about it. These two grow as the band works and double as a way into the
 * screens that hold them, which is the whole reason they are here.
 *
 * The colour is the one each section uses elsewhere in the app, shown as a dot
 * because that is the smallest mark that still tells them apart.
 */
const counters = computed(() => [
    {
        href: '/services', value: props.stats.services_total,
        label: t('nav.services'), icon: 'calendar', tint: 'text-indigo-500',
    },
    {
        href: '/songs', value: props.stats.songs,
        label: t('nav.songs'), icon: 'music', tint: 'text-violet-500',
    },
]);

const audioPercent = computed(() => {
    const quota = props.stats.audio_quota_mb || 1;
    return Math.min(100, Math.round((props.stats.audio_used_mb / quota) * 100));
});

/** An odd one out takes the whole row rather than half of it. */
const lastSpansRow = computed(() => shortcuts.value.length % 2 === 1);

/** Secondary, so a strip: everything here is also in the navigation. */
const shortcuts = computed(() => {
    // Each chip takes the colour its section uses elsewhere. Grey on white
    // read as decoration; a tint reads as something that does something.
    const list = [
        { href: '/calendar', label: t('calendar.title'), icon: 'calendar', chip: 'bg-indigo-100', tint: 'text-indigo-600' },
        { href: '/songs', label: t('dashboard.browse_library'), icon: 'music', chip: 'bg-violet-100', tint: 'text-violet-600' },
    ];

    if (isCreator.value) {
        list.push({
            href: '/settings/members', label: t('settings.members.title'),
            icon: 'members', chip: 'bg-emerald-100', tint: 'text-emerald-600',
        });
    }

    if (audioEnabled.value && canWrite.value) {
        list.push({
            href: '/audio', label: t('nav.audio'),
            icon: 'audio', chip: 'bg-sky-100', tint: 'text-sky-600',
        });
    }

    return list;
});
</script>

<template>
    <Head :title="t('nav.home')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-6 lg:py-10 space-y-5 lg:space-y-6 lg:max-w-3xl lg:mx-auto">

            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">
                <span class="font-medium text-slate-600">{{ greeting }},</span> {{ displayName }}
            </h1>

            <!-- Subtle upgrade prompt for session-only members (entered via invite link, no account) -->
            <div
                v-if="isSessionMember"
                class="bg-white rounded-xl border border-indigo-100 p-4"
            >
                <div class="flex items-start gap-3 mb-3">
                    <div class="w-9 h-9 bg-indigo-50 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 8v6M23 11h-6" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900">{{ t('dashboard.upgrade_title') }}</p>
                        <p class="text-xs text-slate-600 mt-0.5">{{ t('dashboard.upgrade_subtitle') }}</p>
                    </div>
                </div>

                <ul class="space-y-1.5 mb-3">
                    <li v-for="benefit in [
                        t('dashboard.upgrade_benefit_1'),
                        t('dashboard.upgrade_benefit_2'),
                        t('dashboard.upgrade_benefit_3'),
                    ]" :key="benefit" class="flex items-start gap-2 text-xs text-slate-600">
                        <svg class="w-3.5 h-3.5 text-indigo-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ benefit }}</span>
                    </li>
                </ul>

                <Link
                    href="/upgrade"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
                >
                    {{ t('dashboard.upgrade_cta') }}
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
                <p class="text-2xs text-slate-500 mt-2 italic">{{ t('dashboard.upgrade_optional') }}</p>
            </div>

            <!-- The hero carries the answer, not just the date. What someone
                 wants from this screen is what they have to play. -->
            <Link
                v-if="next_service"
                :href="`/services/${next_service.id}`"
                class="block rounded-2xl overflow-hidden shadow-md transition hover:shadow-lg active:scale-[0.995]"
                :class="serviceColor(next_service.color).shadow"
            >
                <div class="bg-gradient-to-br p-4 sm:p-5 lg:p-6 text-white" :class="serviceColor(next_service.color).gradient">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-2xs font-semibold text-white/75 uppercase tracking-widest">
                                {{ t('dashboard.next_service') }}
                            </p>
                            <h2 class="text-lg sm:text-xl lg:text-2xl font-bold mt-1.5 capitalize leading-tight line-clamp-2">
                                {{ typeLabel(next_service.type) }}
                            </h2>
                            <p class="text-sm font-medium text-white/90 mt-1 capitalize">
                                {{ formatLongDate(next_service.date) }}
                                <span v-if="next_service.time"> · {{ next_service.time.slice(0, 5) }}</span>
                            </p>
                        </div>

                        <span class="shrink-0 text-2xs font-bold bg-white/20 text-white rounded-full px-3 py-1.5 backdrop-blur-sm">
                            {{ daysFromNow(next_service.date) }}
                        </span>
                    </div>

                    <!-- Your part in it, stated before anything else. -->
                    <div class="flex flex-wrap items-center gap-2 mt-5">
                        <span
                            v-if="myRolesText"
                            class="inline-flex items-center gap-1.5 text-xs font-bold bg-white text-slate-900 rounded-lg px-2.5 py-1.5"
                        >
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                            </svg>
                            {{ myRolesText }}
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center text-xs font-semibold bg-white/15 text-white/90 rounded-lg px-2.5 py-1.5"
                        >
                            {{ t('assignments.not_assigned') }}
                        </span>

                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold bg-white/15 text-white rounded-lg px-2.5 py-1.5">
                            {{ t('dashboard.songs_count', { count: next_service.song_count }) }}
                        </span>
                    </div>
                </div>

                <!-- The setlist as one line that runs out.
                     It was a strip you dragged sideways, which had no padding
                     to stop against at the far end and asked for a gesture to
                     read something you have to open the service for anyway.
                     The browser now decides how many names fit and ends them
                     with an ellipsis; how many there are is in the chip above. -->
                <div
                    v-if="next_service.song_names?.length"
                    class="bg-white/95 backdrop-blur px-4 lg:px-6 py-3"
                >
                    <!-- Spaced and greyed separators rather than a joined
                         string: HTML collapses repeated spaces, so the names
                         ran together as though they were one title. -->
                    <p class="text-xs font-medium text-slate-600 truncate">
                        <template v-for="(name, i) in next_service.song_names" :key="name">
                            <span v-if="i" class="text-slate-300 mx-2">·</span>{{ name }}
                        </template>
                    </p>
                </div>
            </Link>

            <!-- No services state (clickable only if user can create) -->
            <Link
                v-else-if="canWrite"
                href="/services/create"
                class="block bg-white rounded-2xl p-5 border border-dashed border-slate-300 text-center hover:border-indigo-300 transition-colors"
            >
                <div class="w-10 h-10 mx-auto bg-indigo-50 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-700">{{ t('dashboard.no_upcoming_title') }}</p>
                <p class="text-xs text-slate-600 mt-0.5">{{ t('dashboard.no_upcoming_body') }}</p>
            </Link>
            <div
                v-else
                class="block bg-white rounded-2xl p-5 border border-dashed border-slate-300 text-center"
            >
                <div class="w-10 h-10 mx-auto bg-slate-100 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <p class="text-sm font-medium text-slate-600">{{ t('dashboard.no_upcoming_title') }}</p>
                <p class="text-xs text-slate-600 mt-0.5">{{ t('dashboard.no_upcoming_readonly') }}</p>
            </div>

            <!-- One agenda. Services and the rest in the order they happen. -->
            <div v-if="hasAgenda">
                <div class="flex items-center justify-between gap-2 mb-2.5 px-1">
                    <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">
                        {{ t('dashboard.upcoming') }}
                    </p>
                    <Link href="/calendar" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                        {{ t('calendar.title') }} →
                    </Link>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="divide-y divide-slate-100">
                    <Link
                        v-for="entry in agenda.services"
                        :key="entry.kind + entry.id"
                        :href="entryHref(entry)"
                        class="flex items-center gap-3 sm:gap-3.5 px-3.5 sm:px-4 py-3.5 hover:bg-slate-50 transition-colors"
                    >
                        <!-- The date as a date, not as a sentence to read. -->
                        <span class="shrink-0 w-11 text-center">
                            <span class="block text-2xs font-bold text-slate-500 uppercase leading-none">{{ weekdayShort(entry.date) }}</span>
                            <span class="block text-lg font-bold text-slate-900 leading-tight tabular-nums">{{ dayNumber(entry.date) }}</span>
                            <span class="block text-2xs font-semibold text-slate-500 uppercase leading-none">{{ monthShort(entry.date) }}</span>
                        </span>

                        <span class="w-1 self-stretch rounded-full shrink-0" :class="entryAccent(entry)" />

                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold text-slate-900 truncate leading-tight capitalize">
                                {{ typeLabel(entry.name) }}
                            </span>
                            <span class="block text-xs font-medium text-slate-600 mt-0.5 truncate">
                                <template v-if="entry.kind !== 'service'">{{ t('calendar.kind_' + entry.kind) }} · </template>
                                <template v-if="entry.time">{{ entry.time.slice(0, 5) }}<template v-if="entry.end_time">–{{ entry.end_time.slice(0, 5) }}</template></template>
                                <template v-if="entry.song_count"> · {{ t('dashboard.songs_count', { count: entry.song_count }) }}</template>
                            </span>
                        </span>

                        <span class="hidden sm:block shrink-0 text-2xs font-semibold text-slate-500">{{ daysFromNow(entry.date) }}</span>

                        <svg class="sm:hidden w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                    </div>

                    <!-- One hairline between the two kinds. The rows are
                         divided by slate-100; this sits two steps darker so
                         the change of subject registers, while staying a
                         single pixel rather than a bar across the card. -->
                    <div v-if="showAgendaRule" class="h-px bg-slate-300" />

                    <div class="divide-y divide-slate-100">
                    <Link
                        v-for="entry in agenda.entries"
                        :key="entry.kind + entry.id"
                        :href="entryHref(entry)"
                        class="flex items-center gap-3 sm:gap-3.5 px-3.5 sm:px-4 py-3.5 hover:bg-slate-50 transition-colors"
                    >
                        <!-- The date as a date, not as a sentence to read. -->
                        <span class="shrink-0 w-11 text-center">
                            <span class="block text-2xs font-bold text-slate-500 uppercase leading-none">{{ weekdayShort(entry.date) }}</span>
                            <span class="block text-lg font-bold text-slate-900 leading-tight tabular-nums">{{ dayNumber(entry.date) }}</span>
                            <span class="block text-2xs font-semibold text-slate-500 uppercase leading-none">{{ monthShort(entry.date) }}</span>
                        </span>

                        <span class="w-1 self-stretch rounded-full shrink-0" :class="entryAccent(entry)" />

                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold text-slate-900 truncate leading-tight capitalize">
                                {{ typeLabel(entry.name) }}
                            </span>
                            <span class="block text-xs font-medium text-slate-600 mt-0.5 truncate">
                                <template v-if="entry.kind !== 'service'">{{ t('calendar.kind_' + entry.kind) }} · </template>
                                <template v-if="entry.time">{{ entry.time.slice(0, 5) }}<template v-if="entry.end_time">–{{ entry.end_time.slice(0, 5) }}</template></template>
                                <template v-if="entry.song_count"> · {{ t('dashboard.songs_count', { count: entry.song_count }) }}</template>
                            </span>
                        </span>

                        <span class="hidden sm:block shrink-0 text-2xs font-semibold text-slate-500">{{ daysFromNow(entry.date) }}</span>

                        <svg class="sm:hidden w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                    </div>
                </div>
            </div>

            <!-- One card per number. Divided cells read as a table, which
                 is a shape for comparing things; these are two separate facts
                 that happen to sit side by side. -->
            <div class="grid grid-cols-2 gap-2.5 lg:gap-3">
                <Link
                    v-for="counter in counters"
                    :key="counter.href"
                    :href="counter.href"
                    class="bg-white rounded-2xl border border-slate-200 shadow-sm px-4 py-4 lg:py-5 hover:border-slate-300 hover:shadow-md transition"
                >
                    <span class="flex items-start justify-between gap-2">
                        <span class="text-xs font-medium text-slate-500 truncate">{{ counter.label }}</span>

                        <!-- Tinted glyph instead of a coloured dot: it carries
                             the same colour and also says what is being
                             counted. Top right, out of the number's way. -->
                        <svg v-if="counter.icon === 'calendar'" class="w-4 h-4 shrink-0" :class="counter.tint" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3a.75.75 0 0 1 1.5 0v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd" />
                        </svg>
                        <svg v-else class="w-4 h-4 shrink-0" :class="counter.tint" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                        </svg>
                    </span>

                    <span class="block text-2xl lg:text-3xl font-semibold text-slate-900 tracking-tight tabular-nums mt-1.5 leading-none">
                        {{ counter.value }}
                    </span>
                </Link>
            </div>

            <!-- Shortcuts. Small enough not to compete with the service
                 above, big enough to press without aiming. Creating a service
                 keeps the same footprint as the rest and earns its place by
                 colour instead of by size — on a phone it is left out, since
                 the floating button is already there. -->
            <div class="grid grid-cols-2 gap-2.5 lg:flex lg:flex-row">
                <Link
                    v-if="canWrite"
                    href="/services/create"
                    class="hidden lg:flex lg:flex-1 flex-col items-center gap-2 px-3 py-3.5 rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-sm shadow-indigo-200 hover:shadow-md active:scale-[0.98] transition"
                >
                    <span class="w-9 h-9 rounded-xl bg-white/20 backdrop-blur-sm flex items-center justify-center">
                        <svg class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </span>
                    <span class="text-xs font-bold text-center leading-tight">{{ t('dashboard.new_service') }}</span>
                </Link>

                <Link
                    v-for="(shortcut, i) in shortcuts"
                    :key="shortcut.href"
                    :href="shortcut.href"
                    class="flex lg:flex-1 flex-col items-center gap-2 px-3 py-3.5 bg-white rounded-xl border border-slate-200 shadow-sm hover:border-slate-300 hover:shadow-md active:scale-[0.98] transition"
                    :class="lastSpansRow && i === shortcuts.length - 1 ? 'col-span-2 lg:col-span-1' : ''"
                >
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center" :class="shortcut.chip">
                        <svg v-if="shortcut.icon === 'calendar'" class="w-[18px] h-[18px]" :class="shortcut.tint" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3a.75.75 0 0 1 1.5 0v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z" clip-rule="evenodd" />
                        </svg>
                        <svg v-else-if="shortcut.icon === 'music'" class="w-[18px] h-[18px]" :class="shortcut.tint" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                        </svg>
                        <svg v-else-if="shortcut.icon === 'members'" class="w-[18px] h-[18px]" :class="shortcut.tint" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.42 0-8 2.24-8 5v1h16v-1c0-2.76-3.58-5-8-5z" />
                        </svg>
                        <svg v-else class="w-[18px] h-[18px]" :class="shortcut.tint" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 0 1 0 12.728M16.463 8.288a5.25 5.25 0 0 1 0 7.424M6.75 8.25l4.72-4.72a.75.75 0 0 1 1.28.53v15.88a.75.75 0 0 1-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 0 1 2.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75Z" />
                        </svg>
                    </span>
                    <span class="text-xs font-semibold text-slate-700 text-center leading-tight">{{ shortcut.label }}</span>
                </Link>
            </div>

            <!-- Storage sits last. It is the one figure here that can stop
                 someone working, so it keeps the full width, but it is also
                 the one nobody opens this screen to read — and putting it
                 above the shortcuts pushed them off the first screenful. -->
            <Link
                v-if="audioEnabled && canWrite"
                href="/audio"
                class="block bg-white rounded-2xl border border-slate-200 shadow-sm px-4 py-3.5 hover:border-slate-300 hover:shadow-md transition"
            >
                <div class="flex items-baseline justify-between gap-2 mb-2">
                    <p class="text-xs font-medium text-slate-500 truncate">{{ t('audio_library.title') }}</p>
                    <p class="text-xs font-medium text-slate-500 tabular-nums shrink-0">
                        <span class="font-semibold text-slate-900">{{ stats.audio_used_mb }}</span>
                        / {{ stats.audio_quota_mb }} MB
                    </p>
                </div>
                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="audioPercent >= 90 ? 'bg-red-500' : audioPercent >= 80 ? 'bg-amber-500' : 'bg-indigo-600'"
                        :style="{ width: audioPercent + '%' }"
                    />
                </div>
            </Link>

        </div>

        <NotificationsSheet
            :open="pushSheetOpen"
            @close="closePushPrompt"
            @snooze="snoozePushPrompt"
            @enabled="pushSheetOpen = false"
        />
    </AppLayout>
</template>
