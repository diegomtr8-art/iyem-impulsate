<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    encuesta:  Object,
    evento:    Object,
    usuario:   Object,
    preguntas: Array,
});

const respuestas = ref({});
const enviando   = ref(false);
const errores    = ref({});

props.preguntas?.forEach(p => {
    respuestas.value[p.id] = p.tipo === 'multiple' ? [] : '';
});

// ── Evaluador de condicionales ────────────────────────────────────────────
const preguntaVisible = (pregunta) => {
    if (!pregunta.condicion?.pregunta_id) return true;
    const { pregunta_id, operador, valor } = pregunta.condicion;
    const respuestaFuente = respuestas.value[pregunta_id];
    if (respuestaFuente === undefined || respuestaFuente === '' ||
        (Array.isArray(respuestaFuente) && respuestaFuente.length === 0)) {
        return false;
    }
    const rf = String(respuestaFuente);
    const vl = String(valor);
    switch (operador) {
        case 'igual':     return rf === vl;
        case 'diferente': return rf !== vl;
        case 'mayor':     return parseFloat(rf) > parseFloat(vl);
        case 'menor':     return parseFloat(rf) < parseFloat(vl);
        default:          return true;
    }
};

const enviar = () => {
    errores.value = {};
    let valido = true;

    props.preguntas?.forEach(p => {
        if (!preguntaVisible(p) || p.requerida === false) return;
        const r = respuestas.value[p.id];
        const vacio = Array.isArray(r) ? r.length === 0 : (!r && r !== '0');
        if (vacio) {
            errores.value[p.id] = 'Este campo es requerido.';
            valido = false;
        }
    });

    if (!valido) return;

    enviando.value = true;
    router.post(route('encuestas.responder.store', props.encuesta.token), respuestas.value, {
        onError: () => { enviando.value = false; },
    });
};
</script>

