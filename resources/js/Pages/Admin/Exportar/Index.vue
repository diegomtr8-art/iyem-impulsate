<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({
    evento: Object,
});
</script>

<template>
    <AdminLayout title="Exportar">
        <template #header>
            <div>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Exportar datos</h1>
                <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Descarga reportes en formato Excel</p>
            </div>
        </template>

        <div class="max-w-3xl space-y-6">

            <!-- Aviso sin evento activo -->
            <div v-if="!evento" class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-2xl p-5 flex items-center gap-3">
                <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div>
                    <p class="font-semibold text-amber-800 dark:text-amber-300 text-sm">Sin evento activo</p>
                    <p class="text-amber-700 dark:text-amber-400/80 text-xs mt-0.5">Las exportaciones de evento completo y filtradas no estarán disponibles.</p>
                </div>
            </div>

            <!-- Evento Completo -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-2xl">📊</span>
                            <h2 class="font-bold text-gray-900 dark:text-white">Evento Completo (Excel)</h2>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Exporta todas las hojas: datos del evento, proveedores, compradores, citas y resumen de KPIs.
                        </p>
                        <p v-if="evento" class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 font-medium">
                            Evento: {{ evento.nombre }}
                        </p>
                    </div>
                    <a v-if="evento"
                        :href="route('admin.exportar.evento-completo')"
                        class="shrink-0 inline-flex items-center gap-2 px-5 py-2.5 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-bold rounded-xl transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Descargar
                    </a>
                    <span v-else class="text-xs text-gray-400 italic">Sin evento activo</span>
                </div>
            </div>

            <!-- Exportaciones individuales -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm">
                <h2 class="font-bold text-gray-900 dark:text-white mb-4">Exportaciones individuales</h2>
                <div class="space-y-3">
                    <div v-for="item in [
                        { label: 'Solo Proveedores', desc: 'Lista completa de proveedores con estadísticas', route: 'admin.exportar.proveedores', icon: '🏪' },
                        { label: 'Solo Compradores', desc: 'Lista de compradores con conteo de citas', route: 'admin.exportar.compradores', icon: '🛒' },
                        { label: 'Solo Citas', desc: 'Todas las citas con estado y detalles', route: 'admin.exportar.citas', icon: '📅' },
                    ]" :key="item.route"
                        class="flex items-center justify-between gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-xl">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">{{ item.icon }}</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ item.label }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ item.desc }}</p>
                            </div>
                        </div>
                        <a :href="route(item.route)"
                            class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2 border border-guinda-200 dark:border-guinda-800 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 text-xs font-bold rounded-lg transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Excel
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
