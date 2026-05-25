<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const { t } = useI18n();
const tab = ref('admin');

const adminForm = useForm({ email: '', password: '' });
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

    <main class="min-h-screen flex items-start justify-center bg-slate-50 px-4 pt-12 pb-8">
        <div class="w-full max-w-sm space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-2xl">🎶</span>
                    <span class="font-semibold text-slate-900">{{ t('app.name') }}</span>
                </div>
                <LanguageSwitcher />
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <!-- Tabs -->
                <div class="flex border-b border-slate-200">
                    <button
                        @click="tab = 'admin'"
                        :class="[
                            'flex-1 py-3.5 text-sm font-semibold transition-colors',
                            tab === 'admin'
                                ? 'text-indigo-600 border-b-2 border-indigo-600'
                                : 'text-slate-500 hover:text-slate-700',
                        ]"
                    >
                        {{ t('auth.tabs.admin') }}
                    </button>
                    <button
                        @click="tab = 'member'"
                        :class="[
                            'flex-1 py-3.5 text-sm font-semibold transition-colors',
                            tab === 'member'
                                ? 'text-indigo-600 border-b-2 border-indigo-600'
                                : 'text-slate-500 hover:text-slate-700',
                        ]"
                    >
                        {{ t('auth.tabs.member') }}
                    </button>
                </div>

                <!-- Admin form -->
                <form v-if="tab === 'admin'" @submit.prevent="loginAdmin" class="p-5 space-y-4">
                    <div class="space-y-1.5">
                        <label for="email" class="block text-xs font-medium text-slate-600">
                            {{ t('auth.fields.email') }}
                        </label>
                        <input
                            id="email"
                            v-model="adminForm.email"
                            type="email"
                            autocomplete="email"
                            required
                            :placeholder="t('auth.placeholders.email')"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                        <p v-if="adminForm.errors.email" class="text-xs text-red-600">{{ adminForm.errors.email }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="password" class="block text-xs font-medium text-slate-600">
                            {{ t('auth.fields.password') }}
                        </label>
                        <input
                            id="password"
                            v-model="adminForm.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            :placeholder="t('auth.placeholders.password')"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                        />
                        <p v-if="adminForm.errors.password" class="text-xs text-red-600">{{ adminForm.errors.password }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="adminForm.processing"
                        class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
                    >
                        {{ adminForm.processing ? t('auth.actions.logging_in') : t('auth.actions.login') }}
                    </button>
                </form>

                <!-- Member form -->
                <form v-else @submit.prevent="joinBand" class="p-5 space-y-4">
                    <div class="space-y-1.5">
                        <label for="code" class="block text-xs font-medium text-slate-600">
                            {{ t('auth.fields.band_code') }}
                        </label>
                        <input
                            id="code"
                            v-model="memberForm.code"
                            type="text"
                            autocomplete="off"
                            required
                            maxlength="6"
                            :placeholder="t('auth.placeholders.band_code')"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent uppercase tracking-widest font-mono"
                            @input="memberForm.code = memberForm.code.toUpperCase()"
                        />
                        <p v-if="memberForm.errors.code" class="text-xs text-red-600">{{ memberForm.errors.code }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="pin" class="block text-xs font-medium text-slate-600">
                            {{ t('auth.fields.pin') }}
                        </label>
                        <input
                            id="pin"
                            v-model="memberForm.pin"
                            type="password"
                            inputmode="numeric"
                            autocomplete="off"
                            required
                            minlength="4"
                            maxlength="8"
                            :placeholder="t('auth.placeholders.pin')"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent tracking-widest font-mono"
                        />
                        <p v-if="memberForm.errors.pin" class="text-xs text-red-600">{{ memberForm.errors.pin }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="memberForm.processing"
                        class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
                    >
                        {{ memberForm.processing ? t('auth.actions.joining') : t('auth.actions.join') }}
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-slate-400">{{ t('app.tagline') }}</p>
        </div>
    </main>
</template>
