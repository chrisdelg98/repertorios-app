<script setup>
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const { t } = useI18n();

defineProps({
    laravelVersion: String,
    phpVersion: String,
    appName: String,
});
</script>

<template>
    <Head :title="t('welcome.title')" />

    <main class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="max-w-md w-full text-center space-y-6">
            <div class="flex justify-end">
                <LanguageSwitcher />
            </div>

            <div class="space-y-2">
                <p class="text-4xl">🎶</p>
                <h1 class="text-3xl font-semibold tracking-tight">{{ appName }}</h1>
                <p class="text-slate-500">{{ t('app.tagline') }}</p>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                <p class="font-medium">✓ {{ t('welcome.status') }}</p>
                <p class="text-sm opacity-80 mt-1">
                    {{ t('welcome.versions', { laravel: laravelVersion, php: phpVersion }) }}
                </p>
            </div>

            <div class="text-sm text-slate-500 space-y-1">
                <p>✓ {{ t('welcome.phase0') }}</p>
                <p>✓ {{ t('welcome.phase1') }}</p>
                <p class="text-indigo-600 font-medium">→ {{ t('welcome.next') }}</p>
            </div>
        </div>
    </main>
</template>
