<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import TabEventos from '@/Components/TabEventos.vue';
import EventosSidebar from '@/Components/EventosSidebar.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import esLocale from '@fullcalendar/core/locales/es';

const props = defineProps({
    citas: Array,
    citasCount: Number,
    evento: Object,
    edicionesHistorial: Array,
    proveedores: Array,
    compradorAprobado: Boolean,
    tieneKeywords: Boolean,
    notificaciones: Array,
    eventos: { type: Array, default: () => [] },
});

const page = usePage();
const historialAbierto = ref(false);
const MAX_CITAS = computed(() => props.evento?.max_citas_por_comprador ?? 3);
const activeTab = ref('lista');
const paramsIniciales = new URLSearchParams(window.location.search);
const mainTab = ref(paramsIniciales.get('tab') === 'eventos' ? 'eventos' : 'citas');

const estadoConfig = {
    pendiente:  { label: 'Pendiente',  class: 'bg-guinda-500/15 text-guinda-600 dark:text-guinda-400 border-guinda-500/20' },
    confirmada: { label: 'Confirmada', class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' },
    cancelada:  { label: 'Cancelada',  class: 'bg-gray-500/15 text-gray-500 dark:text-gray-400 border-gray-500/20' },
    completada: { label: 'Completada', class: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/20' },
    rechazada:  { label: 'Rechazada',  class: 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/20' },
    reagendada: { label: 'Reagendada', class: 'bg-guinda-500/15 text-guinda-600 dark:text-guinda-400 border-guinda-500/20' },
};

const estadoColors = {
    pendiente:  '#8b1028',
    confirmada: '#10b981',
    cancelada:  '#6b7280',
    completada: '#6366f1',
};

const porcentaje = computed(() => Math.min((props.citasCount / MAX_CITAS.value) * 100, 100));

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleString('es-MX', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};

const cancelarCita = (id) => {
    if (!confirm('¿Seguro que deseas cancelar esta cita?')) return;
    router.delete(route('citas.destroy', id));
};

const puedesCancelar = (estado) => !['completada', 'cancelada', 'rechazada'].includes(estado);

const calendarEvents = computed(() =>
    props.citas.map(c => ({
        title: c.restaurantero?.nombre_restaurante || 'Cita',
        start: c.inicio,
        end: c.fin,
        color: estadoColors[c.estado] || '#8b1028',
    }))
);

const proximaCita = computed(() => {
    const now = new Date();
    const upcoming = props.citas.filter(c => new Date(c.inicio) >= now && !['cancelada','rechazada'].includes(c.estado));
    if (!upcoming.length) return null;
    return upcoming.reduce((min, c) => new Date(c.inicio) < new Date(min.inicio) ? c : min);
});

const citasActivas = computed(() =>
    props.citas.filter(c => !['cancelada', 'rechazada', 'completada'].includes(c.estado))
);

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin],
    initialView: 'dayGridMonth',
    locale: esLocale,
    headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek' },
    initialDate: proximaCita.value ? proximaCita.value.inicio : undefined,
    events: calendarEvents.value,
    height: 'auto',
    slotMinTime: '09:00:00',
    slotMaxTime: '16:00:00',
    hiddenDays: [0, 6],
    nowIndicator: true,
    businessHours: { daysOfWeek: [1, 2, 3, 4, 5], startTime: '09:00', endTime: '16:00' },
}));

const iniciales = (nombre) => {
    if (!nombre) return '?';
    return nombre.split(' ').slice(0, 2).map(p => p[0]).join('').toUpperCase();
};

// ── BANNER PERFIL INCOMPLETO ─────────────────────────────────
const perfilPorcentaje = computed(() => {
    const u = page.props.auth.user;
    if (!u) return 0;
    let pct = 0;
    if (u.name) pct += 25;
    if (u.telefono) pct += 25;
    if (u.necesidades) pct += 35;
    if (u.sitio_web) pct += 15;
    return pct;
});

const perfilCamposFaltantes = computed(() => {
    const u = page.props.auth.user;
    const f = [];
    if (!u?.telefono) f.push('Teléfono');
    if (!u?.necesidades) f.push('Qué buscas');
    if (!u?.sitio_web) f.push('Sitio web');
    return f;
});

const bannerPerfilVisible = ref(false);

onMounted(() => {
    const key = `perfil_banner_${page.props.auth.user?.id}`;
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
        localStorage.setItem(`perfil_banner_${page.props.auth.user?.id}`, JSON.stringify({ cerradoEn: Date.now() }));
    } catch {}
};

