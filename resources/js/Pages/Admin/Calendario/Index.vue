<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';

const props = defineProps({
    restauranteros: Array,
});

const restauranteroFiltro = ref('');
const eventoSeleccionado = ref(null);
const modalVisible = ref(false);

const leyenda = [
    { color: '#8b1028', label: 'Pendiente' },
    { color: '#22c55e', label: 'Confirmada' },
    { color: '#6366f1', label: 'Completada' },
    { color: '#ef4444', label: 'Cancelada' },
];

const calendarOptions = ref({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: esLocale,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    height: 'auto',
    events: fetchEvents,
    eventClick: handleEventClick,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
});

function fetchEvents(info, successCallback, failureCallback) {
    const params = new URLSearchParams({ start: info.startStr, end: info.endStr });
    if (restauranteroFiltro.value) params.append('restaurantero_id', restauranteroFiltro.value);

    fetch(`/admin/calendario/eventos?${params.toString()}`)
        .then(res => res.json())
        .then(data => successCallback(data))
        .catch(() => failureCallback());
}

function handleEventClick(info) {
    eventoSeleccionado.value = {
        title: info.event.title,
        start: info.event.start,
        end: info.event.end,
        ...info.event.extendedProps,
    };
    modalVisible.value = true;
}

const calendarRef = ref(null);

function aplicarFiltro() {
    calendarRef.value?.getApi()?.refetchEvents();
}

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleString('es-MX', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
};

