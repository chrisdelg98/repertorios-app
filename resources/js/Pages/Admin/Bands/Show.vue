<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatBytes, formatMb, formatSince, formatDate } from '@/Utils/adminFormat';

const props = defineProps({
    band: Object,
    members: { type: Array, default: () => [] },
    default_quota_mb: Number,
    history: { type: Array, default: () => [] },
});

const stats = computed(() => [
    { label: 'Miembros', value: props.band.members },
    { label: 'Canciones', value: props.band.songs },
    { label: 'Servicios', value: props.band.services },
    { label: 'Calendario', value: props.band.entries },
    { label: 'Pistas', value: props.band.tracks },
]);

// ── The one control that writes ──────────────────────────────────
const editing = ref(false);

const form = useForm({ audio_quota_mb: props.band.audio_quota_mb });

function save() {
    form
        .transform(data => ({ audio_quota_mb: data.audio_quota_mb === '' ? null : data.audio_quota_mb }))
        .put(`/admin/bands/${props.band.id}/quota`, {
            preserveScroll: true,
            onSuccess: () => { editing.value = false; },
        });
}

/** Clearing it is not the same as setting zero: it hands the band back to the default. */
function useDefault() {
    form.audio_quota_mb = null;
    save();
}

const actionLabel = {
    'band.quota_changed': 'Cambió la cuota',
    'band.owner_transferred': 'Transfirió la propiedad',
    'panel.opened': 'Abrió el panel',
};

/**
 * Handing the band to someone else in it.
 *
 * Behind a confirmation because it is the one thing in this panel that takes
 * something away from somebody: the previous creator keeps their place in the
 * band and loses the right to manage it.
 */
const transferring = ref(false);

const ownerForm = useForm({ user_id: null });

// By id, never by name: two people called Daniel in one band would otherwise
// hide the wrong one from the list.
const candidates = computed(() =>
    props.members.filter(member => member.id !== props.band.creator_id)
);

function transfer() {
    if (!ownerForm.user_id) return;

    ownerForm.put(`/admin/bands/${props.band.id}/owner`, {
        preserveScroll: true,
        onSuccess: () => {
            transferring.value = false;
            ownerForm.reset();
        },
    });
}
</script>

