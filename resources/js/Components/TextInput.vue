<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-md border-[#27272c] bg-[#09090b] text-white placeholder-zinc-600 focus:border-zinc-400 focus:ring-0 rounded-md shadow-sm"
        v-model="model"
        ref="input"
    />
</template>
