<script setup>
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { usePlantillaEditor, VISTAS, BLOQUES, filtrarGrupos } from './usePlantillaEditor';
import TarjetaPrueba from './TarjetaPrueba.vue';

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

const guardar = () => form.put(route('admin.plantillas.update', props.plantilla.id), {
    preserveScroll: true,
    onSuccess: () => form.defaults(),
});

// 'editor' | 'dividido' | 'preview'
const tab = ref('editor');

const {
    previewHtml, previewAsunto, cargandoPreview, errorPreview, generarPreview,
    emailPrueba, enviandoPrueba, avisoPrueba, avisoPruebaOk, enviarPrueba, usuarioPrueba,
    textareaContenido, inputAsunto, campoActivo, insertarPlaceholder, insertarBloque,
    variablesDesconocidas,
} = usePlantillaEditor(form, tab, () => [
    ...Object.keys(props.placeholders || {}),
    ...Object.values(props.grupos || {}).flatMap((g) => Object.keys(g)),
]);

const vistas = VISTAS;
const bloques = BLOQUES;
const menuBloques = ref(false);
const elegirBloque = (b) => { insertarBloque(b.html); menuBloques.value = false; };

const destinatarios = {
    ambos:     'Proveedores y compradores',
    proveedor: 'Proveedores',
    comprador: 'Compradores',
};

// Vista previa en escritorio o celular
const dispositivo = ref('escritorio');

// Buscador del catalogo de variables (incluye las propias de este correo)
const busqueda = ref('');
const propiasFiltradas = computed(() => filtrarGrupos({ propias: props.placeholders || {} }, busqueda.value).propias || {});
const gruposFiltrados = computed(() => filtrarGrupos(props.grupos, busqueda.value));
const sinCoincidencias = computed(() => !Object.keys(propiasFiltradas.value).length && !Object.keys(gruposFiltrados.value).length);

const lineas = computed(() => (form.contenido || '').split('\n').length);
</script>

