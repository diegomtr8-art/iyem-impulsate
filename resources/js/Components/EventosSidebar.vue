<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
    eventos: { type: Array, default: () => [] },
});

const page = usePage();
const collapsed = ref(false);
const activeRole = computed(() => page.props.auth?.user?.active_role ?? 'comprador');
const registradoEnEvento = computed(() => page.props.registradoEnEvento ?? {});

const estaRegistrado = computed(() => {
    const tipo = activeRole.value === 'proveedor' ? 'proveedor' : 'comprador';
    return registradoEnEvento.value?.[tipo]?.estado === 'aprobado';
});

const badgeClase = (evento) => {
    if (evento.activa) return 'bg-emerald-500/90 text-white';
    const inicio = evento.fecha_hora_inicio ? new Date(evento.fecha_hora_inicio) : null;
    if (inicio && inicio > new Date()) return 'bg-guinda-700/90 text-white';
    return 'bg-gray-600/80 text-white';
};

const badgeTexto = (evento) => {
    if (evento.activa) return 'Activo';
    const inicio = evento.fecha_hora_inicio ? new Date(evento.fecha_hora_inicio) : null;
    if (inicio && inicio > new Date()) return 'Próximamente';
    return 'Finalizado';
};

const formatFecha = (fecha) => {
    if (!fecha) return null;
    return new Date(fecha).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });
};
</script>

