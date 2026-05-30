<script setup>
import { useI18n } from 'vue-i18n';
import { usePage, router, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import LanguageSwitcher from '@/Components/LanguageSwitcher.vue';
import Logo from '@/Components/Logo.vue';
import WelcomeOverlay from '@/Components/WelcomeOverlay.vue';

const donateUrl = computed(() => page.props.donate?.url || null);

function openDonate() {
    if (!donateUrl.value) return;
    window.open(donateUrl.value, '_blank', 'noopener,noreferrer');
}

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

const navItems = computed(() => {
    const base = [
        { href: '/dashboard', label: t('nav.home'),     icon: 'home' },
        { href: '/services',  label: t('nav.services'), icon: 'calendar' },
        { href: '/songs',     label: t('nav.songs'),    icon: 'music' },
    ];
    if (auth.value.access === 'admin') {
        base.push({ href: '/settings', label: t('nav.settings'), icon: 'settings' });
    }
    return base;
});
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <!-- ─────────────────────────────────────────────────────────── -->
        <!-- Desktop sidebar (lg+)                                       -->
        <!-- ─────────────────────────────────────────────────────────── -->
        <aside class="hidden lg:flex fixed inset-y-0 left-0 w-64 bg-white border-r border-slate-200 flex-col z-30">
            <!-- Brand -->
            <div class="px-5 py-5">
                <Link href="/dashboard" class="flex items-center gap-2.5 min-w-0">
                    <Logo :size="36" />
                    <span class="font-bold text-slate-900 truncate">{{ auth.band?.name ?? t('app.name') }}</span>
                </Link>
            </div>

            <!-- New Service CTA (write access only) -->
            <div v-if="auth.can_write" class="px-3 pb-3">
                <Link
                    href="/services/create"
                    class="flex items-center justify-center gap-2 px-3 py-2.5 bg-gradient-to-br from-indigo-600 to-violet-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 hover:shadow-lg active:scale-[0.98] transition"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('nav.add') }}
                </Link>
            </div>

            <!-- Nav items -->
            <nav class="flex-1 px-3 space-y-0.5 overflow-y-auto">
                <Link
                    v-for="item in navItems"
                    :key="item.href"
                    :href="item.href"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                    :class="isActive(item.href) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                >
                    <svg v-if="item.icon === 'home'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <svg v-else-if="item.icon === 'calendar'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <svg v-else-if="item.icon === 'music'" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                    </svg>
                    <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    {{ item.label }}
                </Link>
            </nav>

            <!-- Upgrade temp-member to registered account (subtle) -->
            <div v-if="auth.access === 'member' && !auth.user" class="px-3 pb-2">
                <Link
                    href="/upgrade"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-indigo-600 hover:bg-indigo-50 active:bg-indigo-100 transition-colors"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20 8v6M23 11h-6" />
                    </svg>
                    {{ t('auth.upgrade.sidebar_cta') }}
                </Link>
            </div>

            <!-- Support project (donate) -->
            <div v-if="donateUrl" class="px-3 pb-2">
                <button
                    @click="openDonate"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium text-rose-600 hover:bg-rose-50 active:bg-rose-100 transition-colors"
                >
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                    </svg>
                    {{ t('donate.tile_title') }}
                </button>
            </div>

            <!-- Profile / Logout (bottom) -->
            <div class="px-3 py-3 border-t border-slate-100">
                <div class="flex items-center gap-3 px-2.5 py-2 rounded-xl bg-slate-50 border border-slate-100">
                    <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center text-sm font-bold text-white shrink-0 shadow-sm">
                        <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="w-full h-full object-cover" />
                        <span v-else>{{ userInitial }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-900 truncate leading-tight">{{ auth.user?.name || auth.band?.name }}</p>
                        <p class="text-[11px] text-slate-500 truncate mt-0.5">
                            {{ auth.user?.email || (auth.access ? t('auth.access.' + auth.access) : '') }}
                        </p>
                    </div>
                    <button
                        @click="logout"
                        class="w-9 h-9 flex items-center justify-center rounded-lg bg-red-50 text-red-500 border border-red-100 hover:bg-red-100 hover:text-red-600 hover:border-red-200 active:scale-95 transition shrink-0"
                        :title="t('nav.logout')"
                        :aria-label="t('nav.logout')"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- ─────────────────────────────────────────────────────────── -->
        <!-- Mobile header (hidden on lg+)                               -->
        <!-- ─────────────────────────────────────────────────────────── -->
        <header class="lg:hidden bg-white border-b border-slate-200 px-4 h-14 flex items-center justify-between sticky top-0 z-20">
            <Link href="/dashboard" class="flex items-center gap-2 min-w-0">
                <Logo :size="32" />
                <span class="font-semibold text-slate-900 text-sm truncate">
                    {{ auth.band?.name ?? t('app.name') }}
                </span>
            </Link>

            <div class="flex items-center gap-2">
                <LanguageSwitcher />

                <div class="relative" data-profile-menu>
                    <button
                        @click="toggleMenu"
                        class="w-8 h-8 rounded-full overflow-hidden bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600 hover:bg-slate-200 transition-colors"
                        :aria-label="t('nav.logout')"
                    >
                        <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" class="w-full h-full object-cover" />
                        <span v-else>{{ userInitial }}</span>
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
                            <Link
                                v-if="auth.access === 'member' && !auth.user"
                                href="/upgrade"
                                @click="menuOpen = false"
                                class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-indigo-600 hover:bg-indigo-50 transition-colors border-b border-slate-100"
                            >
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M20 8v6M23 11h-6" />
                                </svg>
                                {{ t('auth.upgrade.sidebar_cta') }}
                            </Link>
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

        <!-- ─────────────────────────────────────────────────────────── -->
        <!-- Desktop top bar (lg+ only)                                  -->
        <!-- ─────────────────────────────────────────────────────────── -->
        <header class="hidden lg:flex lg:ml-64 h-14 items-center justify-end gap-3 px-6 border-b border-slate-200 bg-white sticky top-0 z-10">
            <LanguageSwitcher />
        </header>

        <!-- ─────────────────────────────────────────────────────────── -->
        <!-- Main content                                                -->
        <!-- ─────────────────────────────────────────────────────────── -->
        <main class="lg:ml-64 pb-24 lg:pb-12">
            <slot />
        </main>

        <!-- Welcome overlay (admin first login) -->
        <WelcomeOverlay v-if="auth.show_welcome" />

        <!-- ─────────────────────────────────────────────────────────── -->
        <!-- Mobile bottom nav (hidden on lg+)                           -->
        <!-- ─────────────────────────────────────────────────────────── -->
        <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 z-10">
            <div class="flex items-end justify-around h-16 max-w-md mx-auto px-2">
                <!-- Home -->
                <Link href="/dashboard" class="flex-1 flex flex-col items-center gap-0.5 py-2.5" :class="isActive('/dashboard') ? 'text-indigo-600' : 'text-slate-500'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span class="text-[10px] font-medium">{{ t('nav.home') }}</span>
                </Link>

                <!-- Services -->
                <Link href="/services" class="flex-1 flex flex-col items-center gap-0.5 py-2.5" :class="isActive('/services') ? 'text-indigo-600' : 'text-slate-500'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <span class="text-[10px] font-medium">{{ t('nav.services') }}</span>
                </Link>

                <!-- Add (center FAB) — write access only -->
                <div v-if="auth.can_write" class="flex-1 flex justify-center">
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
                <Link href="/songs" class="flex-1 flex flex-col items-center gap-0.5 py-2.5" :class="isActive('/songs') ? 'text-indigo-600' : 'text-slate-500'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2z" />
                    </svg>
                    <span class="text-[10px] font-medium">{{ t('nav.songs') }}</span>
                </Link>

                <!-- Settings (admin only) -->
                <Link
                    v-if="auth.access === 'admin'"
                    href="/settings"
                    class="flex-1 flex flex-col items-center gap-0.5 py-2.5"
                    :class="isActive('/settings') ? 'text-indigo-600' : 'text-slate-500'"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-[10px] font-medium">{{ t('nav.settings') }}</span>
                </Link>
            </div>
        </nav>
    </div>
</template>
