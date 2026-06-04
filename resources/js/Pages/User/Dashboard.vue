<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';

const props = defineProps({
    citas: Array,
    citasCount: Number,
    edicion: Object,
    edicionesHistorial: Array,
});

const historialAbierto = ref(false);

const MAX_CITAS = 12;
const activeTab = ref('lista');

const estadoConfig = {
    pendiente:  { label: 'Pendiente',  class: 'bg-guinda-500/15 text-guinda-600 dark:text-guinda-400 border-guinda-500/20' },
    confirmada: { label: 'Confirmada', class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' },
    cancelada:  { label: 'Cancelada',  class: 'bg-gray-500/15 text-gray-500 dark:text-gray-400 border-gray-500/20' },
    completada: { label: 'Completada', class: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/20' },
};

const estadoColors = {
    pendiente:  '#8b1028',
    confirmada: '#10b981',
    cancelada:  '#6b7280',
    completada: '#6366f1',
};

const porcentaje = Math.min((props.citasCount / MAX_CITAS) * 100, 100);

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

const puedesCancelar = (estado) => !['completada', 'cancelada'].includes(estado);

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
    const upcoming = props.citas.filter(c => new Date(c.inicio) >= now);
    if (!upcoming.length) return null;
    return upcoming.reduce((min, c) =>
        new Date(c.inicio) < new Date(min.inicio) ? c : min
    );
});

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin],
    initialView: 'dayGridMonth',
    locale: 'es',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek',
    },
    initialDate: proximaCita.value ? proximaCita.value.inicio : undefined,
    events: calendarEvents.value,
    height: 'auto',
    slotMinTime: '09:00:00',
    slotMaxTime: '16:00:00',
    hiddenDays: [0, 6],
    nowIndicator: true,
    businessHours: {
        daysOfWeek: [1, 2, 3, 4, 5],
        startTime: '09:00',
        endTime: '16:00',
    },
}));
</script>

<template>
    <AppLayout title="Mis Citas">
        <template #header>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Mis Citas</h1>
            <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Gestiona tus citas de networking</p>
        </template>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
            <!-- Progress de citas -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm transition-colors">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">Citas utilizadas</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            <span v-if="edicion">{{ edicion.nombre }}</span>
                            <span v-else class="text-amber-500">Sin edición activa</span>
                            · Máximo 12 citas
                        </p>
                    </div>
                    <span class="text-2xl font-black" :class="citasCount >= 12 ? 'text-red-500 dark:text-red-400' : 'text-guinda-700 dark:text-guinda-400'">
                        {{ citasCount }}<span class="text-sm font-normal text-gray-400">/12</span>
                    </span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-800 rounded-full h-2.5 overflow-hidden">
                    <div
                        class="h-2.5 rounded-full transition-all duration-500"
                        :class="citasCount >= 12 ? 'bg-red-500' : citasCount >= 9 ? 'bg-guinda-600' : 'bg-emerald-500'"
                        :style="{ width: porcentaje + '%' }"
                    ></div>
                </div>
                <div v-if="citasCount < 12" class="mt-4">
                    <Link :href="route('restauranteros.index')"
                        class="inline-flex items-center gap-2 text-sm text-guinda-700 dark:text-guinda-400 hover:text-guinda-600 dark:hover:text-guinda-300 font-medium transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Agendar nueva cita
                    </Link>
                </div>
            </div>

            <!-- Info oficina -->
            <div class="bg-guinda-50 dark:bg-guinda-950/30 border border-guinda-200 dark:border-guinda-900 rounded-2xl p-5 transition-colors">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-guinda-100 dark:bg-guinda-900/40 border border-guinda-200 dark:border-guinda-800 flex items-center justify-center shrink-0 mt-0.5">
                        <svg class="w-4 h-4 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="font-semibold text-guinda-800 dark:text-guinda-300 text-sm">Tu cita será en nuestra oficina</p>
                        <p class="text-guinda-700 dark:text-guinda-400/80 text-sm mt-1">Av. Industrias No Contaminantes Tab 13613, Col. Sodzil Norte, C.P. 97110, Mérida, Yucatán</p>
                        <p class="text-guinda-600/70 dark:text-guinda-500/60 text-xs mt-1">Encuentro de Negocios Impulsate · Lunes a Viernes 9:00 am — 4:00 pm</p>
                    </div>
                </div>
            </div>

            <!-- Tab toggle Lista / Calendario -->
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
                        Lista
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
                    <!-- Empty state -->
                    <div v-if="citas.length === 0" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-16 text-center shadow-sm transition-colors">
                        <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-700 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                        </svg>
                        <p class="text-gray-600 dark:text-gray-400 font-medium text-lg mb-2">Todavía no tienes citas</p>
                        <p class="text-gray-400 dark:text-gray-600 text-sm mb-6">Explora nuestros proveedores y agenda tu primera reunión.</p>
                        <Link :href="route('restauranteros.index')"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-guinda-800 hover:bg-guinda-700 text-white font-semibold rounded-xl transition-colors text-sm shadow-sm">
                            Ver proveedores
                        </Link>
                    </div>

                    <!-- Citas list -->
                    <div v-else class="space-y-4">
                        <div
                            v-for="cita in citas"
                            :key="cita.id"
                            class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center gap-4 hover:border-guinda-300 dark:hover:border-gray-700 transition-colors shadow-sm"
                        >
                            <div class="shrink-0 w-12 h-12 bg-guinda-50 dark:bg-guinda-900/20 border border-guinda-200 dark:border-guinda-800 rounded-xl flex items-center justify-center">
                                <svg class="w-6 h-6 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                </svg>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <h4 class="font-semibold text-gray-900 dark:text-white truncate">
                                        {{ cita.restaurantero?.nombre_restaurante || 'Proveedor' }}
                                    </h4>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
                                        :class="estadoConfig[cita.estado]?.class || 'bg-gray-500/15 text-gray-400'"
                                    >
                                        {{ estadoConfig[cita.estado]?.label || cita.estado }}
                                    </span>
                                </div>

                                <p class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 shrink-0 text-guinda-500 dark:text-guinda-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    <span class="capitalize">{{ formatFecha(cita.inicio) }}</span>
                                </p>

                                <p v-if="cita.notas" class="text-xs text-gray-400 dark:text-gray-600 mt-1 truncate">
                                    {{ cita.notas }}
                                </p>

                                <div v-if="puedesCancelar(cita.estado)" class="mt-2">
                                    <button @click="cancelarCita(cita.id)"
                                        class="text-xs text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 font-medium transition-colors inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        Cancelar cita
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

            <!-- Historial de ediciones anteriores -->
            <div v-if="edicionesHistorial && edicionesHistorial.length > 0" class="mt-6">
                <button
                    @click="historialAbierto = !historialAbierto"
                    class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 font-medium transition-colors w-full text-left"
                >
                    <svg
                        class="w-4 h-4 transition-transform duration-200"
                        :class="historialAbierto ? 'rotate-90' : ''"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                    Historial de ediciones anteriores ({{ edicionesHistorial.length }})
                </button>

                <div v-if="historialAbierto" class="mt-4 space-y-5">
                    <div v-for="ed in edicionesHistorial" :key="ed.id"
                        class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 transition-colors">
                        <div class="flex items-center gap-2 mb-4">
                            <svg class="w-4 h-4 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                            <h4 class="font-semibold text-gray-700 dark:text-gray-300 text-sm">{{ ed.nombre }}</h4>
                            <span v-if="ed.sector" class="text-xs text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">{{ ed.sector }}</span>
                        </div>

                        <div class="space-y-3">
                            <div v-for="cita in ed.mis_citas" :key="cita.id"
                                class="flex items-center gap-3 text-sm">
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

        </div>
    </AppLayout>
