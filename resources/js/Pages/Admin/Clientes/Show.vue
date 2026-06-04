<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    cliente: Object,
});

const confirmarEliminar = ref(false);

const MAX_CITAS = 12;
const citasUsadas = computed(() => props.cliente.citas_como_cliente_count ?? 0);
const porcentajeCitas = computed(() => Math.min((citasUsadas.value / MAX_CITAS) * 100, 100));

const estadoClases = {
    pendiente:  'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    confirmada: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    cancelada:  'bg-gray-500/15 text-gray-500 dark:text-gray-400',
    completada: 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-400',
};

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleString('es-MX', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit',
    });
};

const formatFechaCorta = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
};

const iniciales = (nombre) => {
    if (!nombre) return '?';
    return nombre.trim().split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();
};

function eliminarCliente() {
    confirmarEliminar.value = false;
    router.delete(route('admin.usuarios.destroy', props.cliente.id), {
        onSuccess: () => router.visit(route('admin.usuarios.index')),
    });
}
</script>

<template>
    <AdminLayout :title="cliente.name">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.usuarios.index')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Detalle de cliente</h1>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <!-- Columna izquierda — perfil del cliente -->
            <div class="lg:col-span-2 space-y-5">

                <!-- Card principal -->
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl shadow-sm dark:shadow-none p-6 text-center">
                    <!-- Avatar -->
                    <div class="w-20 h-20 rounded-full bg-guinda-100 dark:bg-guinda-500/15 text-guinda-800 dark:text-guinda-300 font-black text-2xl flex items-center justify-center mx-auto mb-4">
                        {{ iniciales(cliente.name) }}
                    </div>

                    <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-1">{{ cliente.name }}</h2>

                    <!-- Badge rol -->
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-guinda-50 dark:bg-guinda-500/10 text-guinda-700 dark:text-guinda-400 mb-4">
                        Cliente
                    </span>

                    <div class="text-left space-y-3 mt-4 text-sm">
                        <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400">
                            <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                            <span class="truncate">{{ cliente.email }}</span>
                        </div>

                        <div class="flex items-center gap-3 text-gray-600 dark:text-gray-400">
                            <svg class="w-4 h-4 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span>Registrado: {{ formatFechaCorta(cliente.created_at) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Citas usadas -->
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl shadow-sm dark:shadow-none p-6">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-300 text-sm uppercase tracking-wider mb-4">Citas agendadas</h3>

                    <div class="flex items-end justify-between mb-3">
                        <div>
                            <span class="text-3xl font-black text-gray-900 dark:text-white">{{ citasUsadas }}</span>
                            <span class="text-gray-400 dark:text-gray-600 text-lg font-medium"> / {{ MAX_CITAS }}</span>
                        </div>
                        <span class="text-sm text-gray-500 dark:text-gray-400">{{ MAX_CITAS - citasUsadas }} disponibles</span>
                    </div>

                    <!-- Barra de progreso -->
                    <div class="h-2.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-500"
                            :style="`width: ${porcentajeCitas}%`"
                            :class="citasUsadas >= MAX_CITAS ? 'bg-red-500' : citasUsadas >= 9 ? 'bg-amber-500' : 'bg-guinda-600'"
                        ></div>
                    </div>

                    <p v-if="citasUsadas >= MAX_CITAS" class="text-xs text-red-500 mt-2 font-medium">
                        Límite de citas alcanzado
                    </p>
                    <p v-else-if="citasUsadas >= 9" class="text-xs text-amber-600 dark:text-amber-500 mt-2 font-medium">
                        Casi al límite
                    </p>
                </div>

                <!-- Botones de acción -->
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl shadow-sm dark:shadow-none p-6 space-y-3">
                    <h3 class="font-semibold text-gray-700 dark:text-gray-300 text-sm uppercase tracking-wider mb-4">Acciones</h3>
                    <button
                        @click="confirmarEliminar = true"
                        class="w-full py-2.5 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 font-semibold rounded-xl text-sm transition-colors flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Eliminar usuario
                    </button>
                </div>
            </div>

            <!-- Columna derecha — historial de citas -->
            <div class="lg:col-span-3">
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl shadow-sm dark:shadow-none overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-gray-800">
                        <h2 class="font-bold text-gray-800 dark:text-white text-lg">Historial de Citas</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Todas las citas agendadas por este usuario</p>
                    </div>

                    <div v-if="cliente.citas_como_cliente && cliente.citas_como_cliente.length > 0" class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div
                            v-for="cita in cliente.citas_como_cliente"
                            :key="cita.id"
                            class="px-6 py-5 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div class="flex-1 min-w-0">
                                    <!-- Proveedor -->
                                    <p class="font-semibold text-gray-800 dark:text-white truncate">
                                        {{ cita.restaurantero?.nombre_restaurante ?? 'Proveedor no disponible' }}
                                    </p>

                                    <!-- Fecha y hora -->
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                                        {{ formatFecha(cita.inicio) }}
                                        <span v-if="cita.fin"> — {{ new Date(cita.fin).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) }}</span>
                                    </p>

                                    <!-- Notas -->
                                    <p v-if="cita.notas" class="text-sm text-gray-400 dark:text-gray-500 mt-2 italic line-clamp-2">
                                        "{{ cita.notas }}"
                                    </p>
                                </div>

                                <!-- Estado badge -->
                                <div class="shrink-0">
                                    <span
                                        :class="estadoClases[cita.estado] || 'bg-gray-500/15 text-gray-500 dark:text-gray-400'"
                                        class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold capitalize"
                                    >
                                        {{ cita.estado }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="px-6 py-14 text-center text-gray-400 dark:text-gray-600">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <p class="text-sm">Este usuario no tiene citas registradas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal confirmar eliminación -->
        <div v-if="confirmarEliminar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="confirmarEliminar = false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl dark:shadow-2xl w-full max-w-sm p-6 border border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-red-100 dark:bg-red-500/15 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 dark:text-white">Eliminar usuario</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Esta acción no se puede deshacer</p>
                    </div>
                </div>

                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    ¿Estás seguro de que quieres eliminar a
                    <span class="font-semibold text-gray-800 dark:text-white">{{ cliente.name }}</span>?
                    Se eliminarán también todas sus citas.
                </p>

                <div class="flex gap-3">
                    <button
                        @click="confirmarEliminar = false"
                        class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-xl text-sm transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="eliminarCliente"
                        class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl text-sm transition-colors"
                    >
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
