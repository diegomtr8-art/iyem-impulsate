<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    evento: Object,
    pendientes: Array,
    aprobados: Array,
    rechazados: Array,
});

const tabActiva = ref('pendientes');

const pendientesProveedores = computed(() => props.pendientes.filter(s => s.tipo === 'proveedor'));
const pendientesCompradores = computed(() => props.pendientes.filter(s => s.tipo === 'comprador'));

// Modal de rechazo
const modalRechazo = ref(false);
const solicitudARechazar = ref(null);
const motivoRechazo = ref('');
const procesando = ref(false);
const procesandoId = ref(null);

// Mini-confirm por tarjeta
const confirmandoId = ref(null);

// Modal de perfil completo
const modalPerfil = ref(false);
const solicitudPreview = ref(null);

const abrirPerfil = (solicitud) => {
    solicitudPreview.value = solicitud;
    modalPerfil.value = true;
};

const abrirModalRechazo = (solicitud) => {
    solicitudARechazar.value = solicitud;
    motivoRechazo.value = '';
    modalRechazo.value = true;
    confirmandoId.value = null;
};

const cerrarModal = () => {
    modalRechazo.value = false;
    solicitudARechazar.value = null;
    motivoRechazo.value = '';
};

const confirmarRechazo = () => {
    if (!motivoRechazo.value.trim()) return;
    procesando.value = true;
    procesandoId.value = solicitudARechazar.value?.id;
    router.post(
        route('admin.eventos.solicitudes.rechazar', { evento: props.evento.id, user: solicitudARechazar.value.user_id }),
        { motivo_rechazo: motivoRechazo.value },
        {
            onFinish: () => { procesando.value = false; procesandoId.value = null; cerrarModal(); },
            preserveScroll: true,
        }
    );
};

const iniciarAprobar = (solicitud) => {
    confirmandoId.value = solicitud.id;
};

const confirmarAprobar = (solicitud) => {
    procesandoId.value = solicitud.id;
    confirmandoId.value = null;
    router.post(
        route('admin.eventos.solicitudes.aprobar', { evento: props.evento.id, user: solicitud.user_id }),
        {},
        {
            onFinish: () => { procesandoId.value = null; },
            preserveScroll: true,
        }
    );
};

const cancelarAprobar = () => { confirmandoId.value = null; };

const aprobarTodos = (tipo) => {
    const tipoLabel = tipo === 'proveedor' ? 'proveedores' : 'compradores';
    if (!confirm(`¿Aprobar todas las solicitudes pendientes de ${tipoLabel}?`)) return;
    router.post(
        route('admin.eventos.solicitudes.aprobar-todos', props.evento.id),
        { tipo },
        { preserveScroll: true }
    );
};

const revertir = (solicitud) => {
    router.post(
        route('admin.eventos.solicitudes.revertir', { evento: props.evento.id, user: solicitud.user_id }),
        {},
        { preserveScroll: true }
    );
};

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatFechaCorta = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};
</script>

