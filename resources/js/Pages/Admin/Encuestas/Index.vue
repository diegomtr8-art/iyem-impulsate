<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    encuestas:          Object,
    eventos:            Array,
    filtroEvento:       [String, Number, null],
    metricas:           Object,
    ultimasRespondidas: Array,
    plantillas:         { type: Array, default: () => [] },
    plantillaActiva:    { type: Object, default: null },
});

const segmentosEnvio = [
    { value: 'todos', label: 'Todos' },
    { value: 'proveedores_evento', label: 'Proveedores del evento' },
    { value: 'compradores_evento', label: 'Compradores del evento' },
    { value: 'proveedores', label: 'Solo proveedores' },
    { value: 'compradores', label: 'Solo compradores' },
];

const eventoSeleccionado = ref(props.filtroEvento ?? '');
const plantillaEnvioId   = ref(props.plantillaActiva?.id ?? '');
const segmentoEnvio      = ref('todos');
const emailPrueba        = ref('');
const plantillaPruebaId  = ref('');
const abiertas           = ref([]);

const filtrar = () => {
    router.get(route('admin.encuestas.index'), { evento_id: eventoSeleccionado.value || undefined }, { preserveScroll: true });
};

const enviarAEvento = () => {
    if (!eventoSeleccionado.value || !plantillaEnvioId.value) return;
    if (!confirm('¿Enviar la encuesta a los participantes aprobados de este evento que aún no la han recibido?')) return;
    router.post(route('admin.encuestas.enviar-evento'), {
        evento_id:    eventoSeleccionado.value,
        plantilla_id: plantillaEnvioId.value,
        segmento:     segmentoEnvio.value,
    }, { preserveScroll: true });
};

const enviarRecordatorio = () => {
    if (!eventoSeleccionado.value) {
        alert('Selecciona un evento primero.');
        return;
    }
    if (!confirm('¿Enviar recordatorio a todos los que NO han contestado aún?')) return;
    router.post(route('admin.encuestas.enviar-recordatorio'), {
        evento_id: eventoSeleccionado.value,
    }, { preserveScroll: true });
};

const enviarPrueba = () => {
    router.post(route('admin.encuestas.enviar-prueba'), {
        email:        emailPrueba.value,
        plantilla_id: plantillaPruebaId.value || undefined,
    }, {
        preserveScroll: true,
        onSuccess: () => { emailPrueba.value = ''; },
    });
};

const toggleAcordeon = (id) => {
    const idx = abiertas.value.indexOf(id);
    if (idx >= 0) abiertas.value.splice(idx, 1);
    else abiertas.value.push(id);
};

const urlExportar = computed(() => {
    const base = route('admin.encuestas.exportar');
    return eventoSeleccionado.value ? `${base}?evento_id=${eventoSeleccionado.value}` : base;
});

// ── Envío personalizado ──────────────────────────────────────────────
const modalPersonalizado     = ref(false);
const eventoSelPersonalizado = ref('');
const plantillaPersonalizada = ref('');
const participantes          = ref([]);
const cargandoPart           = ref(false);
const filtroTipo             = ref(''); // '' | 'proveedor' | 'comprador'
const busquedaPart            = ref('');
const seleccionados           = ref([]); // array de user_ids

const participantesFiltrados = computed(() => {
    let lista = participantes.value;
    if (filtroTipo.value) lista = lista.filter(p => p.tipo === filtroTipo.value);
    if (busquedaPart.value) {
        const q = busquedaPart.value.toLowerCase();
        lista = lista.filter(p =>
            p.name.toLowerCase().includes(q) ||
            p.nombre_negocio.toLowerCase().includes(q) ||
            p.email.toLowerCase().includes(q)
        );
    }
    return lista;
});

const todosSeleccionados = computed(() =>
    participantesFiltrados.value.length > 0 &&
    participantesFiltrados.value.every(p => seleccionados.value.includes(p.user_id))
);

async function cargarParticipantes() {
    if (!eventoSelPersonalizado.value) return;
    cargandoPart.value = true;
    seleccionados.value = [];
    try {
        const { data } = await axios.get(route('admin.encuestas.participantes'), {
            params: { evento_id: eventoSelPersonalizado.value },
        });
        participantes.value = data;
    } finally {
        cargandoPart.value = false;
    }
}

function toggleTodos() {
    const ids = participantesFiltrados.value.map(p => p.user_id);
    if (todosSeleccionados.value) {
        seleccionados.value = seleccionados.value.filter(id => !ids.includes(id));
    } else {
        seleccionados.value = [...new Set([...seleccionados.value, ...ids])];
    }
}

