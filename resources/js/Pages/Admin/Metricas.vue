<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import VueApexCharts from 'vue3-apexcharts';
import { router } from '@inertiajs/vue3';
import { computed, ref, onMounted, watch } from 'vue';

const props = defineProps({
    sinDatos:         Boolean,
    totalVisitas:     Number,
    visitasHoy:       Number,
    visitasSemana:    Number,
    visitasPorDia:    Array,
    visitasPorPagina: Array,
    porDispositivo:   Array,
    proveedoresTop:   Array,
    topMunicipiosProveedores:  { type: Array, default: () => [] },
    topMunicipiosCompradores:  { type: Array, default: () => [] },
    topCategoriasProveedores:  { type: Array, default: () => [] },
    topNecesidadesCompradores: { type: Array, default: () => [] },

    generoHombre:       { type: Number, default: 0 },
    generoMujer:        { type: Number, default: 0 },
    generoNoIdentif:    { type: Number, default: 0 },
    generoTotal:        { type: Number, default: 0 },
    proveedorConRFC:    { type: Number, default: 0 },
    proveedorSinRFC:    { type: Number, default: 0 },
    compradorConRFC:    { type: Number, default: 0 },
    compradorSinRFC:    { type: Number, default: 0 },
    rfcTotal:           { type: Number, default: 0 },
    rfcConRFC:          { type: Number, default: 0 },
    rfcSinRFC:          { type: Number, default: 0 },
    noIdentificados:    { type: Array, default: () => [] },
});

const isDark = ref(document.documentElement.classList.contains('dark'));

