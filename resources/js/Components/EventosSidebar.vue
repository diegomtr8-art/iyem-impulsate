<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    eventos: { type: Array, default: () => [] },
});

const page = usePage();
const collapsed = ref(false);

const activeRole = computed(() => page.props.auth?.user?.active_role ?? 'comprador');
const panelRoute = computed(() =>
    activeRole.value === 'proveedor' ? route('restaurantero.panel') : route('user.dashboard')
);
const registradoEnEvento = computed(() => page.props.registradoEnEvento ?? {});
const eventoActivo = computed(() => page.props.eventoActivo ?? null);

const badgeClase = (evento) => {
    if (evento.activa) return 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300';
    const ahora = new Date();
    const inicio = evento.fecha_hora_inicio ? new Date(evento.fecha_hora_inicio) : null;
    if (inicio && inicio > ahora) return 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-300';
    return 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400';
};

const badgeTexto = (evento) => {
    if (evento.activa) return 'Activo';
    const ahora = new Date();
    const inicio = evento.fecha_hora_inicio ? new Date(evento.fecha_hora_inicio) : null;
    if (inicio && inicio > ahora) return 'Próximamente';
    return 'Finalizado';
};

const formatFecha = (fecha) => {
    if (!fecha) return null;
    return new Date(fecha).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
};

const registroActivo = computed(() => {
    const tipo = activeRole.value === 'proveedor' ? 'proveedor' : 'comprador';
    return registradoEnEvento.value?.[tipo] ?? null;
});

const estaRegistrado = computed(() => registroActivo.value?.estado === 'aprobado');
</script>

<template>
    <!-- Sidebar solo en desktop/tablet (lg+) -->
    <aside class="hidden lg:flex flex-col w-64 xl:w-72 shrink-0">
        <div class="sticky top-20 space-y-3 max-h-[calc(100vh-6rem)] overflow-y-auto pr-1 pb-4">

            <!-- Header sidebar -->
            <div class="flex items-center justify-between px-1 mb-1">
                <h3 class="text-xs font-bold uppercase tracking-widest text-guinda-700 dark:text-guinda-400">
                    Eventos
                </h3>
                <button @click="collapsed = !collapsed"
                    class="p-1 rounded text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                    <svg class="w-4 h-4 transition-transform" :class="collapsed ? 'rotate-180' : ''" fill="none"
                        stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>

            <template v-if="!collapsed">
                <div v-if="!eventos || eventos.length === 0"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 text-center">
                    <p class="text-xs text-gray-400 dark:text-gray-500">Sin eventos disponibles</p>
                </div>

                <!-- Tarjeta por evento -->
                <div v-for="evento in eventos" :key="evento.id"
                    class="bg-white dark:bg-gray-900 border rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition-shadow"
                    :class="evento.activa
                        ? 'border-guinda-200 dark:border-guinda-800/60'
                        : 'border-gray-200 dark:border-gray-800'">

                    <!-- Imagen o fallback con gradiente -->
                    <div class="relative h-28 overflow-hidden">
                        <img v-if="evento.imagen_url" :src="evento.imagen_url" :alt="evento.nombre"
                            class="w-full h-full object-cover" />
                        <div v-else
                            class="w-full h-full flex items-center justify-center"
                            :class="evento.activa
                                ? 'bg-gradient-to-br from-guinda-800 to-guinda-950'
                                : 'bg-gradient-to-br from-gray-700 to-gray-900'">
                            <svg class="w-10 h-10 text-white/30" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>

                        <!-- Badge estado -->
                        <span class="absolute top-2 left-2 text-xs font-semibold px-2 py-0.5 rounded-full backdrop-blur-sm"
                            :class="badgeClase(evento)">
                            {{ badgeTexto(evento) }}
                        </span>

                        <!-- Pulso activo -->
                        <span v-if="evento.activa"
                            class="absolute top-2 right-2 w-2.5 h-2.5 bg-emerald-400 rounded-full animate-pulse ring-2 ring-white dark:ring-gray-900">
                        </span>
                    </div>

                    <!-- Contenido -->
                    <div class="p-3">
                        <h4 class="font-bold text-sm text-gray-900 dark:text-white leading-tight line-clamp-2">
                            {{ evento.nombre }}
                        </h4>
                        <p v-if="evento.sector_economico" class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">
                            {{ evento.sector_economico }}
                        </p>
                        <p v-if="evento.fecha_hora_inicio" class="text-xs text-guinda-600 dark:text-guinda-400 mt-1 font-medium">
                            {{ formatFecha(evento.fecha_hora_inicio) }}
                        </p>

                        <!-- CTA solo en evento activo y no registrado -->
                        <template v-if="evento.activa">
                            <div v-if="estaRegistrado"
                                class="mt-2 flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-medium">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                                Registrado
                            </div>
                            <Link v-else :href="panelRoute"
                                class="mt-2 block text-center text-xs font-semibold py-1.5 px-3 rounded-lg bg-guinda-800 hover:bg-guinda-700 text-white transition-colors">
                                Más información
                            </Link>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </aside>

    <!-- Carrusel en móvil (< lg) -->
    <div class="lg:hidden overflow-x-auto pb-3 -mx-4 px-4">
        <div class="flex gap-3" style="width: max-content">
            <div v-for="evento in eventos" :key="'m-' + evento.id"
                class="w-52 shrink-0 bg-white dark:bg-gray-900 border rounded-xl overflow-hidden shadow-sm"
                :class="evento.activa ? 'border-guinda-200 dark:border-guinda-800/60' : 'border-gray-200 dark:border-gray-800'">

                <div class="relative h-20 overflow-hidden">
                    <img v-if="evento.imagen_url" :src="evento.imagen_url" :alt="evento.nombre"
                        class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full"
                        :class="evento.activa ? 'bg-gradient-to-br from-guinda-800 to-guinda-950' : 'bg-gradient-to-br from-gray-700 to-gray-900'">
                    </div>
                    <span class="absolute top-1.5 left-1.5 text-xs font-semibold px-1.5 py-0.5 rounded-full backdrop-blur-sm"
                        :class="badgeClase(evento)">
                        {{ badgeTexto(evento) }}
                    </span>
                </div>

                <div class="p-2.5">
                    <p class="font-bold text-xs text-gray-900 dark:text-white line-clamp-1">{{ evento.nombre }}</p>
                    <p v-if="evento.fecha_hora_inicio" class="text-xs text-guinda-600 dark:text-guinda-400 mt-0.5">
                        {{ formatFecha(evento.fecha_hora_inicio) }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
