<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    usuarios: Object,
    roles: Array,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const rolFiltro = ref(props.filters?.rol ?? '');

let debounceTimer = null;
const applyFilter = () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(route('admin.usuarios-gestion.index'), {
            search: search.value || undefined,
            rol: rolFiltro.value || undefined,
        }, { preserveState: true, replace: true });
    }, 350);
};

watch([search, rolFiltro], applyFilter);

const formatFecha = (d) => d ? new Date(d).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : '—';

const confirmarEliminar = (usuario) => {
    if (!confirm(`¿Eliminar al usuario ${usuario.email}? Esta acción no se puede deshacer.`)) return;
    router.delete(route('admin.usuarios-gestion.destroy', usuario.id));
};

const badgeClass = (rol) => {
    const map = {
        'super-admin':   'bg-guinda-100 text-guinda-800 dark:bg-guinda-900/30 dark:text-guinda-300 border border-guinda-300 dark:border-guinda-700',
        'admin':         'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'restaurantero': 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        'cliente':       'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
    };
    return map[rol] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};

const rolLabel = (rol) => {
    const labels = {
        'super-admin':   'Super Admin',
        'admin':         'Admin',
        'restaurantero': 'Proveedor',
        'cliente':       'Comprador',
    };
    return labels[rol] ?? rol;
};
</script>

<template>
    <AdminLayout title="Gestión de Usuarios">
        <Head title="Gestión de Usuarios" />

        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Gestión de Usuarios</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ usuarios.total }} usuario{{ usuarios.total !== 1 ? 's' : '' }} registrado{{ usuarios.total !== 1 ? 's' : '' }}
                    </p>
                </div>
            </div>

            <!-- Filtros -->
            <div class="flex flex-wrap gap-3">
                <div class="relative flex-1 min-w-[200px]">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0" />
                    </svg>
                    <input v-model="search" type="text" placeholder="Buscar por nombre, email o empresa…"
                        class="w-full pl-9 pr-4 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 focus:border-transparent transition-colors" />
                </div>
                <select v-model="rolFiltro"
                    class="px-4 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors">
                    <option value="">Todos los roles</option>
                    <option value="cliente">Comprador</option>
                    <option value="restaurantero">Proveedor</option>
                    <option value="admin">Admin</option>
                    <option value="super-admin">Super Admin</option>
                </select>
            </div>

            <!-- Tabla -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left px-5 py-3.5 font-semibold text-gray-600 dark:text-gray-300">Usuario</th>
                                <th class="text-left px-5 py-3.5 font-semibold text-gray-600 dark:text-gray-300 hidden md:table-cell">Empresa</th>
                                <th class="text-left px-5 py-3.5 font-semibold text-gray-600 dark:text-gray-300">Roles</th>
                                <th class="text-left px-5 py-3.5 font-semibold text-gray-600 dark:text-gray-300 hidden lg:table-cell">Registro</th>
                                <th class="px-5 py-3.5"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-gray-800">
                            <tr v-for="u in usuarios.data" :key="u.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">
                                <td class="px-5 py-4">
                                    <div class="font-medium text-gray-900 dark:text-white">{{ u.name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ u.email }}</div>
                                </td>
                                <td class="px-5 py-4 hidden md:table-cell text-gray-600 dark:text-gray-400">
                                    {{ u.nombre_empresa || '—' }}
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        <span v-for="r in u.roles_nombres" :key="r"
                                            :class="['text-xs px-2 py-0.5 rounded-full font-medium', badgeClass(r)]">
                                            {{ rolLabel(r) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-5 py-4 hidden lg:table-cell text-gray-500 dark:text-gray-400">
                                    {{ formatFecha(u.created_at) }}
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <Link :href="route('admin.usuarios-gestion.edit', u.id)"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-guinda-700 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 dark:hover:text-guinda-400 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </Link>
                                        <button @click="confirmarEliminar(u)"
                                            :disabled="u.id === $page.props.auth.user.id"
                                            class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!usuarios.data.length">
                                <td colspan="5" class="px-5 py-12 text-center text-gray-400 dark:text-gray-600">
                                    No se encontraron usuarios con los filtros seleccionados.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="usuarios.last_page > 1"
                    class="flex items-center justify-between px-5 py-4 border-t border-gray-100 dark:border-gray-800">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Mostrando {{ usuarios.from }}–{{ usuarios.to }} de {{ usuarios.total }}
                    </p>
                    <div class="flex gap-2">
                        <Link v-for="link in usuarios.links" :key="link.label"
                            :href="link.url ?? '#'"
                            v-html="link.label"
                            :class="[
                                'px-3 py-1.5 text-sm rounded-lg border transition-colors',
                                link.active
                                    ? 'bg-guinda-700 border-guinda-700 text-white font-bold'
                                    : link.url
                                        ? 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-guinda-400 hover:text-guinda-700'
                                        : 'border-gray-100 dark:border-gray-800 text-gray-300 dark:text-gray-700 cursor-not-allowed'
                            ]" />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
