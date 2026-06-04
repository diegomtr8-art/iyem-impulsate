<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({ tvToken: String });

const citas = ref([]);
const updatedAt = ref('');
const horaActual = ref('');
const fechaActual = ref('');
const countdown = ref(30);
let intervaloReloj = null;
let intervaloCitas = null;
let intervaloCountdown = null;

const estadoConfig = {
    pendiente:  { label: 'Pendiente',   color: '#f59e0b', bg: 'rgba(245,158,11,0.15)', blink: false },
    confirmada: { label: 'Confirmada',  color: '#10b981', bg: 'rgba(16,185,129,0.15)', blink: false },
    en_curso:   { label: 'En curso',    color: '#3b82f6', bg: 'rgba(59,130,246,0.20)', blink: true  },
    completada: { label: 'Completada',  color: '#6b7280', bg: 'rgba(107,114,128,0.15)', blink: false },
    reagendada: { label: 'Reagendada', color: '#8b5cf6', bg: 'rgba(139,92,246,0.15)', blink: false },
};

const getCfg = (estado) => estadoConfig[estado] || estadoConfig.pendiente;

const actualizarReloj = () => {
    const ahora = new Date();
    horaActual.value = ahora.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    fechaActual.value = ahora.toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};

const cargarCitas = async () => {
    try {
        const { data } = await axios.get(`/api/tv/${props.tvToken}/citas`);
        citas.value   = data.citas;
        updatedAt.value = data.updated_at;
        countdown.value = 30;
    } catch {}
};

onMounted(() => {
    actualizarReloj();
    cargarCitas();

    intervaloReloj     = setInterval(actualizarReloj, 1000);
    intervaloCitas     = setInterval(cargarCitas, 30000);
    intervaloCountdown = setInterval(() => {
        countdown.value = Math.max(0, countdown.value - 1);
    }, 1000);
});

onUnmounted(() => {
    clearInterval(intervaloReloj);
    clearInterval(intervaloCitas);
    clearInterval(intervaloCountdown);
});
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-white flex flex-col" style="font-family:'Segoe UI',Arial,sans-serif;">

        <!-- Header -->
        <header class="bg-gray-900 border-b border-gray-800 px-8 py-4 flex items-center justify-between shrink-0">
            <div class="flex items-center gap-6">
                <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-12 w-auto" style="filter:brightness(0) invert(1);" />
                <div>
                    <h1 class="text-2xl font-black text-white leading-none">Panel de Citas</h1>
                    <p class="text-gray-400 text-sm mt-0.5" style="text-transform:capitalize;">{{ fechaActual }}</p>
                </div>
            </div>
            <div class="text-right">
                <div class="text-4xl font-black text-white tabular-nums">{{ horaActual }}</div>
                <div class="flex items-center justify-end gap-2 mt-1">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span class="text-xs text-gray-400">Actualiza en {{ countdown }}s</span>
                </div>
            </div>
        </header>

        <!-- Tabla -->
        <main class="flex-1 p-8">
            <!-- Sin citas -->
            <div v-if="citas.length === 0" class="flex flex-col items-center justify-center h-full text-gray-500">
                <div class="text-8xl mb-6">📅</div>
                <p class="text-2xl font-bold">No hay citas programadas para hoy</p>
            </div>

            <div v-else class="w-full overflow-hidden rounded-2xl border border-gray-800 shadow-2xl">
                <!-- Encabezado -->
                <div class="grid bg-gray-800 text-gray-300 text-sm font-bold uppercase tracking-widest"
                     style="grid-template-columns: 100px 1fr 1fr 160px;">
                    <div class="px-6 py-4">Hora</div>
                    <div class="px-6 py-4">Comprador</div>
                    <div class="px-6 py-4">Proveedor</div>
                    <div class="px-6 py-4 text-center">Estado</div>
                </div>

                <!-- Filas -->
                <div v-for="(cita, i) in citas" :key="cita.id"
                     class="grid border-t border-gray-800 transition-all"
                     :class="cita.estado === 'en_curso' ? 'bg-blue-900/20' : (i % 2 === 0 ? 'bg-gray-900' : 'bg-gray-900/60')"
                     style="grid-template-columns: 100px 1fr 1fr 160px;">

                    <!-- Hora -->
                    <div class="px-6 py-5 flex flex-col justify-center">
                        <span class="text-xl font-black text-white tabular-nums">{{ cita.hora_inicio }}</span>
                        <span class="text-sm text-gray-400 tabular-nums">{{ cita.hora_fin }}</span>
                    </div>

                    <!-- Comprador -->
                    <div class="px-6 py-5 flex items-center">
                        <div>
                            <div class="w-9 h-9 rounded-full bg-guinda-800/40 border border-guinda-700/50 flex items-center justify-center text-guinda-300 font-bold text-sm mr-3 inline-flex">
                                {{ cita.comprador?.charAt(0).toUpperCase() }}
                            </div>
                        </div>
                        <span class="text-lg font-semibold text-white">{{ cita.comprador }}</span>
                    </div>

                    <!-- Proveedor -->
                    <div class="px-6 py-5 flex items-center">
                        <span class="text-lg text-gray-200">{{ cita.proveedor }}</span>
                    </div>

                    <!-- Estado -->
                    <div class="px-6 py-5 flex items-center justify-center">
                        <div class="flex items-center gap-2 px-4 py-2 rounded-full text-sm font-bold"
                             :style="{ background: getCfg(cita.estado).bg, border: `1px solid ${getCfg(cita.estado).color}40`, color: getCfg(cita.estado).color }">
                            <span v-if="getCfg(cita.estado).blink"
                                  class="w-2 h-2 rounded-full animate-pulse"
                                  :style="{ background: getCfg(cita.estado).color }"></span>
                            {{ getCfg(cita.estado).label }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total -->
            <div class="mt-6 flex items-center justify-between text-gray-500 text-sm px-2">
                <span>{{ citas.length }} cita{{ citas.length !== 1 ? 's' : '' }} programadas hoy</span>
                <span>Última actualización: {{ updatedAt }}</span>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-900 border-t border-gray-800 px-8 py-3 text-center text-xs text-gray-600">
            Encuentro de Negocios Impulsate — Instituto Yucateco de Emprendedores — Gobierno del Estado de Yucatán
        </footer>
    </div>
</template>

<style scoped>
/* Optimizado para Full HD 1920x1080 */
@media (min-width: 1920px) {
    .text-xl { font-size: 1.5rem; }
    .text-lg { font-size: 1.25rem; }
    .text-4xl { font-size: 3.5rem; }
}
</style>
