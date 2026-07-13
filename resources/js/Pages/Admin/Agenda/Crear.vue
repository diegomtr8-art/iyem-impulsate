<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    evento: Object,
    compradores: Array,
    proveedores: Array,
    slots: Array,
});

const page = usePage();
const errores = computed(() => page.props.errors ?? {});

const compradorSeleccionado = ref(null);
const proveedorActivo = ref(null);
const citasAsignadas = ref([]);
const guardando = ref(false);
const buscarProveedor = ref('');

const proveedoresFiltrados = computed(() => {
    if (!buscarProveedor.value.trim()) return props.proveedores;
    const q = buscarProveedor.value.toLowerCase();
    return props.proveedores.filter(p =>
        (p.nombre_restaurante || '').toLowerCase().includes(q) ||
        (p.categoria && p.categoria.toLowerCase().includes(q))
    );
});

const yaAsignado = (restauranteroId) => citasAsignadas.value.some(c => c.restaurantero_id === restauranteroId);
const getAsignacion = (slotInicio) => citasAsignadas.value.find(c => c.slot_inicio === slotInicio);

const asignarProveedor = (slot) => {
    if (!proveedorActivo.value || yaAsignado(proveedorActivo.value.id)) return;
    citasAsignadas.value.push({
        slot_inicio: slot.inicio,
        slot_fin: slot.fin,
        restaurantero_id: proveedorActivo.value.id,
        nombreProveedor: proveedorActivo.value.nombre_restaurante,
    });
    proveedorActivo.value = null;
};

const quitarAsignacion = (slotInicio) => {
    citasAsignadas.value = citasAsignadas.value.filter(c => c.slot_inicio !== slotInicio);
};

const formatHora = (iso) => new Date(iso).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' });

const cambiarComprador = () => {
    citasAsignadas.value = [];
    proveedorActivo.value = null;
};

const guardar = (enviar) => {
    if (!compradorSeleccionado.value || citasAsignadas.value.length === 0) return;
    guardando.value = true;
    router.post(route('admin.agenda.store'), {
        user_id: compradorSeleccionado.value.id,
        citas: citasAsignadas.value.map(c => ({
            restaurantero_id: c.restaurantero_id,
            slot_inicio: c.slot_inicio,
            slot_fin: c.slot_fin,
        })),
        enviar,
    }, {
        onFinish: () => { guardando.value = false; },
    });
};
</script>

