<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    evento: Object,
    criterios: Array,
    solicitudes: Array,
    kpis: Object,
});

// ── Tabs ─────────────────────────────────────────────────────────
const tab = ref('pendientes'); // pendientes | evaluacion | seleccionados | no_seleccionados

const pendientes      = computed(() => props.solicitudes.filter(s => s.estado === 'pendiente'));
const enEvaluacion    = computed(() => props.solicitudes.filter(s => s.estado === 'aprobado'));
const seleccionados   = computed(() => props.solicitudes.filter(s => s.estado === 'aprobado' && s.seleccionado));
const noSeleccionados = computed(() => props.solicitudes.filter(s => s.estado === 'aprobado' && !s.seleccionado));

// ── Helpers ───────────────────────────────────────────────────────
const puntajeColor = (p) => {
    if (p === null || p === undefined) return 'text-gray-400';
    if (p >= 70) return 'text-emerald-600 dark:text-emerald-400';
    if (p >= 50) return 'text-amber-600 dark:text-amber-400';
    return 'text-red-600 dark:text-red-400';
};

const csfVigente = (fecha) => {
    if (!fecha) return false;
    const f = new Date(fecha);
    const ahora = new Date();
    const hace3meses = new Date();
    hace3meses.setMonth(hace3meses.getMonth() - 3);
    return f >= hace3meses && f <= ahora;
};

// ── Acciones: aprobar / rechazar solicitud pendiente ──────────────
const procesandoAprobacion = ref(null);
const aprobarSolicitud = (s) => {
    if (!confirm(`¿Aprobar la solicitud de ${s.name} para evaluación?`)) return;
    procesandoAprobacion.value = s.user_id;
    router.post(route('admin.eventos.bazar.aprobar', { evento: props.evento.id, userId: s.user_id }), {}, {
        preserveScroll: true,
        onFinish: () => { procesandoAprobacion.value = null; },
    });
};

const motivoRechazoPendiente = ref('');
const rechazandoId = ref(null);
const abrirRechazoPendiente = (s) => { rechazandoId.value = s.user_id; motivoRechazoPendiente.value = ''; };
const cerrarRechazoPendiente = () => { rechazandoId.value = null; motivoRechazoPendiente.value = ''; };
const confirmarRechazoPendiente = () => {
    if (!motivoRechazoPendiente.value.trim()) return;
    router.post(route('admin.eventos.bazar.rechazar', { evento: props.evento.id, userId: rechazandoId.value }), {
        motivo_rechazo: motivoRechazoPendiente.value,
    }, {
        preserveScroll: true,
        onSuccess: () => cerrarRechazoPendiente(),
    });
};

// ── Acciones: toggle selección ────────────────────────────────────
const procesandoToggle = ref(null);
const toggleSeleccion = (s) => {
    if (s.correo_aprobacion_enviado) return;
    procesandoToggle.value = s.user_id;
    router.post(route('admin.eventos.bazar.toggle-seleccion', props.evento.id), {
        user_id: s.user_id,
        seleccionado: !s.seleccionado,
    }, {
        preserveScroll: true,
        onFinish: () => { procesandoToggle.value = null; },
    });
};

// ── Marcar Seleccionado / Rechazado (pestaña En evaluación) ───────
const marcarSeleccion = (s, seleccionado) => {
    if (s.correo_aprobacion_enviado || s.correo_rechazo_enviado) return;
    if (s.seleccionado === seleccionado) return;
    procesandoToggle.value = s.user_id;
    router.post(route('admin.eventos.bazar.toggle-seleccion', props.evento.id), {
        user_id: s.user_id,
        seleccionado,
    }, {
        preserveScroll: true,
        onFinish: () => { procesandoToggle.value = null; },
    });
};

// ── Notas de rechazo (por participante no seleccionado) ───────────
const notasForm = ref({});
const guardandoNotas = ref(null);

const inicializarNotas = (s) => {
    if (notasForm.value[s.user_id] === undefined) {
        notasForm.value[s.user_id] = s.notas_rechazo ?? '';
    }
};

const guardarNotas = (s) => {
    guardandoNotas.value = s.user_id;
    router.post(route('admin.eventos.bazar.notas-rechazo', { evento: props.evento.id, userId: s.user_id }), {
        notas_rechazo: notasForm.value[s.user_id],
    }, {
        preserveScroll: true,
        onFinish: () => { guardandoNotas.value = null; },
    });
};

