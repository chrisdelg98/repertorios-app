<script setup>
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import Logo from '@/Components/Logo.vue';

defineProps({
    heading:    { type: String, default: '' },
    subheading: { type: String, default: '' },
});

const { t } = useI18n();

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

        <!-- Soft radial glow -->
        <div class="absolute top-1/3 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[28rem] h-[28rem] bg-gradient-to-br from-indigo-300/30 to-violet-300/20 rounded-full blur-3xl pointer-events-none" />

        <!-- Top bar -->
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

        <!-- Card wrapper -->
        <div class="relative w-full max-w-md">
            <!-- Brand mark -->
            <div class="text-center mb-6">
                <Logo :size="64" class="mx-auto" />
                <h1 v-if="heading" class="text-2xl font-bold text-slate-900 mt-3 tracking-tight">{{ heading }}</h1>
                <p v-if="$slots.subheading || subheading" class="text-sm text-slate-500 mt-1">
                    <slot name="subheading">{{ subheading }}</slot>
                </p>
            </div>

            <div class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-xl shadow-indigo-200/40 border border-slate-100 p-6">
                <slot />
            </div>

            <p class="text-center text-[11px] text-slate-500 mt-5">
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