<template>
    <AdminLayout :title="`Editar — ${plantilla.nombre}`">
        <Head :title="`Plantilla: ${plantilla.nombre}`" />

        <form @submit.prevent="guardar" class="space-y-6">
            <!-- Encabezado -->
            <div class="flex flex-wrap items-center gap-3">
                <Link :href="route('admin.plantillas.index')"
                    class="p-2 rounded-xl text-gray-400 hover:text-guinda-700 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white truncate">{{ plantilla.nombre }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Edita el correo, revísalo con datos de ejemplo y pruébalo antes de guardar.</p>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="form.isDirty" class="hidden sm:inline text-xs font-medium text-amber-600 dark:text-amber-400">Cambios sin guardar</span>
                    <Link :href="route('admin.plantillas.index')"
                        class="px-4 py-2 text-sm font-medium rounded-xl text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        Volver
                    </Link>
                    <button type="submit" :disabled="form.processing || !form.isDirty"
                        class="px-5 py-2 text-sm font-semibold rounded-xl bg-guinda-700 hover:bg-guinda-800 text-white shadow-sm transition-colors disabled:opacity-60">
                        {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
                <div class="xl:col-span-2 space-y-6">
                    <!-- 1. Datos generales (solo lectura) -->
                    <section class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <span class="w-7 h-7 rounded-full bg-guinda-700 text-white text-xs font-bold flex items-center justify-center">1</span>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Datos de la plantilla</h2>
                                <p class="text-xs text-gray-400">Se definen al crearla y no se editan aquí</p>
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Clave</dt>
                                <dd><code class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-xs text-gray-600 dark:text-gray-300 font-mono break-all">{{ plantilla.clave }}</code></dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Destinatarios</dt>
                                <dd class="text-sm font-semibold text-gray-900 dark:text-white">{{ destinatarios[plantilla.tipo_destinatario] || 'Todos' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tipo</dt>
                                <dd>
                                    <span :class="['inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold',
                                        plantilla.es_sistema ? 'bg-guinda-50 text-guinda-700 dark:bg-guinda-900/20 dark:text-guinda-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300']">
                                        {{ plantilla.es_sistema ? 'Del sistema' : 'Personalizada' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Estado</dt>
                                <dd>
                                    <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold',
                                        plantilla.activo ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400']">
                                        <span :class="['w-1.5 h-1.5 rounded-full', plantilla.activo ? 'bg-emerald-500' : 'bg-gray-400']"></span>
                                        {{ plantilla.activo ? 'Activa' : 'Inactiva' }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </section>

                    <!-- 2. Asunto -->
                    <section class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <span class="w-7 h-7 rounded-full bg-guinda-700 text-white text-xs font-bold flex items-center justify-center">2</span>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Asunto</h2>
                                <p class="text-xs text-gray-400">Lo primero que se ve en la bandeja de entrada. Admite variables.</p>
                            </div>
                        </div>

                        <div class="relative">
                            <input ref="inputAsunto" v-model="form.asunto" type="text" required maxlength="255"
                                @focus="campoActivo = 'asunto'"
                                class="w-full pl-4 pr-16 py-2.5 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 focus:border-guinda-400 transition-colors" />
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] tabular-nums"
                                :class="form.asunto.length > 80 ? 'text-amber-500' : 'text-gray-400'">
                                {{ form.asunto.length }}/255
                            </span>
                        </div>
                        <p v-if="form.asunto.length > 80" class="mt-1 text-[11px] text-amber-600 dark:text-amber-400">
                            Los asuntos de más de 80 caracteres suelen cortarse en el celular.
                        </p>
                        <p v-if="form.errors.asunto" class="mt-1 text-xs text-red-500">{{ form.errors.asunto }}</p>

                        <!-- Así se ve en la bandeja -->
                        <div class="mt-4 flex items-center gap-3 p-3 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-100 dark:border-gray-800">
                            <span class="w-9 h-9 shrink-0 rounded-full bg-guinda-700 text-white text-sm font-bold flex items-center justify-center">I</span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">Impúlsate</p>
                                    <span class="text-[11px] text-gray-400 shrink-0">ahora</span>
                                </div>
                                <p class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ form.asunto || 'Tu asunto aparecerá aquí' }}</p>
                            </div>
                        </div>
                    </section>

                    <!-- 3. Contenido -->
                    <section class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex flex-wrap items-center gap-3 mb-5">
                            <span class="w-7 h-7 rounded-full bg-guinda-700 text-white text-xs font-bold flex items-center justify-center">3</span>
                            <div class="flex-1 min-w-0">
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Contenido</h2>
                                <p class="text-xs text-gray-400">HTML del cuerpo del correo.</p>
                            </div>
                            <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl">
                                <button v-for="v in vistas" :key="v.valor" type="button" @click="tab = v.valor"
                                    :class="['px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors',
                                        v.valor === 'dividido' ? 'hidden lg:block' : '',
                                        tab === v.valor ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400']">
                                    {{ v.etiqueta }}
                                </button>
                            </div>
                        </div>

                        <div :class="tab === 'dividido' ? 'grid grid-cols-1 lg:grid-cols-2 gap-4' : ''">
                            <!-- Editor -->
                            <div v-show="tab !== 'preview'" class="rounded-xl overflow-hidden border border-gray-800 bg-gray-950">
                                <div class="flex items-center justify-between gap-2 px-3 py-2 border-b border-gray-800 bg-gray-900">
                                    <div class="relative">
                                        <button type="button" @click="menuBloques = !menuBloques"
                                            class="flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg text-gray-200 bg-gray-800 hover:bg-gray-700 transition-colors">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                            </svg>
                                            Insertar bloque
                                        </button>
                                        <div v-if="menuBloques" class="fixed inset-0 z-10" @click="menuBloques = false"></div>
                                        <div v-if="menuBloques"
                                            class="absolute left-0 top-full mt-1 z-20 w-52 py-1 rounded-xl bg-white dark:bg-gray-800 shadow-lg border border-gray-100 dark:border-gray-700">
                                            <button v-for="b in bloques" :key="b.nombre" type="button" @click="elegirBloque(b)"
                                                class="w-full text-left px-3 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-guinda-50 dark:hover:bg-guinda-900/20">
                                                {{ b.nombre }}
                                            </button>
                                        </div>
                                    </div>
                                    <span class="text-[11px] text-gray-500 tabular-nums">{{ lineas }} líneas · {{ form.contenido.length }} caracteres</span>
                                </div>
                                <textarea ref="textareaContenido" v-model="form.contenido" rows="22" spellcheck="false"
                                    @focus="campoActivo = 'contenido'"
                                    class="block w-full px-4 py-3 text-[13px] leading-relaxed font-mono bg-gray-950 text-gray-100 border-0 focus:ring-0 resize-y placeholder-gray-600"></textarea>
                            </div>

                            <!-- Vista previa -->
                            <div v-show="tab !== 'editor'" class="space-y-3">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-lg">
                                        <button type="button" @click="dispositivo = 'escritorio'" title="Escritorio"
                                            :class="['p-1.5 rounded-md', dispositivo === 'escritorio' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-400']">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                        </button>
                                        <button type="button" @click="dispositivo = 'celular'" title="Celular"
                                            :class="['p-1.5 rounded-md', dispositivo === 'celular' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-400']">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>
                                        </button>
                                    </div>
                                    <button type="button" @click="generarPreview" :disabled="cargandoPreview"
                                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 disabled:opacity-50">
                                        <svg :class="['w-3.5 h-3.5', cargandoPreview && 'animate-spin']" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                        {{ cargandoPreview ? 'Generando...' : 'Actualizar' }}
                                    </button>
                                </div>

                                <p v-if="errorPreview" class="text-xs text-red-600 dark:text-red-400">{{ errorPreview }}</p>

                                <!-- Marco tipo cliente de correo -->
                                <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800">
                                    <div class="px-4 py-3 bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 space-y-0.5">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-white truncate">{{ previewAsunto || form.asunto || '(sin asunto)' }}</p>
                                        <p class="text-[11px] text-gray-400 truncate">De: Impúlsate · Para: {{ usuarioPrueba ? usuarioPrueba.email : 'maria.perez@ejemplo.com' }}</p>
                                    </div>
                                    <div class="flex justify-center p-3">
                                        <iframe :srcdoc="previewHtml" sandbox=""
                                            :class="['bg-white rounded-lg shadow-sm transition-all duration-300 h-[560px]',
                                                dispositivo === 'celular' ? 'w-[375px] max-w-full' : 'w-full']"></iframe>
                                    </div>
                                </div>
                                <p class="text-[11px] text-gray-400">
                                    {{ usuarioPrueba ? `Variables con los datos de ${usuarioPrueba.name}.` : 'Variables con datos de ejemplo; al enviarse se usan los de cada destinatario.' }}
                                </p>
                            </div>
                        </div>

                        <p v-if="form.errors.contenido" class="mt-2 text-xs text-red-500">{{ form.errors.contenido }}</p>

                        <div v-if="variablesDesconocidas.length"
                            class="mt-4 flex gap-3 p-3 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                            <svg class="w-5 h-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                            <div>
                                <p class="text-xs font-semibold text-amber-800 dark:text-amber-300">Variables no reconocidas</p>
                                <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                                    No están en el catálogo y saldrán vacías:
                                    <span class="font-mono">{{ variablesDesconocidas.join(', ') }}</span>
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Columna lateral -->
                <aside class="space-y-4 xl:sticky xl:top-6">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-5">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Variables</h3>
                        <p class="text-xs text-gray-400 mt-0.5 mb-3">
                            Clic para insertar en
                            <span class="font-semibold text-guinda-700 dark:text-guinda-400">{{ campoActivo === 'asunto' ? 'el asunto' : 'el contenido' }}</span>.
                        </p>
                        <div class="relative mb-3">
                            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                            <input v-model="busqueda" type="search" placeholder="Buscar variable" @keydown.enter.prevent
                                class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400" />
                        </div>
                        <div class="max-h-[420px] overflow-y-auto -mr-2 pr-2 space-y-4">
                            <div v-if="Object.keys(propiasFiltradas).length"
                                class="p-3 rounded-xl bg-guinda-50/60 dark:bg-guinda-900/10 border border-guinda-100 dark:border-guinda-900/40">
                                <p class="text-[11px] uppercase tracking-wide text-guinda-700 dark:text-guinda-400 font-semibold">Las que rellena este correo</p>
                                <p class="text-[11px] text-gray-500 dark:text-gray-400 mb-1.5">El sistema las sustituye al enviarlo.</p>
                                <div class="space-y-1">
                                    <button v-for="(desc, ph) in propiasFiltradas" :key="ph" type="button" @click="insertarPlaceholder(ph)"
                                        class="w-full text-left px-3 py-1.5 rounded-lg bg-white dark:bg-gray-800 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                                        <span class="block text-xs font-mono text-guinda-700 dark:text-guinda-400">{{ ph }}</span>
                                        <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ desc }}</span>
                                    </button>
                                </div>
                            </div>
                            <div v-for="(vars, grupo) in gruposFiltrados" :key="grupo">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">{{ grupo }}</p>
                                <div class="space-y-1">
                                    <button v-for="(desc, ph) in vars" :key="ph" type="button" @click="insertarPlaceholder(ph)"
                                        class="w-full text-left px-3 py-1.5 rounded-lg hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                                        <span class="block text-xs font-mono text-guinda-700 dark:text-guinda-400">{{ ph }}</span>
                                        <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ desc }}</span>
                                    </button>
                                </div>
                            </div>
                            <p v-if="sinCoincidencias" class="text-xs text-gray-400 text-center py-4">Sin coincidencias</p>
                        </div>
                    </div>

                    <TarjetaPrueba titulo="Enviar prueba" texto="Envía lo que tienes en el editor, aunque no lo hayas guardado."

                        v-model:email="emailPrueba" v-model:usuario="usuarioPrueba"

                        :enviando="enviandoPrueba" :aviso="avisoPrueba" :aviso-ok="avisoPruebaOk"

                        @enviar="enviarPrueba" />

                    <div v-if="plantilla.es_sistema"
                        class="flex gap-3 p-4 rounded-2xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
                        <svg class="w-5 h-5 shrink-0 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        <p class="text-xs text-amber-700 dark:text-amber-400">
                            Si desactivas esta plantilla, el sistema usará el contenido predeterminado del correo correspondiente.
                        </p>
                    </div>
                </aside>
            </div>
        </form>
    </AdminLayout>
</template>
