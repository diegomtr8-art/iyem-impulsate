<script setup>
import { onMounted, ref } from 'vue';

defineProps({
    modelValue: String,
});

defineEmits(['update:modelValue']);

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
        ref="input"
        class="border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 focus:border-guinda-500 dark:focus:border-guinda-500 focus:ring-guinda-500 dark:focus:ring-guinda-500 rounded-md shadow-sm dark:shadow-none transition-colors"
        :value="modelValue"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
