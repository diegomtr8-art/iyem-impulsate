<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import TabEventos from '@/Components/TabEventos.vue';
import EventosSidebar from '@/Components/EventosSidebar.vue';
import { ref, computed, onMounted } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';
import esLocale from '@fullcalendar/core/locales/es';
import { MUNICIPIOS_YUCATAN } from '@/constants/municipiosYucatan';

const props = defineProps({
    restaurantero: Object,
    citasHoy: Number,
    citasSemana: Number,
    citasPendientes: Number,
    todasLasCitas: Array,
    categorias: Array,
    tasaAceptacion: Number,
    totalEnEvento: Number,
    citaProxima2h: Object,
    notificaciones: Array,
    eventos: { type: Array, default: () => [] },
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
    initialView: 'dayGridMonth',
    locale: esLocale,
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
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
        if (!p) return { nombre: '', descripcion: '', foto_path: null, _file: null, capacidad_cantidad: '', capacidad_unidad: 'piezas' };
        if (typeof p === 'string') return { nombre: p, descripcion: '', foto_path: null, _file: null, capacidad_cantidad: '', capacidad_unidad: 'piezas' };
        return { nombre: p.nombre || '', descripcion: p.descripcion || '', foto_path: p.foto_path || null, _file: null, capacidad_cantidad: p.capacidad_cantidad ?? '', capacidad_unidad: p.capacidad_unidad || 'piezas' };
    })
);

const formPerfil = useForm({
    nombre_restaurante:      props.restaurantero.nombre_restaurante || '',
    razon_social:            props.restaurantero.razon_social || '',
    nombre_representante:    props.restaurantero.nombre_representante || '',
    curp_representante:      props.restaurantero.curp_representante || '',
    fecha_inicio_operaciones:props.restaurantero.fecha_inicio_operaciones || '',
    num_empleados:           props.restaurantero.num_empleados ?? '',
    domicilio_en_yucatan:    props.restaurantero.domicilio_en_yucatan ?? null,
    descripcion:             props.restaurantero.descripcion || '',
    mercado_meta:            props.restaurantero.mercado_meta || '',
    tiempo_vida_anaquel:     props.restaurantero.tiempo_vida_anaquel || '',
    requisitos_alimentos:    props.restaurantero.requisitos_alimentos || [],
    apoyo_requisitos:        props.restaurantero.apoyo_requisitos || [],
    requiere_refrigeracion:  props.restaurantero.requiere_refrigeracion ?? null,
    requiere_congelacion:    props.restaurantero.requiere_congelacion ?? null,
    telefono:                props.restaurantero.telefono || '',
    municipio:               props.restaurantero.municipio || '',
    direccion:               props.restaurantero.direccion || '',
    sitio_web:               props.restaurantero.sitio_web || '',
    rfc:                     props.restaurantero.rfc || '',
    acepta_credito:          props.restaurantero.acepta_credito ?? false,
    credito_monto_maximo:    props.restaurantero.credito_monto_maximo ?? '',
    credito_tiempo_cantidad: props.restaurantero.credito_tiempo_cantidad ?? '',
    credito_tiempo_unidad:   props.restaurantero.credito_tiempo_unidad || 'dias',
    credito_a_negociar:      props.restaurantero.credito_a_negociar ?? false,
    pago_contraentrega:      props.restaurantero.pago_contraentrega ?? false,
    factura:                 props.restaurantero.factura ?? false,
    regimen_fiscal:          props.restaurantero.regimen_fiscal || '',
    entrega_domicilio:       props.restaurantero.entrega_domicilio ?? null,
    cobertura_entrega:       props.restaurantero.cobertura_entrega || '',
    forma_entrega:           props.restaurantero.forma_entrega || '',
    productos_top:           [],
    foto:                    null,
});

const guardandoPerfil = ref(false);

