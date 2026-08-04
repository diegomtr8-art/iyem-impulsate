<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    encuesta: Object,
});
</script>

<template>
    <AdminLayout title="Detalle de Respuesta">
        <template #header>
            <div class="flex items-center gap-3 flex-wrap">
                <Link :href="route('admin.encuestas.index')"
                    class="text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors text-sm">
                    ← Volver a Encuestas
                </Link>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Respuesta #{{ encuesta.id }}</h1>
                <span v-if="encuesta.es_prueba"
                    class="px-3 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 rounded-full text-xs font-semibold">
                    PRUEBA
                </span>
            </div>
        </template>

        <div class="space-y-6 max-w-5xl">

            <!-- Meta info -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                    <p class="text-gray-400 dark:text-gray-500 text-xs mb-1">Evento</p>
                    <p class="text-gray-900 dark:text-white font-medium text-sm">{{ encuesta.evento?.nombre ?? '—' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                    <p class="text-gray-400 dark:text-gray-500 text-xs mb-1">Participante</p>
                    <p class="text-gray-900 dark:text-white font-medium text-sm">{{ encuesta.usuario?.name ?? '—' }}</p>
                    <p class="text-gray-400 dark:text-gray-500 text-xs">{{ encuesta.usuario?.email ?? '' }}</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                    <p class="text-gray-400 dark:text-gray-500 text-xs mb-1">Perfil</p>
                    <p class="font-semibold text-sm"
                       :class="encuesta.tipo === 'proveedor' ? 'text-guinda-700 dark:text-guinda-400' : 'text-emerald-600 dark:text-emerald-400'">
                        {{ encuesta.tipo === 'proveedor' ? '🏭 Proveedor' : '🛒 Comprador' }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                    <p class="text-gray-400 dark:text-gray-500 text-xs mb-1">Respondida</p>
                    <p class="text-gray-900 dark:text-white font-medium text-sm">{{ encuesta.completada_at ?? '—' }}</p>
                </div>
            </div>

            <!-- Respuestas -->
            <div class="space-y-4">
                <div
                    v-for="(resp, idx) in encuesta.respuestas"
                    :key="resp.id"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5"
                >
                    <div class="flex items-start gap-4">
                        <span class="flex-shrink-0 w-8 h-8 rounded-full bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400
                                     flex items-center justify-center text-sm font-bold">
                            {{ idx + 1 }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="text-gray-500 dark:text-gray-400 text-sm mb-2">{{ resp.pregunta }}</p>
                            <!-- Texto libre -->
                            <p v-if="resp.tipo === 'texto'"
                               class="text-gray-800 dark:text-gray-200 bg-gray-50 dark:bg-gray-800 rounded-lg p-3 text-sm italic">
                                {{ resp.respuesta || '(sin respuesta)' }}
                            </p>
                            <!-- Opciones / binario / escala -->
                            <span v-else
                                  class="inline-block px-4 py-2 bg-guinda-50 dark:bg-guinda-900/20 border border-guinda-200 dark:border-guinda-800/50
                                         text-guinda-800 dark:text-guinda-300 rounded-lg text-sm font-medium">
                                {{ resp.respuesta }}
                            </span>
                        </div>
                    </div>
                </div>

                <div v-if="!encuesta.respuestas.length"
                     class="text-center text-gray-400 dark:text-gray-600 py-12">
                    Esta encuesta no tiene respuestas registradas.
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
