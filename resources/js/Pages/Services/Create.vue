<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/Layouts/AppLayout.vue';

const { t } = useI18n();

const props = defineProps({
    service: Object,
});

const isEdit = !!props.service;

const form = useForm({
    date: props.service?.date?.slice(0, 10) ?? new Date().toISOString().slice(0, 10),
    time: props.service?.time?.slice(0, 5) ?? '',
    type: props.service?.type ?? 'sunday_am',
    notes: props.service?.notes ?? '',
});

const SERVICE_TYPES = ['sunday_am', 'sunday_pm', 'wednesday', 'rehearsal', 'other'];

function submit() {
    if (isEdit) {
        form.put('/services/' + props.service.id);
    } else {
        form.post('/services');
    }
}
</script>

<template>
    <Head :title="isEdit ? t('services.edit') : t('services.create')" />

    <AppLayout>
        <div class="px-4 py-5 max-w-lg mx-auto">
            <h1 class="text-lg font-semibold text-slate-900 mb-5">
                {{ isEdit ? t('services.edit') : t('services.create') }}
            </h1>

            <form @submit.prevent="submit" class="space-y-4">
                <!-- Type -->
                <div class="space-y-1.5">
                    <label class="block text-xs font-medium text-slate-600">{{ t('services.form.type') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="type in SERVICE_TYPES"
                            :key="type"
                            type="button"
                            @click="form.type = type"
                            :class="[
                                'py-2 px-3 text-xs font-medium rounded-lg border transition-colors',
                                form.type === type
                                    ? 'bg-indigo-600 text-white border-indigo-600'
                                    : 'bg-white text-slate-700 border-slate-300 hover:border-indigo-300',
                            ]"
                        >
                            {{ t('services.types.' + type) }}
                        </button>
                    </div>
                </div>

                <!-- Date -->
                <div class="space-y-1.5">
                    <label for="date" class="block text-xs font-medium text-slate-600">{{ t('services.form.date') }}</label>
                    <input
                        id="date"
                        v-model="form.date"
                        type="date"
                        required
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                    <p v-if="form.errors.date" class="text-xs text-red-600">{{ form.errors.date }}</p>
                </div>

                <!-- Time -->
                <div class="space-y-1.5">
                    <label for="time" class="block text-xs font-medium text-slate-600">{{ t('services.form.time') }}</label>
                    <input
                        id="time"
                        v-model="form.time"
                        type="time"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                    />
                </div>

                <!-- Notes -->
                <div class="space-y-1.5">
                    <label for="notes" class="block text-xs font-medium text-slate-600">{{ t('services.form.notes') }}</label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                    />
                </div>

                <div class="flex gap-3 pt-2">
                    <a
                        href="/services"
                        class="flex-1 py-2.5 text-sm font-semibold text-center rounded-lg border border-slate-300 text-slate-700"
                    >
                        {{ t('services.form.cancel') }}
                    </a>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
                    >
                        {{ form.processing ? t('services.form.saving') : t('services.form.save') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
