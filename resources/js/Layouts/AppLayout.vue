<script setup>
import { useI18n } from 'vue-i18n';
import { usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const { t } = useI18n();
const page = usePage();
const auth = computed(() => page.props.auth);

function logout() {
    router.post('/logout');
}

const navItems = [
    { label: 'nav.home', href: '/dashboard', icon: 'home' },
    { label: 'nav.services', href: '#', icon: 'calendar' },
    { label: 'nav.add', href: '#', icon: 'plus', primary: true },
    { label: 'nav.songs', href: '#', icon: 'music' },
];
</script>

<template>
    <div class="min-h-screen flex flex-col bg-slate-50">
        <header class="bg-white border-b border-slate-200 px-4 h-14 flex items-center justify-between sticky top-0 z-10">
            <div class="flex items-center gap-2">
                <span class="text-lg">🎶</span>
                <span class="font-semibold text-slate-900 text-sm truncate max-w-40">
                    {{ auth.band?.name ?? t('app.name') }}
                </span>
            </div>
            <div class="flex items-center gap-3">
                <LanguageSwitcher />
                <span class="text-[10px] font-medium text-slate-400 uppercase tracking-wide">
                    {{ auth.access ? t('auth.access.' + auth.access) : '' }}
                </span>
            </div>
        </header>

        <main class="flex-1 pb-20">
            <slot />
        </main>

        <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200">
            <div class="flex items-end justify-around px-1 h-16">
                <template v-for="item in navItems" :key="item.icon">
                    <a
                        v-if="!item.primary"
                        :href="item.href"
                        class="flex flex-col items-center gap-0.5 py-2 px-3 text-slate-400 hover:text-indigo-600 transition-colors"
                    >
                        <!-- Home -->
                        <svg v-if="item.icon === 'home'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                        </svg>
                        <!-- Calendar -->
                        <svg v-if="item.icon === 'calendar'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <!-- Music -->
                        <svg v-if="item.icon === 'music'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                        </svg>
                        <span class="text-[10px] font-medium">{{ t(item.label) }}</span>
                    </a>

                    <!-- Add (center, elevated) -->
                    <a v-else :href="item.href" class="flex flex-col items-center gap-0.5 pb-1">
                        <div class="w-12 h-12 bg-indigo-600 rounded-full flex items-center justify-center -mt-5 shadow-lg shadow-indigo-200">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                        </div>
                        <span class="text-[10px] font-medium text-slate-400">{{ t(item.label) }}</span>
                    </a>
                </template>

                <!-- Logout (last item) -->
                <button
                    @click="logout"
                    class="flex flex-col items-center gap-0.5 py-2 px-3 text-slate-400 hover:text-red-500 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="text-[10px] font-medium">{{ t('nav.logout') }}</span>
                </button>
            </div>
        </nav>
    </div>
</template>
