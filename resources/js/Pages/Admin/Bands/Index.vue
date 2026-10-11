<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBytes, formatSince } from '@/Utils/adminFormat';

const props = defineProps({
    bands: Object,
    filters: { type: Object, default: () => ({ q: '' }) },
    default_quota_mb: Number,
});

const search = ref(props.filters.q ?? '');

let debounce = null;

watch(search, (value) => {
    clearTimeout(debounce);

    // A keystroke per query would hammer a table that scans every band.
    debounce = setTimeout(() => {
        router.get('/admin/bands', value ? { q: value } : {}, {
            preserveState: true,
            replace: true,
        });
    }, 350);
});

/** Amber once a band is near its ceiling, red once it is over. */
function barColour(percent) {
    if (percent >= 100) return 'bg-red-500';
    return percent >= 80 ? 'bg-amber-500' : 'bg-indigo-500';
}
</script>

<template>
    <Head title="Panel · Bandas" />

    <AdminLayout>
        <div class="flex items-center justify-between gap-3 mb-4">
            <h1 class="text-lg font-semibold text-slate-900">Bandas</h1>
            <p class="text-xs text-slate-500 shrink-0">
                Cuota por defecto: <span class="font-semibold text-slate-700">{{ default_quota_mb }} MB</span>
            </p>
        </div>

        <input
            :value="search"
            @input="search = $event.target.value"
            type="text"
            placeholder="Buscar por nombre o código..."
            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-4"
        />

        <div v-if="bands.data.length" class="space-y-2">
            <Link
                v-for="band in bands.data"
                :key="band.id"
                :href="`/admin/bands/${band.id}`"
                class="block bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-3.5 hover:border-slate-300 hover:shadow-md transition"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 truncate">{{ band.name }}</p>
                        <p class="text-xs text-slate-500 truncate mt-0.5">
                            {{ band.code }}<span v-if="band.creator"> · {{ band.creator }}</span>
                            · {{ band.members }} {{ band.members === 1 ? 'miembro' : 'miembros' }}
                        </p>
                    </div>

                    <div class="text-right shrink-0">
                        <p class="text-sm font-semibold text-slate-900 tabular-nums">{{ formatBytes(band.storage_bytes) }}</p>
                        <p class="text-2xs text-slate-500 tabular-nums">
                            de {{ formatBytes(band.quota_bytes) }}
                            <span v-if="band.has_override" class="text-indigo-600 font-semibold">· propia</span>
                        </p>
                    </div>
                </div>

                <div class="h-1.5 bg-slate-100 rounded-full overflow-hidden mt-2.5">
                    <div class="h-full rounded-full" :class="barColour(band.percent)" :style="{ width: band.percent + '%' }" />
                </div>

                <p class="text-2xs text-slate-500 mt-2">{{ formatSince(band.team_last_seen_at) }}</p>
            </Link>
        </div>

        <p v-else class="text-sm text-slate-500 text-center py-12">
            {{ filters.q ? 'Ninguna banda coincide con la búsqueda.' : 'Todavía no hay bandas.' }}
        </p>

        <div v-if="bands.links && bands.last_page > 1" class="flex items-center justify-center gap-1 mt-5">
            <Link
                v-for="link in bands.links"
                :key="link.label"
                :href="link.url ?? ''"
                :disabled="!link.url"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors"
                :class="link.active
                    ? 'bg-slate-900 text-white'
                    : link.url ? 'text-slate-600 hover:bg-white' : 'text-slate-300 pointer-events-none'"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