// ── Envío masivo de correos ───────────────────────────────────────
const enviandoAprobacion = ref(false);
const enviandoRechazo = ref(false);

const enviarAprobacion = () => {
    if (!confirm(`¿Enviar correo de selección a los participantes seleccionados?`)) return;
    enviandoAprobacion.value = true;
    router.post(route('admin.eventos.bazar.enviar-aprobacion', props.evento.id), {}, {
        preserveScroll: true,
        onFinish: () => { enviandoAprobacion.value = false; },
    });
};

const enviarRechazo = () => {
    if (!confirm('¿Enviar correo de resultado a los no seleccionados? Se incluirá el enlace a su evaluación.')) return;
    enviandoRechazo.value = true;
    router.post(route('admin.eventos.bazar.enviar-rechazo', props.evento.id), {}, {
        preserveScroll: true,
        onFinish: () => { enviandoRechazo.value = false; },
    });
};

// ── Slide-over: perfil completo ───────────────────────────────────
const perfilAbierto = ref(false);
const perfilViendo = ref(null);
const abrirPerfil = (s) => { perfilViendo.value = s; perfilAbierto.value = true; };
const cerrarPerfil = () => { perfilAbierto.value = false; perfilViendo.value = null; };

// ── Slide-over: evaluación por criterios ─────────────────────────
const evalPanelAbierto = ref(false);
const evaluandoParticipante = ref(null);
const puntajesForm = ref({});
const guardandoEval = ref(false);

const abrirEvaluar = (s) => {
    evaluandoParticipante.value = s;
    puntajesForm.value = {};
    s.evaluaciones.forEach(e => {
        puntajesForm.value[e.criterio_id] = e.puntaje !== null ? Number(e.puntaje) : 0;
    });
    evalPanelAbierto.value = true;
};

const cerrarEval = () => { evalPanelAbierto.value = false; evaluandoParticipante.value = null; };

const ponderado = (criterioId, porcentaje) => {
    return Math.round((puntajesForm.value[criterioId] ?? 0) / 100 * porcentaje * 100) / 100;
};

const totalPonderado = computed(() => {
    if (!evaluandoParticipante.value) return 0;
    return props.criterios.reduce((sum, c) => sum + ponderado(c.id, Number(c.porcentaje)), 0);
});

const guardarEvaluacion = () => {
    if (!evaluandoParticipante.value) return;
    guardandoEval.value = true;
    const evaluaciones = props.criterios.map(c => ({
        criterio_id: c.id,
        puntaje: puntajesForm.value[c.id] ?? 0,
    }));
    router.post(
        route('admin.eventos.bazar.evaluar', { evento: props.evento.id, userId: evaluandoParticipante.value.user_id }),
        { evaluaciones },
        { preserveScroll: true, onFinish: () => { guardandoEval.value = false; }, onSuccess: () => cerrarEval() }
    );
};
</script>

