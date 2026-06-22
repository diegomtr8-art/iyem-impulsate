<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    encuestas:          Object,
    eventos:            Array,
    filtroEvento:       [String, Number, null],
    metricas:           Object,
    ultimasRespondidas: Array,
});

const eventoSeleccionado = ref(props.filtroEvento ?? '');
const emailPrueba = ref('');
const abiertas    = ref([]);

const filtrar = () => {
    router.get(route('admin.encuestas.index'), { evento_id: eventoSeleccionado.value || undefined }, { preserveScroll: true });
};

const enviarAEvento = () => {
    if (!eventoSeleccionado.value) return;
    if (!confirm('¿Enviar la encuesta a todos los participantes aprobados de este evento que aún no la han recibido?')) return;
    router.post(route('admin.encuestas.enviar-evento'), { evento_id: eventoSeleccionado.value }, { preserveScroll: true });
};

const enviarPrueba = () => {
    router.post(route('admin.encuestas.enviar-prueba'), { email: emailPrueba.value }, {
        preserveScroll: true,
        onSuccess: () => { emailPrueba.value = ''; },
    });
};

const toggleAcordeon = (id) => {
    const idx = abiertas.value.indexOf(id);
    if (idx >= 0) abiertas.value.splice(idx, 1);
    else abiertas.value.push(id);
};

const urlExportar = computed(() => {
    const base = route('admin.encuestas.exportar');
    return eventoSeleccionado.value ? `${base}?evento_id=${eventoSeleccionado.value}` : base;
});
</script>

