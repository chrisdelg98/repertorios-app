<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';
import Logo from '@/Components/Logo.vue';

defineProps({
    // false = they belong to no band at all (removed from their last one),
    // so the app chrome would have nothing to show around this form.
    has_bands: { type: Boolean, default: true },
});

const { t } = useI18n();

const form = useForm({ name: '' });

function submit() {
    form.post('/bands');
}
</script>

<template>
    <Head :title="t('bands.create_title')" />


    <AppLayout v-if="has_bands">
        <div class="px-4 lg:px-8 py-5 lg:py-10 max-w-lg mx-auto">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6">
                <h1 class="text-lg font-bold text-slate-900">{{ t('bands.create_title') }}</h1>
                <p class="text-sm text-slate-600 mt-1 leading-relaxed">{{ t('bands.create_subtitle') }}</p>

                <form @submit.prevent="submit" class="mt-5">
                    <label for="band-name" class="block text-xs font-medium text-slate-600 mb-1">{{ t('bands.name_label') }}</label>
                    <input
                        id="band-name"
                        v-model="form.name"
                        type="text"
                        maxlength="100"
                        :placeholder="t('bands.name_placeholder')"
                        class="w-full px-3 py-2.5 text-base bg-white border border-slate-300 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition"
                    />
                    <p v-if="form.errors.name" class="text-xs font-medium text-red-600 mt-1.5">{{ form.errors.name }}</p>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.name.trim()"
                        class="w-full mt-4 py-3 bg-gradient-to-br from-indigo-600 to-violet-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 hover:shadow-lg active:scale-[0.98] disabled:opacity-50 disabled:active:scale-100 transition"
                    >{{ t('bands.create_button') }}</button>
                </form>

                <p class="text-xs text-slate-600 mt-4 leading-relaxed border-t border-slate-100 pt-4">{{ t('bands.join_hint') }}</p>
            </div>
        </div>
    </AppLayout>

    <!-- No band at all: standalone shell, there is no sidebar to render -->
    <div v-else class="min-h-screen bg-slate-50 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-md">
            <div class="flex justify-center mb-6">
                <Logo :size="48" />
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6">
                <h1 class="text-lg font-bold text-slate-900">{{ t('bands.create_first_title') }}</h1>
                <p class="text-sm text-slate-600 mt-1 leading-relaxed">{{ t('bands.create_first_subtitle') }}</p>

                <form @submit.prevent="submit" class="mt-5">
                    <label for="band-name-first" class="block text-xs font-medium text-slate-600 mb-1">{{ t('bands.name_label') }}</label>
                    <input
                        id="band-name-first"
                        v-model="form.name"
                        type="text"
                        maxlength="100"
                        autofocus
                        :placeholder="t('bands.name_placeholder')"
                        class="w-full px-3 py-2.5 text-base bg-white border border-slate-300 rounded-xl focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100 outline-none transition"
                    />
                    <p v-if="form.errors.name" class="text-xs font-medium text-red-600 mt-1.5">{{ form.errors.name }}</p>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.name.trim()"
                        class="w-full mt-4 py-3 bg-gradient-to-br from-indigo-600 to-violet-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-indigo-200 hover:shadow-lg active:scale-[0.98] disabled:opacity-50 disabled:active:scale-100 transition"
                    >{{ t('bands.create_button') }}</button>
                </form>

                <p class="text-xs text-slate-600 mt-4 leading-relaxed border-t border-slate-100 pt-4">{{ t('bands.join_hint') }}</p>
            </div>

            <div class="text-center mt-5">
                <Link href="/logout" method="post" as="button" class="text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors">
                    {{ t('nav.logout') }}
                </Link>
            </div>
        </div>
    </div>
</template>
