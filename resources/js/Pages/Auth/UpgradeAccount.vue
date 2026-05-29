<script setup>
import { computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const { t } = useI18n();

const props = defineProps({
    band: Object,
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const canSubmit = computed(() =>
    form.name.trim().length > 0
    && form.email.trim().length > 0
    && form.password.length >= 8
    && form.password === form.password_confirmation
);

function submit() {
    form.post('/upgrade');
}
</script>

<template>
    <Head :title="t('auth.upgrade.title')" />

    <AuthLayout :heading="t('auth.upgrade.heading')">
        <template #subheading>
            <i18n-t keypath="auth.upgrade.subheading" tag="span" scope="global">
                <template #band>
                    <span class="font-bold text-slate-900">{{ band.name }}</span>
                </template>
            </i18n-t>
        </template>
        <!-- Benefits panel -->
        <div class="bg-gradient-to-br from-indigo-50 to-violet-50 border border-indigo-100 rounded-xl p-4 mb-5">
            <p class="text-xs font-semibold text-indigo-700 mb-2.5">{{ t('auth.upgrade.benefits_title') }}</p>
            <ul class="space-y-1.5">
                <li v-for="benefit in [
                    t('auth.upgrade.benefit_1'),
                    t('auth.upgrade.benefit_2'),
                    t('auth.upgrade.benefit_3'),
                ]" :key="benefit" class="flex items-start gap-2 text-xs text-slate-700">
                    <svg class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ benefit }}</span>
                </li>
            </ul>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <div>
                <label for="name" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.name') }}</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    maxlength="100"
                    autocomplete="name"
                    autofocus
                    :placeholder="t('auth.placeholders.name')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
                <p v-if="form.errors.name" class="text-xs text-red-600 mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    maxlength="150"
                    autocomplete="email"
                    :placeholder="t('auth.placeholders.email')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
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
                    :placeholder="t('auth.placeholders.password')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
                <p class="text-[11px] text-slate-400 mt-1">{{ t('auth.register.password_hint') }}</p>
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
                :disabled="!canSubmit || form.processing"
                class="w-full mt-2 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
            >
                {{ form.processing ? t('auth.actions.registering') : t('auth.upgrade.cta') }}
            </button>
        </form>

        <p class="text-center text-xs text-slate-500 mt-5">
            <Link href="/dashboard" class="text-slate-400 hover:text-slate-600">{{ t('auth.upgrade.cancel') }}</Link>
        </p>
    </AuthLayout>
</template>