<template>
    <AdminLayout title="Encuestas">
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Encuestas de Satisfacción</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Respuestas de participantes por evento</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.encuestas.plantillas')"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-semibold rounded-xl transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Plantillas de encuesta
                    </Link>
                    <a :href="urlExportar"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Exportar Excel
                    </a>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-5xl">

            <!-- Filtro por evento + enviar encuestas -->
            <div class="flex flex-wrap items-end gap-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Filtrar por evento</label>
                    <select v-model="eventoSeleccionado"
                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500">
                        <option value="">— Todos los eventos —</option>
                        <option v-for="ev in eventos" :key="ev.id" :value="ev.id">{{ ev.nombre }}</option>
                    </select>
                </div>
                <button @click="filtrar"
                    class="px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                    Filtrar
                </button>
                <button @click="enviarAEvento" :disabled="!eventoSeleccionado"
                    class="px-4 py-2 bg-amber-600 hover:bg-amber-500 disabled:opacity-40 text-white text-sm font-semibold rounded-xl transition-colors">
                    Enviar encuestas a este evento
                </button>
            </div>

            <!-- Botón de prueba -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Enviar encuesta de prueba a</label>
                    <input v-model="emailPrueba" type="email" placeholder="tu-correo@ejemplo.com"
                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500" />
                </div>
                <button @click="enviarPrueba" :disabled="!emailPrueba"
                    class="px-4 py-2 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl transition-colors">
                    Enviar prueba
                </button>
            </div>

            <!-- Stats rápidos -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-guinda-700 dark:text-guinda-400">{{ metricas?.total_enviadas ?? 0 }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Total enviadas</div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-emerald-200 dark:border-emerald-800/50 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ metricas?.total_respondidas ?? 0 }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Respondidas</div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-amber-200 dark:border-amber-800/50 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ metricas?.total_pendientes ?? 0 }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Pendientes</div>
                </div>
                <div class="bg-white dark:bg-gray-900 border border-blue-200 dark:border-blue-800/50 rounded-2xl p-4 text-center">
                    <div class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ metricas?.tasa_respuesta ?? 0 }}%</div>
                    <div class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Tasa de respuesta</div>
                </div>
            </div>

            <!-- Métricas por pregunta -->
            <div v-if="metricas?.escala?.length || metricas?.binario?.length" class="space-y-4">

                <!-- Preguntas de escala -->
                <div v-if="metricas?.escala?.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Promedio por pregunta (escala 1–5)</h3>
                    <div class="space-y-3">
                        <div v-for="m in metricas.escala" :key="m.pregunta">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-600 dark:text-gray-400 flex-1 mr-3 leading-snug">{{ m.pregunta }}</span>
                                <span class="text-sm font-black text-guinda-700 dark:text-guinda-400 shrink-0">{{ Number(m.promedio).toFixed(1) }} / 5</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2">
                                <div class="bg-guinda-600 dark:bg-guinda-500 h-2 rounded-full transition-all"
                                    :style="{ width: (Number(m.promedio) / 5 * 100) + '%' }"></div>
                            </div>
                            <div class="text-xs text-gray-400 dark:text-gray-600 mt-0.5">{{ m.total }} respuesta{{ m.total !== 1 ? 's' : '' }}</div>
                        </div>
                    </div>
                </div>

                <!-- Preguntas binarias -->
                <div v-if="metricas?.binario?.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4">Preguntas Sí/No</h3>
                    <div class="space-y-3">
                        <div v-for="m in metricas.binario" :key="m.pregunta">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs text-gray-600 dark:text-gray-400 flex-1 mr-3 leading-snug">{{ m.pregunta }}</span>
                                <span class="text-sm font-black text-emerald-600 dark:text-emerald-400 shrink-0">{{ m.porcentaje_si }}% Sí</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-2">
                                <div class="bg-emerald-500 h-2 rounded-full transition-all"
                                    :style="{ width: m.porcentaje_si + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acordeón últimas 10 respondidas -->
            <div v-if="ultimasRespondidas?.length" class="space-y-2">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Últimas 10 encuestas respondidas</h3>
                <div v-for="enc in ultimasRespondidas" :key="enc.id"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden">
                    <button @click="toggleAcordeon(enc.id)"
                        class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                        <div>
                            <span class="font-semibold text-gray-900 dark:text-white text-sm">{{ enc.usuario ?? 'Sin nombre' }}</span>
                            <span class="text-xs text-gray-400 ml-2">{{ enc.evento ?? '—' }} · {{ enc.completada_at }}</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400 transition-transform flex-shrink-0 ml-2"
                            :class="abiertas.includes(enc.id) ? 'rotate-180' : ''"
                            fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div v-if="abiertas.includes(enc.id)" class="px-4 pb-4 space-y-3 border-t border-gray-100 dark:border-gray-800 pt-3">
                        <div v-for="(r, idx) in enc.respuestas" :key="idx">
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ r.pregunta }}</p>
                            <p class="text-sm text-gray-800 dark:text-gray-200">{{ r.respuesta }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm">
                <div v-if="!encuestas?.data?.length"
                    class="text-center py-16 text-gray-400 dark:text-gray-600">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <p class="text-sm">No hay encuestas para mostrar</p>
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Evento</th>
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Participante</th>
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Tipo</th>
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Estado</th>
                            <th class="text-left px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">Respondida</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="enc in encuestas.data" :key="enc.id"
                            class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 font-medium max-w-[180px] truncate">
                                {{ enc.evento?.nombre ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ enc.usuario?.name ?? '—' }}</div>
                                <div class="text-xs text-gray-400 dark:text-gray-500">{{ enc.usuario?.email ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full capitalize"
                                    :class="enc.tipo === 'proveedor'
                                        ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400'
                                        : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'">
                                    {{ enc.tipo }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                    :class="enc.completada
                                        ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'
                                        : 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'">
                                    {{ enc.completada ? 'Respondida' : 'Pendiente' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400 dark:text-gray-500">
                                {{ enc.completada_at ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>

                <!-- Paginación -->
                <div v-if="encuestas?.last_page > 1"
                    class="flex items-center justify-between px-4 py-3 border-t border-gray-100 dark:border-gray-800">
                    <span class="text-xs text-gray-400">
                        Página {{ encuestas.current_page }} de {{ encuestas.last_page }}
                    </span>
                    <div class="flex gap-2">
                        <Link v-if="encuestas.prev_page_url" :href="encuestas.prev_page_url"
                            class="px-3 py-1 text-xs rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                            ← Anterior
                        </Link>
                        <Link v-if="encuestas.next_page_url" :href="encuestas.next_page_url"
                            class="px-3 py-1 text-xs rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800">
                            Siguiente →
                        </Link>
                    </div>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>
