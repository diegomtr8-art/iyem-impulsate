<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';

const props = defineProps({
    restaurantero: Object,
    citasHoy: Number,
    citasSemana: Number,
    citasPendientes: Number,
    todasLasCitas: Array,
    categorias: Array,
});

const page = usePage();

const estadoConfig = {
    pendiente:               { label: 'Pendiente',           class: 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/20' },
    confirmada:              { label: 'Confirmada',           class: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/20' },
    cancelada:               { label: 'Cancelada',            class: 'bg-gray-500/15 text-gray-500 dark:text-gray-400 border-gray-500/20' },
    completada:              { label: 'Completada',           class: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/20' },
    rechazada:               { label: 'Rechazada',            class: 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/20' },
    reagendada:              { label: 'Reagendada',           class: 'bg-guinda-500/15 text-guinda-600 dark:text-guinda-400 border-guinda-500/20' },
    pendiente_reconfirmacion:{ label: 'Esp. reconfirmación', class: 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/20' },
};

const calendarOptions = ref({
    plugins: [timeGridPlugin, dayGridPlugin],
    initialView: 'timeGridWeek',
    locale: 'es',
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'timeGridWeek,dayGridMonth' },
    slotMinTime: '09:00:00', slotMaxTime: '16:00:00',
    hiddenDays: [0, 6], slotDuration: '00:30:00', height: 380,
    events: '/restaurantero/panel/eventos', eventColor: '#8b1028',
    nowIndicator: true,
    businessHours: { daysOfWeek: [1,2,3,4,5], startTime: '09:00', endTime: '16:00' },
});

// ── Acciones sobre citas ──────────────────────────────────────
const procesando = ref(false);
const modalReagendar = ref(false);
const citaSeleccionada = ref(null);
const formReagendar = ref({ propuesta_fecha: '', propuesta_hora: '' });

const abrirReagendar = (cita) => {
    citaSeleccionada.value = cita;
    formReagendar.value = { propuesta_fecha: '', propuesta_hora: '' };
    modalReagendar.value = true;
};
const cerrarModal = () => { modalReagendar.value = false; citaSeleccionada.value = null; };

const accion = (type, cita) => {
    if (procesando.value) return;
    const msg = { aceptar: '¿Confirmar esta cita?', rechazar: '¿Rechazar esta cita? Se notificará al comprador.' };
    if (!confirm(msg[type])) return;
    procesando.value = true;
    router.patch(route(`restaurantero.citas.${type}`, cita.id), {}, { onFinish: () => { procesando.value = false; } });
};

const submitReagendar = () => {
    if (!citaSeleccionada.value) return;
    procesando.value = true;
    router.post(route('restaurantero.citas.reagendar', citaSeleccionada.value.id), formReagendar.value, {
        onSuccess: cerrarModal, onFinish: () => { procesando.value = false; },
    });
};

// ── Edición de perfil ─────────────────────────────────────────
const showPerfilModal = ref(false);
const MAX_PROD = 5;
const productosInputs = ref(
    Array.from({ length: MAX_PROD }, (_, i) => {
        const p = props.restaurantero.productos_top?.[i];
        if (!p) return { nombre: '', descripcion: '', foto_path: null, _file: null };
        if (typeof p === 'string') return { nombre: p, descripcion: '', foto_path: null, _file: null };
        return { nombre: p.nombre || '', descripcion: p.descripcion || '', foto_path: p.foto_path || null, _file: null };
    })
);

const formPerfil = useForm({
    nombre_restaurante: props.restaurantero.nombre_restaurante || '',
    descripcion:        props.restaurantero.descripcion || '',
    telefono:           props.restaurantero.telefono || '',
    municipio:          props.restaurantero.municipio || '',
    direccion:          props.restaurantero.direccion || '',
    sitio_web:          props.restaurantero.sitio_web || '',
    rfc:                props.restaurantero.rfc || '',
    productos_top:      [],
    foto:               null,
});

const guardandoPerfil = ref(false);

const submitPerfil = () => {
    guardandoPerfil.value = true;
    const fd = new FormData();
    fd.append('nombre_restaurante', formPerfil.nombre_restaurante);
    fd.append('descripcion',        formPerfil.descripcion || '');
    fd.append('telefono',           formPerfil.telefono || '');
    fd.append('municipio',          formPerfil.municipio || '');
    fd.append('direccion',          formPerfil.direccion || '');
    fd.append('sitio_web',          formPerfil.sitio_web || '');
    fd.append('rfc',                formPerfil.rfc || '');
    if (formPerfil.foto) fd.append('foto', formPerfil.foto);

    let idx = 0;
    productosInputs.value.forEach(p => {
        if (!p.nombre.trim()) return;
        fd.append(`productos[${idx}][nombre]`,      p.nombre);
        fd.append(`productos[${idx}][descripcion]`, p.descripcion || '');
        if (p._file) fd.append(`producto_foto_${idx}`, p._file);
        idx++;
    });

    router.post(route('restaurantero.completar-perfil.store'), fd, {
        forceFormData: true,
        onSuccess: () => { showPerfilModal.value = false; guardandoPerfil.value = false; },
        onError: () => { guardandoPerfil.value = false; },
    });
};

// ── Helpers ───────────────────────────────────────────────────
const fmt = (dt) => dt ? new Date(dt).toLocaleString('es-MX', {
    weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
}) : '—';
const iniciales = (n) => n?.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase() || '?';

const citasPendientes = computed(() => props.todasLasCitas.filter(c => c.estado === 'pendiente'));
const citasActivas    = computed(() => props.todasLasCitas.filter(c => ['confirmada', 'reagendada', 'pendiente_reconfirmacion'].includes(c.estado)));
const citasHistorial  = computed(() => props.todasLasCitas.filter(c => ['completada', 'cancelada', 'rechazada'].includes(c.estado)));

const kpis = computed(() => [
    { label: 'Citas hoy',   value: props.citasHoy,       bg: 'bg-sky-50 dark:bg-sky-500/10 border-sky-200 dark:border-sky-500/20',         iconColor: 'text-sky-600 dark:text-sky-400',     valColor: 'text-sky-700 dark:text-sky-300',     icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Esta semana', value: props.citasSemana,     bg: 'bg-guinda-50 dark:bg-guinda-500/10 border-guinda-200 dark:border-guinda-500/20', iconColor: 'text-guinda-700 dark:text-guinda-400', valColor: 'text-guinda-700 dark:text-guinda-300', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Pendientes',  value: props.citasPendientes, bg: 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/20',   iconColor: 'text-amber-600 dark:text-amber-400', valColor: 'text-amber-700 dark:text-amber-300', icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' },
]);

const municipios = [
    'Mérida','Valladolid','Tizimín','Progreso','Motul','Ticul','Umán','Izamal',
    'Maxcanú','Hunucmá','Tekax','Chemax','Espita','Oxkutzcab','Peto','Temozón',
    'Kanasín','Telchac Puerto','Celestún','Ixil','Santa Elena','Muna','Tekax',
];
</script>

<template>
    <AppLayout title="Mi Negocio">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Mi Negocio</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">{{ restaurantero.nombre_restaurante }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <button @click="showPerfilModal = true"
                        class="px-4 py-2 text-sm font-semibold bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl transition-colors">
                        Editar perfil
                    </button>
                    <Link :href="route('restaurantero.citas.index')"
                        class="text-sm text-guinda-700 dark:text-guinda-400 hover:underline font-medium">
                        Historial completo →
                    </Link>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 text-xs font-bold rounded-full border border-guinda-200 dark:border-guinda-800">
                        <span class="w-2 h-2 rounded-full bg-guinda-600 dark:bg-guinda-500 animate-pulse"></span>
                        Proveedor
                    </span>
                </div>
            </div>
        </template>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            <!-- ── BANNER MODO PROVEEDOR ─────────────────────────── -->
            <div class="flex items-center gap-3 px-4 py-3 bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-900/40 rounded-2xl">
                <div class="w-8 h-8 rounded-full bg-guinda-700 dark:bg-guinda-800 flex items-center justify-center text-white font-bold text-sm shrink-0">
                    {{ $page.props.auth.user?.name?.charAt(0).toUpperCase() }}
                </div>
                <div class="flex-1">
                    <p class="font-bold text-guinda-800 dark:text-guinda-300 text-sm">
                        Bienvenido, {{ $page.props.auth.user?.name?.split(' ')[0] }}
                    </p>
                    <p class="text-xs text-guinda-600 dark:text-guinda-500">
                        Estás en modo <strong>Proveedor</strong> — aquí gestiones tu negocio y las citas de tus compradores
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    <span v-if="restaurantero.aprobado"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-xs font-bold rounded-full border border-emerald-200 dark:border-emerald-800">
                        ✓ Perfil aprobado
                    </span>
                    <span v-else-if="restaurantero.rechazado"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 text-xs font-bold rounded-full border border-red-200 dark:border-red-800">
                        ✕ Rechazado — corrige tu perfil
                    </span>
                    <span v-else-if="restaurantero.solicitado_aprobacion_at"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 text-xs font-bold rounded-full border border-amber-200 dark:border-amber-800 animate-pulse">
                        ⏳ En revisión
                    </span>
                    <span v-else
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-bold rounded-full border border-gray-200 dark:border-gray-700">
                        Perfil incompleto
                    </span>
                </div>
            </div>

            <!-- ── CARD REGISTRO AL EVENTO (PROVEEDOR) ─────────────── -->
            <div v-if="$page.props.eventoActivo">
                <div v-if="!$page.props.registradoEnEvento?.proveedor"
                    class="bg-gradient-to-r from-guinda-900/60 to-guinda-800/40 border border-guinda-700/50 rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="text-3xl">📅</div>
                    <div class="flex-1">
                        <h3 class="font-bold text-white mb-1">Hay un evento disponible: {{ $page.props.eventoActivo.nombre }}</h3>
                        <p class="text-gray-300 text-sm">Regístrate como proveedor en el evento para que los compradores puedan agendarte citas.</p>
                    </div>
                    <button
                        @click="router.post(route('evento.registrar-proveedor', $page.props.eventoActivo.id))"
                        class="shrink-0 px-5 py-2.5 bg-guinda-500 hover:bg-guinda-400 text-white font-bold text-sm rounded-xl transition-colors shadow-sm">
                        Registrarme como Proveedor
                    </button>
                </div>
                <div v-else class="flex items-center gap-3 px-4 py-3 bg-guinda-50 dark:bg-guinda-900/20 border border-guinda-200 dark:border-guinda-800 rounded-xl text-sm">
                    <svg class="w-4 h-4 text-guinda-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    <span class="text-guinda-700 dark:text-guinda-400 font-medium">Registrado en {{ $page.props.eventoActivo.nombre }}</span>
                </div>
            </div>

            <!-- Alerta si fue rechazado -->
            <div v-if="restaurantero.rechazado"
                class="bg-red-50 dark:bg-red-950/20 border border-red-200 dark:border-red-800/40 rounded-2xl px-5 py-4">
                <div class="flex items-start gap-4">
                    <svg class="w-8 h-8 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                    <div class="flex-1">
                        <p class="font-bold text-red-800 dark:text-red-300 text-sm">Tu perfil fue rechazado</p>
                        <div v-if="restaurantero.motivo_rechazo"
                            class="mt-2 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800/30 rounded-xl px-3 py-2.5">
                            <p class="text-xs font-semibold text-red-600 dark:text-red-400 uppercase tracking-wider mb-1">Motivo:</p>
                            <p class="text-sm text-red-700 dark:text-red-300">{{ restaurantero.motivo_rechazo }}</p>
                        </div>
                        <p class="text-xs text-red-600 dark:text-red-500 mt-2">Corrige la información indicada y vuelve a enviar tu perfil para revisión.</p>
                    </div>
                    <button @click="showPerfilModal = true"
                        class="shrink-0 px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-bold rounded-xl">
                        Corregir perfil
                    </button>
                </div>
            </div>

            <!-- Alerta si perfil incompleto -->
            <div v-if="!restaurantero.solicitado_aprobacion_at"
                class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 rounded-2xl px-5 py-4 flex items-center gap-4">
                <svg class="w-8 h-8 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <div class="flex-1">
                    <p class="font-bold text-amber-800 dark:text-amber-300 text-sm">Completa tu perfil para ser visible</p>
                    <p class="text-xs text-amber-600 dark:text-amber-500 mt-0.5">Agrega tu descripción, fotos y productos para que el admin pueda aprobarte y aparecer en el directorio.</p>
                </div>
                <button @click="showPerfilModal = true"
                    class="shrink-0 px-4 py-2 bg-amber-500 hover:bg-amber-400 text-white text-sm font-bold rounded-xl transition-colors">
                    Completar ahora
                </button>
            </div>

            <!-- ── KPI CARDS ──────────────────────────────────────── -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div v-for="kpi in kpis" :key="kpi.label"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-sm">
                    <div :class="['w-11 h-11 rounded-xl border flex items-center justify-center shrink-0', kpi.bg]">
                        <svg :class="['w-5 h-5', kpi.iconColor]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="kpi.icon" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500 font-medium">{{ kpi.label }}</p>
                        <p :class="['text-3xl font-black', kpi.valColor]">{{ kpi.value ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- ── PRODUCTOS TOP ─────────────────────────────────── -->
            <div v-if="restaurantero.productos_top && restaurantero.productos_top.length > 0"
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-900 dark:text-white">Mis productos / servicios</h3>
                    <button @click="showPerfilModal = true" class="text-xs text-guinda-700 dark:text-guinda-400 hover:underline">Editar →</button>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <div v-for="(prod, i) in restaurantero.productos_top" :key="i"
                        class="border border-gray-100 dark:border-gray-800 rounded-xl overflow-hidden">
                        <div v-if="prod.foto_path" class="w-full h-24 overflow-hidden bg-gray-100 dark:bg-gray-800">
                            <img :src="`/storage/${prod.foto_path}`" class="w-full h-full object-cover" />
                        </div>
                        <div v-else class="w-full h-16 bg-guinda-50 dark:bg-guinda-950/20 flex items-center justify-center">
                            <svg class="w-5 h-5 text-guinda-300 dark:text-guinda-800" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159"/>
                            </svg>
                        </div>
                        <div class="p-2">
                            <p class="font-semibold text-xs text-gray-900 dark:text-white truncate">
                                {{ typeof prod === 'string' ? prod : (prod.nombre || '—') }}
                            </p>
                            <p v-if="prod.descripcion" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ prod.descripcion }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── CITAS PENDIENTES ──────────────────────────────── -->
            <div v-if="citasPendientes.length > 0"
                class="bg-white dark:bg-gray-900 border border-amber-200 dark:border-amber-800/40 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-5 py-3 bg-amber-50 dark:bg-amber-950/20 border-b border-amber-100 dark:border-amber-800/40 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
                        <h3 class="font-bold text-amber-800 dark:text-amber-300 text-sm">Solicitudes pendientes — requieren tu respuesta</h3>
                        <span class="bg-amber-200 dark:bg-amber-800/40 text-amber-800 dark:text-amber-300 text-xs font-bold px-2 py-0.5 rounded-full">{{ citasPendientes.length }}</span>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-800/40">
                    <div v-for="cita in citasPendientes" :key="cita.id"
                        class="px-5 py-4 flex flex-col sm:flex-row sm:items-center gap-3 hover:bg-amber-50/50 dark:hover:bg-amber-950/10 transition-colors">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-guinda-100 dark:bg-guinda-900/30 border border-guinda-200 dark:border-guinda-800 flex items-center justify-center text-guinda-700 dark:text-guinda-400 font-bold text-sm shrink-0">
                                {{ iniciales(cita.cliente.name) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900 dark:text-white truncate">{{ cita.cliente.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ cita.cliente.email }}</p>
                                <p v-if="cita.cliente.telefono && cita.cliente.telefono !== 'N/A'" class="text-xs text-gray-400 dark:text-gray-500">{{ cita.cliente.telefono }}</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 font-medium shrink-0">{{ fmt(cita.inicio) }}</p>
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="accion('aceptar', cita)" :disabled="procesando"
                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl">✓ Aceptar</button>
                            <button @click="abrirReagendar(cita)" :disabled="procesando"
                                class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-white text-xs font-bold rounded-xl">Reagendar</button>
                            <button @click="accion('rechazar', cita)" :disabled="procesando"
                                class="px-3 py-1.5 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl">Rechazar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── TODAS LAS CITAS ────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/30">
                    <h3 class="font-bold text-gray-900 dark:text-white">Todas las citas con compradores</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Personas que han agendado contigo</p>
                </div>

                <div v-if="todasLasCitas.length === 0" class="text-center py-16 text-gray-400 dark:text-gray-600">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                    </svg>
                    <p class="font-medium">No tienes citas aún</p>
                </div>

                <div v-else class="divide-y divide-gray-100 dark:divide-gray-800/40">
                    <div v-for="cita in todasLasCitas" :key="cita.id"
                        class="px-5 py-3.5 flex flex-col sm:flex-row sm:items-center gap-3 hover:bg-gray-50 dark:hover:bg-gray-800/20 transition-colors"
                        :class="{ 'opacity-60': ['cancelada','rechazada'].includes(cita.estado) }">
                        <!-- Comprador -->
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-400 font-bold text-xs shrink-0">
                                {{ iniciales(cita.cliente.name) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-gray-200 truncate text-sm">{{ cita.cliente.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ cita.cliente.email }}</p>
                            </div>
                        </div>
                        <!-- Fecha -->
                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium shrink-0">{{ fmt(cita.inicio) }}</p>
                        <!-- Estado -->
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border shrink-0"
                            :class="estadoConfig[cita.estado]?.class || 'bg-gray-500/15 text-gray-400'">
                            {{ estadoConfig[cita.estado]?.label || cita.estado }}
                        </span>
                        <!-- Acciones -->
                        <div class="flex items-center gap-2 shrink-0">
                            <template v-if="cita.estado === 'pendiente'">
                                <button @click="accion('aceptar', cita)" :disabled="procesando"
                                    class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold rounded-lg">✓</button>
                                <button @click="abrirReagendar(cita)" :disabled="procesando"
                                    class="px-2.5 py-1 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-white text-xs font-bold rounded-lg">↻</button>
                                <button @click="accion('rechazar', cita)" :disabled="procesando"
                                    class="px-2.5 py-1 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white text-xs font-bold rounded-lg">✕</button>
                            </template>
                            <template v-else-if="['confirmada', 'reagendada'].includes(cita.estado)">
                                <button @click="abrirReagendar(cita)" :disabled="procesando"
                                    class="px-2.5 py-1 bg-gray-200 dark:bg-gray-700 hover:bg-amber-500 hover:text-white text-gray-600 dark:text-gray-300 disabled:opacity-50 text-xs font-bold rounded-lg transition-colors">↻ Reagendar</button>
                            </template>
                            <span v-else class="text-xs text-gray-300 dark:text-gray-600 italic">—</span>
                        </div>
                    </div>
                </div>

                <div v-if="todasLasCitas.length >= 50" class="px-5 py-3 border-t border-gray-200 dark:border-gray-800 text-center">
                    <Link :href="route('restaurantero.citas.index')"
                        class="text-sm text-guinda-700 dark:text-guinda-400 hover:underline font-medium">
                        Ver historial completo →
                    </Link>
                </div>
            </div>

            <!-- ── CALENDARIO ─────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                <h3 class="font-bold text-gray-900 dark:text-white mb-4">Calendario</h3>
                <FullCalendar :options="calendarOptions" />
            </div>

        </div>

        <!-- Modal Reagendar -->
        <Teleport to="body">
            <div v-if="modalReagendar" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background:rgba(0,0,0,0.5)" @click.self="cerrarModal">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl w-full max-w-md shadow-2xl">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                        <h3 class="font-bold text-gray-900 dark:text-white">Proponer nueva fecha</h3>
                        <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div v-if="citaSeleccionada" class="bg-gray-50 dark:bg-gray-800 rounded-xl p-3 text-sm">
                            <p class="text-xs text-gray-500 mb-1">Cita original</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ citaSeleccionada.cliente?.name }}</p>
                            <p class="text-xs text-gray-500">{{ fmt(citaSeleccionada.inicio) }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nueva fecha</label>
                            <input v-model="formReagendar.propuesta_fecha" type="date"
                                :min="new Date().toISOString().split('T')[0]"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nueva hora</label>
                            <input v-model="formReagendar.propuesta_hora" type="time"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500" />
                        </div>
                    </div>
                    <div class="px-6 pb-5 flex gap-3">
                        <button @click="cerrarModal" class="flex-1 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium">Cancelar</button>
                        <button @click="submitReagendar" :disabled="procesando || !formReagendar.propuesta_fecha || !formReagendar.propuesta_hora"
                            class="flex-1 py-2.5 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 text-white rounded-xl text-sm font-bold">
                            {{ procesando ? 'Enviando...' : 'Enviar propuesta' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Modal Editar Perfil -->
        <Teleport to="body">
            <div v-if="showPerfilModal" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background:rgba(0,0,0,0.6)" @click.self="showPerfilModal = false">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Editar perfil de negocio</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Al guardar, tu perfil se enviará a revisión del administrador</p>
                        </div>
                        <button @click="showPerfilModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-white p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitPerfil" enctype="multipart/form-data" class="px-6 py-5 space-y-5">
                        <!-- Info básica -->
                        <div>
                            <p class="text-xs font-bold text-gray-500 dark:text-gray-500 uppercase tracking-wider mb-3">Información del negocio</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del negocio *</label>
                                    <input v-model="formPerfil.nombre_restaurante" type="text"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                    <p v-if="formPerfil.errors.nombre_restaurante" class="text-xs text-red-500 mt-1">{{ formPerfil.errors.nombre_restaurante }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción del negocio</label>
                                    <textarea v-model="formPerfil.descripcion" rows="3"
                                        placeholder="¿Qué ofreces? ¿Cuál es tu propuesta de valor?..."
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 resize-none" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
                                    <input v-model="formPerfil.telefono" type="tel" maxlength="10" placeholder="9991234567"
                                        @input="formPerfil.telefono = formPerfil.telefono.replace(/\D/g, '').slice(0, 10)"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Municipio</label>
                                    <select v-model="formPerfil.municipio"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500">
                                        <option value="">— Seleccionar —</option>
                                        <option v-for="m in municipios" :key="m" :value="m">{{ m }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
                                    <input v-model="formPerfil.direccion" type="text"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sitio web</label>
                                    <input v-model="formPerfil.sitio_web" type="url" placeholder="https://..."
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                            </div>
                        </div>

                        <!-- Foto / Logo -->
                        <div>
                            <p class="text-xs font-bold text-gray-500 dark:text-gray-500 uppercase tracking-wider mb-3">Imagen del negocio</p>
                            <div class="flex items-start gap-4">
                                <div v-if="restaurantero.logo_path"
                                    class="w-20 h-20 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shrink-0">
                                    <img :src="`/storage/${restaurantero.logo_path}`" class="w-full h-full object-cover" />
                                </div>
                                <div v-else class="w-20 h-20 rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center shrink-0">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Foto del negocio</label>
                                    <input type="file" accept="image/*" @change="e => formPerfil.foto = e.target.files[0]"
                                        class="text-sm text-gray-600 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-guinda-100 dark:file:bg-guinda-900/30 file:text-guinda-700 dark:file:text-guinda-400 hover:file:bg-guinda-200 dark:hover:file:bg-guinda-900/50 cursor-pointer" />
                                    <p class="text-xs text-gray-400 mt-1">JPG, PNG o WEBP · Máx. 4MB</p>
                                </div>
                            </div>
                        </div>

                        <!-- Productos/servicios top -->
                        <div>
                            <p class="text-xs font-bold text-gray-500 dark:text-gray-500 uppercase tracking-wider mb-3">Productos o servicios destacados (máx. 5)</p>
                            <div class="space-y-4">
                                <div v-for="(prod, i) in productosInputs" :key="i"
                                    class="bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-2.5">
                                    <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Producto {{ i + 1 }}</p>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Nombre del producto</label>
                                        <input v-model="prod.nombre" type="text" :placeholder="`Ej. Vasos de vidrio, Tecnología, Catering...`"
                                            class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Descripción</label>
                                        <textarea v-model="prod.descripcion" rows="2" placeholder="Breve descripción del producto o servicio..."
                                            class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 resize-none" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Fotografía del producto</label>
                                        <div class="flex items-center gap-3">
                                            <div v-if="prod.foto_path" class="w-12 h-12 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 shrink-0">
                                                <img :src="`/storage/${prod.foto_path}`" class="w-full h-full object-cover" />
                                            </div>
                                            <input type="file" accept="image/*"
                                                @change="e => { prod._file = e.target.files[0] }"
                                                class="text-xs text-gray-500 file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-guinda-100 dark:file:bg-guinda-900/30 file:text-guinda-700 dark:file:text-guinda-400" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="formPerfil.errors.nombre_restaurante || formPerfil.errors.foto"
                            class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl px-4 py-3 text-sm text-red-600 dark:text-red-400">
                            {{ formPerfil.errors.nombre_restaurante || formPerfil.errors.foto }}
                        </div>

                        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <button type="button" @click="showPerfilModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl">
                                Cancelar
                            </button>
                            <button type="button" @click="submitPerfil" :disabled="guardandoPerfil"
                                class="px-5 py-2 text-sm font-bold text-white bg-guinda-800 hover:bg-guinda-700 disabled:opacity-60 rounded-xl">
                                {{ guardandoPerfil ? 'Guardando...' : 'Guardar y enviar a revisión' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Banner: invitación a ser comprador -->
        <div v-if="!$page.props.auth.user?.is_cliente"
            class="mt-6 bg-gradient-to-r from-guinda-900/80 to-guinda-800/60 border border-guinda-700/50 rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div class="text-3xl">🛒</div>
            <div class="flex-1">
                <h3 class="font-bold text-white mb-1">¿También buscas proveedores?</h3>
                <p class="text-gray-300 text-sm">Activa tu acceso como comprador y agenda citas en el evento con otros proveedores del mismo sector.</p>
            </div>
            <button
                @click="router.post(route('perfil.agregar-rol'), { rol: 'comprador' })"
                class="shrink-0 px-5 py-2.5 bg-white text-guinda-800 font-bold text-sm rounded-xl hover:bg-guinda-50 transition-colors shadow-sm">
                Quiero ser Comprador
            </button>
        </div>

    </AppLayout>
</template>

<style>
.fc { --fc-border-color: #e5e7eb; --fc-today-bg-color: rgba(139,16,40,0.04); }
.dark .fc { --fc-border-color: #1f2937; --fc-today-bg-color: rgba(139,16,40,0.08); }
.fc-theme-standard td, .fc-theme-standard th { border-color: #e5e7eb; }
.dark .fc-theme-standard td, .dark .fc-theme-standard th { border-color: #1f2937; }
.fc-col-header-cell-cushion, .fc-timegrid-axis-cushion, .fc-timegrid-slot-label-cushion { color: #6b7280; }
.fc-toolbar-title { font-size: 0.95rem !important; font-weight: 700 !important; color: #111827 !important; }
.dark .fc-toolbar-title { color: #f9fafb !important; }
.fc-button { background: #f3f4f6 !important; border-color: #d1d5db !important; color: #374151 !important; font-size: 0.7rem !important; }
.dark .fc-button { background: #374151 !important; border-color: #4b5563 !important; color: #d1d5db !important; }
.fc-button-active, .fc-button:hover { background: #8b1028 !important; border-color: #710d21 !important; color: #fff !important; }
.fc-event-title, .fc-event-time { color: #fff !important; }
.fc-scrollgrid { border-color: #e5e7eb !important; }
.dark .fc-scrollgrid { border-color: #1f2937 !important; }
</style>
