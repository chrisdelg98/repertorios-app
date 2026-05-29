<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const { t } = useI18n();

defineProps({
    status: String,
});

const form = useForm({ email: '' });

function submit() {
    form.post('/forgot-password');
}
</script>

<template>
    <Head :title="t('auth.forgot.title')" />

    <AuthLayout
        :heading="t('auth.forgot.heading')"
        :subheading="t('auth.forgot.subheading')"
    >
        <div v-if="status" class="bg-green-50 border border-green-200 text-green-700 text-xs rounded-lg px-3 py-2 mb-4">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <div>
                <label for="email" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    autofocus
                    :placeholder="t('auth.placeholders.email')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
                <p v-if="form.errors.email" class="text-xs text-red-600 mt-1">{{ form.errors.email }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing || !form.email.trim()"
                class="w-full mt-2 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
            >
                {{ form.processing ? t('auth.actions.sending') : t('auth.actions.send_reset') }}
            </button>
        </form>

        <p class="text-center text-xs text-slate-500 mt-5">
            <Link href="/login" class="font-semibold text-indigo-600 hover:text-indigo-700">{{ t('auth.actions.back_to_login') }}</Link>
        </p>
    </AuthLayout>
</template>
