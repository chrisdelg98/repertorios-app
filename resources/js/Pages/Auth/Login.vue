<script setup>
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const { t } = useI18n();

defineProps({
    status: String,
});

const tab        = ref('admin');
const adminForm  = useForm({ email: '', password: '' });
const memberForm = useForm({ code: '', pin: '' });

function loginAdmin() {
    adminForm.post('/login');
}

function joinBand() {
    memberForm.post('/join');
}
</script>

<template>
    <Head :title="t('auth.login.title')" />

    <AuthLayout
        :heading="t('auth.login.heading')"
        :subheading="t('auth.login.subheading')"
    >
        <div v-if="status" class="bg-green-50 border border-green-200 text-green-700 text-xs rounded-lg px-3 py-2 mb-4">
            {{ status }}
        </div>

        <!-- Tabs -->
        <div class="relative grid grid-cols-2 mb-5 border-b border-slate-100">
            <button
                type="button"
                @click="tab = 'admin'"
                class="py-2.5 text-sm font-semibold transition-colors"
                :class="tab === 'admin' ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-600'"
            >
                {{ t('auth.tabs.admin') }}
            </button>
            <button
                type="button"
                @click="tab = 'member'"
                class="py-2.5 text-sm font-semibold transition-colors"
                :class="tab === 'member' ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-600'"
            >
                {{ t('auth.tabs.member') }}
            </button>
            <span
                class="absolute bottom-0 h-0.5 w-1/2 bg-gradient-to-r from-indigo-500 to-violet-600 rounded-full transition-all duration-300 ease-out"
                :class="tab === 'admin' ? 'left-0' : 'left-1/2'"
            />
        </div>

        <!-- Admin form -->
        <form v-if="tab === 'admin'" @submit.prevent="loginAdmin" class="space-y-3">
            <div>
                <label for="email" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.email') }}</label>
                <input
                    id="email"
                    v-model="adminForm.email"
                    type="email"
                    required
                    autocomplete="email"
                    autofocus
                    :placeholder="t('auth.placeholders.email')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
                <p v-if="adminForm.errors.email" class="text-xs text-red-600 mt-1">{{ adminForm.errors.email }}</p>
            </div>
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label for="password" class="block text-xs font-medium text-slate-600">{{ t('auth.fields.password') }}</label>
                    <Link href="/forgot-password" class="text-[11px] font-semibold text-indigo-600 hover:text-indigo-700">
                        {{ t('auth.forgot_link') }}
                    </Link>
                </div>
                <input
                    id="password"
                    v-model="adminForm.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="t('auth.placeholders.password')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
                <p v-if="adminForm.errors.password" class="text-xs text-red-600 mt-1">{{ adminForm.errors.password }}</p>
            </div>
            <button
                type="submit"
                :disabled="adminForm.processing"
                class="w-full mt-2 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 disabled:opacity-50 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
            >
                {{ adminForm.processing ? t('auth.actions.logging_in') : t('auth.actions.login') }}
            </button>
        </form>

        <!-- Member form -->
        <form v-else @submit.prevent="joinBand" class="space-y-3">
            <!-- Invite link hint -->
            <div class="bg-indigo-50 border border-indigo-100 rounded-xl px-3 py-2.5 flex items-start gap-2.5">
                <svg class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                </svg>
                <p class="text-[11px] text-indigo-700 leading-relaxed">{{ t('auth.member_invite_hint') }}</p>
            </div>

            <div>
                <label for="code" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.band_code') }}</label>
                <input
                    id="code"
                    v-model="memberForm.code"
                    type="text"
                    required
                    maxlength="8"
                    autofocus
                    :placeholder="t('auth.placeholders.band_code')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 uppercase tracking-widest font-mono transition"
                />
                <p v-if="memberForm.errors.code" class="text-xs text-red-600 mt-1">{{ memberForm.errors.code }}</p>
            </div>
            <div>
                <label for="pin" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.pin') }}</label>
                <input
                    id="pin"
                    v-model="memberForm.pin"
                    type="password"
                    required
                    minlength="4"
                    maxlength="20"
                    :placeholder="t('auth.placeholders.pin')"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                />
                <p v-if="memberForm.errors.pin" class="text-xs text-red-600 mt-1">{{ memberForm.errors.pin }}</p>
            </div>
            <button
                type="submit"
                :disabled="memberForm.processing"
                class="w-full mt-2 py-2.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 disabled:opacity-50 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
            >
                {{ memberForm.processing ? t('auth.actions.joining') : t('auth.actions.join') }}
            </button>
        </form>

        <!-- Discrete register link (admin tab only) -->
        <p v-if="tab === 'admin'" class="text-center text-xs text-slate-500 mt-5">
            {{ t('auth.no_account') }}
            <Link href="/register" class="font-semibold text-indigo-600 hover:text-indigo-700 ml-1">{{ t('auth.create_band') }}</Link>
        </p>
    </AuthLayout>
</template>
