<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    plantilla: Object,
    placeholders: Object,
});

const form = useForm({
    asunto:    props.plantilla.asunto,
    contenido: props.plantilla.contenido,
});

const guardar = () => form.put(route('admin.plantillas.update', props.plantilla.id));

// Modal de envío de prueba
const modalEnvio = ref(false);
const emailPrueba = ref('');
const enviando = ref(false);

const enviarPrueba = () => {
    if (!emailPrueba.value) return;
    enviando.value = true;
    router.post(route('admin.plantillas.enviar', props.plantilla.id),
        { destinatarios: [emailPrueba.value] },
        { onFinish: () => { enviando.value = false; modalEnvio.value = false; emailPrueba.value = ''; } }
    );
};

const insertarPlaceholder = (ph) => {
    form.contenido += ph;
};

// Vista previa rápida (renderiza HTML directamente en iframe)
const previewSrc = computed(() =>
    `data:text/html;charset=utf-8,${encodeURIComponent(form.contenido)}`
);

const tab = ref('editor');
</script>

<template>
    <AdminLayout :title="`Editar — ${plantilla.nombre}`">
        <Head :title="`Plantilla: ${plantilla.nombre}`" />

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
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ plantilla.nombre }}</h1>
                    <p class="text-xs text-gray-400 font-mono mt-0.5">clave: {{ plantilla.clave }}</p>
                </div>
                <button @click="modalEnvio = true"
                    class="px-4 py-2 text-sm font-semibold rounded-xl border border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                    Enviar prueba
                </button>
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
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Asunto del correo</label>
                                <input v-model="form.asunto" type="text"
                                    class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                                <p v-if="form.errors.asunto" class="mt-1 text-xs text-red-500">{{ form.errors.asunto }}</p>
                            </div>

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
                                    Guardar plantilla
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

                    <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-2xl p-4">
                        <p class="text-xs text-amber-800 dark:text-amber-300 font-medium mb-1">Aviso</p>
                        <p class="text-xs text-amber-700 dark:text-amber-400">
                            Si desactivas esta plantilla, el sistema usará el contenido predeterminado (hardcodeado) del correo correspondiente.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal envío prueba -->
        <Teleport to="body">
            <div v-if="modalEnvio"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="modalEnvio = false">
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl p-6 w-full max-w-sm">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Enviar correo de prueba</h3>
                    <input v-model="emailPrueba" type="email" placeholder="correo@ejemplo.com"
                        class="w-full px-3 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 mb-4" />
                    <div class="flex gap-3 justify-end">
                        <button @click="modalEnvio = false"
                            class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 transition-colors">
                            Cancelar
                        </button>
                        <button @click="enviarPrueba" :disabled="!emailPrueba || enviando"
                            class="px-5 py-2 text-sm font-semibold rounded-xl bg-guinda-700 hover:bg-guinda-800 text-white disabled:opacity-60 transition-colors">
                            {{ enviando ? 'Enviando…' : 'Enviar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
