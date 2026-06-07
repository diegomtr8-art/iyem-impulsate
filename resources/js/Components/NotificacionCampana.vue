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

const svgCalendario = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" /></svg>`;
const svgCheck = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
const svgX = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
const svgArrow = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>`;
const svgReloj = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>`;
const svgInfo = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zM12 8.25h.008v.008H12V8.25z" /></svg>`;
const svgCampana = `<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>`;

const iconoTipo = (tipo) => {
    const iconos = {
        cita_nueva:       svgCalendario,
        cita_aceptada:    svgCheck,
        cita_rechazada:   svgX,
        cita_reagendada:  svgArrow,
        recordatorio_24h: svgReloj,
        recordatorio_2h:  svgReloj,
        recordatorio_1h:  svgCalendario,
        recordatorio_30m: svgReloj,
        info:             svgInfo,
    };
    return iconos[tipo] || svgCampana;
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
                        <div class="w-8 h-8 mb-2 mx-auto text-gray-300 dark:text-gray-700" v-html="svgCampana"></div>
                        <p>Sin notificaciones</p>
                    </div>
                    <div v-for="n in notificaciones" :key="n.id" @click="marcarLeida(n)"
                        class="flex gap-3 px-4 py-3 cursor-pointer transition-colors border-b border-gray-100 dark:border-gray-800/50 last:border-0"
                        :class="n.leida ? 'hover:bg-gray-50 dark:hover:bg-gray-800/30' : 'bg-guinda-50/50 dark:bg-guinda-900/10 hover:bg-guinda-50 dark:hover:bg-guinda-900/20'">
                        <div class="shrink-0 mt-0.5 text-guinda-600 dark:text-guinda-400" v-html="iconoTipo(n.tipo)"></div>
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
