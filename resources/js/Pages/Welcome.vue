<script setup>
import { Head } from '@inertiajs/vue3'

defineProps({
    laravelVersion: String,
    phpVersion: String,
    appName: String,
})
</script>

<template>
    <Head title="Welcome" />

    <main class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="max-w-md w-full text-center space-y-6">
            <div class="space-y-2">
                <p class="text-4xl">🎶</p>
                <h1 class="text-3xl font-semibold tracking-tight">{{ appName }}</h1>
                <p class="text-slate-500">
                    Worship band repertoire platform
                </p>
            </div>

            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800">
                <p class="font-medium">✓ Inertia.js + Vue 3 + Tailwind running</p>
                <p class="text-sm opacity-80 mt-1">
                    Laravel {{ laravelVersion }} · PHP {{ phpVersion }}
                </p>
            </div>

            <div class="text-sm text-slate-500 space-y-1">
                <p><strong>Phase 0:</strong> Setup &amp; Foundation — done</p>
                <p>Next: i18n + PWA base</p>
            </div>
        </div>
    </main>
</template>
