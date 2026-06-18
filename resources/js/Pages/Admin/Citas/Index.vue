<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    citas: Object,
    filters: Object,
    clientes: Array,
    restauranteros: Array,
});

const search = ref(props.filters?.search ?? '');
const estado = ref(props.filters?.estado ?? '');

const applyFilters = () => {
    router.get(route('admin.citas.index'), {
        search: search.value,
        estado: estado.value,
    }, { preserveState: true, replace: true });
};

const updateEstado = (cita, nuevoEstado) => {
    router.patch(route('admin.citas.update-estado', cita.id), { estado: nuevoEstado });
};

const estadoClases = {
    pendiente:  'bg-guinda-500/15 text-guinda-600 dark:text-guinda-400 border-guinda-500/20',
    confirmada: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
    cancelada:  'bg-gray-500/15 text-gray-500 dark:text-gray-400 border-gray-500/20',
    completada: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
};

const estadoLabels = { pendiente: 'Pendiente', confirmada: 'Confirmada', cancelada: 'Cancelada', completada: 'Completada' };
const estados = ['pendiente', 'confirmada', 'cancelada', 'completada'];

// ── MODAL NUEVA CITA ──────────────────────────────────────────────────────
const mostrarModalNuevaCita = ref(false);
const nuevaCitaForm = useForm({
    cliente_id: '',
    restaurantero_id: '',
    fecha: '',
    hora: '',
    notas: '',
});

const horasDisponibles = ['09:00','09:30','10:00','10:30','11:00','11:30','12:00','12:30','13:00','13:30','14:00','14:30','15:00','15:30'];

const crearCita = () => {
    nuevaCitaForm.post(route('admin.citas.store'), {
        onSuccess: () => {
            mostrarModalNuevaCita.value = false;
            nuevaCitaForm.reset();
        },
    });
};

const cerrarModalNuevaCita = () => {
    mostrarModalNuevaCita.value = false;
    nuevaCitaForm.reset();
    nuevaCitaForm.clearErrors();
};
</script>

