<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const props = defineProps({ tvToken: String });

// ── Estado ────────────────────────────────────────────────────────
const enCurso     = ref([]);
const llamando    = ref([]);
const proximas    = ref([]);
const recientes   = ref([]);
const timestamp   = ref('');
const fechaActual = ref('');
const horaActual  = ref('');
const ahoraMs     = ref(Date.now());

// Anuncio fullscreen
const anuncio        = ref(null);   // la cita siendo anunciada
const anuncioVisible = ref(false);
const anuncioCount   = ref(8);      // countdown
const anunciosVisto  = ref(new Set()); // cita.id + llamada_ts para no repetir

let timerReloj   = null;
let timerCitas   = null;
let timerAnuncio = null;
let anuncioTimer = null;

// ── Reloj ─────────────────────────────────────────────────────────
const actualizarReloj = () => {
    const a = new Date();
    ahoraMs.value   = a.getTime();
    horaActual.value = a.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    fechaActual.value = a.toLocaleDateString('es-MX', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
};

// ── Sonido ────────────────────────────────────────────────────────
const playSound = () => {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        [[880, 0], [1100, 0.3], [880, 0.6], [1320, 0.9]].forEach(([freq, t]) => {
            const osc  = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.frequency.value = freq; osc.type = 'sine';
            const st = ctx.currentTime + t;
            gain.gain.setValueAtTime(0.55, st);
            gain.gain.exponentialRampToValueAtTime(0.001, st + 0.22);
            osc.start(st); osc.stop(st + 0.22);
        });
    } catch {}
};

// ── Polling datos ────────────────────────────────────────────────
const cargar = async () => {
    try {
        const { data } = await axios.get(`/api/tv/${props.tvToken}/publico`);
        enCurso.value    = data.en_curso;
        proximas.value   = data.proximas;
        recientes.value  = data.recientes;
        timestamp.value  = data.timestamp;

        // Detectar citas llamando nuevas
        const nuevas = (data.llamando ?? []).filter(c => {
            const key = `${c.id}-${c.llamada_ts}`;
            return !anunciosVisto.value.has(key);
        });

        if (nuevas.length > 0 && !anuncioVisible.value) {
            const c = nuevas[0];
            const key = `${c.id}-${c.llamada_ts}`;
            anunciosVisto.value.add(key);
            mostrarAnuncio(c);
        }
        llamando.value = data.llamando ?? [];
    } catch {}
};

// ── Anuncio fullscreen ────────────────────────────────────────────
const mostrarAnuncio = (cita) => {
    anuncio.value        = cita;
    anuncioVisible.value = true;
    anuncioCount.value   = 8;
    playSound();

    clearInterval(anuncioTimer);
    anuncioTimer = setInterval(() => {
        anuncioCount.value--;
        if (anuncioCount.value <= 0) {
            cerrarAnuncio();
        }
    }, 1000);
};

const cerrarAnuncio = () => {
    anuncioVisible.value = false;
    clearInterval(anuncioTimer);
};

// ── Tiempo transcurrido ───────────────────────────────────────────
const tiempoTranscurrido = (cita) => {
    const elapsed = Math.max(0, Math.floor((ahoraMs.value / 1000) - (cita.elapsed_sec ?? 0)));
    // elapsed_sec es el elapsed en el momento del poll; lo sumamos al tiempo pasado desde el poll
    const totalElapsed = cita.elapsed_sec + Math.floor((ahoraMs.value - Date.now() + ahoraMs.value) / 1000);
    const e = cita.elapsed_sec;
    const min = Math.floor(e / 60);
    const seg = e % 60;
    return `${String(min).padStart(2,'0')}:${String(seg).padStart(2,'0')}`;
};

const progreso = (cita) => Math.min(100, (cita.elapsed_sec / Math.max(1, cita.duracion_sec)) * 100);

// ── Ciclo de vida ─────────────────────────────────────────────────
onMounted(() => {
    actualizarReloj();
    cargar();
    timerReloj = setInterval(actualizarReloj, 1000);
    timerCitas = setInterval(cargar, 3000);
});
onUnmounted(() => {
    clearInterval(timerReloj);
    clearInterval(timerCitas);
    clearInterval(anuncioTimer);
});

