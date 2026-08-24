<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    plantillas: Array,
    eventos: Array,
    categorias: Array,
});

// ── Filtros ────────────────────────────────────────────────────
const filtros = ref({
    tipo:             'todos',
    estado_proveedor: '',
    categoria:        '',
    evento_id:        '',
    estado_evento:    '',
    canirac:          '',
});

// ── Preview destinatarios ──────────────────────────────────────
const previewing = ref(false);
const preview = ref(null);

const verDestinatarios = async () => {
    previewing.value = true;
    preview.value = null;
    try {
        const res = await fetch(route('admin.correo-masivo.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(filtros.value),
        });
        preview.value = await res.json();
    } finally {
        previewing.value = false;
    }
};

// ── Contenido correo ───────────────────────────────────────────
const tabContenido = ref('plantilla');
const plantillaSeleccionadaId = ref('');

const form = useForm({
    tipo:             'todos',
    estado_proveedor: '',
    categoria:        '',
    evento_id:        '',
    estado_evento:    '',
    canirac:          '',
    asunto:           '',
    contenido:        '',
});

watch(plantillaSeleccionadaId, (id) => {
    if (!id) return;
    const p = props.plantillas.find(p => p.id == id);
    if (p) {
        form.asunto = p.asunto;
        form.contenido = p.contenido;
    }
});

const previewCorreoSrc = computed(() =>
    form.contenido
        ? `data:text/html;charset=utf-8,${encodeURIComponent(form.contenido)}`
        : ''
);

const puedeEnviar = computed(() =>
    form.asunto && form.contenido && preview.value?.total > 0
);

const page = usePage();

// ── Modal de resultado (éxito / error) ──────────────────────────
const modalResultado = ref(false);
const resultado = ref({ tipo: 'success', mensaje: '' });

const mostrarResultado = (tipo, mensaje) => {
    resultado.value = { tipo, mensaje };
    modalResultado.value = true;
};

const cerrarResultado = () => { modalResultado.value = false; };

const enviar = () => {
    if (!preview.value || preview.value.total === 0) {
        alert('Primero haz clic en "Ver destinatarios".');
        return;
    }
    if (!confirm(`¿Enviar este correo a ${preview.value.total} destinatarios?`)) return;
    Object.assign(form, filtros.value);
    form.post(route('admin.correo-masivo.enviar'), {
        preserveScroll: true,
        onSuccess: () => {
            mostrarResultado('success', page.props.flash?.success || 'Correos enviados.');
        },
        onError: (errors) => {
            mostrarResultado('error', errors.error || 'Ocurrió un error al enviar los correos.');
        },
    });
};

// ── Prueba de envío ─────────────────────────────────────────────
const emailPrueba = ref('');
const enviandoPrueba = ref(false);

const enviarPrueba = () => {
    if (!emailPrueba.value || !form.asunto || !form.contenido) {
        alert('Completa el asunto, contenido y el email de prueba.');
        return;
    }
    enviandoPrueba.value = true;
    router.post(route('admin.correo-masivo.prueba'), {
        email_prueba: emailPrueba.value,
        asunto: form.asunto,
        contenido: form.contenido,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            mostrarResultado('success', page.props.flash?.success || `Correo de prueba enviado a ${emailPrueba.value}.`);
        },
        onError: (errors) => {
            mostrarResultado('error', errors.error || 'No se pudo enviar el correo de prueba.');
        },
        onFinish: () => { enviandoPrueba.value = false; },
    });
};
</script>

