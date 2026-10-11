<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBytes, formatSince } from '@/Utils/adminFormat';

const props = defineProps({
    totals: Object,
    growth: { type: Array, default: () => [] },
    dormant: { type: Array, default: () => [] },
    top_storage: { type: Array, default: () => [] },
});

const cards = computed(() => [
    { label: 'Bandas', value: props.totals.bands },
    { label: 'Usuarios', value: props.totals.users },
    { label: 'Canciones', value: props.totals.songs },
    { label: 'Servicios', value: props.totals.services },
    { label: 'Pistas', value: props.totals.tracks },
    { label: 'Almacenado', value: formatBytes(props.totals.stored_bytes) },
]);

/** The tallest month sets the scale; everything else is read against it. */
const peak = computed(() =>
    Math.max(1, ...props.growth.map(m => Math.max(m.bands, m.users)))
);

function monthLabel(key) {
    const [y, m] = key.split('-');
    return new Date(Number(y), Number(m) - 1, 1)
        .toLocaleDateString('es', { month: 'short' })
        .replace('.', '');
}
</script>

<template>
    <Head title="Panel · Resumen" />

    <AdminLayout>
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-2.5 lg:gap-3">
            <div
                v-for="card in cards"
                :key="card.label"
                class="bg-white rounded-xl border border-slate-200 shadow-sm px-3.5 py-3.5"
            >
                <p class="text-xs font-medium text-slate-500 truncate">{{ card.label }}</p>
                <p class="text-xl lg:text-2xl font-semibold text-slate-900 tracking-tight tabular-nums mt-1 leading-none">
                    {{ card.value }}
                </p>
            </div>
        </div>

        <!-- The figure comes out of the database, which only knows about
             uploads it was told about. Saying so beats being believed. -->
        <p class="text-2xs text-slate-500 mt-2 px-1">
            El almacenamiento se calcula sobre lo registrado en la base, no sobre el contenido real del bucket.
        </p>

        <div class="grid lg:grid-cols-2 gap-4 mt-5">
            <section class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Altas por mes</h2>
                <p class="text-xs text-slate-500 mt-0.5">Últimos 12 meses</p>

                <div class="flex items-end gap-1.5 h-32 mt-4">
                    <div v-for="month in growth" :key="month.month" class="flex-1 flex flex-col items-center gap-1 min-w-0">
                        <div class="w-full flex items-end justify-center gap-0.5 h-24">
                            <div
                                class="w-1/2 bg-indigo-500 rounded-t"
                                :style="{ height: `${(month.bands / peak) * 100}%` }"
                                :title="`${month.bands} bandas`"
                            />
                            <div
                                class="w-1/2 bg-violet-300 rounded-t"
                                :style="{ height: `${(month.users / peak) * 100}%` }"
                                :title="`${month.users} usuarios`"
                            />
                        </div>
                        <span class="text-2xs text-slate-400 truncate">{{ monthLabel(month.month) }}</span>
                    </div>
                </div>

                <div class="flex items-center gap-4 mt-3 text-2xs text-slate-600">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-indigo-500" /> Bandas</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-sm bg-violet-300" /> Usuarios</span>
                </div>
            </section>

            <section class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Más almacenamiento</h2>
                <p class="text-xs text-slate-500 mt-0.5">Las 10 bandas que más ocupan</p>

                <div v-if="top_storage.length" class="mt-3 divide-y divide-slate-100">
                    <Link
                        v-for="band in top_storage"
                        :key="band.id"
                        :href="`/admin/bands/${band.id}`"
                        class="flex items-center justify-between gap-3 py-2 hover:bg-slate-50 -mx-1 px-1 rounded transition-colors"
                    >
                        <span class="text-sm text-slate-700 truncate">{{ band.name }}</span>
                        <span class="text-sm font-semibold text-slate-900 tabular-nums shrink-0">{{ formatBytes(band.bytes) }}</span>
                    </Link>
                </div>
                <p v-else class="text-sm text-slate-500 py-6 text-center">Nadie ha subido audio todavía.</p>
            </section>
        </div>

        <!-- Storage held for people who stopped coming is the only cost here
             that nobody is getting anything for. -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4 mt-4">
            <h2 class="text-sm font-semibold text-slate-900">Inactivas con archivos</h2>
            <p class="text-xs text-slate-500 mt-0.5">
                Bandas con audio guardado donde nadie entra desde hace más de 60 días
            </p>

            <div v-if="dormant.length" class="mt-3 divide-y divide-slate-100">
                <Link
                    v-for="band in dormant"
                    :key="band.id"
                    :href="`/admin/bands/${band.id}`"
                    class="flex items-center justify-between gap-3 py-2.5 hover:bg-slate-50 -mx-1 px-1 rounded transition-colors"
                >
                    <span class="min-w-0">
                        <span class="block text-sm text-slate-900 truncate">{{ band.name }}</span>
                        <span class="block text-xs text-slate-500">{{ formatSince(band.team_last_seen_at) }}</span>
                    </span>
                    <span class="text-sm font-semibold text-amber-700 tabular-nums shrink-0">{{ formatBytes(band.bytes) }}</span>
                </Link>
            </div>
            <p v-else class="text-sm text-slate-500 py-6 text-center">Ninguna. Todo lo guardado pertenece a bandas activas.</p>
        </section>
    </AdminLayout>
</template>
