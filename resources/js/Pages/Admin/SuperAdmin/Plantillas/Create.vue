<script setup>
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { usePlantillaEditor, VISTAS, BLOQUES, filtrarGrupos } from './usePlantillaEditor';

const props = defineProps({
    htmlBase: String,
    grupos: Object,
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

// 'editor' | 'dividido' | 'preview'
const tab = ref('editor');

const {
    previewHtml, previewAsunto, cargandoPreview, errorPreview, generarPreview,
    emailPrueba, enviandoPrueba, avisoPrueba, avisoPruebaOk, enviarPrueba,
    textareaContenido, inputAsunto, campoActivo, insertarPlaceholder, insertarBloque,
    variablesDesconocidas,
} = usePlantillaEditor(form, tab, () => Object.values(props.grupos || {}).flatMap((g) => Object.keys(g)));

const vistas = VISTAS;

const destinatarios = [
    { valor: 'ambos',     titulo: 'Todos',       texto: 'Proveedores y compradores',
      icono: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { valor: 'proveedor', titulo: 'Proveedores', texto: 'Quienes venden sus productos',
      icono: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4' },
    { valor: 'comprador', titulo: 'Compradores', texto: 'Restauranteros y negocios',
      icono: 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z' },
];

const bloques = BLOQUES;
const menuBloques = ref(false);
const elegirBloque = (b) => { insertarBloque(b.html); menuBloques.value = false; };

// Vista previa en escritorio o celular
const dispositivo = ref('escritorio');

// Buscador del catalogo de variables
const busqueda = ref('');
const gruposFiltrados = computed(() => filtrarGrupos(props.grupos, busqueda.value));

const lineas = computed(() => (form.contenido || '').split('\n').length);
</script>

<template>
    <AdminLayout title="Nueva Plantilla de Correo">
        <Head title="Nueva Plantilla de Correo" />

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
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Nueva plantilla de correo</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Diseña el correo, revísalo con datos de ejemplo y pruébalo antes de guardarlo.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.plantillas.index')"
                        class="px-4 py-2 text-sm font-medium rounded-xl text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        Cancelar
                    </Link>
                    <button type="submit" :disabled="form.processing"
                        class="px-5 py-2 text-sm font-semibold rounded-xl bg-guinda-700 hover:bg-guinda-800 text-white shadow-sm transition-colors disabled:opacity-60">
                        {{ form.processing ? 'Guardando...' : 'Crear plantilla' }}
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 items-start">
                <div class="xl:col-span-2 space-y-6">
                    <!-- 1. Datos generales -->
                    <section class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex items-center gap-3 mb-5">
                            <span class="w-7 h-7 rounded-full bg-guinda-700 text-white text-xs font-bold flex items-center justify-center">1</span>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Datos de la plantilla</h2>
                                <p class="text-xs text-gray-400">Cómo la identificarás en el panel y a quién va dirigida</p>
                            </div>
                        </div>

                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.nombre" type="text" required placeholder="Ej. Bienvenida especial"
                            class="w-full px-4 py-2.5 text-base rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 focus:border-guinda-400 transition-colors" />
                        <div class="mt-2 flex items-center gap-2 text-xs text-gray-400">
                            <span>Clave:</span>
                            <code class="px-2 py-0.5 rounded-md bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 font-mono">{{ form.clave || 'se_genera_del_nombre' }}</code>
                        </div>
                        <p v-if="form.errors.nombre" class="mt-1 text-xs text-red-500">{{ form.errors.nombre }}</p>
                        <p v-if="form.errors.clave" class="mt-1 text-xs text-red-500">{{ form.errors.clave }}</p>

                        <p class="mt-5 mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                            Destinatarios <span class="text-red-500">*</span>
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <button v-for="d in destinatarios" :key="d.valor" type="button"
                                @click="form.tipo_destinatario = d.valor"
                                :class="['flex items-start gap-3 p-3 rounded-xl border text-left transition-all',
                                    form.tipo_destinatario === d.valor
                                        ? 'border-guinda-500 bg-guinda-50 dark:bg-guinda-900/20 ring-1 ring-guinda-500'
                                        : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600']">
                                <span :class="['w-9 h-9 shrink-0 rounded-lg flex items-center justify-center',
                                    form.tipo_destinatario === d.valor ? 'bg-guinda-700 text-white' : 'bg-gray-100 dark:bg-gray-800 text-gray-500']">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="d.icono" />
                                    </svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900 dark:text-white">{{ d.titulo }}</span>
                                    <span class="block text-[11px] text-gray-400 leading-tight mt-0.5">{{ d.texto }}</span>
                                </span>
                            </button>
                        </div>
                        <p v-if="form.errors.tipo_destinatario" class="mt-1 text-xs text-red-500">{{ form.errors.tipo_destinatario }}</p>
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
                                placeholder="Ej. {{nombre_usuario}}, te esperamos en el encuentro"
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
                                <p class="text-xs text-gray-400">HTML del cuerpo. El encabezado y pie ya vienen en la base.</p>
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
                                        <p class="text-[11px] text-gray-400 truncate">De: Impúlsate · Para: maria.perez@ejemplo.com</p>
                                    </div>
                                    <div class="flex justify-center p-3">
                                        <iframe :srcdoc="previewHtml" sandbox=""
                                            :class="['bg-white rounded-lg shadow-sm transition-all duration-300 h-[560px]',
                                                dispositivo === 'celular' ? 'w-[375px] max-w-full' : 'w-full']"></iframe>
                                    </div>
                                </div>
                                <p class="text-[11px] text-gray-400">
                                    Variables con datos de ejemplo; al enviarse se usan los de cada destinatario.
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
                            <div v-for="(vars, grupo) in gruposFiltrados" :key="grupo">
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 font-semibold mb-1.5">{{ grupo }}</p>
                                <div class="space-y-1">
                                    <button v-for="(desc, ph) in vars" :key="ph" type="button" @click="insertarPlaceholder(ph)"
                                        class="w-full text-left px-3 py-1.5 rounded-lg hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors group">
                                        <span class="block text-xs font-mono text-guinda-700 dark:text-guinda-400">{{ ph }}</span>
                                        <span class="block text-[11px] text-gray-400 dark:text-gray-500">{{ desc }}</span>
                                    </button>
                                </div>
                            </div>
                            <p v-if="!Object.keys(gruposFiltrados).length" class="text-xs text-gray-400 text-center py-4">Sin coincidencias</p>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-5">
                        <div class="flex items-center gap-2 mb-1">
                            <svg class="w-4 h-4 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Probar antes de crear</h3>
                        </div>
                        <p class="text-xs text-gray-400 mb-3">Envía el borrador a tu correo. No guarda la plantilla.</p>
                        <input v-model="emailPrueba" type="email" placeholder="tucorreo@ejemplo.com" @keydown.enter.prevent="enviarPrueba"
                            class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 mb-2" />
                        <button type="button" @click="enviarPrueba" :disabled="enviandoPrueba || !emailPrueba"
                            class="w-full px-3 py-2 text-sm font-semibold rounded-xl border border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-50 dark:hover:bg-guinda-950/20 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                            {{ enviandoPrueba ? 'Enviando...' : 'Enviar prueba' }}
                        </button>
                        <p v-if="avisoPrueba" class="mt-2 text-xs" :class="avisoPruebaOk ? 'text-emerald-600' : 'text-red-600'">
                            {{ avisoPrueba }}
                        </p>
                    </div>

                    <p class="text-[11px] text-gray-400 px-1">
                        Las plantillas personalizadas se pueden eliminar; las del sistema no.
                    </p>
                </aside>
            </div>
        </form>
    </AdminLayout>
</template>
