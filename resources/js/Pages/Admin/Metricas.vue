<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { computed } from 'vue';

const props = defineProps({
    sinDatos:         Boolean,
    totalVisitas:     Number,
    visitasHoy:       Number,
    visitasSemana:    Number,
    visitasPorDia:    Array,
    visitasPorPagina: Array,
    porDispositivo:   Array,
    proveedoresTop:   Array,
});

const darkBase = { background: 'transparent', foreColor: '#9ca3af', toolbar: { show: false } };

const lineOptions = computed(() => ({
    chart: { ...darkBase, type: 'area' },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.01 } },
    colors: ['#8b1028'],
    xaxis: {
        categories: (props.visitasPorDia || []).map(d => d.fecha),
        labels: { style: { colors: '#6b7280' } },
        axisBorder: { color: '#374151' },
    },
    yaxis: { labels: { style: { colors: '#6b7280' } } },
    grid: { borderColor: '#1f2937' },
    theme: { mode: 'dark' },
    tooltip: { theme: 'dark' },
}));

const barOptions = computed(() => ({
    chart: { ...darkBase, type: 'bar' },
    colors: ['#8b1028'],
    xaxis: {
        categories: (props.proveedoresTop || []).map(d => d.nombre),
        labels: { style: { colors: '#6b7280' }, trim: true, maxHeight: 80 },
        axisBorder: { color: '#374151' },
    },
    yaxis: { labels: { style: { colors: '#6b7280' } } },
    grid: { borderColor: '#1f2937' },
    plotOptions: { bar: { borderRadius: 6, horizontal: true } },
    theme: { mode: 'dark' },
    tooltip: { theme: 'dark' },
    dataLabels: { enabled: false },
}));

const maxVisitas = computed(() => {
    if (!props.proveedoresTop?.length) return 1;
    return Math.max(...props.proveedoresTop.map(p => p.total));
});
</script>

<template>
    <AdminLayout title="Métricas">
        <template #header>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Métricas</h1>
            <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Analítica de visitas al sitio</p>
        </template>

        <div class="space-y-6">
            <!-- KPI cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-sm dark:shadow-none transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500">Total visitas</p>
                        <p class="text-2xl font-black text-guinda-700 dark:text-guinda-400">{{ totalVisitas ?? 0 }}</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-sm dark:shadow-none transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-emerald-700 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-6.07-.71.71M5.64 18.36l-.71.71M18.36 18.36l.71.71M5.64 5.64l-.71-.71M12 7a5 5 0 100 10A5 5 0 0012 7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500">Visitas hoy</p>
                        <p class="text-2xl font-black text-emerald-700 dark:text-emerald-400">{{ visitasHoy ?? 0 }}</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-sm dark:shadow-none transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 border border-indigo-200 dark:border-indigo-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-indigo-700 dark:text-indigo-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500">Esta semana</p>
                        <p class="text-2xl font-black text-indigo-700 dark:text-indigo-400">{{ visitasSemana ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <!-- Sin datos -->
            <div v-if="sinDatos" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-12 text-center shadow-sm dark:shadow-none">
                <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-700 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/>
                </svg>
                <p class="text-gray-500 dark:text-gray-500 font-medium">Los datos empezarán a aparecer cuando los usuarios visiten los perfiles de proveedores.</p>
            </div>

            <template v-else>
                <!-- Gráfica visitas por día -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-1">Visitas por día</h3>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mb-4">Últimos 30 días — visitas a perfiles de proveedores</p>
                    <VueApexCharts v-if="visitasPorDia?.length" type="area" height="220" :options="lineOptions"
                        :series="[{ name: 'Visitas', data: visitasPorDia.map(d => d.total) }]" />
                    <div v-else class="h-[220px] flex items-center justify-center text-gray-400 dark:text-gray-600 text-sm">Sin datos aún</div>
                </div>

                <!-- Proveedores más visitados -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="font-bold text-gray-900 dark:text-white">Proveedores más visitados</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Ranking por número de visitas a su perfil</p>
                    </div>

                    <div v-if="proveedoresTop?.length" class="divide-y divide-gray-100 dark:divide-gray-800">
                        <div v-for="(p, i) in proveedoresTop" :key="p.id"
                            class="flex items-center gap-4 px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">

                            <!-- Rank -->
                            <div class="w-7 text-center shrink-0">
                                <span v-if="i === 0" class="text-lg">🥇</span>
                                <span v-else-if="i === 1" class="text-lg">🥈</span>
                                <span v-else-if="i === 2" class="text-lg">🥉</span>
                                <span v-else class="text-sm font-bold text-gray-400 dark:text-gray-600">{{ i + 1 }}</span>
                            </div>

                            <!-- Logo -->
                            <div class="w-9 h-9 rounded-lg bg-gray-100 dark:bg-gray-800 overflow-hidden shrink-0 flex items-center justify-center">
                                <img v-if="p.logo_path" :src="'/storage/' + p.logo_path" :alt="p.nombre" class="w-full h-full object-cover" />
                                <svg v-else class="w-5 h-5 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                                </svg>
                            </div>

                            <!-- Nombre + barra -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ p.nombre }}</p>
                                <div class="mt-1.5 h-1.5 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                    <div class="h-full bg-guinda-600 dark:bg-guinda-500 rounded-full transition-all duration-500"
                                        :style="{ width: ((p.total / maxVisitas) * 100) + '%' }"></div>
                                </div>
                            </div>

                            <!-- Contador -->
                            <div class="text-right shrink-0">
                                <span class="text-guinda-700 dark:text-guinda-400 font-black text-lg">{{ p.total }}</span>
                                <p class="text-xs text-gray-400 dark:text-gray-600">visitas</p>
                            </div>
                        </div>
                    </div>

                    <div v-else class="px-6 py-12 text-center text-gray-400 dark:text-gray-600 text-sm">
                        Sin visitas registradas aún. Los datos aparecerán cuando los usuarios abran perfiles de proveedores.
                    </div>
                </div>

                <!-- Dispositivos -->
                <div v-if="porDispositivo?.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors">
                    <h3 class="font-bold text-gray-900 dark:text-white mb-4">Visitas por dispositivo</h3>
                    <div class="flex flex-wrap gap-4">
                        <div v-for="d in porDispositivo" :key="d.device_type"
                            class="flex items-center gap-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl px-4 py-3 border border-gray-200 dark:border-gray-700">
                            <span class="text-xl">{{ d.device_type === 'mobile' ? '📱' : d.device_type === 'tablet' ? '📟' : '💻' }}</span>
                            <div>
                                <p class="text-xs text-gray-500 dark:text-gray-500 capitalize">{{ d.device_type }}</p>
                                <p class="font-bold text-gray-900 dark:text-white">{{ d.total }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AdminLayout>
</template>
