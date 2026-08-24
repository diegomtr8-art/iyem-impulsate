<script setup>
import { computed, onMounted, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import CuentaRegresiva from '@/Components/CuentaRegresiva.vue';

const props = defineProps({
    eventos: { type: Array, default: () => [] },
});

const irAPerfil = () => router.visit(route('user.perfil'));

const repostularse = (evento, tipo) => {
    if (!confirm(`¿Deseas volver a postularte al evento "${evento.nombre}"?`)) return;
    const ruta = tipo === 'proveedor' ? 'evento.registrar-proveedor' : 'evento.registrar-comprador';
    router.post(route(ruta, evento.id), {}, { preserveScroll: true });
};

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

const modalConvocatoria = ref(false);
const eventoParaRegistro = ref(null);
const tipoParaRegistro = ref(null);
const heLeidoConvocatoria = ref(false);
const errorModal = ref('');
const cargandoRegistro = ref(false);

const abrirModalRegistro = (evento, tipo) => {
    eventoParaRegistro.value = evento;
    tipoParaRegistro.value = tipo;
    heLeidoConvocatoria.value = false;
    errorModal.value = '';
    modalConvocatoria.value = true;
};

// Abrir automáticamente el modal de registro al bazar cuando se llega desde
// el botón "Regístrate" del sidebar (?tab=eventos&registrar=1)
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('registrar') !== '1') return;

    if (eventoActivo.value?.tipo_evento === 'bazar_exposicion' && !eventoActivo.value.mi_registro?.expositor) {
        abrirModalRegistro(eventoActivo.value, 'expositor');
    }

    const url = new URL(window.location.href);
    url.searchParams.delete('registrar');
    window.history.replaceState({}, '', url);
});

const confirmarRegistro = () => {
    if (!heLeidoConvocatoria.value || cargandoRegistro.value) return;
    const ruta = tipoParaRegistro.value === 'proveedor'
        ? 'evento.registrar-proveedor'
        : 'evento.registrar-comprador';
    cargandoRegistro.value = true;
    errorModal.value = '';
    router.post(route(ruta, eventoParaRegistro.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => {
            modalConvocatoria.value = false;
        },
        onError: (errors) => {
            errorModal.value = errors.error || Object.values(errors)[0] || 'Ocurrió un error. Intenta de nuevo.';
        },
        onFinish: () => {
            cargandoRegistro.value = false;
        },
    });
};

const confirmarRegistroBazar = () => {
    if (!heLeidoConvocatoria.value || cargandoRegistro.value) return;
    cargandoRegistro.value = true;
    errorModal.value = '';
    router.post(route('evento.registrar-bazar', eventoParaRegistro.value.id), {}, {
        preserveScroll: true,
        onSuccess: () => { modalConvocatoria.value = false; },
        onError: (errors) => {
            errorModal.value = errors.error || Object.values(errors)[0] || 'Ocurrió un error.';
        },
        onFinish: () => { cargandoRegistro.value = false; },
    });
};

// Documentos legales (INE/CSF) directo desde el modal de registro a bazar
const docsForm = useForm({
    ine: null,
    csf: null,
    csf_fecha: authUser.value?.csf_fecha ?? '',
});

const csfVencida = computed(() => {
    if (!authUser.value?.csf_fecha) return false;
    const fecha = new Date(authUser.value.csf_fecha);
    const limite = new Date();
    limite.setMonth(limite.getMonth() - 3);
    return fecha < limite;
});

const documentosCompletos = computed(() =>
    !!authUser.value?.ine_path && !!authUser.value?.csf_path && !csfVencida.value
);

const onIneChangeModal = (e) => { docsForm.ine = e.target.files[0] ?? null; };
const onCsfChangeModal = (e) => { docsForm.csf = e.target.files[0] ?? null; };

