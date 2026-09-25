<script setup>
import { ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import ServiceColorPicker from '@/Components/ServiceColorPicker.vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    /** null when creating. Services never reach here — they are edited in Services. */
    entry: { type: Object, default: null },
    /** The day the calendar has selected, so a new entry starts there. */
    date: { type: String, required: true },
});

const emit = defineEmits(['close']);

const { t } = useI18n();

const KINDS = ['rehearsal', 'meeting', 'other'];

const confirmingDelete = ref(false);

const form = useForm({
    kind: 'rehearsal',
    type: '',
    date: props.date,
    time: '',
    end_time: '',
    color: 'sky',
    notes: '',
});

watch(() => props.open, (isOpen) => {
    if (!isOpen) return;

    confirmingDelete.value = false;

    if (props.entry) {
        form.kind = props.entry.kind;
        form.type = props.entry.name ?? '';
        form.date = props.entry.date?.slice(0, 10) ?? props.date;
        form.time = props.entry.time ?? '';
        form.end_time = props.entry.end_time ?? '';
        form.color = props.entry.color ?? 'sky';
        form.notes = props.entry.notes ?? '';
        return;
    }

    form.reset();
    form.date = props.date;
});

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };

    props.entry
        ? form.put(`/calendar/${props.entry.id}`, options)
        : form.post('/calendar', options);
}

function destroy() {
    form.delete(`/calendar/${props.entry.id}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
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
            <div v-if="open" class="fixed inset-0 z-40 bg-black/40" @click="emit('close')" />
        </Transition>

        <Transition
            enter-active-class="transition duration-250 ease-out"
            enter-from-class="opacity-0 translate-y-6"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-6"
        >
            <div
                v-if="open"
                class="fixed bottom-0 left-1/2 lg:left-[calc(50%+8rem)] -translate-x-1/2 w-full sm:max-w-lg lg:max-w-2xl z-50 bg-white rounded-t-2xl px-4 pt-4 pb-8 max-h-[85vh] flex flex-col shadow-xl"
            >
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-semibold text-slate-900">
                        {{ entry ? t('calendar.edit_entry') : t('calendar.new_entry') }}
                    </h2>
                    <button @click="emit('close')" class="text-slate-600 hover:text-slate-900 text-lg leading-none">✕</button>
                </div>

                <form @submit.prevent="submit" class="overflow-y-auto flex-1 space-y-4">
                    <!-- Kind. Service is absent on purpose: it is created under
                         Services, where it gets a setlist and a team. -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-medium text-slate-600">{{ t('calendar.kind') }}</label>
                        <div class="flex gap-1.5">
                            <button
                                v-for="kind in KINDS"
                                :key="kind"
                                type="button"
                                @click="form.kind = kind"
                                class="flex-1 px-3 py-2 text-xs font-semibold rounded-lg border transition-colors"
                                :class="form.kind === kind
                                    ? 'bg-indigo-50 border-indigo-300 text-indigo-700'
                                    : 'bg-white border-slate-300 text-slate-600 hover:bg-slate-50'"
                            >{{ t('calendar.kind_' + kind) }}</button>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label for="entry-name" class="block text-xs font-medium text-slate-600">{{ t('calendar.name') }}</label>
                        <input
                            id="entry-name"
                            :value="form.type"
                            @input="form.type = $event.target.value"
                            type="text"
                            maxlength="40"
                            :placeholder="t('calendar.name_placeholder')"
                            class="w-full px-3 py-2.5 text-base rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.type" class="text-xs text-red-600">{{ form.errors.type }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <label for="entry-date" class="block text-xs font-medium text-slate-600">{{ t('services.form.date') }}</label>
                        <input
                            id="entry-date"
                            v-model="form.date"
                            type="date"
                            required
                            class="w-full px-3 py-2.5 text-base rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        />
                        <p v-if="form.errors.date" class="text-xs text-red-600">{{ form.errors.date }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1.5">
                            <label for="entry-time" class="block text-xs font-medium text-slate-600">{{ t('calendar.starts') }}</label>
                            <input
                                id="entry-time"
                                v-model="form.time"
                                type="time"
                                class="w-full px-3 py-2.5 text-base rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                        </div>
                        <div class="space-y-1.5">
                            <label for="entry-end" class="block text-xs font-medium text-slate-600">{{ t('calendar.ends') }}</label>
                            <input
                                id="entry-end"
                                v-model="form.end_time"
                                type="time"
                                class="w-full px-3 py-2.5 text-base rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            />
                            <p v-if="form.errors.end_time" class="text-xs text-red-600">{{ form.errors.end_time }}</p>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-medium text-slate-600">
                            {{ t('services.color_label') }}
                            <span class="text-slate-500 font-normal">· {{ t('services.color_optional') }}</span>
                        </label>
                        <ServiceColorPicker v-model="form.color" />
                    </div>

                    <div class="space-y-1.5">
                        <label for="entry-notes" class="block text-xs font-medium text-slate-600">{{ t('services.form.notes') }}</label>
                        <textarea
                            id="entry-notes"
                            v-model="form.notes"
                            rows="3"
                            maxlength="1000"
                            :placeholder="t('calendar.notes_placeholder')"
                            class="w-full px-3 py-2.5 text-sm rounded-lg border border-slate-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
                        />
                    </div>

                    <div v-if="entry && confirmingDelete" class="rounded-xl bg-red-50 border border-red-200 px-3 py-2.5 flex items-center gap-2">
                        <p class="flex-1 text-xs font-medium text-red-800">{{ t('calendar.delete_confirm') }}</p>
                        <button
                            type="button"
                            @click="confirmingDelete = false"
                            class="px-2.5 py-1 text-2xs font-semibold text-slate-600 rounded-md border border-slate-300 bg-white"
                        >{{ t('services.form.cancel') }}</button>
                        <button
                            type="button"
                            @click="destroy"
                            class="px-2.5 py-1 text-2xs font-semibold text-white bg-red-600 rounded-md"
                        >{{ t('calendar.delete') }}</button>
                    </div>
                </form>

                <div class="flex gap-2.5 pt-4 mt-2 border-t border-slate-100">
                    <button
                        v-if="entry && !confirmingDelete"
                        type="button"
                        @click="confirmingDelete = true"
                        class="px-3 py-2.5 text-sm font-semibold text-red-600 rounded-lg border border-red-200 hover:bg-red-50 transition-colors"
                    >{{ t('calendar.delete') }}</button>

                    <button
                        type="button"
                        @click="emit('close')"
                        class="flex-1 py-2.5 text-sm font-semibold text-slate-600 rounded-lg border border-slate-300"
                    >{{ t('services.form.cancel') }}</button>

                    <button
                        type="button"
                        @click="submit"
                        :disabled="form.processing || !form.type.trim()"
                        class="flex-1 py-2.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg transition-colors"
                    >{{ form.processing ? t('services.form.saving') : t('services.form.save') }}</button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
