<script setup>
import { ref } from 'vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { compressImage, ImageTooLargeError, MAX_INPUT_MB } from '@/composables/useImageCompressor';

const { t } = useI18n();

const props = defineProps({
    band: Object,
});

const nameSaved       = ref(false);
const showInfo        = ref(false);
const pinCopied       = ref(false);
const codeCopied      = ref(false);
const linkCopied      = ref(false);
const showPin         = ref(false);
const confirmCode     = ref(false);
const confirmToken    = ref(false);
const regeneratingCode  = ref(false);
const regeneratingPin   = ref(false);
const regeneratingToken = ref(false);

// ── Name form ────────────────────────────────────────────────────
const nameForm = useForm({ name: props.band.name });

function submitName() {
    nameForm.put('/settings/band', {
        onSuccess: () => {
            nameSaved.value = true;
            setTimeout(() => { nameSaved.value = false; }, 2500);
        },
    });
}

// ── Logo form ────────────────────────────────────────────────────
const logoInput   = ref(null);
const logoPreview = ref(props.band.logo_url);
const logoError   = ref('');
const logoForm    = useForm({ logo: null });

function pickLogo() { logoInput.value?.click(); }

async function onLogoChange(e) {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;

    logoError.value = '';

    let toUpload = file;
    try {
        toUpload = await compressImage(file, { minSide: 640 });
    } catch (err) {
        if (err instanceof ImageTooLargeError) {
            logoError.value = t('settings.band.error_too_large', { max: MAX_INPUT_MB });
            return;
        }
        toUpload = file;
    }

    logoForm.logo     = toUpload;
    logoPreview.value = URL.createObjectURL(toUpload);
    logoForm.post('/settings/band/logo', {
        preserveScroll: true,
        onError: (errors) => {
            logoError.value = errors.logo || '';
        },
    });
}

// ── Regenerate actions ───────────────────────────────────────────
function doRegenerateCode() {
    regeneratingCode.value = true;
    router.post('/settings/band/regenerate-code', {}, {
        onFinish: () => { regeneratingCode.value = false; confirmCode.value = false; },
    });
}

function doRegeneratePin() {
    regeneratingPin.value = true;
    router.post('/settings/band/regenerate-pin', {}, {
        onFinish: () => { regeneratingPin.value = false; },
    });
}

function doRegenerateToken() {
    regeneratingToken.value = true;
    router.post('/settings/band/regenerate-token', {}, {
        onFinish: () => { regeneratingToken.value = false; confirmToken.value = false; },
    });
}

// ── Copy helpers ─────────────────────────────────────────────────
function copyCode() {
    navigator.clipboard.writeText(props.band.code);
    codeCopied.value = true;
    setTimeout(() => { codeCopied.value = false; }, 2000);
}

function copyPin() {
    navigator.clipboard.writeText(props.band.access_pin);
    pinCopied.value = true;
    setTimeout(() => { pinCopied.value = false; }, 2000);
}

function copyLink() {
    navigator.clipboard.writeText(props.band.invite_url);
    linkCopied.value = true;
    setTimeout(() => { linkCopied.value = false; }, 2000);
}

function shareLink() {
    if (navigator.share) {
        navigator.share({ url: props.band.invite_url, title: props.band.name });
    } else {
        copyLink();
    }
}
</script>

