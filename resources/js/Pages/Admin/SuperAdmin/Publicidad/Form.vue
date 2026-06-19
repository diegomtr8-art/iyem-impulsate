<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    publicidad: { type: Object, default: null },
});

const esEdicion = computed(() => !!props.publicidad);

const form = useForm({
    titulo:                props.publicidad?.titulo ?? '',
    imagen:                null,
    enlace:                props.publicidad?.enlace ?? '',
    abre_en_nueva_pestana: props.publicidad?.abre_en_nueva_pestana ?? true,
    fecha_inicio:          props.publicidad?.fecha_inicio ?? '',
    fecha_fin:             props.publicidad?.fecha_fin ?? '',
    activa:                props.publicidad?.activa ?? true,
});

const previewUrl = ref(props.publicidad?.imagen_url ?? null);

const onImagenChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    form.imagen = file;
    previewUrl.value = URL.createObjectURL(file);
};

const guardar = () => {
    if (esEdicion.value) {
        form.post(route('admin.publicidad.update', props.publicidad.id), {
            forceFormData: true,
        });
    } else {
        form.post(route('admin.publicidad.store'), {
            forceFormData: true,
        });
    }
};
</script>

<template>
    <AdminLayout :title="esEdicion ? 'Editar Anuncio' : 'Nuevo Anuncio'">
        <Head :title="esEdicion ? 'Editar Anuncio' : 'Nuevo Anuncio'" />

        <div class="space-y-6 max-w-2xl">
            <!-- Back -->
            <div class="flex items-center gap-3">
                <Link :href="route('admin.publicidad.index')"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-guinda-700 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                    {{ esEdicion ? 'Editar anuncio' : 'Nuevo anuncio' }}
                </h1>
            </div>

            <form @submit.prevent="guardar"
                class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 space-y-5">

                <!-- Título -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Título del anuncio</label>
                    <input v-model="form.titulo" type="text" required
                        class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                    <p v-if="form.errors.titulo" class="mt-1 text-xs text-red-500">{{ form.errors.titulo }}</p>
                </div>

                <!-- Imagen -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-2">Imagen del anuncio</label>
                    <div class="flex items-start gap-4">
                        <div v-if="previewUrl"
                            class="w-40 h-24 rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shrink-0 bg-gray-50">
                            <img :src="previewUrl" class="w-full h-full object-cover" alt="Preview" />
                        </div>
                        <label class="flex-1 flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl p-5 cursor-pointer hover:border-guinda-400 dark:hover:border-guinda-600 transition-colors">
                            <svg class="w-6 h-6 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            <span class="text-xs text-gray-400">{{ form.imagen ? form.imagen.name : (esEdicion ? 'Cambiar imagen' : 'Seleccionar imagen') }}</span>
                            <input type="file" accept="image/*" class="hidden" @change="onImagenChange" />
                        </label>
                    </div>
                    <p v-if="form.errors.imagen" class="mt-1 text-xs text-red-500">{{ form.errors.imagen }}</p>
                </div>

                <!-- Enlace -->
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Enlace al hacer clic (opcional)</label>
                    <input v-model="form.enlace" type="url" placeholder="https://..."
                        class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                    <p v-if="form.errors.enlace" class="mt-1 text-xs text-red-500">{{ form.errors.enlace }}</p>
                </div>

                <!-- Abrir en nueva pestaña -->
                <label class="flex items-center gap-3 cursor-pointer">
                    <button type="button" @click="form.abre_en_nueva_pestana = !form.abre_en_nueva_pestana"
                        :class="['relative inline-flex h-5 w-9 rounded-full transition-colors',
                            form.abre_en_nueva_pestana ? 'bg-guinda-600' : 'bg-gray-300 dark:bg-gray-600']">
                        <span :class="['inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform mt-0.5',
                            form.abre_en_nueva_pestana ? 'translate-x-4' : 'translate-x-0.5']"></span>
                    </button>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Abrir enlace en nueva pestaña</span>
                </label>

                <!-- Fechas -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha y hora de inicio</label>
                        <input v-model="form.fecha_inicio" type="datetime-local" required
                            class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        <p v-if="form.errors.fecha_inicio" class="mt-1 text-xs text-red-500">{{ form.errors.fecha_inicio }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha y hora de fin</label>
                        <input v-model="form.fecha_fin" type="datetime-local" required
                            class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        <p v-if="form.errors.fecha_fin" class="mt-1 text-xs text-red-500">{{ form.errors.fecha_fin }}</p>
                    </div>
                </div>

                <!-- Activa -->
                <label class="flex items-center gap-3 cursor-pointer">
                    <button type="button" @click="form.activa = !form.activa"
                        :class="['relative inline-flex h-5 w-9 rounded-full transition-colors',
                            form.activa ? 'bg-guinda-600' : 'bg-gray-300 dark:bg-gray-600']">
                        <span :class="['inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform mt-0.5',
                            form.activa ? 'translate-x-4' : 'translate-x-0.5']"></span>
                    </button>
                    <span class="text-sm text-gray-700 dark:text-gray-300">Activar anuncio</span>
                </label>

                <div class="flex justify-end pt-2 border-t border-gray-100 dark:border-gray-800">
                    <button type="submit" :disabled="form.processing"
                        class="px-6 py-2.5 text-sm font-semibold rounded-xl bg-guinda-700 hover:bg-guinda-800 text-white transition-colors disabled:opacity-60">
                        {{ esEdicion ? 'Guardar cambios' : 'Crear anuncio' }}
                    </button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
