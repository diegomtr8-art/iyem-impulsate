<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    stats: Object,
    citasPorEstado: Object,
    citasUltimos7Dias: Array,
});

const statCards = [
    { label: 'Total Usuarios',     value: props.stats?.totalUsuarios,      color: 'text-sky-400 dark:text-sky-400',    bg: 'bg-sky-50 dark:bg-sky-500/10 border-sky-200 dark:border-sky-500/20',       icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { label: 'Proveedores Activos', value: props.stats?.totalRestauranteros, color: 'text-guinda-700 dark:text-guinda-400', bg: 'bg-guinda-50 dark:bg-guinda-500/10 border-guinda-200 dark:border-guinda-500/20', icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1' },
    { label: 'Total Citas',         value: props.stats?.totalCitas,          color: 'text-violet-600 dark:text-violet-400', bg: 'bg-violet-50 dark:bg-violet-500/10 border-violet-200 dark:border-violet-500/20', icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Citas Hoy',           value: props.stats?.citasHoy,            color: 'text-amber-600 dark:text-amber-400',  bg: 'bg-amber-50 dark:bg-amber-500/10 border-amber-200 dark:border-amber-500/20',  icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
];

const estadoLabels = { pendiente: 'Pendiente', confirmada: 'Confirmada', cancelada: 'Cancelada', completada: 'Completada' };

const isDark = ref(document.documentElement.classList.contains('dark'));

onMounted(() => {
    const observer = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

const chartTheme = computed(() => isDark.value ? 'dark' : 'light');
const axisColor  = computed(() => isDark.value ? '#6b7280' : '#9ca3af');
const gridColor  = computed(() => isDark.value ? '#1f2937' : '#f3f4f6');
const legendColor = computed(() => isDark.value ? '#9ca3af' : '#374151');

const donutSeries = Object.values(props.citasPorEstado || {}).map(Number);
const donutLabels = Object.keys(props.citasPorEstado || {}).map(k => estadoLabels[k] || k);

const donutOptions = computed(() => ({
    chart: { type: 'donut', background: 'transparent' },
    labels: donutLabels,
    colors: ['#8b1028', '#22c55e', '#6b7280', '#6366f1'],
    legend: { position: 'bottom', labels: { colors: legendColor.value } },
    stroke: { colors: ['transparent'] },
    plotOptions: { pie: { donut: { size: '65%' } } },
    theme: { mode: chartTheme.value },
    tooltip: { theme: chartTheme.value },
}));

const lineData = (props.citasUltimos7Dias || []).map(d => d.total);
const lineCategories = (props.citasUltimos7Dias || []).map(d => d.fecha);

const lineOptions = computed(() => ({
    chart: { type: 'area', toolbar: { show: false }, background: 'transparent' },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.25, opacityTo: 0.01, stops: [0, 90, 100] } },
    colors: ['#8b1028'],
    xaxis: { categories: lineCategories, labels: { style: { colors: axisColor.value } }, axisBorder: { color: gridColor.value } },
    yaxis: { labels: { style: { colors: axisColor.value } }, min: 0, forceNiceScale: true },
    grid: { borderColor: gridColor.value },
    theme: { mode: chartTheme.value },
    tooltip: { theme: chartTheme.value },
}));

const tasaConfirmacion = (() => {
    const total = props.stats?.totalCitas || 0;
    const conf  = props.citasPorEstado?.confirmada || 0;
    return total ? Math.round((conf / total) * 100) : 0;
})();
</script>

<template>
    <AdminLayout title="Dashboard">
        <template #header>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
            <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Resumen general del sistema</p>
        </template>

        <div class="space-y-6">
            <!-- KPI Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div v-for="(card, i) in statCards" :key="card.label"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-sm dark:shadow-none hover:shadow-md dark:hover:shadow-none transition-all duration-300 hover:scale-[1.02] animate-fadeInUp"
                    :style="{ animationDelay: i * 0.1 + 's' }">
                    <div :class="['w-11 h-11 rounded-xl border flex items-center justify-center shrink-0', card.bg]">
                        <svg :class="['w-5 h-5', card.color]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="card.icon"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500 font-medium">{{ card.label }}</p>
                        <p :class="['text-3xl font-black', card.color]">{{ card.value ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-1">Citas últimos 7 días</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mb-4">Actividad reciente</p>
                    <VueApexCharts type="area" height="220"
                        :options="lineOptions"
                        :series="[{ name: 'Citas', data: lineData }]"
                        :key="chartTheme" />
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-1">Por estado</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mb-4">Distribución actual</p>
                    <VueApexCharts v-if="donutSeries.length" type="donut" height="220"
                        :options="donutOptions" :series="donutSeries"
                        :key="chartTheme" />
                    <div v-else class="h-[220px] flex items-center justify-center text-gray-400 dark:text-gray-600 text-sm">Sin datos</div>
                </div>
            </div>

            <!-- Bottom stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.4s">
                    <p class="text-xs text-gray-500 dark:text-gray-500 mb-1">Tasa de confirmación</p>
                    <p class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ tasaConfirmacion }}%</p>
                    <div class="mt-3 h-2 bg-gray-200 dark:bg-gray-800 rounded-full overflow-hidden">
                        <div class="h-2 bg-emerald-500 rounded-full" :style="`width:${tasaConfirmacion}%`"></div>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.5s">
                    <p class="text-xs text-gray-500 dark:text-gray-500 mb-1">Pendientes</p>
                    <p class="text-3xl font-black text-guinda-700 dark:text-guinda-400">{{ citasPorEstado?.pendiente ?? 0 }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">Por confirmar</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.6s">
                    <p class="text-xs text-gray-500 dark:text-gray-500 mb-1">Completadas</p>
                    <p class="text-3xl font-black text-indigo-600 dark:text-indigo-400">{{ citasPorEstado?.completada ?? 0 }}</p>
                    <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">Total histórico</p>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fadeInUp {
    animation: fadeInUp 0.5s ease forwards;
    opacity: 0;
}
</style>
