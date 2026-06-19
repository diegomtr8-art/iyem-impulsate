<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    publicidades: Array,
});

const toggleActiva = (p) => router.patch(route('admin.publicidad.toggle', p.id));
const eliminar = (p) => {
    if (!confirm(`¿Eliminar el anuncio "${p.titulo}"?`)) return;
    router.delete(route('admin.publicidad.destroy', p.id));
};

const formatFecha = (d) => d
    ? new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : '—';

const estadoClass = (label) => {
    const map = {
        'Vigente':    'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        'Programada': 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
        'Expirada':   'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
        'Inactiva':   'bg-red-50 text-red-600 dark:bg-red-900/20 dark:text-red-400',
    };
    return map[label] ?? 'bg-gray-100 text-gray-700';
};
</script>

<template>
    <AdminLayout title="Publicidad">
        <Head title="Publicidad" />

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Publicidad</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Pop-ups que se muestran a compradores y proveedores al iniciar sesión.
                    </p>
                </div>
                <Link :href="route('admin.publicidad.create')"
                    class="flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl bg-guinda-700 hover:bg-guinda-800 text-white transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo anuncio
                </Link>
            </div>

            <div v-if="!publicidades.length"
                class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-12 text-center text-gray-400 dark:text-gray-600">
                No hay anuncios creados aún.
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <div v-for="p in publicidades" :key="p.id"
                    class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden flex flex-col">

                    <!-- Imagen -->
                    <div class="relative aspect-video bg-gray-100 dark:bg-gray-800">
                        <img v-if="p.imagen_url" :src="p.imagen_url" :alt="p.titulo"
                            class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-700">
                            <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <!-- Badge estado -->
                        <span :class="['absolute top-2 right-2 text-xs px-2.5 py-1 rounded-full font-semibold', estadoClass(p.estado_label)]">
                            {{ p.estado_label }}
                        </span>
                    </div>

                    <!-- Info -->
                    <div class="p-4 flex-1 flex flex-col gap-3">
                        <h3 class="font-semibold text-gray-900 dark:text-white text-sm">{{ p.titulo }}</h3>

                        <div class="text-xs text-gray-400 dark:text-gray-500 space-y-0.5">
                            <div>Inicio: {{ formatFecha(p.fecha_inicio) }}</div>
                            <div>Fin: {{ formatFecha(p.fecha_fin) }}</div>
                        </div>

                        <a v-if="p.enlace" :href="p.enlace" target="_blank"
                            class="text-xs text-guinda-600 dark:text-guinda-400 truncate hover:underline">
                            {{ p.enlace }}
                        </a>

                        <!-- Acciones -->
                        <div class="flex items-center justify-between mt-auto pt-2 border-t border-gray-100 dark:border-gray-800">
                            <!-- Toggle activa -->
                            <div class="flex items-center gap-2">
                                <button @click="toggleActiva(p)"
                                    :class="['relative inline-flex h-5 w-9 rounded-full transition-colors focus:outline-none',
                                        p.activa ? 'bg-guinda-600' : 'bg-gray-300 dark:bg-gray-600']">
                                    <span :class="['inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform mt-0.5',
                                        p.activa ? 'translate-x-4' : 'translate-x-0.5']"></span>
                                </button>
                                <span class="text-xs text-gray-400 dark:text-gray-500">{{ p.activa ? 'Activa' : 'Inactiva' }}</span>
                            </div>

                            <div class="flex gap-1">
                                <Link :href="route('admin.publicidad.edit', p.id)"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-guinda-700 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </Link>
                                <button @click="eliminar(p)"
                                    class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
