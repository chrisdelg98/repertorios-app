<script setup>
import { useI18n } from 'vue-i18n';
import { SERVICE_COLORS, SERVICE_COLOR_KEYS, DEFAULT_SERVICE_COLOR } from '@/Constants/serviceColors';

const props = defineProps({
    modelValue: { type: String, default: DEFAULT_SERVICE_COLOR },
    // 4 in a narrow menu so the dots stay thumb-sized, 8 across a full-width
    // field. Literal classes — Tailwind cannot see a built-up grid-cols-N.
    columns: { type: Number, default: 4 },
});

const emit = defineEmits(['update:modelValue']);

const { t } = useI18n();

const colors = SERVICE_COLOR_KEYS.map(key => SERVICE_COLORS[key]);

function isSelected(key) {
    return (props.modelValue || DEFAULT_SERVICE_COLOR) === key;
}
</script>

<template>
    <div class="grid gap-2" :class="columns === 8 ? 'grid-cols-8' : 'grid-cols-4'">
        <button
            v-for="color in colors"
            :key="color.key"
            type="button"
            @click.stop="emit('update:modelValue', color.key)"
            class="aspect-square rounded-full transition ring-offset-2 ring-offset-white hover:scale-110 active:scale-95"
            :class="[
                color.swatch,
                isSelected(color.key) ? ['ring-2', color.ring] : 'ring-0',
            ]"
            :aria-label="t('services.colors.' + color.key)"
            :aria-pressed="isSelected(color.key)"
            :title="t('services.colors.' + color.key)"
        />
    </div>
</template>