onMounted(() => {
    const observer = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

const chartTheme  = computed(() => isDark.value ? 'dark' : 'light');
const axisColor   = computed(() => isDark.value ? '#6b7280' : '#9ca3af');
const gridColor   = computed(() => isDark.value ? '#1f2937' : '#f3f4f6');

const lineOptions = computed(() => ({
    chart: { type: 'area', background: 'transparent', toolbar: { show: false } },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { opacityFrom: 0.25, opacityTo: 0.01 } },
    colors: ['#8b1028'],
    xaxis: {
        categories: (props.visitasPorDia || []).map(d => d.fecha),
        labels: { style: { colors: axisColor.value } },
        axisBorder: { color: gridColor.value },
    },
    yaxis: { labels: { style: { colors: axisColor.value } } },
    grid: { borderColor: gridColor.value },
    theme: { mode: chartTheme.value },
    tooltip: { theme: chartTheme.value },
}));

const barOptions = computed(() => ({
    chart: { type: 'bar', background: 'transparent', toolbar: { show: false } },
    colors: ['#8b1028'],
    xaxis: {
        categories: (props.proveedoresTop || []).map(d => d.nombre),
        labels: { style: { colors: axisColor.value }, trim: true, maxHeight: 80 },
        axisBorder: { color: gridColor.value },
    },
    yaxis: { labels: { style: { colors: axisColor.value } } },
    grid: { borderColor: gridColor.value },
    plotOptions: { bar: { borderRadius: 6, horizontal: true } },
    theme: { mode: chartTheme.value },
    tooltip: { theme: chartTheme.value },
    dataLabels: { enabled: false },
}));

const maxVisitas = computed(() => {
    if (!props.proveedoresTop?.length) return 1;
    return Math.max(...props.proveedoresTop.map(p => p.total));
});

// ── Gráficas de pastel (municipios/categorías/necesidades) ────────────
const PIE_PALETTE = ['#8b1028', '#c8113b', '#eb2d4e', '#f59e0b', '#d97706', '#10b981', '#059669', '#78716c', '#a16207', '#f97316'];

function pieOptions(lista, labelKey) {
    return {
        chart: {
            type: 'pie',
            background: 'transparent',
            animations: {
                enabled: true,
                easing: 'easeinout',
                speed: 700,
                animateGradually: { enabled: true, delay: 100 },
                dynamicAnimation: { enabled: true, speed: 350 },
            },
        },
        labels: (lista || []).map(i => i[labelKey]),
        colors: PIE_PALETTE,
        stroke: { width: 2, colors: [isDark.value ? '#111827' : '#ffffff'] },
        legend: {
            position: 'bottom',
            fontSize: '12px',
            labels: { colors: axisColor.value },
            markers: { size: 7 },
        },
        dataLabels: {
            enabled: true,
            formatter: (val) => Math.round(val) + '%',
            style: { fontSize: '11px', fontWeight: 700 },
        },
        theme: { mode: chartTheme.value },
        tooltip: { theme: chartTheme.value },
    };
}

const pieMunicipiosProveedores  = computed(() => pieOptions(props.topMunicipiosProveedores, 'municipio'));
const pieMunicipiosCompradores  = computed(() => pieOptions(props.topMunicipiosCompradores, 'municipio'));
const pieCategoriasProveedores  = computed(() => pieOptions(props.topCategoriasProveedores, 'categoria'));
const pieNecesidadesCompradores = computed(() => pieOptions(props.topNecesidadesCompradores, 'necesidad'));

const serieMunicipiosProveedores  = computed(() => (props.topMunicipiosProveedores || []).map(i => i.total));
const serieMunicipiosCompradores  = computed(() => (props.topMunicipiosCompradores || []).map(i => i.total));
const serieCategoriasProveedores  = computed(() => (props.topCategoriasProveedores || []).map(i => i.total));
const serieNecesidadesCompradores = computed(() => (props.topNecesidadesCompradores || []).map(i => i.total));

// ── Contadores animados ──────────────────────────────────────────────
const animated = ref({});
const animateValue = (key, target, duration = 1200) => {
    const start = Date.now();
    const tick = () => {
        const elapsed = Date.now() - start;
        const progress = Math.min(elapsed / duration, 1);
        const ease = 1 - Math.pow(1 - progress, 3);
        animated.value[key] = Math.round(target * ease);
        if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
};

// Vuelve a animar los contadores cada vez que cambian los props (p.ej. tras
// clasificar a alguien y que Inertia recargue la página con datos frescos),
// no solo en el montaje inicial del componente.
watch(
    () => [
        props.totalVisitas, props.visitasHoy, props.visitasSemana,
        props.generoHombre, props.generoMujer, props.generoNoIdentif,
        props.rfcConRFC, props.rfcSinRFC,
    ],
    ([totalVisitas, visitasHoy, visitasSemana, generoHombre, generoMujer, generoNoIdentif, rfcConRFC, rfcSinRFC]) => {
        animateValue('totalVisitas',    totalVisitas ?? 0);
        animateValue('visitasHoy',      visitasHoy ?? 0);
        animateValue('visitasSemana',   visitasSemana ?? 0);
        animateValue('generoHombre',    generoHombre ?? 0);
        animateValue('generoMujer',     generoMujer ?? 0);
        animateValue('generoNoIdentif', generoNoIdentif ?? 0);
        animateValue('rfcConRFC',       rfcConRFC ?? 0);
        animateValue('rfcSinRFC',       rfcSinRFC ?? 0);
    },
    { immediate: true }
);

const pct = (val, tot) => tot > 0 ? Math.round(val / tot * 100) : 0;

// ── Clasificar no identificados ──────────────────────────────────────
const clasificando = ref(null);
const clasificar = (userId, genero) => {
    clasificando.value = userId;
    router.patch(route('admin.usuarios.genero', userId),
        { genero },
        {
            preserveScroll: true,
            onError: (errors) => {
                alert('No se pudo actualizar el género: ' + (Object.values(errors)[0] ?? 'error desconocido'));
            },
            onFinish: () => { clasificando.value = null; },
        }
    );
};
</script>

<template>
    <AdminLayout title="Métricas">
        <template #header>
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                    <div class="w-1 h-7 rounded-full bg-gradient-to-b from-guinda-400 to-guinda-700"></div>
                    <div>
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">Panel de Métricas</h1>
                        <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">IMPULSATE · IYEM Yucatán · Analítica en tiempo real</p>
                    </div>
                </div>
                <a :href="route('admin.metricas.exportar')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-bold rounded-xl transition-colors shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                    </svg>
                    Exportar a Excel
                </a>
            </div>
        </template>

        <div class="space-y-6">
            <!-- KPI cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="relative overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-sm dark:shadow-none group hover:border-guinda-300 dark:hover:border-guinda-500/40 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500">Total visitas</p>
                        <p class="text-2xl font-black text-guinda-700 dark:text-guinda-400 tabular-nums">{{ animated.totalVisitas ?? 0 }}</p>
                    </div>
                </div>

                <div class="relative overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-sm dark:shadow-none group hover:border-emerald-300 dark:hover:border-emerald-500/40 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-emerald-700 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-6.07-.71.71M5.64 18.36l-.71.71M18.36 18.36l.71.71M5.64 5.64l-.71-.71M12 7a5 5 0 100 10A5 5 0 0012 7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500">Visitas hoy</p>
                        <p class="text-2xl font-black text-emerald-700 dark:text-emerald-400 tabular-nums">{{ animated.visitasHoy ?? 0 }}</p>
                    </div>
                </div>

                <div class="relative overflow-hidden bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 flex items-center gap-4 shadow-sm dark:shadow-none group hover:border-guinda-300 dark:hover:border-guinda-500/40 transition-colors">
                    <div class="w-11 h-11 rounded-xl bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-500">Esta semana</p>
                        <p class="text-2xl font-black text-guinda-700 dark:text-guinda-400 tabular-nums">{{ animated.visitasSemana ?? 0 }}</p>
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
                        :series="[{ name: 'Visitas', data: visitasPorDia.map(d => d.total) }]" :key="chartTheme" />
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

            <!-- Género + RFC -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- GÉNERO -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors">
                    <div class="flex items-center gap-2 mb-5">
                        <span class="text-xl">👥</span>
                        <h3 class="font-bold text-gray-900 dark:text-white">Distribución de Género</h3>
                        <span class="ml-auto text-xs text-gray-400 dark:text-gray-600">{{ generoTotal }} personas únicas</span>
                    </div>

                    <div class="space-y-4">
                        <!-- Hombres -->
                        <div>
                            <div class="flex justify-between items-center mb-1.5">
                                <span class="text-sm font-bold text-blue-600 dark:text-blue-400">♂ Hombres</span>
                                <span class="text-gray-900 dark:text-white font-black text-lg tabular-nums">
                                    {{ animated.generoHombre ?? generoHombre }}
                                    <span class="text-xs text-gray-400 dark:text-gray-600 font-normal ml-1">{{ pct(generoHombre, generoTotal) }}%</span>
                                </span>
                            </div>
                            <div class="h-3 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-blue-600 to-blue-400 rounded-full transition-all duration-1000"
                                    :style="{ width: pct(generoHombre, generoTotal) + '%' }"></div>
                            </div>
                        </div>

                        <!-- Mujeres -->
                        <div>
                            <div class="flex justify-between items-center mb-1.5">
                                <span class="text-sm font-bold text-guinda-600 dark:text-guinda-400">♀ Mujeres</span>
                                <span class="text-gray-900 dark:text-white font-black text-lg tabular-nums">
                                    {{ animated.generoMujer ?? generoMujer }}
                                    <span class="text-xs text-gray-400 dark:text-gray-600 font-normal ml-1">{{ pct(generoMujer, generoTotal) }}%</span>
                                </span>
                            </div>
                            <div class="h-3 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-guinda-700 to-guinda-400 rounded-full transition-all duration-1000"
                                    :style="{ width: pct(generoMujer, generoTotal) + '%' }"></div>
                            </div>
                        </div>

                        <!-- No identificado -->
                        <div>
                            <div class="flex justify-between items-center mb-1.5">
                                <span class="text-sm font-bold text-gray-500 dark:text-gray-500">◌ No identificado</span>
                                <span class="text-gray-600 dark:text-gray-400 font-black text-lg tabular-nums">
                                    {{ animated.generoNoIdentif ?? generoNoIdentif }}
                                    <span class="text-xs text-gray-400 dark:text-gray-600 font-normal ml-1">{{ pct(generoNoIdentif, generoTotal) }}%</span>
                                </span>
                            </div>
                            <div class="h-3 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                                <div class="h-full bg-gray-400 dark:bg-gray-600 rounded-full transition-all duration-1000"
                                    :style="{ width: pct(generoNoIdentif, generoTotal) + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RFC / FORMALIZACIÓN -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors">
                    <div class="flex items-center gap-2 mb-5">
                        <span class="text-xl">🏛️</span>
                        <h3 class="font-bold text-gray-900 dark:text-white">Formalización (RFC)</h3>
                    </div>

                    <div class="grid grid-cols-2 gap-3 mb-5">
                        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/30 p-3 text-center">
                            <p class="text-[11px] text-emerald-700 dark:text-emerald-400 font-bold uppercase tracking-wide mb-1">Proveedores con RFC</p>
                            <p class="text-2xl font-black text-emerald-700 dark:text-emerald-400 tabular-nums">{{ proveedorConRFC }}</p>
                            <p class="text-xs text-emerald-600 dark:text-emerald-600 mt-1">{{ pct(proveedorConRFC, proveedorConRFC + proveedorSinRFC) }}% del total</p>
                        </div>
                        <div class="rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/30 p-3 text-center">
                            <p class="text-[11px] text-red-700 dark:text-red-400 font-bold uppercase tracking-wide mb-1">Proveedores sin RFC</p>
                            <p class="text-2xl font-black text-red-700 dark:text-red-400 tabular-nums">{{ proveedorSinRFC }}</p>
                            <p class="text-xs text-red-600 dark:text-red-700 mt-1">{{ pct(proveedorSinRFC, proveedorConRFC + proveedorSinRFC) }}% del total</p>
                        </div>
                        <div class="rounded-xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800/30 p-3 text-center">
                            <p class="text-[11px] text-emerald-700 dark:text-emerald-400 font-bold uppercase tracking-wide mb-1">Compradores con RFC</p>
                            <p class="text-2xl font-black text-emerald-700 dark:text-emerald-400 tabular-nums">{{ compradorConRFC }}</p>
                            <p class="text-xs text-emerald-600 dark:text-emerald-600 mt-1">{{ pct(compradorConRFC, compradorConRFC + compradorSinRFC) }}% del total</p>
                        </div>
                        <div class="rounded-xl bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/30 p-3 text-center">
                            <p class="text-[11px] text-red-700 dark:text-red-400 font-bold uppercase tracking-wide mb-1">Compradores sin RFC</p>
                            <p class="text-2xl font-black text-red-700 dark:text-red-400 tabular-nums">{{ compradorSinRFC }}</p>
                            <p class="text-xs text-red-600 dark:text-red-700 mt-1">{{ pct(compradorSinRFC, compradorConRFC + compradorSinRFC) }}% del total</p>
                        </div>
                    </div>

                    <!-- Barra global RFC -->
                    <div class="bg-gray-100 dark:bg-gray-800 rounded-full h-4 overflow-hidden flex">
                        <div class="h-full bg-gradient-to-r from-emerald-700 to-emerald-400 transition-all duration-1000 flex items-center justify-center"
                            :style="{ width: pct(rfcConRFC, rfcTotal) + '%' }">
                            <span v-if="pct(rfcConRFC, rfcTotal) > 15" class="text-[11px] font-black text-white">{{ pct(rfcConRFC, rfcTotal) }}% formalizados</span>
                        </div>
                        <div class="h-full bg-gray-300 dark:bg-gray-700 flex-1 flex items-center justify-center">
                            <span v-if="pct(rfcSinRFC, rfcTotal) > 15" class="text-[11px] font-bold text-gray-600 dark:text-gray-400">{{ pct(rfcSinRFC, rfcTotal) }}% sin RFC</span>
                        </div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-500 mt-1.5">
                        <span>{{ rfcConRFC }} con RFC</span>
                        <span>{{ rfcSinRFC }} sin RFC</span>
                    </div>
                </div>
            </div>

            <!-- No identificados -->
            <div v-if="noIdentificados.length > 0" class="bg-amber-50 dark:bg-amber-950/10 border border-amber-200 dark:border-amber-500/20 rounded-2xl overflow-hidden transition-colors">
                <div class="px-6 py-4 border-b border-amber-200 dark:border-amber-500/20 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
                        </span>
                        <h3 class="font-bold text-amber-700 dark:text-amber-400">Género no identificado — Clasificar manualmente</h3>
                    </div>
                    <span class="text-xs bg-amber-100 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 font-bold px-3 py-1 rounded-full border border-amber-200 dark:border-amber-500/30">
                        {{ noIdentificados.length }} pendientes
                    </span>
                </div>

                <div class="p-4">
                    <p class="text-xs text-gray-500 dark:text-gray-500 mb-4 px-2">
                        Haz clic en ♂ o ♀ para clasificar. El registro desaparece automáticamente de esta lista al guardarse.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2">
                        <div v-for="persona in noIdentificados" :key="persona.id"
                            class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl bg-white dark:bg-white/5 border border-gray-200 dark:border-white/5 hover:border-amber-300 dark:hover:border-amber-500/30 transition-colors">

                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ persona.name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">{{ persona.roles?.join(', ') }}</p>
                            </div>

                            <div class="flex gap-1.5 flex-shrink-0">
                                <button
                                    @click="clasificar(persona.id, 'hombre')"
                                    :disabled="clasificando === persona.id"
                                    class="px-3 py-1.5 rounded-lg text-xs font-black bg-blue-50 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-100 dark:hover:bg-blue-800/70 hover:border-blue-400 dark:hover:border-blue-500 disabled:opacity-50 transition-all">
                                    ♂ H
                                </button>
                                <button
                                    @click="clasificar(persona.id, 'mujer')"
                                    :disabled="clasificando === persona.id"
                                    class="px-3 py-1.5 rounded-lg text-xs font-black bg-guinda-50 dark:bg-guinda-900/50 text-guinda-600 dark:text-guinda-400 border border-guinda-200 dark:border-guinda-800/50 hover:bg-guinda-100 dark:hover:bg-guinda-800/70 hover:border-guinda-400 dark:hover:border-guinda-500 disabled:opacity-50 transition-all">
                                    ♀ M
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="rounded-2xl border border-emerald-200 dark:border-emerald-500/20 bg-emerald-50 dark:bg-emerald-950/10 px-6 py-4 flex items-center gap-3">
                <span class="text-2xl">✅</span>
                <p class="text-sm text-emerald-700 dark:text-emerald-400 font-semibold">Todos los usuarios tienen género clasificado.</p>
            </div>

            <!-- Distribución geográfica y categorías -->
            <div>
                <h2 class="font-bold text-gray-900 dark:text-white mb-4">Distribución geográfica y categorías</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Top municipios proveedores -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                            <h3 class="font-bold text-gray-900 dark:text-white">📍 Top municipios — Proveedores</h3>
                        </div>
                        <div v-if="topMunicipiosProveedores.length" class="p-6">
                            <VueApexCharts type="pie" height="300" :options="pieMunicipiosProveedores"
                                :series="serieMunicipiosProveedores" :key="'municipiosProv-' + chartTheme" />
                        </div>
                        <div v-else class="px-6 py-12 text-center text-gray-400 dark:text-gray-600 text-sm">Sin datos</div>
                    </div>

                    <!-- Top municipios compradores -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                            <h3 class="font-bold text-gray-900 dark:text-white">📍 Top municipios — Compradores</h3>
                        </div>
                        <div v-if="topMunicipiosCompradores.length" class="p-6">
                            <VueApexCharts type="pie" height="300" :options="pieMunicipiosCompradores"
                                :series="serieMunicipiosCompradores" :key="'municipiosComp-' + chartTheme" />
                        </div>
                        <div v-else class="px-6 py-12 text-center text-gray-400 dark:text-gray-600 text-sm">Sin datos</div>
                    </div>

                    <!-- Top categorías proveedores -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                            <h3 class="font-bold text-gray-900 dark:text-white">🏷️ Top categorías — Proveedores</h3>
                        </div>
                        <div v-if="topCategoriasProveedores.length" class="p-6">
                            <VueApexCharts type="pie" height="300" :options="pieCategoriasProveedores"
                                :series="serieCategoriasProveedores" :key="'categoriasProv-' + chartTheme" />
                        </div>
                        <div v-else class="px-6 py-12 text-center text-gray-400 dark:text-gray-600 text-sm">Sin datos</div>
                    </div>

                    <!-- Top necesidades compradores -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors">
                        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                            <h3 class="font-bold text-gray-900 dark:text-white">🛒 Top necesidades — Compradores</h3>
                        </div>
                        <div v-if="topNecesidadesCompradores.length" class="p-6">
                            <VueApexCharts type="pie" height="300" :options="pieNecesidadesCompradores"
                                :series="serieNecesidadesCompradores" :key="'necesidadesComp-' + chartTheme" />
                        </div>
                        <div v-else class="px-6 py-12 text-center text-gray-400 dark:text-gray-600 text-sm">Sin datos</div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
