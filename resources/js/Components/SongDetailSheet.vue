<script setup>
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps({
    // Flat song object (single-version use case — e.g. service setlist)
    // { name, artist, version, key, bpm, notes, youtube_url }
    //
    // OR
    //
    // Song with versions array (library viewer use case):
    // { name, artist, versions: [{ id, name, key, bpm, notes, youtube_url }] }
    song: Object,
});

const emit = defineEmits(['close']);

const selectedVersionIdx = ref(0);

// Reset version selector each time a new song is opened
watch(() => props.song?.name, () => { selectedVersionIdx.value = 0; });

const versions = computed(() => props.song?.versions ?? null);
const hasVersionList = computed(() => !!(versions.value && versions.value.length));

// The "current" version data: from the versions array if provided, else the flat song fields.
const current = computed(() => {
    if (hasVersionList.value) {
        return versions.value[selectedVersionIdx.value] ?? versions.value[0];
    }
    return {
        name:        props.song?.version,
        key:         props.song?.key,
        bpm:         props.song?.bpm,
        notes:       props.song?.notes,
        youtube_url: props.song?.youtube_url,
    };
});

const youtubeEmbed = computed(() => {
    const url = current.value?.youtube_url;
    if (!url) return null;
    const m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([\w-]{11})/);
    return m ? `https://www.youtube.com/embed/${m[1]}` : null;
});

const hasAnyDetail = computed(() => {
    const c = current.value;
    return !!(c && (c.key || c.bpm || c.youtube_url || c.notes));
});
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
                class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-md z-50 bg-white rounded-t-2xl max-h-[88vh] flex flex-col shadow-xl"
            >
                <!-- Header -->
                <div class="px-4 pt-3 pb-3 border-b border-slate-100">
                    <div class="w-10 h-1 bg-slate-200 rounded-full mx-auto mb-3" />
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-slate-900 truncate">{{ song.name }}</h2>
                            <p v-if="song.artist" class="text-xs text-slate-500 mt-0.5 truncate">{{ song.artist }}</p>
                        </div>
                        <span
                            v-if="!hasVersionList && current?.name"
                            class="shrink-0 inline-flex items-center text-[11px] font-semibold text-slate-600 bg-slate-100 rounded-md px-2 py-1"
                        >
                            {{ current.name }}
                        </span>
                    </div>

                    <!-- Version switcher (only when multiple versions exist) -->
                    <div v-if="hasVersionList && versions.length > 1" class="flex gap-1.5 mt-3 overflow-x-auto -mx-1 px-1">
                        <button
                            v-for="(v, i) in versions"
                            :key="v.id"
                            type="button"
                            @click="selectedVersionIdx = i"
                            :class="[
                                'shrink-0 px-2.5 py-1 text-[11px] font-semibold rounded-md border transition-colors',
                                selectedVersionIdx === i
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300',
                            ]"
                        >
                            {{ v.name }}
                            <span v-if="v.key" class="opacity-70 ml-0.5">· {{ v.key }}</span>
                        </button>
                    </div>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
                    <!-- Empty state -->
                    <p v-if="!hasAnyDetail" class="text-center text-sm text-slate-500 py-8">
                        {{ t('services.no_details') }}
                    </p>

                    <!-- Key + BPM -->
                    <div v-if="current?.key || current?.bpm" class="grid grid-cols-2 gap-3">
                        <div v-if="current.key" class="bg-indigo-50 rounded-xl px-3 py-2.5">
                            <p class="text-[10px] font-semibold text-indigo-500 uppercase tracking-wide">{{ t('songs.form.key') }}</p>
                            <p class="text-lg font-bold text-indigo-700 mt-0.5">{{ current.key }}</p>
                        </div>
                        <div v-if="current.bpm" class="bg-violet-50 rounded-xl px-3 py-2.5">
                            <p class="text-[10px] font-semibold text-violet-500 uppercase tracking-wide">{{ t('songs.form.bpm') }}</p>
                            <p class="text-lg font-bold text-violet-700 mt-0.5">{{ current.bpm }}</p>
                        </div>
                    </div>

                    <!-- YouTube -->
                    <div v-if="current?.youtube_url" class="space-y-2">
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
                            :href="current.youtube_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex items-center gap-2 text-xs text-indigo-600 font-medium break-all"
                        >
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            {{ current.youtube_url }}
                        </a>
                    </div>

                    <!-- Notes -->
                    <div v-if="current?.notes" class="space-y-1.5">
                        <p class="text-[10px] font-semibold text-slate-500 uppercase tracking-wide">{{ t('songs.form.notes') }}</p>
                        <p class="text-sm text-slate-700 bg-slate-50 rounded-xl px-3 py-2.5 whitespace-pre-wrap">{{ current.notes }}</p>
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
