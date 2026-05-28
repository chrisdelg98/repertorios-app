<script setup>
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();

const props = defineProps({
    band: Object,
});

const nameSaved   = ref(false);
const copiedCode  = ref(false);
const showInfo    = ref(false);
const showPinForm = ref(false);
const pinsSaved   = ref(false);

// ── Name form ───────────────────────────────────────────────────
const nameForm = useForm({ name: props.band.name });

function submitName() {
    nameForm.put('/settings/band', {
        onSuccess: () => {
            nameSaved.value = true;
            setTimeout(() => { nameSaved.value = false; }, 2500);
        },
    });
}

// ── Logo form ───────────────────────────────────────────────────
const logoInput   = ref(null);
const logoPreview = ref(props.band.logo_url);
const logoForm    = useForm({ logo: null });

function pickLogo() { logoInput.value?.click(); }

function onLogoChange(e) {
    const file = e.target.files[0];
    if (!file) return;
    logoForm.logo    = file;
    logoPreview.value = URL.createObjectURL(file);
    logoForm.post('/settings/band/logo');
}

// ── PINs form ───────────────────────────────────────────────────
const pinForm = useForm({
    access_pin: '',
    edit_pin:   '',
});

function submitPins() {
    pinForm.put('/settings/band/pins', {
        onSuccess: () => {
            pinsSaved.value = true;
            pinForm.reset();
            showPinForm.value = false;
            setTimeout(() => { pinsSaved.value = false; }, 2500);
        },
    });
}

// ── Copy band code ──────────────────────────────────────────────
function copyCode() {
    navigator.clipboard.writeText(props.band.code).then(() => {
        copiedCode.value = true;
        setTimeout(() => { copiedCode.value = false; }, 2000);
    });
}
</script>

