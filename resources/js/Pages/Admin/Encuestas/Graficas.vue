<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import VueApexCharts from 'vue3-apexcharts';
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    plantilla:      Object,
    plantillas:     Array,
    eventos:        Array,
    filtros:        Object,
    kpis:           Object,
    datos:          Object, // keyed por pregunta.texto
    respondientes:  { type: Array, default: () => [] },
});

const filtros = ref({
    evento_id:    props.filtros.eventoId    ?? '',
    plantilla_id: props.filtros.plantillaId ?? '',
    segmento:     props.filtros.segmento    ?? '',
    desde:        props.filtros.desde       ?? '',
    hasta:        props.filtros.hasta       ?? '',
    canirac:      props.filtros.canirac     ?? '',
});

const aplicarFiltros = () => {
    const params = Object.fromEntries(
        Object.entries(filtros.value).filter(([, v]) => v !== '')
    );
    router.get(route('admin.encuestas.graficas'), params, { preserveState: false });
};

const labelTipo = (tipo) => ({
    opciones: 'Opción única',
    multiple: 'Múltiple',
    binario:  'Sí / No',
    escala:   'Escala',
    nps:      'NPS',
    texto:    'Texto libre',
}[tipo] ?? tipo);

// Detección de inicio de sección para separadores visuales
const SECCIONES = [
    { preguntaId: 'c_oferta_coincide',    label: '🛒 Sección Compradores' },
    { preguntaId: 'p_perfil_compradores', label: '🏭 Sección Proveedores' },
    { preguntaId: 'tiempo_cita',          label: '📋 Logística y Organización' },
];

const esInicioSeccion = (idx) => {
    if (!props.plantilla) return false;
    const p = props.plantilla.preguntas[idx];
    return SECCIONES.some(s => s.preguntaId === p.id);
};

const labelSeccion = (pregunta) => {
    const sec = SECCIONES.find(s => s.preguntaId === pregunta.id);
    return sec?.label ?? '';
};

// ── Descargas ─────────────────────────────────────────────────────────
const urlReporte = computed(() => {
    const params = new URLSearchParams();
    if (filtros.value.evento_id)    params.set('evento_id', filtros.value.evento_id);
    if (filtros.value.plantilla_id) params.set('plantilla_id', filtros.value.plantilla_id);
    if (filtros.value.canirac)      params.set('canirac', filtros.value.canirac);
    return route('admin.encuestas.reporte-pdf') + '?' + params.toString();
});

const urlExcel = computed(() => {
    const params = new URLSearchParams();
    if (filtros.value.evento_id)    params.set('evento_id', filtros.value.evento_id);
    if (filtros.value.plantilla_id) params.set('plantilla_id', filtros.value.plantilla_id);
    if (filtros.value.canirac)      params.set('canirac', filtros.value.canirac);
    return route('admin.encuestas.reporte-excel') + '?' + params.toString();
});

// ── Tabs General / Por Persona ───────────────────────────────────────
const tab           = ref('general'); // 'general' | 'persona'
const vistaGeneral  = ref('barras');  // 'barras' | 'pastel'
const busquedaPersona = ref('');

const respondientesFiltrados = computed(() => {
    if (!busquedaPersona.value) return props.respondientes;
    const q = busquedaPersona.value.toLowerCase();
    return props.respondientes.filter(r =>
        (r.nombre ?? '').toLowerCase().includes(q) ||
        (r.email ?? '').toLowerCase().includes(q)
    );
});

// ── Gráficas de pastel (ApexCharts) ──────────────────────────────────
const isDark = ref(document.documentElement.classList.contains('dark'));