<template>
    <AdminLayout title="Citas">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Citas</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Gestión y seguimiento de todas las citas</p>
                </div>
                <button @click="mostrarModalNuevaCita = true"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nueva cita para cliente
                </button>
            </div>
        </template>

        <!-- Modal nueva cita para cliente -->
        <Teleport to="body">
            <Transition name="modal-fade">
                <div v-if="mostrarModalNuevaCita" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="cerrarModalNuevaCita"></div>
                    <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 w-full max-w-lg shadow-2xl">
                        <div class="flex items-center justify-between mb-5">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Agendar cita para cliente</h3>
                            <button @click="cerrarModalNuevaCita" class="text-gray-400 hover:text-gray-700 dark:hover:text-white p-1 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="crearCita" class="space-y-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Cliente *</label>
                                <select v-model="nuevaCitaForm.cliente_id"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-guinda-500 transition-colors">
                                    <option value="">Seleccionar cliente...</option>
                                    <option v-for="c in clientes" :key="c.id" :value="c.id">{{ c.name }} — {{ c.email }}</option>
                                </select>
                                <p v-if="nuevaCitaForm.errors.cliente_id" class="text-red-500 text-xs mt-1">{{ nuevaCitaForm.errors.cliente_id }}</p>
                                <p v-if="nuevaCitaForm.errors.cliente" class="text-red-500 text-xs mt-1">{{ nuevaCitaForm.errors.cliente }}</p>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Proveedor *</label>
                                <select v-model="nuevaCitaForm.restaurantero_id"
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-guinda-500 transition-colors">
                                    <option value="">Seleccionar proveedor...</option>
                                    <option v-for="r in restauranteros" :key="r.id" :value="r.id">{{ r.nombre_restaurante }}</option>
                                </select>
                                <p v-if="nuevaCitaForm.errors.restaurantero_id" class="text-red-500 text-xs mt-1">{{ nuevaCitaForm.errors.restaurantero_id }}</p>
                                <p v-if="nuevaCitaForm.errors.servicio" class="text-red-500 text-xs mt-1">{{ nuevaCitaForm.errors.servicio }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Fecha *</label>
                                    <input v-model="nuevaCitaForm.fecha" type="date"
                                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-guinda-500 transition-colors" />
                                    <p v-if="nuevaCitaForm.errors.fecha" class="text-red-500 text-xs mt-1">{{ nuevaCitaForm.errors.fecha }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Hora *</label>
                                    <select v-model="nuevaCitaForm.hora"
                                        class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-guinda-500 transition-colors">
                                        <option value="">Seleccionar...</option>
                                        <option v-for="h in horasDisponibles" :key="h" :value="h">{{ h }}</option>
                                    </select>
                                    <p v-if="nuevaCitaForm.errors.hora" class="text-red-500 text-xs mt-1">{{ nuevaCitaForm.errors.hora }}</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Notas</label>
                                <textarea v-model="nuevaCitaForm.notas" rows="2"
                                    placeholder="Notas opcionales..."
                                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-guinda-500 transition-colors resize-none">
                                </textarea>
                            </div>

                            <div v-if="nuevaCitaForm.errors.limit || nuevaCitaForm.errors.edicion" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/30 text-red-600 dark:text-red-400 text-xs rounded-xl px-3 py-2">
                                {{ nuevaCitaForm.errors.limit || nuevaCitaForm.errors.edicion }}
                            </div>

                            <div class="flex gap-3 pt-2">
                                <button type="button" @click="cerrarModalNuevaCita"
                                    class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-xl transition-colors text-sm">
                                    Cancelar
                                </button>
                                <button type="submit" :disabled="nuevaCitaForm.processing"
                                    class="flex-1 py-2.5 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 text-white font-bold rounded-xl transition-colors text-sm">
                                    {{ nuevaCitaForm.processing ? 'Agendando...' : 'Agendar cita' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Filters -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 mb-6 flex flex-wrap gap-3 items-end shadow-sm dark:shadow-none transition-colors">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Buscar cliente</label>
                <input v-model="search" @keyup.enter="applyFilters" type="text" placeholder="Nombre o email..."
                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors" />
            </div>
            <div class="w-44">
                <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5">Estado</label>
                <select v-model="estado"
                    class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors">
                    <option value="">Todos</option>
                    <option v-for="e in estados" :key="e" :value="e">{{ estadoLabels[e] }}</option>
                </select>
            </div>
            <button @click="applyFilters"
                class="px-5 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                Filtrar
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors overflow-x-auto">
            <table class="w-full text-sm min-w-[640px]">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                        <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Cliente</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden md:table-cell">Proveedor</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden lg:table-cell">Servicio</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Inicio</th>
                        <th class="text-center px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Estado</th>
                        <th class="text-center px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Cambiar</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="c in citas.data" :key="c.id"
                        class="border-b border-gray-100 dark:border-gray-800/40 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors duration-150">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900 dark:text-gray-200">{{ c.cliente?.name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-500">{{ c.cliente?.email }}</div>
                        </td>
                        <td class="px-6 py-4 hidden md:table-cell">
                            <Link :href="route('admin.restauranteros.show', c.restaurantero_id)"
                                class="text-gray-700 dark:text-gray-300 hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors">
                                {{ c.restaurantero?.nombre_restaurante }}
                            </Link>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400 hidden lg:table-cell">{{ c.servicio?.nombre }}</td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400 text-xs">
                            <span class="whitespace-nowrap">{{ new Date(c.inicio).toLocaleString('es-MX') }}</span>
                            <div v-if="c.productos_interes && c.productos_interes.length > 0" class="flex flex-wrap gap-1 mt-1.5">
                                <span v-for="p in c.productos_interes" :key="p"
                                      class="inline-block bg-guinda-50 dark:bg-guinda-500/10 text-guinda-700 dark:text-guinda-400 font-medium px-2 py-0.5 rounded-full border border-guinda-200 dark:border-guinda-500/20">
                                    {{ p }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span :class="estadoClases[c.estado]" class="text-xs font-semibold px-2.5 py-1 rounded-full border">
                                {{ estadoLabels[c.estado] || c.estado }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            <select :value="c.estado" @change="updateEstado(c, $event.target.value)"
                                class="bg-gray-100 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg px-2 py-1 text-xs focus:outline-none focus:border-guinda-500 transition-colors">
                                <option v-for="e in estados" :key="e" :value="e">{{ estadoLabels[e] }}</option>
                            </select>
                        </td>
                    </tr>
                    <tr v-if="!citas.data.length">
                        <td colspan="6" class="px-6 py-16 text-center text-gray-400 dark:text-gray-600">No se encontraron citas.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="citas.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-800 flex justify-end gap-2">
                <Link v-for="link in citas.links" :key="link.label"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    :class="[
                        'px-3 py-1.5 text-xs rounded-lg transition-colors',
                        link.active ? 'bg-guinda-800 text-white font-semibold' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700',
                        !link.url ? 'opacity-40 cursor-not-allowed' : ''
                    ]" />
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
