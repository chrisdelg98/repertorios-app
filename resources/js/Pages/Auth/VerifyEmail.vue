<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const { t } = useI18n();

defineProps({
    status: String,
});

const resendForm = useForm({});

function resend() {
    resendForm.post('/email/verification-notification', { preserveScroll: true });
}

function logout() {
    router.post('/logout');
}
</script>

<template>
    <Head :title="t('auth.verify.title')" />

    <AuthLayout
        :heading="t('auth.verify.heading')"
        :subheading="t('auth.verify.subheading')"
    >
        <div v-if="status === 'verification-link-sent'" class="bg-green-50 border border-green-200 text-green-700 text-xs rounded-lg px-3 py-2 mb-4">
            {{ t('auth.verify.resent') }}
        </div>

        <!-- Mail icon visualization -->
        <div class="flex justify-center mb-4">
            <div class="w-16 h-16 bg-indigo-50 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                </svg>
            </div>
        </div>

        <p class="text-center text-xs text-slate-500 mb-5 leading-relaxed">
            {{ t('auth.verify.tip') }}
        </p>

        <button
            @click="resend"
            :disabled="resendForm.processing"
            class="w-full py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
        >
            {{ resendForm.processing ? t('auth.actions.sending') : t('auth.actions.resend_email') }}
        </button>

        <button
            @click="logout"
            class="w-full mt-2 py-2.5 text-sm font-medium text-slate-500 hover:text-slate-700 transition-colors"
        >
            {{ t('auth.actions.logout') }}
        </button>
    </AuthLayout>
</template>
