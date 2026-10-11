<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatSince, formatDate } from '@/Utils/adminFormat';

const props = defineProps({
    users: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ q: '' }) },
});

const search = ref(props.filters.q ?? '');

let debounce = null;

watch(search, (value) => {
    clearTimeout(debounce);

    debounce = setTimeout(() => {
        router.get('/admin/users', value ? { q: value } : {}, {
            preserveState: true,
            replace: true,
        });
    }, 350);
});

/**
 * A registration the browser quietly dropped looks exactly like a working one
 * until you notice it has not been used in months.
 */
function deviceLooksStale(device) {
    if (!device.last_used_at) return true;

    return Date.now() - Date.parse(device.last_used_at) > 1000 * 60 * 60 * 24 * 60;
}
</script>

<template>
    <Head title="Panel · Personas" />

    <AdminLayout>
        <h1 class="text-lg font-semibold text-slate-900 mb-1">Personas</h1>
        <p class="text-xs text-slate-500 mb-4">
            Busca por correo o nombre para ver a qué bandas llega una cuenta y si sus notificaciones están vivas.
        </p>

        <input
            :value="search"
            @input="search = $event.target.value"
            type="text"
            placeholder="correo@ejemplo.com"
            class="w-full px-3.5 py-2.5 text-sm rounded-xl border border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 mb-4"
        />

        <div v-if="users.length" class="space-y-3">
            <article
                v-for="user in users"
                :key="user.id"
                class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 truncate">
                            {{ user.name }}
                            <span v-if="user.is_platform_admin" class="text-2xs font-semibold text-white bg-slate-900 px-1.5 py-0.5 rounded ml-1">plataforma</span>
                        </p>
                        <p class="text-xs text-slate-600 truncate mt-0.5">{{ user.email }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-2xs text-slate-600">{{ formatSince(user.last_seen_at) }}</p>
                        <p class="text-2xs text-slate-400 mt-0.5">desde {{ formatDate(user.created_at) }}</p>
                    </div>
                </div>

                <!-- Which bands this account reaches. The first thing to check
                     when somebody says they cannot see their repertoire. -->
                <div class="mt-3 pt-3 border-t border-slate-100">
                    <p class="text-2xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Bandas</p>

                    <div v-if="user.bands.length" class="flex flex-wrap gap-1.5">
                        <Link
                            v-for="band in user.bands"
                            :key="band.id"
                            :href="`/admin/bands/${band.id}`"
                            class="inline-flex items-center gap-1.5 text-xs font-medium bg-slate-100 hover:bg-slate-200 rounded-md px-2 py-1 transition-colors"
                        >
                            {{ band.name }}
                            <span v-if="band.is_creator" class="text-2xs font-semibold text-indigo-600">creador</span>
                            <span v-else-if="band.role === 'admin'" class="text-2xs text-slate-500">admin</span>
                        </Link>
                    </div>
                    <p v-else class="text-xs text-amber-700">
                        No pertenece a ninguna banda. Si dice que no ve nada, esta es la razón.
                    </p>
                </div>

                <div class="mt-3 pt-3 border-t border-slate-100">
                    <p class="text-2xs font-semibold text-slate-500 uppercase tracking-wide mb-1.5">Notificaciones</p>

                    <div v-if="user.devices.length" class="space-y-1.5">
                        <div
                            v-for="device in user.devices"
                            :key="device.id"
                            class="flex items-center justify-between gap-3 text-xs"
                        >
                            <span class="text-slate-700 truncate">
                                {{ device.device }}
                                <span v-if="device.locale" class="text-slate-400">· {{ device.locale }}</span>
                            </span>
                            <span
                                class="shrink-0 text-2xs"
                                :class="deviceLooksStale(device) ? 'text-amber-700 font-semibold' : 'text-slate-500'"
                            >
                                {{ device.last_used_at ? formatSince(device.last_used_at) : 'nunca se usó' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-500">
                        Sin dispositivos registrados. No puede recibir notificaciones en ningún lado.
                    </p>
                </div>
            </article>
        </div>

        <p v-else-if="filters.q" class="text-sm text-slate-500 text-center py-12">
            Ninguna cuenta coincide con «{{ filters.q }}».
        </p>

        <p v-else class="text-sm text-slate-500 text-center py-12">
            Escribe un correo o un nombre para empezar.
        </p>
    </AdminLayout>
</template>