const guardarDocumentosModal = () => {
    docsForm.post(route('perfil.documentos.subir'), {
        preserveScroll: true,
        preserveState: true,
        forceFormData: true,
        onSuccess: () => { docsForm.ine = null; docsForm.csf = null; },
    });
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
                        <p v-if="eventoActivo.descripcion"
                           class="text-sm text-gray-600 dark:text-gray-400 mt-2 leading-relaxed">
                            {{ eventoActivo.descripcion }}
                        </p>
                    </div>
                </div>

                <template v-if="eventoActivo.tipo_evento !== 'bazar_exposicion'">
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
                </template>

                <!-- Para bazar: mostrar solo la fecha del evento si existe -->
                <template v-else>
                    <div v-if="eventoActivo.fecha_hora_inicio
                               || eventoActivo.fecha_hora_fin
                               || eventoActivo.fecha_aceptacion_solicitudes"
                         class="p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl space-y-1.5 text-xs text-gray-600 dark:text-gray-400 mb-4">
                        <div v-if="eventoActivo.fecha_aceptacion_solicitudes" class="flex gap-2">
                            <span class="w-2 h-2 bg-blue-400 rounded-full mt-1 shrink-0"></span>
                            <span>Apertura de registro:
                                <strong class="text-gray-800 dark:text-gray-200">
                                    {{ formatFecha(eventoActivo.fecha_aceptacion_solicitudes) }}
                                </strong>
                            </span>
                        </div>
                        <div v-if="eventoActivo.fecha_hora_inicio" class="flex gap-2">
                            <span class="w-2 h-2 bg-guinda-500 rounded-full mt-1 shrink-0"></span>
                            <span>Fecha del evento:
                                <strong class="text-gray-800 dark:text-gray-200">
                                    {{ formatFecha(eventoActivo.fecha_hora_inicio) }}
                                </strong>
                            </span>
                        </div>
                        <div v-if="eventoActivo.fecha_hora_fin" class="flex gap-2">
                            <span class="w-2 h-2 bg-red-400 rounded-full mt-1 shrink-0"></span>
                            <span>Fin: <strong class="text-gray-800 dark:text-gray-200">
                                {{ formatFecha(eventoActivo.fecha_hora_fin) }}
                            </strong></span>
                        </div>
                    </div>
                </template>

                <!-- CTAs según rol -->
                <div class="space-y-3">

                    <!-- CASO BAZAR: registro simple sin rol -->
                    <template v-if="eventoActivo.tipo_evento === 'bazar_exposicion'">
                        <!-- No registrado -->
                        <div v-if="!eventoActivo.mi_registro?.expositor">
                            <!-- Cuenta regresiva si el registro aún no ha abierto -->
                            <CuentaRegresiva
                                v-if="eventoActivo.segundos_hasta_solicitudes"
                                :segundos="eventoActivo.segundos_hasta_solicitudes"
                                label="El registro de expositores abre en:"
                                label-cero="¡El registro de expositores ya está abierto!" />

                            <!-- Registro abierto: mostrar botón -->
                            <template v-else>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">
                                    Solicita tu participación en este evento. El equipo revisará tu
                                    solicitud y te notificará por correo el resultado.
                                </p>
                                <button @click="abrirModalRegistro(eventoActivo, 'expositor')"
                                    class="w-full sm:w-auto px-5 py-2.5 bg-guinda-800 hover:bg-guinda-700
                                           text-white text-sm font-semibold rounded-xl transition-colors">
                                    Solicitar participación
                                </button>
                            </template>
                        </div>

                        <!-- Pendiente -->
                        <div v-else-if="eventoActivo.mi_registro.expositor.estado === 'pendiente'"
                             class="p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200
                                    dark:border-amber-800/40 rounded-xl">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-sm font-semibold text-amber-700 dark:text-amber-400">
                                    Solicitud enviada — pendiente de revisión
                                </span>
                            </div>
                            <p class="text-xs text-amber-600 dark:text-amber-500/80 mt-1 ml-6">
                                Te notificaremos por correo cuando tu solicitud sea revisada.
                            </p>
                        </div>

                        <!-- Aprobado para evaluación, pero aún sin decisión final -->
                        <div v-else-if="eventoActivo.mi_registro.expositor.estado === 'aprobado'
                                && !eventoActivo.mi_registro.expositor.seleccionado
                                && !eventoActivo.mi_registro.expositor.correo_rechazo_enviado"
                             class="p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200
                                    dark:border-amber-800/40 rounded-xl">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-sm font-semibold text-amber-700 dark:text-amber-400">
                                    Pendiente — tu solicitud está en evaluación
                                </span>
                            </div>
                            <p class="text-xs text-amber-600 dark:text-amber-500/80 mt-1 ml-6">
                                El equipo está evaluando tu participación. Te notificaremos por
                                correo el resultado final.
                            </p>
                        </div>

                        <!-- Seleccionado -->
                        <div v-else-if="eventoActivo.mi_registro.expositor.estado === 'aprobado'
                                && eventoActivo.mi_registro.expositor.seleccionado"
                             class="p-4 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200
                                    dark:border-emerald-700/40 rounded-xl">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">
                                    ¡Participación aprobada!
                                </span>
                            </div>
                            <p class="text-xs text-emerald-600 dark:text-emerald-500/80 mt-1 ml-6">
                                Has sido seleccionado(a) para participar en este evento. Revisa
                                tu correo para los detalles.
                            </p>
                        </div>

                        <!-- No seleccionado (evaluado, decisión final comunicada) -->
                        <div v-else-if="eventoActivo.mi_registro.expositor.estado === 'aprobado'
                                && !eventoActivo.mi_registro.expositor.seleccionado
                                && eventoActivo.mi_registro.expositor.correo_rechazo_enviado"
                             class="p-4 bg-red-50 dark:bg-red-950/20 border border-red-200
                                    dark:border-red-800/40 rounded-xl space-y-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-sm font-semibold text-red-700 dark:text-red-400">
                                    Tu solicitud no fue seleccionada
                                </span>
                            </div>
                            <p v-if="eventoActivo.mi_registro.expositor.notas_rechazo"
                               class="text-xs text-red-600 dark:text-red-500/80 ml-6">
                                {{ eventoActivo.mi_registro.expositor.notas_rechazo }}
                            </p>
                            <p class="text-xs text-red-600 dark:text-red-500/80 ml-6">
                                Revisa tu correo para ver el detalle de tu evaluación.
                            </p>
                        </div>

                        <!-- Rechazado en la revisión inicial (antes de pasar a evaluación) -->
                        <div v-else-if="eventoActivo.mi_registro.expositor.estado === 'rechazado'"
                             class="p-4 bg-red-50 dark:bg-red-950/20 border border-red-200
                                    dark:border-red-800/40 rounded-xl space-y-2">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-sm font-semibold text-red-700 dark:text-red-400">
                                    Tu solicitud fue rechazada
                                </span>
                            </div>
                            <p v-if="eventoActivo.mi_registro.expositor.motivo_rechazo"
                               class="text-xs text-red-600 dark:text-red-500/80 ml-6">
                                <span class="font-semibold">Motivo:</span>
                                {{ eventoActivo.mi_registro.expositor.motivo_rechazo }}
                            </p>
                        </div>
                    </template>

                    <!-- CASO ENCUENTRO DE NEGOCIOS: flujo original -->
                    <template v-else>
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
                            <button @click="abrirModalRegistro(eventoActivo, 'proveedor')"
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
                        <div v-else-if="eventoActivo.mi_registro?.proveedor">
                            <div v-if="eventoActivo.mi_registro.proveedor.estado === 'rechazado'"
                                 class="border border-red-200 dark:border-red-800/40 bg-red-50 dark:bg-red-950/20 rounded-xl p-4 space-y-3">
                                <div>
                                    <p class="text-sm font-bold text-red-700 dark:text-red-400 mb-1">
                                        ❌ Tu solicitud al evento fue rechazada
                                    </p>
                                    <p v-if="eventoActivo.mi_registro.proveedor.motivo_rechazo"
                                       class="text-xs text-red-600 dark:text-red-400/80 leading-relaxed">
                                        <span class="font-semibold">Motivo:</span>
                                        {{ eventoActivo.mi_registro.proveedor.motivo_rechazo }}
                                    </p>
                                </div>
                                <div class="border-t border-red-200 dark:border-red-800/30 pt-3">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                        Actualiza tu perfil con lo indicado y vuelve a postularte:
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button @click="irAPerfil()"
                                            class="px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700
                                                   text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-xl transition-colors">
                                            Actualizar mi perfil
                                        </button>
                                        <button @click="repostularse(eventoActivo, 'proveedor')"
                                            class="px-4 py-2 bg-guinda-700 hover:bg-guinda-600 text-white
                                                   text-xs font-bold rounded-xl transition-colors">
                                            Volver a postularme →
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Tu registro como proveedor:</span>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full"
                                    :class="estadoBadgeClass(eventoActivo.mi_registro.proveedor.estado)">
                                    {{ estadoLabel(eventoActivo.mi_registro.proveedor.estado) }}
                                </span>
                            </div>
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
                            <button @click="abrirModalRegistro(eventoActivo, 'comprador')"
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
                        <div v-else-if="eventoActivo.mi_registro?.comprador">
                            <div v-if="eventoActivo.mi_registro.comprador.estado === 'rechazado'"
                                 class="border border-red-200 dark:border-red-800/40 bg-red-50 dark:bg-red-950/20 rounded-xl p-4 space-y-3">
                                <div>
                                    <p class="text-sm font-bold text-red-700 dark:text-red-400 mb-1">
                                        ❌ Tu solicitud al evento fue rechazada
                                    </p>
                                    <p v-if="eventoActivo.mi_registro.comprador.motivo_rechazo"
                                       class="text-xs text-red-600 dark:text-red-400/80 leading-relaxed">
                                        <span class="font-semibold">Motivo:</span>
                                        {{ eventoActivo.mi_registro.comprador.motivo_rechazo }}
                                    </p>
                                </div>
                                <div class="border-t border-red-200 dark:border-red-800/30 pt-3">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">
                                        Actualiza tu perfil con lo indicado y vuelve a postularte:
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button @click="irAPerfil()"
                                            class="px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700
                                                   text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-xl transition-colors">
                                            Actualizar mi perfil
                                        </button>
                                        <button @click="repostularse(eventoActivo, 'comprador')"
                                            class="px-4 py-2 bg-guinda-700 hover:bg-guinda-600 text-white
                                                   text-xs font-bold rounded-xl transition-colors">
                                            Volver a postularme →
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Tu registro como comprador:</span>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full"
                                    :class="estadoBadgeClass(eventoActivo.mi_registro.comprador.estado)">
                                    {{ estadoLabel(eventoActivo.mi_registro.comprador.estado) }}
                                </span>
                            </div>
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
                                    @click="abrirModalRegistro(eventoActivo, 'proveedor')"
                                    class="w-full sm:w-auto px-5 py-2.5 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                                    Registrarme como Proveedor
                                </button>
                                <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                                    El período de registro de proveedores ha cerrado.
                                </p>
                            </div>
                            <div v-else>
                                <div v-if="eventoActivo.mi_registro.proveedor.estado === 'rechazado'"
                                     class="border border-red-200 dark:border-red-800/40 bg-red-50 dark:bg-red-950/20 rounded-xl p-3 space-y-2">
                                    <p class="text-xs font-bold text-red-700 dark:text-red-400">
                                        ❌ Solicitud proveedor rechazada
                                        <span v-if="eventoActivo.mi_registro.proveedor.motivo_rechazo" class="font-normal">
                                            — {{ eventoActivo.mi_registro.proveedor.motivo_rechazo }}
                                        </span>
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button @click="irAPerfil()"
                                            class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700
                                                   text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-xl transition-colors">
                                            Actualizar perfil
                                        </button>
                                        <button @click="repostularse(eventoActivo, 'proveedor')"
                                            class="px-3 py-1.5 bg-guinda-700 hover:bg-guinda-600 text-white text-xs font-bold rounded-xl transition-colors">
                                            Volver a postularme →
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Proveedor:</span>
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full"
                                        :class="estadoBadgeClass(eventoActivo.mi_registro.proveedor.estado)">
                                        {{ estadoLabel(eventoActivo.mi_registro.proveedor.estado) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Botón Comprador -->
                            <div v-if="!eventoActivo.mi_registro?.comprador">
                                <CuentaRegresiva
                                    v-if="eventoActivo.segundos_hasta_compradores"
                                    :segundos="eventoActivo.segundos_hasta_compradores"
                                    label="El agendado de compradores abre en:"
                                    label-cero="¡Ya puedes registrarte como comprador!" />
                                <button v-else-if="eventoActivo.registro_comprador_abierto"
                                    @click="abrirModalRegistro(eventoActivo, 'comprador')"
                                    class="w-full sm:w-auto px-5 py-2.5 bg-emerald-700 hover:bg-emerald-600 text-white text-sm font-semibold rounded-xl transition-colors">
                                    Registrarme como Comprador
                                </button>
                                <p v-else class="text-xs text-gray-500 dark:text-gray-400">
                                    El período de registro de compradores ha cerrado.
                                </p>
                            </div>
                            <div v-else>
                                <div v-if="eventoActivo.mi_registro.comprador.estado === 'rechazado'"
                                     class="border border-red-200 dark:border-red-800/40 bg-red-50 dark:bg-red-950/20 rounded-xl p-3 space-y-2">
                                    <p class="text-xs font-bold text-red-700 dark:text-red-400">
                                        ❌ Solicitud comprador rechazada
                                        <span v-if="eventoActivo.mi_registro.comprador.motivo_rechazo" class="font-normal">
                                            — {{ eventoActivo.mi_registro.comprador.motivo_rechazo }}
                                        </span>
                                    </p>
                                    <div class="flex flex-wrap gap-2">
                                        <button @click="irAPerfil()"
                                            class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700
                                                   text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-xl transition-colors">
                                            Actualizar perfil
                                        </button>
                                        <button @click="repostularse(eventoActivo, 'comprador')"
                                            class="px-3 py-1.5 bg-guinda-700 hover:bg-guinda-600 text-white text-xs font-bold rounded-xl transition-colors">
                                            Volver a postularme →
                                        </button>
                                    </div>
                                </div>
                                <div v-else class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Comprador:</span>
                                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full"
                                        :class="estadoBadgeClass(eventoActivo.mi_registro.comprador.estado)">
                                        {{ estadoLabel(eventoActivo.mi_registro.comprador.estado) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
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
                    <span class="shrink-0 text-xs bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 px-2.5 py-1 rounded-full font-semibold">
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

    <!-- Modal de confirmación de convocatoria -->
    <Teleport to="body">
        <div v-if="modalConvocatoria" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
            <div class="bg-white dark:bg-gray-900 rounded-2xl max-w-md w-full p-6 shadow-xl border border-gray-200 dark:border-gray-800 max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Antes de continuar</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    <template v-if="tipoParaRegistro === 'expositor'">
                        Para solicitar tu participación en
                        <strong>{{ eventoParaRegistro?.nombre }}</strong>
                        debes haber leído la convocatoria oficial del evento.
                    </template>
                    <template v-else>
                        Para registrarte como
                        <strong>{{ tipoParaRegistro === 'proveedor' ? 'proveedor' : 'comprador' }}</strong>
                        en <strong>{{ eventoParaRegistro?.nombre }}</strong>
                        debes haber leído la convocatoria oficial del evento.
                    </template>
                </p>
                <a v-if="eventoParaRegistro?.convocatoria_url" :href="eventoParaRegistro.convocatoria_url" target="_blank"
                    class="block text-center w-full mb-4 px-4 py-2 border border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-400 text-sm font-semibold rounded-xl hover:bg-guinda-50 dark:hover:bg-guinda-950/20 transition-colors">
                    Leer la convocatoria
                </a>
                <label class="flex items-start gap-2 mb-4 cursor-pointer">
                    <input type="checkbox" v-model="heLeidoConvocatoria" class="mt-0.5 accent-guinda-700" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">He leído la convocatoria del evento.</span>
                </label>

                <!-- Documentos legales (solo bazar/exposición) -->
                <div v-if="tipoParaRegistro === 'expositor'" class="mb-4 space-y-3">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Documentos requeridos</p>

                    <!-- INE -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-gray-600 dark:text-gray-300">INE</span>
                            <span v-if="authUser?.ine_path"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                                ✓ Subido
                            </span>
                            <span v-else
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                ⚠ Sin subir
                            </span>
                        </div>
                        <label class="block">
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ authUser?.ine_path ? 'Reemplazar INE' : 'Subir INE' }} (PDF, JPG, PNG – máx 5 MB)</span>
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                                @change="onIneChangeModal"
                                class="mt-1 block w-full text-[11px] text-gray-500 dark:text-gray-400
                                       file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0
                                       file:text-[11px] file:font-semibold
                                       file:bg-guinda-50 dark:file:bg-guinda-900/30
                                       file:text-guinda-700 dark:file:text-guinda-400
                                       hover:file:bg-guinda-100 dark:hover:file:bg-guinda-900/50 cursor-pointer" />
                            <p v-if="docsForm.errors.ine" class="mt-1 text-[11px] text-red-600">{{ docsForm.errors.ine }}</p>
                        </label>
                    </div>

                    <!-- CSF -->
                    <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-3">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-xs font-bold text-gray-600 dark:text-gray-300">Constancia de Situación Fiscal</span>
                            <span v-if="authUser?.csf_path"
                                :class="csfVencida
                                    ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                                    : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold">
                                {{ csfVencida ? '⚠ Vencida' : '✓ Subida' }}
                            </span>
                            <span v-else
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                                ⚠ Sin subir
                            </span>
                        </div>
                        <div class="mb-2">
                            <label class="text-[11px] text-gray-500 dark:text-gray-400">Fecha de emisión *</label>
                            <input type="date" v-model="docsForm.csf_fecha"
                                :max="new Date().toISOString().split('T')[0]"
                                class="block w-full rounded-lg border border-gray-300 dark:border-gray-600
                                       bg-white dark:bg-gray-800 text-gray-900 dark:text-white
                                       px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-guinda-500" />
                            <p v-if="docsForm.errors.csf_fecha" class="mt-1 text-[11px] text-red-600">{{ docsForm.errors.csf_fecha }}</p>
                        </div>
                        <label class="block">
                            <span class="text-[11px] text-gray-500 dark:text-gray-400">{{ authUser?.csf_path ? 'Reemplazar CSF' : 'Subir CSF' }} (PDF, JPG, PNG – máx 5 MB)</span>
                            <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                                @change="onCsfChangeModal"
                                class="mt-1 block w-full text-[11px] text-gray-500 dark:text-gray-400
                                       file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0
                                       file:text-[11px] file:font-semibold
                                       file:bg-guinda-50 dark:file:bg-guinda-900/30
                                       file:text-guinda-700 dark:file:text-guinda-400
                                       hover:file:bg-guinda-100 dark:hover:file:bg-guinda-900/50 cursor-pointer" />
                            <p v-if="docsForm.errors.csf" class="mt-1 text-[11px] text-red-600">{{ docsForm.errors.csf }}</p>
                        </label>
                    </div>

                    <button type="button" @click="guardarDocumentosModal" :disabled="docsForm.processing || (!docsForm.ine && !docsForm.csf)"
                        class="w-full px-3 py-2 text-xs font-semibold rounded-lg border border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-50 dark:hover:bg-guinda-950/20 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                        {{ docsForm.processing ? 'Guardando documentos...' : 'Guardar documentos' }}
                    </button>
                    <p v-if="!documentosCompletos" class="text-[11px] text-amber-600 dark:text-amber-400">
                        Sube tu INE y tu CSF vigente (máx. 3 meses) para poder confirmar tu registro.
                    </p>
                </div>

                <!-- Error del servidor -->
                <div v-if="errorModal" class="mb-4 p-3 bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800 rounded-xl text-sm text-red-700 dark:text-red-400">
                    {{ errorModal }}
                </div>
                <div class="flex gap-3">
                    <button @click="modalConvocatoria = false" :disabled="cargandoRegistro"
                        class="flex-1 px-4 py-2 text-sm font-semibold rounded-xl border border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50 transition-colors">
                        Cancelar
                    </button>
                    <button @click="tipoParaRegistro === 'expositor' ? confirmarRegistroBazar() : confirmarRegistro()"
                        :disabled="!heLeidoConvocatoria || cargandoRegistro || (tipoParaRegistro === 'expositor' && !documentosCompletos)"
                        class="flex-1 px-4 py-2 text-sm font-semibold rounded-xl bg-guinda-800 hover:bg-guinda-700 disabled:opacity-40 disabled:cursor-not-allowed text-white transition-colors">
                        <span v-if="cargandoRegistro">Enviando...</span>
                        <span v-else>Confirmar registro</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
