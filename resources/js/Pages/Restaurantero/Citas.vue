<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    citas:         Object,
    tab:           String,
    counts:        Object,
    restaurantero: Object,
});

const page = usePage();
const flash = computed(() => page.props.flash);

const tabs = [
    { key: 'pendientes',  label: 'Pendientes',  count: props.counts?.pendientes },
    { key: 'confirmadas', label: 'Confirmadas', count: props.counts?.confirmadas },
    { key: 'rechazadas',  label: 'Rechazadas',  count: props.counts?.rechazadas },
    { key: 'historial',   label: 'Historial',   count: null },
];

const cambiarTab = (key) => {
    router.get(route('restaurantero.citas.index'), { tab: key }, { preserveState: true, preserveScroll: true });
};

// Modal de reagendamiento
const modalReagendar = ref(false);
const citaSeleccionada = ref(null);
const formReagendar = ref({ propuesta_fecha: '', propuesta_hora: '' });

const abrirReagendar = (cita) => {
    citaSeleccionada.value = cita;
    formReagendar.value = { propuesta_fecha: '', propuesta_hora: '' };
    modalReagendar.value = true;
};

const cerrarModal = () => { modalReagendar.value = false; citaSeleccionada.value = null; };

const procesando = ref(false);

const accion = (type, cita) => {
    if (procesando.value) return;
    const mensajes = { aceptar: '¿Aceptar esta cita?', rechazar: '¿Rechazar esta cita?' };
    if (!confirm(mensajes[type])) return;
    procesando.value = true;
    router[type === 'aceptar' ? 'patch' : 'patch'](
        route(`restaurantero.citas.${type}`, cita.id),
        {},
        { onFinish: () => { procesando.value = false; } }
    );
};

const submitReagendar = () => {
    if (!citaSeleccionada.value) return;
    procesando.value = true;
    router.post(route('restaurantero.citas.reagendar', citaSeleccionada.value.id), formReagendar.value, {
        onSuccess: () => { cerrarModal(); },
        onFinish:  () => { procesando.value = false; },
    });
};

