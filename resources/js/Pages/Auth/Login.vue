<script setup>
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const { t } = useI18n();

const tab        = ref('admin');
const adminForm  = useForm({ email: '', password: '' });
const memberForm = useForm({ code: '', pin: '' });

function loginAdmin() {
    adminForm.post('/login');
}

function joinBand() {
    memberForm.post('/join');
}

// Notes config: position, size, rotation, animation duration & delay
// Negative delays so notes appear mid-flight on first paint
const notes = [
    { left: '8%',  size: 36, dur: 22, delay: -2,  drift:  20, rot: -8  },
    { left: '22%', size: 24, dur: 26, delay: -10, drift: -25, rot:  12 },
    { left: '38%', size: 44, dur: 24, delay: -16, drift:  30, rot: -5  },
    { left: '55%', size: 28, dur: 28, delay: -6,  drift: -18, rot:  10 },
    { left: '70%', size: 38, dur: 23, delay: -19, drift:  22, rot: -10 },
    { left: '85%', size: 30, dur: 25, delay: -12, drift: -28, rot:  6  },
    { left: '15%', size: 26, dur: 27, delay: -22, drift:  15, rot: -14 },
    { left: '92%', size: 32, dur: 21, delay: -4,  drift: -20, rot:  8  },
];
</script>

<template>
    <Head :title="t('auth.login.title')" />

    <div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-50 via-white to-indigo-50 flex items-center justify-center px-4 py-10">

        <!-- Animated background music notes -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <svg
                v-for="(n, i) in notes"
                :key="i"
                class="absolute floating-note text-indigo-400/20"
                :style="{
                    left: n.left,
                    width: n.size + 'px',
                    height: n.size + 'px',
                    animationDuration: n.dur + 's',
                    animationDelay: n.delay + 's',
                    '--drift': n.drift + 'px',
                    '--rotation': n.rot + 'deg',
                }"
                fill="currentColor" viewBox="0 0 24 24"
            >
                <path d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
            </svg>
        </div>

        <!-- Soft radial glow behind card -->
        <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[28rem] h-[28rem] bg-gradient-to-br from-indigo-300/30 to-violet-300/20 rounded-full blur-3xl pointer-events-none" />

        <!-- Top bar: back + lang switcher -->
        <div class="absolute top-4 left-4 right-4 flex items-center justify-between z-10">
            <Link
                href="/"
                class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-indigo-600 transition-colors"
            >
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                {{ t('app.name') }}
            </Link>
            <LanguageSwitcher />
        </div>

        <!-- Login card -->
        <div class="relative w-full max-w-md">
            <!-- Brand mark -->
            <div class="text-center mb-6">
                <div class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-2xl flex items-center justify-center mx-auto shadow-lg shadow-indigo-300/50">
                    <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-slate-900 mt-3 tracking-tight">{{ t('auth.login.heading') }}</h1>
                <p class="text-sm text-slate-500 mt-1">{{ t('auth.login.subheading') }}</p>
            </div>

            <div class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-xl shadow-indigo-200/40 border border-slate-100 p-6">
                <!-- Tabs -->
                <div class="relative grid grid-cols-2 mb-5 border-b border-slate-100">
                    <button
                        type="button"
                        @click="tab = 'admin'"
                        class="py-2.5 text-sm font-semibold transition-colors"
                        :class="tab === 'admin' ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600'"
                    >
                        {{ t('auth.tabs.admin') }}
                    </button>
                    <button
                        type="button"
                        @click="tab = 'member'"
                        class="py-2.5 text-sm font-semibold transition-colors"
                        :class="tab === 'member' ? 'text-indigo-600' : 'text-slate-400 hover:text-slate-600'"
                    >
                        {{ t('auth.tabs.member') }}
                    </button>
                    <!-- Animated underline -->
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
                        <label for="password" class="block text-xs font-medium text-slate-600 mb-1">{{ t('auth.fields.password') }}</label>
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
            </div>

            <!-- Footer hint -->
            <p class="text-center text-[11px] text-slate-400 mt-5">
                {{ t('app.tagline') }}
            </p>
        </div>
    </div>
</template>

<style scoped>
@keyframes float-up {
    0%   { transform: translateY(0) translateX(0) rotate(0deg); opacity: 0; }
    10%  { opacity: 1; }
    90%  { opacity: 1; }
    100% { transform: translateY(-110vh) translateX(var(--drift, 20px)) rotate(var(--rotation, 10deg)); opacity: 0; }
}

.floating-note {
    bottom: -64px;
    animation-name: float-up;
    animation-timing-function: linear;
    animation-iteration-count: infinite;
    will-change: transform, opacity;
}

@media (prefers-reduced-motion: reduce) {
    .floating-note { animation: none; opacity: 0; }
}
</style>
