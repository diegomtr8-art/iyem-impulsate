<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import CuentaRegresiva from '@/Components/CuentaRegresiva.vue';

const props = defineProps({
    eventos: { type: Array, default: () => [] },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const esProveedor = computed(() => authUser.value?.is_restaurantero ?? false);
const esComprador = computed(() => authUser.value?.is_cliente ?? false);
const tieneDualRol = computed(() => authUser.value?.tiene_dual_rol ?? false);

const eventoActivo = computed(() => props.eventos.find(e => e.activa) || null);
const eventoProximos = computed(() =>
    props.eventos.filter(e => !e.activa && e.fecha_hora_inicio && new Date(e.fecha_hora_inicio) > new Date())
);
const eventosPasados = computed(() =>
    props.eventos.filter(e => !e.activa && (!e.fecha_hora_inicio || new Date(e.fecha_hora_inicio) <= new Date()))
);

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', {
        year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};

const formatFechaCorta = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

const faseActual = (evento) => {
    if (!evento) return 0;
    const now = new Date();
    const inicio = evento.fecha_hora_inicio ? new Date(evento.fecha_hora_inicio) : null;
    const fin = evento.fecha_hora_fin ? new Date(evento.fecha_hora_fin) : null;
    const inicioComp = evento.fecha_hora_inicio_compradores ? new Date(evento.fecha_hora_inicio_compradores) : null;
    const inicioProvs = evento.fecha_hora_inicio_proveedores ? new Date(evento.fecha_hora_inicio_proveedores) : null;

    if (fin && now > fin) return 4;
    if (inicio && now >= inicio) return 3;
    if (inicioComp && now >= inicioComp) return 2;
    if (inicioProvs && now >= inicioProvs) return 1;
    return 0;
};

const registrarProveedor = (evento) => {
    router.post(route('evento.registrar-proveedor', evento.id), {}, { preserveScroll: true });
};

const registrarComprador = (evento) => {
    router.post(route('evento.registrar-comprador', evento.id), {}, { preserveScroll: true });
};

const estadoBadgeClass = (estado) => {
    if (estado === 'aprobado') return 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-700';
    if (estado === 'pendiente') return 'bg-amber-100 dark:bg-amber-950/30 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800';
    if (estado === 'rechazado') return 'bg-red-100 dark:bg-red-950/20 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-800';
    return 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400';
};

const estadoLabel = (estado) => {
    if (estado === 'aprobado') return 'Aprobado';
    if (estado === 'pendiente') return 'Pendiente de aprobación';
    if (estado === 'rechazado') return 'Rechazado';
    return estado;
};

const fases = [
    { label: 'Reg. Proveedores', fase: 1 },
    { label: 'Reg. Compradores', fase: 2 },
    { label: 'Evento Presencial', fase: 3 },
    { label: 'Finalizado', fase: 4 },
];
</script>

<template>
    <div class="space-y-6">

        <!-- SECCIÓN A: Evento Activo -->
        <div v-if="eventoActivo">
            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Evento Activo</h3>
            <div class="bg-white dark:bg-gray-900 border-2 border-emerald-300 dark:border-emerald-700 rounded-2xl p-5 shadow-sm">

                <!-- Header -->
                <div class="flex items-start gap-3 mb-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span>
                            <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">En Curso</span>
                        </div>
                        <h4 class="text-lg font-black text-gray-900 dark:text-white">{{ eventoActivo.nombre }}</h4>
                        <p v-if="eventoActivo.sector_economico" class="text-sm text-gray-500 dark:text-gray-400">
                            {{ eventoActivo.sector_economico }}
                        </p>
                    </div>
                </div>

                <!-- Timeline de fases -->
                <div class="overflow-x-auto mb-5">
                    <div class="flex items-center gap-0 min-w-max">
                        <template v-for="(f, i) in fases" :key="f.fase">
                            <div class="flex flex-col items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-colors"
                                    :class="faseActual(eventoActivo) >= f.fase
                                        ? 'bg-guinda-700 border-guinda-700 text-white'
                                        : 'bg-gray-100 dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-400 dark:text-gray-500'">
                                    {{ f.fase }}
                                </div>
                                <span class="text-[10px] text-center mt-1 w-20"
                                    :class="faseActual(eventoActivo) >= f.fase
                                        ? 'text-guinda-700 dark:text-guinda-400 font-semibold'
                                        : 'text-gray-400 dark:text-gray-600'">
                                    {{ f.label }}
                                </span>
                            </div>
                            <div v-if="i < fases.length - 1" class="h-0.5 w-8 mx-1 mb-4"
                                :class="faseActual(eventoActivo) > f.fase
                                    ? 'bg-guinda-400'
                                    : 'bg-gray-200 dark:bg-gray-700'">
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Fechas clave -->
                <div class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl space-y-1.5 text-xs text-gray-600 dark:text-gray-400 mb-4">
                    <div v-if="eventoActivo.fecha_hora_inicio_proveedores" class="flex gap-2">
                        <span class="w-2 h-2 bg-guinda-400 rounded-full mt-1 shrink-0"></span>
                        <span>Reg. proveedores: <strong class="text-gray-800 dark:text-gray-200">{{ formatFecha(eventoActivo.fecha_hora_inicio_proveedores) }}</strong>
                            <span v-if="eventoActivo.fecha_hora_fin_proveedores"> — {{ formatFecha(eventoActivo.fecha_hora_fin_proveedores) }}</span>
                        </span>
                    </div>
                    <div v-if="eventoActivo.fecha_hora_inicio_compradores" class="flex gap-2">
                        <span class="w-2 h-2 bg-emerald-400 rounded-full mt-1 shrink-0"></span>
                        <span>Agendado compradores: <strong class="text-gray-800 dark:text-gray-200">{{ formatFecha(eventoActivo.fecha_hora_inicio_compradores) }}</strong>
                            <span v-if="eventoActivo.fecha_hora_fin_compradores"> — {{ formatFecha(eventoActivo.fecha_hora_fin_compradores) }}</span>
                        </span>
                    </div>
                    <div v-if="eventoActivo.fecha_hora_inicio" class="flex gap-2">
                        <span class="w-2 h-2 bg-guinda-600 rounded-full mt-1 shrink-0"></span>
                        <span>Inicio evento: <strong class="text-gray-800 dark:text-gray-200">{{ formatFecha(eventoActivo.fecha_hora_inicio) }}</strong></span>
                    </div>
                    <div v-if="eventoActivo.fecha_hora_fin" class="flex gap-2">
                        <span class="w-2 h-2 bg-red-400 rounded-full mt-1 shrink-0"></span>
                        <span>Fin: <strong class="text-gray-800 dark:text-gray-200">{{ formatFecha(eventoActivo.fecha_hora_fin) }}</strong></span>
                    </div>
                </div>

                <!-- CTAs según rol -->
                <div class="space-y-3">

                    <!-- CASO: Solo Proveedor (no comprador) -->
                    <template v-if="esProveedor && !esComprador">
                        <!-- Cuenta regresiva si no ha abierto -->
                        <CuentaRegresiva
                            v-if="eventoActivo.segundos_hasta_proveedores && !eventoActivo.mi_registro?.proveedor"
                            :segundos="eventoActivo.segundos_hasta_proveedores"
                            label="El registro de proveedores abre en:"
                            label-cero="¡El registro de proveedores ya está abierto!" />

                        <!-- Ventana abierta y no registrado -->
                        <div v-else-if="eventoActivo.registro_proveedor_abierto && !eventoActivo.mi_registro?.proveedor">
                            <button @click="registrarProveedor(eventoActivo)"
                                class="w-full sm:w-auto px-5 py-2.5 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                                Solicitar registro como Proveedor
                            </button>
                        </div>

                        <!-- Ventana cerrada y no registrado -->
                        <div v-else-if="!eventoActivo.registro_proveedor_abierto && !eventoActivo.mi_registro?.proveedor"
                            class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl text-sm text-gray-600 dark:text-gray-400">
                            El período de registro de proveedores ha cerrado.
                        </div>

                        <!-- Ya registrado -->
                        <div v-else-if="eventoActivo.mi_registro?.proveedor" class="flex items-center gap-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Tu registro como proveedor:</span>
                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full"
                                :class="estadoBadgeClass(eventoActivo.mi_registro.proveedor.estado)">
                                {{ estadoLabel(eventoActivo.mi_registro.proveedor.estado) }}
                            </span>
                        </div>
                    </template>

                    <!-- CASO: Solo Comprador (no proveedor) -->
                    <template v-else-if="esComprador && !esProveedor">
                        <!-- No ha abierto aún -->
                        <div v-if="eventoActivo.segundos_hasta_compradores && !eventoActivo.mi_registro?.comprador">
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                El registro para compradores abre el <strong>{{ formatFecha(eventoActivo.fecha_hora_inicio_compradores) }}</strong>
                            </p>
                            <CuentaRegresiva
                                :segundos="eventoActivo.segundos_hasta_compradores"
                                label="El agendado de citas abre en:"
                                label-cero="¡Ya puedes registrarte como comprador!" />
                        </div>

                        <!-- Ventana abierta y no registrado -->
                        <div v-else-if="eventoActivo.registro_comprador_abierto && !eventoActivo.mi_registro?.comprador">
                            <button @click="registrarComprador(eventoActivo)"
                                class="w-full sm:w-auto px-5 py-2.5 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                                Solicitar registro como Comprador
                            </button>
                        </div>

                        <!-- Ventana cerrada y no registrado -->
                        <div v-else-if="!eventoActivo.registro_comprador_abierto && !eventoActivo.mi_registro?.comprador"
                            class="p-3 bg-gray-50 dark:bg-gray-800 rounded-xl text-sm text-gray-600 dark:text-gray-400">
                            El período de registro de compradores ha cerrado.
                        </div>

                        <!-- Ya registrado -->
                        <div v-else-if="eventoActivo.mi_registro?.comprador" class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Tu registro como comprador:</span>
                            <span class="px-2.5 py-1 text-xs font-semibold rounded-full"
                                :class="estadoBadgeClass(eventoActivo.mi_registro.comprador.estado)">
                                {{ estadoLabel(eventoActivo.mi_registro.comprador.estado) }}
                            </span>
                        </div>
                    </template>

                    <!-- CASO: Dual rol — registro independiente por cada ventana -->
                    <template v-else-if="tieneDualRol">
                        <div class="space-y-3">
                            <!-- Botón Proveedor -->
                            <div v-if="!eventoActivo.mi_registro?.proveedor">
                                <CuentaRegresiva
                                    v-if="eventoActivo.segundos_hasta_proveedores"
                                    :segundos="eventoActivo.segundos_hasta_proveedores"
                                    label="El registro de proveedores abre en:"
                                    label-cero="¡El registro de proveedores ya está abierto!" />
                                <button v-else-if="eventoActivo.registro_proveedor_abierto"
                                    @click="registrarProveedor(eventoActivo)"
                                    class="w-full sm:w-auto px-5 py-2.5 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                                    Registrarme como Proveedor
                                </button>
                                <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                                    El período de registro de proveedores ha cerrado.
                                </p>
                            </div>
                            <div v-else class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Proveedor:</span>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full"
                                    :class="estadoBadgeClass(eventoActivo.mi_registro.proveedor.estado)">
                                    {{ estadoLabel(eventoActivo.mi_registro.proveedor.estado) }}
                                </span>
                            </div>

                            <!-- Botón Comprador -->
                            <div v-if="!eventoActivo.mi_registro?.comprador">
                                <CuentaRegresiva
                                    v-if="eventoActivo.segundos_hasta_compradores"
                                    :segundos="eventoActivo.segundos_hasta_compradores"
                                    label="El agendado de compradores abre en:"
                                    label-cero="¡Ya puedes registrarte como comprador!" />
                                <button v-else-if="eventoActivo.registro_comprador_abierto"
                                    @click="registrarComprador(eventoActivo)"
                                    class="w-full sm:w-auto px-5 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white text-sm font-semibold rounded-xl transition-colors">
                                    Registrarme como Comprador
                                </button>
                                <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                                    El período de registro de compradores ha cerrado.
                                </p>
                            </div>
                            <div v-else class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Comprador:</span>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full"
                                    :class="estadoBadgeClass(eventoActivo.mi_registro.comprador.estado)">
                                    {{ estadoLabel(eventoActivo.mi_registro.comprador.estado) }}
                                </span>
                            </div>
                        </div>
                    </template>

                </div>
            </div>
        </div>

        <!-- Sin evento activo -->
        <div v-else class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-2xl p-5">
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">No hay ningún evento activo en este momento.</p>
            <p class="text-xs text-amber-700 dark:text-amber-400/80 mt-1">Revisa más adelante o consulta los eventos próximos.</p>
        </div>

        <!-- SECCIÓN B: Eventos Próximos -->
        <div v-if="eventoProximos.length > 0">
            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Próximos Eventos</h3>
            <div class="space-y-2">
                <div v-for="ev in eventoProximos" :key="ev.id"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ ev.nombre }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatFechaCorta(ev.fecha_hora_inicio) }}</p>
                    </div>
                    <span class="shrink-0 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 px-2.5 py-1 rounded-full font-semibold">
                        Próximamente
                    </span>
                </div>
            </div>
        </div>

        <!-- SECCIÓN C: Eventos Anteriores -->
        <div v-if="eventosPasados.length > 0">
            <h3 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Eventos Anteriores</h3>
            <div class="space-y-2">
                <div v-for="ev in eventosPasados" :key="ev.id"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4 flex items-center justify-between gap-3 opacity-70">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900 dark:text-white text-sm truncate">{{ ev.nombre }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatFechaCorta(ev.fecha_hora_inicio) }}</p>
                    </div>
                    <span class="shrink-0 text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 px-2.5 py-1 rounded-full font-medium">
                        Finalizado
                    </span>
                </div>
            </div>
        </div>

    </div>
</template>