const mostrarBannerPerfil = computed(() =>
    bannerPerfilVisible.value && perfilCamposFaltantes.value.length > 0
);

// ── NOTIFICACIONES IN-APP ────────────────────────────────────
const notificacionesDismissed = ref(new Set());
const notificacionesVisibles = computed(() =>
    (props.notificaciones || []).filter(n => !notificacionesDismissed.value.has(n.id))
);
const dismissNotificacion = (id) => {
    notificacionesDismissed.value = new Set([...notificacionesDismissed.value, id]);
    router.patch(route('notificaciones.leer', id), {}, { preserveState: true, preserveScroll: true });
};

// ── COUNTDOWN A PRÓXIMA CITA ─────────────────────────────────
const tiempoHastaProxima = computed(() => {
    if (!proximaCita.value) return null;
    const diff = new Date(proximaCita.value.inicio) - new Date();
    if (diff <= 0) return 'En curso';
    const totalMins = Math.floor(diff / 60000);
    const days = Math.floor(totalMins / 1440);
    const hours = Math.floor((totalMins % 1440) / 60);
    const mins = totalMins % 60;
    if (days > 0) return `Faltan ${days} día${days !== 1 ? 's' : ''}`;
    if (hours > 0) return `Faltan ${hours}h ${mins}min`;
    return `En ${mins} min`;
});

const countdownUrgente = computed(() => {
    if (!proximaCita.value) return false;
    const diff = new Date(proximaCita.value.inicio) - new Date();
    return diff > 0 && diff < 2 * 60 * 60 * 1000;
});

// ── FILTRO DE PROVEEDORES ────────────────────────────────────
const filtroTexto = ref('');
const filtroCategoria = ref('');

const categoriasDisponibles = computed(() =>
    [...new Set((props.proveedores || []).map(p => p.categoria).filter(Boolean))].sort()
);

const proveedoresFiltrados = computed(() => {
    let lista = props.proveedores || [];
    if (filtroTexto.value.trim()) {
        const q = filtroTexto.value.toLowerCase();
        lista = lista.filter(p =>
            (p.nombre_restaurante || '').toLowerCase().includes(q) ||
            (p.descripcion || '').toLowerCase().includes(q) ||
            (p.categoria || '').toLowerCase().includes(q)
        );
    }
    if (filtroCategoria.value) {
        lista = lista.filter(p => p.categoria === filtroCategoria.value);
    }
    return lista;
});