function abrirPersonalizado() {
    modalPersonalizado.value = true;
    eventoSelPersonalizado.value = '';
    plantillaPersonalizada.value = '';
    participantes.value = [];
    seleccionados.value = [];
    busquedaPart.value = '';
    filtroTipo.value = '';
}

function enviarPersonalizado() {
    if (seleccionados.value.length === 0) return;
    router.post(route('admin.encuestas.enviar-seleccion'), {
        evento_id:    eventoSelPersonalizado.value,
        plantilla_id: plantillaPersonalizada.value || undefined,
        user_ids:     seleccionados.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            modalPersonalizado.value = false;
            seleccionados.value = [];
        },
    });
}
</script>

<template>
    <AdminLayout title="Encuestas">
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Encuestas de Satisfacción</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Respuestas de participantes por evento</p>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="abrirPersonalizado"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl transition-colors">
                        🎯 Envío personalizado
                    </button>
                    <Link :href="route('admin.encuestas.graficas')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                        📊 Ver Gráficas
                    </Link>
                    <Link :href="route('admin.encuestas.plantillas')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Plantillas de encuesta
                    </Link>
                    <a :href="urlExportar"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportar Excel
                    </a>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-5xl">

            <!-- Filtro por evento -->
            <div class="flex flex-wrap items-end gap-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Filtrar por evento</label>
                    <select v-model="eventoSeleccionado"
                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500">
                        <option value="">— Todos los eventos —</option>
                        <option v-for="ev in eventos" :key="ev.id" :value="ev.id">{{ ev.nombre }}</option>
                    </select>
                </div>
                <button @click="filtrar"
                    class="px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Filtrar
                </button>
            </div>

            <!-- Enviar encuestas -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 space-y-3">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Enviar encuestas a un evento</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Plantilla</label>
                        <select v-model="plantillaEnvioId"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500">
                            <option value="">— Selecciona plantilla —</option>
                            <option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.activa ? '✓ ' : '' }}{{ p.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Evento</label>
                        <select v-model="eventoSeleccionado"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500">
                            <option value="">— Selecciona evento —</option>
                            <option v-for="ev in eventos" :key="ev.id" :value="ev.id">{{ ev.nombre }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Segmento</label>
                        <select v-model="segmentoEnvio"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500">
                            <option v-for="s in segmentosEnvio" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button @click="enviarAEvento" :disabled="!eventoSeleccionado || !plantillaEnvioId"
                        class="px-4 py-2 bg-amber-600 hover:bg-amber-500 disabled:opacity-40 text-white text-sm font-semibold rounded-xl transition-colors">
                        Enviar encuestas
                    </button>
                    <button @click="enviarRecordatorio" :disabled="!eventoSeleccionado"
                        class="px-4 py-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-40 text-white rounded-xl text-sm font-medium transition-colors flex items-center gap-2">
                        ⏰ Recordatorio a pendientes
                    </button>
                </div>
            </div>

            <!-- Botón de prueba -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Enviar encuesta de prueba a</label>
                    <input v-model="emailPrueba" type="email" placeholder="tu-correo@ejemplo.com"
                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500" />
                </div>
                <div class="min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Plantilla a probar</label>
                    <select v-model="plantillaPruebaId"
                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500">
                        <option value="">Plantilla activa</option>
                        <option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                    </select>
                </div>
                <button @click="enviarPrueba" :disabled="!emailPrueba"
                    class="px-4 py-2 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl transition-colors">
                    Enviar prueba
                </button>
            </div>

            <!-- Stats rápidos -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-guinda-700 dark:text-guinda-400">{{ metricas?.total_enviadas ?? 0 }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Total enviadas</div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-emerald-200 dark:border-emerald-800/50 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ metricas?.total_respondidas ?? 0 }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Respondidas</div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-amber-200 dark:border-amber-800/50 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ metricas?.total_pendientes ?? 0 }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Pendientes</div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800/50 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ metricas?.tasa_respuesta ?? 0 }}%</div>
                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Tasa de respuesta</div>
                </div>
            </div>

            <!-- Métricas por pregunta -->
            <div v-if="metricas?.escala?.length || metricas?.binario?.length" class="space-y-4">

                <!-- Preguntas de escala -->
                <div v-if="metricas?.escala?.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Promedio por pregunta (escala 1–5)</h3>
                    <div class="space-y-3">
                        <div v-for="m in metricas.escala" :key="m.pregunta">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-600 dark:text-gray-400 flex-1 mr-3 leading-snug">{{ m.pregunta }}</span>
                                <span class="text-sm font-black text-guinda-700 dark:text-guinda-400 shrink-0">{{ Number(m.promedio).toFixed(1) }} / 5</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2">
                                <div class="bg-guinda-600 dark:bg-guinda-500 h-2 rounded-full transition-all"
                                    :style="{ width: (Number(m.promedio) / 5 * 100) + '%' }"></div>
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-600 mt-0.5">{{ m.total }} respuesta{{ m.total !== 1 ? 's' : '' }}</div>
                        </div>
                    </div>
                </div>

                <!-- Preguntas binarias -->
                <div v-if="metricas?.binario?.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Preguntas Sí/No</h3>
                    <div class="space-y-3">
                        <div v-for="m in metricas.binario" :key="m.pregunta">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-600 dark:text-gray-400 flex-1 mr-3 leading-snug">{{ m.pregunta }}</span>
                                <span class="text-sm font-black text-emerald-600 dark:text-emerald-400 shrink-0">{{ m.porcentaje_si }}% Sí</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all"
                                    :style="{ width: m.porcentaje_si + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acordeón últimas 10 respondidas -->
            <div v-if="ultimasRespondidas?.length" class="space-y-2">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Últimas 10 encuestas respondidas</h3>
                <div v-for="enc in ultimasRespondidas" :key="enc.id"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
                    <button @click="toggleAcordeon(enc.id)"
                        class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <div>
                            <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ enc.usuario ?? 'Sin nombre' }}</span>
                            <span class="text-xs text-gray-400 ml-2">{{ enc.evento ?? '—' }} · {{ enc.completada_at }}</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0 ml-2"
                            :class="abiertas.includes(enc.id) ? 'rotate-180' : ''"
                            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div v-if="abiertas.includes(enc.id)" class="px-4 pb-4 space-y-3 border-t border-gray-100 dark:border-gray-800 pt-3">
                        <div v-for="(r, idx) in enc.respuestas" :key="idx">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ r.pregunta }}</p>
                            <p class="text-sm text-gray-800 dark:text-gray-200">{{ r.respuesta }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm">
                <div v-if="!encuestas?.data?.length"
                    class="text-center py-16 text-gray-400 dark:text-gray-600">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-sm">No hay encuestas para mostrar</p>
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Evento</th>
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Participante</th>
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Tipo</th>
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Estado</th>
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Respondida</th>
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="enc in encuestas.data" :key="enc.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 font-medium max-w-[180px] truncate">
                                {{ enc.evento?.nombre ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ enc.usuario?.name ?? '—' }}</div>
                                <div class="text-xs text-gray-400 dark:text-gray-500">{{ enc.usuario?.email ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full capitalize"
                                    :class="enc.tipo === 'proveedor'
                                        ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400'
                                        : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'">
                                    {{ enc.tipo }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="enc.completada
                                        ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'
                                        : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'">
                                    {{ enc.completada ? 'Respondida' : 'Pendiente' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500">
                                {{ enc.completada_at ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link v-if="enc.completada"
                                    :href="route('admin.encuestas.ver', enc.id)"
                                    class="text-xs px-3 py-1 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
                                    👁 Ver
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Paginación -->
                <div v-if="encuestas?.last_page > 1"
                    class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-800">
                    <span class="text-xs text-gray-400">
                        Página {{ encuestas.current_page }} de {{ encuestas.last_page }}
                    </span>
                    <div class="flex gap-2">
                        <Link v-if="encuestas.prev_page_url" :href="encuestas.prev_page_url"
                            class="px-3 py-1 text-xs rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                            ← Anterior
                        </Link>
                        <Link v-if="encuestas.next_page_url" :href="encuestas.next_page_url"
                            class="px-3 py-1 text-xs rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                            Siguiente →
                        </Link>
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal Envío Personalizado -->
        <Teleport to="body">
            <div v-if="modalPersonalizado"
                class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background:rgba(0,0,0,0.55)" @click.self="modalPersonalizado = false">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl w-full max-w-3xl max-h-[90vh] flex flex-col shadow-2xl">

                    <!-- Header -->
                    <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-gray-800 flex-shrink-0">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">🎯 Envío Personalizado</h2>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-0.5">Selecciona exactamente a quién enviar la encuesta</p>
                        </div>
                        <button @click="modalPersonalizado = false"
                            class="text-gray-400 hover:text-gray-700 dark:hover:text-white text-2xl leading-none transition-colors">✕</button>
                    </div>

                    <!-- Filtros de carga -->
                    <div class="p-6 border-b border-gray-100 dark:border-gray-800 flex-shrink-0 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Evento *</label>
                                <select v-model="eventoSelPersonalizado" @change="cargarParticipantes"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500">
                                    <option value="">Selecciona un evento</option>
                                    <option v-for="e in eventos" :key="e.id" :value="e.id">{{ e.nombre }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Plantilla</label>
                                <select v-model="plantillaPersonalizada"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500">
                                    <option value="">Usar plantilla activa</option>
                                    <option v-for="p in plantillas" :key="p.id" :value="p.id">
                                        {{ p.nombre }}{{ p.activa ? ' ✓' : '' }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Filtros de lista -->
                        <div v-if="participantes.length > 0" class="flex gap-3">
                            <input v-model="busquedaPart" type="text" placeholder="Buscar por nombre o correo..."
                                class="flex-1 text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500" />
                            <select v-model="filtroTipo"
                                class="text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500">
                                <option value="">Todos</option>
                                <option value="proveedor">Proveedores</option>
                                <option value="comprador">Compradores</option>
                            </select>
                        </div>
                    </div>

                    <!-- Lista de participantes -->
                    <div class="flex-1 overflow-y-auto">
                        <div v-if="cargandoPart" class="flex items-center justify-center py-16">
                            <div class="w-8 h-8 border-2 border-guinda-600 border-t-transparent rounded-full animate-spin"></div>
                        </div>

                        <div v-else-if="!eventoSelPersonalizado" class="text-center py-16 text-gray-400 dark:text-gray-600 text-sm">
                            Selecciona un evento para ver los participantes
                        </div>

                        <div v-else-if="participantesFiltrados.length === 0" class="text-center py-16 text-gray-400 dark:text-gray-600 text-sm">
                            No hay participantes con los filtros seleccionados
                        </div>

                        <div v-else>
                            <div class="sticky top-0 bg-white/95 dark:bg-gray-900/95 backdrop-blur px-6 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input type="checkbox" :checked="todosSeleccionados" @change="toggleTodos"
                                        class="w-4 h-4 accent-guinda-700 rounded" />
                                    <span class="text-gray-600 dark:text-gray-300 text-sm">
                                        Seleccionar todos ({{ participantesFiltrados.length }})
                                    </span>
                                </label>
                                <span class="text-guinda-700 dark:text-guinda-400 text-sm font-medium">
                                    {{ seleccionados.length }} seleccionado(s)
                                </span>
                            </div>

                            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                                <label v-for="p in participantesFiltrados" :key="p.user_id"
                                    class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer transition-colors"
                                    :class="p.ya_enviada ? 'opacity-50' : ''">
                                    <input type="checkbox" :value="p.user_id" v-model="seleccionados" :disabled="p.ya_enviada"
                                        class="w-4 h-4 accent-guinda-700 rounded flex-shrink-0" />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span class="text-gray-900 dark:text-white text-sm font-medium truncate">{{ p.nombre_negocio }}</span>
                                            <span v-if="p.ya_enviada"
                                                class="text-[10px] px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-400 rounded-full flex-shrink-0">
                                                ya enviada
                                            </span>
                                        </div>
                                        <p class="text-gray-400 dark:text-gray-500 text-xs truncate">{{ p.email }}</p>
                                    </div>
                                    <span class="flex-shrink-0 text-xs px-2 py-0.5 rounded-full font-medium"
                                        :class="p.tipo === 'proveedor'
                                            ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400'
                                            : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'">
                                        {{ p.tipo === 'proveedor' ? '🏭 Proveedor' : '🛒 Comprador' }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-6 border-t border-gray-100 dark:border-gray-800 flex-shrink-0 flex items-center justify-between gap-4">
                        <p class="text-gray-400 dark:text-gray-500 text-xs">
                            Los participantes con "ya enviada" no volverán a recibir la encuesta.
                        </p>
                        <button @click="enviarPersonalizado" :disabled="seleccionados.length === 0"
                            class="px-6 py-2 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-xl font-semibold text-sm transition-colors flex items-center gap-2 shrink-0">
                            Enviar a {{ seleccionados.length }} persona(s)
                        </button>
                    </div>

                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
