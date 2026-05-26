<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    song: Object,
});

const emit = defineEmits(['close']);

const youtubeEmbed = computed(() => {
    const url = props.song?.youtube_url;
    if (!url) return null;
    const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]{11})/);
    return m ? `https://www.youtube.com/embed/${m[1]}` : null;
});

const hasAnyDetail = computed(() =>
    props.song && (props.song.key || props.song.bpm || props.song.youtube_url || props.song.notes)
);
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="song"
                class="fixed inset-0 z-40 bg-black/40"
                @click="emit('close')"
            />
        </Transition>

        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="translate-y-full"
            enter-to-class="translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="song"
                class="fixed bottom-0 inset-x-0 z-50 bg-white rounded-t-2xl max-h-[88vh] flex flex-col shadow-xl"
            >
                <!-- Header -->
                <div class="px-4 pt-3 pb-3 border-b border-slate-100">
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-slate-900 truncate">{{ song.name }}</h2>
                            <p v-if="song.artist" class="text-xs text-slate-500 mt-0.5 truncate">{{ song.artist }}</p>
                        </div>
                        <span class="shrink-0 inline-flex items-center text-[11px] font-semibold text-slate-600 bg-slate-100 rounded-md px-2 py-1">
                            {{ song.version }}
                        </span>
                    </div>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                    <!-- Empty state -->
                    <p v-if="!hasAnyDetail" class="text-center text-sm text-slate-400 py-8">
                        {{ t('services.no_details') }}
                    </p>

                    <!-- Key + BPM -->
                    <div v-if="song.key || song.bpm" class="grid grid-cols-2 gap-3">
                        <div v-if="song.key" class="bg-indigo-50 rounded-xl px-3 py-2.5">
                            <p class="text-[10px] font-semibold text-indigo-500 uppercase tracking-wide">{{ t('songs.form.key') }}</p>
                            <p class="text-lg font-bold text-indigo-700 mt-0.5">{{ song.key }}</p>
                        </div>
                        <div v-if="song.bpm" class="bg-violet-50 rounded-xl px-3 py-2.5">
                            <p class="text-[10px] font-semibold text-violet-500 uppercase tracking-wide">{{ t('songs.form.bpm') }}</p>
                            <p class="text-lg font-bold text-violet-700 mt-0.5">{{ song.bpm }}</p>
                        </div>
                    </div>

                    <!-- YouTube -->
                    <div v-if="song.youtube_url" class="space-y-2">
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">YouTube</p>
                        <div v-if="youtubeEmbed" class="aspect-video rounded-xl overflow-hidden bg-slate-100">
                            <iframe
                                :src="youtubeEmbed"
                                class="w-full h-full"
                                frameborder="0"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                            />
                        </div>
                        <a
                            :href="song.youtube_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-2 text-xs text-indigo-600 font-medium break-all"
                        >
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            {{ song.youtube_url }}
                        </a>
                    </div>

                    <!-- Notes -->
                    <div v-if="song.notes" class="space-y-1.5">
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">{{ t('songs.form.notes') }}</p>
                        <p class="text-sm text-slate-700 bg-slate-50 rounded-xl px-3 py-2.5 whitespace-pre-wrap">{{ song.notes }}</p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="px-4 py-3 border-t border-slate-100">
                    <button
                        @click="emit('close')"
                        class="w-full py-2.5 text-sm font-semibold text-slate-600 rounded-xl border border-slate-300"
                    >
                        {{ t('services.close') }}
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
