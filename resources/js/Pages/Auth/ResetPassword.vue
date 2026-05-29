<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const { t } = useI18n();

const props = defineProps({
    token: String,
    email: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/reset-password');
}
</script>

<template>
    <Head :title="t('auth.reset.title')" />

    <AuthLayout
        :heading="t('auth.reset.heading')"
        :subheading="t('auth.reset.subheading')"
    >
        <form @submit.prevent="submit" class="space-y-3">
            <div>
                <label for="email" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    readonly
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-200 bg-slate-50 text-slate-600"
                />
                <p v-if="form.errors.email" class="text-xs text-red-600 mt-1">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.password') }}</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                    autofocus
                    :placeholder="t('auth.placeholders.password')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
                <p v-if="form.errors.password" class="text-xs text-red-600 mt-1">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.password_confirm') }}</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    :placeholder="t('auth.placeholders.password')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full mt-2 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
            >
                {{ form.processing ? t('auth.actions.resetting') : t('auth.actions.reset_password') }}
            </button>
        </form>
    </AuthLayout>
</template>