<template>
    <AdminLayout title="Correo Masivo">
        <Head title="Correo Masivo" />

        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Correo Masivo</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Filtra destinatarios y envía correos personalizados en cola.
                </p>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                <!-- ── COLUMNA IZQUIERDA: Filtros + Preview ── -->
                <div class="space-y-4">

                    <!-- Filtros -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 space-y-4">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Filtros de destinatarios</h2>

                        <!-- Tipo -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Destinatarios</label>
                            <select v-model="filtros.tipo"
                                class="w-full text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400">
                                <option value="todos">Todos (sin admins)</option>
                                <option value="proveedores">Solo proveedores</option>
                                <option value="compradores">Solo compradores</option>
                            </select>
                        </div>

                        <!-- Estado proveedor (solo si tipo=proveedores) -->
                        <div v-if="filtros.tipo === 'proveedores'">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Estado del proveedor</label>
                            <select v-model="filtros.estado_proveedor"
                                class="w-full text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400">
                                <option value="">Todos los estados</option>
                                <option value="aprobado">Aprobados</option>
                                <option value="pendiente">Pendientes</option>
                                <option value="rechazado">Rechazados</option>
                            </select>
                        </div>

                        <!-- Categoría (solo si tipo=proveedores) -->
                        <div v-if="filtros.tipo === 'proveedores'">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Categoría</label>
                            <select v-model="filtros.categoria"
                                class="w-full text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400">
                                <option value="">Todas las categorías</option>
                                <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>

                        <!-- Evento -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Evento (opcional)</label>
                            <select v-model="filtros.evento_id"
                                class="w-full text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400">
                                <option value="">-- Ninguno --</option>
                                <option v-for="ev in eventos" :key="ev.id" :value="ev.id">{{ ev.nombre }}</option>
                            </select>
                        </div>

                        <!-- Estado en evento (solo si hay evento) -->
                        <div v-if="filtros.evento_id">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Estado en el evento</label>
                            <select v-model="filtros.estado_evento"
                                class="w-full text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400">
                                <option value="">Todos</option>
                                <option value="aprobado">Aprobados</option>
                                <option value="pendiente">Pendientes</option>
                                <option value="rechazado">Rechazados</option>
                            </select>
                        </div>

                        <!-- Canirac -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Cámara / Asociación</label>
                            <select v-model="filtros.canirac"
                                class="w-full text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400">
                                <option value="">Canirac: Todos</option>
                                <option value="si">Solo Canirac</option>
                                <option value="no">No Canirac</option>
                            </select>
                        </div>

                        <button @click="verDestinatarios" :disabled="previewing"
                            class="w-full py-2.5 text-sm font-semibold rounded-xl bg-gray-800 hover:bg-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 text-white transition-colors disabled:opacity-60">
                            {{ previewing ? 'Cargando...' : 'Ver destinatarios' }}
                        </button>
                    </div>

                    <!-- Preview de destinatarios -->
                    <div v-if="preview" class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Destinatarios seleccionados</h3>
                            <span class="text-2xl font-black text-guinda-700 dark:text-guinda-400">{{ preview.total }}</span>
                        </div>
                        <div v-if="preview.total === 0" class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">
                            Ningún destinatario coincide con los filtros.
                        </div>
                        <div v-else class="space-y-2">
                            <div v-for="u in preview.destinatarios" :key="u.id"
                                class="flex items-center gap-3 px-3 py-2 rounded-xl bg-gray-50 dark:bg-gray-800">
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-800 dark:text-gray-200 truncate flex items-center gap-1.5">
                                        <span class="truncate">{{ u.name }}</span>
                                        <span v-if="u.canirac"
                                            class="shrink-0 text-[10px] px-1.5 py-0.5 bg-orange-900/30 text-orange-400 border border-orange-500/20 rounded font-medium">
                                            CANIRAC
                                        </span>
                                    </p>
                                    <p class="text-xs text-gray-400 dark:text-gray-500 truncate">{{ u.email }}</p>
                                </div>
                                <span class="text-xs text-gray-400 dark:text-gray-500 shrink-0">{{ u.rol }}</span>
                            </div>
                            <p v-if="preview.total > 20" class="text-xs text-gray-400 dark:text-gray-500 text-center pt-1">
                                ...y {{ preview.total - 20 }} más
                            </p>
                        </div>
                    </div>
                </div>

                <!-- ── COLUMNA DERECHA: Contenido del correo ── -->
                <div class="space-y-4">
                    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 space-y-4">
                        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Contenido del correo</h2>

                        <!-- Tabs plantilla / personalizado -->
                        <div class="flex gap-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-xl w-fit">
                            <button @click="tabContenido = 'plantilla'"
                                :class="['px-4 py-1.5 text-xs font-medium rounded-lg transition-colors',
                                    tabContenido === 'plantilla' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400']">
                                Usar plantilla
                            </button>
                            <button @click="tabContenido = 'personalizado'"
                                :class="['px-4 py-1.5 text-xs font-medium rounded-lg transition-colors',
                                    tabContenido === 'personalizado' ? 'bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400']">
                                Personalizado
                            </button>
                        </div>

                        <!-- Selector de plantilla -->
                        <div v-if="tabContenido === 'plantilla'">
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Selecciona plantilla</label>
                            <select v-model="plantillaSeleccionadaId"
                                class="w-full text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400">
                                <option value="">-- Elegir plantilla --</option>
                                <option v-for="p in plantillas" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                            </select>
                        </div>

                        <!-- Asunto -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                Asunto <span class="text-red-500">*</span>
                            </label>
                            <input v-model="form.asunto" type="text"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        </div>

                        <!-- Contenido HTML -->
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                Contenido HTML <span class="text-red-500">*</span>
                            </label>
                            <textarea v-model="form.contenido" rows="12"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 font-mono transition-colors resize-y"></textarea>
                        </div>

                        <!-- Vista previa del email -->
                        <div v-if="form.contenido" class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 bg-white">
                            <p class="text-xs text-gray-400 dark:text-gray-500 px-3 py-2 border-b border-gray-100 dark:border-gray-800">Vista previa del correo</p>
                            <iframe :src="previewCorreoSrc" class="w-full h-72" sandbox="allow-same-origin"></iframe>
                        </div>

                        <!-- Prueba de envío -->
                        <div class="border border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-4 bg-gray-50 dark:bg-gray-800/50">
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                                Prueba de envío
                            </p>
                            <div class="flex gap-2">
                                <input v-model="emailPrueba" type="email" placeholder="tu@correo.com"
                                    class="flex-1 text-sm rounded-xl border border-gray-300 dark:border-gray-600
                                           bg-white dark:bg-gray-800 text-gray-900 dark:text-white
                                           px-3 py-2 focus:ring-2 focus:ring-gray-400 focus:outline-none" />
                                <button @click="enviarPrueba"
                                    :disabled="!emailPrueba || !form.asunto || !form.contenido || enviandoPrueba"
                                    class="px-4 py-2 text-sm font-semibold rounded-xl
                                           bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200
                                           hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors
                                           disabled:opacity-50 disabled:cursor-not-allowed">
                                    {{ enviandoPrueba ? 'Enviando…' : 'Enviar prueba' }}
                                </button>
                            </div>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1.5">
                                Se antepondrá [PRUEBA] al asunto. Verifica el diseño antes del envío masivo.
                            </p>
                        </div>

                        <!-- Botón enviar -->
                        <div class="pt-2">
                            <button @click="enviar"
                                :disabled="!puedeEnviar || form.processing"
                                class="w-full py-3 text-sm font-bold rounded-xl bg-[#8b1028] hover:bg-[#6f0c1f] text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ form.processing
                                    ? 'Enviando...'
                                    : preview?.total
                                        ? `Enviar correo a ${preview.total} destinatarios`
                                        : 'Enviar correos' }}
                            </button>
                            <p v-if="!preview" class="text-xs text-gray-400 dark:text-gray-500 text-center mt-2">
                                Primero selecciona los filtros y haz clic en "Ver destinatarios"
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal de resultado -->
        <Teleport to="body">
            <div v-if="modalResultado" class="fixed inset-0 z-50 flex items-center justify-center p-4"
                style="background:rgba(0,0,0,0.55)" @click.self="cerrarResultado">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl w-full max-w-sm shadow-2xl p-6 text-center">
                    <div :class="[
                            'w-14 h-14 mx-auto rounded-full flex items-center justify-center mb-4',
                            resultado.tipo === 'success'
                                ? 'bg-emerald-100 dark:bg-emerald-900/30'
                                : 'bg-red-100 dark:bg-red-900/30'
                        ]">
                        <svg v-if="resultado.tipo === 'success'" class="w-7 h-7 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <svg v-else class="w-7 h-7 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1.5">
                        {{ resultado.tipo === 'success' ? 'Correo enviado' : 'No se pudo enviar' }}
                    </h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                        {{ resultado.mensaje }}
                    </p>
                    <button @click="cerrarResultado"
                        class="w-full py-2.5 text-sm font-semibold rounded-xl bg-[#8b1028] hover:bg-[#6f0c1f] text-white transition-colors">
                        Cerrar
                    </button>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
