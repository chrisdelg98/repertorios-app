<script setup>
import { ref, computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Logo from '@/Components/Logo.vue';

const { t } = useI18n();
const page = usePage();

const visible = ref(true);

const userName = computed(() => {
    const full = page.props.auth?.user?.name || '';
    return full.split(' ')[0];
});

const checklist = [
    { icon: 'calendar', titleKey: 'welcome.step_templates_title', bodyKey: 'welcome.step_templates_body', href: '/settings/schedule-templates' },
    { icon: 'music',    titleKey: 'welcome.step_songs_title',     bodyKey: 'welcome.step_songs_body',     href: '/songs' },
    { icon: 'plus',     titleKey: 'welcome.step_service_title',   bodyKey: 'welcome.step_service_body',   href: '/services/create' },
    { icon: 'share',    titleKey: 'welcome.step_share_title',     bodyKey: 'welcome.step_share_body',     href: '/settings/band' },
];

// Any close interaction (CTA, checklist click, backdrop, or "don't show again")
// permanently dismisses the welcome. It only ever appears ONCE per user.
let dismissed = false;
function dismissOnce() {
    visible.value = false;
    if (dismissed) return;
    dismissed = true;
    router.post('/welcome/dismiss', {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

</script>

<template>
    <Teleport to="body">
        <!-- Backdrop with blur -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="visible"
                class="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-sm"
                @click="dismissOnce"
            />
        </Transition>

        <!-- Modal -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-4"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-4"
        >
            <div
                v-if="visible"
                class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4 pointer-events-none"
            >
                <div class="bg-white w-full sm:max-w-lg rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden pointer-events-auto max-h-[92vh] flex flex-col">
                    <!-- Gradient header -->
                    <div class="relative bg-gradient-to-br from-indigo-600 to-violet-600 px-6 pt-7 pb-6 text-white">
                        <!-- Decorative blobs -->
                        <div class="absolute -top-12 -right-12 w-40 h-40 bg-white/10 rounded-full blur-2xl pointer-events-none" />
                        <div class="absolute -bottom-12 -left-12 w-40 h-40 bg-violet-400/30 rounded-full blur-2xl pointer-events-none" />

                        <div class="relative flex items-center gap-3">
                            <Logo :size="48" />
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold text-indigo-200 uppercase tracking-wide">{{ t('welcome.eyebrow') }}</p>
                                <h2 class="text-xl font-bold leading-tight truncate">
                                    {{ t('welcome.greeting', { name: userName }) }}
                                </h2>
                            </div>
                        </div>

                        <p class="relative mt-3 text-sm text-indigo-100 leading-relaxed">
                            {{ t('welcome.blessing') }}
                        </p>
                    </div>

                    <!-- Body: checklist -->
                    <div class="px-5 py-5 overflow-y-auto flex-1">
                        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">
                            {{ t('welcome.checklist_title') }}
                        </p>

                        <div class="space-y-2">
                            <Link
                                v-for="(item, i) in checklist"
                                :key="i"
                                :href="item.href"
                                @click="dismissOnce"
                                class="group flex items-start gap-3 bg-slate-50 hover:bg-indigo-50 border border-slate-100 hover:border-indigo-200 rounded-xl px-3.5 py-3 transition-colors"
                            >
                                <!-- Step number -->
                                <span class="shrink-0 w-7 h-7 flex items-center justify-center rounded-full bg-indigo-100 group-hover:bg-indigo-600 text-indigo-600 group-hover:text-white text-xs font-bold transition-colors">
                                    {{ i + 1 }}
                                </span>

                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900">{{ t(item.titleKey) }}</p>
                                    <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">{{ t(item.bodyKey) }}</p>
                                </div>

                                <svg class="w-4 h-4 text-slate-300 group-hover:text-indigo-500 shrink-0 mt-1 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </Link>
                        </div>
                    </div>

                    <!-- Footer actions -->
                    <div class="px-5 pt-2 pb-6 border-t border-slate-100 space-y-2">
                        <button
                            type="button"
                            @click="dismissOnce"
                            class="w-full py-3 bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 active:scale-[0.98] transition"
                        >
                            {{ t('welcome.cta_explore') }}
                        </button>
                        <button
                            type="button"
                            @click="dismissOnce"
                            class="w-full py-2 text-xs font-medium text-slate-500 hover:text-slate-600 transition-colors"
                        >
                            {{ t('welcome.dont_show_again') }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
