<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import Logo from '@/Components/Logo.vue';

const props = defineProps({
    // Mobile header variant: smaller logo, tighter type.
    compact: { type: Boolean, default: false },
});

const { t } = useI18n();
const page = usePage();

const auth        = computed(() => page.props.auth);
const memberships = computed(() => auth.value?.memberships ?? []);
const bandName    = computed(() => auth.value?.band?.name ?? t('app.name'));
const bandLogo    = computed(() => auth.value?.band?.logo_url ?? null);

// Open for any registered user, even with a single band: the menu is also where
// "create another band" lives, so gating it on having two would make the second
// band unreachable. Invite-link guests have no membership and keep a plain link.
const canSwitch = computed(() => memberships.value.length > 0);

const open = ref(false);
const switching = ref(null);

function toggle() {
    if (!canSwitch.value) return;
    open.value = !open.value;
}

function switchTo(band) {
    if (band.is_active || switching.value) {
        open.value = false;
        return;
    }
    switching.value = band.id;
    router.post(`/bands/${band.id}/switch`, {}, {
        onFinish: () => {
            switching.value = null;
            open.value = false;
        },
    });
}

function initial(name) {
    return (name || '?').charAt(0).toUpperCase();
}

function onDocClick(e) {
    if (!e.target.closest('[data-band-switcher]')) open.value = false;
}

onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<template>
    <div class="relative min-w-0" data-band-switcher>
        <!-- Guest session → the plain link the layout always had -->
        <Link
            v-if="!canSwitch"
            href="/dashboard"
            class="flex items-center gap-2.5 min-w-0"
        >
            <img
                v-if="bandLogo"
                :src="bandLogo"
                :width="compact ? 32 : 36"
                :height="compact ? 32 : 36"
                alt=""
                class="rounded-lg object-contain shrink-0"
            />
            <Logo v-else :size="compact ? 32 : 36" />
            <span
                class="truncate text-slate-900"
                :class="compact ? 'font-semibold text-sm' : 'font-bold'"
            >{{ bandName }}</span>
        </Link>

        <!-- Registered user → switcher -->
        <button
            v-else
            type="button"
            @click.stop="toggle"
            class="flex items-center gap-2.5 min-w-0 max-w-full rounded-lg px-1.5 py-1 -mx-1.5 hover:bg-slate-50 active:bg-slate-100 transition-colors"
            :aria-label="t('bands.switcher_label')"
            :aria-expanded="open"
        >
            <img
                v-if="bandLogo"
                :src="bandLogo"
                :width="compact ? 32 : 36"
                :height="compact ? 32 : 36"
                alt=""
                class="rounded-lg object-contain shrink-0"
            />
            <Logo v-else :size="compact ? 32 : 36" />
            <span
                class="truncate text-slate-900"
                :class="compact ? 'font-semibold text-sm' : 'font-bold'"
            >{{ bandName }}</span>
            <svg
                class="w-4 h-4 text-slate-500 shrink-0 transition-transform"
                :class="open ? 'rotate-180' : ''"
                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute left-0 top-full mt-1.5 w-64 max-w-[calc(100vw-2rem)] origin-top-left bg-white rounded-xl border border-slate-200 shadow-lg overflow-hidden z-40"
            >
                <p class="text-2xs font-semibold text-slate-600 uppercase tracking-wide px-3 pt-3 pb-1.5">
                    {{ t('bands.your_bands') }}
                </p>

                <button
                    v-for="band in memberships"
                    :key="band.id"
                    type="button"
                    @click.stop="switchTo(band)"
                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-left hover:bg-slate-50 transition-colors disabled:opacity-50"
                    :disabled="switching !== null"
                >
                    <!-- The gradient is the fallback, not a frame: a logo with a
                         transparent background would otherwise show it through. -->
                    <span
                        class="w-8 h-8 rounded-lg overflow-hidden flex items-center justify-center text-xs font-bold shrink-0"
                        :class="band.logo_url ? '' : 'bg-gradient-to-br from-indigo-500 to-violet-600 text-white'"
                    >
                        <img v-if="band.logo_url" :src="band.logo_url" class="w-full h-full object-contain" alt="" />
                        <span v-else>{{ initial(band.name) }}</span>
                    </span>

                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-slate-900 truncate leading-tight">{{ band.name }}</span>
                        <span class="block text-xs font-medium text-slate-600 mt-0.5">
                            {{ band.role === 'admin' ? t('bands.role_admin') : t('bands.role_member') }}
                        </span>
                    </span>

                    <svg
                        v-if="band.is_active"
                        class="w-4 h-4 text-indigo-600 shrink-0"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </button>

                <Link
                    href="/bands/create"
                    class="w-full flex items-center gap-2.5 px-3 py-2.5 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 transition-colors border-t border-slate-100"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ t('bands.create_another') }}
                </Link>
            </div>
        </Transition>
    </div>
</template>