<template>
    <Head :title="`Panel · ${band.name}`" />

    <AdminLayout>
        <Link href="/admin/bands" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-600 hover:text-slate-900 mb-3 transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Bandas
        </Link>

        <h1 class="text-lg font-semibold text-slate-900">{{ band.name }}</h1>
        <p class="text-xs text-slate-500 mt-0.5">
            {{ band.code }}<span v-if="band.creator"> · creada por {{ band.creator }}</span>
            · {{ formatDate(band.created_at) }}
        </p>

        <div class="grid grid-cols-3 lg:grid-cols-5 gap-2.5 mt-4">
            <div v-for="stat in stats" :key="stat.label" class="bg-white rounded-xl border border-slate-200 shadow-sm px-3 py-3">
                <p class="text-2xs font-medium text-slate-500 truncate">{{ stat.label }}</p>
                <p class="text-lg font-semibold text-slate-900 tabular-nums mt-0.5 leading-none">{{ stat.value }}</p>
            </div>
        </div>

        <!-- Storage and its limit, together, because one is meaningless
             without the other. -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4 mt-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Almacenamiento</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        {{ band.has_override ? 'Cuota propia asignada' : `Usa la cuota por defecto (${default_quota_mb} MB)` }}
                    </p>
                </div>
                <button
                    v-if="!editing"
                    type="button"
                    @click="editing = true"
                    class="shrink-0 px-3 py-1.5 text-xs font-semibold text-indigo-600 bg-white border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-colors"
                >Cambiar cuota</button>
            </div>

            <p class="text-2xl font-semibold text-slate-900 tabular-nums tracking-tight mt-3 leading-none">
                {{ formatBytes(band.storage_bytes) }}
                <span class="text-sm font-medium text-slate-500">/ {{ formatBytes(band.quota_bytes) }}</span>
            </p>

            <div class="h-2 bg-slate-100 rounded-full overflow-hidden mt-2.5">
                <div
                    class="h-full rounded-full"
                    :class="band.percent >= 100 ? 'bg-red-500' : band.percent >= 80 ? 'bg-amber-500' : 'bg-indigo-500'"
                    :style="{ width: band.percent + '%' }"
                />
            </div>

            <div v-if="editing" class="mt-4 pt-4 border-t border-slate-100">
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Cuota para esta banda, en MB</label>

                <div class="flex items-center gap-2">
                    <input
                        v-model="form.audio_quota_mb"
                        type="number"
                        min="1"
                        :placeholder="String(default_quota_mb)"
                        class="flex-1 px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <button
                        type="button"
                        @click="save"
                        :disabled="form.processing"
                        class="px-4 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50 transition-colors"
                    >Guardar</button>
                </div>

                <p v-if="form.errors.audio_quota_mb" class="text-xs text-red-600 mt-1.5">{{ form.errors.audio_quota_mb }}</p>
                <p v-else class="text-xs text-slate-500 mt-1.5">
                    20 GB son 20480 MB. Vaciarlo devuelve la banda al valor por defecto.
                </p>

                <div class="flex items-center gap-3 mt-3">
                    <button type="button" @click="editing = false" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
                        Cancelar
                    </button>
                    <button
                        v-if="band.has_override"
                        type="button"
                        @click="useDefault"
                        class="text-xs font-semibold text-slate-600 hover:text-slate-900"
                    >Usar el valor por defecto ({{ formatMb(default_quota_mb) }})</button>
                </div>
            </div>
        </section>

        <!-- Who is in it and when they were last around. No instruments, no
             repertoire: support needs to reach people, not read their work. -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4 mt-4">
            <h2 class="text-sm font-semibold text-slate-900">Equipo</h2>
            <p class="text-xs text-slate-500 mt-0.5">Último acceso del equipo: {{ formatSince(band.team_last_seen_at) }}</p>

            <div class="mt-3 divide-y divide-slate-100">
                <div v-for="member in members" :key="member.id" class="flex items-center justify-between gap-3 py-2.5">
                    <div class="min-w-0">
                        <p class="text-sm text-slate-900 truncate">
                            {{ member.name }}
                            <span v-if="member.role === 'admin'" class="text-2xs font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded ml-1">admin</span>
                        </p>
                        <p class="text-xs text-slate-500 truncate">{{ member.email }}</p>
                    </div>
                    <p class="text-2xs text-slate-500 shrink-0 text-right">{{ formatSince(member.last_seen_at) }}</p>
                </div>
            </div>
        </section>

        <!-- The one control that takes something away. A creator is the only
             account that can manage or delete a band, and nothing else moves
             it — so when one disappears, this is the only way out. -->
        <section class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4 mt-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Propiedad</h2>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Creada por <span class="font-medium text-slate-700">{{ band.creator ?? 'cuenta eliminada' }}</span>
                    </p>
                </div>
                <button
                    v-if="!transferring && candidates.length"
                    type="button"
                    @click="transferring = true"
                    class="shrink-0 px-3 py-1.5 text-xs font-semibold text-amber-700 bg-white border border-amber-300 rounded-lg hover:bg-amber-50 transition-colors"
                >Transferir</button>
            </div>

            <div v-if="transferring" class="mt-4 pt-4 border-t border-slate-100">
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Nuevo propietario</label>

                <select
                    v-model="ownerForm.user_id"
                    class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                    <option :value="null">Elige a alguien de la banda…</option>
                    <option v-for="member in candidates" :key="member.id" :value="member.id">
                        {{ member.name }} · {{ member.email }}
                    </option>
                </select>

                <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2.5 leading-relaxed">
                    El propietario actual seguirá en la banda pero dejará de poder administrarla o eliminarla.
                    La persona elegida pasa a ser administradora. Queda registrado en el historial.
                </p>

                <p v-if="ownerForm.errors.user_id" class="text-xs text-red-600 mt-1.5">{{ ownerForm.errors.user_id }}</p>

                <div class="flex items-center gap-2 mt-3">
                    <button
                        type="button"
                        @click="transfer"
                        :disabled="ownerForm.processing || !ownerForm.user_id"
                        class="px-4 py-2 text-sm font-semibold text-white bg-amber-600 hover:bg-amber-700 rounded-lg disabled:opacity-40 transition-colors"
                    >Transferir propiedad</button>
                    <button
                        type="button"
                        @click="transferring = false; ownerForm.reset()"
                        class="text-xs font-semibold text-slate-600 hover:text-slate-900"
                    >Cancelar</button>
                </div>
            </div>

            <p v-else-if="!candidates.length" class="text-xs text-slate-500 mt-2">
                No hay nadie más en la banda a quien transferirla.
            </p>
        </section>

        <section v-if="history.length" class="bg-white rounded-xl border border-slate-200 shadow-sm px-4 py-4 mt-4">
            <h2 class="text-sm font-semibold text-slate-900">Historial de administración</h2>

            <div class="mt-3 divide-y divide-slate-100">
                <div v-for="entry in history" :key="entry.id" class="py-2.5">
                    <p class="text-sm text-slate-900">
                        {{ actionLabel[entry.action] ?? entry.action }}
                        <span v-if="entry.action === 'band.owner_transferred'" class="text-slate-500">
                            · a {{ entry.context?.to_name ?? 'otra cuenta' }}
                        </span>
                        <span v-else-if="entry.context?.to !== undefined" class="text-slate-500">
                            · {{ entry.context.from ?? 'por defecto' }} → {{ entry.context.to ?? 'por defecto' }} MB
                        </span>
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ entry.by }} · {{ formatDate(entry.created_at) }}</p>
                </div>
            </div>
        </section>
    </AdminLayout>
</template>
