<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n/index.js';

/**
 * A dropdown rather than two side-by-side buttons.
 *
 * Both languages on display cost twice the width for something people set once
 * and never touch again — width the band name needs far more, since that is
 * what tells you which band you are looking at.
 */

const { locale, t } = useI18n();

const locales = [
    { code: 'en', flag: '🇬🇧', label: 'EN' },
    { code: 'es', flag: '🇪🇸', label: 'ES' },
];

const open = ref(false);

const current = computed(() => locales.find(l => l.code === locale.value) ?? locales[1]);

function choose(code) {
    setLocale(code);
    open.value = false;
}

function onDocClick(e) {
    if (!e.target.closest('[data-lang-switcher]')) open.value = false;
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div class="relative shrink-0" data-lang-switcher>
        <button
            type="button"
            @click.stop="open = !open"
            class="flex items-center gap-1 px-2 py-1.5 text-xs font-semibold text-slate-700 rounded-lg hover:bg-slate-100 transition-colors"
            :aria-label="t('lang.switch')"
            :aria-expanded="open"
        >
            <span>{{ current.flag }}</span>
            <span>{{ current.label }}</span>
            <svg
                class="w-3 h-3 text-slate-500 transition-transform"
                :class="open ? 'rotate-180' : ''"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute right-0 top-full mt-1 w-32 origin-top-right bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden z-50"
            >
                <button
                    v-for="loc in locales"
                    :key="loc.code"
                    type="button"
                    @click.stop="choose(loc.code)"
                    class="w-full flex items-center gap-2 px-3 py-2.5 text-sm font-medium text-left hover:bg-slate-50 transition-colors"
                    :class="locale === loc.code ? 'text-indigo-600' : 'text-slate-700'"
                >
                    <span>{{ loc.flag }}</span>
                    <span class="flex-1">{{ t('lang.' + loc.code) }}</span>
                    <svg
                        v-if="locale === loc.code"
                        class="w-4 h-4 shrink-0"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </button>
            </div>
        </Transition>
    </div>
</template>
