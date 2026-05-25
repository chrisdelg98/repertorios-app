<script setup>
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n/index.js';

const { locale, t } = useI18n();

const locales = [
    { code: 'en', flag: '🇬🇧', label: 'EN' },
    { code: 'es', flag: '🇪🇸', label: 'ES' },
];
</script>

<template>
    <div class="flex items-center gap-1" :aria-label="t('lang.switch')">
        <button
            v-for="loc in locales"
            :key="loc.code"
            @click="setLocale(loc.code)"
            :class="[
                'px-2.5 py-1 text-xs font-semibold rounded-md transition-colors',
                locale === loc.code
                    ? 'bg-indigo-600 text-white'
                    : 'text-slate-500 hover:bg-slate-100',
            ]"
        >
            {{ loc.flag }} {{ loc.label }}
        </button>
    </div>
</template>
