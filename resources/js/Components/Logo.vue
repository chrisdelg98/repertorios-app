<script setup>
defineProps({
    size: { type: [String, Number], default: 32 },
    alt:  { type: String, default: 'Repertorios' },
});
</script>

<template>
    <img
        src="/icons/logorepertorios.svg"
        :width="size"
        :height="size"
        :alt="alt"
        class="object-contain shrink-0"
    />
</template>
