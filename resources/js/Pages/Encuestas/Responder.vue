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
    respuestas.value[p.id] = '';
});

const enviar = () => {
    errores.value = {};
    let valido = true;

    props.preguntas?.forEach(p => {
        if (!respuestas.value[p.id] && respuestas.value[p.id] !== '0') {
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

                    <div v-for="(pregunta, idx) in preguntas" :key="pregunta.id">
                        <label class="block text-sm font-semibold text-gray-800 mb-2">
                            {{ idx + 1 }}. {{ pregunta.texto }}
                        </label>

                        <!-- Escala 1-5 -->
                        <template v-if="pregunta.tipo === 'escala'">
                            <div class="flex gap-2">
                                <button v-for="n in [1,2,3,4,5]" :key="n" type="button"
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
                        <template v-else>
                            <textarea v-model="respuestas[pregunta.id]" rows="3"
                                placeholder="Escribe tu respuesta aquí..."
                                class="w-full text-sm bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:border-guinda-500 resize-none transition-colors">
                            </textarea>
                        </template>

                        <p v-if="errores[pregunta.id]" class="text-xs text-red-500 mt-1">
                            {{ errores[pregunta.id] }}
                        </p>
                    </div>

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