<template>
    <Head :title="t('settings.band.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-2xl mx-auto">

            <!-- Back -->
            <Link href="/settings" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-slate-600 mb-4 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ t('settings.title') }}
            </Link>

            <h1 class="text-lg font-semibold text-slate-900 mb-5">{{ t('settings.band.title') }}</h1>

            <!-- Logo -->
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-4 mb-4 flex items-center gap-4">
                <div class="w-16 h-16 rounded-xl overflow-hidden shrink-0 flex items-center justify-center"
                    :class="logoPreview ? 'bg-white border border-slate-200' : 'bg-gradient-to-br from-indigo-500 to-violet-600'"
                >
                    <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-cover" />
                    <svg v-else class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">{{ t('settings.band.logo_label') }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ t('settings.band.logo_hint', { max: MAX_INPUT_MB }) }}</p>
                    <button
                        @click="pickLogo"
                        :disabled="logoForm.processing"
                        class="mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-700 disabled:opacity-40"
                    >
                        {{ logoForm.processing ? t('settings.band.saving') : t('settings.band.change_logo') }}
                    </button>
                    <p v-if="logoError" class="text-xs text-red-600 mt-1">{{ logoError }}</p>
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
                <!-- Header -->
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ t('settings.band.access_section') }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ t('settings.band.access_hint') }}</p>
                    </div>
                    <button
                        @click="showInfo = !showInfo"
                        class="w-7 h-7 flex items-center justify-center rounded-full transition-colors shrink-0 mt-0.5"
                        :class="showInfo ? 'text-indigo-600 bg-indigo-50' : 'text-slate-500 hover:text-indigo-600 hover:bg-indigo-50'"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </button>
                </div>

                <!-- Info (expandable) -->
                <div v-if="showInfo" class="bg-indigo-50 rounded-xl px-3.5 py-3 space-y-2 text-xs text-indigo-700">
                    <p class="font-semibold">{{ t('settings.band.info_title') }}</p>
                    <ol class="space-y-1.5 list-decimal list-inside leading-relaxed">
                        <li>{{ t('settings.band.info_step1') }}</li>
                        <li>{{ t('settings.band.info_step2') }}</li>
                        <li>{{ t('settings.band.info_step3') }}</li>
                    </ol>
                </div>

                <!-- Band code -->
                <div class="bg-slate-50 rounded-xl px-4 py-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest">{{ t('settings.band.band_code') }}</p>
                        <button
                            @click="confirmCode = true"
                            :disabled="regeneratingCode"
                            class="flex items-center gap-1 text-[10px] font-semibold text-slate-500 hover:text-amber-600 disabled:opacity-40 transition-colors"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            {{ t('settings.band.regenerate') }}
                        </button>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-2xl font-bold text-slate-900 tracking-[0.2em]">{{ band.code }}</p>
                        <button
                            @click="copyCode"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors"
                            :class="codeCopied ? 'text-green-700 bg-green-50' : 'text-indigo-600 bg-indigo-100 hover:bg-indigo-200'"
                        >
                            <svg v-if="!codeCopied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ codeCopied ? t('settings.band.copied') : t('settings.band.copy') }}
                        </button>
                    </div>
                </div>

                <!-- Access PIN -->
                <div class="bg-slate-50 rounded-xl px-4 py-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest">{{ t('settings.band.access_pin') }}</p>
                        <button
                            @click="doRegeneratePin"
                            :disabled="regeneratingPin"
                            class="flex items-center gap-1 text-[10px] font-semibold text-slate-500 hover:text-amber-600 disabled:opacity-40 transition-colors"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            {{ regeneratingPin ? t('settings.band.regenerating') : t('settings.band.regenerate') }}
                        </button>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-2xl font-bold text-slate-900 tracking-[0.2em] font-mono">
                            {{ band.access_pin ? (showPin ? band.access_pin : '●●●●') : '—' }}
                        </p>
                        <div class="flex items-center gap-1.5">
                            <button
                                v-if="band.access_pin"
                                @click="showPin = !showPin"
                                class="w-8 h-8 flex items-center justify-center text-slate-500 hover:text-indigo-600 transition-colors"
                            >
                                <svg v-if="!showPin" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                            </button>
                            <button
                                v-if="band.access_pin && showPin"
                                @click="copyPin"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors"
                                :class="pinCopied ? 'text-green-700 bg-green-50' : 'text-indigo-600 bg-indigo-100 hover:bg-indigo-200'"
                            >
                                <svg v-if="!pinCopied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                </svg>
                                <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                {{ pinCopied ? t('settings.band.copied') : t('settings.band.copy') }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Invite link -->
                <div class="bg-slate-50 rounded-xl px-4 py-3">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-widest">{{ t('settings.band.invite_link') }}</p>
                        <button
                            @click="confirmToken = true"
                            :disabled="regeneratingToken"
                            class="flex items-center gap-1 text-[10px] font-semibold text-slate-500 hover:text-amber-600 disabled:opacity-40 transition-colors"
                        >
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            {{ t('settings.band.regenerate') }}
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 truncate mb-3">{{ band.invite_url }}</p>
                    <div class="flex gap-2">
                        <button
                            @click="copyLink"
                            class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-semibold rounded-lg transition-colors"
                            :class="linkCopied ? 'text-green-700 bg-green-50' : 'text-indigo-600 bg-indigo-100 hover:bg-indigo-200'"
                        >
                            <svg v-if="!linkCopied" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <svg v-else class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            {{ linkCopied ? t('settings.band.copied') : t('settings.band.copy') }}
                        </button>
                        <button
                            @click="shareLink"
                            class="flex-1 flex items-center justify-center gap-1.5 py-2 text-xs font-semibold text-slate-600 bg-slate-200 hover:bg-slate-300 rounded-lg transition-colors"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                            </svg>
                            {{ t('settings.band.share') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Confirm regenerate code -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="confirmCode" class="fixed inset-0 z-40 bg-black/40" @click="confirmCode = false" />
            </Transition>
            <Transition enter-active-class="transition duration-250 ease-out" enter-from-class="translate-y-full" enter-to-class="translate-y-0" leave-active-class="transition duration-200 ease-in" leave-from-class="translate-y-0" leave-to-class="translate-y-full">
                <div v-if="confirmCode" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-3 pb-8 shadow-xl">
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-4" />
                    <h2 class="text-base font-semibold text-slate-900 mb-1">{{ t('settings.band.regenerate_code_title') }}</h2>
                    <p class="text-sm text-slate-500 mb-5">{{ t('settings.band.regenerate_code_confirm') }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="confirmCode = false" class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300">
                            {{ t('settings.band.cancel') }}
                        </button>
                        <button
                            @click="doRegenerateCode"
                            :disabled="regeneratingCode"
                            class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors"
                        >
                            {{ regeneratingCode ? t('settings.band.regenerating') : t('settings.band.regenerate') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Confirm regenerate token -->
        <Teleport to="body">
            <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-if="confirmToken" class="fixed inset-0 z-40 bg-black/40" @click="confirmToken = false" />
            </Transition>
            <Transition enter-active-class="transition duration-250 ease-out" enter-from-class="translate-y-full" enter-to-class="translate-y-0" leave-active-class="transition duration-200 ease-in" leave-from-class="translate-y-0" leave-to-class="translate-y-full">
                <div v-if="confirmToken" class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl px-4 pt-3 pb-8 shadow-xl">
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-4" />
                    <h2 class="text-base font-semibold text-slate-900 mb-1">{{ t('settings.band.regenerate_token_title') }}</h2>
                    <p class="text-sm text-slate-500 mb-5">{{ t('settings.band.regenerate_token_confirm') }}</p>
                    <div class="flex gap-2">
                        <button type="button" @click="confirmToken = false" class="flex-1 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300">
                            {{ t('settings.band.cancel') }}
                        </button>
                        <button
                            @click="doRegenerateToken"
                            :disabled="regeneratingToken"
                            class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors"
                        >
                            {{ regeneratingToken ? t('settings.band.regenerating') : t('settings.band.regenerate') }}
                        </button>
                    </div>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>
