<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    propuesta:        Object,
    citasConfirmadas: Array,
});

const nombreComprador = props.propuesta.comprador?.nombre_empresa
    ?? props.propuesta.comprador?.name
    ?? 'Sin nombre';

const estadoConfig = {
    pendiente: { label: 'Pendiente', color: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400' },
    aceptada:  { label: 'Aceptada',  color: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' },
    rechazada: { label: 'Rechazada', color: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' },
};

const cfg = estadoConfig[props.propuesta.estado] ?? estadoConfig.pendiente;

const formatFecha = (f) => f
    ? new Date(f).toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—';

const formatHora = (f) => f
    ? new Date(f).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
    : '—';
</script>

<template>
    <AdminLayout :title="`Agenda — ${nombreComprador}`">
        <div class="p-6 max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white">Propuesta de agenda</h1>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
                        {{ propuesta.evento?.nombre ?? 'Evento' }}
                    </p>
                </div>
                <Link :href="route('admin.agenda.index')"
                    class="text-sm text-gray-500 hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors">
                    ← Volver
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm p-5 mb-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-bold mb-0.5">Comprador</p>
                        <p class="text-base font-black text-gray-900 dark:text-white">{{ nombreComprador }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ propuesta.comprador?.email }}</p>
                    </div>
                    <span :class="['text-xs font-bold px-3 py-1.5 rounded-full', cfg.color]">
                        {{ cfg.label }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-4 mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-bold mb-0.5">Enviada</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ formatFecha(propuesta.enviada_at) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide font-bold mb-0.5">Respondida</p>
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ formatFecha(propuesta.respondida_at) }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden mb-5">
                <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/50 border-b border-gray-100 dark:border-gray-800">
                    <h2 class="text-sm font-black text-gray-700 dark:text-white uppercase tracking-wide">
                        Citas propuestas ({{ propuesta.citas?.length ?? 0 }})
                    </h2>
                </div>

                <div v-if="propuesta.citas?.length">
                    <div v-for="cita in propuesta.citas" :key="cita.id"
                        class="flex items-center justify-between px-5 py-3 border-b border-gray-50 dark:border-gray-800/50 last:border-0">
                        <div class="flex items-center gap-3">
                            <img v-if="cita.proveedor?.logo_path"
                                :src="`/storage/${cita.proveedor.logo_path}`"
                                class="w-8 h-8 rounded-full object-cover border border-gray-200 dark:border-gray-700 flex-shrink-0" />
                            <div v-else class="w-8 h-8 rounded-full bg-guinda-100 dark:bg-guinda-900/30 flex items-center justify-center flex-shrink-0">
                                <span class="text-guinda-700 dark:text-guinda-400 text-xs font-bold">
                                    {{ (cita.proveedor?.nombre_restaurante ?? '?')[0] }}
                                </span>
                            </div>
                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                                {{ cita.proveedor?.nombre_restaurante ?? '—' }}
                            </span>
                        </div>
                        <span class="text-xs font-bold text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 px-2.5 py-1 rounded-full">
                            {{ formatHora(cita.slot_inicio) }} – {{ formatHora(cita.slot_fin) }}
                        </span>
                    </div>
                </div>
                <div v-else class="px-5 py-8 text-center text-sm text-gray-400">
                    Sin citas propuestas
                </div>
            </div>

            <div v-if="propuesta.estado === 'aceptada' && citasConfirmadas?.length"
                class="bg-white dark:bg-gray-900 border border-green-200 dark:border-green-800 rounded-2xl shadow-sm overflow-hidden mb-5">
                <div class="px-5 py-3 bg-green-50 dark:bg-green-900/20 border-b border-green-100 dark:border-green-800">
                    <h2 class="text-sm font-black text-green-700 dark:text-green-400 uppercase tracking-wide">
                        ✅ Citas confirmadas en sistema ({{ citasConfirmadas.length }})
                    </h2>
                </div>
                <div v-for="cita in citasConfirmadas" :key="cita.id"
                    class="flex items-center justify-between px-5 py-3 border-b border-green-50 dark:border-green-900/20 last:border-0">
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ cita.restaurantero?.nombre_restaurante ?? '—' }}
                    </span>
                    <span class="text-xs font-bold text-green-700 dark:text-green-400 bg-green-100 dark:bg-green-900/30 px-2.5 py-1 rounded-full">
                        {{ formatHora(cita.inicio) }} – {{ formatHora(cita.fin) }}
                    </span>
                </div>
            </div>

            <div class="flex justify-end">
                <Link :href="route('admin.agenda.index')"
                    class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">
                    ← Volver a la lista
                </Link>
            </div>
        </div>
    </AdminLayout>
</template>
