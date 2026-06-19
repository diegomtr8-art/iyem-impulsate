<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

defineProps({ user: Object });

const form = useForm({
    telefono:               '',
    curp:                   '',
    rfc:                    '',
    municipio:              '',
    nombre_empresa:         '',
    camara_asociacion:      '',
    nombre_establecimiento: '',
});

const submit = () => {
    form.post(route('perfil.completar.store'));
};
</script>

<template>
    <Head title="Completa tu perfil — Impulsate" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex items-center justify-center px-4 transition-colors">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-12 w-auto mx-auto mb-4" />
                <h1 class="text-2xl font-black text-gray-900 dark:text-white">¡Completa tu perfil!</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">
                    Necesitamos algunos datos adicionales para continuar.
                </p>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 shadow-sm">
                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Teléfono <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.telefono" type="tel" required maxlength="10"
                            @input="form.telefono = form.telefono.replace(/\D/g, '').slice(0, 10)"
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors"
                            placeholder="9991234567" />
                        <InputError class="mt-1.5" :message="form.errors.telefono" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            CURP <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input v-model="form.curp" type="text" maxlength="18"
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors uppercase"
                            placeholder="XXXX000000XXXXXXXX" style="text-transform:uppercase" />
                        <InputError class="mt-1.5" :message="form.errors.curp" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            RFC <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input v-model="form.rfc" type="text" maxlength="13"
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors uppercase"
                            placeholder="XXXX000000XXX"
                            @input="form.rfc = form.rfc.replace(/[^a-zA-ZñÑ&0-9]/g,'').toUpperCase().slice(0,13)" />
                        <InputError class="mt-1.5" :message="form.errors.rfc" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Municipio <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input v-model="form.municipio" type="text" maxlength="100"
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors"
                            placeholder="Mérida" />
                        <InputError class="mt-1.5" :message="form.errors.municipio" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Nombre de empresa <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nombre_empresa" type="text" maxlength="200" required
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors"
                            placeholder="Mi Empresa S.A. de C.V." />
                        <InputError class="mt-1.5" :message="form.errors.nombre_empresa" />
                    </div>

                    <!-- Cámara o asociación -->
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                            ¿Pertenece a alguna cámara o asociación? <span class="text-red-500">*</span>
                        </label>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <label v-for="opcion in ['CANIRAC', 'Ninguna']" :key="opcion"
                                class="flex items-center gap-2.5 cursor-pointer px-4 py-3 rounded-xl border transition-all"
                                :class="form.camara_asociacion === opcion
                                    ? 'border-guinda-500 bg-guinda-50 dark:bg-guinda-500/10 dark:border-guinda-500'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-guinda-300 dark:hover:border-guinda-600'">
                                <input type="radio" :value="opcion" v-model="form.camara_asociacion" class="accent-guinda-700" />
                                <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ opcion }}</span>
                            </label>
                        </div>
                        <InputError class="mt-1.5" :message="form.errors.camara_asociacion" />
                    </div>

                    <!-- Nombre del establecimiento (solo si CANIRAC) -->
                    <Transition name="fade">
                        <div v-if="form.camara_asociacion === 'CANIRAC'">
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                                Nombre del restaurante, cafetería o establecimiento <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.nombre_establecimiento" type="text"
                                placeholder="Ej. Restaurante El Mesón de San Juan"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 focus:border-transparent transition-colors text-sm" />
                            <InputError class="mt-1.5" :message="form.errors.nombre_establecimiento" />
                        </div>
                    </Transition>

                    <button type="submit" :disabled="form.processing"
                        class="w-full py-3 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 text-white font-bold rounded-xl transition-colors text-sm shadow-sm">
                        {{ form.processing ? 'Guardando...' : 'Continuar' }}
                    </button>
                </form>
            </div>

            <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-6">
                Programa Gubernamental • Gobierno del Estado de Yucatán
            </p>
        </div>
    </div>
</template>
