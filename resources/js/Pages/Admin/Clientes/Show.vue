<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    cliente: Object,
    eventosAsistidos: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, aceptadas: 0, pendientes: 0 }) },
});

const confirmarEliminar = ref(false);

const estadoClases = {
    pendiente:  'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    confirmada: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    cancelada:  'bg-gray-500/15 text-gray-500 dark:text-gray-400',
    completada: 'bg-indigo-500/15 text-indigo-700 dark:text-indigo-400',
    reagendada: 'bg-sky-500/15 text-sky-700 dark:text-sky-400',
};

const estadoLabel = {
    pendiente:  'Pendiente',
    confirmada: 'Confirmada',
    cancelada:  'Cancelada',
    completada: 'Completada',
    reagendada: 'Reagendada',
};

const eventoEstadoClases = {
    aprobado:  'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    pendiente: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    rechazado: 'bg-red-500/15 text-red-600 dark:text-red-400',
};

const eventoEstadoLabel = {
    aprobado:  'Aprobado',
    pendiente: 'Pendiente',
    rechazado: 'Rechazado',
};

const formatFechaCorta = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric',
    });
};

const formatFechaEvento = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
};

const iniciales = (nombre) => {
    if (!nombre) return '?';
    return nombre.trim().split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();
};

const esEventoActivo = (ev) => {
    if (!ev.activa) return false;
    const fin = ev.fecha_hora_fin ? new Date(ev.fecha_hora_fin) : null;
    return fin ? fin >= new Date() : true;
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
                <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Detalle de comprador</h1>
            </div>
        </template>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- ── Columna izquierda: tarjeta de identidad ── -->
            <div class="lg:col-span-1 space-y-4">

                <!-- Card principal -->
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm p-6">
                    <div class="flex flex-col items-center text-center mb-5">
                        <div class="w-20 h-20 rounded-full bg-guinda-100 dark:bg-guinda-500/15 text-guinda-800 dark:text-guinda-300 font-black text-2xl flex items-center justify-center mb-3">
                            {{ iniciales(cliente.name) }}
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ cliente.name }}</h2>
                        <p class="text-sm text-gray-400 dark:text-gray-500 mt-0.5 mb-2">{{ cliente.email }}</p>
                        <div class="flex gap-2 flex-wrap justify-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-guinda-50 dark:bg-guinda-500/10 text-guinda-700 dark:text-guinda-400">
                                Comprador
                            </span>
                            <span :class="cliente.perfil_completo
                                ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400'"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                {{ cliente.perfil_completo ? 'Perfil completo' : 'Perfil incompleto' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 mb-4">
                        <svg class="w-4 h-4 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Registrado: {{ formatFechaCorta(cliente.created_at) }}
                    </div>

                    <hr class="border-gray-100 dark:border-gray-800 mb-4"/>

                    <!-- Contacto -->
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Contacto</p>
                    <div class="space-y-2.5 text-sm mb-4">
                        <div class="flex items-center gap-2.5 text-gray-600 dark:text-gray-300">
                            <svg class="w-4 h-4 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <span class="truncate">{{ cliente.email }}</span>
                        </div>
                        <div class="flex items-center gap-2.5 text-gray-500 dark:text-gray-400">
                            <svg class="w-4 h-4 text-gray-300 dark:text-gray-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span>{{ cliente.telefono || '—' }}</span>
                        </div>
                    </div>

                    <hr class="border-gray-100 dark:border-gray-800 mb-4"/>

                    <!-- Datos de perfil -->
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Datos de perfil</p>
                    <div class="space-y-2.5 text-sm">
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 dark:text-gray-500 shrink-0">Empresa</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200 text-right">{{ cliente.nombre_empresa || '—' }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 dark:text-gray-500 shrink-0">Municipio</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ cliente.municipio || '—' }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 dark:text-gray-500 shrink-0">RFC</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200 font-mono text-xs">{{ cliente.rfc || '—' }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 dark:text-gray-500 shrink-0">CURP</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200 font-mono text-xs">{{ cliente.curp || '—' }}</span>
                        </div>
                        <div class="flex justify-between gap-2">
                            <span class="text-gray-400 dark:text-gray-500 shrink-0">Página web</span>
                            <a v-if="cliente.sitio_web" :href="cliente.sitio_web" target="_blank"
                                class="text-guinda-700 dark:text-guinda-400 underline text-xs truncate max-w-[160px]">
                                {{ cliente.sitio_web.replace(/^https?:\/\//, '') }}
                            </a>
                            <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                        </div>
                        <div class="flex justify-between gap-2 items-center">
                            <span class="text-gray-400 dark:text-gray-500 shrink-0">Cámara/Asoc.</span>
                            <span v-if="cliente.camara_asociacion"
                                :class="cliente.camara_asociacion === 'CANIRAC'
                                    ? 'bg-guinda-50 dark:bg-guinda-500/10 text-guinda-700 dark:text-guinda-400'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                {{ cliente.camara_asociacion }}
                            </span>
                            <span v-else class="text-xs text-gray-400 dark:text-gray-600">No especificado</span>
                        </div>
                        <div v-if="cliente.camara_asociacion === 'CANIRAC'" class="flex justify-between gap-2">
                            <span class="text-gray-400 dark:text-gray-500 shrink-0">Establecimiento</span>
                            <span class="font-medium text-gray-800 dark:text-gray-200 text-right">{{ cliente.nombre_establecimiento || '—' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Stats -->
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm p-4">
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Estadísticas</p>
                    <div class="flex flex-wrap gap-2">
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700">
                            {{ stats.total }} citas totales
                        </span>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20">
                            {{ stats.aceptadas }} confirmadas
                        </span>
                        <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold border bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/20">
                            {{ stats.pendientes }} pendientes
                        </span>
                    </div>
                </div>

                <!-- Acciones -->
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm p-4">
                    <p class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Acciones</p>
                    <Link :href="route('admin.clientes.editar', cliente.id)"
                        class="w-full mb-2 py-2.5 bg-guinda-50 dark:bg-guinda-900/20 hover:bg-guinda-100 dark:hover:bg-guinda-900/40 text-guinda-700 dark:text-guinda-400 border border-guinda-200 dark:border-guinda-800 font-semibold rounded-xl text-sm transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        Editar comprador
                    </Link>
                    <button
                        @click="confirmarEliminar = true"
                        class="w-full py-2.5 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 font-semibold rounded-xl text-sm transition-colors flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Eliminar usuario
                    </button>
                </div>
            </div>

            <!-- ── Columna derecha ── -->
            <div class="lg:col-span-2 space-y-5">

                <!-- PARTICIPACIÓN EN EVENTOS -->
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <div>
                            <h2 class="font-bold text-gray-800 dark:text-white text-base">Participación en Eventos</h2>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">Eventos en los que ha participado</p>
                        </div>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-guinda-50 dark:bg-guinda-500/10 text-guinda-700 dark:text-guinda-400">
                            {{ eventosAsistidos.length }} eventos
                        </span>
                    </div>

                    <div v-if="eventosAsistidos.length > 0" class="p-4 space-y-3">
                        <div v-for="ev in eventosAsistidos" :key="ev.id"
                            class="bg-gray-50 dark:bg-gray-800/40 rounded-xl p-4 flex flex-wrap gap-3 items-start justify-between">
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white text-sm mb-1">{{ ev.nombre }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ formatFechaEvento(ev.fecha_hora_inicio) }}
                                    <span v-if="ev.fecha_hora_fin"> — {{ formatFechaEvento(ev.fecha_hora_fin) }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <span :class="ev.tipo === 'proveedor'
                                    ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                    : 'bg-guinda-50 dark:bg-guinda-500/10 text-guinda-700 dark:text-guinda-400'"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                    {{ ev.tipo === 'proveedor' ? 'Proveedor' : 'Comprador' }}
                                </span>
                                <span :class="eventoEstadoClases[ev.estado] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-500'"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                    {{ eventoEstadoLabel[ev.estado] ?? ev.estado }}
                                </span>
                                <span :class="esEventoActivo(ev)
                                    ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                    : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                    {{ esEventoActivo(ev) ? 'Activo' : 'Finalizado' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-else class="px-6 py-12 text-center text-gray-400 dark:text-gray-600">
                        <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/>
                        </svg>
                        <p class="text-sm">No ha participado en ningún evento</p>
                    </div>
                </div>

                <!-- HISTORIAL DE CITAS -->
                <div class="bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                        <div>
                            <h2 class="font-bold text-gray-800 dark:text-white text-base">Historial de Citas</h2>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ stats.total }} citas registradas</p>
                        </div>
                    </div>

                    <div v-if="cliente.citas_como_cliente && cliente.citas_como_cliente.length > 0" class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/30">
                                    <th class="text-left px-4 py-3 text-xs text-gray-400 dark:text-gray-500 font-semibold uppercase tracking-wider">Proveedor</th>
                                    <th class="text-left px-4 py-3 text-xs text-gray-400 dark:text-gray-500 font-semibold uppercase tracking-wider hidden md:table-cell">Evento</th>
                                    <th class="text-left px-4 py-3 text-xs text-gray-400 dark:text-gray-500 font-semibold uppercase tracking-wider">Fecha</th>
                                    <th class="text-left px-4 py-3 text-xs text-gray-400 dark:text-gray-500 font-semibold uppercase tracking-wider hidden lg:table-cell">Horario</th>
                                    <th class="text-center px-4 py-3 text-xs text-gray-400 dark:text-gray-500 font-semibold uppercase tracking-wider">Estado</th>
                                    <th class="text-left px-4 py-3 text-xs text-gray-400 dark:text-gray-500 font-semibold uppercase tracking-wider hidden xl:table-cell">Notas</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800/40">
                                <tr v-for="cita in cliente.citas_como_cliente" :key="cita.id"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-800/20 transition-colors">
                                    <td class="px-4 py-3.5">
                                        <p class="font-medium text-gray-800 dark:text-gray-200">
                                            {{ cita.restaurantero?.nombre_restaurante ?? '—' }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3.5 hidden md:table-cell">
                                        <span v-if="cita.evento" class="text-xs text-guinda-700 dark:text-guinda-400 font-medium">
                                            {{ cita.evento.nombre }}
                                        </span>
                                        <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">
                                        {{ new Date(cita.inicio).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' }) }}
                                    </td>
                                    <td class="px-4 py-3.5 text-gray-500 dark:text-gray-400 text-xs hidden lg:table-cell whitespace-nowrap">
                                        {{ new Date(cita.inicio).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) }}
                                        <span v-if="cita.fin"> – {{ new Date(cita.fin).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' }) }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 text-center">
                                        <span :class="estadoClases[cita.estado] ?? 'bg-gray-500/15 text-gray-500'"
                                            class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold">
                                            {{ estadoLabel[cita.estado] ?? cita.estado }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-gray-400 dark:text-gray-500 text-xs hidden xl:table-cell">
                                        <span v-if="cita.notas" class="italic line-clamp-1">{{ cita.notas }}</span>
                                        <span v-else>—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-else class="px-6 py-14 text-center text-gray-400 dark:text-gray-600">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm">Este usuario no tiene citas registradas</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal confirmar eliminación -->
        <div v-if="confirmarEliminar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="confirmarEliminar = false">
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-sm p-6 border border-gray-100 dark:border-gray-800">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-red-100 dark:bg-red-500/15 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 dark:text-white">Eliminar usuario</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Esta acción no se puede deshacer</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    ¿Estás seguro de eliminar a
                    <span class="font-semibold text-gray-800 dark:text-white">{{ cliente.name }}</span>?
                    Se eliminarán también todas sus citas.
                </p>
                <div class="flex gap-3">
                    <button @click="confirmarEliminar = false"
                        class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-xl text-sm transition-colors">
                        Cancelar
                    </button>
                    <button @click="eliminarCliente"
                        class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl text-sm transition-colors">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
