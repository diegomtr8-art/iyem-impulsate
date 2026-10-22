<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { usePlantillaEditor } from './usePlantillaEditor';

const props = defineProps({
    plantilla: Object,
    grupos: Object,
    // Variables que rellena el flujo de esta plantilla de sistema
    placeholders: Object,
});

const form = useForm({
    asunto:    props.plantilla.asunto,
    contenido: props.plantilla.contenido,
});

const guardar = () => form.put(route('admin.plantillas.update', props.plantilla.id));

const tab = ref('editor');

const {
    previewHtml, previewAsunto, cargandoPreview, errorPreview, generarPreview,
    emailPrueba, enviandoPrueba, avisoPrueba, avisoPruebaOk, enviarPrueba,
    textareaContenido, insertarPlaceholder,
    variablesDesconocidas,
} = usePlantillaEditor(form, tab, () => [
    ...Object.keys(props.placeholders || {}),
    ...Object.values(props.grupos || {}).flatMap((g) => Object.keys(g)),
]);
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
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Editor -->
                <div class="xl:col-span-2 space-y-4">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <!-- Tabs -->
                        <div class="flex gap-1 mb-5 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl w-fit">
                            <button type="button" @click="tab = 'editor'"
                                :class="['px-4 py-1.5 text-sm font-medium rounded-lg transition-colors',
                                    tab === 'editor' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400']">
                                Editor
                            </button>
                            <button type="button" @click="tab = 'preview'"
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
                                <textarea ref="textareaContenido" v-model="form.contenido" rows="20"
                                    class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 font-mono transition-colors resize-y"></textarea>
                                <p v-if="form.errors.contenido" class="mt-1 text-xs text-red-500">{{ form.errors.contenido }}</p>
                            </div>

                            <div v-show="tab === 'preview'" class="space-y-3">
                                <div class="flex items-center justify-between gap-3 flex-wrap">
                                    <div class="min-w-0">
                                        <p class="text-[11px] uppercase tracking-wide text-gray-400 font-semibold">Asunto</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ previewAsunto || '—' }}</p>
                                    </div>
                                    <button type="button" @click="generarPreview" :disabled="cargandoPreview"
                                        class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50">
                                        {{ cargandoPreview ? 'Generando...' : 'Actualizar vista previa' }}
                                    </button>
                                </div>

                                <p v-if="errorPreview" class="text-xs text-red-600 dark:text-red-400">{{ errorPreview }}</p>

                                <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-white">
                                    <iframe :srcdoc="previewHtml" class="w-full h-[500px]" sandbox=""></iframe>
                                </div>

                                <p class="text-[11px] text-gray-400">
                                    Las variables se muestran con datos de ejemplo. Al enviarse de verdad
                                    se sustituyen por los datos de cada destinatario.
                                </p>
                            </div>

                            <div v-if="variablesDesconocidas.length"
                                class="p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                                <p class="text-xs font-semibold text-amber-800 dark:text-amber-300 mb-1">Variables no reconocidas</p>
                                <p class="text-xs text-amber-700 dark:text-amber-400">
                                    No están en el catálogo y saldrán vacías en el correo:
                                    <span class="font-mono">{{ variablesDesconocidas.join(', ') }}</span>
                                </p>
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
                            Haz clic para insertarla donde está el cursor.
                        </p>
                        <div v-if="placeholders && Object.keys(placeholders).length" class="mb-5 p-3 rounded-xl bg-guinda-50/60 dark:bg-guinda-900/10 border border-guinda-100 dark:border-guinda-900/40">
                            <p class="text-[11px] uppercase tracking-wide text-guinda-700 dark:text-guinda-400 font-semibold mb-1">Las que rellena este correo</p>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400 mb-2">El sistema las sustituye automáticamente al enviarlo.</p>
                            <div class="space-y-1.5">
                                <button v-for="(desc, ph) in placeholders" :key="ph" type="button"
                                    @click="insertarPlaceholder(ph)"
                                    class="w-full text-left px-3 py-2 rounded-lg bg-white dark:bg-gray-800 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors group">
                                    <div class="text-xs font-mono text-guinda-700 dark:text-guinda-400 group-hover:text-guinda-800">{{ ph }}</div>
                                    <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ desc }}</div>
                                </button>
                            </div>
                        </div>
                        <div v-for="(vars, grupo) in grupos" :key="grupo" class="mb-4 last:mb-0">
                            <p class="text-[11px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">{{ grupo }}</p>
                            <div class="space-y-1.5">
                                <button v-for="(desc, ph) in vars" :key="ph" type="button"
                                    @click="insertarPlaceholder(ph)"
                                    class="w-full text-left px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors group">
                                    <div class="text-xs font-mono text-guinda-700 dark:text-guinda-400 group-hover:text-guinda-800">{{ ph }}</div>
                                    <div class="text-[11px] text-gray-400 dark:text-gray-500 mt-0.5">{{ desc }}</div>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Enviar prueba</h3>
                        <p class="text-xs text-gray-400 mb-3">
                            Envía lo que tienes en el editor (aunque no lo hayas guardado) con datos de ejemplo.
                        </p>
                        <input v-model="emailPrueba" type="email" placeholder="tucorreo@ejemplo.com"
                            class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 mb-2" />
                        <button type="button" @click="enviarPrueba" :disabled="enviandoPrueba || !emailPrueba"
                            class="w-full px-3 py-2 text-xs font-semibold rounded-xl border border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-50 dark:hover:bg-guinda-950/20 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            {{ enviandoPrueba ? 'Enviando...' : 'Enviar correo de prueba' }}
                        </button>
                        <p v-if="avisoPrueba" class="mt-2 text-xs" :class="avisoPruebaOk ? 'text-emerald-600' : 'text-red-600'">
                            {{ avisoPrueba }}
                        </p>
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

    </AdminLayout>
</template>
