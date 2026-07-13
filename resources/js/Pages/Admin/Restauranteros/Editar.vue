<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MUNICIPIOS_YUCATAN } from '@/constants/municipiosYucatan';

const props = defineProps({
    restaurantero: Object,
    categorias: Array,
});

const municipios = MUNICIPIOS_YUCATAN;

const form = useForm({
    user_name:                props.restaurantero.user?.name ?? '',
    user_email:                props.restaurantero.user?.email ?? '',
    nombre_restaurante:        props.restaurantero.nombre_restaurante ?? '',
    descripcion:               props.restaurantero.descripcion ?? '',
    telefono:                  props.restaurantero.telefono ?? '',
    direccion:                 props.restaurantero.direccion ?? '',
    municipio:                 props.restaurantero.municipio ?? '',
    rfc:                       props.restaurantero.rfc ?? '',
    sitio_web:                 props.restaurantero.sitio_web ?? '',
    categoria:                 props.restaurantero.categoria ?? '',
    acepta_credito:            props.restaurantero.acepta_credito ?? false,
    credito_monto_maximo:      props.restaurantero.credito_monto_maximo ?? '',
    credito_tiempo_cantidad:   props.restaurantero.credito_tiempo_cantidad ?? '',
    credito_tiempo_unidad:     props.restaurantero.credito_tiempo_unidad ?? '',
    credito_a_negociar:        props.restaurantero.credito_a_negociar ?? false,
    pago_contraentrega:        props.restaurantero.pago_contraentrega ?? false,
    factura:                   props.restaurantero.factura ?? false,
    regimen_fiscal:            props.restaurantero.regimen_fiscal ?? '',
    entrega_domicilio:         props.restaurantero.entrega_domicilio ?? false,
    cobertura_entrega:         props.restaurantero.cobertura_entrega ?? '',
    forma_entrega:             props.restaurantero.forma_entrega ?? '',
});

const submit = () => {
    form.post(route('admin.restauranteros.update', props.restaurantero.id));
};
</script>

