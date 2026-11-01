<script setup>
/**
 * The mark for a kind of panel, chosen by name.
 *
 * Four known shapes rather than markup carried in the data: an icon passed as
 * a string is a habit that eventually renders something somebody else wrote.
 */
defineProps({
    name: { type: String, default: '' },
});
</script>

<template>
    <svg v-if="name === 'video'" fill="currentColor" viewBox="0 0 24 24">
        <path d="M7 4l12 8-12 8z" />
    </svg>
    <svg v-else-if="name === 'audio'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M9 18V5l10-2v13" />
        <circle cx="6.5" cy="18" r="2.5" />
        <circle cx="16.5" cy="16" r="2.5" />
    </svg>
    <svg v-else-if="name === 'lyrics'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h10M4 10h16M4 14h12M4 18h7" />
    </svg>
    <svg v-else fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 19V7l12-3v12" />
        <path stroke-linecap="round" d="M5 11h12M5 15h12" />
    </svg>
</template>