<template>
    <AdminLayout title="Gestión del Bazar">
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <Link :href="route('admin.eventos.index')" class="text-xs text-gray-400 hover:text-guinda-600 flex items-center gap-1 mb-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Volver a eventos
                    </Link>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Bazar — {{ evento.nombre }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Gestión de expositores · hasta {{ evento.max_espacios || '—' }} espacios</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button @click="enviarAprobacion" :disabled="!seleccionados.length || enviandoAprobacion"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Notificar seleccionados
                    </button>
                    <button @click="enviarRechazo" :disabled="enviandoRechazo"
                        class="inline-flex items-center gap-2 px-4 py-2 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-40 text-sm font-semibold rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Notificar no seleccionados
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6">

            <!-- KPIs -->
            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4">
                    <p class="text-2xl font-black text-gray-900 dark:text-white">{{ kpis.total }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">Total solicitudes</p>
                </div>
                <div class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 rounded-xl p-4">
                    <p class="text-2xl font-black text-amber-700 dark:text-amber-400">{{ kpis.pendientes }}</p>
                    <p class="text-xs text-amber-600 dark:text-amber-500 mt-0.5">Pendientes</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl p-4">
                    <p class="text-2xl font-black text-gray-900 dark:text-white">{{ kpis.en_evaluacion }}</p>
                    <p class="text-xs text-gray-500 mt-0.5">En evaluación</p>
                </div>
                <div class="bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-800/40 rounded-xl p-4">
                    <p class="text-2xl font-black text-guinda-700 dark:text-guinda-400">{{ kpis.seleccionados }} / {{ kpis.max_espacios || '—' }}</p>
                    <p class="text-xs text-guinda-600 dark:text-guinda-500 mt-0.5">Seleccionados</p>
                </div>
                <div class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 rounded-xl p-4">
                    <p class="text-2xl font-black text-emerald-700 dark:text-emerald-400">{{ kpis.disponibles }}</p>
                    <p class="text-xs text-emerald-600 dark:text-emerald-500 mt-0.5">Espacios disponibles</p>
                </div>
                <div class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800/40 rounded-xl p-4">
                    <p class="text-2xl font-black text-red-600 dark:text-red-400">{{ kpis.rechazados }}</p>
                    <p class="text-xs text-red-500 dark:text-red-500 mt-0.5">Rechazados</p>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 rounded-xl p-1 w-full sm:w-auto">
                <button v-for="t in [
                    { key: 'pendientes', label: 'Pendientes', count: kpis.pendientes },
                    { key: 'evaluacion', label: 'En evaluación', count: kpis.en_evaluacion },
                    { key: 'seleccionados', label: 'Seleccionados', count: kpis.seleccionados },
                    { key: 'no_seleccionados', label: 'No seleccionados', count: kpis.no_seleccionados },
                ]" :key="t.key" @click="tab = t.key"
                    class="flex-1 flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-lg transition-all"
                    :class="tab === t.key
                        ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm'
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'">
                    {{ t.label }}
                    <span v-if="t.count > 0"
                        class="inline-flex items-center justify-center min-w-[18px] h-4.5 px-1 rounded-full text-[10px] font-bold"
                        :class="tab === t.key ? 'bg-guinda-100 text-guinda-700 dark:bg-guinda-900/40 dark:text-guinda-400' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400'">
                        {{ t.count }}
                    </span>
                </button>
            </div>

            <!-- TAB: PENDIENTES -->
            <div v-if="tab === 'pendientes'" class="space-y-3">
                <div v-if="!pendientes.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 text-center">
                    <p class="text-gray-400 text-sm">No hay solicitudes pendientes de revisión</p>
                </div>
                <div v-for="s in pendientes" :key="s.user_id"
                    class="bg-white dark:bg-gray-900 border border-amber-200 dark:border-amber-800/40 rounded-2xl p-5">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <p class="font-bold text-gray-900 dark:text-white">{{ s.name }}</p>
                                <span class="text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 font-semibold px-2 py-0.5 rounded-full">Pendiente</span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ s.email }}</p>
                            <p v-if="s.nombre_empresa" class="text-sm text-gray-600 dark:text-gray-300 mt-0.5">{{ s.nombre_empresa }}</p>
                            <!-- Documentos -->
                            <div class="flex items-center gap-3 mt-3 flex-wrap">
                                <a v-if="s.ine_url" :href="s.ine_url" target="_blank"
                                    class="inline-flex items-center gap-1.5 text-xs text-guinda-600 dark:text-guinda-400 font-semibold hover:underline">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Ver INE
                                </a>
                                <span v-else class="text-xs text-red-500 dark:text-red-400 font-medium">Sin INE</span>
                                <a v-if="s.csf_url" :href="s.csf_url" target="_blank"
                                    class="inline-flex items-center gap-1.5 text-xs font-semibold hover:underline"
                                    :class="csfVigente(s.csf_fecha) ? 'text-guinda-600 dark:text-guinda-400' : 'text-amber-600 dark:text-amber-400'">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Ver CSF {{ csfVigente(s.csf_fecha) ? '✓' : '⚠ desactualizada' }}
                                </a>
                                <span v-else class="text-xs text-red-500 dark:text-red-400 font-medium">Sin CSF</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button @click="abrirPerfil(s)"
                                class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg transition-colors">
                                Ver perfil
                            </button>
                            <button @click="aprobarSolicitud(s)" :disabled="procesandoAprobacion === s.user_id"
                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 text-white text-xs font-semibold rounded-lg transition-colors">
                                Aprobar para evaluación
                            </button>
                            <button @click="abrirRechazoPendiente(s)"
                                class="px-3 py-1.5 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-xs font-semibold rounded-lg transition-colors">
                                Rechazar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: EN EVALUACIÓN -->
            <div v-if="tab === 'evaluacion'" class="space-y-3">
                <div v-if="!enEvaluacion.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 text-center">
                    <p class="text-gray-400 text-sm">No hay participantes en evaluación</p>
                </div>
                <div v-for="s in enEvaluacion" :key="s.user_id"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <p class="font-bold text-gray-900 dark:text-white">{{ s.name }}</p>
                                <span :class="s.evaluado ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'"
                                    class="text-xs font-semibold px-2 py-0.5 rounded-full">
                                    {{ s.evaluado ? 'Evaluado' : 'Sin evaluar' }}
                                </span>
                                <span :class="s.seleccionado ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-red-600 dark:bg-red-900/30 dark:text-red-400'"
                                    class="text-xs font-semibold px-2 py-0.5 rounded-full">
                                    {{ s.seleccionado ? 'Seleccionado' : 'Rechazado' }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ s.email }}</p>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-sm font-bold" :class="puntajeColor(s.puntaje_total)">
                                    {{ s.puntaje_total !== null ? s.puntaje_total + ' / 100' : 'Sin puntaje' }}
                                </span>
                                <div v-if="s.puntaje_total !== null" class="w-20 h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full bg-guinda-500" :style="{ width: s.puntaje_total + '%' }"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button @click="abrirPerfil(s)"
                                class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg transition-colors">
                                Ver perfil
                            </button>
                            <button v-if="criterios.length" @click="abrirEvaluar(s)"
                                class="px-3 py-1.5 bg-guinda-100 dark:bg-guinda-900/30 hover:bg-guinda-200 text-guinda-700 dark:text-guinda-400 text-xs font-semibold rounded-lg transition-colors">
                                {{ s.evaluado ? 'Editar evaluación' : 'Evaluar' }}
                            </button>
                            <button @click="marcarSeleccion(s, true)"
                                :disabled="s.correo_aprobacion_enviado || s.correo_rechazo_enviado || procesandoToggle === s.user_id"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors disabled:opacity-40"
                                :class="s.seleccionado
                                    ? 'bg-emerald-600 text-white'
                                    : 'border border-emerald-200 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20'">
                                Seleccionado
                            </button>
                            <button @click="marcarSeleccion(s, false)"
                                :disabled="s.correo_aprobacion_enviado || s.correo_rechazo_enviado || procesandoToggle === s.user_id"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors disabled:opacity-40"
                                :class="!s.seleccionado
                                    ? 'bg-red-600 text-white'
                                    : 'border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20'">
                                Rechazado
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: SELECCIONADOS -->
            <div v-if="tab === 'seleccionados'" class="space-y-3">
                <div v-if="!seleccionados.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 text-center">
                    <p class="text-gray-400 text-sm">No hay participantes seleccionados aún</p>
                </div>
                <div v-for="s in seleccionados" :key="s.user_id"
                    class="bg-white dark:bg-gray-900 border border-emerald-200 dark:border-emerald-800/40 rounded-2xl p-5">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 mb-0.5">
                                <p class="font-bold text-gray-900 dark:text-white">{{ s.name }}</p>
                                <span v-if="s.correo_aprobacion_enviado" class="text-xs bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 font-semibold px-2 py-0.5 rounded-full">Notificado</span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ s.email }}</p>
                            <span class="text-sm font-bold" :class="puntajeColor(s.puntaje_total)">{{ s.puntaje_total ?? '—' }} / 100</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="abrirPerfil(s)"
                                class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg transition-colors">
                                Ver perfil
                            </button>
                            <button @click="toggleSeleccion(s)" :disabled="s.correo_aprobacion_enviado"
                                class="px-3 py-1.5 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 text-xs font-semibold rounded-lg transition-colors disabled:opacity-40">
                                Quitar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: NO SELECCIONADOS -->
            <div v-if="tab === 'no_seleccionados'" class="space-y-3">
                <div v-if="!noSeleccionados.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 text-center">
                    <p class="text-gray-400 text-sm">No hay participantes no seleccionados</p>
                </div>
                <div v-for="s in noSeleccionados" :key="s.user_id"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5"
                    @vue:mounted="inicializarNotas(s)">
                    <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                        <div>
                            <div class="flex items-center gap-2 mb-0.5">
                                <p class="font-bold text-gray-900 dark:text-white">{{ s.name }}</p>
                                <span v-if="s.correo_rechazo_enviado" class="text-xs bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 font-semibold px-2 py-0.5 rounded-full">Notificado</span>
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ s.email }}</p>
                            <span class="text-sm font-bold" :class="puntajeColor(s.puntaje_total)">{{ s.puntaje_total ?? '—' }} / 100</span>
                        </div>
                        <button @click="abrirPerfil(s)"
                            class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-xs font-semibold rounded-lg transition-colors">
                            Ver perfil
                        </button>
                    </div>
                    <!-- Notas de rechazo (por participante) -->
                    <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
                        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1.5">
                            Notas de retroalimentación <span class="font-normal text-gray-400">(visibles para el participante)</span>
                        </label>
                        <textarea v-model="notasForm[s.user_id]" rows="3"
                            placeholder="Explica brevemente por qué no fue seleccionado y qué puede mejorar..."
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-guinda-500 resize-none"></textarea>
                        <div class="flex justify-end mt-2">
                            <button @click="guardarNotas(s)" :disabled="guardandoNotas === s.user_id"
                                class="px-3 py-1.5 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-40 text-white text-xs font-semibold rounded-lg transition-colors">
                                {{ guardandoNotas === s.user_id ? 'Guardando...' : 'Guardar notas' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: motivo rechazo para pendiente -->
        <Teleport to="body">
            <div v-if="rechazandoId" class="fixed inset-0 z-50 flex items-center justify-center px-4" style="background:rgba(0,0,0,0.5)">
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md p-6">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Rechazar solicitud</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Indica el motivo para que el participante lo vea en su notificación.</p>
                    <textarea v-model="motivoRechazoPendiente" rows="3"
                        placeholder="Ej: La documentación presentada está incompleta o no cumple con los requisitos..."
                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-red-400 resize-none mb-4"></textarea>
                    <div class="flex gap-3 justify-end">
                        <button @click="cerrarRechazoPendiente"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Cancelar
                        </button>
                        <button @click="confirmarRechazoPendiente" :disabled="!motivoRechazoPendiente.trim()"
                            class="px-4 py-2 text-sm font-semibold bg-red-600 hover:bg-red-700 disabled:opacity-40 text-white rounded-xl transition-colors">
                            Confirmar rechazo
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Slide-over: ver perfil completo -->
        <Teleport to="body">
            <div v-if="perfilAbierto && perfilViendo" class="fixed inset-0 z-50 flex justify-end" style="background:rgba(0,0,0,0.5)" @click.self="cerrarPerfil">
                <div class="w-full max-w-md h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900">
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">Perfil del participante</h2>
                            <p class="text-xs text-gray-500">{{ perfilViendo.name }}</p>
                        </div>
                        <button @click="cerrarPerfil" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-6 py-5 space-y-4 text-sm">
                        <div class="grid grid-cols-2 gap-4">
                            <div><p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Nombre</p><p class="font-semibold text-gray-900 dark:text-white">{{ perfilViendo.name }}</p></div>
                            <div><p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Empresa</p><p class="font-semibold text-gray-900 dark:text-white">{{ perfilViendo.nombre_empresa || '—' }}</p></div>
                            <div><p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Email</p><p class="text-gray-700 dark:text-gray-300">{{ perfilViendo.email }}</p></div>
                            <div><p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Teléfono</p><p class="text-gray-700 dark:text-gray-300">{{ perfilViendo.telefono || '—' }}</p></div>
                            <div><p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">Municipio</p><p class="text-gray-700 dark:text-gray-300">{{ perfilViendo.municipio || '—' }}</p></div>
                            <div><p class="text-xs text-gray-400 uppercase tracking-wider mb-0.5">RFC</p><p class="text-gray-700 dark:text-gray-300">{{ perfilViendo.rfc || '—' }}</p></div>
                        </div>
                        <div class="border-t border-gray-100 dark:border-gray-800 pt-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-guinda-700 dark:text-guinda-400 mb-3">Documentos</p>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 rounded-xl p-3">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-guinda-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">INE / Identificación</span>
                                    </div>
                                    <a v-if="perfilViendo.ine_url" :href="perfilViendo.ine_url" target="_blank"
                                        class="text-xs text-guinda-600 dark:text-guinda-400 font-semibold hover:underline">Ver →</a>
                                    <span v-else class="text-xs text-red-500 font-medium">Sin documento</span>
                                </div>
                                <div class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 rounded-xl p-3">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-guinda-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        <div>
                                            <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">CSF</span>
                                            <span v-if="perfilViendo.csf_fecha" class="ml-2 text-xs" :class="csfVigente(perfilViendo.csf_fecha) ? 'text-emerald-600' : 'text-amber-600'">
                                                {{ csfVigente(perfilViendo.csf_fecha) ? '✓ Vigente' : '⚠ Desactualizada' }}
                                            </span>
                                        </div>
                                    </div>
                                    <a v-if="perfilViendo.csf_url" :href="perfilViendo.csf_url" target="_blank"
                                        class="text-xs text-guinda-600 dark:text-guinda-400 font-semibold hover:underline">Ver →</a>
                                    <span v-else class="text-xs text-red-500 font-medium">Sin documento</span>
                                </div>
                            </div>
                        </div>
                        <!-- Puntaje y evaluación -->
                        <div v-if="perfilViendo.evaluaciones?.length" class="border-t border-gray-100 dark:border-gray-800 pt-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-guinda-700 dark:text-guinda-400 mb-3">Evaluación por criterios</p>
                            <div class="space-y-2">
                                <div v-for="e in perfilViendo.evaluaciones" :key="e.criterio_id"
                                    class="flex items-center justify-between bg-gray-50 dark:bg-gray-800 rounded-xl px-3 py-2">
                                    <span class="text-xs text-gray-600 dark:text-gray-400">{{ e.nombre }} ({{ e.porcentaje }}%)</span>
                                    <span class="text-sm font-bold" :class="puntajeColor(e.puntaje)">{{ e.puntaje ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="mt-3 p-3 bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-800/30 rounded-xl flex justify-between items-center">
                                <span class="text-xs font-semibold text-guinda-700 dark:text-guinda-400">Puntaje total</span>
                                <span class="text-lg font-black text-guinda-800 dark:text-guinda-300">{{ perfilViendo.puntaje_total ?? '—' }} / 100</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Slide-over: evaluar por criterios -->
        <Teleport to="body">
            <div v-if="evalPanelAbierto && evaluandoParticipante" class="fixed inset-0 z-50 flex justify-end" style="background:rgba(0,0,0,0.5)" @click.self="cerrarEval">
                <div class="w-full max-w-md h-full bg-white dark:bg-gray-900 shadow-2xl overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900">
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">Evaluar participante</h2>
                            <p class="text-xs text-gray-500">{{ evaluandoParticipante.name }}</p>
                        </div>
                        <button @click="cerrarEval" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-6 py-5 space-y-5">
                        <div v-for="c in criterios" :key="c.id" class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ c.nombre }} <span class="text-gray-400 font-normal">({{ c.porcentaje }}%)</span></label>
                                <span class="text-xs text-gray-500">Ponderado: <strong class="text-guinda-700 dark:text-guinda-400">{{ ponderado(c.id, Number(c.porcentaje)) }}</strong> / {{ c.porcentaje }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <input type="range" min="0" max="100" v-model.number="puntajesForm[c.id]" class="flex-1 accent-guinda-700" />
                                <input type="number" min="0" max="100" v-model.number="puntajesForm[c.id]"
                                    class="w-16 text-sm text-center bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-2 py-1.5" />
                            </div>
                        </div>
                        <div class="p-4 bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-800/30 rounded-xl flex items-center justify-between">
                            <span class="text-sm font-semibold text-guinda-700 dark:text-guinda-400">Total acumulado</span>
                            <span class="text-xl font-black text-guinda-800 dark:text-guinda-300">{{ Math.round(totalPonderado * 100) / 100 }} / 100</span>
                        </div>
                        <button @click="guardarEvaluacion" :disabled="guardandoEval"
                            class="w-full px-5 py-2.5 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 text-white text-sm font-bold rounded-xl transition-colors">
                            Guardar evaluación
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