<template>
    <AdminLayout title="Editar proveedor">
        <Head :title="`Editar proveedor — ${restaurantero.nombre_restaurante}`" />

        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <Link :href="route('admin.restauranteros.show', restaurantero.id)"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </Link>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Editar proveedor</h1>
            </div>
        </template>

        <div class="max-w-3xl">
            <form @submit.prevent="submit"
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm p-6 space-y-6">

                <!-- Sección: Cuenta de usuario -->
                <div>
                    <h2 class="text-sm font-bold text-guinda-700 dark:text-guinda-400 uppercase tracking-wide mb-3 pb-1 border-b border-guinda-100 dark:border-guinda-900/40">
                        Cuenta de usuario
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label-admin">Nombre completo</label>
                            <input v-model="form.user_name" type="text" class="input-admin" />
                            <p v-if="form.errors.user_name" class="error-admin">{{ form.errors.user_name }}</p>
                        </div>
                        <div>
                            <label class="label-admin">Correo electrónico</label>
                            <input v-model="form.user_email" type="email" class="input-admin" />
                            <p v-if="form.errors.user_email" class="error-admin">{{ form.errors.user_email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Sección: Datos del negocio -->
                <div>
                    <h2 class="text-sm font-bold text-guinda-700 dark:text-guinda-400 uppercase tracking-wide mb-3 pb-1 border-b border-guinda-100 dark:border-guinda-900/40">
                        Datos del negocio
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label-admin">Nombre del negocio</label>
                            <input v-model="form.nombre_restaurante" type="text" class="input-admin" />
                            <p v-if="form.errors.nombre_restaurante" class="error-admin">{{ form.errors.nombre_restaurante }}</p>
                        </div>
                        <div>
                            <label class="label-admin">Teléfono</label>
                            <input v-model="form.telefono" type="text" class="input-admin" />
                        </div>
                        <div>
                            <label class="label-admin">Municipio</label>
                            <select v-model="form.municipio" class="input-admin">
                                <option value="">— Selecciona —</option>
                                <option v-for="m in municipios" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label-admin">Dirección</label>
                            <input v-model="form.direccion" type="text" class="input-admin" />
                        </div>
                        <div>
                            <label class="label-admin">RFC</label>
                            <input v-model="form.rfc" type="text" class="input-admin" maxlength="13" />
                        </div>
                        <div>
                            <label class="label-admin">Sitio web</label>
                            <input v-model="form.sitio_web" type="url" class="input-admin" placeholder="https://" />
                        </div>
                        <div>
                            <label class="label-admin">Categoría</label>
                            <select v-model="form.categoria" class="input-admin">
                                <option value="">Sin categoría</option>
                                <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="label-admin">Descripción del negocio</label>
                        <textarea v-model="form.descripcion" rows="3" class="input-admin"></textarea>
                    </div>
                </div>

                <!-- Sección: Crédito -->
                <div>
                    <h2 class="text-sm font-bold text-guinda-700 dark:text-guinda-400 uppercase tracking-wide mb-3 pb-1 border-b border-guinda-100 dark:border-guinda-900/40">
                        Crédito
                    </h2>
                    <label class="flex items-center gap-2 cursor-pointer mb-4">
                        <input v-model="form.acepta_credito" type="checkbox" class="w-4 h-4 accent-guinda-700" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Acepta crédito</span>
                    </label>
                    <div v-if="form.acepta_credito" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="label-admin">Monto máximo</label>
                            <input v-model="form.credito_monto_maximo" type="number" min="0" step="0.01" class="input-admin" />
                        </div>
                        <div>
                            <label class="label-admin">Plazo</label>
                            <input v-model="form.credito_tiempo_cantidad" type="number" min="1" class="input-admin" />
                        </div>
                        <div>
                            <label class="label-admin">Unidad</label>
                            <select v-model="form.credito_tiempo_unidad" class="input-admin">
                                <option value="">—</option>
                                <option value="dias">Días</option>
                                <option value="semanas">Semanas</option>
                                <option value="meses">Meses</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer mt-4">
                        <input v-model="form.credito_a_negociar" type="checkbox" class="w-4 h-4 accent-guinda-700" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Crédito a negociar</span>
                    </label>
                </div>

                <!-- Sección: Logística y condiciones -->
                <div>
                    <h2 class="text-sm font-bold text-guinda-700 dark:text-guinda-400 uppercase tracking-wide mb-3 pb-1 border-b border-guinda-100 dark:border-guinda-900/40">
                        Logística y condiciones
                    </h2>
                    <div class="flex flex-wrap gap-6 mb-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.pago_contraentrega" type="checkbox" class="w-4 h-4 accent-guinda-700" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Pago contraentrega</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.factura" type="checkbox" class="w-4 h-4 accent-guinda-700" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Acepta factura</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="form.entrega_domicilio" type="checkbox" class="w-4 h-4 accent-guinda-700" />
                            <span class="text-sm text-gray-700 dark:text-gray-300">Entrega a domicilio</span>
                        </label>
                    </div>
                    <div v-if="form.factura" class="mb-4">
                        <label class="label-admin">Régimen fiscal</label>
                        <input v-model="form.regimen_fiscal" type="text" class="input-admin max-w-sm" />
                    </div>
                    <div v-if="form.entrega_domicilio" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label-admin">Cobertura</label>
                            <select v-model="form.cobertura_entrega" class="input-admin">
                                <option value="">—</option>
                                <option value="local">Local</option>
                                <option value="regional">Regional</option>
                                <option value="nacional">Nacional</option>
                            </select>
                        </div>
                        <div>
                            <label class="label-admin">Forma de entrega</label>
                            <select v-model="form.forma_entrega" class="input-admin">
                                <option value="">—</option>
                                <option value="programada">Programada</option>
                                <option value="flexible">Flexible</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <Link :href="route('admin.restauranteros.show', restaurantero.id)"
                        class="px-4 py-2 text-sm font-semibold text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2 bg-guinda-800 hover:bg-guinda-700 text-white font-bold text-sm rounded-xl transition-colors disabled:opacity-60">
                        {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<style scoped>
.label-admin {
    @apply block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1;
}
.input-admin {
    @apply w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2
           text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white
           focus:outline-none focus:ring-2 focus:ring-guinda-500 transition;
}
.error-admin {
    @apply text-xs text-red-600 mt-1;
}
</style>
