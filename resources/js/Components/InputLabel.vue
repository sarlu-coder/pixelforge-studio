<script setup>
defineProps({
    value: {
        type: String,
    },
});
</script>

<template>
    <label class="block text-sm font-medium text-zinc-400 text-xs uppercase tracking-wider">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
