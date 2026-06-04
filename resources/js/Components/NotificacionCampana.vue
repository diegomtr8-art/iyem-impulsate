<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const notificaciones = ref([]);
const noLeidas = ref(0);
const abierto = ref(false);
let intervalo = null;

const cargar = async () => {
    try {
        const { data } = await axios.get('/api/notificaciones');
        notificaciones.value = data.notificaciones;
        noLeidas.value = data.no_leidas;
    } catch {}
};

const marcarLeida = async (n) => {
    if (n.leida) return;
    n.leida = true;
    noLeidas.value = Math.max(0, noLeidas.value - 1);
    try { await axios.patch(`/api/notificaciones/${n.id}/leer`); } catch {}
};

const marcarTodas = async () => {
    notificaciones.value.forEach(n => { n.leida = true; });
    noLeidas.value = 0;
    try { await axios.post('/api/notificaciones/leer-todas'); } catch {}
};

const toggle = () => {
    abierto.value = !abierto.value;
    if (abierto.value) cargar();
};

const cerrar = () => { abierto.value = false; };

const tiempoRelativo = (fecha) => {
    const diff = (Date.now() - new Date(fecha)) / 1000;
    if (diff < 60)   return 'ahora';
    if (diff < 3600) return `${Math.floor(diff/60)}m`;
    if (diff < 86400) return `${Math.floor(diff/3600)}h`;
    return `${Math.floor(diff/86400)}d`;
};

const iconoTipo = (tipo) => {
    const iconos = {
        cita_nueva:       '📅',
        cita_aceptada:    '✅',
        cita_rechazada:   '❌',
        cita_reagendada:  '🔄',
        recordatorio_24h: '⏰',
        recordatorio_2h:  '🔔',
        info:             'ℹ️',
    };
    return iconos[tipo] || '🔔';
};

onMounted(() => {
    cargar();
    intervalo = setInterval(cargar, 30000);
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-campana]')) abierto.value = false;
    });
});

onUnmounted(() => { clearInterval(intervalo); });
</script>

<template>
    <div class="relative" data-campana>
        <!-- Botón campana -->
        <button @click="toggle"
            class="relative p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            <!-- Badge -->
            <span v-if="noLeidas > 0"
                class="absolute -top-0.5 -right-0.5 w-4 h-4 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center leading-none">
                {{ noLeidas > 9 ? '9+' : noLeidas }}
            </span>
        </button>

        <!-- Dropdown -->
        <Transition enter-active-class="transition ease-out duration-150" enter-from-class="opacity-0 translate-y-1" enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in duration-100" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="abierto"
                class="absolute right-0 mt-2 w-80 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xl shadow-black/10 dark:shadow-black/40 z-50 overflow-hidden">

                <!-- Header -->
                <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <span class="font-bold text-sm text-gray-900 dark:text-white">Notificaciones</span>
                    <button v-if="noLeidas > 0" @click="marcarTodas"
                        class="text-xs text-guinda-700 dark:text-guinda-400 hover:underline font-medium">
                        Marcar todas como leídas
                    </button>
                </div>

                <!-- Lista -->
                <div class="max-h-80 overflow-y-auto">
                    <div v-if="notificaciones.length === 0" class="py-10 text-center text-gray-400 dark:text-gray-600 text-sm">
                        <div class="text-2xl mb-2">🔔</div>
                        <p>Sin notificaciones</p>
                    </div>
                    <div v-for="n in notificaciones" :key="n.id" @click="marcarLeida(n)"
                        class="flex gap-3 px-4 py-3 cursor-pointer transition-colors border-b border-gray-100 dark:border-gray-800/50 last:border-0"
                        :class="n.leida ? 'hover:bg-gray-50 dark:hover:bg-gray-800/30' : 'bg-guinda-50/50 dark:bg-guinda-900/10 hover:bg-guinda-50 dark:hover:bg-guinda-900/20'">
                        <div class="text-lg shrink-0 mt-0.5">{{ iconoTipo(n.tipo) }}</div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ n.titulo }}</p>
                                <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">{{ tiempoRelativo(n.created_at) }}</span>
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ n.mensaje }}</p>
                        </div>
                        <div v-if="!n.leida" class="w-2 h-2 rounded-full bg-guinda-600 dark:bg-guinda-500 shrink-0 mt-1.5"></div>
                    </div>
                </div>

            </div>
        </Transition>
    </div>
</template>