<template>
    <Head :title="t('settings.band.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-2xl mx-auto">

            <!-- Back -->
            <Link href="/settings" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-slate-600 mb-4 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ t('settings.title') }}
            </Link>

            <h1 class="text-lg font-semibold text-slate-900 mb-5">{{ t('settings.band.title') }}</h1>

            <!-- Logo -->
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-4 mb-4 flex items-center gap-4">
                <div class="w-16 h-16 rounded-xl overflow-hidden bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shrink-0">
                    <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-cover" />
                    <svg v-else class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">{{ t('settings.band.logo_label') }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">{{ t('settings.band.logo_hint') }}</p>
                    <button
                        @click="pickLogo"
                        :disabled="logoForm.processing"
                        class="mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-700 disabled:opacity-40"
                    >
                        {{ logoForm.processing ? t('settings.band.saving') : t('settings.band.change_logo') }}
                    </button>
                    <input ref="logoInput" type="file" accept="image/*" class="hidden" @change="onLogoChange" />
                </div>
            </div>

            <!-- Name form -->
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-4 mb-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.band.name_label') }}</label>
                    <input
                        v-model="nameForm.name"
                        type="text"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="nameForm.errors.name" class="text-xs text-red-600 mt-1">{{ nameForm.errors.name }}</p>
                </div>
                <button
                    @click="submitName"
                    :disabled="nameForm.processing || !nameForm.name.trim()"
                    class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors"
                >
                    {{ nameForm.processing ? t('settings.band.saving') : nameSaved ? '✓ ' + t('settings.profile.saved') : t('settings.band.save') }}
                </button>
            </div>

            <!-- Member access card -->
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-4 space-y-4">
                <!-- Section header -->
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ t('settings.band.access_section') }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ t('settings.band.access_hint') }}</p>
                    </div>
                    <button
                        @click="showInfo = !showInfo"
                        class="w-7 h-7 flex items-center justify-center rounded-full text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors shrink-0 mt-0.5"
                        :aria-label="t('settings.band.how_it_works')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                </div>

                <!-- Info card (expandable) -->
                <div v-if="showInfo" class="bg-indigo-50 rounded-xl px-3.5 py-3 space-y-2 text-xs text-indigo-700">
                    <p class="font-semibold">{{ t('settings.band.info_title') }}</p>
                    <ol class="space-y-1 list-decimal list-inside leading-relaxed">
                        <li>{{ t('settings.band.info_step1') }}</li>
                        <li>{{ t('settings.band.info_step2') }}</li>
                        <li>{{ t('settings.band.info_step3') }}</li>
                    </ol>
                </div>

                <!-- Band code -->
                <div class="flex items-center justify-between bg-slate-50 rounded-xl px-4 py-3">
                    <div>
                        <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest">{{ t('settings.band.band_code') }}</p>
                        <p class="text-2xl font-bold text-slate-900 tracking-[0.2em] mt-1">{{ band.code }}</p>
                    </div>
                    <button
                        @click="copyCode"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors"
                        :class="copiedCode ? 'text-green-700 bg-green-50' : 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100'"
                    >
                        <svg v-if="!copiedCode" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        {{ copiedCode ? t('settings.band.copied') : t('settings.band.copy') }}
                    </button>
                </div>

                <!-- PINs status -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full bg-green-400" />
                            <span class="text-xs text-slate-600">{{ t('settings.band.access_pin') }}</span>
                            <span class="text-xs text-slate-400">· {{ t('settings.band.pin_set') }}</span>
                        </div>
                    </div>
                    <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full" :class="band.has_edit_pin ? 'bg-green-400' : 'bg-slate-200'" />
                            <span class="text-xs text-slate-600">{{ t('settings.band.edit_pin') }}</span>
                            <span class="text-xs text-slate-400">· {{ band.has_edit_pin ? t('settings.band.pin_set') : t('settings.band.pin_not_set') }}</span>
                        </div>
                    </div>
                </div>

                <button
                    @click="showPinForm = true"
                    class="w-full py-2.5 text-sm font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-xl transition-colors"
                >
                    {{ pinsSaved ? '✓ ' + t('settings.profile.saved') : t('settings.band.change_pins') }}
                </button>
            </div>
        </div>

        <!-- Change PINs bottom sheet -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="showPinForm" class="fixed inset-0 z-40 bg-black/40" @click="showPinForm = false; pinForm.reset()" />
            </Transition>
            <Transition enter-active-class="transition duration-250 ease-out" enter-from-class="translate-y-full" enter-to-class="translate-y-0" leave-active-class="transition duration-200 ease-in" leave-from-class="translate-y-0" leave-to-class="translate-y-full">
                <div v-if="showPinForm" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-3 pb-8 shadow-xl">
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-4" />
                    <h2 class="text-base font-semibold text-slate-900 mb-1">{{ t('settings.band.change_pins') }}</h2>
                    <p class="text-xs text-slate-400 mb-4">{{ t('settings.band.pins_hint') }}</p>

                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.band.access_pin') }}</label>
                            <input
                                v-model="pinForm.access_pin"
                                type="text"
                                maxlength="20"
                                :placeholder="t('settings.band.pin_placeholder')"
                                class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p v-if="pinForm.errors.access_pin" class="text-xs text-red-600 mt-1">{{ pinForm.errors.access_pin }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">
                                {{ t('settings.band.edit_pin') }}
                                <span class="text-slate-400 font-normal ml-1">{{ t('settings.band.optional') }}</span>
                            </label>
                            <input
                                v-model="pinForm.edit_pin"
                                type="text"
                                maxlength="20"
                                :placeholder="t('settings.band.pin_placeholder')"
                                class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p class="text-xs text-slate-400 mt-1">{{ t('settings.band.edit_pin_hint') }}</p>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-5">
                        <button type="button" @click="showPinForm = false; pinForm.reset()" class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300">
                            {{ t('settings.band.cancel') }}
                        </button>
                        <button
                            @click="submitPins"
                            :disabled="pinForm.processing || !pinForm.access_pin.trim()"
                            class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors"
                        >
                            {{ pinForm.processing ? t('settings.band.saving') : t('settings.band.save') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
