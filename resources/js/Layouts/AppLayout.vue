<script setup>
import { useI18n } from 'vue-i18n';
import { usePage, router, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';

const { t } = useI18n();
const page = usePage();
const auth = computed(() => page.props.auth);

const currentPath = computed(() => page.url.split('?')[0]);

function isActive(href) {
    if (href === '/dashboard') return currentPath.value === '/dashboard';
    return currentPath.value.startsWith(href);
}

const menuOpen = ref(false);

function toggleMenu() {
    menuOpen.value = !menuOpen.value;
}

function closeMenu() {
    menuOpen.value = false;
}

function logout() {
    menuOpen.value = false;
    router.post('/logout');
}

function onDocClick(e) {
    if (!e.target.closest('[data-profile-menu]')) menuOpen.value = false;
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

const userInitial = computed(() => {
    const src = auth.value.user?.name || auth.value.band?.name || '?';
    return src.charAt(0).toUpperCase();
});
</script>

<template>
    <div class="min-h-screen flex flex-col bg-slate-50">
        <!-- ── Header ──────────────────────────────────────────────── -->
        <header class="bg-white border-b border-slate-200 px-4 h-14 flex items-center justify-between sticky top-0 z-20">
            <Link href="/dashboard" class="flex items-center gap-2 min-w-0">
                <div class="w-8 h-8 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-lg flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                    </svg>
                </div>
                <span class="font-semibold text-slate-900 text-sm truncate">
                    {{ auth.band?.name ?? t('app.name') }}
                </span>
            </Link>

            <div class="flex items-center gap-2">
                <LanguageSwitcher />

                <!-- Profile menu -->
                <div class="relative" data-profile-menu>
                    <button
                        @click="toggleMenu"
                        class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors"
                        :aria-label="t('nav.logout')"
                    >
                        {{ userInitial }}
                    </button>

                    <!-- Dropdown -->
                    <Transition
                        enter-active-class="transition duration-150 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-100 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="menuOpen"
                            class="absolute right-0 mt-1.5 w-56 origin-top-right bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden"
                        >
                            <div class="px-4 py-3 border-b border-slate-100">
                                <p class="text-sm font-medium text-slate-900 truncate">
                                    {{ auth.user?.name || auth.band?.name }}
                                </p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    <span class="capitalize">{{ auth.access ? t('auth.access.' + auth.access) : '' }}</span>
                                    <span v-if="auth.user"> · {{ auth.user.email }}</span>
                                </p>
                            </div>
                            <button
                                @click="logout"
                                class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                {{ t('nav.logout') }}
                            </button>
                        </div>
                    </Transition>
                </div>
            </div>
        </header>

        <main class="flex-1 pb-24">
            <slot />
        </main>

        <!-- ── Bottom nav ──────────────────────────────────────────── -->
        <nav class="fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 z-10">
            <div class="grid grid-cols-5 items-end h-16 max-w-md mx-auto">
                <!-- Home -->
                <Link href="/dashboard" class="flex flex-col items-center gap-0.5 py-2.5" :class="isActive('/dashboard') ? 'text-indigo-600' : 'text-slate-400'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="text-[10px] font-medium">{{ t('nav.home') }}</span>
                </Link>

                <!-- Services -->
                <Link href="/services" class="flex flex-col items-center gap-0.5 py-2.5" :class="isActive('/services') ? 'text-indigo-600' : 'text-slate-400'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-[10px] font-medium">{{ t('nav.services') }}</span>
                </Link>

                <!-- Add (center FAB) -->
                <div class="flex justify-center">
                    <Link
                        href="/services/create"
                        class="w-14 h-14 -mt-6 bg-gradient-to-br from-indigo-500 to-violet-600 rounded-full flex items-center justify-center shadow-lg shadow-indigo-300/50 active:scale-95 transition-transform"
                        :aria-label="t('nav.add')"
                    >
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                    </Link>
                </div>

                <!-- Songs -->
                <Link href="/songs" class="flex flex-col items-center gap-0.5 py-2.5" :class="isActive('/songs') ? 'text-indigo-600' : 'text-slate-400'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                    </svg>
                    <span class="text-[10px] font-medium">{{ t('nav.songs') }}</span>
                </Link>

                <!-- Settings (admin only) / placeholder for members -->
                <Link
                    v-if="auth.access === 'admin'"
                    href="/settings/schedule-templates"
                    class="flex flex-col items-center gap-0.5 py-2.5"
                    :class="isActive('/settings') ? 'text-indigo-600' : 'text-slate-400'"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-[10px] font-medium">{{ t('nav.settings') }}</span>
                </Link>
                <div v-else />
            </div>
        </nav>
    </div>
</template>
