<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import { compressImage, ImageTooLargeError, MAX_INPUT_MB } from '@/composables/useImageCompressor';

const { t } = useI18n();
const page = usePage();
const auth = computed(() => page.props.auth);

const infoSaved = ref(false);
const passwordSaved = ref(false);

// ── Info form ───────────────────────────────────────────────────
const infoForm = useForm({
    name:  auth.value.user?.name ?? '',
    email: auth.value.user?.email ?? '',
});

function submitInfo() {
    infoForm.put('/settings/profile', {
        onSuccess: () => {
            infoSaved.value = true;
            setTimeout(() => { infoSaved.value = false; }, 2500);
        },
    });
}

// ── Avatar form ─────────────────────────────────────────────────
const avatarInput   = ref(null);
const avatarPreview = ref(auth.value.user?.avatar_url ?? null);
const avatarError   = ref('');
const avatarForm    = useForm({ avatar: null });

function pickAvatar() {
    avatarInput.value?.click();
}

async function onAvatarChange(e) {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;

    avatarError.value = '';

    let toUpload = file;
    try {
        toUpload = await compressImage(file, { minSide: 512 });
    } catch (err) {
        if (err instanceof ImageTooLargeError) {
            avatarError.value = t('settings.profile.error_too_large', { max: MAX_INPUT_MB });
            return;
        }
        // Other compression errors (memory, unsupported format): fall back to original.
        toUpload = file;
    }

    avatarForm.avatar   = toUpload;
    avatarPreview.value = URL.createObjectURL(toUpload);
    avatarForm.post('/settings/profile/avatar', {
        preserveScroll: true,
        onError: (errors) => {
            avatarError.value = errors.avatar || '';
        },
    });
}

// ── Password form ───────────────────────────────────────────────
const passwordForm = useForm({
    current_password: '',
    password:         '',
    password_confirmation: '',
});

function submitPassword() {
    passwordForm.put('/settings/profile/password', {
        onSuccess: () => {
            passwordSaved.value = true;
            passwordForm.reset();
            setTimeout(() => { passwordSaved.value = false; }, 2500);
        },
    });
}
</script>

<template>
    <Head :title="t('settings.profile.title')" />

    <AppLayout>
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg lg:max-w-2xl mx-auto">

            <!-- Back -->
            <Link href="/settings" class="inline-flex items-center gap-1.5 text-xs text-slate-400 hover:text-slate-600 mb-4 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ t('settings.title') }}
            </Link>

            <h1 class="text-lg font-semibold text-slate-900 mb-5">{{ t('settings.profile.title') }}</h1>

            <!-- Avatar -->
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-4 mb-4 flex items-center gap-4">
                <div class="relative shrink-0">
                    <div class="w-16 h-16 rounded-full overflow-hidden bg-indigo-100 flex items-center justify-center">
                        <img v-if="avatarPreview" :src="avatarPreview" class="w-full h-full object-cover" />
                        <span v-else class="text-2xl font-bold text-indigo-600">
                            {{ (auth.user?.name || '?').charAt(0).toUpperCase() }}
                        </span>
                    </div>
                    <button
                        v-if="avatarForm.processing"
                        class="absolute inset-0 rounded-full bg-black/30 flex items-center justify-center"
                    >
                        <svg class="w-5 h-5 text-white animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                    </button>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900">{{ t('settings.profile.avatar_label') }}</p>
                    <p class="text-xs text-slate-400 mt-0.5">{{ t('settings.profile.avatar_hint', { max: MAX_INPUT_MB }) }}</p>
                    <button
                        @click="pickAvatar"
                        :disabled="avatarForm.processing"
                        class="mt-2 text-xs font-semibold text-indigo-600 hover:text-indigo-700 disabled:opacity-40"
                    >
                        {{ avatarForm.processing ? t('settings.profile.uploading') : t('settings.profile.change_photo') }}
                    </button>
                    <p v-if="avatarError" class="text-xs text-red-600 mt-1">{{ avatarError }}</p>
                    <input ref="avatarInput" type="file" accept="image/*" class="hidden" @change="onAvatarChange" />
                </div>
            </div>

            <!-- Info form -->
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-4 mb-4 space-y-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.profile.name_label') }}</label>
                    <input
                        v-model="infoForm.name"
                        type="text"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="infoForm.errors.name" class="text-xs text-red-600 mt-1">{{ infoForm.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.profile.email_label') }}</label>
                    <input
                        v-model="infoForm.email"
                        type="email"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="infoForm.errors.email" class="text-xs text-red-600 mt-1">{{ infoForm.errors.email }}</p>
                </div>

                <button
                    @click="submitInfo"
                    :disabled="infoForm.processing"
                    class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors"
                >
                    {{ infoForm.processing ? t('settings.profile.saving') : infoSaved ? t('settings.profile.saved') : t('settings.profile.save') }}
                </button>
            </div>

            <!-- Password form -->
            <div class="bg-white rounded-xl border border-slate-200 px-4 py-4 space-y-3">
                <p class="text-sm font-semibold text-slate-900">{{ t('settings.profile.password_section') }}</p>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.profile.current_password') }}</label>
                    <input
                        v-model="passwordForm.current_password"
                        type="password"
                        autocomplete="current-password"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="passwordForm.errors.current_password" class="text-xs text-red-600 mt-1">{{ passwordForm.errors.current_password }}</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.profile.new_password') }}</label>
                    <input
                        v-model="passwordForm.password"
                        type="password"
                        autocomplete="new-password"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="passwordForm.errors.password" class="text-xs text-red-600 mt-1">{{ passwordForm.errors.password }}</p>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">{{ t('settings.profile.confirm_password') }}</label>
                    <input
                        v-model="passwordForm.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <button
                    @click="submitPassword"
                    :disabled="passwordForm.processing || !passwordForm.current_password || !passwordForm.password"
                    class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors"
                >
                    {{ passwordForm.processing ? t('settings.profile.saving') : passwordSaved ? t('settings.profile.saved') : t('settings.profile.save') }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>
