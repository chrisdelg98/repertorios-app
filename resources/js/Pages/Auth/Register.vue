<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const { t } = useI18n();

const step = ref(1);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    band_name: '',
});

const canContinueStep1 = computed(() =>
    form.name.trim().length > 0
    && form.email.trim().length > 0
    && form.password.length >= 8
    && form.password === form.password_confirmation
);

const canSubmit = computed(() =>
    canContinueStep1.value && form.band_name.trim().length > 0
);

function next() {
    if (canContinueStep1.value) step.value = 2;
}

function back() {
    step.value = 1;
}

function submit() {
    form.post('/register');
}
</script>

<template>
    <Head :title="t('auth.register.title')" />

    <AuthLayout
        :heading="t('auth.register.heading')"
        :subheading="t('auth.register.subheading')"
    >
        <!-- Step indicator -->
        <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 flex items-center justify-center rounded-full text-[11px] font-bold transition-colors"
                    :class="step >= 1 ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500'">1</span>
                <span class="text-xs font-medium transition-colors"
                    :class="step === 1 ? 'text-slate-900' : 'text-slate-500'">{{ t('auth.register.step_account') }}</span>
            </div>
            <div class="flex-1 h-px bg-slate-200 mx-3" />
            <div class="flex items-center gap-2">
                <span class="w-6 h-6 flex items-center justify-center rounded-full text-[11px] font-bold transition-colors"
                    :class="step >= 2 ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500'">2</span>
                <span class="text-xs font-medium transition-colors"
                    :class="step === 2 ? 'text-slate-900' : 'text-slate-500'">{{ t('auth.register.step_band') }}</span>
            </div>
        </div>

        <!-- Step 1: Account -->
        <form v-if="step === 1" @submit.prevent="next" class="space-y-3">
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
                <p class="text-[11px] text-slate-500 mt-1">{{ t('auth.register.password_hint') }}</p>
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
                :disabled="!canContinueStep1"
                class="w-full mt-2 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
            >
                {{ t('auth.register.next') }}
            </button>
        </form>

        <!-- Step 2: Band -->
        <form v-else @submit.prevent="submit" class="space-y-3">
            <div>
                <label for="band_name" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.band_name') }}</label>
                <input
                    id="band_name"
                    v-model="form.band_name"
                    type="text"
                    required
                    maxlength="100"
                    autofocus
                    :placeholder="t('auth.placeholders.band_name')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
                <p class="text-[11px] text-slate-500 mt-1">{{ t('auth.register.band_name_hint') }}</p>
                <p v-if="form.errors.band_name" class="text-xs text-red-600 mt-1">{{ form.errors.band_name }}</p>
            </div>

            <div class="bg-indigo-50 rounded-xl p-3 flex items-start gap-2.5">
                <svg class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-[11px] text-indigo-700 leading-relaxed">{{ t('auth.register.info_what_next') }}</p>
            </div>

            <div class="flex gap-2 pt-1">
                <button
                    type="button"
                    @click="back"
                    class="px-4 py-2.5 text-sm font-medium text-slate-600 rounded-xl border border-slate-300 hover:bg-slate-50 transition-colors"
                >
                    {{ t('auth.register.back') }}
                </button>
                <button
                    type="submit"
                    :disabled="!canSubmit || form.processing"
                    class="flex-1 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
                >
                    {{ form.processing ? t('auth.actions.registering') : t('auth.actions.register') }}
                </button>
            </div>
        </form>

        <!-- Switch to login -->
        <p class="text-center text-xs text-slate-500 mt-5">
            {{ t('auth.register.have_account') }}
            <Link href="/login" class="font-semibold text-indigo-600 hover:text-indigo-700 ml-1">{{ t('auth.register.sign_in') }}</Link>
        </p>
    </AuthLayout>
</template>
