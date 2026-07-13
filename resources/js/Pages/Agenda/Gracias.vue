<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tipo:         String,
    propuesta:    Object,
    yaRespondida: { type: Boolean, default: false },
});

const esAceptada = computed(() => props.tipo === 'aceptada');

const citasOrdenadas = computed(() =>
    [...(props.propuesta?.citas ?? [])].sort((a, b) => new Date(a.slot_inicio) - new Date(b.slot_inicio))
);

const formatHora = (iso) => new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });
</script>

<template>
    <Head title="Gracias — Impulsate" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex items-center justify-center px-4 py-10 transition-colors">
        <div class="w-full max-w-md text-center">
            <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-12 w-auto mx-auto mb-6" />

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 shadow-sm">
                <template v-if="yaRespondida">
                    <div class="text-5xl mb-4">ℹ️</div>
                    <h1 class="text-xl font-black text-gray-900 dark:text-white mb-2">Esta propuesta ya fue respondida</h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                        Esta propuesta de agenda ya tiene una respuesta registrada
                        (<strong>{{ tipo }}</strong>). Si necesitas hacer un cambio, contacta a
                        <a href="mailto:impulsate@iyemyucatan.com" class="text-guinda-700 dark:text-guinda-400 underline">impulsate@iyemyucatan.com</a>.
                    </p>
                </template>

                <template v-else>
                    <div class="text-5xl mb-4">{{ esAceptada ? '🎉' : '📩' }}</div>

                    <h1 class="text-xl font-black text-gray-900 dark:text-white mb-2">
                        {{ esAceptada ? '¡Agenda aceptada!' : 'Respuesta registrada' }}
                    </h1>

                    <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                        <template v-if="esAceptada">
                            Gracias, <strong>{{ propuesta.comprador?.nombre_empresa || propuesta.comprador?.name }}</strong>. Tu agenda fue aceptada
                            y las citas ya fueron creadas en el sistema. Te contactaremos si hay algún cambio.
                        </template>
                        <template v-else>
                            Entendido. Hemos notificado al equipo de IMPULSATE. Te contactaremos pronto para coordinar
                            una nueva propuesta de agenda.
                        </template>
                    </p>

                    <div v-if="esAceptada && citasOrdenadas.length"
                        class="mt-5 text-left bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-800 rounded-xl divide-y divide-gray-200 dark:divide-gray-800">
                        <div v-for="c in citasOrdenadas" :key="c.id" class="flex justify-between px-4 py-3 text-sm">
                            <span class="font-bold text-guinda-700 dark:text-guinda-400">
                                {{ formatHora(c.slot_inicio) }} – {{ formatHora(c.slot_fin) }}
                            </span>
                            <span class="text-gray-700 dark:text-gray-300 font-medium">
                                {{ c.proveedor?.nombre_restaurante }}
                            </span>
                        </div>
                    </div>

                    <a v-if="esAceptada" href="/mi-panel"
                        class="mt-6 block w-full py-3 text-center bg-guinda-800 hover:bg-guinda-700 text-white font-bold rounded-xl text-sm transition-colors">
                        Ver mis citas en Mi Panel →
                    </a>
                </template>
            </div>

            <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-6">
                Programa Gubernamental • Gobierno del Estado de Yucatán
            </p>
        </div>
    </div>
</template>