const estadoConfig = {
    pendiente:              { label: 'Pendiente',             class: 'bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-500/20' },
    confirmada:             { label: 'Confirmada',            class: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-500/20' },
    cancelada:              { label: 'Cancelada',             class: 'bg-gray-500/15 text-gray-500 dark:text-gray-400 border-gray-500/20' },
    completada:             { label: 'Completada',            class: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/20' },
    rechazada:              { label: 'Rechazada',             class: 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/20' },
    reagendada:             { label: 'Reagendada',            class: 'bg-guinda-500/15 text-guinda-600 dark:text-guinda-400 border-guinda-500/20' },
    pendiente_reconfirmacion: { label: 'Esp. confirmación',  class: 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/20' },
};

const fmt = (dt) => dt ? new Date(dt).toLocaleString('es-MX', { weekday:'short', month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' }) : '—';
</script>

<template>
    <AppLayout title="Mis Citas — Panel Proveedor">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Gestión de Citas</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">{{ restaurantero?.nombre_restaurante }}</p>
                </div>
                <Link :href="route('restaurantero.panel')"
                    class="text-sm text-guinda-700 dark:text-guinda-400 hover:underline flex items-center gap-1">
                    ← Panel principal
                </Link>
            </div>
        </template>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

            <!-- Flash -->
            <div v-if="flash?.success" class="mb-5 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-300 dark:border-emerald-700 text-emerald-700 dark:text-emerald-400 text-sm font-medium px-4 py-3 rounded-xl">
                {{ flash.success }}
            </div>

            <!-- Tabs -->
            <div class="flex gap-1 p-1 bg-gray-100 dark:bg-gray-800 rounded-xl mb-6 overflow-x-auto">
                <button v-for="t in tabs" :key="t.key" @click="cambiarTab(t.key)"
                    :class="[
                        'flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all whitespace-nowrap',
                        tab === t.key
                            ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-white shadow-sm'
                            : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200'
                    ]">
                    {{ t.label }}
                    <span v-if="t.count != null && t.count > 0"
                        class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold"
                        :class="tab === t.key ? 'bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-400' : 'bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-300'">
                        {{ t.count }}
                    </span>
                </button>
            </div>

            <!-- Tabla -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm">

                <div v-if="citas.data.length === 0" class="text-center py-16 text-gray-400 dark:text-gray-600">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/>
                    </svg>
                    <p class="font-medium">No hay citas en esta categoría</p>
                </div>

                <!-- Cards móvil -->
                <div v-else class="md:hidden divide-y divide-gray-100 dark:divide-gray-800/40">
                    <div v-for="cita in citas.data" :key="'m-' + cita.id"
                        class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900 dark:text-white truncate">{{ cita.cliente?.name || '—' }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ cita.cliente?.email }}</p>
                                <p v-if="cita.cliente?.telefono" class="text-xs text-gray-400 dark:text-gray-500">{{ cita.cliente.telefono }}</p>
                            </div>
                            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
                                :class="estadoConfig[cita.estado]?.class || 'bg-gray-500/15 text-gray-400'">
                                {{ estadoConfig[cita.estado]?.label || cita.estado }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300 font-medium">{{ fmt(cita.inicio) }}</p>
                        <p v-if="cita.propuesta_inicio" class="text-xs text-purple-600 dark:text-purple-400">
                            → Propuesta: {{ fmt(cita.propuesta_inicio) }}
                        </p>
                        <div v-if="cita.estado === 'pendiente'" class="flex gap-2 pt-1">
                            <button @click="accion('aceptar', cita)" :disabled="procesando"
                                class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-colors min-h-[44px]">
                                ✓ Aceptar
                            </button>
                            <button @click="abrirReagendar(cita)" :disabled="procesando"
                                class="flex-1 py-2.5 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-colors min-h-[44px]">
                                ↻ Reagendar
                            </button>
                            <button @click="accion('rechazar', cita)" :disabled="procesando"
                                class="flex-1 py-2.5 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white text-xs font-bold rounded-xl transition-colors min-h-[44px]">
                                ✕ Rechazar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabla escritorio -->
                <div v-if="citas.data.length > 0" class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                                <th class="text-left py-3 px-5 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Comprador</th>
                                <th class="text-left py-3 px-5 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Fecha/Hora</th>
                                <th class="text-left py-3 px-5 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Estado</th>
                                <th class="text-right py-3 px-5 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="cita in citas.data" :key="cita.id"
                                class="border-b border-gray-100 dark:border-gray-800/40 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors">
                                <td class="py-3.5 px-5">
                                    <p class="font-semibold text-gray-800 dark:text-gray-200">{{ cita.cliente?.name || '—' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ cita.cliente?.email }}</p>
                                    <p v-if="cita.cliente?.telefono" class="text-xs text-gray-400 dark:text-gray-500">{{ cita.cliente.telefono }}</p>
                                </td>
                                <td class="py-3.5 px-5">
                                    <p class="text-gray-700 dark:text-gray-300 font-medium">{{ fmt(cita.inicio) }}</p>
                                    <p v-if="cita.propuesta_inicio" class="text-xs text-purple-600 dark:text-purple-400 mt-0.5">
                                        → Propuesta: {{ fmt(cita.propuesta_inicio) }}
                                    </p>
                                </td>
                                <td class="py-3.5 px-5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border"
                                          :class="estadoConfig[cita.estado]?.class || 'bg-gray-500/15 text-gray-400'">
                                        {{ estadoConfig[cita.estado]?.label || cita.estado }}
                                    </span>
                                </td>
                                <td class="py-3.5 px-5">
                                    <div class="flex items-center justify-end gap-2">
                                        <template v-if="cita.estado === 'pendiente'">
                                            <button @click="accion('aceptar', cita)" :disabled="procesando"
                                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-semibold rounded-lg transition-colors">
                                                Aceptar
                                            </button>
                                            <button @click="abrirReagendar(cita)" :disabled="procesando"
                                                class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 disabled:opacity-50 text-white text-xs font-semibold rounded-lg transition-colors">
                                                Reagendar
                                            </button>
                                            <button @click="accion('rechazar', cita)" :disabled="procesando"
                                                class="px-3 py-1.5 bg-red-600 hover:bg-red-500 disabled:opacity-50 text-white text-xs font-semibold rounded-lg transition-colors">
                                                Rechazar
                                            </button>
                                        </template>
                                        <span v-else class="text-xs text-gray-400 dark:text-gray-600 italic">—</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <div v-if="citas.last_page > 1" class="px-5 py-4 border-t border-gray-200 dark:border-gray-800 flex items-center justify-between">
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Mostrando {{ citas.from }}–{{ citas.to }} de {{ citas.total }}
                    </p>
                    <div class="flex gap-2">
                        <Link v-if="citas.prev_page_url" :href="citas.prev_page_url"
                            class="px-3 py-1.5 text-xs border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            ←
                        </Link>
                        <Link v-if="citas.next_page_url" :href="citas.next_page_url"
                            class="px-3 py-1.5 text-xs border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            →
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Reagendar -->
        <Teleport to="body">
            <div v-if="modalReagendar" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 style="background:rgba(0,0,0,0.5)" @click.self="cerrarModal">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl w-full max-w-md shadow-2xl">
                    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 flex items-center justify-between">
                        <h3 class="font-bold text-gray-900 dark:text-white">Proponer nueva fecha</h3>
                        <button @click="cerrarModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-white transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div v-if="citaSeleccionada" class="bg-gray-50 dark:bg-gray-800 rounded-xl p-3 text-sm">
                            <p class="text-gray-500 dark:text-gray-400 text-xs mb-1">Cita original</p>
                            <p class="font-semibold text-gray-800 dark:text-gray-200">{{ citaSeleccionada.cliente?.name }}</p>
                            <p class="text-gray-500 dark:text-gray-400 text-xs">{{ fmt(citaSeleccionada.inicio) }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nueva fecha</label>
                            <input v-model="formReagendar.propuesta_fecha" type="date"
                                :min="new Date().toISOString().split('T')[0]"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nueva hora</label>
                            <input v-model="formReagendar.propuesta_hora" type="time"
                                class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            El comprador recibirá un correo con botones para aceptar o rechazar la propuesta.
                        </p>
                    </div>

                    <div class="px-6 pb-5 flex gap-3">
                        <button @click="cerrarModal" class="flex-1 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Cancelar
                        </button>
                        <button @click="submitReagendar" :disabled="procesando || !formReagendar.propuesta_fecha || !formReagendar.propuesta_hora"
                            class="flex-1 py-2.5 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 text-white rounded-xl text-sm font-bold transition-colors">
                            {{ procesando ? 'Enviando...' : 'Enviar propuesta' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
