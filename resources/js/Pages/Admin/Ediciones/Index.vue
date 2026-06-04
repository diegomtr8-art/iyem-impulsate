<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    ediciones: Array,
});

const mostrarForm = ref(false);
const form = ref({
    nombre: '',
    sector: '',
    descripcion: '',
    fecha_inicio: new Date().toISOString().split('T')[0],
    fecha_inicio_agenda: '',
    fecha_fin_agenda: '',
});

const edicionActiva = () => props.ediciones.find(e => e.activa) || null;
const edicionesArchivadas = () => props.ediciones.filter(e => !e.activa);

const crearEdicion = () => {
    router.post(route('admin.ediciones.store'), form.value, {
        onSuccess: () => {
            mostrarForm.value = false;
            form.value = { nombre: '', sector: '', descripcion: '', fecha_inicio: new Date().toISOString().split('T')[0], fecha_inicio_agenda: '', fecha_fin_agenda: '' };
        },
    });
};

const archivar = (edicion) => {
    if (!confirm(`¿Archivar la edición "${edicion.nombre}"? Los usuarios no podrán agendar nuevas citas hasta que actives otra edición.`)) return;
    router.post(route('admin.ediciones.archivar', edicion.id));
};

const activar = (edicion) => {
    if (!confirm(`¿Activar la edición "${edicion.nombre}"? Esto desactivará cualquier edición activa actual.`)) return;
    router.post(route('admin.ediciones.activar', edicion.id));
};

const eliminar = (edicion) => {
    if (!confirm(`¿Eliminar la edición "${edicion.nombre}"? Esta acción no se puede deshacer.`)) return;
    router.delete(route('admin.ediciones.destroy', edicion.id));
};

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
};
</script>

<template>
    <AdminLayout title="Ediciones">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Ediciones</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Gestiona los ciclos de la plataforma por sector económico</p>
                </div>
                <button
                    @click="mostrarForm = !mostrarForm"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nueva edición
                </button>
            </div>
        </template>

        <div class="space-y-6 max-w-5xl">

            <!-- Formulario nueva edición -->
            <div v-if="mostrarForm" class="bg-white dark:bg-gray-900 border border-guinda-200 dark:border-guinda-900 rounded-2xl p-6 shadow-sm transition-colors">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-guinda-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Crear nueva edición
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre *</label>
                        <input v-model="form.nombre" type="text" placeholder="Ej. Impulsate 2026 — Tech"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sector económico</label>
                        <input v-model="form.sector" type="text" placeholder="Ej. Tecnología, Restaurantero..."
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha de inicio *</label>
                        <input v-model="form.fecha_inicio" type="date"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Descripción</label>
                        <input v-model="form.descripcion" type="text" placeholder="Descripción breve..."
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                    </div>
                </div>

                <!-- Rango de agenda -->
                <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/30 rounded-xl">
                    <p class="text-xs font-semibold text-blue-700 dark:text-blue-400 mb-3 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                        Período en que los clientes pueden agendar citas (opcional)
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Inicio de agenda</label>
                            <input v-model="form.fecha_inicio_agenda" type="date"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fin de agenda</label>
                            <input v-model="form.fecha_fin_agenda" type="date"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                    </div>
                    <p class="text-xs text-blue-600/70 dark:text-blue-500/70 mt-2">Si no se define, los clientes pueden agendar en cualquier fecha futura.</p>
                </div>
                <div class="flex items-center gap-3 mt-4">
                    <button @click="crearEdicion"
                        class="px-5 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                        Crear edición
                    </button>
                    <button @click="mostrarForm = false"
                        class="px-5 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 text-sm font-medium rounded-xl transition-colors">
                        Cancelar
                    </button>
                </div>
            </div>

            <!-- Edición activa -->
            <div>
                <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Edición activa</h2>

                <div v-if="edicionActiva()" class="bg-white dark:bg-gray-900 border-2 border-emerald-300 dark:border-emerald-700 rounded-2xl p-6 shadow-sm transition-colors">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">En curso</span>
                            </div>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white">{{ edicionActiva().nombre }}</h3>
                            <p v-if="edicionActiva().sector" class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Sector: {{ edicionActiva().sector }}</p>
                            <p v-if="edicionActiva().descripcion" class="text-sm text-gray-400 dark:text-gray-600 mt-1">{{ edicionActiva().descripcion }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-600 mt-2">Inicio: {{ formatFecha(edicionActiva().fecha_inicio) }}</p>
                            <p v-if="edicionActiva().fecha_inicio_agenda || edicionActiva().fecha_fin_agenda" class="text-xs text-blue-600 dark:text-blue-400 mt-1">
                                📅 Agenda abierta: {{ edicionActiva().fecha_inicio_agenda ? formatFecha(edicionActiva().fecha_inicio_agenda) : 'sin límite inicial' }}
                                — {{ edicionActiva().fecha_fin_agenda ? formatFecha(edicionActiva().fecha_fin_agenda) : 'sin límite final' }}
                            </p>
                        </div>
                        <button @click="archivar(edicionActiva())"
                            class="shrink-0 inline-flex items-center gap-2 px-4 py-2 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-semibold rounded-xl transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                            </svg>
                            Archivar edición
                        </button>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-3 gap-4 mt-5 pt-5 border-t border-gray-100 dark:border-gray-800">
                        <div class="text-center">
                            <div class="text-2xl font-black text-guinda-700 dark:text-guinda-400">{{ edicionActiva().citas_count }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Citas</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-black text-guinda-700 dark:text-guinda-400">{{ edicionActiva().restauranteros_count }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Proveedores</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-black text-guinda-700 dark:text-guinda-400">{{ edicionActiva().usuarios_count }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Usuarios activos</div>
                        </div>
                    </div>
                </div>

                <div v-else class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-2xl p-6">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="font-semibold text-amber-800 dark:text-amber-300 text-sm">Sin edición activa</p>
                            <p class="text-amber-700 dark:text-amber-400/80 text-xs mt-0.5">Los usuarios no pueden agendar citas. Activa o crea una edición.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ediciones archivadas -->
            <div v-if="edicionesArchivadas().length > 0">
                <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Ediciones archivadas</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-for="edicion in edicionesArchivadas()" :key="edicion.id"
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm transition-colors">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <h3 class="font-bold text-gray-900 dark:text-gray-200 truncate">{{ edicion.nombre }}</h3>
                                <p v-if="edicion.sector" class="text-xs text-gray-500 dark:text-gray-500">{{ edicion.sector }}</p>
                            </div>
                            <span class="shrink-0 text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded-full font-medium">Archivada</span>
                        </div>

                        <div class="text-xs text-gray-400 dark:text-gray-600 space-y-0.5 mb-4">
                            <p>Inicio: {{ formatFecha(edicion.fecha_inicio) }}</p>
                            <p>Archivada: {{ formatFecha(edicion.fecha_corte) }}</p>
                        </div>

                        <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-500 mb-4">
                            <span>{{ edicion.citas_count }} citas</span>
                            <span>·</span>
                            <span>{{ edicion.restauranteros_count }} proveedores</span>
                            <span>·</span>
                            <span>{{ edicion.usuarios_count }} usuarios</span>
                        </div>

                        <div class="flex items-center gap-2">
                            <button @click="activar(edicion)"
                                class="flex-1 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 text-xs font-semibold rounded-lg transition-colors text-center">
                                Reactivar
                            </button>
                            <button v-if="edicion.citas_count === 0" @click="eliminar(edicion)"
                                class="px-3 py-1.5 text-red-400 dark:text-red-500 hover:text-red-500 dark:hover:text-red-400 text-xs font-medium rounded-lg hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
