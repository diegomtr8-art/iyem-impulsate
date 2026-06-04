<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import timeGridPlugin from '@fullcalendar/timegrid';
import dayGridPlugin from '@fullcalendar/daygrid';

const props = defineProps({
    restaurantero: Object,
    citasHoy: Number,
    citasSemana: Number,
    citasPendientes: Number,
    proximasCitas: Array,
});

const estadoConfig = {
    pendiente:  { label: 'Pendiente',  class: 'bg-guinda-500/15 text-guinda-600 dark:text-guinda-400 border-guinda-500/20' },
    confirmada: { label: 'Confirmada', class: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' },
    cancelada:  { label: 'Cancelada',  class: 'bg-gray-500/15 text-gray-500 dark:text-gray-400 border-gray-500/20' },
    completada: { label: 'Completada', class: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/20' },
};

const calendarOptions = ref({
    plugins: [timeGridPlugin, dayGridPlugin],
    initialView: 'timeGridWeek',
    locale: 'es',
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'timeGridWeek,dayGridMonth',
    },
    slotMinTime: '09:00:00',
    slotMaxTime: '16:00:00',
    hiddenDays: [0, 6],
    slotDuration: '00:30:00',
    height: 500,
    events: '/restaurantero/panel/eventos',
    eventColor: '#8b1028',
    nowIndicator: true,
    businessHours: {
        daysOfWeek: [1, 2, 3, 4, 5],
        startTime: '09:00',
        endTime: '16:00',
    },
});

const kpis = [
    { label: 'Citas hoy',   value: props.citasHoy,       icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z', bg: 'bg-sky-50 dark:bg-sky-500/10 border-sky-200 dark:border-sky-500/20', iconColor: 'text-sky-600 dark:text-sky-400', valColor: 'text-sky-700 dark:text-sky-300' },
    { label: 'Esta semana', value: props.citasSemana,    icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',                                               bg: 'bg-guinda-50 dark:bg-guinda-500/10 border-guinda-200 dark:border-guinda-500/20', iconColor: 'text-guinda-700 dark:text-guinda-400', valColor: 'text-guinda-700 dark:text-guinda-300' },
    { label: 'Pendientes',  value: props.citasPendientes, icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z', bg: 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/20', iconColor: 'text-amber-600 dark:text-amber-400', valColor: 'text-amber-700 dark:text-amber-300' },
];

const formatFecha = (f) => f ? new Date(f).toLocaleString('es-MX', { weekday:'short', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '—';
</script>

<template>
    <AppLayout title="Mi Panel">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Panel Informativo</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">{{ restaurantero.nombre_restaurante }} — solo lectura</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 text-xs font-semibold rounded-full border border-guinda-200 dark:border-guinda-800">
                    <span class="w-2 h-2 rounded-full bg-guinda-600 dark:bg-guinda-500 animate-pulse"></span>
                    Proveedor
                </span>
            </div>
        </template>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

            <!-- Info banner -->
            <div class="bg-guinda-50 dark:bg-guinda-950/30 border border-guinda-200 dark:border-guinda-900 rounded-xl px-5 py-3 flex items-center gap-3 transition-colors">
                <svg class="w-4 h-4 text-guinda-700 dark:text-guinda-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-sm text-guinda-700 dark:text-guinda-400">
                    Este panel es informativo. Aqui puedes ver tus citas agendadas pero no puedes modificarlas.
                </p>
            </div>

            <!-- KPI Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div v-for="(kpi, i) in kpis" :key="kpi.label"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-sm hover:shadow-md transition-all duration-300 hover:scale-[1.02]"
                    :style="{ animationDelay: i * 0.1 + 's' }">
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

            <!-- Calendario FullCalendar -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm transition-colors">
                <h3 class="font-bold text-gray-900 dark:text-white mb-5">Calendario de citas (solo vista)</h3>
                <FullCalendar :options="calendarOptions" />
            </div>

            <!-- Proximas citas (solo lectura) -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm transition-colors">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/30">
                    <h3 class="font-bold text-gray-900 dark:text-white">Proximas citas</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Informacion de tus citas agendadas</p>
                </div>

                <div v-if="proximasCitas.length === 0" class="text-center py-16 text-gray-400 dark:text-gray-600">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5" />
                    </svg>
                    <p class="font-medium">No tienes citas proximas</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left py-3 px-6 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Cliente</th>
                                <th class="text-left py-3 px-6 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden md:table-cell">Email</th>
                                <th class="text-left py-3 px-6 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden lg:table-cell">Telefono</th>
                                <th class="text-left py-3 px-6 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Fecha/Hora</th>
                                <th class="text-left py-3 px-6 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cita in proximasCitas" :key="cita.id"
                                class="border-b border-gray-100 dark:border-gray-800/40 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="py-3 px-6 font-medium text-gray-800 dark:text-gray-200">{{ cita.cliente?.nombre || cita.cliente?.name || '—' }}</td>
                                <td class="py-3 px-6 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ cita.cliente?.email }}</td>
                                <td class="py-3 px-6 text-gray-500 dark:text-gray-400 hidden lg:table-cell">{{ cita.cliente?.phone || '—' }}</td>
                                <td class="py-3 px-6 text-gray-500 dark:text-gray-400 whitespace-nowrap text-xs">{{ formatFecha(cita.inicio) }}</td>
                                <td class="py-3 px-6">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
                                          :class="estadoConfig[cita.estado]?.class || 'bg-gray-500/15 text-gray-400'">
                                        {{ estadoConfig[cita.estado]?.label || cita.estado }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

<style>
.fc { --fc-border-color: #e5e7eb; --fc-today-bg-color: rgba(139,16,40,0.04); }
.dark .fc { --fc-border-color: #1f2937; --fc-today-bg-color: rgba(139,16,40,0.08); }
.fc-theme-standard td, .fc-theme-standard th { border-color: #e5e7eb; }
.dark .fc-theme-standard td, .dark .fc-theme-standard th { border-color: #1f2937; }
.fc-col-header-cell-cushion, .fc-timegrid-axis-cushion, .fc-timegrid-slot-label-cushion { color: #6b7280; }
.fc-toolbar-title { font-size: 1rem !important; font-weight: 700 !important; color: #111827 !important; }
.dark .fc-toolbar-title { color: #f9fafb !important; }
.fc-button { background: #f3f4f6 !important; border-color: #d1d5db !important; color: #374151 !important; font-size: 0.75rem !important; }
.dark .fc-button { background: #374151 !important; border-color: #4b5563 !important; color: #d1d5db !important; }
.fc-button-active, .fc-button:hover { background: #8b1028 !important; border-color: #710d21 !important; color: #fff !important; }
.fc-event-title, .fc-event-time { color: #fff !important; }
.fc-scrollgrid { border-color: #e5e7eb !important; }
.dark .fc-scrollgrid { border-color: #1f2937 !important; }
</style>
