<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBytes, formatSince } from '@/Utils/adminFormat';

const props = defineProps({
    totals: Object,
    presence: Object,
    averages: Object,
    adoption: Object,
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
const adoptionRows = computed(() => [
    { key: 'audio', label: 'Suben audio' },
    { key: 'calendar', label: 'Usan el calendario' },
    { key: 'push', label: 'Reciben notificaciones' },
    { key: 'shared', label: 'Comparten repertorios' },
    { key: 'multi_band', label: 'Personas en más de una banda' },
].map(row => ({ ...row, ...props.adoption[row.key] })));

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
        <!-- Who is here. The only figure on this page about this minute
             rather than about the whole history. -->
        <section class="bg-slate-900 rounded-xl px-4 py-4 mb-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="relative flex w-2 h-2">
                    <span v-if="presence.online" class="absolute inline-flex w-full h-full rounded-full bg-emerald-400 opacity-75 animate-ping" />
                    <span class="relative inline-flex w-2 h-2 rounded-full" :class="presence.online ? 'bg-emerald-400' : 'bg-slate-600'" />
                </span>
                <h2 class="text-xs font-bold text-slate-200 uppercase tracking-widest">Actividad</h2>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <p class="text-2xl font-semibold text-white tabular-nums leading-none">
                        {{ presence.online === null ? '—' : presence.online }}
                    </p>
                    <p class="text-2xs font-medium text-slate-300 mt-1">
                        {{ presence.online === null ? 'sesiones fuera de la base' : 'conectados ahora' }}
                    </p>
                </div>
                <div>
                    <p class="text-2xl font-semibold text-white tabular-nums leading-none">{{ presence.today }}</p>
                    <p class="text-2xs font-medium text-slate-300 mt-1">entraron hoy</p>
                </div>
                <div>
                    <p class="text-2xl font-semibold text-white tabular-nums leading-none">{{ presence.week }}</p>
                    <p class="text-2xs font-medium text-slate-300 mt-1">últimos 7 días</p>
                </div>
                <div>
                    <p class="text-2xl font-semibold text-white tabular-nums leading-none">{{ presence.month }}</p>
                    <p class="text-2xs font-medium text-slate-300 mt-1">últimos 30 días</p>
                </div>
                <div>
                    <p class="text-2xl font-semibold text-slate-300 tabular-nums leading-none">{{ presence.never }}</p>
                    <p class="text-2xs font-medium text-slate-300 mt-1">sin registro</p>
                </div>
            </div>

            <!-- Said plainly, because a number that started counting last
                 Tuesday looks like a collapse if you assume it always ran. -->
            <p class="text-2xs text-slate-400 mt-3 leading-relaxed">
                «Conectados» sale de las sesiones de los últimos 5 minutos. El resto se mide desde que
                se instaló este panel, así que las bandas que no han vuelto desde entonces cuentan como «sin registro».
            </p>
        </section>

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
                <h2 class="text-sm font-semibold text-slate-900">La banda promedio</h2>
                <p class="text-xs text-slate-500 mt-0.5">Repartido entre todas las bandas, incluidas las vacías</p>

                <div class="mt-3 divide-y divide-slate-100">
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600">Miembros</span>
                        <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ averages.members_per_band }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600">Canciones</span>
                        <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ averages.songs_per_band }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600">Servicios</span>
                        <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ averages.services_per_band }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600">Almacenamiento</span>
                        <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ formatBytes(averages.bytes_per_band) }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600">Canciones por servicio</span>
                        <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ averages.songs_per_service }}</span>
                    </div>
                    <div class="flex items-center justify-between py-2">
                        <span class="text-sm text-slate-600">Versiones por canción</span>
                        <span class="text-sm font-semibold text-slate-900 tabular-nums">{{ averages.versions_per_song }}</span>
                    </div>
                </div>
            </section>

            <!-- What gets used, which is the only honest answer to what is
                 worth building more of. -->
            <section class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4">
                <h2 class="text-sm font-semibold text-slate-900">Qué se usa</h2>
                <p class="text-xs text-slate-500 mt-0.5">Proporción de bandas que llegó a cada función</p>

                <div class="mt-3 space-y-3">
                    <div v-for="row in adoptionRows" :key="row.key">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-sm text-slate-600 truncate">{{ row.label }}</span>
                            <span class="text-sm font-semibold text-slate-900 tabular-nums shrink-0">
                                {{ row.bands }}<span class="text-xs font-normal text-slate-500"> · {{ row.percent }}%</span>
                            </span>
                        </div>
                        <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden mt-1.5">
                            <div class="h-full bg-indigo-500 rounded-full" :style="{ width: row.percent + '%' }" />
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="grid lg:grid-cols-2 gap-4 mt-4">
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