<template>
    <Head title="Encuesta de satisfacción — Impúlsate" />

    <div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
        <div class="w-full max-w-xl">

            <!-- Header -->
            <div class="text-center mb-8">
                <img src="/images/logo_impulsate.png" alt="Impúlsate" class="h-10 mx-auto mb-4" />
                <h1 class="text-2xl font-black text-gray-900">Encuesta de Satisfacción</h1>
                <p v-if="evento" class="text-guinda-700 font-semibold mt-1">{{ evento.nombre }}</p>
                <p v-if="usuario" class="text-gray-500 text-sm mt-1">Hola, {{ usuario.name }}</p>
            </div>

            <form @submit.prevent="enviar" class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">

                <div class="px-6 py-5 border-b border-gray-100 bg-guinda-50">
                    <p class="text-sm text-guinda-700 font-medium">
                        Por favor responde las siguientes preguntas. Tu opinión nos ayuda a mejorar.
                    </p>
                </div>

                <div class="px-6 py-6 space-y-6">

                    <template v-for="(pregunta, idx) in preguntas" :key="pregunta.id">
                        <div v-if="preguntaVisible(pregunta)">
                            <label class="block text-sm font-semibold text-gray-800 mb-2">
                                {{ idx + 1 }}. {{ pregunta.texto }}
                                <span v-if="pregunta.requerida !== false" class="text-red-500 ml-1">*</span>
                            </label>

                            <!-- Escala -->
                            <template v-if="pregunta.tipo === 'escala'">
                                <div class="flex gap-2">
                                    <button v-for="n in Array.from({ length: (pregunta.escala_max ?? 5) - (pregunta.escala_min ?? 1) + 1 }, (_, i) => i + (pregunta.escala_min ?? 1))"
                                        :key="n" type="button"
                                        @click="respuestas[pregunta.id] = String(n)"
                                        class="flex-1 py-2.5 rounded-xl text-sm font-bold border-2 transition-all"
                                        :class="respuestas[pregunta.id] === String(n)
                                            ? 'bg-guinda-800 border-guinda-800 text-white shadow-sm'
                                            : 'border-gray-200 text-gray-600 hover:border-guinda-300 hover:text-guinda-700'">
                                        {{ n }}
                                    </button>
                                </div>
                                <div class="flex justify-between text-xs text-gray-400 mt-1 px-0.5">
                                    <span>Muy malo</span>
                                    <span>Excelente</span>
                                </div>
                            </template>

                            <!-- NPS -->
                            <template v-else-if="pregunta.tipo === 'nps'">
                                <div class="flex gap-1 flex-wrap">
                                    <button v-for="n in 11" :key="n - 1" type="button"
                                        @click="respuestas[pregunta.id] = String(n - 1)"
                                        class="w-10 h-10 rounded-lg text-sm font-bold border-2 transition-all"
                                        :class="respuestas[pregunta.id] === String(n - 1)
                                            ? 'bg-guinda-800 border-guinda-800 text-white shadow-sm'
                                            : 'border-gray-200 text-gray-600 hover:border-guinda-300 hover:text-guinda-700'">
                                        {{ n - 1 }}
                                    </button>
                                </div>
                                <div class="flex justify-between text-xs text-gray-400 mt-1 px-0.5">
                                    <span>Muy improbable</span>
                                    <span>Muy probable</span>
                                </div>
                            </template>

                            <!-- Sí/No -->
                            <template v-else-if="pregunta.tipo === 'binario'">
                                <div class="flex gap-3">
                                    <button v-for="opcion in ['Sí', 'No']" :key="opcion" type="button"
                                        @click="respuestas[pregunta.id] = opcion"
                                        class="flex-1 py-2.5 rounded-xl text-sm font-bold border-2 transition-all"
                                        :class="respuestas[pregunta.id] === opcion
                                            ? 'bg-guinda-800 border-guinda-800 text-white'
                                            : 'border-gray-200 text-gray-600 hover:border-guinda-300'">
                                        {{ opcion }}
                                    </button>
                                </div>
                            </template>

                            <!-- Texto libre -->
                            <template v-else-if="pregunta.tipo === 'texto'">
                                <textarea v-model="respuestas[pregunta.id]" rows="3"
                                    placeholder="Escribe tu respuesta aquí..."
                                    class="w-full text-sm bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-guinda-500 resize-none transition-colors">
                                </textarea>
                            </template>

                            <!-- Opción única -->
                            <template v-else-if="pregunta.tipo === 'opciones'">
                                <div class="space-y-2">
                                    <label v-for="opcion in pregunta.opciones" :key="opcion"
                                        class="flex items-center gap-3 cursor-pointer p-3 rounded-xl border transition-colors"
                                        :class="respuestas[pregunta.id] === opcion ? 'border-guinda-500 bg-guinda-50' : 'border-gray-200 hover:border-guinda-300'">
                                        <input type="radio" :name="pregunta.id" :value="opcion" v-model="respuestas[pregunta.id]" class="accent-guinda-700" />
                                        <span class="text-sm font-medium text-gray-700">{{ opcion }}</span>
                                    </label>
                                </div>
                            </template>

                            <!-- Selección múltiple -->
                            <template v-else-if="pregunta.tipo === 'multiple'">
                                <div class="space-y-2">
                                    <label v-for="opcion in pregunta.opciones" :key="opcion"
                                        class="flex items-center gap-3 cursor-pointer p-3 rounded-xl border transition-colors"
                                        :class="respuestas[pregunta.id]?.includes(opcion) ? 'border-guinda-500 bg-guinda-50' : 'border-gray-200 hover:border-guinda-300'">
                                        <input type="checkbox" :value="opcion" v-model="respuestas[pregunta.id]" class="accent-guinda-700" />
                                        <span class="text-sm font-medium text-gray-700">{{ opcion }}</span>
                                    </label>
                                </div>
                            </template>

                            <p v-if="errores[pregunta.id]" class="text-xs text-red-500 mt-1">
                                {{ errores[pregunta.id] }}
                            </p>
                        </div>
                    </template>

                </div>

                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                    <button type="submit" :disabled="enviando"
                        class="w-full py-3 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-60 text-white font-bold text-sm rounded-xl transition-colors">
                        {{ enviando ? 'Enviando...' : 'Enviar encuesta' }}
                    </button>
                </div>
            </form>

            <p class="text-center text-xs text-gray-400 mt-4">
                Impúlsate · Instituto Yucateco de Emprendedores
            </p>
        </div>
    </div>
</template>
