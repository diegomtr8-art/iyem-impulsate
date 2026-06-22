<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, reactive, computed } from 'vue';

const props = defineProps({
    plantillas: Array,
});

// ── Formulario ─────────────────────────────────────────────────────────────
const mostrarFormulario = ref(false);
const form = reactive({
    id:          null,
    nombre:      '',
    descripcion: '',
    preguntas:   [],
});

const abrirNueva = () => {
    form.id = null;
    form.nombre = '';
    form.descripcion = '';
    form.preguntas = [];
    mostrarFormulario.value = true;
};

const abrirEditar = (p) => {
    form.id          = p.id;
    form.nombre      = p.nombre;
    form.descripcion = p.descripcion ?? '';
    form.preguntas   = p.preguntas.map(q => ({ ...q }));
    mostrarFormulario.value = true;
};

const cerrar = () => { mostrarFormulario.value = false; };

const generarId = () => `pregunta_${Date.now()}_${Math.floor(Math.random() * 1000)}`;

const agregarPregunta = () => {
    form.preguntas.push({ id: generarId(), tipo: 'escala', texto: '' });
};

const eliminarPregunta = (idx) => {
    form.preguntas.splice(idx, 1);
};

const moverArriba = (idx) => {
    if (idx === 0) return;
    const tmp = form.preguntas[idx - 1];
    form.preguntas[idx - 1] = form.preguntas[idx];
    form.preguntas[idx]     = tmp;
};

const moverAbajo = (idx) => {
    if (idx === form.preguntas.length - 1) return;
    const tmp = form.preguntas[idx + 1];
    form.preguntas[idx + 1] = form.preguntas[idx];
    form.preguntas[idx]     = tmp;
};

const guardar = () => {
    router.post(route('admin.encuestas.plantillas.guardar'), { ...form }, {
        preserveScroll: true,
        onSuccess: () => cerrar(),
    });
};

// ── Acciones de plantilla existente ────────────────────────────────────────
const activar = (p) => {
    router.post(route('admin.encuestas.plantillas.activar', p.id), {}, { preserveScroll: true });
};

const eliminar = (p) => {
    if (!confirm(`¿Eliminar la plantilla "${p.nombre}"? Esta acción no se puede deshacer.`)) return;
    router.delete(route('admin.encuestas.plantillas.eliminar', p.id), { preserveScroll: true });
};

// ── Vista previa ────────────────────────────────────────────────────────────
const vistaPrevia = ref({});
const selPreview  = (pregId, val) => { vistaPrevia.value[pregId] = val; };
</script>

