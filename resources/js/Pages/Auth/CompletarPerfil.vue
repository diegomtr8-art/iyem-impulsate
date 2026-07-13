<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ user: Object, municipios: Array });

const paso = ref(1);

const form = useForm({
    telefono:               props.user?.telefono ?? '',
    curp:                   props.user?.curp ?? '',
    rfc:                    props.user?.rfc ?? '',
    municipio:              props.user?.municipio ?? '',
    nombre_empresa:         props.user?.nombre_empresa ?? '',
    camara_asociacion:      props.user?.camara_asociacion ?? '',
    nombre_establecimiento: props.user?.nombre_establecimiento ?? '',
    necesidades:            props.user?.necesidades ?? '',
    es_restaurantero:       props.user?.es_restaurantero ?? false,
});

const puedeAvanzar = computed(() => !!(form.telefono));

const puedeEnviar = computed(() => !!(
    form.nombre_empresa &&
    form.municipio &&
    form.camara_asociacion &&
    (form.camara_asociacion !== 'CANIRAC' || form.nombre_establecimiento) &&
    form.necesidades
));

const submit = () => {
    form.post(route('perfil.completar.store'));
};
</script>

<template>
    <Head title="Completa tu perfil — Impulsate" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex items-center justify-center px-4 py-10 transition-colors">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-12 w-auto mx-auto mb-4" />
                <h1 class="text-2xl font-black text-gray-900 dark:text-white">¡Completa tu perfil!</h1>
                <p class="text-gray-500 dark:text-gray-400 text-sm mt-2">
                    Necesitamos algunos datos adicionales para continuar.
                </p>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 shadow-sm">
                <!-- Barra de progreso -->
                <div class="flex gap-0 mb-6 rounded-xl overflow-hidden">
                    <div class="flex-1 py-2 text-center text-xs font-bold transition-colors"
                        :class="paso >= 1 ? 'bg-guinda-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500'">
                        1. Datos personales
                    </div>
                    <div class="flex-1 py-2 text-center text-xs font-bold transition-colors"
                        :class="paso >= 2 ? 'bg-guinda-800 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-400 dark:text-gray-500'">
                        2. Tu empresa
                    </div>
                </div>

                <!-- PASO 1: Datos personales -->
                <div v-if="paso === 1" class="space-y-5">
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

                    <button type="button" @click="paso = 2" :disabled="!puedeAvanzar"
                        class="w-full py-3 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 text-white font-bold rounded-xl transition-colors text-sm shadow-sm">
                        Siguiente →
                    </button>
                </div>

                <!-- PASO 2: Empresa y necesidades -->
                <form v-if="paso === 2" @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Nombre de empresa <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nombre_empresa" type="text" maxlength="200" required
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors"
                            placeholder="Mi Empresa S.A. de C.V." />
                        <InputError class="mt-1.5" :message="form.errors.nombre_empresa" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Municipio <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.municipio" required
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors">
                            <option value="">-- Selecciona tu municipio --</option>
                            <option v-for="m in municipios" :key="m" :value="m">{{ m }}</option>
                        </select>
                        <InputError class="mt-1.5" :message="form.errors.municipio" />
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

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            ¿Qué necesidades tienes? <span class="text-red-500">*</span>
                        </label>
                        <textarea v-model="form.necesidades" rows="3" required
                            placeholder="Busco proveedores de salsas, embutidos, productos orgánicos..."
                            class="w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 transition-colors"></textarea>
                        <InputError class="mt-1.5" :message="form.errors.necesidades" />
                    </div>

                    <!-- es_restaurantero checkbox -->
                    <div class="border-2 border-guinda-500 rounded-2xl p-4 bg-guinda-50 dark:bg-guinda-500/10">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox" v-model="form.es_restaurantero"
                                class="accent-guinda-700 w-5 h-5 mt-0.5 flex-shrink-0" />
                            <div>
                                <p class="text-sm font-bold text-guinda-800 dark:text-guinda-300">
                                    ¿Eres restaurantero?
                                </p>
                                <p class="text-xs text-guinda-700 dark:text-guinda-400 mt-1 leading-relaxed">
                                    Si eres dueño o encargado de un restaurante, cafetería, hotel u otro
                                    establecimiento de alimentos, marca esta opción para poder participar
                                    en el evento y agendar citas con proveedores.
                                </p>
                                <Transition name="fade">
                                    <p v-if="form.es_restaurantero"
                                        class="mt-2 text-xs font-semibold text-green-700 dark:text-green-400">
                                        ✅ Quedarás registrado automáticamente en el evento.
                                    </p>
                                </Transition>
                            </div>
                        </label>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" @click="paso = 1"
                            class="flex-1 py-3 border border-guinda-500 text-guinda-700 dark:text-guinda-400 font-bold rounded-xl transition-colors text-sm">
                            ← Anterior
                        </button>
                        <button type="submit" :disabled="!puedeEnviar || form.processing"
                            class="flex-[2] py-3 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 text-white font-bold rounded-xl transition-colors text-sm shadow-sm">
                            {{ form.processing ? 'Guardando...' : 'Finalizar y acceder' }}
                        </button>
                    </div>
                </form>
            </div>

            <p class="text-center text-xs text-gray-400 dark:text-gray-600 mt-6">
                Programa Gubernamental • Gobierno del Estado de Yucatán
            </p>
        </div>
    </div>
</template>