</template>

<style>
/* ── LIGHT MODE ─────────────────────────────────────────── */
.fc {
    --fc-border-color: #e5e7eb;
    --fc-today-bg-color: rgba(139,16,40,0.05);
    --fc-neutral-text-color: #111827;
    --fc-page-bg-color: #ffffff;
    --fc-event-text-color: #ffffff;
}
/* Bordes */
.fc-theme-standard td,
.fc-theme-standard th,
.fc-scrollgrid { border-color: #e5e7eb !important; }

/* Encabezados de columna (Lun, Mar…) */
.fc-col-header-cell-cushion {
    color: #111827 !important;
    font-weight: 700 !important;
    text-decoration: none !important;
}
/* Números de día */
.fc-daygrid-day-number {
    color: #111827 !important;
    font-weight: 600 !important;
    text-decoration: none !important;
}
/* "Hoy" resaltado */
.fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: #8b1028 !important; }

/* Título del toolbar (mes actual) */
.fc-toolbar-title {
    font-size: 1rem !important;
    font-weight: 700 !important;
    color: #111827 !important;
}

/* Botones prev/next/today/mes/semana */
.fc-button {
    background: #f3f4f6 !important;
    border-color: #d1d5db !important;
    color: #111827 !important;
    font-size: 0.75rem !important;
    font-weight: 600 !important;
}
.fc-button:hover,
.fc-button-active {
    background: #8b1028 !important;
    border-color: #710d21 !important;
    color: #ffffff !important;
}
.fc-button:focus { box-shadow: 0 0 0 2px rgba(139,16,40,0.3) !important; }

/* Texto de eventos */
.fc-event-title,
.fc-event-time { color: #ffffff !important; font-weight: 600 !important; }

/* Etiquetas de horas en timeGrid */
.fc-timegrid-slot-label-cushion,
.fc-timegrid-axis-cushion { color: #374151 !important; font-weight: 500 !important; }

/* Más eventos (+N more) */
.fc-daygrid-more-link { color: #8b1028 !important; font-weight: 700 !important; }

/* ── DARK MODE ──────────────────────────────────────────── */
.dark .fc {
    --fc-border-color: #1f2937;
    --fc-today-bg-color: rgba(139,16,40,0.10);
    --fc-neutral-text-color: #f9fafb;
    --fc-page-bg-color: #111827;
    --fc-event-text-color: #ffffff;
}
.dark .fc-theme-standard td,
.dark .fc-theme-standard th,
.dark .fc-scrollgrid { border-color: #1f2937 !important; }

.dark .fc-col-header-cell-cushion {
    color: #f9fafb !important;
    font-weight: 700 !important;
    text-decoration: none !important;
}
.dark .fc-daygrid-day-number {
    color: #e5e7eb !important;
    font-weight: 600 !important;
    text-decoration: none !important;
}
.dark .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: #f87171 !important; }

.dark .fc-toolbar-title { color: #f9fafb !important; font-weight: 700 !important; }

.dark .fc-button {
    background: #374151 !important;
    border-color: #4b5563 !important;
    color: #e5e7eb !important;
    font-weight: 600 !important;
}
.dark .fc-button:hover,
.dark .fc-button-active {
    background: #8b1028 !important;
    border-color: #710d21 !important;
    color: #ffffff !important;
}

.dark .fc-timegrid-slot-label-cushion,
.dark .fc-timegrid-axis-cushion { color: #9ca3af !important; }

.dark .fc-daygrid-more-link { color: #f87171 !important; font-weight: 700 !important; }
</style>
