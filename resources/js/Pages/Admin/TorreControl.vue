<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({ numMesas: { type: Number, default: 10 } });

// ── Configuración de número de mesas ────────────────────────────────
const formMesas = useForm({ num_mesas: props.numMesas });
const guardarMesas = () => {
    formMesas.patch(route('admin.config.mesas'), { preserveScroll: true });
};

// ── Estado ────────────────────────────────────────────────────────
const citas      = ref([]);
const timestamp  = ref('');
const mesasOcup  = ref([]);
let   timer      = null;

// Modal llamar / cambiar mesa
const modalMesa       = ref(false);
const citaParaMesa    = ref(null);
const mesaSeleccionada = ref('');
const accionPendiente = ref('llamar'); // 'llamar' | 'cambiar'

// Estado de procesando
const procesando = ref({});

// ── Mesas disponibles ─────────────────────────────────────────────
const mesas = computed(() =>
    Array.from({ length: props.numMesas }, (_, i) => `Mesa ${i + 1}`)
);

// ── Cargar datos ──────────────────────────────────────────────────
const cargar = async () => {
    try {
        const { data } = await axios.get(route('admin.torre.estado'));
        citas.value     = data.citas;
        timestamp.value = data.timestamp;
        mesasOcup.value = data.mesas_ocupadas;
    } catch {}
};

// ── Stats ─────────────────────────────────────────────────────────
const citasEnCurso   = computed(() => citas.value.filter(c => c.estado_tv === 'en_curso').length);
const citasLlamando  = computed(() => citas.value.filter(c => c.estado_tv === 'llamando').length);
const mesasOcupadas  = computed(() => mesasOcup.value.length);
const mesasLibres    = computed(() => props.numMesas - mesasOcupadas.value);
const proximaCita    = computed(() => citas.value.find(c => c.estado_tv === 'pendiente'));

// ── Colores estado ────────────────────────────────────────────────
const colorEstado = {
    pendiente:    'bg-gray-500/15 text-gray-400 border-gray-500/20',
    llamando:     'bg-amber-500/20 text-amber-300 border-amber-500/30 animate-pulse',
    en_curso:     'bg-guinda-500/20 text-guinda-300 border-guinda-500/30',
    finalizada:   'bg-emerald-500/15 text-emerald-400 border-emerald-500/20',
    ausente:      'bg-red-500/15 text-red-400 border-red-500/20',
    reprogramada: 'bg-guinda-500/15 text-guinda-400 border-guinda-500/20',
};
const labelEstado = {
    pendiente:    'Pendiente',
    llamando:     '📣 Llamando',
    en_curso:     '▶ En curso',
    finalizada:   '✓ Finalizada',
    ausente:      '✗ Ausente',
    reprogramada: '↻ Reprogramada',
};

// ── Helpers ───────────────────────────────────────────────────────
const fmtMesa = (mesa) => mesa?.replace('Mesa ', 'M-') ?? '—';
const isBusy = (id) => !!procesando.value[id];

// ── Acciones ──────────────────────────────────────────────────────
const accion = async (endpoint, cita, payload = {}) => {
    if (isBusy(cita.id)) return;
    procesando.value[cita.id] = true;
    try {
        const { data } = await axios.post(endpoint, payload);
        if (data.cita) {
            const idx = citas.value.findIndex(c => c.id === cita.id);
            if (idx !== -1) citas.value[idx] = data.cita;
        }
    } catch (e) {
        alert('Error: ' + (e.response?.data?.message ?? e.message));
    } finally {
        procesando.value[cita.id] = false;
    }
};

const abrirModalLlamar = (cita) => {
    citaParaMesa.value     = cita;
    mesaSeleccionada.value = cita.mesa || mesas.value[0];
    accionPendiente.value  = 'llamar';
    modalMesa.value        = true;
};

const abrirModalCambiarMesa = (cita) => {
    citaParaMesa.value     = cita;
    mesaSeleccionada.value = cita.mesa || mesas.value[0];
    accionPendiente.value  = 'cambiar';
    modalMesa.value        = true;
};

const confirmarMesa = async () => {
    if (!mesaSeleccionada.value || !citaParaMesa.value) return;
    const cita = citaParaMesa.value;
    modalMesa.value = false;
    if (accionPendiente.value === 'llamar') {
        await accion(route('admin.torre.llamar', cita.id), cita, { mesa: mesaSeleccionada.value });
    } else {
        await accion(route('admin.torre.mesa', cita.id), cita, { mesa: mesaSeleccionada.value });
    }
};