const submitPerfil = () => {
    guardandoPerfil.value = true;
    const fd = new FormData();
    fd.append('nombre_restaurante',       formPerfil.nombre_restaurante);
    fd.append('razon_social',             formPerfil.razon_social || '');
    fd.append('nombre_representante',     formPerfil.nombre_representante || '');
    fd.append('curp_representante',       formPerfil.curp_representante || '');
    fd.append('fecha_inicio_operaciones', formPerfil.fecha_inicio_operaciones || '');
    fd.append('num_empleados',            formPerfil.num_empleados ?? '');
    if (formPerfil.domicilio_en_yucatan !== null) fd.append('domicilio_en_yucatan', formPerfil.domicilio_en_yucatan ? '1' : '0');
    fd.append('descripcion',              formPerfil.descripcion || '');
    fd.append('mercado_meta',             formPerfil.mercado_meta || '');
    fd.append('tiempo_vida_anaquel',      formPerfil.tiempo_vida_anaquel || '');
    (formPerfil.requisitos_alimentos || []).forEach(v => fd.append('requisitos_alimentos[]', v));
    (formPerfil.apoyo_requisitos || []).forEach(v => fd.append('apoyo_requisitos[]', v));
    if (formPerfil.requiere_refrigeracion !== null) fd.append('requiere_refrigeracion', formPerfil.requiere_refrigeracion ? '1' : '0');
    if (formPerfil.requiere_congelacion !== null) fd.append('requiere_congelacion', formPerfil.requiere_congelacion ? '1' : '0');
    fd.append('telefono',                 formPerfil.telefono || '');
    fd.append('municipio',                formPerfil.municipio || '');
    fd.append('direccion',                formPerfil.direccion || '');
    fd.append('sitio_web',                formPerfil.sitio_web || '');
    fd.append('rfc',                      formPerfil.rfc || '');
    fd.append('acepta_credito',     formPerfil.acepta_credito ? '1' : '0');
    fd.append('credito_a_negociar', formPerfil.credito_a_negociar ? '1' : '0');
    fd.append('pago_contraentrega', formPerfil.pago_contraentrega ? '1' : '0');
    fd.append('factura',            formPerfil.factura ? '1' : '0');
    if (formPerfil.acepta_credito) {
        if (formPerfil.credito_monto_maximo !== '') fd.append('credito_monto_maximo', formPerfil.credito_monto_maximo);
        if (formPerfil.credito_tiempo_cantidad !== '') fd.append('credito_tiempo_cantidad', formPerfil.credito_tiempo_cantidad);
        fd.append('credito_tiempo_unidad', formPerfil.credito_tiempo_unidad || 'dias');
    }
    if (formPerfil.factura && formPerfil.regimen_fiscal) fd.append('regimen_fiscal', formPerfil.regimen_fiscal);
    fd.append('entrega_domicilio', formPerfil.entrega_domicilio === true ? '1' : '0');
    if (formPerfil.entrega_domicilio) {
        if (formPerfil.cobertura_entrega) fd.append('cobertura_entrega', formPerfil.cobertura_entrega);
        if (formPerfil.forma_entrega)     fd.append('forma_entrega', formPerfil.forma_entrega);
    }
    if (formPerfil.foto) fd.append('foto', formPerfil.foto);

    let idx = 0;
    productosInputs.value.forEach(p => {
        if (!p.nombre.trim()) return;
        fd.append(`productos[${idx}][nombre]`,      p.nombre);
        fd.append(`productos[${idx}][descripcion]`, p.descripcion || '');
        fd.append(`productos[${idx}][capacidad_cantidad]`, p.capacidad_cantidad ?? '');
        fd.append(`productos[${idx}][capacidad_unidad]`,   p.capacidad_unidad   || 'piezas');
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
    { label: 'Citas hoy',    value: props.citasHoy,       bg: 'bg-sky-50 dark:bg-sky-500/10 border-sky-200 dark:border-sky-500/20',           iconColor: 'text-sky-600 dark:text-sky-400',      valColor: 'text-sky-700 dark:text-sky-300',     icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Esta semana',  value: props.citasSemana,     bg: 'bg-guinda-50 dark:bg-guinda-500/10 border-guinda-200 dark:border-guinda-500/20', iconColor: 'text-guinda-700 dark:text-guinda-400', valColor: 'text-guinda-700 dark:text-guinda-300', icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Pendientes',   value: props.citasPendientes, bg: 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/20',     iconColor: 'text-amber-600 dark:text-amber-400',  valColor: 'text-amber-700 dark:text-amber-300', icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z' },
    { label: '% Aceptadas',  value: props.tasaAceptacion !== null && props.tasaAceptacion !== undefined ? props.tasaAceptacion + '%' : '—', bg: 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-200 dark:border-emerald-500/20', iconColor: 'text-emerald-600 dark:text-emerald-400', valColor: 'text-emerald-700 dark:text-emerald-300', icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Total evento', value: props.totalEnEvento,   bg: 'bg-indigo-50 dark:bg-indigo-500/10 border-indigo-200 dark:border-indigo-500/20', iconColor: 'text-indigo-600 dark:text-indigo-400', valColor: 'text-indigo-700 dark:text-indigo-300', icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
]);

const municipios = MUNICIPIOS_YUCATAN;

// ── PERFIL INCOMPLETO ─────────────────────────────────────────
const perfilPorcentaje = computed(() => {
    const r = props.restaurantero;
    if (!r) return 0;
    let pct = 0;
    if (r.nombre_restaurante)        pct += 10;
    if (r.descripcion)               pct += 10;
    if (r.telefono)                  pct += 8;
    if (r.municipio)                 pct += 7;
    if (r.logo_path)                 pct += 15;
    if (r.productos_top?.length > 0) pct += 15;
    if (r.acepta_credito !== null && r.acepta_credito !== undefined) pct += 5;
    if (r.pago_contraentrega !== null && r.pago_contraentrega !== undefined) pct += 5;
    if (r.factura !== null && r.factura !== undefined) pct += 5;
    if (!r.factura || (r.rfc && r.regimen_fiscal)) pct += 10;
    if (r.entrega_domicilio !== null && r.entrega_domicilio !== undefined) pct += 10;
    return Math.min(pct, 100);
});

const perfilCamposFaltantes = computed(() => {
    const r = props.restaurantero;
    const f = [];
    if (!r?.nombre_restaurante)       f.push('Nombre del negocio');
    if (!r?.descripcion)              f.push('Descripción');
    if (!r?.telefono)                 f.push('Teléfono');
    if (!r?.municipio)                f.push('Municipio');
    if (!r?.logo_path)                f.push('Logo');
    if (!r?.productos_top?.length)    f.push('Al menos 1 producto');
    if (r?.entrega_domicilio === null || r?.entrega_domicilio === undefined)
        f.push('Logística (¿entrega a domicilio?)');
    else if (r?.entrega_domicilio && !r?.cobertura_entrega)
        f.push('Cobertura de entrega');
    else if (r?.entrega_domicilio && !r?.forma_entrega)
        f.push('Forma de entrega');
    return f;
});

const bannerPerfilVisible = ref(false);

onMounted(() => {
    const key = `perfil_prov_banner_${page.props.auth.user?.id}`;
    try {
        const stored = localStorage.getItem(key);
        if (stored) {
            const { cerradoEn } = JSON.parse(stored);
            bannerPerfilVisible.value = Date.now() - cerradoEn > 24 * 60 * 60 * 1000;
        } else {
            bannerPerfilVisible.value = true;
        }
    } catch { bannerPerfilVisible.value = true; }
});

const cerrarBannerPerfil = () => {
    bannerPerfilVisible.value = false;
    try {
        localStorage.setItem(`perfil_prov_banner_${page.props.auth.user?.id}`, JSON.stringify({ cerradoEn: Date.now() }));
    } catch {}
};

const mostrarBannerPerfil = computed(() =>
    !props.restaurantero?.aprobado && bannerPerfilVisible.value && perfilCamposFaltantes.value.length > 0
);

// ── MODAL "COMPLETA TU PERFIL" (una vez por sesión) ────────────
const mostrarModalPerfil = ref(false);

onMounted(() => {
    if (!props.restaurantero?.perfil_completo) {
        const yaVisto = sessionStorage.getItem('modal_perfil_proveedor_visto');
        if (!yaVisto) {
            mostrarModalPerfil.value = true;
            sessionStorage.setItem('modal_perfil_proveedor_visto', '1');
        }
    }
});

const completarDesdeModal = () => {
    mostrarModalPerfil.value = false;
    showPerfilModal.value = true;
};

// ── NOTIFICACIONES ────────────────────────────────────────────
const notificacionesDismissed = ref(new Set());
const notificacionesVisibles = computed(() =>
    (props.notificaciones || []).filter(n => !notificacionesDismissed.value.has(n.id))
);
const dismissNotificacion = (id) => {
    notificacionesDismissed.value = new Set([...notificacionesDismissed.value, id]);
    router.patch(route('notificaciones.leer', id), {}, { preserveState: true, preserveScroll: true });
};

const mainTab = ref('panel');

// ── CITA PRÓXIMA 2H ───────────────────────────────────────────
const tiempoHastaCitaProxima = computed(() => {
    if (!props.citaProxima2h) return null;
    const diff = new Date(props.citaProxima2h.inicio) - new Date();
    if (diff <= 0) return 'En curso';
    const totalMins = Math.floor(diff / 60000);
    if (totalMins < 60) return `${totalMins} min`;
    return `${Math.floor(totalMins / 60)}h ${totalMins % 60}min`;
});
</script>

<template>
    <AppLayout title="Mi Negocio">
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Mi Negocio</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">{{ restaurantero.nombre_restaurante }}</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button @click="showPerfilModal = true"
                        class="px-4 py-2 text-sm font-semibold bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl transition-colors">
                        Editar perfil
                    </button>
                    <!-- Ver perfil público -->
                    <Link :href="route('proveedores.show', restaurantero.id)"
                        class="px-4 py-2 text-sm font-semibold bg-guinda-50 dark:bg-guinda-950/30 hover:bg-guinda-100 dark:hover:bg-guinda-900/40 border border-guinda-200 dark:border-guinda-800 text-guinda-700 dark:text-guinda-400 rounded-xl transition-colors flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        Ver perfil
                    </Link>
                    <Link :href="route('restaurantero.citas.index')"
                        class="hidden sm:inline text-sm text-guinda-700 dark:text-guinda-400 hover:underline font-medium">
                        Historial completo →
                    </Link>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 text-xs font-bold rounded-full border border-guinda-200 dark:border-guinda-800">
                        <span class="w-2 h-2 rounded-full bg-guinda-600 dark:bg-guinda-500 animate-pulse"></span>
                        Proveedor
                    </span>
                </div>
            </div>
        </template>

        <!-- Mobile sidebar (carrusel) -->
        <div class="lg:hidden max-w-7xl mx-auto px-4 sm:px-6 pt-4">
            <EventosSidebar :eventos="$page.props.eventosSidebar ?? []" />
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex gap-6 items-start">

        <!-- Sidebar desktop -->
        <div class="hidden lg:block">
            <EventosSidebar :eventos="$page.props.eventosSidebar ?? []" />
        </div>

        <div class="flex-1 min-w-0 space-y-6">

            <!-- ── TABS PRINCIPALES ─────────────────────────────────── -->
            <div class="overflow-x-auto -mx-1 px-1">
                <div class="flex border-b border-gray-200 dark:border-gray-700 gap-0">
                    <button @click="mainTab = 'panel'"
                        class="px-5 py-3 text-sm font-semibold transition-colors whitespace-nowrap"
                        :class="mainTab === 'panel'
                            ? 'border-b-2 border-guinda-700 text-guinda-700 dark:text-guinda-400 dark:border-guinda-400'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'">
                        Mi Panel
                    </button>
                    <button @click="mainTab = 'citas'"
                        class="px-5 py-3 text-sm font-semibold transition-colors whitespace-nowrap"
                        :class="mainTab === 'citas'
                            ? 'border-b-2 border-guinda-700 text-guinda-700 dark:text-guinda-400 dark:border-guinda-400'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'">
                        Mis Citas
                    </button>
                    <button @click="mainTab = 'eventos'"
                        class="px-5 py-3 text-sm font-semibold transition-colors whitespace-nowrap"
                        :class="mainTab === 'eventos'
                            ? 'border-b-2 border-guinda-700 text-guinda-700 dark:text-guinda-400 dark:border-guinda-400'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'">
                        Eventos
                    </button>
                </div>
            </div>

            <!-- ── TAB EVENTOS ──────────────────────────────────────── -->
            <div v-if="mainTab === 'eventos'">
                <TabEventos :eventos="eventos" @open-perfil-modal="showPerfilModal = true" />
            </div>

            <!-- ── TAB CITAS ────────────────────────────────────────── -->
            <div v-if="mainTab === 'citas'">
                <Link :href="route('restaurantero.citas.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Ver historial completo de citas →
                </Link>
            </div>

            <!-- ── TAB PANEL ────────────────────────────────────────── -->
            <template v-if="mainTab === 'panel'">

            <!-- ── BANNER MODO PROVEEDOR ─────────────────────────── -->
            <div class="flex items-center gap-3 px-4 py-3 bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-900/40 rounded-2xl">
                <div class="w-8 h-8 rounded-full bg-guinda-700 dark:bg-guinda-800 flex items-center justify-center text-white font-bold text-sm shrink-0">
                    {{ $page.props.auth.user?.name?.charAt(0).toUpperCase() }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-guinda-800 dark:text-guinda-300 text-sm">
                        Bienvenido, {{ $page.props.auth.user?.name?.split(' ')[0] }}
                    </p>
                    <p class="text-xs text-guinda-600 dark:text-guinda-500">
                        Estás en modo <strong>Proveedor</strong> — aquí gestionas tu negocio y las citas de tus compradores
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-2 flex-wrap">
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
                        ⏳ Perfil incompleto
                    </span>
                    <span v-else
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-bold rounded-full border border-gray-200 dark:border-gray-700">
                        Perfil incompleto
                    </span>
                </div>
            </div>

            <!-- ── ALERTA: CITA EN LAS PRÓXIMAS 2 HORAS ─────────── -->
            <div v-if="citaProxima2h"
                class="flex items-center gap-3 px-5 py-4 bg-red-50 dark:bg-red-950/30 border border-red-300 dark:border-red-800/60 rounded-2xl">
                <div class="w-10 h-10 rounded-xl bg-red-100 dark:bg-red-900/40 border border-red-200 dark:border-red-800 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-red-800 dark:text-red-300">
                        ¡Cita próxima en {{ tiempoHastaCitaProxima }}!
                    </p>
                    <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">
                        {{ citaProxima2h.cliente_name }} · {{ new Date(citaProxima2h.inicio).toLocaleString('es-MX', { hour: '2-digit', minute: '2-digit', weekday: 'short', day: 'numeric', month: 'short' }) }}
                    </p>
                </div>
                <span class="shrink-0 px-3 py-1 bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400 text-xs font-bold rounded-full border border-red-200 dark:border-red-800 animate-pulse">
                    ¡Prepárate!
                </span>
            </div>

            <!-- ── NOTIFICACIONES IN-APP ──────────────────────────── -->
            <div v-if="notificacionesVisibles.length > 0" class="space-y-2">
                <div
                    v-for="notif in notificacionesVisibles" :key="notif.id"
                    class="flex items-start gap-3 px-4 py-3 bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/50 rounded-xl">
                    <div class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-900/40 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-indigo-800 dark:text-indigo-300">{{ notif.titulo }}</p>
                        <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-0.5">{{ notif.mensaje }}</p>
                    </div>
                    <button @click="dismissNotificacion(notif.id)"
                        class="shrink-0 text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors p-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
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
                        <p class="text-xs text-red-600 dark:text-red-500 mt-2">Corrige la información indicada y guarda de nuevo tu perfil.</p>
                    </div>
                    <button @click="showPerfilModal = true"
                        class="shrink-0 px-4 py-2 bg-red-600 hover:bg-red-500 text-white text-sm font-bold rounded-xl">
                        Corregir perfil
                    </button>
                </div>
            </div>

            <!-- ── MODAL: Completa tu perfil (una vez por sesión) ─── -->
            <Transition name="fade">
                <div v-if="mostrarModalPerfil"
                    class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/60 backdrop-blur-sm">
                    <div class="w-full max-w-md bg-white dark:bg-gray-900 rounded-3xl shadow-2xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <!-- Header guinda -->
                        <div class="bg-guinda-800 px-6 py-5 text-white">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-black">¡Completa tu perfil!</h2>
                                    <p class="text-guinda-200 text-sm mt-1">
                                        Tu perfil está incompleto. Complétalo para aparecer en el directorio
                                        y ser aprobado automáticamente en el evento.
                                    </p>
                                </div>
                                <button @click="mostrarModalPerfil = false"
                                    class="text-guinda-300 hover:text-white transition-colors flex-shrink-0 text-2xl leading-none mt-0.5">
                                    ×
                                </button>
                            </div>
                        </div>

                        <!-- Barra de progreso -->
                        <div class="px-6 pt-5">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-semibold text-gray-600 dark:text-gray-400">Progreso de tu perfil</span>
                                <span class="text-xs font-bold text-guinda-700 dark:text-guinda-400">{{ perfilPorcentaje }}%</span>
                            </div>
                            <div class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div class="h-full bg-guinda-700 rounded-full transition-all duration-500"
                                    :style="{ width: perfilPorcentaje + '%' }"></div>
                            </div>
                        </div>

                        <!-- Lista de campos faltantes -->
                        <div class="px-6 py-4 max-h-48 overflow-y-auto">
                            <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                                Te falta completar:
                            </p>
                            <ul class="space-y-1.5">
                                <li v-for="campo in perfilCamposFaltantes" :key="campo"
                                    class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <span class="w-4 h-4 rounded-full bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xs flex-shrink-0 font-bold">!</span>
                                    {{ campo }}
                                </li>
                            </ul>
                        </div>

                        <!-- Acciones -->
                        <div class="px-6 pb-6 flex gap-3">
                            <button @click="mostrarModalPerfil = false"
                                class="flex-1 py-3 border border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-400 font-semibold rounded-xl text-sm hover:border-gray-400 transition-colors">
                                Más tarde
                            </button>
                            <button @click="completarDesdeModal"
                                class="flex-[2] py-3 bg-guinda-800 hover:bg-guinda-700 text-white font-bold rounded-xl text-sm transition-colors shadow-sm">
                                Completar ahora →
                            </button>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ── BANNER PERFIL INCOMPLETO CON PROGRESO ──────────── -->
            <div v-if="mostrarBannerPerfil"
                class="bg-white dark:bg-gray-900 border border-guinda-200 dark:border-guinda-900/50 rounded-2xl px-5 py-4 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-2 gap-2 flex-wrap">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">
                                Tu perfil está al
                                <span class="text-guinda-700 dark:text-guinda-400">{{ perfilPorcentaje }}%</span>
                                — complétalo para mejorar tu visibilidad
                            </p>
                            <button @click="cerrarBannerPerfil" class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden mb-2">
                            <div
                                class="h-2 rounded-full transition-all duration-500"
                                :class="perfilPorcentaje >= 80 ? 'bg-emerald-500' : 'bg-guinda-600'"
                                :style="{ width: perfilPorcentaje + '%' }"
                            ></div>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs text-gray-500 dark:text-gray-400">Falta:</span>
                            <span
                                v-for="campo in perfilCamposFaltantes" :key="campo"
                                class="inline-flex items-center px-2 py-0.5 bg-guinda-50 dark:bg-guinda-950/30 text-guinda-700 dark:text-guinda-400 text-xs font-medium rounded-full border border-guinda-200 dark:border-guinda-900">
                                {{ campo }}
                            </span>
                            <button @click="showPerfilModal = true"
                                class="ml-auto shrink-0 px-3 py-1.5 bg-guinda-800 hover:bg-guinda-700 text-white text-xs font-bold rounded-xl transition-colors">
                                Completar ahora
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── KPI CARDS (5 métricas) ────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <div v-for="kpi in kpis" :key="kpi.label"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex items-center gap-3 shadow-sm">
                    <div :class="['w-10 h-10 rounded-xl border flex items-center justify-center shrink-0', kpi.bg]">
                        <svg :class="['w-5 h-5', kpi.iconColor]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="kpi.icon" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500 dark:text-gray-500 font-medium truncate">{{ kpi.label }}</p>
                        <p :class="['text-2xl font-black', kpi.valColor]">{{ kpi.value ?? 0 }}</p>
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
                        class="px-5 py-4 flex flex-col sm:flex-row sm:items-start gap-3 hover:bg-amber-50/50 dark:hover:bg-amber-950/10 transition-colors">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-guinda-100 dark:bg-guinda-900/30 border border-guinda-200 dark:border-guinda-800 flex items-center justify-center text-guinda-700 dark:text-guinda-400 font-bold text-sm shrink-0">
                                {{ iniciales(cita.cliente.name) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900 dark:text-white truncate">{{ cita.cliente.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ cita.cliente.email }}</p>
                                <p v-if="cita.cliente.telefono && cita.cliente.telefono !== 'N/A'" class="text-xs text-gray-400 dark:text-gray-500">{{ cita.cliente.telefono }}</p>
                                <!-- Necesidades del comprador -->
                                <p v-if="cita.cliente.necesidades"
                                    class="mt-1 text-xs text-guinda-700 dark:text-guinda-400 bg-guinda-50 dark:bg-guinda-950/30 border border-guinda-200 dark:border-guinda-900/40 rounded-lg px-2 py-1 line-clamp-2">
                                    <span class="font-semibold">Busca:</span> {{ cita.cliente.necesidades }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2 sm:items-end shrink-0">
                            <p class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ fmt(cita.inicio) }}</p>
                            <div class="flex items-center gap-2">
                                <button @click="accion('aceptar', cita)" :disabled="procesando"
                                    class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl min-h-[36px]">✓ Aceptar</button>
                                <button @click="abrirReagendar(cita)" :disabled="procesando"
                                    class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-white text-xs font-bold rounded-xl min-h-[36px]">Reagendar</button>
                                <button @click="accion('rechazar', cita)" :disabled="procesando"
                                    class="px-3 py-1.5 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl min-h-[36px]">Rechazar</button>
                            </div>
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
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-center text-gray-600 dark:text-gray-400 font-bold text-xs shrink-0">
                                {{ iniciales(cita.cliente.name) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 dark:text-gray-200 truncate text-sm">{{ cita.cliente.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ cita.cliente.email }}</p>
                            </div>
                        </div>
                        <p class="text-xs text-gray-600 dark:text-gray-400 font-medium shrink-0">{{ fmt(cita.inicio) }}</p>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border shrink-0"
                            :class="estadoConfig[cita.estado]?.class || 'bg-gray-500/15 text-gray-400'">
                            {{ estadoConfig[cita.estado]?.label || cita.estado }}
                        </span>
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

            </template><!-- /mainTab === 'panel' -->
        </div><!-- /flex-1 -->
        </div><!-- /flex -->
        </div><!-- /max-w-7xl -->

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
                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Al completar todos los campos requeridos, tu perfil se aprueba automáticamente</p>
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
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">RFC</label>
                                    <input v-model="formPerfil.rfc" type="text" placeholder="XXXX000000XXX"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Sitio web</label>
                                    <input v-model="formPerfil.sitio_web" type="url" placeholder="https://..."
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                            </div>
                        </div>

                        <!-- Condiciones comerciales -->
                        <div>
                            <p class="text-xs font-bold text-gray-500 dark:text-gray-500 uppercase tracking-wider mb-3">Condiciones comerciales</p>
                            <div class="space-y-4">
                                <!-- Crédito -->
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="formPerfil.acepta_credito"
                                        class="w-4 h-4 rounded border-gray-300 text-guinda-600 focus:ring-guinda-500" />
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Acepta crédito</span>
                                </label>
                                <div v-if="formPerfil.acepta_credito" class="ml-7 space-y-3">
                                    <!-- Checkbox A negociar -->
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" v-model="formPerfil.credito_a_negociar"
                                            class="w-4 h-4 rounded border-gray-300 text-guinda-600 focus:ring-guinda-500" />
                                        <span class="text-sm text-gray-700 dark:text-gray-300">A negociar</span>
                                    </label>

                                    <!-- Campos condicionales: requeridos si NO es a negociar -->
                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                Monto máximo (MXN)
                                                <span v-if="!formPerfil.credito_a_negociar" class="text-red-500">*</span>
                                            </label>
                                            <input v-model="formPerfil.credito_monto_maximo"
                                                type="number" min="0" step="0.01" placeholder="50000"
                                                :required="formPerfil.acepta_credito && !formPerfil.credito_a_negociar"
                                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 focus:outline-none focus:border-guinda-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                Plazo
                                                <span v-if="!formPerfil.credito_a_negociar" class="text-red-500">*</span>
                                            </label>
                                            <input v-model="formPerfil.credito_tiempo_cantidad"
                                                type="number" min="1" placeholder="30"
                                                :required="formPerfil.acepta_credito && !formPerfil.credito_a_negociar"
                                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 focus:outline-none focus:border-guinda-500" />
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                                Unidad
                                                <span v-if="!formPerfil.credito_a_negociar" class="text-red-500">*</span>
                                            </label>
                                            <select v-model="formPerfil.credito_tiempo_unidad"
                                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:border-guinda-500">
                                                <option value="">-- Selecciona --</option>
                                                <option value="dias">Días</option>
                                                <option value="semanas">Semanas</option>
                                                <option value="meses">Meses</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <!-- Contraentrega -->
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="formPerfil.pago_contraentrega"
                                        class="w-4 h-4 rounded border-gray-300 text-guinda-600 focus:ring-guinda-500" />
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Acepta pago contraentrega</span>
                                </label>
                                <!-- Factura -->
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" v-model="formPerfil.factura"
                                        class="w-4 h-4 rounded border-gray-300 text-guinda-600 focus:ring-guinda-500" />
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Emite factura</span>
                                </label>
                                <div v-if="formPerfil.factura" class="ml-7">
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Régimen fiscal</label>
                                    <input v-model="formPerfil.regimen_fiscal" type="text" maxlength="100"
                                        placeholder="Ej. Régimen Simplificado de Confianza (RESICO)"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2 focus:outline-none focus:border-guinda-500" />
                                </div>
                            </div>
                        </div>

                        <!-- Logística y Distribución -->
                        <div class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 space-y-4">
                            <h3 class="text-sm font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                🚚 Logística y Distribución
                            </h3>

                            <div>
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    ¿Entregan a domicilio?
                                </p>
                                <div class="flex gap-3">
                                    <button type="button"
                                        @click="formPerfil.entrega_domicilio = true"
                                        :class="formPerfil.entrega_domicilio === true
                                            ? 'bg-guinda-700 text-white border-guinda-700 dark:bg-guinda-600 dark:border-guinda-600'
                                            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-guinda-400'"
                                        class="flex-1 py-2 rounded-xl border text-sm font-semibold transition-colors">
                                        Sí
                                    </button>
                                    <button type="button"
                                        @click="formPerfil.entrega_domicilio = false; formPerfil.cobertura_entrega = ''; formPerfil.forma_entrega = ''"
                                        :class="formPerfil.entrega_domicilio === false
                                            ? 'bg-guinda-700 text-white border-guinda-700 dark:bg-guinda-600 dark:border-guinda-600'
                                            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-guinda-400'"
                                        class="flex-1 py-2 rounded-xl border text-sm font-semibold transition-colors">
                                        No
                                    </button>
                                </div>
                                <p v-if="formPerfil.errors?.entrega_domicilio"
                                   class="text-red-500 text-xs mt-1">
                                    {{ formPerfil.errors.entrega_domicilio }}
                                </p>
                            </div>

                            <template v-if="formPerfil.entrega_domicilio === true">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        ¿Cobertura de entrega?
                                    </p>
                                    <div class="flex gap-2 flex-wrap">
                                        <button v-for="op in ['local', 'regional', 'nacional']"
                                            :key="op" type="button"
                                            @click="formPerfil.cobertura_entrega = op"
                                            :class="formPerfil.cobertura_entrega === op
                                                ? 'bg-guinda-700 text-white border-guinda-700 dark:bg-guinda-600 dark:border-guinda-600'
                                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-guinda-400'"
                                            class="px-5 py-2 rounded-xl border text-sm font-semibold transition-colors capitalize">
                                            {{ op.charAt(0).toUpperCase() + op.slice(1) }}
                                        </button>
                                    </div>
                                    <p v-if="formPerfil.errors?.cobertura_entrega"
                                       class="text-red-500 text-xs mt-1">
                                        {{ formPerfil.errors.cobertura_entrega }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        ¿Forma de entrega?
                                    </p>
                                    <div class="flex gap-2">
                                        <button v-for="op in ['programada', 'flexible']"
                                            :key="op" type="button"
                                            @click="formPerfil.forma_entrega = op"
                                            :class="formPerfil.forma_entrega === op
                                                ? 'bg-guinda-700 text-white border-guinda-700 dark:bg-guinda-600 dark:border-guinda-600'
                                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-guinda-400'"
                                            class="flex-1 py-2 rounded-xl border text-sm font-semibold transition-colors capitalize">
                                            {{ op.charAt(0).toUpperCase() + op.slice(1) }}
                                        </button>
                                    </div>
                                    <p v-if="formPerfil.errors?.forma_entrega"
                                       class="text-red-500 text-xs mt-1">
                                        {{ formPerfil.errors.forma_entrega }}
                                    </p>
                                </div>
                            </template>
                        </div>

                        <!-- Información legal y operativa (IYEM) -->
                        <div>
                            <p class="text-xs font-bold text-gray-500 dark:text-gray-500 uppercase tracking-wider mb-3">Información legal y operativa</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Razón social</label>
                                    <input v-model="formPerfil.razon_social" type="text" maxlength="200" placeholder="Mi Empresa S.A. de C.V."
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del representante legal</label>
                                    <input v-model="formPerfil.nombre_representante" type="text" maxlength="200" placeholder="Nombre completo"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CURP del representante</label>
                                    <input v-model="formPerfil.curp_representante" type="text" maxlength="18" placeholder="XXXX000000XXXXXXXX"
                                        @input="formPerfil.curp_representante = formPerfil.curp_representante.toUpperCase().slice(0,18)"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 uppercase" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de inicio de operaciones</label>
                                    <input v-model="formPerfil.fecha_inicio_operaciones" type="date"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número de empleados</label>
                                    <input v-model.number="formPerfil.num_empleados" type="number" min="0" placeholder="0"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Domicilio fiscal en Yucatán?</label>
                                    <select v-model="formPerfil.domicilio_en_yucatan"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500">
                                        <option :value="null">— Sin especificar —</option>
                                        <option :value="true">Sí</option>
                                        <option :value="false">No</option>
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Mercado meta / Segmento objetivo</label>
                                    <textarea v-model="formPerfil.mercado_meta" rows="2" placeholder="¿A quién va dirigido tu producto o servicio?"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 resize-none" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tiempo de vida en anaquel (si aplica)</label>
                                    <input v-model="formPerfil.tiempo_vida_anaquel" type="text" maxlength="500"
                                        placeholder="Ej: Vasos 365 días, Mermelada 180 días"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500" />
                                </div>
                                <!-- Solo alimentos y bebidas -->
                                <div class="sm:col-span-2 space-y-3">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Requisitos de alimentos y bebidas (marcar los que cumple)</label>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                        <label v-for="req in ['Tabla Nutrimental','Etiquetado NOM-051','Código de barras','Registro de Marca','Cert. calidad/inocuidad']"
                                            :key="req" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                                            <input type="checkbox" :value="req" v-model="formPerfil.requisitos_alimentos"
                                                class="rounded border-gray-300 text-guinda-600 focus:ring-guinda-500" />
                                            {{ req }}
                                        </label>
                                    </div>
                                </div>
                                <div class="sm:col-span-2 space-y-3">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">¿En qué requisitos requieres apoyo del IYEM?</label>
                                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                        <label v-for="req in ['Tabla Nutrimental','Etiquetado NOM-051','Código de barras','Registro de Marca','Cert. calidad/inocuidad']"
                                            :key="req" class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 cursor-pointer">
                                            <input type="checkbox" :value="req" v-model="formPerfil.apoyo_requisitos"
                                                class="rounded border-gray-300 text-guinda-600 focus:ring-guinda-500" />
                                            {{ req }}
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Requiere refrigeración?</label>
                                    <select v-model="formPerfil.requiere_refrigeracion"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500">
                                        <option :value="null">— No aplica —</option>
                                        <option :value="true">Sí</option>
                                        <option :value="false">No</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">¿Requiere congelación (-4° o menos)?</label>
                                    <select v-model="formPerfil.requiere_congelacion"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500">
                                        <option :value="null">— No aplica —</option>
                                        <option :value="true">Sí</option>
                                        <option :value="false">No</option>
                                    </select>
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
                                    <p class="text-xs text-gray-400 mt-1">JPG, PNG o WEBP · Máx. 4MB · Resolución recomendada: <span class="font-semibold text-gray-500 dark:text-gray-300">1280 × 720 px</span> (proporción 16:9 horizontal)</p>
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
                                        <p class="text-xs text-gray-400 mt-1">JPG, PNG o WEBP · Máx. 4MB · Resolución recomendada: <span class="font-semibold text-gray-500 dark:text-gray-300">800 × 600 px</span></p>
                                    </div>
                                    <div class="flex gap-2 mt-2">
                                        <input
                                            type="number"
                                            v-model="prod.capacidad_cantidad"
                                            min="0"
                                            placeholder="Cant. aprox."
                                            class="w-1/2 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700
                                                   text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600
                                                   rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-guinda-500
                                                   dark:focus:border-guinda-500 transition-colors"
                                        />
                                        <select
                                            v-model="prod.capacidad_unidad"
                                            class="w-1/2 bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700
                                                   text-gray-900 dark:text-white rounded-xl px-3 py-2 text-sm
                                                   focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500
                                                   transition-colors"
                                        >
                                            <option value="piezas">Piezas</option>
                                            <option value="cajas">Cajas</option>
                                            <option value="litros">Litros</option>
                                            <option value="kilogramos">Kilogramos</option>
                                        </select>
                                    </div>
                                    <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">
                                        Capacidad de producción mensual aprox.
                                    </p>
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
                                {{ guardandoPerfil ? 'Guardando...' : 'Guardar perfil' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Banner: invitación a ser comprador -->
        <div v-if="!$page.props.auth.user?.is_cliente"
            class="mx-4 sm:mx-6 lg:mx-8 mt-6 mb-8 max-w-7xl lg:mx-auto bg-gradient-to-r from-guinda-900/80 to-guinda-800/60 border border-guinda-700/50 rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
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

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
