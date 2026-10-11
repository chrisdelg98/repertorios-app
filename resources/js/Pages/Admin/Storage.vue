<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBytes, formatDate } from '@/Utils/adminFormat';

const props = defineProps({
    report: { type: Object, default: null },
});

const scanning = ref(false);

function scan() {
    scanning.value = true;

    router.post('/admin/storage', {}, {
        preserveScroll: true,
        onFinish: () => { scanning.value = false; },
    });
}

const ok = computed(() => props.report && !props.report.failed && props.report.configured);

/** The gap is the headline: everything else explains it. */
const drift = computed(() =>
    ok.value ? props.report.bucket_bytes - props.report.database_bytes : 0
);
</script>

<template>
    <Head title="Panel · Almacenamiento" />

    <AdminLayout>
        <div class="flex items-start justify-between gap-3 mb-4">
            <div>
                <h1 class="text-lg font-semibold text-slate-900">Almacenamiento real</h1>
                <p class="text-xs text-slate-500 mt-0.5 max-w-xl leading-relaxed">
                    Compara lo que hay en el bucket contra lo que la base tiene registrado.
                    Recorrer el bucket son varias llamadas a Cloudflare, por eso no se hace solo.
                </p>
            </div>

            <button
                type="button"
                @click="scan"
                :disabled="scanning"
                class="shrink-0 px-4 py-2 text-sm font-semibold text-white bg-slate-900 hover:bg-slate-800 rounded-lg disabled:opacity-50 transition-colors"
            >{{ scanning ? 'Revisando...' : 'Revisar ahora' }}</button>
        </div>

        <p v-if="report && !report.configured" class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-xl px-4 py-3">
            El almacenamiento en R2 no está configurado en este servidor.
        </p>

        <p v-else-if="report && report.failed" class="bg-red-50 border border-red-200 text-red-900 text-sm rounded-xl px-4 py-3">
            El bucket no respondió, o tiene más archivos de los que esta revisión recorre de una vez.
            No se muestra nada para no reportar como perdido lo que sólo no se alcanzó a mirar.
        </p>

        <template v-else-if="ok">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-3.5 py-3.5">
                    <p class="text-xs font-medium text-slate-500">En el bucket</p>
                    <p class="text-xl font-semibold text-slate-900 tabular-nums mt-1 leading-none">{{ formatBytes(report.bucket_bytes) }}</p>
                    <p class="text-2xs text-slate-500 mt-1">{{ report.bucket_objects }} archivos</p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-3.5 py-3.5">
                    <p class="text-xs font-medium text-slate-500">En la base</p>
                    <p class="text-xl font-semibold text-slate-900 tabular-nums mt-1 leading-none">{{ formatBytes(report.database_bytes) }}</p>
                    <p class="text-2xs text-slate-500 mt-1">{{ report.database_rows }} registros</p>
                </div>
                <div
                    class="rounded-xl border shadow-sm px-3.5 py-3.5"
                    :class="report.orphan_count ? 'bg-amber-50 border-amber-200' : 'bg-white border-slate-200'"
                >
                    <p class="text-xs font-medium" :class="report.orphan_count ? 'text-amber-800' : 'text-slate-500'">Huérfanos</p>
                    <p class="text-xl font-semibold tabular-nums mt-1 leading-none" :class="report.orphan_count ? 'text-amber-900' : 'text-slate-900'">
                        {{ formatBytes(report.orphan_bytes) }}
                    </p>
                    <p class="text-2xs mt-1" :class="report.orphan_count ? 'text-amber-700' : 'text-slate-500'">
                        {{ report.orphan_count }} archivos que nadie puede abrir
                    </p>
                </div>
                <div
                    class="rounded-xl border shadow-sm px-3.5 py-3.5"
                    :class="report.missing_count ? 'bg-red-50 border-red-200' : 'bg-white border-slate-200'"
                >
                    <p class="text-xs font-medium" :class="report.missing_count ? 'text-red-800' : 'text-slate-500'">Faltantes</p>
                    <p class="text-xl font-semibold tabular-nums mt-1 leading-none" :class="report.missing_count ? 'text-red-900' : 'text-slate-900'">
                        {{ report.missing_count }}
                    </p>
                    <p class="text-2xs mt-1" :class="report.missing_count ? 'text-red-700' : 'text-slate-500'">
                        pistas que la app promete y no existen
                    </p>
                </div>
            </div>

            <p class="text-2xs text-slate-500 mt-2 px-1">
                Revisado el {{ formatDate(report.scanned_at) }} · diferencia de {{ formatBytes(Math.abs(drift)) }}
                {{ drift > 0 ? 'de más en el bucket' : drift < 0 ? 'de más en la base' : '— todo cuadra' }}
            </p>

            <!-- Orphans cost money every month and belong to nobody. -->
            <section v-if="report.orphans.length" class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4 mt-4">
                <h2 class="text-sm font-semibold text-slate-900">Archivos huérfanos</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    Están en el bucket pero ninguna canción los referencia. Se pagan cada mes y no cuentan en ninguna cuota.
                </p>

                <div class="mt-3 divide-y divide-slate-100">
                    <div v-for="object in report.orphans" :key="object.key" class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="text-xs text-slate-700 truncate font-mono">{{ object.key }}</p>
                            <p class="text-2xs text-slate-500 mt-0.5">{{ object.band ?? 'Banda desconocida' }}</p>
                        </div>
                        <p class="text-sm font-semibold text-slate-900 tabular-nums shrink-0">{{ formatBytes(object.size) }}</p>
                    </div>
                </div>

                <p v-if="report.orphan_count > report.orphans.length" class="text-2xs text-slate-500 mt-3">
                    Se muestran los {{ report.orphans.length }} más pesados de {{ report.orphan_count }}.
                </p>
            </section>

            <!-- A row pointing nowhere costs nothing and breaks a rehearsal. -->
            <section v-if="report.missing.length" class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4 mt-4">
                <h2 class="text-sm font-semibold text-slate-900">Pistas faltantes</h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    La app las ofrece pero el archivo no está. Quien las abra verá un reproductor roto.
                </p>

                <div class="mt-3 divide-y divide-slate-100">
                    <div v-for="row in report.missing" :key="row.id" class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <p class="text-sm text-slate-900 truncate">{{ row.name ?? row.key }}</p>
                            <p class="text-2xs text-slate-500 mt-0.5">{{ row.band ?? 'Banda desconocida' }}</p>
                        </div>
                        <p class="text-2xs text-slate-500 tabular-nums shrink-0">{{ formatBytes(row.size) }} esperados</p>
                    </div>
                </div>
            </section>

            <!-- The only one of the three that quietly skews every quota. -->
            <section v-if="report.mismatched.length" class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4 mt-4">
                <h2 class="text-sm font-semibold text-slate-900">Tamaños que no coinciden</h2>
                <p class="text-xs text-slate-500 mt-0.5">El registro dice un tamaño y el archivo pesa otro. Desvía la cuota de esa banda.</p>

                <div class="mt-3 divide-y divide-slate-100">
                    <div v-for="row in report.mismatched" :key="row.id" class="flex items-center justify-between gap-3 py-2">
                        <p class="text-xs text-slate-700 truncate font-mono min-w-0">{{ row.key }}</p>
                        <p class="text-2xs tabular-nums shrink-0">
                            <span class="text-slate-500">{{ formatBytes(row.db_size) }}</span>
                            <span class="text-slate-400"> → </span>
                            <span class="font-semibold text-slate-900">{{ formatBytes(row.real_size) }}</span>
                        </p>
                    </div>
                </div>
            </section>

            <p
                v-if="!report.orphans.length && !report.missing.length && !report.mismatched.length"
                class="bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm rounded-xl px-4 py-3 mt-4"
            >
                Todo cuadra. Cada archivo del bucket tiene su registro y cada registro su archivo.
            </p>
        </template>

        <p v-else class="text-sm text-slate-500 text-center py-12">
            Pulsa «Revisar ahora» para comparar el bucket con la base.
        </p>
    </AdminLayout>
</template>