<template>
    <!-- ── Desktop sidebar ─────────────────────────────────────────────── -->
    <aside class="hidden lg:flex flex-col w-64 xl:w-72 shrink-0">
        <div class="sticky top-20 space-y-3 max-h-[calc(100vh-6rem)] overflow-y-auto pr-1 pb-4">

            <div class="flex items-center justify-between px-1 mb-1">
                <h3 class="text-xs font-bold uppercase tracking-widest text-guinda-700 dark:text-guinda-400">
                    Eventos
                </h3>
                <button @click="collapsed = !collapsed"
                    class="p-1 rounded text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                    <svg class="w-4 h-4 transition-transform" :class="collapsed ? 'rotate-180' : ''"
                        fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                    </svg>
                </button>
            </div>

            <template v-if="!collapsed">
                <div v-if="!eventos || eventos.length === 0"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 text-center">
                    <p class="text-xs text-gray-400 dark:text-gray-500">Sin eventos disponibles</p>
                </div>

                <template v-for="evento in eventos" :key="evento.id">

                    <!-- ── TARJETA ACTIVO: hero con imagen + overlay ── -->
                    <div v-if="evento.activa"
                        class="bg-white dark:bg-gray-900 rounded-2xl overflow-hidden shadow-lg ring-1 ring-guinda-200 dark:ring-guinda-800/50">

                        <!-- Imagen hero con gradient overlay -->
                        <div class="relative h-44 overflow-hidden">
                            <img v-if="evento.imagen_url"
                                :src="evento.imagen_url"
                                :alt="evento.nombre"
                                class="w-full h-full object-cover scale-105 transition-transform duration-700 hover:scale-100" />
                            <div v-else
                                class="w-full h-full bg-gradient-to-br from-guinda-700 via-guinda-900 to-black" />

                            <!-- Gradient de abajo hacia arriba -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-transparent" />

                            <!-- Badge activo arriba izquierda -->
                            <span class="absolute top-3 left-3 flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-500/90 text-white backdrop-blur-sm shadow-sm">
                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse inline-block"></span>
                                Activo
                            </span>

                            <!-- Texto sobre imagen (parte inferior) -->
                            <div class="absolute bottom-0 left-0 right-0 px-3.5 pb-3.5">
                                <p v-if="evento.sector_economico"
                                    class="text-xs text-white/65 uppercase tracking-wider font-medium mb-0.5">
                                    {{ evento.sector_economico }}
                                </p>
                                <h4 class="font-black text-sm text-white leading-snug line-clamp-2 drop-shadow-md">
                                    {{ evento.nombre }}
                                </h4>
                            </div>
                        </div>

                        <!-- Cuerpo de la tarjeta -->
                        <div class="px-3.5 py-3 space-y-2.5">
                            <!-- Fecha -->
                            <div v-if="evento.fecha_hora_inicio"
                                class="flex items-center gap-1.5 text-xs text-guinda-600 dark:text-guinda-400 font-semibold">
                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                {{ formatFecha(evento.fecha_hora_inicio) }}
                            </div>

                            <!-- Descripción breve -->
                            <p v-if="evento.descripcion"
                                class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed line-clamp-2">
                                {{ evento.descripcion }}
                            </p>

                            <!-- Acciones -->
                            <div v-if="estaRegistrado" class="flex items-center justify-between pt-0.5">
                                <span class="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400 font-semibold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Registrado
                                </span>
                                <Link :href="route('proveedores.index')"
                                    class="text-xs text-guinda-600 dark:text-guinda-400 hover:text-guinda-800 dark:hover:text-guinda-300 font-bold transition-colors">
                                    Ver proveedores →
                                </Link>
                            </div>

                            <Link v-else :href="route('proveedores.index')"
                                class="block text-center text-sm font-bold py-2.5 rounded-xl
                                       bg-guinda-800 hover:bg-guinda-700 active:bg-guinda-900
                                       text-white transition-colors shadow-sm">
                                Ver Proveedores
                            </Link>
                        </div>
                    </div>

                    <!-- ── TARJETA NORMAL ── -->
                    <div v-else
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800
                               rounded-2xl overflow-hidden hover:shadow-md dark:hover:border-gray-700 transition-all">

                        <div class="relative h-24 overflow-hidden">
                            <img v-if="evento.imagen_url"
                                :src="evento.imagen_url"
                                :alt="evento.nombre"
                                class="w-full h-full object-cover opacity-90" />
                            <div v-else
                                class="w-full h-full bg-gradient-to-br from-gray-500 to-gray-800" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 to-transparent" />
                            <span class="absolute top-2 left-2 text-xs font-semibold px-2 py-0.5 rounded-full backdrop-blur-sm"
                                :class="badgeClase(evento)">
                                {{ badgeTexto(evento) }}
                            </span>
                            <p class="absolute bottom-2 left-2.5 right-2.5 text-xs font-bold text-white drop-shadow line-clamp-2">
                                {{ evento.nombre }}
                            </p>
                        </div>

                        <div class="px-3 py-2.5">
                            <p v-if="evento.sector_economico"
                                class="text-xs text-gray-400 dark:text-gray-500 truncate">
                                {{ evento.sector_economico }}
                            </p>
                            <p v-if="evento.fecha_hora_inicio"
                                class="text-xs text-guinda-600 dark:text-guinda-500 font-medium mt-0.5">
                                {{ formatFecha(evento.fecha_hora_inicio) }}
                            </p>
                        </div>
                    </div>

                </template>
            </template>
        </div>
    </aside>

    <!-- ── Carrusel móvil ──────────────────────────────────────────────── -->
    <div class="lg:hidden overflow-x-auto pb-3 -mx-4 px-4">
        <div class="flex gap-3" style="width: max-content">
            <div v-for="evento in eventos" :key="'m-' + evento.id"
                class="w-56 shrink-0 rounded-xl overflow-hidden shadow-sm"
                :class="evento.activa
                    ? 'ring-1 ring-guinda-300 dark:ring-guinda-700/60 bg-white dark:bg-gray-900'
                    : 'border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900'">

                <!-- Imagen con overlay -->
                <div class="relative h-32 overflow-hidden">
                    <img v-if="evento.imagen_url"
                        :src="evento.imagen_url"
                        :alt="evento.nombre"
                        class="w-full h-full object-cover" />
                    <div v-else class="w-full h-full"
                        :class="evento.activa
                            ? 'bg-gradient-to-br from-guinda-700 to-guinda-950'
                            : 'bg-gradient-to-br from-gray-600 to-gray-900'" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/75 to-transparent" />
                    <span class="absolute top-2 left-2 text-xs font-bold px-2 py-0.5 rounded-full backdrop-blur-sm"
                        :class="badgeClase(evento)">
                        {{ badgeTexto(evento) }}
                    </span>
                    <p class="absolute bottom-2 left-2.5 right-2.5 text-xs font-bold text-white drop-shadow line-clamp-2">
                        {{ evento.nombre }}
                    </p>
                </div>

                <!-- Footer tarjeta móvil -->
                <div class="px-3 pt-2 pb-3 space-y-1.5">
                    <p v-if="evento.fecha_hora_inicio"
                        class="text-xs text-guinda-600 dark:text-guinda-400 font-medium">
                        {{ formatFecha(evento.fecha_hora_inicio) }}
                    </p>
                    <Link v-if="evento.activa"
                        :href="route('proveedores.index')"
                        class="block text-center text-xs font-bold py-1.5 rounded-lg
                               bg-guinda-800 hover:bg-guinda-700 text-white transition-colors">
                        Ver Proveedores
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