const iniciales = (n) => n?.split(' ').slice(0,2).map(p=>p[0]).join('').toUpperCase() || '?';
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-white flex flex-col select-none overflow-hidden"
         style="font-family:'Segoe UI',Arial,sans-serif;">

        <!-- ANUNCIO FULLSCREEN ──────────────────────────────────── -->
        <Transition
            enter-active-class="transition-all duration-500"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition-all duration-500"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95">
            <div v-if="anuncioVisible && anuncio"
                 class="fixed inset-0 z-50 flex flex-col items-center justify-center"
                 style="background: radial-gradient(ellipse at center, rgba(139,16,40,0.35) 0%, rgba(3,7,18,0.97) 70%), #030712;">

                <!-- Countdown ring -->
                <div class="absolute top-8 right-8 text-right">
                    <div class="text-5xl font-black text-guinda-400/80 tabular-nums leading-none">{{ anuncioCount }}</div>
                    <p class="text-xs text-gray-600 mt-1">seg.</p>
                </div>

                <!-- Skip -->
                <button @click="cerrarAnuncio"
                    class="absolute bottom-8 right-8 text-xs text-gray-600 hover:text-gray-400 border border-gray-700 rounded-lg px-3 py-1.5 transition-colors">
                    Cerrar anuncio
                </button>

                <!-- Contenido del anuncio -->
                <div class="text-center px-8 max-w-4xl w-full">
                    <!-- Label ATENCIÓN -->
                    <div class="flex items-center justify-center gap-3 mb-6">
                        <div class="h-px flex-1 bg-guinda-700/60"></div>
                        <span class="text-sm font-black uppercase tracking-[0.4em] text-guinda-400">⚠ Atención</span>
                        <div class="h-px flex-1 bg-guinda-700/60"></div>
                    </div>

                    <p class="text-2xl font-black uppercase tracking-widest text-white/60 mb-4">Nueva Cita</p>

                    <!-- Mesa grande -->
                    <div class="inline-flex items-center justify-center bg-guinda-800/40 border-2 border-guinda-600/60 rounded-3xl px-10 py-4 mb-8">
                        <span class="text-6xl font-black text-white tracking-tight">{{ anuncio.mesa }}</span>
                    </div>

                    <!-- Participantes -->
                    <div class="flex items-center justify-center gap-8 mb-8">
                        <!-- Comprador -->
                        <div class="text-center">
                            <div class="w-20 h-20 rounded-2xl bg-guinda-900/60 border border-guinda-700/50 flex items-center justify-center text-guinda-200 font-black text-3xl mx-auto mb-3">
                                {{ iniciales(anuncio.comprador) }}
                            </div>
                            <p class="text-xs text-guinda-400/70 font-bold uppercase tracking-widest mb-1">Comprador</p>
                            <p class="text-2xl font-black text-white leading-tight">{{ anuncio.comprador }}</p>
                        </div>

                        <!-- Icono -->
                        <div class="text-5xl text-guinda-600/80 font-thin">↔</div>

                        <!-- Proveedor -->
                        <div class="text-center">
                            <div class="w-20 h-20 rounded-2xl bg-guinda-900/60 border border-guinda-700/50 flex items-center justify-center text-guinda-200 font-black text-3xl mx-auto mb-3">
                                {{ iniciales(anuncio.proveedor) }}
                            </div>
                            <p class="text-xs text-guinda-400/70 font-bold uppercase tracking-widest mb-1">Proveedor</p>
                            <p class="text-2xl font-black text-white leading-tight">{{ anuncio.proveedor }}</p>
                            <p v-if="anuncio.categoria" class="text-sm text-gray-500 mt-1">{{ anuncio.categoria }}</p>
                        </div>
                    </div>

                    <!-- Indicación -->
                    <p class="text-lg text-gray-300 font-medium">
                        Favor de dirigirse a su mesa asignada.
                    </p>
                    <p class="text-sm text-gray-600 mt-2">Hora programada: {{ anuncio.hora_inicio }}</p>
                </div>

                <!-- Línea animada inferior -->
                <div class="absolute bottom-0 left-0 h-1 bg-guinda-600/80" :style="{ width: ((8 - anuncioCount) / 8 * 100) + '%', transition: 'width 1s linear' }"></div>
            </div>
        </Transition>

        <!-- HEADER ──────────────────────────────────────────────── -->
        <header class="bg-gray-900/95 border-b border-gray-800/60 px-8 py-3 flex items-center justify-between shrink-0 backdrop-blur-sm">
            <div class="flex items-center gap-5">
                <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-9 w-auto"
                     style="filter:brightness(0) invert(1);" />
                <div class="border-l border-gray-700 pl-5">
                    <p class="text-white font-black text-sm leading-tight">Encuentro de Negocios Impulsate</p>
                    <p class="text-gray-400 text-xs capitalize">{{ fechaActual }}</p>
                </div>
            </div>
            <div class="text-3xl font-black text-white tabular-nums tracking-tight">{{ horaActual }}</div>
        </header>

        <!-- BODY ────────────────────────────────────────────────── -->
        <main class="flex-1 flex gap-0 overflow-hidden min-h-0">

            <!-- Área principal -->
            <section class="flex-1 overflow-y-auto p-6 flex flex-col gap-6 min-h-0">

                <!-- Sin actividad -->
                <div v-if="enCurso.length === 0 && proximas.length === 0 && llamando.length === 0"
                     class="flex-1 flex flex-col items-center justify-center text-gray-600">
                    <svg class="w-20 h-20 mb-4 opacity-30" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-2xl font-bold text-gray-500">Sin reuniones activas</p>
                    <p class="text-sm text-gray-700 mt-1">Las reuniones en curso aparecerán aquí</p>
                </div>

                <!-- REUNIONES EN CURSO ──────────────────────── -->
                <div v-if="enCurso.length > 0">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-2.5 h-2.5 rounded-full bg-guinda-500 animate-pulse"></div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-guinda-400">Reuniones en Curso</h2>
                        <span class="bg-guinda-500/15 text-guinda-400 text-xs font-bold px-2 py-0.5 rounded-full border border-guinda-500/20">{{ enCurso.length }}</span>
                    </div>
                    <div class="grid gap-5"
                         :class="enCurso.length === 1 ? 'grid-cols-1 max-w-2xl' : enCurso.length === 2 ? 'grid-cols-2' : 'grid-cols-2 xl:grid-cols-3'">
                        <div v-for="cita in enCurso" :key="cita.id"
                             class="relative rounded-2xl p-5 border border-guinda-700/30 overflow-hidden"
                             style="background: linear-gradient(135deg, rgba(139,16,40,0.13) 0%, rgba(80,8,22,0.06) 100%);">
                            <div class="absolute inset-0 rounded-2xl pointer-events-none"
                                 style="box-shadow: inset 0 0 40px rgba(139,16,40,0.08);"></div>

                            <!-- Mesa badge -->
                            <div class="flex items-center justify-between mb-3 relative z-10">
                                <span class="inline-flex items-center gap-1.5 bg-guinda-800/50 border border-guinda-700/50 rounded-full px-3 py-1 text-sm font-black text-guinda-200">
                                    <span class="w-2 h-2 rounded-full bg-guinda-400 animate-pulse"></span>
                                    {{ cita.mesa }}
                                </span>
                                <span class="text-xs text-gray-500 font-mono">{{ cita.hora_real || cita.hora_inicio }}</span>
                            </div>

                            <!-- Participantes -->
                            <div class="grid grid-cols-2 gap-4 mb-4 relative z-10">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-10 h-10 rounded-xl bg-guinda-900/50 border border-guinda-700/40 flex items-center justify-center text-guinda-200 font-black text-base shrink-0">
                                        {{ iniciales(cita.comprador) }}
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-guinda-500 font-bold uppercase tracking-wider">Comprador</p>
                                        <p class="text-sm font-bold text-white leading-tight">{{ cita.comprador }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2.5">
                                    <div class="w-10 h-10 rounded-xl bg-guinda-900/50 border border-guinda-700/40 flex items-center justify-center text-guinda-200 font-black text-base shrink-0">
                                        {{ iniciales(cita.proveedor) }}
                                    </div>
                                    <div>
                                        <p class="text-[10px] text-guinda-500 font-bold uppercase tracking-wider">Proveedor</p>
                                        <p class="text-sm font-bold text-white leading-tight">{{ cita.proveedor }}</p>
                                        <p v-if="cita.categoria" class="text-xs text-gray-600">{{ cita.categoria }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Progreso -->
                            <div class="relative z-10">
                                <div class="w-full h-1.5 bg-guinda-950/60 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full"
                                         style="background: linear-gradient(90deg, #8b1028, #c0394e); transition: width 3s linear;"
                                         :style="{ width: progreso(cita) + '%' }"></div>
                                </div>
                                <div class="flex items-center justify-between mt-1.5">
                                    <span class="text-xs text-guinda-500">En curso</span>
                                    <span class="text-xs font-mono text-guinda-400">{{ tiempoTranscurrido(cita) }} transcurridos</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- LLAMANDO (en espera de iniciar) ────────────── -->
                <div v-if="llamando.length > 0">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-amber-400">Favor de dirigirse a su mesa</h2>
                    </div>
                    <div class="flex gap-4 flex-wrap">
                        <div v-for="cita in llamando" :key="'ll-'+cita.id"
                             class="bg-gray-900 border border-amber-700/40 rounded-2xl p-4 flex items-center gap-5">
                            <span class="text-2xl font-black text-amber-300">{{ cita.mesa }}</span>
                            <div class="text-sm">
                                <p class="font-bold text-white">{{ cita.comprador }}</p>
                                <p class="text-gray-400">{{ cita.proveedor }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PRÓXIMAS CITAS ──────────────────────────── -->
                <div v-if="proximas.length > 0">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-2 h-2 rounded-full bg-gray-500"></div>
                        <h2 class="text-sm font-black uppercase tracking-widest text-gray-400">Próximas Citas</h2>
                    </div>
                    <div class="bg-gray-900/60 border border-gray-800/60 rounded-2xl overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-800/60 bg-gray-900/80">
                                    <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Hora</th>
                                    <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Mesa</th>
                                    <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Comprador</th>
                                    <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Proveedor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in proximas" :key="'prox-'+c.id"
                                    class="border-b border-gray-800/40 hover:bg-gray-800/30 transition-colors">
                                    <td class="px-4 py-3 font-mono font-bold text-white">{{ c.hora }}</td>
                                    <td class="px-4 py-3">
                                        <span class="text-xs font-semibold text-gray-400 bg-gray-800 px-2 py-0.5 rounded">{{ c.mesa !== '—' ? c.mesa : '—' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-300 font-medium">{{ c.comprador }}</td>
                                    <td class="px-4 py-3 text-gray-400 hidden lg:table-cell">{{ c.proveedor }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>

            <!-- SIDEBAR DERECHA ──────────────────────────────────── -->
            <aside class="w-64 xl:w-72 bg-gray-900/60 border-l border-gray-800/60 flex flex-col shrink-0 overflow-hidden">

                <!-- Contador actualización -->
                <div class="px-5 py-3 border-b border-gray-800/60 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                        <span class="text-xs text-gray-500">Actualizado: {{ timestamp }}</span>
                    </div>
                </div>

                <!-- Stats rápidos -->
                <div class="px-5 py-3 border-b border-gray-800/60 grid grid-cols-2 gap-3">
                    <div class="text-center">
                        <p class="text-2xl font-black text-guinda-400">{{ enCurso.length }}</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">En curso</p>
                    </div>
                    <div class="text-center">
                        <p class="text-2xl font-black text-gray-400">{{ proximas.length }}</p>
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider">Próximas</p>
                    </div>
                </div>

                <!-- Próxima cita llamada -->
                <div v-if="proximas[0]" class="px-5 py-3 border-b border-gray-800/60">
                    <p class="text-[10px] text-gray-600 font-bold uppercase tracking-wider mb-2">Próxima llamada</p>
                    <p class="text-xl font-black text-white tabular-nums">{{ proximas[0].hora }}</p>
                    <p class="text-xs text-gray-400 truncate mt-0.5">{{ proximas[0].comprador }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ proximas[0].proveedor }}</p>
                </div>

                <!-- Últimas citas llamadas / recientes -->
                <div class="flex-1 overflow-y-auto">
                    <p class="px-5 pt-3 pb-2 text-[10px] text-gray-600 font-bold uppercase tracking-wider sticky top-0 bg-gray-900/60">Últimas atendidas</p>
                    <div v-if="recientes.length === 0" class="px-5 py-3 text-xs text-gray-700 italic">Sin atendidas aún</div>
                    <div v-for="c in recientes" :key="'rec-'+c.id"
                         class="px-5 py-2.5 border-b border-gray-800/40 hover:bg-gray-800/20 transition-colors">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-base font-black text-gray-400 tabular-nums">{{ c.hora }}</span>
                            <span class="text-xs text-gray-600">{{ c.mesa }}</span>
                        </div>
                        <p class="text-xs font-semibold text-gray-300 truncate">{{ c.comprador }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ c.proveedor }}</p>
                    </div>
                </div>
            </aside>

        </main>

        <!-- FOOTER ──────────────────────────────────────────────── -->
        <footer class="bg-gray-900/80 border-t border-gray-800/60 px-8 py-2 flex items-center justify-between shrink-0">
            <p class="text-xs text-gray-700">Encuentro de Negocios Impulsate · Instituto Yucateco de Emprendedores</p>
            <p class="text-xs text-gray-700">Pantalla Pública · Actualización cada 3s</p>
        </footer>

    </div>
</template>

<style scoped>
@keyframes shimmer {
    0%   { background-position: -200% 0; }
    100% { background-position:  200% 0; }
}
</style>
