<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    plantillas: Array,
});

const toggleActivo = (p) => router.patch(route('admin.plantillas.toggle', p.id));

const eliminar = (p) => {
    if (!confirm(`¿Eliminar la plantilla "${p.nombre}"? Esta acción no se puede deshacer.`)) return;
    router.delete(route('admin.plantillas.destroy', p.id), { preserveScroll: true });
};

const tipoBadge = (tipo) => {
    const map = {
        comprador: 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
        proveedor: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        ambos:     'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
    };
    return map[tipo] ?? 'bg-gray-100 text-gray-700';
};

const tipoLabel = { comprador: 'Comprador', proveedor: 'Proveedor', ambos: 'Ambos' };
</script>

<template>
    <AdminLayout title="Plantillas de Correo">
        <Head title="Plantillas de Correo" />

        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Plantillas de Correo</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Personaliza el asunto y contenido de los correos automáticos del sistema.
                    </p>
                </div>
                <Link :href="route('admin.plantillas.create')"
                    class="px-4 py-2 text-sm font-semibold rounded-xl bg-[#8b1028] hover:bg-[#6f0c1f] text-white transition-colors">
                    + Nueva plantilla
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left px-5 py-3.5 font-semibold text-gray-600 dark:text-gray-300">Nombre</th>
                                <th class="text-left px-5 py-3.5 font-semibold text-gray-600 dark:text-gray-300 hidden md:table-cell">Asunto</th>
                                <th class="text-left px-5 py-3.5 font-semibold text-gray-600 dark:text-gray-300 hidden sm:table-cell">Destinatario</th>
                                <th class="text-left px-5 py-3.5 font-semibold text-gray-600 dark:text-gray-300">Estado</th>
                                <th class="px-5 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            <tr v-for="p in plantillas" :key="p.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ p.nombre }}</div>
                                    <div class="text-xs text-gray-400 dark:text-gray-500 font-mono mt-0.5">{{ p.clave }}</div>
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell text-gray-600 dark:text-gray-400 max-w-[220px] truncate">
                                    {{ p.asunto }}
                                </td>
                                <td class="px-5 py-4 hidden sm:table-cell">
                                    <span :class="['text-xs px-2 py-0.5 rounded-full font-medium', tipoBadge(p.tipo_destinatario)]">
                                        {{ tipoLabel[p.tipo_destinatario] ?? p.tipo_destinatario }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <button @click="toggleActivo(p)"
                                        :class="['relative inline-flex h-5 w-9 rounded-full transition-colors focus:outline-none',
                                            p.activo ? 'bg-guinda-600' : 'bg-gray-300 dark:bg-gray-600']">
                                        <span :class="['inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform mt-0.5',
                                            p.activo ? 'translate-x-4' : 'translate-x-0.5']"></span>
                                    </button>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('admin.plantillas.edit', p.id)"
                                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-guinda-50 dark:bg-guinda-900/20 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-100 dark:hover:bg-guinda-900/40 transition-colors">
                                            Editar
                                        </Link>
                                        <button v-if="!p.es_sistema"
                                            @click="eliminar(p)"
                                            class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 hover:bg-red-100 transition-colors">
                                            Eliminar
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