const estadoConfig = {
    pendiente:  { label: 'Pendiente',  class: 'bg-guinda-500/15 text-guinda-600 dark:text-guinda-400 border-guinda-500/20' },
    confirmada: { label: 'Confirmada', class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' },
    cancelada:  { label: 'Cancelada',  class: 'bg-gray-500/15 text-gray-500 dark:text-gray-400 border-gray-500/20' },
    completada: { label: 'Completada', class: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/20' },
};
</script>

<template>
    <AdminLayout title="Calendario">
        <template #header>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Calendario de Citas</h1>
            <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Vista consolidada de todas las citas</p>
        </template>

        <!-- Controles -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 mb-6">
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-gray-600 dark:text-gray-400 shrink-0">Proveedor:</label>
                <select v-model="restauranteroFiltro" @change="aplicarFiltro"
                    class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors shadow-sm dark:shadow-none">
                    <option value="">Todos</option>
                    <option v-for="r in restauranteros" :key="r.id" :value="r.id">{{ r.nombre_restaurante }}</option>
                </select>
            </div>

            <div class="flex items-center gap-4 sm:ml-auto flex-wrap">
                <div v-for="item in leyenda" :key="item.label" class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-500">
                    <span class="w-3 h-3 rounded-full shrink-0" :style="{ backgroundColor: item.color }"></span>
                    {{ item.label }}
                </div>
            </div>
        </div>

        <!-- Calendario -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 sm:p-6 shadow-sm dark:shadow-none transition-colors">
            <FullCalendar ref="calendarRef" :options="calendarOptions" />
        </div>

        <!-- Modal de evento -->
        <div v-if="modalVisible" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="modalVisible = false">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-bold text-gray-900 dark:text-white text-lg">Detalle de cita</h3>
                    <button @click="modalVisible = false" class="text-gray-400 dark:text-gray-500 hover:text-gray-700 dark:hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="eventoSeleccionado" class="space-y-4 text-sm">
                    <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium border"
                        :class="estadoConfig[eventoSeleccionado.estado]?.class || 'bg-gray-500/15 text-gray-400 border-gray-500/20'">
                        {{ estadoConfig[eventoSeleccionado.estado]?.label || eventoSeleccionado.estado }}
                    </span>

                    <div class="grid grid-cols-1 gap-3 pt-1">
                        <div>
                            <p class="text-gray-400 dark:text-gray-600 text-xs mb-0.5">Cliente</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ eventoSeleccionado.cliente }}</p>
                            <p v-if="eventoSeleccionado.clienteEmail" class="text-gray-500 dark:text-gray-500 text-xs">{{ eventoSeleccionado.clienteEmail }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-600 text-xs mb-0.5">Proveedor</p>
                            <p class="font-semibold text-gray-900 dark:text-white">{{ eventoSeleccionado.restaurantero }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 dark:text-gray-600 text-xs mb-0.5">Servicio</p>
                            <p class="text-gray-700 dark:text-gray-300">{{ eventoSeleccionado.servicio }}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-gray-400 dark:text-gray-600 text-xs mb-0.5">Inicio</p>
                                <p class="text-gray-700 dark:text-gray-300 text-xs">{{ formatFecha(eventoSeleccionado.start) }}</p>
                            </div>
                            <div>
                                <p class="text-gray-400 dark:text-gray-600 text-xs mb-0.5">Fin</p>
                                <p class="text-gray-700 dark:text-gray-300 text-xs">{{ formatFecha(eventoSeleccionado.end) }}</p>
                            </div>
                        </div>
                        <div v-if="eventoSeleccionado.notas">
                            <p class="text-gray-400 dark:text-gray-600 text-xs mb-0.5">Notas</p>
                            <p class="text-gray-700 dark:text-gray-300">{{ eventoSeleccionado.notas }}</p>
                        </div>
                    </div>
                </div>

                <button @click="modalVisible = false"
                    class="mt-6 w-full py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-xl text-sm transition-colors">
                    Cerrar
                </button>
            </div>
        </div>
    </AdminLayout>
</template>

<style>
/* FullCalendar — Light mode */
.fc {
    --fc-border-color: #e5e7eb;
    --fc-today-bg-color: rgba(139,16,40,0.05);
    --fc-page-bg-color: #ffffff;
    --fc-neutral-bg-color: #f9fafb;
    --fc-neutral-text-color: #111827;
    --fc-list-event-hover-bg-color: #f3f4f6;
    --fc-event-text-color: #ffffff;
    color: #111827;
}
.fc-theme-standard td, .fc-theme-standard th { border-color: #e5e7eb !important; background-color: #ffffff; color: #111827; }
.fc-col-header { background-color: #f9fafb; }
.fc-col-header-cell-cushion { color: #374151 !important; font-weight: 700 !important; text-decoration: none !important; }
.fc-daygrid-day-number { color: #374151 !important; font-weight: 600 !important; text-decoration: none !important; }
.fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: #8b1028 !important; }
.fc-timegrid-axis-cushion, .fc-timegrid-slot-label-cushion { color: #374151 !important; font-weight: 500 !important; }
.fc-toolbar-title { color: #111827 !important; font-size: 1rem !important; font-weight: 700 !important; }
.fc-button { background: #f3f4f6 !important; border-color: #d1d5db !important; color: #374151 !important; font-size: 0.75rem !important; font-weight: 600 !important; }
.fc-button-active, .fc-button:hover { background: #8b1028 !important; border-color: #710d21 !important; color: #fff !important; }
.fc-button:focus { box-shadow: 0 0 0 2px rgba(139,16,40,0.3) !important; }
/* Dot events (mes): fondo transparente → texto oscuro */
.fc-daygrid-dot-event .fc-event-title { color: #111827 !important; font-weight: 600 !important; }
.fc-daygrid-dot-event .fc-event-time  { color: #6b7280 !important; font-weight: 500 !important; }
/* Block/timegrid events: fondo de color → texto blanco */
.fc-daygrid-block-event .fc-event-title,
.fc-daygrid-block-event .fc-event-time,
.fc-timegrid-event .fc-event-title,
.fc-timegrid-event .fc-event-time { color: #ffffff !important; font-weight: 600 !important; }
/* List view */
.fc-list-event-title a, .fc-list-event-time { color: #111827 !important; }
.fc-daygrid-more-link { color: #8b1028 !important; font-weight: 700 !important; }
.fc-scrollgrid { border-color: #e5e7eb !important; }
.fc-day-disabled { background-color: #f9fafb !important; }
.fc-day-disabled .fc-daygrid-day-number { color: #d1d5db !important; }

/* FullCalendar — Dark mode */
.dark .fc {
    --fc-border-color: #1f2937;
    --fc-today-bg-color: rgba(139,16,40,0.10);
    --fc-page-bg-color: #111827;
    --fc-neutral-bg-color: #1f2937;
    --fc-neutral-text-color: #f9fafb;
    --fc-list-event-hover-bg-color: #1f2937;
    --fc-event-text-color: #ffffff;
    color: #e5e7eb;
}
.dark .fc-theme-standard td, .dark .fc-theme-standard th { border-color: #1f2937 !important; background-color: #111827; color: #e5e7eb; }
.dark .fc-col-header { background-color: #1f2937; }
.dark .fc-col-header-cell-cushion { color: #f9fafb !important; font-weight: 700 !important; text-decoration: none !important; }
.dark .fc-daygrid-day-number { color: #e5e7eb !important; font-weight: 600 !important; text-decoration: none !important; }
.dark .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number { color: #f87171 !important; }
.dark .fc-timegrid-axis-cushion, .dark .fc-timegrid-slot-label-cushion { color: #9ca3af !important; }
.dark .fc-toolbar-title { color: #f9fafb !important; font-weight: 700 !important; }
.dark .fc-button { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; font-weight: 600 !important; }
.dark .fc-button-active, .dark .fc-button:hover { background: #8b1028 !important; border-color: #710d21 !important; color: #ffffff !important; }
.dark .fc-event-title, .dark .fc-event-time { color: #ffffff !important; font-weight: 600 !important; }
.dark .fc-daygrid-more-link { color: #f87171 !important; font-weight: 700 !important; }
.dark .fc-scrollgrid { border-color: #1f2937 !important; }
.dark .fc-day-disabled { background-color: #1a2332 !important; }
.dark .fc-day-disabled .fc-daygrid-day-number { color: #374151 !important; }
</style>