<template>
    <AdminLayout title="Plantillas de encuesta">
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Plantillas de encuesta</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Define las preguntas que verán los participantes</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.encuestas.index')"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors">
                        ← Volver
                    </Link>
                    <button @click="abrirNueva"
                        class="px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                        + Nueva plantilla
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-4xl">

            <!-- Lista de plantillas -->
            <div v-if="!plantillas.length" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center text-gray-400 dark:text-gray-600">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-sm">No hay plantillas todavía. Crea la primera.</p>
            </div>

            <div v-for="p in plantillas" :key="p.id"
                class="bg-white dark:bg-gray-900 border rounded-2xl p-5 space-y-3"
                :class="p.activa ? 'border-guinda-400 dark:border-guinda-700 ring-1 ring-guinda-300 dark:ring-guinda-800' : 'border-gray-200 dark:border-gray-800'">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-gray-900 dark:text-white">{{ p.nombre }}</h3>
                            <span v-if="p.activa"
                                class="text-xs font-semibold px-2 py-0.5 rounded-full bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-300">
                                Activa
                            </span>
                        </div>
                        <p v-if="p.descripcion" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ p.descripcion }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">{{ p.preguntas?.length ?? 0 }} pregunta{{ (p.preguntas?.length ?? 0) !== 1 ? 's' : '' }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button v-if="!p.activa" @click="activar(p)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                            Activar
                        </button>
                        <button @click="abrirEditar(p)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Editar
                        </button>
                        <button v-if="!p.activa" @click="eliminar(p)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            Eliminar
                        </button>
                    </div>
                </div>

                <!-- Preguntas en modo lectura -->
                <ol class="space-y-1 text-sm text-gray-600 dark:text-gray-400 list-decimal list-inside">
                    <li v-for="q in p.preguntas" :key="q.id">
                        {{ q.texto }}
                        <span class="text-xs text-gray-400 ml-1">({{ q.tipo === 'escala' ? 'Escala 1-5' : q.tipo === 'binario' ? 'Sí/No' : 'Texto libre' }})</span>
                    </li>
                </ol>
            </div>

            <!-- Modal / formulario de edición -->
            <Teleport to="body">
                <div v-if="mostrarFormulario"
                    class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/50 backdrop-blur-sm p-4 py-8"
                    @click.self="cerrar">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-xl w-full max-w-2xl">

                        <!-- Header modal -->
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                            <h2 class="font-bold text-gray-900 dark:text-white">
                                {{ form.id ? 'Editar plantilla' : 'Nueva plantilla' }}
                            </h2>
                            <button @click="cerrar" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="px-6 py-5 space-y-5">

                            <!-- Datos básicos -->
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre de la plantilla *</label>
                                    <input v-model="form.nombre" type="text" placeholder="Ej: Encuesta principal 2026"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Descripción (opcional)</label>
                                    <input v-model="form.descripcion" type="text" placeholder="Breve descripción..."
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500" />
                                </div>
                            </div>

                            <!-- Preguntas -->
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400">Preguntas</label>
                                    <button @click="agregarPregunta" type="button"
                                        class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 hover:underline">
                                        + Agregar pregunta
                                    </button>
                                </div>

                                <div v-if="!form.preguntas.length" class="text-xs text-gray-400 dark:text-gray-600 text-center py-4 border border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
                                    Ninguna pregunta todavía. Haz clic en "+ Agregar pregunta".
                                </div>

                                <div class="space-y-2">
                                    <div v-for="(q, idx) in form.preguntas" :key="q.id"
                                        class="flex items-start gap-2 bg-gray-50 dark:bg-gray-800 rounded-xl p-3">

                                        <!-- Ordenar -->
                                        <div class="flex flex-col gap-0.5 pt-0.5">
                                            <button @click="moverArriba(idx)" type="button"
                                                class="p-0.5 rounded text-gray-400 hover:text-guinda-600 disabled:opacity-30"
                                                :disabled="idx === 0">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>
                                                </svg>
                                            </button>
                                            <button @click="moverAbajo(idx)" type="button"
                                                class="p-0.5 rounded text-gray-400 hover:text-guinda-600 disabled:opacity-30"
                                                :disabled="idx === form.preguntas.length - 1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                </svg>
                                            </button>
                                        </div>

                                        <!-- Tipo -->
                                        <select v-model="q.tipo"
                                            class="text-xs bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-700 dark:text-gray-200 rounded-lg px-2 py-1.5 focus:outline-none focus:border-guinda-500 shrink-0">
                                            <option value="escala">Escala 1-5</option>
                                            <option value="binario">Sí/No</option>
                                            <option value="texto">Texto libre</option>
                                        </select>

                                        <!-- Texto -->
                                        <input v-model="q.texto" type="text" placeholder="Texto de la pregunta..."
                                            class="flex-1 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 text-gray-900 dark:text-white rounded-lg px-3 py-1.5 focus:outline-none focus:border-guinda-500" />

                                        <!-- Eliminar -->
                                        <button @click="eliminarPregunta(idx)" type="button"
                                            class="p-1.5 text-gray-400 hover:text-red-500 transition-colors shrink-0">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Vista previa en vivo -->
                            <div v-if="form.preguntas.length" class="border border-dashed border-guinda-300 dark:border-guinda-800 rounded-xl p-4 space-y-4">
                                <p class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 uppercase tracking-wide">Vista previa (como lo verá el participante)</p>

                                <div v-for="(q, idx) in form.preguntas" :key="q.id">
                                    <label class="block text-sm font-semibold text-gray-800 dark:text-gray-200 mb-2">
                                        {{ idx + 1 }}. {{ q.texto || '(sin texto)' }}
                                    </label>

                                    <!-- Escala -->
                                    <template v-if="q.tipo === 'escala'">
                                        <div class="flex gap-2">
                                            <button v-for="n in [1,2,3,4,5]" :key="n" type="button"
                                                @click="selPreview(q.id, String(n))"
                                                class="flex-1 py-2 rounded-xl text-sm font-bold border-2 transition-all"
                                                :class="vistaPrevia[q.id] === String(n)
                                                    ? 'bg-guinda-800 border-guinda-800 text-white'
                                                    : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-guinda-300'">
                                                {{ n }}
                                            </button>
                                        </div>
                                        <div class="flex justify-between text-xs text-gray-400 mt-1">
                                            <span>Muy malo</span><span>Excelente</span>
                                        </div>
                                    </template>

                                    <!-- Binario -->
                                    <template v-else-if="q.tipo === 'binario'">
                                        <div class="flex gap-3">
                                            <button v-for="op in ['Sí', 'No']" :key="op" type="button"
                                                @click="selPreview(q.id, op)"
                                                class="flex-1 py-2 rounded-xl text-sm font-bold border-2 transition-all"
                                                :class="vistaPrevia[q.id] === op
                                                    ? 'bg-guinda-800 border-guinda-800 text-white'
                                                    : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:border-guinda-300'">
                                                {{ op }}
                                            </button>
                                        </div>
                                    </template>

                                    <!-- Texto -->
                                    <template v-else>
                                        <textarea rows="2" disabled placeholder="(El participante escribirá aquí)"
                                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 resize-none text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                        </textarea>
                                    </template>
                                </div>
                            </div>

                        </div>

                        <!-- Footer modal -->
                        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 dark:border-gray-800">
                            <button @click="cerrar" type="button"
                                class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                                Cancelar
                            </button>
                            <button @click="guardar" type="button"
                                :disabled="!form.nombre || !form.preguntas.length"
                                class="px-5 py-2 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-40 text-white text-sm font-semibold rounded-xl transition-colors">
                                Guardar plantilla
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

        </div>
    </AdminLayout>
</template>
