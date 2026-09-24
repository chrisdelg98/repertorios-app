<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import InstallSheet from '@/Components/InstallSheet.vue';
import NotificationsSheet from '@/Components/NotificationsSheet.vue';
import { usePush } from '@/Composables/usePush';
import { useInstall } from '@/Composables/useInstall';

const { t, locale } = useI18n();
const page = usePage();
const { isInstalled, isIosSafari, promptInstall } = useInstall();

const props = defineProps({
    push_devices: { type: Array, default: () => [] },
});

// --- Notifications ---
const pushSheetOpen = ref(false);
const devices = ref([...props.push_devices]);

const { subscribed, busy: pushBusy, isGranted, endpoint, refresh: refreshPush, unsubscribe } = usePush(
    page.props.push?.public_key ?? null
);

/** The row that belongs to the browser being used right now. */
function isThisDevice(device) {
    return !!endpoint.value && endpoint.value.endsWith(device.tail);
}

// Also re-registers this browser if the server lost track of it.
onMounted(() => { refreshPush(locale.value).then(() => router.reload({ only: ['push_devices'] })); });

const pushOn = computed(() => subscribed.value && isGranted.value);

async function disablePush() {
    if (await unsubscribe()) {
        router.reload({
            only: ['push_devices'],
            onSuccess: () => { devices.value = [...page.props.push_devices]; },
        });
    }
}

// --- Test notification ---
const testState = ref('');   // '' | 'sending' | 'sent' | 'none' | 'failed'

async function sendTestNotification() {
    testState.value = 'sending';

    try {
        const response = await fetch('/push/test', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
        });

        const result = await response.json();
        testState.value = result.sent > 0 ? 'sent' : (result.reason ?? 'none');
    } catch {
        testState.value = 'failed';
    }

    setTimeout(() => { testState.value = ''; }, 6000);
}

