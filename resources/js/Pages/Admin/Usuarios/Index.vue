<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    usuarios: Object,
    filters: Object,
});

const search = ref(props.filters?.search || '');
const confirmarEliminar = ref(null);

let debounceTimer = null;
watch(search, (val) => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        router.get(route('admin.usuarios.index'), { search: val }, {
            preserveState: true,
            replace: true,
        });
    }, 400);
});

function eliminarUsuario(usuario) {
    confirmarEliminar.value = null;
    router.delete(route('admin.usuarios.destroy', usuario.id), { preserveScroll: true });
}

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

const iniciales = (nombre) => {
    if (!nombre) return '?';
    return nombre.trim().split(' ').slice(0, 2).map(n => n[0]).join('').toUpperCase();
};
</script>

<template>
    <AdminLayout title="Usuarios">
        <template #header>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">Usuarios</h1>
            <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Clientes registrados en el sistema</p>
        </template>

        <!-- Búsqueda -->
        <div class="mb-6">
            <div class="relative w-full sm:max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input v-model="search" type="text" placeholder="Buscar usuario..."
                    class="w-full pl-9 pr-4 py-2.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 rounded-xl text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors shadow-sm dark:shadow-none" />
            </div>
        </div>

        <!-- Tabla -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                            <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Usuario</th>
                            <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden md:table-cell">Email</th>
                            <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden lg:table-cell">Citas</th>
                            <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden xl:table-cell">Registro</th>
                            <th class="text-right px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="usuarios.data.length === 0">
                            <td colspan="5" class="px-6 py-16 text-center text-gray-400 dark:text-gray-600">No se encontraron usuarios</td>
                        </tr>
                        <tr v-for="usuario in usuarios.data" :key="usuario.id"
                            class="border-b border-gray-100 dark:border-gray-800/40 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors duration-150">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-guinda-50 dark:bg-guinda-500/15 border border-guinda-200 dark:border-guinda-500/20 text-guinda-700 dark:text-guinda-400 font-bold text-xs flex items-center justify-center shrink-0">
                                        {{ iniciales(usuario.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 dark:text-white truncate">{{ usuario.name }}</p>
                                        <p class="text-xs text-gray-500 md:hidden truncate">{{ usuario.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-400 hidden md:table-cell">{{ usuario.email }}</td>
                            <td class="px-6 py-4 hidden lg:table-cell">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-guinda-50 dark:bg-guinda-500/15 text-guinda-700 dark:text-guinda-400 border border-guinda-200 dark:border-guinda-500/20">
                                    {{ usuario.citas_count || 0 }} / 12
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-500 hidden xl:table-cell">{{ formatFecha(usuario.created_at) }}</td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="route('admin.clientes.show', usuario.id)"
                                        class="text-gray-400 dark:text-gray-400 hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors p-1.5 hover:bg-guinda-50 dark:hover:bg-guinda-500/10 rounded-lg" title="Ver detalle">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                    <button @click="confirmarEliminar = usuario"
                                        class="text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-400 transition-colors p-1.5 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg" title="Eliminar usuario">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="usuarios.last_page > 1" class="flex items-center justify-center gap-1 px-6 py-4 border-t border-gray-200 dark:border-gray-800">
                <template v-for="link in usuarios.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                            link.active ? 'bg-guinda-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700'
                        ]"
                        v-html="link.label" />
                    <span v-else class="px-3 py-1.5 rounded-lg text-xs text-gray-400 dark:text-gray-600 cursor-not-allowed" v-html="link.label" />
                </template>
            </div>
        </div>

        <!-- Modal confirmar eliminación -->
        <div v-if="confirmarEliminar" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.self="confirmarEliminar = null">
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-red-50 dark:bg-red-500/15 border border-red-200 dark:border-red-500/20 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-red-500 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white">Eliminar usuario</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-500">Esta acción no se puede deshacer</p>
                    </div>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                    ¿Estás seguro de que quieres eliminar a <span class="font-semibold text-gray-900 dark:text-white">{{ confirmarEliminar.name }}</span>? Se eliminarán también todas sus citas.
                </p>
                <div class="flex gap-3">
                    <button @click="confirmarEliminar = null"
                        class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-xl text-sm transition-colors">
                        Cancelar
                    </button>
                    <button @click="eliminarUsuario(confirmarEliminar)"
                        class="flex-1 py-2.5 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl text-sm transition-colors">
                        Eliminar
                    </button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
