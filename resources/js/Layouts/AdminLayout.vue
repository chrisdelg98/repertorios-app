<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/**
 * Deliberately not AppLayout.
 *
 * No band switcher, no bottom bar, and a dark header, because the worst
 * mistake available here is reading another band's numbers while believing
 * they are your own. The chrome should never let the two feel alike.
 */

const page = usePage();
const currentPath = computed(() => page.url.split('?')[0]);

function isActive(href) {
    return href === '/admin'
        ? currentPath.value === '/admin'
        : currentPath.value.startsWith(href);
}

const tabs = [
    { href: '/admin', label: 'Resumen' },
    { href: '/admin/bands', label: 'Bandas' },
    { href: '/admin/storage', label: 'Almacenamiento' },
];
</script>

<template>
    <div class="min-h-screen bg-slate-100">
        <header class="bg-slate-900">
            <div class="px-4 lg:px-8 py-3 lg:max-w-5xl lg:mx-auto flex items-center justify-between gap-4">
                <div class="flex items-center gap-2.5 min-w-0">
                    <span class="w-7 h-7 rounded-lg bg-white/10 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                    </span>
                    <p class="text-sm font-bold text-white truncate">Panel de plataforma</p>
                </div>

                <Link href="/dashboard" class="shrink-0 text-xs font-semibold text-slate-300 hover:text-white transition-colors">
                    Volver a la app →
                </Link>
            </div>

            <div class="px-4 lg:px-8 lg:max-w-5xl lg:mx-auto flex items-center gap-1">
                <Link
                    v-for="tab in tabs"
                    :key="tab.href"
                    :href="tab.href"
                    class="px-3 py-2 text-xs font-semibold rounded-t-lg transition-colors"
                    :class="isActive(tab.href)
                        ? 'bg-slate-100 text-slate-900'
                        : 'text-slate-300 hover:text-white hover:bg-white/10'"
                >{{ tab.label }}</Link>
            </div>
        </header>

        <main class="px-4 lg:px-8 py-5 lg:py-8 lg:max-w-5xl lg:mx-auto">
            <slot />
        </main>
    </div>
</template>