onMounted(() => {
    const observer = new MutationObserver(() => {
        isDark.value = document.documentElement.classList.contains('dark');
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

const chartTheme   = computed(() => isDark.value ? 'dark' : 'light');
const legendColor  = computed(() => isDark.value ? '#9ca3af' : '#374151');

const PALETTE = ['#8b1028', '#c8113b', '#eb2d4e', '#f36178', '#f89aa9', '#0ea5e9', '#6366f1', '#22c55e', '#f59e0b', '#a855f7'];

const pieSeries = (pregunta) => Object.values(props.datos[pregunta.texto]?.opciones ?? {}).map(o => o.count);
const pieLabels = (pregunta) => Object.keys(props.datos[pregunta.texto]?.opciones ?? {});

const pieOptions = (pregunta) => ({
    chart: { type: 'pie', background: 'transparent' },
    labels: pieLabels(pregunta),
    colors: PALETTE,
    legend: { position: 'bottom', labels: { colors: legendColor.value } },
    theme: { mode: chartTheme.value },
    tooltip: { theme: chartTheme.value },
});
</script>

<template>
    <AdminLayout title="Gráficas de Encuestas">
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">📊 Análisis de Encuestas</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">{{ plantilla?.nombre ?? 'Sin plantilla activa' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="urlReporte" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-700 hover:bg-emerald-600 text-white text-sm font-semibold rounded-xl transition-colors">
                        📥 Descargar PDF con gráficas
                    </a>
                    <a :href="urlExcel" target="_blank"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-green-700 hover:bg-green-600 text-white text-sm font-semibold rounded-xl transition-colors">
                        📊 Descargar Excel
                    </a>
                    <Link :href="route('admin.encuestas.index')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl transition-colors">
                        ← Volver
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-5xl">

            <!-- Filtros -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                <form @submit.prevent="aplicarFiltros"
                      class="grid grid-cols-2 md:grid-cols-5 gap-3">

                    <select v-model="filtros.evento_id"
                            class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-guinda-500">
                        <option value="">Todos los eventos</option>
                        <option v-for="e in eventos" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                    </select>

                    <select v-model="filtros.plantilla_id"
                            class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-guinda-500">
                        <option value="">Plantilla activa</option>
                        <option v-for="p in plantillas" :key="p.id" :value="p.id">
                            {{ p.nombre }}{{ p.activa ? ' ✓' : '' }}
                        </option>
                    </select>

                    <select v-model="filtros.segmento"
                            class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-guinda-500">
                        <option value="">Todos los perfiles</option>
                        <option value="comprador">Solo Compradores</option>
                        <option value="proveedor">Solo Proveedores</option>
                    </select>

                    <input v-model="filtros.desde" type="date"
                           class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-guinda-500" />
                    <input v-model="filtros.hasta" type="date"
                           class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-guinda-500" />

                    <select v-model="filtros.canirac"
                            class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-guinda-500">
                        <option value="">Canirac: Todos</option>
                        <option value="si">✅ Solo Canirac</option>
                        <option value="no">❌ No Canirac</option>
                    </select>

                    <button type="submit"
                            class="col-span-2 md:col-span-6 bg-guinda-800 hover:bg-guinda-700 text-white
                                   rounded-lg py-2 text-sm font-semibold transition-colors">
                        Aplicar filtros
                    </button>
                </form>
            </div>

            <!-- KPIs -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 text-center">
                    <p class="text-3xl font-black text-gray-900 dark:text-white">{{ kpis.total_enviadas }}</p>
                    <p class="text-gray-500 dark:text-gray-500 text-xs mt-1">Enviadas</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-emerald-200 dark:border-emerald-800/50 rounded-2xl p-5 text-center">
                    <p class="text-3xl font-black text-emerald-600 dark:text-emerald-400">{{ kpis.total_respondidas }}</p>
                    <p class="text-gray-500 dark:text-gray-500 text-xs mt-1">Respondidas</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-guinda-200 dark:border-guinda-800/50 rounded-2xl p-5 text-center">
                    <p class="text-3xl font-black text-guinda-700 dark:text-guinda-400">{{ kpis.tasa }}%</p>
                    <p class="text-gray-500 dark:text-gray-500 text-xs mt-1">Tasa respuesta</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800/50 rounded-2xl p-5 text-center">
                    <p class="text-3xl font-black text-blue-600 dark:text-blue-400">{{ kpis.compradores }}</p>
                    <p class="text-gray-500 dark:text-gray-500 text-xs mt-1">Compradores</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-purple-200 dark:border-purple-800/50 rounded-2xl p-5 text-center">
                    <p class="text-3xl font-black text-purple-600 dark:text-purple-400">{{ kpis.proveedores }}</p>
                    <p class="text-gray-500 dark:text-gray-500 text-xs mt-1">Proveedores</p>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-orange-200 dark:border-orange-800/50 rounded-2xl p-5 text-center">
                    <p class="text-3xl font-black text-orange-600 dark:text-orange-400">{{ kpis.canirac }}</p>
                    <p class="text-gray-500 dark:text-gray-500 text-xs mt-1">Canirac</p>
                </div>
            </div>

            <!-- Tabs General / Por Persona -->
            <div class="flex flex-wrap items-center gap-2">
                <button @click="tab = 'general'"
                        :class="tab === 'general' ? 'bg-guinda-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                        class="px-5 py-2 rounded-xl text-sm font-semibold transition-colors">
                    📊 General
                </button>
                <button @click="tab = 'persona'"
                        :class="tab === 'persona' ? 'bg-guinda-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700'"
                        class="px-5 py-2 rounded-xl text-sm font-semibold transition-colors">
                    👤 Por Persona ({{ respondientes.length }})
                </button>

                <div v-if="tab === 'general'" class="ml-auto flex items-center gap-2">
                    <span class="text-gray-400 dark:text-gray-500 text-xs">Vista:</span>
                    <button @click="vistaGeneral = 'barras'"
                            :class="vistaGeneral === 'barras' ? 'bg-guinda-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                        ▬ Barras
                    </button>
                    <button @click="vistaGeneral = 'pastel'"
                            :class="vistaGeneral === 'pastel' ? 'bg-guinda-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors">
                        ● Pastel
                    </button>
                </div>
            </div>

            <!-- ═══ VISTA GENERAL ═══ -->
            <template v-if="tab === 'general'">

            <!-- Sin datos -->
            <div v-if="!plantilla || kpis.total_respondidas === 0"
                 class="text-center text-gray-400 dark:text-gray-600 py-20">
                <p class="text-5xl mb-4">📭</p>
                <p class="text-lg">No hay respuestas con los filtros seleccionados.</p>
            </div>

            <!-- Gráficas por pregunta -->
            <div v-else class="space-y-4">

                <template v-for="(pregunta, idx) in plantilla.preguntas" :key="pregunta.id">

                    <!-- Separador de sección -->
                    <div v-if="esInicioSeccion(idx)"
                         class="pt-4 pb-2 border-b border-gray-200 dark:border-gray-800">
                        <h2 class="text-gray-900 dark:text-white font-bold text-base">{{ labelSeccion(pregunta) }}</h2>
                    </div>

                    <!-- Tarjeta de pregunta (solo si tiene datos) -->
                    <div v-if="datos[pregunta.texto]"
                         class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6">

                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <span class="text-xs text-gray-400 dark:text-gray-500 uppercase tracking-wider font-semibold">
                                    {{ labelTipo(pregunta.tipo) }}
                                    <span v-if="pregunta.condicion" class="ml-2 px-2 py-0.5 bg-gray-100 dark:bg-gray-800 rounded text-gray-500 dark:text-gray-400 normal-case">
                                        Solo {{ pregunta.condicion.valor }}
                                    </span>
                                </span>
                                <h3 class="text-gray-900 dark:text-white font-semibold mt-1">{{ pregunta.texto }}</h3>
                            </div>
                            <span class="text-gray-400 dark:text-gray-500 text-sm flex-shrink-0 ml-4">
                                {{ datos[pregunta.texto].total }} resp.
                            </span>
                        </div>

                        <!-- TIPO: opciones / multiple / binario -> Barras horizontales -->
                        <div v-if="['opciones','multiple','binario'].includes(pregunta.tipo) && vistaGeneral === 'barras'">
                            <div v-for="(stat, opcion) in datos[pregunta.texto].opciones"
                                 :key="opcion" class="mb-3">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-300">{{ opcion }}</span>
                                    <span class="text-gray-900 dark:text-white font-medium">
                                        {{ stat.porcentaje }}% <span class="text-gray-400 dark:text-gray-500">({{ stat.count }})</span>
                                    </span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2.5">
                                    <div class="h-2.5 rounded-full bg-guinda-700 dark:bg-guinda-500 transition-all duration-700"
                                         :style="{ width: stat.porcentaje + '%' }">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TIPO: opciones / multiple / binario -> Pastel (ApexCharts) -->
                        <div v-else-if="['opciones','multiple','binario'].includes(pregunta.tipo) && vistaGeneral === 'pastel'">
                            <VueApexCharts type="pie" height="260"
                                :options="pieOptions(pregunta)" :series="pieSeries(pregunta)"
                                :key="'pastel-' + idx + '-' + chartTheme" />
                        </div>

                        <!-- TIPO: escala / nps -> Promedio + distribución -->
                        <div v-else-if="['escala','nps'].includes(pregunta.tipo)">
                            <div class="flex items-center gap-6 mb-4">
                                <div class="text-center">
                                    <p class="text-4xl font-black text-guinda-700 dark:text-guinda-400">
                                        {{ datos[pregunta.texto].promedio }}
                                    </p>
                                    <p class="text-gray-400 dark:text-gray-500 text-xs mt-1">promedio</p>
                                </div>
                                <div class="flex-1">
                                    <div v-for="(stat, valor) in datos[pregunta.texto].opciones"
                                         :key="valor" class="mb-2">
                                        <div class="flex justify-between text-xs mb-0.5">
                                            <span class="text-gray-500 dark:text-gray-400">{{ valor }}</span>
                                            <span class="text-gray-900 dark:text-white">{{ stat.count }}</span>
                                        </div>
                                        <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5">
                                            <div class="h-1.5 rounded-full bg-guinda-600"
                                                 :style="{ width: stat.porcentaje + '%' }"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TIPO: texto -> Lista de respuestas -->
                        <div v-else-if="pregunta.tipo === 'texto'">
                            <div v-if="datos[pregunta.texto].textos.length === 0"
                                 class="text-gray-400 dark:text-gray-600 text-sm italic">Sin respuestas de texto.</div>
                            <div v-else>
                                <div class="max-h-60 overflow-y-auto space-y-2 pr-2">
                                    <div v-for="(txt, i) in datos[pregunta.texto].textos" :key="i"
                                         class="bg-gray-50 dark:bg-gray-800 rounded-lg px-4 py-2 text-gray-700 dark:text-gray-200 text-sm border-l-2 border-guinda-600">
                                        {{ txt }}
                                    </div>
                                </div>
                                <p class="text-gray-400 dark:text-gray-500 text-xs mt-2">
                                    {{ datos[pregunta.texto].total }} respuestas escritas
                                </p>
                            </div>
                        </div>

                    </div>

                    <!-- Tarjeta vacía (pregunta sin respuestas aún) -->
                    <div v-else
                         class="bg-gray-50 dark:bg-gray-900/50 border border-dashed border-gray-200 dark:border-gray-800 rounded-2xl p-4
                                flex items-center gap-3 opacity-60">
                        <span class="text-gray-500 dark:text-gray-500 text-sm">{{ pregunta.texto }}</span>
                        <span class="text-xs text-gray-400 dark:text-gray-600 ml-auto">Sin datos</span>
                    </div>

                </template>
            </div>

            </template>
            <!-- ═══ FIN VISTA GENERAL ═══ -->

            <!-- ═══ VISTA POR PERSONA ═══ -->
            <div v-else class="space-y-4">

                <input v-model="busquedaPersona" type="text"
                       placeholder="Buscar por nombre o correo..."
                       class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:border-guinda-500" />

                <div v-if="respondientesFiltrados.length === 0" class="text-center text-gray-400 dark:text-gray-600 py-16">
                    No hay respondientes con ese filtro.
                </div>

                <details v-for="p in respondientesFiltrados" :key="p.id"
                          class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden group">
                    <summary class="flex items-center justify-between gap-4 px-5 py-4 cursor-pointer list-none hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <div class="flex items-center gap-4 min-w-0">
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold flex-shrink-0 capitalize"
                                  :class="p.tipo === 'proveedor'
                                    ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400'
                                    : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'">
                                {{ p.tipo === 'proveedor' ? '🏭' : '🛒' }} {{ p.tipo }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-gray-900 dark:text-white font-medium text-sm truncate">{{ p.nombre }}</p>
                                <p class="text-gray-400 dark:text-gray-500 text-xs truncate">{{ p.email }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <span class="text-gray-400 dark:text-gray-500 text-xs">{{ p.fecha }}</span>
                            <span class="text-gray-400 dark:text-gray-500 transition-transform group-open:rotate-180">▼</span>
                        </div>
                    </summary>

                    <div class="px-5 pb-4 pt-1 border-t border-gray-100 dark:border-gray-800 space-y-2">
                        <div v-for="(resp, i) in p.respuestas" :key="i"
                             class="flex gap-4 py-2 border-b border-gray-50 dark:border-gray-800/50">
                            <p class="text-gray-500 dark:text-gray-400 text-xs flex-1">{{ resp.pregunta }}</p>
                            <p class="text-gray-900 dark:text-white text-xs font-medium text-right max-w-xs">
                                {{ resp.respuesta || '—' }}
                            </p>
                        </div>
                        <div class="pt-2 flex justify-end">
                            <button @click="confirmarEliminar(p.id, p.nombre)"
                                class="text-xs px-3 py-1.5 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-800/40 text-red-600 dark:text-red-400 rounded-lg transition-colors border border-red-200 dark:border-red-500/20">
                                🗑 Eliminar respuestas
                            </button>
                        </div>
                    </div>
                </details>
            </div>

        </div>

        <!-- Modal confirmar eliminar respuestas -->
        <Teleport to="body">
            <div v-if="modalEliminar"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background:rgba(0,0,0,0.55)" @click.self="modalEliminar = false">
                <div class="bg-white dark:bg-gray-900 border border-red-200 dark:border-red-500/30 rounded-2xl p-8 max-w-sm w-full shadow-2xl">
                    <p class="text-2xl mb-2 text-center">🗑</p>
                    <h3 class="text-gray-900 dark:text-white font-bold text-lg text-center mb-2">¿Eliminar respuestas?</h3>
                    <p class="text-gray-500 dark:text-gray-400 text-sm text-center mb-6">
                        Se borrarán todas las respuestas de
                        <strong class="text-gray-900 dark:text-white">{{ eliminarNombre }}</strong>.
                        Esta acción no se puede deshacer.<br />
                        <span class="text-amber-600 dark:text-amber-400 text-xs mt-1 block">
                            La persona podrá volver a recibir la encuesta.
                        </span>
                    </p>
                    <div class="flex gap-3">
                        <button @click="modalEliminar = false"
                            class="flex-1 px-4 py-2 bg-gray-100 dark:bg-white/10 hover:bg-gray-200 dark:hover:bg-white/20 text-gray-700 dark:text-white rounded-lg text-sm font-medium transition-colors">
                            Cancelar
                        </button>
                        <button @click="ejecutarEliminar"
                            class="flex-1 px-4 py-2 bg-red-700 hover:bg-red-600 text-white rounded-lg text-sm font-semibold transition-colors">
                            Sí, eliminar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
