<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    acepta: false,
});

const submit = () => {
    form.post(route('aviso.aceptar.post'));
};

const cerrarSesion = () => {
    router.post(route('logout'));
};
</script>

<template>
    <Head title="Aviso de Privacidad — Impulsate" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex items-center justify-center px-4 py-12 transition-colors">
        <div class="w-full max-w-lg">
            <div class="text-center mb-8">
                <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-12 w-auto mx-auto mb-4" />
                <h1 class="text-2xl font-black text-gray-900 dark:text-white">Aviso de Privacidad</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">
                    Antes de continuar, necesitas leer y aceptar nuestro Aviso de Privacidad.
                </p>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 shadow-sm">
                <!-- Resumen del aviso -->
                <div class="bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 rounded-xl p-5 mb-6">
                    <h2 class="text-sm font-bold text-guinda-800 dark:text-guinda-300 mb-3">Resumen del Aviso de Privacidad</h2>
                    <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                        <p>
                            <strong class="text-gray-800 dark:text-gray-200">Responsable:</strong>
                            Dr. Rodrigo Ignacio Ortiz Eljure, Director Jurídico,
                            Dirección Jurídica del Instituto Yucateco de Emprendedores (IYEM).
                        </p>
                        <p>
                            <strong class="text-gray-800 dark:text-gray-200">Finalidad principal:</strong>
                            Verificar tu identidad, asignarte espacios en eventos de networking y mantener comunicación
                            contigo sobre fechas, requisitos y logística.
                        </p>
                        <p>
                            <strong class="text-gray-800 dark:text-gray-200">Datos recabados:</strong>
                            Nombre, correo electrónico, teléfono, CURP, RFC, municipio, datos del negocio.
                        </p>
                        <p>
                            <strong class="text-gray-800 dark:text-gray-200">Finalidades secundarias:</strong>
                            Estadísticas institucionales, publicaciones en redes sociales oficiales y promoción de
                            programas del IYEM.
                        </p>
                    </div>
                    <div class="mt-4">
                        <Link :href="route('aviso.privacidad')" target="_blank"
                            class="inline-flex items-center gap-1.5 text-sm font-medium text-guinda-700 dark:text-guinda-400 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                            Leer el Aviso de Privacidad completo
                        </Link>
                    </div>
                </div>

                <!-- Formulario de aceptación -->
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="flex items-start gap-3 cursor-pointer">
                            <Checkbox id="acepta" v-model:checked="form.acepta" name="acepta" class="mt-0.5 shrink-0" />
                            <span class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                                He leído y acepto el
                                <Link :href="route('aviso.privacidad')" target="_blank"
                                    class="text-guinda-700 dark:text-guinda-400 underline font-medium">
                                    Aviso de Privacidad
                                </Link>
                                del Instituto Yucateco de Emprendedores (IYEM). Entiendo que mis datos serán tratados
                                conforme a la Ley General de Protección de Datos Personales en Posesión de Sujetos Obligados.
                            </span>
                        </label>
                        <InputError class="mt-2" :message="form.errors.acepta" />
                    </div>

                    <button type="submit"
                        :disabled="form.processing || !form.acepta"
                        class="w-full py-3 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-colors text-sm shadow-sm">
                        {{ form.processing ? 'Procesando...' : 'Aceptar y continuar' }}
                    </button>
                </form>

                <div class="text-center mt-4">
                    <button @click="cerrarSesion"
                        class="text-sm text-gray-400 dark:text-gray-600 hover:text-gray-600 dark:hover:text-gray-400 underline transition-colors">
                        No acepto — cerrar sesión
                    </button>
                </div>
            </div>

            <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-6">
                Programa Gubernamental • Gobierno del Estado de Yucatán
            </p>
        </div>
    </div>
</template>
