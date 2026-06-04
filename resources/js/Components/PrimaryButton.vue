<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
});
</script>

<template>
    <button :type="type" class="inline-flex items-center px-4 py-2 bg-guinda-800 dark:bg-guinda-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-guinda-700 dark:hover:bg-guinda-600 focus:outline-none focus:ring-2 focus:ring-guinda-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 disabled:opacity-50 transition ease-in-out duration-150">
        <slot />
    </button>
</template>
