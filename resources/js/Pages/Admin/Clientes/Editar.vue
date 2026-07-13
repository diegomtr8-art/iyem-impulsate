<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MUNICIPIOS_YUCATAN } from '@/constants/municipiosYucatan';

const props = defineProps({
    cliente: Object,
});

const municipios = MUNICIPIOS_YUCATAN;

const form = useForm({
    name:                    props.cliente.name ?? '',
    email:                   props.cliente.email ?? '',
    telefono:                props.cliente.telefono ?? '',
    rfc:                     props.cliente.rfc ?? '',
    municipio:               props.cliente.municipio ?? '',
    nombre_empresa:          props.cliente.nombre_empresa ?? '',
    sitio_web:               props.cliente.sitio_web ?? '',
    camara_asociacion:       props.cliente.camara_asociacion ?? '',
    nombre_establecimiento:  props.cliente.nombre_establecimiento ?? '',
});

const submit = () => {
    form.put(route('admin.clientes.actualizar', props.cliente.id));
};
</script>

<template>
    <AdminLayout title="Editar comprador">
        <Head :title="`Editar comprador — ${cliente.name}`" />

        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                <Link :href="route('admin.clientes.show', cliente.id)"
                    class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Volver
                </Link>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">Editar comprador</h1>
            </div>
        </template>

        <div class="max-w-2xl">
            <form @submit.prevent="submit"
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl shadow-sm p-6 space-y-6">

                <div>
                    <h2 class="text-sm font-bold text-guinda-700 dark:text-guinda-400 uppercase tracking-wide mb-3 pb-1 border-b border-guinda-100 dark:border-guinda-900/40">
                        Cuenta de usuario
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label-admin">Nombre completo</label>
                            <input v-model="form.name" type="text" class="input-admin" />
                            <p v-if="form.errors.name" class="error-admin">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="label-admin">Correo electrónico</label>
                            <input v-model="form.email" type="email" class="input-admin" />
                            <p v-if="form.errors.email" class="error-admin">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="label-admin">Teléfono</label>
                            <input v-model="form.telefono" type="text" class="input-admin" />
                        </div>
                        <div>
                            <label class="label-admin">RFC</label>
                            <input v-model="form.rfc" type="text" class="input-admin" maxlength="13" />
                        </div>
                    </div>
                </div>

                <div>
                    <h2 class="text-sm font-bold text-guinda-700 dark:text-guinda-400 uppercase tracking-wide mb-3 pb-1 border-b border-guinda-100 dark:border-guinda-900/40">
                        Datos de la empresa
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label-admin">Nombre de la empresa</label>
                            <input v-model="form.nombre_empresa" type="text" class="input-admin" />
                        </div>
                        <div>
                            <label class="label-admin">Nombre del establecimiento</label>
                            <input v-model="form.nombre_establecimiento" type="text" class="input-admin" />
                        </div>
                        <div>
                            <label class="label-admin">Municipio</label>
                            <select v-model="form.municipio" class="input-admin">
                                <option value="">— Selecciona —</option>
                                <option v-for="m in municipios" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label-admin">Cámara / Asociación</label>
                            <input v-model="form.camara_asociacion" type="text" class="input-admin" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label-admin">Sitio web</label>
                            <input v-model="form.sitio_web" type="url" class="input-admin" placeholder="https://" />
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <Link :href="route('admin.clientes.show', cliente.id)"
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