async function removeDevice(device) {
    // Removing the browser you are sitting at has to unsubscribe it too.
    // Deleting only the row leaves the browser subscribed, and the next visit
    // to this page registers it straight back — which is exactly why "Remove"
    // used to look like it did nothing.
    if (isThisDevice(device)) {
        await disablePush();
        return;
    }

    const response = await fetch(`/push/devices/${device.id}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            'Accept': 'application/json',
        },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        console.error('[push] could not remove device', response.status);
        return;
    }

    devices.value = devices.value.filter(d => d.id !== device.id);
}

function onPushEnabled() {
    pushSheetOpen.value = false;
    router.reload({ only: ['push_devices'], onSuccess: () => { devices.value = [...page.props.push_devices]; } });
}

const installSheetOpen = ref(false);

// iOS has no install prompt to fire, so it goes straight to the instructions.
async function onInstallClick() {
    if (isIosSafari.value) {
        installSheetOpen.value = true;
        return;
    }

    await promptInstall();
}

const donateUrl = computed(() => page.props.donate?.url || null);
const isAdmin   = computed(() => page.props.auth?.access === 'admin');
const isCreator = computed(() => !!page.props.auth?.is_creator);

function openDonate() {
    if (!donateUrl.value) return;
    window.open(donateUrl.value, '_blank', 'noopener,noreferrer');
}

const sections = computed(() => {
    const list = [];
    // Templates: admin (creator + delegated)
    if (isAdmin.value) list.push({
        href: '/settings/schedule-templates',
        icon: 'calendar',
        title: () => t('settings.templates.title'),
        subtitle: () => t('settings.templates.subtitle'),
    });
    // Profile: any registered user
    list.push({
        href: '/settings/profile',
        icon: 'user',
        title: () => t('settings.profile.title'),
        subtitle: () => t('settings.profile.subtitle'),
    });
    // Band + Administrators: creator only
    if (isCreator.value) list.push({
        href: '/settings/band',
        icon: 'band',
        title: () => t('settings.band.title'),
        subtitle: () => t('settings.band.subtitle'),
    });
    if (isCreator.value) list.push({
        href: '/settings/members',
        icon: 'members',
        title: () => t('settings.members.title'),
        subtitle: () => t('settings.members.subtitle'),
    });
    // Second path to the same place as the band switcher menu — a chevron next
    // to the band name is not where people look for this the first time.
    list.push({
        href: '/bands/create',
        icon: 'newband',
        title: () => t('bands.create_another'),
        subtitle: () => t('bands.create_subtitle'),
    });
    return list;
});
</script>

<template>
    <Head :title="t('settings.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-2xl mx-auto">
            <h1 class="text-lg font-semibold text-slate-900 mb-5">{{ t('settings.title') }}</h1>

            <div class="space-y-2">
                <Link
                    v-for="s in sections"
                    :key="s.href"
                    :href="s.href"
                    class="flex items-center gap-4 bg-white rounded-xl px-4 py-3.5 border border-slate-200 hover:bg-slate-50 active:bg-slate-100 transition-colors"
                >
                    <!-- Icon -->
                    <div class="w-9 h-9 bg-indigo-50 rounded-lg flex items-center justify-center shrink-0">
                        <svg v-if="s.icon === 'user'" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        <svg v-else-if="s.icon === 'band'" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                        </svg>
                        <svg v-else-if="s.icon === 'members'" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <svg v-else-if="s.icon === 'newband'" class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m6-6H6" />
                        </svg>
                        <svg v-else class="w-5 h-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-slate-900 text-sm">{{ s.title() }}</p>
                        <p class="text-xs font-medium text-slate-600 mt-0.5 truncate">{{ s.subtitle() }}</p>
                    </div>

                    <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>

                <!-- Notifications -->
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <button
                        type="button"
                        @click="pushOn ? disablePush() : (pushSheetOpen = true)"
                        :disabled="pushBusy"
                        class="w-full flex items-center gap-4 px-4 py-3.5 hover:bg-slate-50 active:bg-slate-100 disabled:opacity-60 transition-colors"
                    >
                        <div
                            class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                            :class="pushOn ? 'bg-emerald-50' : 'bg-indigo-50'"
                        >
                            <svg class="w-5 h-5" :class="pushOn ? 'text-emerald-600' : 'text-indigo-600'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                        </div>

                        <div class="flex-1 min-w-0 text-left">
                            <p class="font-medium text-slate-900 text-sm">{{ t('push.settings_title') }}</p>
                            <p class="text-xs font-medium mt-0.5" :class="pushOn ? 'text-emerald-600' : 'text-slate-600'">
                                {{ pushOn ? t('push.settings_on') : t('push.settings_off') }}
                            </p>
                        </div>

                        <span
                            class="shrink-0 text-2xs font-semibold px-2.5 py-1 rounded-full"
                            :class="pushOn ? 'bg-slate-100 text-slate-600' : 'bg-indigo-600 text-white'"
                        >{{ pushOn ? t('push.settings_turn_off') : t('push.settings_turn_on') }}</span>
                    </button>

                    <!-- Check that this device really receives, which a real
                         notification cannot do: those skip whoever caused them -->
                    <div v-if="pushOn" class="border-t border-slate-100 px-4 py-3 flex items-center justify-between gap-3">
                        <p class="text-xs font-medium min-w-0 flex-1" :class="{
                            'text-emerald-600': testState === 'sent',
                            'text-amber-700': testState && !['sent', 'sending'].includes(testState),
                            'text-slate-600': !testState || testState === 'sending',
                        }">
                            {{ testState === 'sent'           ? t('push.test_sent')
                             : testState === 'not_configured' ? t('push.test_not_configured')
                             : testState === 'no_devices'     ? t('push.test_no_devices')
                             : testState === 'rejected'       ? t('push.test_rejected')
                             : testState === 'failed'         ? t('push.test_failed')
                             : testState === 'none'           ? t('push.test_none')
                             : t('push.test_hint') }}
                        </p>
                        <button
                            type="button"
                            @click="sendTestNotification"
                            :disabled="testState === 'sending'"
                            class="shrink-0 px-3 py-1.5 text-2xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 disabled:opacity-60 rounded-lg transition-colors"
                        >{{ testState === 'sending' ? t('push.test_sending') : t('push.test_button') }}</button>
                    </div>

                    <!-- Other devices of this same person -->
                    <div v-if="devices.length" class="border-t border-slate-100 px-4 py-3">
                        <p class="text-2xs font-semibold text-slate-600 uppercase tracking-wide mb-2">
                            {{ t('push.settings_devices') }}
                        </p>
                        <div class="space-y-1.5">
                            <div
                                v-for="device in devices"
                                :key="device.id"
                                class="flex items-center gap-2 text-xs"
                            >
                                <span class="flex-1 min-w-0 font-medium text-slate-700 truncate">
                                    {{ device.label }}
                                    <span v-if="isThisDevice(device)" class="text-indigo-600">· {{ t('push.settings_this_device') }}</span>
                                </span>
                                <button
                                    type="button"
                                    @click="removeDevice(device)"
                                    class="shrink-0 font-semibold text-slate-600 hover:text-red-600 transition-colors"
                                >{{ t('push.settings_remove_device') }}</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Install app tile (hidden only when already installed) -->
                <button
                    v-if="!isInstalled"
                    @click="onInstallClick"
                    class="w-full flex items-center gap-4 bg-gradient-to-r from-indigo-50 to-violet-50 rounded-xl px-4 py-3.5 border border-indigo-100 hover:from-indigo-100 hover:to-violet-100 active:scale-[0.99] transition"
                >
                    <div class="w-9 h-9 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-indigo-200">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0 text-left">
                        <p class="font-medium text-indigo-700 text-sm">{{ t('install.tile_title') }}</p>
                        <p class="text-xs text-indigo-600 mt-0.5 truncate">{{ t('install.tile_subtitle') }}</p>
                    </div>

                    <svg class="w-4 h-4 text-indigo-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>



                <!-- Already installed badge -->
                <div
                    v-if="isInstalled"
                    class="flex items-center gap-4 bg-green-50 rounded-xl px-4 py-3.5 border border-green-100"
                >
                    <div class="w-9 h-9 bg-green-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-green-700 text-sm">{{ t('install.installed_title') }}</p>
                        <p class="text-xs text-green-500 mt-0.5">{{ t('install.installed_subtitle') }}</p>
                    </div>
                </div>

                <!-- Donate tile -->
                <button
                    v-if="donateUrl"
                    @click="openDonate"
                    class="w-full flex items-center gap-4 bg-gradient-to-r from-pink-50 to-rose-50 rounded-xl px-4 py-3.5 border border-pink-100 hover:from-pink-100 hover:to-rose-100 active:scale-[0.99] transition"
                >
                    <div class="w-9 h-9 bg-gradient-to-br from-pink-500 to-rose-500 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-pink-200">
                        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                        </svg>
                    </div>

                    <div class="flex-1 min-w-0 text-left">
                        <p class="font-medium text-rose-700 text-sm">{{ t('donate.tile_title') }}</p>
                        <p class="text-xs text-rose-400 mt-0.5 truncate">{{ t('donate.tile_subtitle') }}</p>
                    </div>

                    <svg class="w-4 h-4 text-rose-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </button>
            </div>
        </div>

        <InstallSheet :open="installSheetOpen" @close="installSheetOpen = false" />
        <NotificationsSheet
            :open="pushSheetOpen"
            @close="pushSheetOpen = false"
            @snooze="pushSheetOpen = false"
            @enabled="onPushEnabled"
        />
    </AppLayout>
</template>