// ── Ciclo de vida ─────────────────────────────────────────────────
onMounted(() => {
    cargar();
    timer = setInterval(cargar, 3000);
});
onUnmounted(() => clearInterval(timer));

// ── Estado mesa (para el grid inferior) ──────────────────────────
const estadoMesa = (mesaNombre) => {
    const c = citas.value.find(c => c.mesa === mesaNombre);
    if (!c) return { estado: 'libre', cita: null };
    return { estado: c.estado_tv, cita: c };
};

const coloresMesa = {
    libre:      'bg-gray-800 border-gray-700 text-gray-500',
    pendiente:  'bg-gray-800 border-gray-700 text-gray-400',
    llamando:   'bg-amber-900/40 border-amber-600/60 text-amber-300',
    en_curso:   'bg-guinda-900/40 border-guinda-600/60 text-guinda-300',
    finalizada: 'bg-emerald-900/30 border-emerald-700/50 text-emerald-400',
    ausente:    'bg-red-900/30 border-red-700/50 text-red-400',
};
</script>

<template>
    <AdminLayout title="Torre de Control">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Torre de Control</h1>
                    <p class="text-xs text-gray-500 mt-0.5">Centro operativo del evento · Actualiza cada 3s · {{ timestamp }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-2 bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 px-3 py-1.5">
                        <span class="text-xs font-bold text-gray-700 dark:text-gray-300 whitespace-nowrap">🪑 Mesas:</span>
                        <input
                            v-model.number="formMesas.num_mesas"
                            type="number"
                            min="1"
                            max="500"
                            class="w-16 border border-gray-300 dark:border-gray-600 rounded-lg px-2 py-0.5 text-sm text-center font-bold bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-guinda-500"
                        />
                        <button @click="guardarMesas"
                            :disabled="formMesas.processing"
                            class="px-3 py-1 bg-guinda-800 hover:bg-guinda-700 text-white text-xs font-bold rounded-lg transition-colors disabled:opacity-60">
                            Guardar
                        </button>
                        <span v-if="formMesas.recentlySuccessful" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold">✓</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                        <span class="text-xs text-emerald-500 font-semibold">EN VIVO</span>
                    </div>
                </div>
            </div>
        </template>

        <div class="space-y-5">

            <!-- ── STATS ────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-guinda-100 dark:bg-guinda-900/30 border border-guinda-200 dark:border-guinda-800 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">En curso</p>
                        <p class="text-2xl font-black text-guinda-700 dark:text-guinda-400">{{ citasEnCurso }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Llamando</p>
                        <p class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ citasLlamando }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex items-center gap-3 shadow-sm">
                    <div class="w-10 h-10 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 flex items-center justify-center shrink-0">
                        <span class="text-base font-black text-red-600 dark:text-red-400">{{ mesasOcupadas }}</span>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 font-medium">Mesas ocupadas</p>
                        <p class="text-2xl font-black text-gray-900 dark:text-white">{{ mesasOcupadas }}<span class="text-sm font-normal text-gray-400">/{{ numMesas }}</span></p>
                    </div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-sm">
                    <p class="text-xs text-gray-500 font-medium mb-1">Próxima cita</p>
                    <template v-if="proximaCita">
                        <p class="text-lg font-black text-gray-900 dark:text-white">{{ proximaCita.hora_prog }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ proximaCita.comprador }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ proximaCita.proveedor }}</p>
                    </template>
                    <p v-else class="text-sm text-gray-400 italic">Sin pendientes</p>
                </div>
            </div>

            <!-- ── TABLA PRINCIPAL ───────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800/30 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="font-bold text-gray-900 dark:text-white text-sm">Citas del día</h2>
                    <span class="text-xs text-gray-400">{{ citas.length }} citas</span>
                </div>

                <div v-if="citas.length === 0" class="py-12 text-center text-gray-400 dark:text-gray-600">
                    <p class="font-medium">No hay citas para hoy</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800/60 bg-gray-50 dark:bg-gray-800/20">
                                <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Hora prog.</th>
                                <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Hora real</th>
                                <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Mesa</th>
                                <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Comprador</th>
                                <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider hidden xl:table-cell">Proveedor</th>
                                <th class="text-center px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Estado</th>
                                <th class="text-right px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cita in citas" :key="cita.id"
                                class="border-b border-gray-100 dark:border-gray-800/40 transition-colors"
                                :class="{
                                    'bg-amber-50/50 dark:bg-amber-950/10': cita.estado_tv === 'llamando',
                                    'bg-guinda-50/30 dark:bg-guinda-950/10': cita.estado_tv === 'en_curso',
                                    'bg-gray-50/50 dark:bg-gray-800/20': cita.estado_tv === 'finalizada',
                                    'hover:bg-gray-50 dark:hover:bg-gray-800/30': !['llamando','en_curso'].includes(cita.estado_tv),
                                }">
                                <!-- Hora programada -->
                                <td class="px-4 py-3 font-mono font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                    {{ cita.hora_prog }}
                                    <span class="text-gray-400 font-normal">–{{ cita.hora_fin_prog }}</span>
                                </td>
                                <!-- Hora real -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div v-if="cita.hora_real_inicio" class="font-mono text-guinda-700 dark:text-guinda-400 font-semibold text-xs">
                                        {{ cita.hora_real_inicio }}
                                        <span v-if="cita.hora_real_fin"> – {{ cita.hora_real_fin }}</span>
                                    </div>
                                    <span v-else class="text-gray-400 text-xs">—</span>
                                </td>
                                <!-- Mesa -->
                                <td class="px-4 py-3">
                                    <span v-if="cita.mesa"
                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border"
                                        :class="cita.estado_tv === 'en_curso' ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 border-guinda-200 dark:border-guinda-800' : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700'">
                                        {{ fmtMesa(cita.mesa) }}
                                    </span>
                                    <span v-else class="text-gray-400 text-xs italic">Sin asignar</span>
                                </td>
                                <!-- Comprador -->
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-900 dark:text-white truncate max-w-[140px]">{{ cita.comprador }}</p>
                                    <p v-if="cita.comprador_tel" class="text-xs text-gray-400">{{ cita.comprador_tel }}</p>
                                </td>
                                <!-- Proveedor -->
                                <td class="px-4 py-3 hidden xl:table-cell">
                                    <p class="text-gray-700 dark:text-gray-300 truncate max-w-[140px]">{{ cita.proveedor }}</p>
                                    <p v-if="cita.categoria" class="text-xs text-gray-400">{{ cita.categoria }}</p>
                                </td>
                                <!-- Estado -->
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold border whitespace-nowrap"
                                          :class="colorEstado[cita.estado_tv] || colorEstado.pendiente">
                                        {{ labelEstado[cita.estado_tv] || cita.estado_tv }}
                                    </span>
                                </td>
                                <!-- Acciones -->
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                        <!-- Llamar -->
                                        <button v-if="cita.estado_tv === 'pendiente'"
                                            @click="abrirModalLlamar(cita)" :disabled="isBusy(cita.id)"
                                            class="px-2.5 py-1.5 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-white text-xs font-bold rounded-lg transition-colors whitespace-nowrap">
                                            📣 Llamar
                                        </button>
                                        <!-- Repetir anuncio -->
                                        <button v-if="cita.estado_tv === 'llamando'"
                                            @click="accion(route('admin.torre.repetir', cita.id), cita)" :disabled="isBusy(cita.id)"
                                            class="px-2.5 py-1.5 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white text-xs font-bold rounded-lg transition-colors whitespace-nowrap">
                                            🔁 Repetir
                                        </button>
                                        <!-- Iniciar -->
                                        <button v-if="['llamando'].includes(cita.estado_tv)"
                                            @click="accion(route('admin.torre.iniciar', cita.id), cita)" :disabled="isBusy(cita.id)"
                                            class="px-2.5 py-1.5 bg-guinda-700 hover:bg-guinda-600 disabled:opacity-50 text-white text-xs font-bold rounded-lg transition-colors whitespace-nowrap">
                                            ▶ Iniciar
                                        </button>
                                        <!-- Finalizar -->
                                        <button v-if="cita.estado_tv === 'en_curso'"
                                            @click="accion(route('admin.torre.finalizar', cita.id), cita)" :disabled="isBusy(cita.id)"
                                            class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold rounded-lg transition-colors whitespace-nowrap">
                                            ■ Finalizar
                                        </button>
                                        <!-- Retrasar -->
                                        <div v-if="['pendiente','llamando'].includes(cita.estado_tv)" class="flex gap-1">
                                            <button @click="accion(route('admin.torre.retrasar', cita.id), cita, {minutos:5})" :disabled="isBusy(cita.id)"
                                                class="px-2 py-1.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-bold rounded-lg transition-colors">
                                                +5'
                                            </button>
                                            <button @click="accion(route('admin.torre.retrasar', cita.id), cita, {minutos:10})" :disabled="isBusy(cita.id)"
                                                class="px-2 py-1.5 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 text-xs font-bold rounded-lg transition-colors">
                                                +10'
                                            </button>
                                        </div>
                                        <!-- Cambiar mesa -->
                                        <button v-if="cita.mesa && cita.estado_tv !== 'finalizada'"
                                            @click="abrirModalCambiarMesa(cita)" :disabled="isBusy(cita.id)"
                                            class="px-2.5 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 text-xs font-bold rounded-lg transition-colors whitespace-nowrap">
                                            ⬡ Mesa
                                        </button>
                                        <!-- Ausente -->
                                        <button v-if="['pendiente','llamando'].includes(cita.estado_tv)"
                                            @click="accion(route('admin.torre.ausente', cita.id), cita)" :disabled="isBusy(cita.id)"
                                            class="px-2.5 py-1.5 bg-red-100 dark:bg-red-900/20 hover:bg-red-200 dark:hover:bg-red-900/40 text-red-600 dark:text-red-400 text-xs font-bold rounded-lg transition-colors whitespace-nowrap">
                                            ✗ Ausente
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ── GRID DE MESAS ─────────────────────────────────────── -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                <h2 class="font-bold text-gray-900 dark:text-white text-sm mb-4">Estado de Mesas</h2>
                <div class="grid grid-cols-5 sm:grid-cols-10 gap-3">
                    <div v-for="mesa in mesas" :key="mesa"
                        class="rounded-xl border-2 p-2 text-center cursor-default transition-colors"
                        :class="coloresMesa[estadoMesa(mesa).estado] || coloresMesa.libre">
                        <p class="text-xs font-bold">{{ mesa.replace('Mesa ', 'M') }}</p>
                        <p class="text-[10px] mt-0.5 truncate leading-tight">
                            <template v-if="estadoMesa(mesa).cita">
                                {{ estadoMesa(mesa).cita.comprador.split(' ')[0] }}
                            </template>
                            <template v-else>Libre</template>
                        </p>
                        <div class="mt-1 w-1.5 h-1.5 rounded-full mx-auto"
                            :class="{
                                'bg-gray-600': estadoMesa(mesa).estado === 'libre',
                                'bg-amber-400 animate-pulse': estadoMesa(mesa).estado === 'llamando',
                                'bg-guinda-500 animate-pulse': estadoMesa(mesa).estado === 'en_curso',
                                'bg-emerald-500': estadoMesa(mesa).estado === 'finalizada',
                                'bg-red-500': estadoMesa(mesa).estado === 'ausente',
                            }"></div>
                    </div>
                </div>
                <div class="flex items-center gap-6 mt-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-gray-600"></span>Libre</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-amber-400"></span>Llamando</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-guinda-500"></span>En curso</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span>Finalizada</span>
                    <span class="flex items-center gap-1.5"><span class="w-2 h-2 rounded-full bg-red-500"></span>Ausente</span>
                </div>
            </div>
        </div>

        <!-- Modal: Asignar Mesa ──────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="modalMesa" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background:rgba(0,0,0,0.6)" @click.self="modalMesa=false">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl w-full max-w-sm shadow-2xl">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                        <h3 class="font-bold text-gray-900 dark:text-white">
                            {{ accionPendiente === 'llamar' ? '📣 Asignar mesa y llamar' : '⬡ Cambiar mesa' }}
                        </h3>
                        <p v-if="citaParaMesa" class="text-xs text-gray-500 mt-0.5">
                            {{ citaParaMesa.comprador }} — {{ citaParaMesa.proveedor }}
                        </p>
                    </div>
                    <div class="p-5">
                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Selecciona la mesa:</p>
                        <div class="grid grid-cols-5 gap-2">
                            <button v-for="mesa in mesas" :key="mesa"
                                @click="mesaSeleccionada = mesa"
                                class="py-2 rounded-xl border-2 text-xs font-bold transition-all"
                                :class="mesaSeleccionada === mesa
                                    ? 'bg-guinda-800 border-guinda-700 text-white shadow-sm'
                                    : 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:border-guinda-500'">
                                {{ mesa.replace('Mesa ', 'M') }}
                            </button>
                        </div>
                    </div>
                    <div class="px-5 pb-5 flex gap-3">
                        <button @click="modalMesa=false"
                            class="flex-1 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800">
                            Cancelar
                        </button>
                        <button @click="confirmarMesa" :disabled="!mesaSeleccionada"
                            class="flex-1 py-2.5 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 text-white rounded-xl text-sm font-bold">
                            {{ accionPendiente === 'llamar' ? 'Llamar ahora' : 'Cambiar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
