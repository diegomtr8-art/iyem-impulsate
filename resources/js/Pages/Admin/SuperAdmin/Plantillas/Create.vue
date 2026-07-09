<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    htmlBase: String,
    placeholders: Object,
});

const form = useForm({
    nombre:            '',
    clave:             '',
    asunto:            '',
    contenido:         props.htmlBase,
    tipo_destinatario: 'ambos',
});

watch(() => form.nombre, (val) => {
    form.clave = val.toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9\s]/g, '')
        .trim().replace(/\s+/g, '_');
});

const guardar = () => form.post(route('admin.plantillas.store'));

const tab = ref('editor');

const previewSrc = computed(() =>
    form.contenido
        ? `data:text/html;charset=utf-8,${encodeURIComponent(form.contenido)}`
        : ''
);

const insertarPlaceholder = (ph) => {
    form.contenido += ph;
};
</script>

<template>
    <AdminLayout title="Nueva Plantilla de Correo">
        <Head title="Nueva Plantilla de Correo" />

        <div class="space-y-6">
            <!-- Back + Header -->
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="route('admin.plantillas.index')"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-guinda-700 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div class="flex-1">
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Nueva plantilla de correo</h1>
                    <p class="text-xs text-gray-400 mt-0.5">Crea una plantilla personalizada para envíos manuales</p>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Editor -->
                <div class="xl:col-span-2 space-y-4">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <!-- Tabs -->
                        <div class="flex gap-1 mb-5 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl w-fit">
                            <button @click="tab = 'editor'"
                                :class="['px-4 py-1.5 text-sm font-medium rounded-lg transition-colors',
                                    tab === 'editor' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400']">
                                Editor
                            </button>
                            <button @click="tab = 'preview'"
                                :class="['px-4 py-1.5 text-sm font-medium rounded-lg transition-colors',
                                    tab === 'preview' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400']">
                                Vista previa
                            </button>
                        </div>

                        <form @submit.prevent="guardar" class="space-y-4">
                            <!-- Nombre -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Nombre de la plantilla <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.nombre" type="text" required
                                    placeholder="Ej. Bienvenida especial"
                                    class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                                <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-500">{{ form.errors.nombre }}</p>
                            </div>

                            <!-- Clave (auto-generada) -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Clave (identificador único)
                                </label>
                                <input v-model="form.clave" type="text" readonly
                                    placeholder="se_genera_automaticamente"
                                    class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-gray-500 dark:text-gray-400 font-mono focus:outline-none" />
                                <p v-if="form.errors.clave" class="mt-1 text-xs text-red-500">{{ form.errors.clave }}</p>
                            </div>

                            <!-- Tipo destinatario -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Tipo de destinatario <span class="text-red-500">*</span>
                                </label>
                                <select v-model="form.tipo_destinatario"
                                    class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors">
                                    <option value="ambos">Ambos</option>
                                    <option value="proveedor">Proveedor</option>
                                    <option value="comprador">Comprador</option>
                                </select>
                                <p v-if="form.errors.tipo_destinatario" class="mt-1 text-xs text-red-500">{{ form.errors.tipo_destinatario }}</p>
                            </div>

                            <!-- Asunto -->
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Asunto del correo <span class="text-red-500">*</span>
                                </label>
                                <input v-model="form.asunto" type="text" required
                                    class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                                <p v-if="form.errors.asunto" class="mt-1 text-xs text-red-500">{{ form.errors.asunto }}</p>
                            </div>

                            <!-- Contenido HTML -->
                            <div v-show="tab === 'editor'">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Contenido HTML</label>
                                <textarea v-model="form.contenido" rows="20"
                                    class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 font-mono transition-colors resize-y"></textarea>
                                <p v-if="form.errors.contenido" class="mt-1 text-xs text-red-500">{{ form.errors.contenido }}</p>
                            </div>

                            <div v-show="tab === 'preview'" class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-white">
                                <iframe :src="previewSrc" class="w-full h-[500px]" sandbox="allow-same-origin"></iframe>
                            </div>

                            <div class="flex justify-end pt-2">
                                <button type="submit" :disabled="form.processing"
                                    class="px-5 py-2 text-sm font-semibold rounded-xl bg-guinda-700 hover:bg-guinda-800 text-white transition-colors disabled:opacity-60">
                                    {{ form.processing ? 'Guardando...' : 'Crear plantilla' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Placeholders -->
                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Variables disponibles</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">
                            Haz clic para insertar al final del contenido.
                        </p>
                        <div class="space-y-2">
                            <button v-for="(desc, ph) in placeholders" :key="ph"
                                type="button"
                                @click="insertarPlaceholder(ph)"
                                class="w-full text-left px-3 py-2.5 rounded-xl bg-gray-50 dark:bg-gray-800 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors group">
                                <div class="text-xs font-mono text-guinda-700 dark:text-guinda-400 group-hover:text-guinda-800">{{ ph }}</div>
                                <div class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ desc }}</div>
                            </button>
                        </div>
                    </div>

                    <div class="bg-sky-50 dark:bg-sky-900/20 border border-sky-200 dark:border-sky-800 rounded-2xl p-4">
                        <p class="text-xs text-sky-800 dark:text-sky-300 font-medium mb-1">Plantilla personalizada</p>
                        <p class="text-xs text-sky-700 dark:text-sky-400">
                            Las plantillas creadas aquí son personalizadas y pueden eliminarse. Solo las plantillas del sistema no se pueden borrar.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