<template>
    <AdminLayout title="Solicitudes al Evento">
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
                <Link :href="route('admin.eventos.index')"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Eventos
                </Link>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white truncate">{{ evento.nombre }}</h1>
                        <span v-if="evento.activa"
                            class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Activo
                        </span>
                        <span v-else class="px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                            Archivado
                        </span>
                    </div>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Gestión de solicitudes de registro</p>
                </div>
            </div>
        </template>

        <div class="max-w-5xl space-y-6">

            <!-- Tabs -->
            <div class="overflow-x-auto">
                <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 rounded-xl p-1 w-fit min-w-full sm:min-w-0">
                    <button @click="tabActiva = 'pendientes'"
                        :class="tabActiva === 'pendientes' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap flex items-center gap-2 justify-center sm:justify-start">
                        Pendientes
                        <span v-if="pendientes.length > 0"
                            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full text-xs font-bold bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-400 animate-pulse">
                            {{ pendientes.length }}
                        </span>
                    </button>
                    <button @click="tabActiva = 'aprobados'"
                        :class="tabActiva === 'aprobados' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap flex items-center gap-2 justify-center sm:justify-start">
                        Aprobados
                        <span v-if="aprobados.length > 0"
                            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full text-xs font-bold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                            {{ aprobados.length }}
                        </span>
                    </button>
                    <button @click="tabActiva = 'rechazados'"
                        :class="tabActiva === 'rechazados' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="flex-1 sm:flex-none px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap flex items-center gap-2 justify-center sm:justify-start">
                        Rechazados
                        <span v-if="rechazados.length > 0"
                            class="inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full text-xs font-bold bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">
                            {{ rechazados.length }}
                        </span>
                    </button>
                </div>
            </div>

            <!-- ── TAB PENDIENTES ─────────────────────────────────── -->
            <div v-if="tabActiva === 'pendientes'">
                <div v-if="pendientes.length === 0"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-12 text-center">
                    <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-700 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 font-medium">No hay solicitudes pendientes</p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Proveedores pendientes -->
                    <div v-if="pendientesProveedores.length > 0">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-guinda-500"></span>
                                Proveedores pendientes ({{ pendientesProveedores.length }})
                            </h3>
                            <button @click="aprobarTodos('proveedor')"
                                class="px-4 py-2 min-h-[40px] bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition-colors">
                                Aprobar todos los proveedores
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div v-for="s in pendientesProveedores" :key="s.id"
                                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <!-- Info -->
                                    <div class="flex items-start gap-3 flex-1 min-w-0">
                                        <div class="w-10 h-10 rounded-xl bg-guinda-100 dark:bg-guinda-900/30 border border-guinda-200 dark:border-guinda-800 flex items-center justify-center shrink-0 overflow-hidden">
                                            <img v-if="s.restaurantero?.logo_path" :src="'/storage/' + s.restaurantero.logo_path" class="w-full h-full object-contain" />
                                            <span v-else class="text-sm font-black text-guinda-700 dark:text-guinda-400">
                                                {{ (s.restaurantero?.nombre_restaurante || s.user?.name || '?').charAt(0).toUpperCase() }}
                                            </span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-gray-900 dark:text-white text-sm">{{ s.restaurantero?.nombre_restaurante || s.user?.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ s.user?.name }} · {{ s.user?.email }}</p>
                                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                                                <span v-if="s.restaurantero?.categoria" class="text-gray-500 dark:text-gray-400">{{ s.restaurantero.categoria }}</span>
                                                <span v-if="s.restaurantero?.municipio" class="text-gray-500 dark:text-gray-400">{{ s.restaurantero.municipio }}</span>
                                                <span v-if="s.restaurantero?.rfc" class="text-gray-400 dark:text-gray-600">RFC: {{ s.restaurantero.rfc }}</span>
                                                <span v-if="s.restaurantero?.aprobado"
                                                    class="text-emerald-600 dark:text-emerald-400 font-medium">✓ Aprobado globalmente</span>
                                                <span v-else class="text-amber-600 dark:text-amber-400 font-medium">⚠ Pendiente aprobación global</span>
                                                <span class="text-gray-300 dark:text-gray-700">{{ new Date(s.created_at).toLocaleDateString('es-MX', { month: 'short', day: 'numeric' }) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Acciones -->
                                    <div class="flex items-center gap-2 sm:flex-col sm:items-end sm:justify-center shrink-0">
                                        <button @click="abrirPerfil(s)"
                                            class="px-3 py-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-colors">
                                            Ver perfil
                                        </button>
                                        <!-- Mini-confirm aprobar -->
                                        <div v-if="confirmandoId === s.id" class="flex items-center gap-1.5 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-xl px-2 py-1">
                                            <span class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">¿Confirmar?</span>
                                            <button @click="confirmarAprobar(s)" :disabled="procesandoId === s.id"
                                                class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition-colors disabled:opacity-50">
                                                {{ procesandoId === s.id ? '...' : 'Sí' }}
                                            </button>
                                            <button @click="cancelarAprobar"
                                                class="px-2 py-1 text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                                                No
                                            </button>
                                        </div>
                                        <div v-else class="flex gap-2">
                                            <button @click="iniciarAprobar(s)" :disabled="procesandoId === s.id"
                                                class="px-3 py-2 min-h-[36px] bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-colors">
                                                <span v-if="procesandoId === s.id" class="flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                                </span>
                                                <span v-else>Aprobar</span>
                                            </button>
                                            <button @click="abrirModalRechazo(s)" :disabled="procesandoId === s.id"
                                                class="px-3 py-2 min-h-[36px] bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-colors">
                                                Rechazar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Compradores pendientes -->
                    <div v-if="pendientesCompradores.length > 0">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                Compradores pendientes ({{ pendientesCompradores.length }})
                            </h3>
                            <button @click="aprobarTodos('comprador')"
                                class="px-4 py-2 min-h-[40px] bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition-colors">
                                Aprobar todos los compradores
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div v-for="s in pendientesCompradores" :key="s.id"
                                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <div class="flex items-start gap-3 flex-1 min-w-0">
                                        <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 flex items-center justify-center shrink-0">
                                            <span class="text-sm font-black text-amber-700 dark:text-amber-400">
                                                {{ (s.user?.name || '?').charAt(0).toUpperCase() }}
                                            </span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-bold text-gray-900 dark:text-white text-sm">{{ s.user?.name }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ s.user?.email }}</p>
                                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-400 dark:text-gray-600">
                                                <span v-if="s.user?.telefono">{{ s.user.telefono }}</span>
                                                <span v-if="s.user?.necesidades" class="truncate max-w-[200px]">{{ s.user.necesidades }}</span>
                                                <span v-if="s.user?.citas_historico">{{ s.user.citas_historico }} citas históricas</span>
                                                <span>{{ new Date(s.created_at).toLocaleDateString('es-MX', { month: 'short', day: 'numeric' }) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 sm:flex-col sm:items-end sm:justify-center shrink-0">
                                        <button @click="abrirPerfil(s)"
                                            class="px-3 py-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-colors">
                                            Ver perfil
                                        </button>
                                        <div v-if="confirmandoId === s.id" class="flex items-center gap-1.5 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-xl px-2 py-1">
                                            <span class="text-xs text-emerald-700 dark:text-emerald-400 font-medium">¿Confirmar?</span>
                                            <button @click="confirmarAprobar(s)" :disabled="procesandoId === s.id"
                                                class="px-2 py-1 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition-colors disabled:opacity-50">
                                                {{ procesandoId === s.id ? '...' : 'Sí' }}
                                            </button>
                                            <button @click="cancelarAprobar" class="px-2 py-1 text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">No</button>
                                        </div>
                                        <div v-else class="flex gap-2">
                                            <button @click="iniciarAprobar(s)" :disabled="procesandoId === s.id"
                                                class="px-3 py-2 min-h-[36px] bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-colors">
                                                <span v-if="procesandoId === s.id">...</span>
                                                <span v-else>Aprobar</span>
                                            </button>
                                            <button @click="abrirModalRechazo(s)" :disabled="procesandoId === s.id"
                                                class="px-3 py-2 min-h-[36px] bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-colors">
                                                Rechazar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TAB APROBADOS ──────────────────────────────────── -->
            <div v-if="tabActiva === 'aprobados'">
                <div v-if="aprobados.length === 0"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400 font-medium">No hay aprobados aún</p>
                </div>
                <div v-else class="space-y-3">
                    <div v-for="s in aprobados" :key="s.id"
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-emerald-100 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 flex items-center justify-center shrink-0">
                                <span class="text-sm font-bold text-emerald-700 dark:text-emerald-400">
                                    {{ (s.restaurantero?.nombre_restaurante || s.user?.name || '?').charAt(0).toUpperCase() }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">
                                    {{ s.restaurantero?.nombre_restaurante || s.user?.name }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ s.user?.email }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 capitalize">
                                {{ s.tipo }}
                            </span>
                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                                Aprobado
                            </span>
                            <span class="text-xs text-gray-400 dark:text-gray-600 hidden sm:inline">
                                {{ formatFecha(s.respondido_at) }}
                            </span>
                            <button @click="abrirPerfil(s)"
                                class="px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                Ver
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TAB RECHAZADOS ─────────────────────────────────── -->
            <div v-if="tabActiva === 'rechazados'">
                <div v-if="rechazados.length === 0"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-12 text-center">
                    <p class="text-gray-500 dark:text-gray-400 font-medium">No hay rechazados</p>
                </div>
                <div v-else class="space-y-3">
                    <div v-for="s in rechazados" :key="s.id"
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 flex items-center justify-center shrink-0">
                                    <span class="text-sm font-bold text-red-600 dark:text-red-400">
                                        {{ (s.restaurantero?.nombre_restaurante || s.user?.name || '?').charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">
                                        {{ s.restaurantero?.nombre_restaurante || s.user?.name }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ s.user?.email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 capitalize">
                                    {{ s.tipo }}
                                </span>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-red-100 dark:bg-red-900/20 text-red-600 dark:text-red-400">
                                    Rechazado
                                </span>
                                <button @click="revertir(s)"
                                    class="px-3 py-1.5 min-h-[36px] text-xs font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-colors">
                                    Volver a pendiente
                                </button>
                                <button @click="abrirPerfil(s)"
                                    class="px-3 py-1.5 min-h-[36px] text-xs font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-colors">
                                    Ver
                                </button>
                            </div>
                        </div>
                        <div v-if="s.motivo_rechazo" class="mt-3 px-3 py-2 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800/30 rounded-lg">
                            <p class="text-xs text-red-700 dark:text-red-400">
                                <span class="font-semibold">Motivo:</span> {{ s.motivo_rechazo }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal de Rechazo -->
        <Teleport to="body">
            <div v-if="modalRechazo" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4"
                style="background:rgba(0,0,0,0.5)" @click.self="cerrarModal">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-t-2xl sm:rounded-2xl w-full sm:max-w-lg shadow-2xl max-h-[85vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">
                            Rechazar: {{ solicitudARechazar?.restaurantero?.nombre_restaurante || solicitudARechazar?.user?.name }}
                        </h2>
                        <button @click="cerrarModal" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Motivo del rechazo <span class="text-red-500">*</span>
                            </label>
                            <textarea v-model="motivoRechazo" rows="4"
                                placeholder="Ej: Los productos no corresponden al sector económico del evento..."
                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 resize-none placeholder-gray-400 dark:placeholder-gray-600">
                            </textarea>
                            <p v-if="!motivoRechazo.trim()" class="text-xs text-red-500 mt-1">El motivo es requerido</p>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <button @click="cerrarModal"
                                class="flex-1 sm:flex-none px-5 py-2.5 min-h-[44px] bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl transition-colors">
                                Cancelar
                            </button>
                            <button @click="confirmarRechazo" :disabled="!motivoRechazo.trim() || procesando"
                                class="flex-1 sm:flex-none px-5 py-2.5 min-h-[44px] bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-xl transition-colors">
                                {{ procesando ? 'Rechazando...' : 'Confirmar rechazo' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal de Perfil Completo -->
        <Teleport to="body">
            <div v-if="modalPerfil && solicitudPreview" class="fixed inset-0 z-50 flex items-start justify-center p-3 pt-12"
                style="background:rgba(0,0,0,0.55)" @click.self="modalPerfil = false">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[85vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">
                            Perfil: {{ solicitudPreview.restaurantero?.nombre_restaurante || solicitudPreview.user?.name }}
                        </h2>
                        <button @click="modalPerfil = false" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-6 py-5 space-y-5">
                        <!-- Proveedor -->
                        <template v-if="solicitudPreview.tipo === 'proveedor' && solicitudPreview.restaurantero">
                            <div class="flex items-start gap-4">
                                <div class="w-16 h-16 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center justify-center overflow-hidden shrink-0 bg-gray-50 dark:bg-gray-800">
                                    <img v-if="solicitudPreview.restaurantero.logo_path" :src="'/storage/' + solicitudPreview.restaurantero.logo_path" class="w-full h-full object-contain" />
                                    <span v-else class="text-2xl font-black text-gray-400">{{ solicitudPreview.restaurantero.nombre_restaurante?.charAt(0) }}</span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-lg">{{ solicitudPreview.restaurantero.nombre_restaurante }}</h3>
                                    <div class="flex flex-wrap gap-2 mt-1">
                                        <span v-if="solicitudPreview.restaurantero.aprobado"
                                            class="px-2 py-0.5 text-xs font-semibold rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                                            ✓ Aprobado globalmente
                                        </span>
                                        <span v-else class="px-2 py-0.5 text-xs font-semibold rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                            ⚠ Pendiente aprobación global
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <p v-if="solicitudPreview.restaurantero.descripcion" class="text-sm text-gray-600 dark:text-gray-400">
                                {{ solicitudPreview.restaurantero.descripcion }}
                            </p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                                <div v-if="solicitudPreview.restaurantero.rfc">
                                    <p class="text-xs text-gray-400 dark:text-gray-600">RFC</p>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ solicitudPreview.restaurantero.rfc }}</p>
                                </div>
                                <div v-if="solicitudPreview.restaurantero.municipio">
                                    <p class="text-xs text-gray-400 dark:text-gray-600">Municipio</p>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ solicitudPreview.restaurantero.municipio }}</p>
                                </div>
                                <div v-if="solicitudPreview.restaurantero.telefono">
                                    <p class="text-xs text-gray-400 dark:text-gray-600">Teléfono</p>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ solicitudPreview.restaurantero.telefono }}</p>
                                </div>
                                <div v-if="solicitudPreview.restaurantero.categoria">
                                    <p class="text-xs text-gray-400 dark:text-gray-600">Categoría</p>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ solicitudPreview.restaurantero.categoria }}</p>
                                </div>
                                <div v-if="solicitudPreview.restaurantero.sitio_web">
                                    <p class="text-xs text-gray-400 dark:text-gray-600">Sitio web</p>
                                    <a :href="solicitudPreview.restaurantero.sitio_web" target="_blank"
                                        class="font-medium text-guinda-700 dark:text-guinda-400 hover:underline truncate block max-w-full">
                                        {{ solicitudPreview.restaurantero.sitio_web }}
                                    </a>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-600">Historial</p>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ solicitudPreview.restaurantero.citas_count ?? 0 }} citas totales</p>
                                </div>
                            </div>
                            <!-- Productos top -->
                            <div v-if="solicitudPreview.restaurantero.productos_top?.length" class="space-y-2">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Productos/Servicios</p>
                                <div v-for="(prod, i) in solicitudPreview.restaurantero.productos_top.slice(0, 5)" :key="i"
                                    class="flex gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-xl">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-sm text-gray-800 dark:text-gray-200">{{ typeof prod === 'string' ? prod : prod.nombre }}</p>
                                        <p v-if="typeof prod !== 'string' && prod.descripcion" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ prod.descripcion }}</p>
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-600">
                                Registrado en la plataforma: {{ formatFechaCorta(solicitudPreview.restaurantero.created_at) }}
                            </p>
                        </template>

                        <!-- Comprador -->
                        <template v-else>
                            <div class="flex items-center gap-4">
                                <div class="w-14 h-14 rounded-full bg-amber-100 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 flex items-center justify-center shrink-0">
                                    <span class="text-xl font-black text-amber-700 dark:text-amber-400">
                                        {{ solicitudPreview.user?.name?.charAt(0).toUpperCase() }}
                                    </span>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-lg">{{ solicitudPreview.user?.name }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ solicitudPreview.user?.email }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div v-if="solicitudPreview.user?.telefono">
                                    <p class="text-xs text-gray-400 dark:text-gray-600">Teléfono</p>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ solicitudPreview.user.telefono }}</p>
                                </div>
                                <div v-if="solicitudPreview.user?.sitio_web">
                                    <p class="text-xs text-gray-400 dark:text-gray-600">Sitio web</p>
                                    <a :href="solicitudPreview.user.sitio_web" target="_blank"
                                        class="font-medium text-guinda-700 dark:text-guinda-400 hover:underline truncate block max-w-full">
                                        {{ solicitudPreview.user.sitio_web }}
                                    </a>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-600">Historial</p>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ solicitudPreview.user?.citas_historico ?? 0 }} citas históricas</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 dark:text-gray-600">Registro</p>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ formatFechaCorta(solicitudPreview.user?.created_at) }}</p>
                                </div>
                            </div>
                            <div v-if="solicitudPreview.user?.necesidades">
                                <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Necesidades/Intereses</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ solicitudPreview.user.necesidades }}</p>
                            </div>
                        </template>

                        <!-- Botones de acción -->
                        <div v-if="solicitudPreview.estado === 'pendiente'" class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <div v-if="confirmandoId === solicitudPreview.id" class="flex items-center gap-2 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800 rounded-xl px-3 py-2">
                                <span class="text-sm text-emerald-700 dark:text-emerald-400 font-medium">¿Confirmar aprobación?</span>
                                <button @click="confirmarAprobar(solicitudPreview); modalPerfil = false"
                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition-colors">
                                    Sí, aprobar
                                </button>
                                <button @click="cancelarAprobar" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 px-2">Cancelar</button>
                            </div>
                            <template v-else>
                                <button @click="iniciarAprobar(solicitudPreview)"
                                    class="flex-1 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold rounded-xl transition-colors">
                                    Aprobar
                                </button>
                                <button @click="abrirModalRechazo(solicitudPreview); modalPerfil = false"
                                    class="flex-1 px-4 py-2.5 bg-red-600 hover:bg-red-500 text-white text-sm font-bold rounded-xl transition-colors">
                                    Rechazar
                                </button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
