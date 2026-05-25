<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();
const page = usePage();
const auth = computed(() => page.props.auth);
</script>

<template>
    <Head :title="t('nav.home')" />

    <AppLayout>
        <div class="px-4 py-6 space-y-4">
            <!-- Welcome card -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-indigo-100 rounded-full flex items-center justify-center shrink-0">
                        <span class="text-lg">🎶</span>
                    </div>
                    <div class="min-w-0">
                        <h1 class="font-semibold text-slate-900 truncate">{{ auth.band?.name }}</h1>
                        <p class="text-xs text-slate-500 capitalize">
                            {{ auth.access ? t('auth.access.' + auth.access) : '' }}
                            <span v-if="auth.user"> · {{ auth.user.email }}</span>
                        </p>
                    </div>
                </div>
                <p class="text-sm text-slate-500">{{ t('dashboard.welcome') }}</p>
            </div>

            <!-- Quick stats -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-white rounded-xl p-4 border border-slate-200 text-center">
                    <p class="text-2xl font-bold text-indigo-600">0</p>
                    <p class="text-xs text-slate-500 mt-1">{{ t('nav.services') }}</p>
                </div>
                <div class="bg-white rounded-xl p-4 border border-slate-200 text-center">
                    <p class="text-2xl font-bold text-indigo-600">0</p>
                    <p class="text-xs text-slate-500 mt-1">{{ t('nav.songs') }}</p>
                </div>
            </div>

            <!-- Progress indicator -->
            <div class="bg-indigo-50 rounded-xl p-4 border border-indigo-100 space-y-1">
                <p class="text-xs font-medium text-indigo-700">✓ {{ t('welcome.phase0') }}</p>
                <p class="text-xs font-medium text-indigo-700">✓ {{ t('welcome.phase1') }}</p>
                <p class="text-xs font-medium text-indigo-700">✓ Phase 2: Authentication — done</p>
                <p class="text-xs text-indigo-500 mt-1">→ {{ t('dashboard.next_phase') }}</p>
            </div>
        </div>
    </AppLayout>
</template>