// ── DISPONIBILIDAD DE SLOTS POR PROVEEDOR ───────────────────
const disponibilidadInfo = (prov) => {
    const count = prov.citas_evento_count ?? 0;
    if (count >= 12) return { text: 'Lleno', cls: 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' };
    if (count >= 8)  return { text: 'Pocos slots', cls: 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400' };
    return { text: 'Disponible', cls: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400' };
};

// ── KEYWORDS DETECTADAS ──────────────────────────────────────
const keywordsDetectadas = computed(() => {
    const nec = page.props.auth.user?.necesidades || '';
    if (!nec.trim()) return [];
    return [...new Set(
        nec.split(/[\s,;.]+/).filter(p => p.length >= 4)
    )].slice(0, 6);
});

// ── EDITAR NOTAS DE CITA ─────────────────────────────────────
const citaEditandoNota = ref(null);
const notaEdicion = ref('');

const abrirEditarNota = (cita) => {
    citaEditandoNota.value = cita.id;
    notaEdicion.value = cita.notas || '';
};
const cancelarEditarNota = () => { citaEditandoNota.value = null; };
const guardarNota = (citaId) => {
    router.patch(route('citas.notas', citaId), { notas: notaEdicion.value }, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => { citaEditandoNota.value = null; },
    });
};
</script>

<template>
    <AppLayout title="Inicio">
        <template #header>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Mi Panel</h1>
            <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Bienvenido, {{ $page.props.auth.user?.name?.split(' ')[0] }}</p>
        </template>

        <!-- Mobile sidebar (carrusel) -->
        <div class="lg:hidden max-w-5xl mx-auto px-4 sm:px-6 pt-4">
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
                <TabEventos :eventos="eventos" />
            </div>

            <!-- ── TAB CITAS ────────────────────────────────────────── -->
            <template v-if="mainTab === 'citas'">

            <!-- ── BANNER MODO COMPRADOR ─────────────────────────── -->
            <div class="flex items-center gap-3 px-4 py-3 bg-guinda-50 dark:bg-guinda-950/30 border border-guinda-200 dark:border-guinda-900 rounded-2xl">
                <div class="w-8 h-8 rounded-full bg-guinda-700 dark:bg-guinda-600 flex items-center justify-center text-white font-bold text-sm shrink-0">
                    {{ $page.props.auth.user?.name?.charAt(0).toUpperCase() }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-bold text-guinda-800 dark:text-guinda-300 text-sm">
                        Bienvenido, {{ $page.props.auth.user?.name?.split(' ')[0] }}
                    </p>
                    <p class="text-xs text-guinda-600 dark:text-guinda-500">Estás en modo <strong>Comprador</strong> — aquí puedes ver y gestionar tus citas de networking</p>
                </div>
                <span class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1 bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-400 text-xs font-bold rounded-full border border-guinda-200 dark:border-guinda-800">
                    <span class="w-2 h-2 rounded-full bg-guinda-600 dark:bg-guinda-500"></span>
                    Comprador
                </span>
            </div>

            <!-- ── NOTIFICACIONES IN-APP ──────────────────────────── -->
            <div v-if="notificacionesVisibles.length > 0" class="space-y-2">
                <div
                    v-for="notif in notificacionesVisibles" :key="notif.id"
                    class="flex items-start gap-3 px-4 py-3 bg-indigo-50 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800/50 rounded-xl"
                >
                    <div class="w-8 h-8 rounded-full bg-indigo-100 dark:bg-indigo-900/40 border border-indigo-200 dark:border-indigo-800 flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-indigo-800 dark:text-indigo-300">{{ notif.titulo }}</p>
                        <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-0.5">{{ notif.mensaje }}</p>
                    </div>
                    <button @click="dismissNotificacion(notif.id)"
                        class="shrink-0 text-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- ── BANNER PERFIL INCOMPLETO ──────────────────────── -->
            <div v-if="mostrarBannerPerfil"
                class="bg-white dark:bg-gray-900 border border-guinda-200 dark:border-guinda-900/50 rounded-2xl px-5 py-4 shadow-sm">
                <div class="flex items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-2 gap-2 flex-wrap">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">
                                Tu perfil está al <span class="text-guinda-700 dark:text-guinda-400">{{ perfilPorcentaje }}%</span> — complétalo para un mejor matchmaking
                            </p>
                            <button @click="cerrarBannerPerfil" class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 overflow-hidden mb-2">
                            <div
                                class="h-2 rounded-full bg-guinda-600 transition-all duration-500"
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
                            <Link :href="route('user.perfil')"
                                class="ml-auto shrink-0 px-3 py-1.5 bg-guinda-800 hover:bg-guinda-700 text-white text-xs font-bold rounded-xl transition-colors">
                                Completar perfil
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── BANNER CÁMARA/ASOCIACIÓN SIN RESPONDER ────────── -->
            <div v-if="!$page.props.auth.user?.camara_asociacion"
                class="mb-4 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl flex items-start gap-3">
                <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Tenemos una pregunta nueva para ti</p>
                    <p class="text-sm text-amber-700 dark:text-amber-400 mt-0.5">
                        ¿Perteneces a alguna cámara o asociación (CANIRAC u otra)?
                        <Link :href="route('user.perfil')"
                            class="ml-1 font-semibold underline hover:no-underline">
                            Actualizar mi perfil →
                        </Link>
                    </p>
                </div>
            </div>

            <!-- ── BIENVENIDA + PRÓXIMA CITA ──────────────────────── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Progress de citas -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm transition-colors">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="font-semibold text-gray-900 dark:text-white">Citas agendadas</h3>
                            <p class="text-sm text-gray-500 mt-0.5">
                                <span v-if="evento">{{ evento.nombre }}</span>
                                <span v-else class="text-amber-500">Sin evento activo</span>
                                · Máx. {{ MAX_CITAS }} citas
                            </p>
                        </div>
                        <span class="text-3xl font-black" :class="citasCount >= MAX_CITAS ? 'text-red-500 dark:text-red-400' : 'text-guinda-700 dark:text-guinda-400'">
                            {{ citasCount }}<span class="text-sm font-normal text-gray-400">/{{ MAX_CITAS }}</span>
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 dark:bg-gray-800 rounded-full h-2.5 overflow-hidden">
                        <div
                            class="h-2.5 rounded-full transition-all duration-500"
                            :class="citasCount >= MAX_CITAS ? 'bg-red-500' : citasCount >= MAX_CITAS * 0.75 ? 'bg-guinda-600' : 'bg-emerald-500'"
                            :style="{ width: porcentaje + '%' }"
                        ></div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ MAX_CITAS - citasCount }} citas disponibles</span>
                        <Link v-if="citasCount < MAX_CITAS" :href="route('proveedores.index')"
                            class="text-sm text-guinda-700 dark:text-guinda-400 hover:text-guinda-600 font-medium transition-colors flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Nueva cita
                        </Link>
                    </div>
                </div>

                <!-- Próxima cita o banner info -->
                <div v-if="proximaCita"
                    class="bg-gradient-to-br from-guinda-800 to-guinda-900 dark:from-guinda-900 dark:to-gray-900 border border-guinda-700 dark:border-guinda-800 rounded-2xl p-6 shadow-sm text-white relative overflow-hidden">
                    <div class="absolute inset-0 opacity-10 pointer-events-none">
                        <svg viewBox="0 0 200 200" class="w-48 h-48 absolute -right-8 -bottom-8 text-white" fill="currentColor">
                            <circle cx="100" cy="100" r="80" fill="none" stroke="currentColor" stroke-width="8"/>
                            <path d="M100 30 L100 100 L140 140" stroke="currentColor" stroke-width="8" fill="none" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <p class="text-guinda-200 text-xs font-semibold uppercase tracking-widest mb-2 relative z-10">Próxima cita</p>
                    <p class="text-white font-bold text-lg leading-tight relative z-10">
                        {{ proximaCita.restaurantero?.nombre_restaurante || 'Proveedor' }}
                    </p>
                    <p class="text-guinda-200 text-sm mt-2 relative z-10 capitalize">{{ formatFecha(proximaCita.inicio) }}</p>
                    <!-- Countdown -->
                    <p v-if="tiempoHastaProxima" class="mt-2 relative z-10">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold"
                            :class="countdownUrgente
                                ? 'bg-red-500/30 text-red-100 border border-red-400/30'
                                : 'bg-white/10 text-guinda-100 border border-white/20'">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ tiempoHastaProxima }}
                        </span>
                    </p>
                    <span class="mt-3 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border relative z-10"
                        :class="estadoConfig[proximaCita.estado]?.class || 'bg-white/10 text-white border-white/20'">
                        {{ estadoConfig[proximaCita.estado]?.label || proximaCita.estado }}
                    </span>
                </div>
                <div v-else
                    class="bg-guinda-50 dark:bg-guinda-950/30 border border-guinda-200 dark:border-guinda-900 rounded-2xl p-6 transition-colors">
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-lg bg-guinda-100 dark:bg-guinda-900/40 border border-guinda-200 dark:border-guinda-800 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-guinda-800 dark:text-guinda-300 text-sm">Ubicación del evento</p>
                            <p class="text-guinda-700 dark:text-guinda-400/80 text-sm mt-1">Av. Industrias No Contaminantes Tab 13613, Col. Sodzil Norte, Mérida, Yucatán</p>
                            <p class="text-guinda-600/70 dark:text-guinda-500/60 text-xs mt-1">Lunes a Viernes · 9:00 am — 4:00 pm</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── PERFIL DEL COMPRADOR ──────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white">Mi perfil</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            Completa tu perfil y cuéntanos qué buscas — te mostraremos proveedores relevantes
                        </p>
                    </div>
                    <Link :href="route('user.perfil')"
                        class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 hover:underline flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Editar perfil
                    </Link>
                </div>
                <!-- Resumen de datos -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500 font-medium">Nombre</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $page.props.auth.user?.name || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500 font-medium">Teléfono</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-200">{{ $page.props.auth.user?.telefono || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500 font-medium">Correo</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $page.props.auth.user?.email || '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500 font-medium">Página web</p>
                        <p class="font-semibold text-gray-800 dark:text-gray-200 truncate">{{ $page.props.auth.user?.sitio_web || '—' }}</p>
                    </div>
                </div>
                <!-- Necesidades -->
                <div class="mt-3 p-3 bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-900/30 rounded-xl">
                    <p class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 mb-1">¿Qué necesidades tienes?</p>
                    <p v-if="$page.props.auth.user?.necesidades" class="text-sm text-gray-700 dark:text-gray-300">
                        {{ $page.props.auth.user.necesidades }}
                    </p>
                    <Link v-else :href="route('user.perfil')"
                        class="text-sm text-guinda-600 dark:text-guinda-400 italic">
                        Escribe qué tipo de proveedores buscas para recibir sugerencias personalizadas →
                    </Link>
                    <!-- Keywords detectadas -->
                    <div v-if="keywordsDetectadas.length > 0" class="mt-2 flex flex-wrap gap-1.5">
                        <span class="text-xs text-guinda-500 dark:text-guinda-500 font-medium">Palabras clave:</span>
                        <span
                            v-for="kw in keywordsDetectadas" :key="kw"
                            class="inline-flex items-center px-2 py-0.5 bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 text-xs font-medium rounded-full border border-guinda-200 dark:border-guinda-800">
                            {{ kw }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- ── CARRUSEL DE PROVEEDORES ─────────────────────────── -->
            <!-- Sin evento activo: no mostrar nada -->
            <!-- Con evento pero sin aprobación: mostrar mensaje según estado -->
            <div v-if="$page.props.eventoActivo && !compradorAprobado"
                class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 text-center">
                <svg class="w-10 h-10 mx-auto text-gray-300 dark:text-gray-700 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>
                </svg>
                <p v-if="$page.props.registro_evento?.estado === 'pendiente'"
                    class="text-sm font-semibold text-amber-700 dark:text-amber-400 mb-1">
                    Tu solicitud al evento está pendiente de aprobación
                </p>
                <p v-else-if="$page.props.registro_evento?.estado === 'rechazado'"
                    class="text-sm font-semibold text-red-600 dark:text-red-400 mb-1">
                    Tu solicitud al evento fue rechazada
                </p>
                <p v-else class="text-sm font-semibold text-gray-600 dark:text-gray-400 mb-1">
                    Regístrate al evento para ver los proveedores disponibles
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-600">
                    Los proveedores solo son visibles para compradores aprobados en el evento.
                </p>
            </div>

            <div v-if="compradorAprobado && proveedores && proveedores.length > 0">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Proveedores disponibles</h2>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Haz clic para ver el perfil y agendar una cita</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span v-if="tieneKeywords" class="text-xs bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 px-2.5 py-1 rounded-full border border-guinda-200 dark:border-guinda-800 font-semibold hidden sm:inline-flex">
                            ✦ Ordenados por tus necesidades
                        </span>
                        <Link :href="route('proveedores.index')"
                            class="text-sm text-guinda-700 dark:text-guinda-400 hover:underline font-medium">
                            Ver todos →
                        </Link>
                    </div>
                </div>

                <!-- Buscador + chips de categoría -->
                <div class="mb-3 space-y-2">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                        </svg>
                        <input
                            v-model="filtroTexto"
                            type="text"
                            placeholder="Buscar por nombre, categoría o descripción..."
                            class="w-full pl-9 pr-4 py-2 text-sm bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl text-gray-700 dark:text-gray-300 placeholder-gray-400 focus:outline-none focus:border-guinda-400 dark:focus:border-guinda-600 transition-colors"
                        />
                    </div>
                    <div v-if="categoriasDisponibles.length > 0" class="flex flex-wrap gap-2">
                        <button
                            @click="filtroCategoria = ''"
                            :class="filtroCategoria === ''
                                ? 'bg-guinda-800 text-white border-guinda-800 dark:bg-guinda-700 dark:border-guinda-700'
                                : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-guinda-300'"
                            class="px-3 py-1 text-xs font-medium rounded-full border transition-colors">
                            Todos
                        </button>
                        <button
                            v-for="cat in categoriasDisponibles" :key="cat"
                            @click="filtroCategoria = filtroCategoria === cat ? '' : cat"
                            :class="filtroCategoria === cat
                                ? 'bg-guinda-800 text-white border-guinda-800 dark:bg-guinda-700 dark:border-guinda-700'
                                : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-guinda-300'"
                            class="px-3 py-1 text-xs font-medium rounded-full border transition-colors">
                            {{ cat }}
                        </button>
                    </div>
                </div>

                <!-- Scroll horizontal -->
                <div v-if="proveedoresFiltrados.length > 0"
                    class="flex gap-4 overflow-x-auto pb-3 -mx-1 px-1 snap-x scroll-smooth"
                    style="scrollbar-width: thin; scrollbar-color: #e5e7eb transparent;">
                    <Link
                        v-for="p in proveedoresFiltrados" :key="p.id"
                        :href="route('proveedores.show', p.id)"
                        class="snap-start shrink-0 w-52 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 hover:border-guinda-300 dark:hover:border-guinda-800 hover:shadow-md transition-all duration-200 group"
                    >
                        <!-- Logo / Iniciales -->
                        <div class="w-12 h-12 rounded-xl mb-3 flex items-center justify-center shrink-0 overflow-hidden"
                             :class="p.logo_path ? '' : 'bg-guinda-100 dark:bg-guinda-900/30 border border-guinda-200 dark:border-guinda-800'">
                            <img v-if="p.logo_path" :src="`/storage/${p.logo_path}`"
                                class="w-full h-full object-contain" :alt="p.nombre_restaurante" />
                            <span v-else class="text-lg font-black text-guinda-700 dark:text-guinda-400">
                                {{ iniciales(p.nombre_restaurante) }}
                            </span>
                        </div>
                        <p class="font-bold text-gray-900 dark:text-white text-sm leading-tight group-hover:text-guinda-700 dark:group-hover:text-guinda-400 transition-colors line-clamp-2">
                            {{ p.nombre_restaurante }}
                        </p>
                        <p v-if="p.categoria" class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ p.categoria }}</p>
                        <p v-if="p.municipio" class="text-xs text-gray-400 dark:text-gray-600 mt-0.5">{{ p.municipio }}</p>
                        <p v-if="p.descripcion" class="text-xs text-gray-500 dark:text-gray-500 mt-2 line-clamp-2">{{ p.descripcion }}</p>
                        <!-- Badge disponibilidad -->
                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Agendar
                            </span>
                            <span v-if="p.citas_evento_count !== undefined"
                                class="text-xs font-medium px-2 py-0.5 rounded-full"
                                :class="disponibilidadInfo(p).cls">
                                {{ disponibilidadInfo(p).text }}
                            </span>
                        </div>
                    </Link>
                </div>
                <div v-else class="py-8 text-center text-gray-400 dark:text-gray-600 text-sm">
                    No se encontraron proveedores con ese criterio.
                </div>
            </div>

            <!-- ── MIS CITAS ──────────────────────────────────────── -->
            <div>
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 rounded-xl p-1 w-fit mb-5">
                    <button
                        @click="activeTab = 'lista'"
                        :class="activeTab === 'lista'
                            ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200 flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                        </svg>
                        Mis citas
                        <span v-if="citasActivas.length > 0"
                            class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-400">
                            {{ citasActivas.length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'calendario'"
                        :class="activeTab === 'calendario'
                            ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200 flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Calendario
                    </button>
                </div>

                <!-- Vista Lista -->
                <div v-if="activeTab === 'lista'">
                    <div v-if="citas.length === 0"
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-16 text-center shadow-sm transition-colors">
                        <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-700 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                        </svg>
                        <p class="text-gray-600 dark:text-gray-400 font-medium text-lg mb-2">Todavía no tienes citas</p>
                        <p class="text-gray-400 dark:text-gray-600 text-sm mb-6">Explora los proveedores disponibles y agenda tu primera reunión.</p>
                        <Link :href="route('proveedores.index')"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-guinda-800 hover:bg-guinda-700 text-white font-semibold rounded-xl transition-colors text-sm shadow-sm">
                            Ver proveedores
                        </Link>
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="cita in citas" :key="cita.id"
                            class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm transition-colors"
                            :class="{ 'opacity-60': ['cancelada','rechazada'].includes(cita.estado) }"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                <!-- Icono estado -->
                                <div class="shrink-0 w-11 h-11 rounded-xl flex items-center justify-center border"
                                     :class="cita.estado === 'confirmada' ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800'
                                           : cita.estado === 'completada' ? 'bg-indigo-50 dark:bg-indigo-900/20 border-indigo-200 dark:border-indigo-800'
                                           : 'bg-guinda-50 dark:bg-guinda-900/20 border-guinda-200 dark:border-guinda-800'">
                                    <svg class="w-5 h-5"
                                        :class="cita.estado === 'confirmada' ? 'text-emerald-600 dark:text-emerald-400'
                                              : cita.estado === 'completada' ? 'text-indigo-600 dark:text-indigo-400'
                                              : 'text-guinda-700 dark:text-guinda-400'"
                                        fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                                    </svg>
                                </div>

                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap mb-0.5">
                                        <h4 class="font-bold text-gray-900 dark:text-white truncate">
                                            {{ cita.restaurantero?.nombre_restaurante || 'Proveedor' }}
                                        </h4>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
                                            :class="estadoConfig[cita.estado]?.class || 'bg-gray-500/15 text-gray-400'">
                                            {{ estadoConfig[cita.estado]?.label || cita.estado }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mt-0.5">
                                        <svg class="w-3.5 h-3.5 shrink-0 text-guinda-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="capitalize">{{ formatFecha(cita.inicio) }}</span>
                                    </p>
                                    <!-- Notas (modo lectura) -->
                                    <p v-if="cita.notas && citaEditandoNota !== cita.id" class="text-xs text-gray-400 dark:text-gray-600 mt-1 truncate italic">
                                        "{{ cita.notas }}"
                                    </p>
                                    <!-- Notas (modo edición inline) -->
                                    <div v-if="citaEditandoNota === cita.id" class="mt-2">
                                        <textarea
                                            v-model="notaEdicion"
                                            rows="2"
                                            placeholder="Escribe notas para esta cita (qué quieres discutir, qué mostrar...)"
                                            class="w-full text-xs bg-gray-50 dark:bg-gray-800 border border-guinda-300 dark:border-guinda-700 rounded-lg px-3 py-2 text-gray-700 dark:text-gray-300 focus:outline-none focus:border-guinda-500 resize-none"
                                        ></textarea>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <button @click="guardarNota(cita.id)"
                                                class="px-3 py-1 bg-guinda-800 hover:bg-guinda-700 text-white text-xs font-bold rounded-lg transition-colors">
                                                Guardar
                                            </button>
                                            <button @click="cancelarEditarNota"
                                                class="px-3 py-1 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs font-medium rounded-lg transition-colors">
                                                Cancelar
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div class="shrink-0 flex items-center gap-2 flex-wrap">
                                    <Link :href="route('proveedores.show', cita.restaurantero?.id)"
                                        class="text-xs font-medium px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl transition-colors">
                                        Ver proveedor
                                    </Link>
                                    <button
                                        v-if="!['cancelada','rechazada','completada'].includes(cita.estado) && citaEditandoNota !== cita.id"
                                        @click="abrirEditarNota(cita)"
                                        class="text-xs font-medium px-3 py-1.5 bg-guinda-50 dark:bg-guinda-950/20 hover:bg-guinda-100 dark:hover:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 rounded-xl transition-colors border border-guinda-200 dark:border-guinda-900/40">
                                        Editar notas
                                    </button>
                                    <button v-if="puedesCancelar(cita.estado)"
                                        @click="cancelarCita(cita.id)"
                                        class="text-xs font-medium px-3 py-1.5 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 rounded-xl transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vista Calendario -->
                <div v-if="activeTab === 'calendario'" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm transition-colors">
                    <div v-if="citas.length === 0" class="text-center py-12 text-gray-400 dark:text-gray-600">
                        <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                        </svg>
                        <p class="text-sm font-medium">No hay citas para mostrar en el calendario</p>
                    </div>
                    <FullCalendar v-else :options="calendarOptions" />
                </div>
            </div>

            <!-- ── HISTORIAL EDICIONES ANTERIORES ────────────────── -->
            <div v-if="edicionesHistorial && edicionesHistorial.length > 0" class="mt-2">
                <button
                    @click="historialAbierto = !historialAbierto"
                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 font-medium transition-colors w-full text-left"
                >
                    <svg class="w-4 h-4 transition-transform duration-200" :class="historialAbierto ? 'rotate-90' : ''"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                    Historial de ediciones anteriores ({{ edicionesHistorial.length }})
                </button>

                <div v-if="historialAbierto" class="mt-4 space-y-4">
                    <div v-for="ed in edicionesHistorial" :key="ed.id"
                        class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 transition-colors">
                        <div class="flex items-center gap-2 mb-3">
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300 text-sm">{{ ed.nombre }}</h4>
                            <span v-if="ed.sector" class="text-xs text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">{{ ed.sector }}</span>
                        </div>
                        <div class="space-y-2">
                            <div v-for="cita in ed.mis_citas" :key="cita.id" class="flex items-center gap-3 text-sm">
                                <div class="w-2 h-2 rounded-full shrink-0"
                                    :class="{
                                        'bg-guinda-500': cita.estado === 'pendiente',
                                        'bg-emerald-500': cita.estado === 'confirmada',
                                        'bg-indigo-500': cita.estado === 'completada',
                                        'bg-gray-400': cita.estado === 'cancelada',
                                    }"></div>
                                <span class="text-gray-700 dark:text-gray-300 font-medium truncate">
                                    {{ cita.restaurantero?.nombre_restaurante || 'Proveedor' }}
                                </span>
                                <span class="text-gray-400 dark:text-gray-600 text-xs ml-auto shrink-0">
                                    {{ formatFecha(cita.inicio) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            </template><!-- /mainTab === 'citas' -->
        </div><!-- /flex-1 -->
        </div><!-- /flex -->
        </div><!-- /max-w-7xl -->

        <!-- Banner: invitación a ser proveedor -->
        <div v-if="!$page.props.auth.user?.is_restaurantero"
            class="mx-4 sm:mx-6 lg:mx-8 mt-6 mb-8 max-w-5xl lg:mx-auto bg-gradient-to-r from-guinda-900/80 to-guinda-800/60 border border-guinda-700/50 rounded-2xl p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
            <div class="text-3xl">🏪</div>
            <div class="flex-1">
                <h3 class="font-bold text-white mb-1">¿También vendes productos o servicios?</h3>
                <p class="text-gray-300 text-sm">Regístrate como proveedor y participa en los eventos como expositor. Recibe compradores interesados en lo que ofreces.</p>
            </div>
            <button
                @click="router.post(route('perfil.agregar-rol'), { rol: 'proveedor' })"
                class="shrink-0 px-5 py-2.5 bg-white text-guinda-800 font-bold text-sm rounded-xl hover:bg-guinda-50 transition-colors shadow-sm">
                Quiero ser Proveedor
            </button>
        </div>

    </AppLayout>
</template>

<style>
.fc { --fc-border-color: #e5e7eb; --fc-today-bg-color: rgba(139,16,40,0.05); --fc-neutral-text-color: #111827; --fc-page-bg-color: #ffffff; --fc-event-text-color: #ffffff; }
.fc-theme-standard td, .fc-theme-standard th, .fc-scrollgrid { border-color: #e5e7eb !important; }
.fc-col-header-cell-cushion { color: #111827 !important; font-weight: 700 !important; text-decoration: none !important; }
.fc-daygrid-day-number { color: #111827 !important; font-weight: 600 !important; text-decoration: none !important; }
.fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: #8b1028 !important; }
.fc-toolbar-title { font-size: 1rem !important; font-weight: 700 !important; color: #111827 !important; }
.fc-button { background: #f3f4f6 !important; border-color: #d1d5db !important; color: #111827 !important; font-size: 0.75rem !important; font-weight: 600 !important; }
.fc-button:hover, .fc-button-active { background: #8b1028 !important; border-color: #710d21 !important; color: #ffffff !important; }
.fc-button:focus { box-shadow: 0 0 0 2px rgba(139,16,40,0.3) !important; }
/* Dot events (mes): fondo transparente → texto oscuro */
.fc-daygrid-dot-event .fc-event-title { color: #111827 !important; font-weight: 600 !important; }
.fc-daygrid-dot-event .fc-event-time  { color: #6b7280 !important; font-weight: 500 !important; }
/* Block/timegrid events: tienen fondo de color → texto blanco */
.fc-daygrid-block-event .fc-event-title,
.fc-daygrid-block-event .fc-event-time,
.fc-timegrid-event .fc-event-title,
.fc-timegrid-event .fc-event-time { color: #ffffff !important; font-weight: 600 !important; }
/* List view */
.fc-list-event-title a, .fc-list-event-time { color: #111827 !important; }
.fc-timegrid-slot-label-cushion, .fc-timegrid-axis-cushion { color: #374151 !important; font-weight: 500 !important; }
.fc-daygrid-more-link { color: #8b1028 !important; font-weight: 700 !important; }

.dark .fc { --fc-border-color: #1f2937; --fc-today-bg-color: rgba(139,16,40,0.10); --fc-neutral-text-color: #f9fafb; --fc-page-bg-color: #111827; --fc-event-text-color: #ffffff; }
.dark .fc-theme-standard td, .dark .fc-theme-standard th, .dark .fc-scrollgrid { border-color: #1f2937 !important; }
.dark .fc-col-header-cell-cushion { color: #f9fafb !important; font-weight: 700 !important; text-decoration: none !important; }
.dark .fc-daygrid-day-number { color: #e5e7eb !important; font-weight: 600 !important; text-decoration: none !important; }
.dark .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: #f87171 !important; }
.dark .fc-toolbar-title { color: #f9fafb !important; font-weight: 700 !important; }
.dark .fc-button { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; font-weight: 600 !important; }
.dark .fc-button:hover, .dark .fc-button-active { background: #8b1028 !important; border-color: #710d21 !important; color: #ffffff !important; }
.dark .fc-timegrid-slot-label-cushion, .dark .fc-timegrid-axis-cushion { color: #9ca3af !important; }
.dark .fc-daygrid-more-link { color: #f87171 !important; font-weight: 700 !important; }
</style>
