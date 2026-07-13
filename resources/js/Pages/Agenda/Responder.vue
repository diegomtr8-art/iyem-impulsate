<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({ propuesta: Object, token: String });

const procesando = ref(false);

const fechaEvento = computed(() => {
    if (!props.propuesta?.evento?.fecha_hora_inicio) return '';
    return new Date(props.propuesta.evento.fecha_hora_inicio).toLocaleDateString('es-MX', {
        day: 'numeric', month: 'long', year: 'numeric',
    });
});

const citasOrdenadas = computed(() =>
    [...(props.propuesta?.citas ?? [])].sort((a, b) => new Date(a.slot_inicio) - new Date(b.slot_inicio))
);

const formatHora = (iso) => new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });

const aceptar = () => {
    procesando.value = true;
    router.get(route('agenda.aceptar', props.token), {}, { onFinish: () => procesando.value = false });
};

const rechazar = () => {
    if (!confirm('¿Seguro que quieres rechazar esta propuesta de agenda?')) return;
    procesando.value = true;
    router.get(route('agenda.rechazar', props.token), {}, { onFinish: () => procesando.value = false });
};
</script>

<template>
    <Head title="Propuesta de agenda — Impulsate" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex items-center justify-center px-4 py-10 transition-colors">
        <div class="w-full max-w-lg">
            <div class="text-center mb-8">
                <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-12 w-auto mx-auto mb-4" />
                <h1 class="text-2xl font-black text-gray-900 dark:text-white">Propuesta de agenda</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">{{ propuesta.evento?.nombre }} · {{ fechaEvento }}</p>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 shadow-sm">
                <template v-if="propuesta.estado === 'pendiente'">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-5">
                        Hola <strong class="text-gray-900 dark:text-white">{{ propuesta.comprador?.nombre_empresa || propuesta.comprador?.name }}</strong>,
                        el equipo de IMPULSATE preparó esta agenda de citas con proveedores para tu participación en el evento:
                    </p>

                    <div class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-800 rounded-xl divide-y divide-gray-200 dark:divide-gray-800 mb-6">
                        <div v-for="c in citasOrdenadas" :key="c.id" class="flex justify-between px-4 py-3 text-sm">
                            <span class="font-bold text-guinda-700 dark:text-guinda-400">
                                {{ formatHora(c.slot_inicio) }} – {{ formatHora(c.slot_fin) }}
                            </span>
                            <span class="text-gray-700 dark:text-gray-300 font-medium">
                                {{ c.proveedor?.nombre_restaurante }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <button type="button" @click="aceptar" :disabled="procesando"
                            class="w-full py-3 bg-green-600 hover:bg-green-700 disabled:opacity-50 text-white font-bold rounded-xl transition-colors text-sm shadow-sm">
                            ✅ Aceptar agenda
                        </button>
                        <button type="button" @click="rechazar" :disabled="procesando"
                            class="w-full py-3 border border-gray-300 dark:border-gray-700 text-gray-500 dark:text-gray-400 font-bold rounded-xl transition-colors text-sm">
                            ❌ No acepto
                        </button>
                    </div>
                </template>

                <div v-else class="text-center py-6">
                    <p class="text-gray-600 dark:text-gray-400 text-sm">
                        Esta propuesta ya fue respondida
                        (<strong>{{ propuesta.estado }}</strong>). Si necesitas hacer un cambio, contacta a
                        <a href="mailto:impulsate@iyemyucatan.com" class="text-guinda-700 dark:text-guinda-400 underline">impulsate@iyemyucatan.com</a>.
                    </p>
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-6">
                Programa Gubernamental • Gobierno del Estado de Yucatán
            </p>
        </div>
    </div>
</template>
