<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SongDetailSheet from '@/Components/SongDetailSheet.vue';
import PlaylistOverlay from '@/Components/PlaylistOverlay.vue';

const { t } = useI18n();

const props = defineProps({
    service: Object,
    expired: Boolean,
});

const serviceTitle = computed(() => {
    if (!props.service) return '';
    return props.service.type === 'other' ? t('services.type_other') : props.service.type;
});

function formatDate(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

const detailSong = ref(null);

function openDetail(song) {
    detailSong.value = song;
}

const playlistOpen = ref(false);
const hasAnyVideo  = computed(() => (props.service?.songs ?? []).some(s => !!s.youtube_url));
</script>

<template>
    <Head :title="serviceTitle || t('public.expired_title')" />

    <div class="min-h-screen bg-slate-50 flex flex-col">

        <!-- Expired state -->
        <div v-if="expired" class="flex-1 flex flex-col items-center justify-center px-6 text-center">
            <svg class="w-12 h-12 text-slate-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
            <h1 class="text-lg font-semibold text-slate-700 mb-2">{{ t('public.expired_title') }}</h1>
            <p class="text-sm text-slate-500">{{ t('public.expired_body') }}</p>
        </div>

        <!-- Service view -->
        <template v-else-if="service">
            <!-- Header -->
            <div class="bg-indigo-600 px-5 pt-10 pb-6 text-white">
                <h1 class="text-xl font-bold leading-tight capitalize">{{ serviceTitle }}</h1>
                <p class="text-sm text-indigo-200 mt-1">
                    {{ formatDate(service.date) }}
                    <span v-if="service.time"> · {{ service.time.slice(0, 5) }}</span>
                </p>
            </div>

            <!-- Notes -->
            <div v-if="service.notes" class="bg-white border-b border-slate-100 px-5 py-3">
                <p class="text-sm text-slate-600">{{ service.notes }}</p>
            </div>

            <!-- Songs -->
            <div class="flex-1 px-4 py-5">
                <div v-if="!service.songs.length" class="text-center py-12 text-sm text-slate-500">
                    {{ t('public.no_songs') }}
                </div>

                <template v-else>
                    <button
                        v-if="hasAnyVideo"
                        @click="playlistOpen = true"
                        class="w-full mb-4 flex items-center justify-center gap-2 py-3.5 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white text-sm font-bold rounded-2xl shadow-lg shadow-indigo-200 active:scale-[0.99] transition"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z" />
                        </svg>
                        {{ t('playlist.public_cta') }}
                    </button>
                </template>

                <div v-if="service.songs.length" class="space-y-2">
                    <button
                        type="button"
                        v-for="(song, i) in service.songs"
                        :key="i"
                        @click="openDetail(song)"
                        class="w-full flex items-center gap-3 bg-white rounded-xl px-4 py-3.5 border border-slate-200 text-left hover:border-indigo-200 transition-colors"
                    >
                        <span class="text-xs font-bold text-slate-300 w-5 text-center shrink-0">{{ i + 1 }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-slate-900 truncate">{{ song.name }}</p>
                            <p class="text-xs text-slate-500 mt-0.5">
                                <span v-if="song.artist" class="text-slate-500">{{ song.artist }} · </span>{{ song.version }}<span v-if="song.key" class="text-indigo-500 font-medium"> · {{ song.key }}</span>
                            </p>
                        </div>
                        <svg class="w-4 h-4 text-slate-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center py-6 text-xs text-slate-300">
                {{ t('public.powered_by') }}
            </div>
        </template>

        <!-- Song detail (read-only) -->
        <SongDetailSheet :song="detailSong" @close="detailSong = null" />

        <!-- Playlist overlay -->
        <PlaylistOverlay :open="playlistOpen" :songs="service?.songs ?? []" @close="playlistOpen = false" />
    </div>
</template>
