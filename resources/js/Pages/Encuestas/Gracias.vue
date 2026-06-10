<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    evento: Object,
});
</script>

<template>
    <Head title="Encuesta enviada — Impúlsate" />

    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-md text-center">
            <img src="/images/logo_impulsate.png" alt="Impúlsate" class="h-10 mx-auto mb-8" />

            <div class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-6">
                <svg class="w-10 h-10 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <h1 class="text-2xl font-black text-gray-900 mb-2">¡Gracias por tu respuesta!</h1>
            <p class="text-gray-500 mb-2">Tu opinión ha sido registrada exitosamente.</p>
            <p v-if="evento" class="text-guinda-700 font-semibold">{{ evento.nombre }}</p>

            <p class="mt-8 text-xs text-gray-400">
                Impúlsate · Instituto Yucateco de Emprendedores
            </p>
        </div>
    </div>
</template>