<template>
    <AdminLayout title="Nueva propuesta de agenda">
        <div class="p-6 max-w-6xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-black text-gray-900 dark:text-white">Nueva propuesta de agenda</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">Evento: {{ evento?.nombre }}</p>
            </div>

            <div v-if="errores.error" class="mb-6 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 text-sm">
                {{ errores.error }}
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Columna izquierda -->
                <div class="lg:col-span-1 space-y-5">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Comprador</label>
                        <select v-model="compradorSeleccionado" @change="cambiarComprador"
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-guinda-500">
                            <option :value="null">-- Selecciona comprador --</option>
                            <option v-for="c in compradores" :key="c.id" :value="c">
                                {{ c.nombre_empresa || c.name }}{{ c.municipio ? ' — ' + c.municipio : '' }}{{ c.camara_asociacion === 'CANIRAC' ? ' (CANIRAC)' : '' }}
                            </option>
                        </select>
                    </div>

                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">
                            Proveedores aprobados en el evento
                        </p>
                        <p class="text-xs text-gray-400 mb-3">Selecciona uno y haz clic en un horario libre</p>

                        <!-- Búsqueda -->
                        <div class="relative mb-3">
                            <input v-model="buscarProveedor" type="text" placeholder="Buscar por nombre o categoría..."
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-guinda-500 transition-colors" />
                            <span class="absolute right-3 top-2.5 text-gray-400 text-xs">
                                {{ proveedoresFiltrados.length }}
                            </span>
                        </div>

                        <!-- Lista desplegable scrolleable -->
                        <div class="border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden max-h-64 overflow-y-auto bg-white dark:bg-gray-900">
                            <div v-for="p in proveedoresFiltrados" :key="p.id"
                                @click="!yaAsignado(p.id) && (proveedorActivo = p)"
                                :class="[
                                    'flex items-center gap-3 px-4 py-2.5 cursor-pointer border-b border-gray-100 dark:border-gray-800 last:border-0 transition-colors text-sm',
                                    proveedorActivo?.id === p.id
                                        ? 'bg-guinda-50 dark:bg-guinda-900/20 text-guinda-800 dark:text-guinda-300'
                                        : yaAsignado(p.id)
                                        ? 'bg-gray-50 dark:bg-gray-800 text-gray-400 cursor-not-allowed'
                                        : 'hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-800 dark:text-gray-200'
                                ]">
                                <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center flex-shrink-0">
                                    <img v-if="p.logo_path" :src="'/storage/' + p.logo_path" class="w-full h-full object-cover" />
                                    <span v-else class="text-sm">🏪</span>
                                </div>

                                <span v-if="yaAsignado(p.id)" class="text-green-500 flex-shrink-0">✓</span>
                                <span v-else-if="proveedorActivo?.id === p.id" class="w-3 h-3 rounded-full bg-guinda-600 flex-shrink-0"></span>
                                <span v-else class="w-3 h-3 rounded-full border-2 border-gray-300 dark:border-gray-600 flex-shrink-0"></span>

                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold truncate">{{ p.nombre_restaurante }}</p>
                                    <p v-if="p.categoria" class="text-xs text-gray-400 truncate">{{ p.categoria }}</p>
                                </div>
                            </div>
                            <div v-if="proveedoresFiltrados.length === 0" class="py-8 text-center text-gray-400 text-sm">
                                No se encontraron proveedores
                            </div>
                        </div>

                        <!-- Proveedor seleccionado activo -->
                        <div v-if="proveedorActivo && !yaAsignado(proveedorActivo.id)"
                            class="mt-3 flex items-center gap-2 px-3 py-2 bg-guinda-50 dark:bg-guinda-900/20 border border-guinda-300 dark:border-guinda-700 rounded-xl text-sm text-guinda-700 dark:text-guinda-300">
                            <span class="font-semibold flex-1 truncate">{{ proveedorActivo.nombre_restaurante }}</span>
                            <span class="text-xs opacity-70 shrink-0">elige un slot →</span>
                            <button type="button" @click="proveedorActivo = null" class="text-guinda-400 hover:text-guinda-700 ml-1 text-lg leading-none shrink-0">×</button>
                        </div>
                    </div>

                    <div v-if="citasAsignadas.length > 0" class="bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800 rounded-2xl p-5">
                        <p class="text-sm font-bold text-green-800 dark:text-green-400 mb-2">
                            {{ citasAsignadas.length }} cita(s) en la propuesta
                        </p>
                        <div v-for="c in [...citasAsignadas].sort((a, b) => new Date(a.slot_inicio) - new Date(b.slot_inicio))" :key="c.slot_inicio"
                            class="flex justify-between text-xs py-1 border-b border-green-200 dark:border-green-800/50 last:border-0">
                            <span class="text-gray-500 dark:text-gray-400 font-semibold">
                                {{ formatHora(c.slot_inicio) }} – {{ formatHora(c.slot_fin) }}
                            </span>
                            <span class="font-semibold text-gray-800 dark:text-gray-200">{{ c.nombreProveedor }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <button type="button" @click="guardar(false)" :disabled="!compradorSeleccionado || citasAsignadas.length === 0 || guardando"
                            class="py-3 border border-guinda-500 text-guinda-700 dark:text-guinda-400 font-bold rounded-xl disabled:opacity-50 transition-colors">
                            💾 Guardar borrador
                        </button>
                        <button type="button" @click="guardar(true)" :disabled="!compradorSeleccionado || citasAsignadas.length === 0 || guardando"
                            class="py-3 bg-guinda-800 hover:bg-guinda-700 text-white font-bold rounded-xl disabled:opacity-50 transition-colors shadow-sm">
                            📧 Enviar propuesta al comprador
                        </button>
                    </div>
                </div>

                <!-- Columna derecha: timeline -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                        <p v-if="!compradorSeleccionado" class="text-center text-gray-400 dark:text-gray-600 py-16 text-sm">
                            Selecciona un comprador para ver los horarios disponibles.
                        </p>
                        <div v-else>
                            <div v-for="slot in slots" :key="slot.inicio" class="flex items-center gap-3 py-1.5">
                                <span class="w-14 text-xs font-bold text-gray-400 dark:text-gray-600 text-right shrink-0">{{ slot.label }}</span>

                                <div v-if="!getAsignacion(slot.inicio)"
                                    @click="asignarProveedor(slot)"
                                    :class="[
                                        'flex-1 py-2 px-3 rounded-xl border text-xs transition-all',
                                        proveedorActivo
                                            ? 'border-guinda-300 dark:border-guinda-700 bg-guinda-50 dark:bg-guinda-900/10 text-guinda-700 dark:text-guinda-400 hover:border-guinda-500 cursor-pointer'
                                            : 'border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/40 text-gray-400 dark:text-gray-600 cursor-default'
                                    ]">
                                    {{ proveedorActivo ? '+ Asignar: ' + proveedorActivo.nombre_restaurante : '— Libre —' }}
                                </div>
                                <div v-else class="flex-1 flex items-center justify-between py-2 px-3 rounded-xl border border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/10 text-xs">
                                    <span class="font-semibold text-green-800 dark:text-green-400">
                                        ✓ {{ getAsignacion(slot.inicio).nombreProveedor }}
                                    </span>
                                    <button type="button" @click="quitarAsignacion(slot.inicio)"
                                        class="text-red-400 hover:text-red-600 ml-2 text-base leading-none">×</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
