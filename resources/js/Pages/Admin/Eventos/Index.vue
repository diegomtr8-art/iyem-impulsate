<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    eventos: Array,
    categorias: Array,
});

const mostrarForm = ref(false);
const previewNuevo = ref(null);
const previewEditar = ref(null);
const previewCarruselNuevo = ref(null);
const previewCarruselEditar = ref(null);

const form = useForm({
    nombre: '',
    sector_economico: '',
    descripcion: '',
    convocatoria_url: '',
    imagen: null,
    imagen_carrusel: null,
    fecha_hora_inicio: '',
    fecha_hora_fin: '',
    fecha_hora_inicio_proveedores: '',
    fecha_hora_fin_proveedores: '',
    fecha_hora_inicio_compradores: '',
    fecha_hora_fin_compradores: '',
    max_citas_por_comprador: 3,
    tiempo_entre_citas_minutos: 30,
    tipo_evento: 'encuentro_negocios',
    max_espacios: null,
    con_criterios_evaluacion: false,
    fecha_aceptacion_solicitudes: '',
    criterios: [],
});

const totalPorcentaje = computed(() =>
    form.criterios.reduce((s, c) => s + (Number(c.porcentaje) || 0), 0)
);

const onImagenNuevo = (e) => {
    const f = e.target.files[0];
    form.imagen = f ?? null;
    previewNuevo.value = f ? URL.createObjectURL(f) : null;
};

const onImagenCarruselNuevo = (e) => {
    const f = e.target.files[0];
    form.imagen_carrusel = f ?? null;
    previewCarruselNuevo.value = f ? URL.createObjectURL(f) : null;
};

const modalEditar = ref(false);
const eventoEditando = ref(null);
const formEditar = useForm({
    nombre: '',
    sector_economico: '',
    descripcion: '',
    convocatoria_url: '',
    imagen: null,
    imagen_carrusel: null,
    _method: 'PATCH',
    fecha_hora_inicio: '',
    fecha_hora_fin: '',
    fecha_hora_inicio_proveedores: '',
    fecha_hora_fin_proveedores: '',
    fecha_hora_inicio_compradores: '',
    fecha_hora_fin_compradores: '',
    max_citas_por_comprador: 3,
    tiempo_entre_citas_minutos: 30,
    tipo_evento: 'encuentro_negocios',
    max_espacios: null,
    con_criterios_evaluacion: false,
    fecha_aceptacion_solicitudes: '',
    criterios: [],
});

const totalPorcentajeEditar = computed(() =>
    formEditar.criterios.reduce((s, c) => s + (Number(c.porcentaje) || 0), 0)
);

const onImagenEditar = (e) => {
    const f = e.target.files[0];
    formEditar.imagen = f ?? null;
    previewEditar.value = f ? URL.createObjectURL(f) : null;
};

const onImagenCarruselEditar = (e) => {
    const f = e.target.files[0];
    formEditar.imagen_carrusel = f ?? null;
    previewCarruselEditar.value = f ? URL.createObjectURL(f) : null;
};

const abrirEditar = (evento) => {
    eventoEditando.value = evento;
    previewEditar.value = evento.imagen_url ?? null;
    const toLocal = (iso) => {
        if (!iso) return '';
        const d = new Date(iso);
        const pad = n => String(n).padStart(2, '0');
        return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
    };
    formEditar.nombre                        = evento.nombre || '';
    formEditar.sector_economico              = evento.sector_economico || '';
    formEditar.descripcion                   = evento.descripcion || '';
    formEditar.imagen                        = null;
    formEditar._method                       = 'PATCH';
    formEditar.fecha_hora_inicio             = toLocal(evento.fecha_hora_inicio);
    formEditar.fecha_hora_fin                = toLocal(evento.fecha_hora_fin);
    formEditar.fecha_hora_inicio_proveedores = toLocal(evento.fecha_hora_inicio_proveedores);
    formEditar.fecha_hora_fin_proveedores    = toLocal(evento.fecha_hora_fin_proveedores);
    formEditar.fecha_hora_inicio_compradores = toLocal(evento.fecha_hora_inicio_compradores);
    formEditar.fecha_hora_fin_compradores    = toLocal(evento.fecha_hora_fin_compradores);
    formEditar.max_citas_por_comprador       = evento.max_citas_por_comprador || 3;
    formEditar.fecha_aceptacion_solicitudes  = toLocal(evento.fecha_aceptacion_solicitudes);
    formEditar.tiempo_entre_citas_minutos    = evento.tiempo_entre_citas_minutos || 30;
    formEditar.convocatoria_url              = evento.convocatoria_url || '';
    formEditar.imagen_carrusel               = null;
    previewCarruselEditar.value              = evento.imagen_carrusel_url ?? null;
    formEditar.tipo_evento                   = evento.tipo_evento || 'encuentro_negocios';
    formEditar.max_espacios                  = evento.max_espacios ?? null;
    formEditar.con_criterios_evaluacion      = !!evento.con_criterios_evaluacion;
    formEditar.criterios                     = (evento.criterios || []).map(c => ({ id: c.id, nombre: c.nombre, porcentaje: Number(c.porcentaje) }));
    formEditar.clearErrors();
    modalEditar.value = true;
};

const guardarEdicion = () => {
    formEditar.post(route('admin.eventos.update', eventoEditando.value.id), {
        forceFormData: true,
        onSuccess: () => { modalEditar.value = false; previewEditar.value = null; },
    });
};

// Puede haber varios eventos activos simultáneos.
const eventosActivos = computed(() => props.eventos.filter(e => e.activa));
const eventoActivo = computed(() => eventosActivos.value[0] || null);
const eventosArchivados = computed(() => props.eventos.filter(e => !e.activa));

const toggleForm = () => {
    mostrarForm.value = !mostrarForm.value;
    if (mostrarForm.value) form.clearErrors();
};

const crearEvento = () => {
    form.post(route('admin.eventos.store'), {
        forceFormData: true,
        onSuccess: () => {
            mostrarForm.value = false;
            previewNuevo.value = null;
            form.reset();
        },
    });
};

const archivar = (evento) => {
    if (!confirm(`¿Archivar el evento "${evento.nombre}"? Los usuarios no podrán agendar nuevas citas hasta que actives otro evento.`)) return;
    router.post(route('admin.eventos.archivar', evento.id));
};

const enviarEncuestas = (evento) => {
    if (!confirm(`¿Enviar encuesta de satisfacción a todos los participantes aprobados de "${evento.nombre}"?`)) return;
    router.post(route('admin.eventos.enviar-encuestas', evento.id));
};

const activar = (evento) => {
    if (!confirm(`¿Activar el evento "${evento.nombre}"? Los eventos que ya estén activos seguirán activos.`)) return;
    router.post(route('admin.eventos.activar', evento.id));
};

const eliminar = (evento) => {
    if (!confirm(`¿Eliminar el evento "${evento.nombre}"? Esta acción no se puede deshacer.`)) return;
    router.delete(route('admin.eventos.destroy', evento.id));
};

const formatFecha = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' });
};

const formatFechaCorta = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

// Diagrama de secuencia
const secuenciaValida = computed(() =>
    form.fecha_hora_inicio_proveedores && form.fecha_hora_inicio_compradores && form.fecha_hora_inicio
);
const secuenciaValidaEditar = computed(() =>
    formEditar.fecha_hora_inicio_proveedores && formEditar.fecha_hora_inicio_compradores && formEditar.fecha_hora_inicio
);
</script>

<template>
    <AdminLayout title="Eventos">
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Eventos</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Gestiona los eventos de la plataforma por sector económico</p>
                </div>
                <button
                    @click="toggleForm"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Nuevo evento
                </button>
            </div>
        </template>

        <div class="space-y-6 max-w-5xl">

            <!-- Formulario nuevo evento -->
            <div v-if="mostrarForm" class="bg-white dark:bg-gray-900 border border-guinda-200 dark:border-guinda-900 rounded-2xl p-6 shadow-sm">
                <h2 class="font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-guinda-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Crear nuevo evento
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre del Evento *</label>
                        <input v-model="form.nombre" type="text" placeholder="Ej. Impulsate 2026 — Tech"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sector Económico</label>
                        <select v-model="form.sector_economico"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors">
                            <option value="">— Seleccionar sector —</option>
                            <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Descripción</label>
                        <input v-model="form.descripcion" type="text" placeholder="Descripción breve del evento..."
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Imagen del evento <span class="text-gray-400">(opcional, JPG/PNG/WebP, máx. 4MB — recomendado 1200×675px, horizontal 16:9)</span></label>
                        <input type="file" accept="image/*" @change="onImagenNuevo"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        <div v-if="previewNuevo" class="mt-2">
                            <img :src="previewNuevo" alt="Preview" class="h-28 w-full object-cover rounded-lg border border-gray-200 dark:border-gray-700" />
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                            Liga de la convocatoria <span class="text-red-500">*</span>
                        </label>
                        <input v-model="form.convocatoria_url" type="url" placeholder="https://..."
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        <p class="text-[11px] text-gray-400 mt-1">Esta liga se mostrará en el aviso emergente que verán los usuarios antes de registrarse como proveedor o comprador a este evento.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                            Imagen para el carrusel de la página principal
                            <span class="text-gray-400">(opcional, JPG/PNG/WebP, máx. 4MB — recomendado 1200×1500px vertical/cuadrada)</span>
                        </label>
                        <input type="file" accept="image/*" @change="onImagenCarruselNuevo"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        <div v-if="previewCarruselNuevo" class="mt-2">
                            <img :src="previewCarruselNuevo" alt="Preview carrusel" class="h-28 w-full object-cover rounded-lg border border-gray-200 dark:border-gray-700" />
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Si se sube, esta imagen aparecerá en el carrusel de "Próximos Encuentros" de la página de inicio.</p>
                    </div>
                </div>

                <!-- Ventanas temporales -->
                <div class="mt-5 p-4 bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-800/30 rounded-xl">
                    <p class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 mb-3 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ form.tipo_evento === 'bazar_exposicion' ? 'Fecha del evento' : 'Secuencia temporal del evento' }}
                    </p>

                    <!-- Fechas de registro proveedores/compradores: solo encuentro de negocios -->
                    <div v-if="form.tipo_evento === 'encuentro_negocios'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Inicio registro proveedores</label>
                            <input v-model="form.fecha_hora_inicio_proveedores" type="datetime-local"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fin registro proveedores <span class="text-gray-400">(opcional)</span></label>
                            <input v-model="form.fecha_hora_fin_proveedores" type="datetime-local"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Inicio agendado compradores</label>
                            <input v-model="form.fecha_hora_inicio_compradores" type="datetime-local"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fin agendado compradores <span class="text-gray-400">(opcional)</span></label>
                            <input v-model="form.fecha_hora_fin_compradores" type="datetime-local"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha y Hora de Inicio del Evento *</label>
                            <input v-model="form.fecha_hora_inicio" type="datetime-local"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha y Hora de Finalización</label>
                            <input v-model="form.fecha_hora_fin" type="datetime-local"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                    </div>

                    <!-- Solo inicio/fin del evento: Bazar / Exposición -->
                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha y Hora de Inicio *</label>
                            <input v-model="form.fecha_hora_inicio" type="datetime-local"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha y Hora de Finalización</label>
                            <input v-model="form.fecha_hora_fin" type="datetime-local"
                                class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        </div>
                    </div>

                    <!-- Diagrama de secuencia: solo encuentro de negocios -->
                    <div v-if="form.tipo_evento === 'encuentro_negocios' && secuenciaValida" class="mt-4 flex items-center gap-1 flex-wrap text-xs">
                        <span class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded font-medium text-gray-600 dark:text-gray-400">Ahora</span>
                        <span class="text-gray-400">→</span>
                        <span class="px-2 py-1 bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-300 rounded font-medium">Inicio Reg. Prov.</span>
                        <template v-if="form.fecha_hora_fin_proveedores">
                            <span class="text-gray-400">→</span>
                            <span class="px-2 py-1 bg-guinda-50 dark:bg-guinda-900/20 text-guinda-600 dark:text-guinda-400 rounded font-medium">Fin Reg. Prov.</span>
                        </template>
                        <span class="text-gray-400">→</span>
                        <span class="px-2 py-1 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 rounded font-medium">Inicio Reg. Comp.</span>
                        <template v-if="form.fecha_hora_fin_compradores">
                            <span class="text-gray-400">→</span>
                            <span class="px-2 py-1 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 rounded font-medium">Fin Reg. Comp.</span>
                        </template>
                        <span class="text-gray-400">→</span>
                        <span class="px-2 py-1 bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-300 rounded font-medium">Evento Presencial</span>
                        <span class="text-gray-400">→</span>
                        <span class="px-2 py-1 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded font-medium">Fin</span>
                    </div>
                </div>

                <!-- Config adicional: solo encuentro de negocios -->
                <div v-if="form.tipo_evento === 'encuentro_negocios'" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Máximo de citas por comprador</label>
                        <input v-model.number="form.max_citas_por_comprador" type="number" min="1" max="50"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tiempo entre citas (minutos)</label>
                        <input v-model.number="form.tiempo_entre_citas_minutos" type="number" min="5" max="120" step="5"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        <p class="text-xs text-gray-400 mt-0.5">Debe ser múltiplo de 5 (ej: 10, 15, 30)</p>
                    </div>
                </div>

                <!-- Tipo de evento -->
                <div class="mt-5 p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl">
                    <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3">Tipo de evento</p>
                    <div class="flex gap-2 flex-wrap">
                        <button type="button"
                            @click="form.tipo_evento = 'encuentro_negocios'"
                            :class="form.tipo_evento === 'encuentro_negocios'
                                ? 'bg-guinda-800 text-white border-guinda-800'
                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700'"
                            class="px-4 py-2 text-sm font-semibold rounded-xl border transition-colors flex items-center gap-2">
                            Encuentro de negocios
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Con citas</span>
                        </button>
                        <button type="button"
                            @click="form.tipo_evento = 'bazar_exposicion'"
                            :class="form.tipo_evento === 'bazar_exposicion'
                                ? 'bg-guinda-800 text-white border-guinda-800'
                                : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700'"
                            class="px-4 py-2 text-sm font-semibold rounded-xl border transition-colors flex items-center gap-2">
                            Exposición / Bazar
                            <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700">Sin citas</span>
                        </button>
                    </div>

                    <div v-if="form.tipo_evento === 'bazar_exposicion'" class="mt-4">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Número máximo de espacios (N)</label>
                        <input v-model.number="form.max_espacios" type="number" min="1" max="9999" placeholder="Ej: 12"
                            class="w-40 text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                        <p class="text-[11px] text-gray-400 mt-1">Solo se podrán seleccionar hasta este número de participantes como ganadores del evento.</p>
                    </div>

                    <!-- Apertura de solicitudes (solo bazar) -->
                    <div v-if="form.tipo_evento === 'bazar_exposicion'">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Apertura de solicitudes de expositores
                            <span class="text-xs text-gray-400 font-normal ml-1">(opcional — si no se define, el registro abre inmediatamente)</span>
                        </label>
                        <input
                            type="datetime-local"
                            v-model="form.fecha_aceptacion_solicitudes"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500"
                        />
                        <p class="text-xs text-gray-400 mt-1">
                            Los usuarios no podrán registrarse antes de esta fecha/hora.
                        </p>
                    </div>

                    <div v-if="form.tipo_evento === 'bazar_exposicion'" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <label class="flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3">
                            <input type="checkbox" v-model="form.con_criterios_evaluacion" class="rounded" />
                            Habilitar criterios de evaluación
                        </label>

                        <div v-if="form.con_criterios_evaluacion" class="space-y-2">
                            <div v-for="(c, i) in form.criterios" :key="i" class="flex items-center gap-2">
                                <input v-model="c.nombre" placeholder="Nombre del criterio"
                                    class="flex-1 text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                <input v-model.number="c.porcentaje" type="number" min="1" max="100" placeholder="%"
                                    class="w-20 text-sm text-center bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                <span class="text-gray-400 text-sm">%</span>
                                <button type="button" @click="form.criterios.splice(i, 1)"
                                    class="p-1.5 text-red-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">✕</button>
                            </div>

                            <button type="button" @click="form.criterios.push({ nombre: '', porcentaje: 0 })"
                                class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 hover:underline">
                                + Agregar criterio
                            </button>

                            <div v-if="form.criterios.length > 0" class="flex items-center gap-2 text-xs font-medium mt-2"
                                :class="totalPorcentaje === 100 ? 'text-emerald-600' : 'text-amber-600'">
                                <span>Total: {{ totalPorcentaje }}%</span>
                                <span v-if="totalPorcentaje === 100">✓ Completo</span>
                                <span v-else>⚠ Debe sumar 100%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Errores de validación -->
                <div v-if="Object.keys(form.errors).length" class="mt-5 p-4 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 rounded-xl">
                    <p class="text-sm font-semibold text-red-700 dark:text-red-400 mb-2 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 12.25A2 2 0 004.99 19z" /></svg>
                        No se pudo crear el evento. Revisa lo siguiente:
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-sm text-red-600 dark:text-red-300">
                        <li v-for="(mensaje, campo) in form.errors" :key="campo">{{ mensaje }}</li>
                    </ul>
                </div>

                <div class="flex items-center gap-3 mt-5">
                    <button @click="crearEvento" :disabled="form.processing"
                        class="px-5 py-2 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-semibold rounded-xl transition-colors">
                        {{ form.processing ? 'Creando…' : 'Crear evento' }}
                    </button>
                    <button @click="mostrarForm = false"
                        class="px-5 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400 text-sm font-medium rounded-xl transition-colors">
                        Cancelar
                    </button>
                </div>
            </div>

            <!-- Eventos activos (pueden ser varios simultáneos) -->
            <div>
                <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">
                    {{ eventosActivos.length > 1 ? `Eventos activos (${eventosActivos.length})` : 'Evento activo' }}
                </h2>

                <div v-if="eventosActivos.length" class="space-y-4">
                <div v-for="eventoActivo in eventosActivos" :key="eventoActivo.id"
                     class="bg-white dark:bg-gray-900 border-2 border-emerald-300 dark:border-emerald-700 rounded-2xl p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="w-2.5 h-2.5 bg-emerald-500 rounded-full animate-pulse"></span>
                                <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">En curso</span>
                            </div>
                            <h3 class="text-xl font-black text-gray-900 dark:text-white">{{ eventoActivo.nombre }}</h3>
                            <p v-if="eventoActivo.sector_economico" class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Sector: {{ eventoActivo.sector_economico }}</p>
                            <p v-if="eventoActivo.descripcion" class="text-sm text-gray-400 dark:text-gray-600 mt-1">{{ eventoActivo.descripcion }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 flex-wrap">
                            <button @click="abrirEditar(eventoActivo)"
                                class="inline-flex items-center gap-2 px-4 py-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 text-sm font-semibold rounded-xl transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Editar
                            </button>
                            <button @click="archivar(eventoActivo)"
                                class="inline-flex items-center gap-2 px-4 py-2 border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm font-semibold rounded-xl transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
                                </svg>
                                Archivar evento
                            </button>
                        </div>
                    </div>

                    <!-- Secuencia temporal -->
                    <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-xl space-y-1.5 text-xs text-gray-600 dark:text-gray-400">
                        <div v-if="eventoActivo.fecha_hora_inicio_proveedores" class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-guinda-400 rounded-full shrink-0"></span>
                            <span>Reg. Proveedores desde: <strong class="text-gray-800 dark:text-gray-200">{{ formatFecha(eventoActivo.fecha_hora_inicio_proveedores) }}</strong></span>
                        </div>
                        <div v-if="eventoActivo.fecha_hora_inicio_compradores" class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-emerald-400 rounded-full shrink-0"></span>
                            <span>Agendado desde: <strong class="text-gray-800 dark:text-gray-200">{{ formatFecha(eventoActivo.fecha_hora_inicio_compradores) }}</strong></span>
                        </div>
                        <div v-if="eventoActivo.fecha_hora_inicio" class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-guinda-500 rounded-full shrink-0"></span>
                            <span>Inicio del evento: <strong class="text-gray-800 dark:text-gray-200">{{ formatFecha(eventoActivo.fecha_hora_inicio) }}</strong></span>
                        </div>
                        <div v-if="eventoActivo.fecha_hora_fin" class="flex items-center gap-2">
                            <span class="w-2 h-2 bg-red-400 rounded-full shrink-0"></span>
                            <span>Fin: <strong class="text-gray-800 dark:text-gray-200">{{ formatFecha(eventoActivo.fecha_hora_fin) }}</strong></span>
                        </div>
                        <div class="flex items-center gap-4 pt-1 border-t border-gray-200 dark:border-gray-700 mt-2">
                            <span>Máx. citas/comprador: <strong>{{ eventoActivo.max_citas_por_comprador || 3 }}</strong></span>
                            <span>Tiempo entre citas: <strong>{{ eventoActivo.tiempo_entre_citas_minutos || 30 }} min</strong></span>
                        </div>
                    </div>


                    <!-- Acciones adicionales evento activo -->
                    <div class="flex flex-col sm:flex-row gap-3 mt-5 pt-5 border-t border-gray-100 dark:border-gray-800">
                        <Link v-if="eventoActivo.tipo_evento === 'bazar_exposicion'"
                            :href="route('admin.eventos.bazar.index', eventoActivo.id)"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14"/>
                            </svg>
                            Evaluación del bazar
                        </Link>
                        <button v-if="eventoActivo.tipo_evento !== 'bazar_exposicion'" @click="enviarEncuestas(eventoActivo)"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-800/40 hover:bg-guinda-100 dark:hover:bg-guinda-950/30 text-guinda-700 dark:text-guinda-400 text-sm font-semibold rounded-xl transition-colors">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Enviar encuestas
                        </button>
                        <Link v-if="eventoActivo.tipo_evento !== 'bazar_exposicion'" :href="route('admin.eventos.solicitudes', eventoActivo.id)"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 hover:bg-amber-100 dark:hover:bg-amber-950/30 text-amber-800 dark:text-amber-400 text-sm font-semibold rounded-xl transition-colors relative">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>Ver solicitudes</span>
                            <span v-if="eventoActivo.solicitudes_pendientes > 0"
                                class="inline-flex items-center justify-center min-w-[20px] h-5 px-1 rounded-full text-xs font-bold bg-guinda-600 text-white animate-pulse">
                                {{ eventoActivo.solicitudes_pendientes }}
                            </span>
                        </Link>
                        <a :href="route('admin.exportar.evento', eventoActivo.id)"
                            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 hover:bg-emerald-100 dark:hover:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400 text-sm font-semibold rounded-xl transition-colors">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Descargar Excel
                        </a>
                    </div>
                </div>
                </div>

                <div v-else class="bg-amber-50 dark:bg-amber-900/10 border border-amber-200 dark:border-amber-800 rounded-2xl p-6">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div>
                            <p class="font-semibold text-amber-800 dark:text-amber-300 text-sm">Sin evento activo</p>
                            <p class="text-amber-700 dark:text-amber-400/80 text-xs mt-0.5">Los usuarios no pueden agendar citas. Activa o crea un evento.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Eventos archivados -->
            <div v-if="eventosArchivados.length > 0">
                <h2 class="text-xs font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-3">Eventos archivados</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-for="evento in eventosArchivados" :key="evento.id"
                        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <h3 class="font-bold text-gray-900 dark:text-gray-200 truncate">{{ evento.nombre }}</h3>
                                <p v-if="evento.sector_economico" class="text-xs text-gray-500 dark:text-gray-500">{{ evento.sector_economico }}</p>
                            </div>
                            <span class="shrink-0 text-xs bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400 px-2 py-0.5 rounded-full font-medium">Archivado</span>
                        </div>

                        <div class="text-xs text-gray-400 dark:text-gray-600 space-y-0.5 mb-4">
                            <p v-if="evento.fecha_hora_inicio">Inicio: {{ formatFechaCorta(evento.fecha_hora_inicio) }}</p>
                            <p v-if="evento.fecha_corte">Archivado: {{ formatFechaCorta(evento.fecha_corte) }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <Link v-if="evento.tipo_evento === 'bazar_exposicion'"
                                :href="route('admin.eventos.bazar.index', evento.id)"
                                class="px-3 py-1.5 bg-guinda-800 hover:bg-guinda-700 text-white text-xs font-semibold rounded-lg transition-colors flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14"/>
                                </svg>
                                Evaluación
                            </Link>
                            <button @click="activar(evento)"
                                class="flex-1 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 hover:bg-emerald-100 dark:hover:bg-emerald-900/30 text-xs font-semibold rounded-lg transition-colors text-center">
                                Reactivar
                            </button>
                            <button @click="abrirEditar(evento)"
                                class="px-3 py-1.5 bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                Editar
                            </button>
                            <Link v-if="evento.tipo_evento !== 'bazar_exposicion'" :href="route('admin.eventos.solicitudes', evento.id)"
                                class="relative px-3 py-1.5 bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800/40 hover:bg-amber-100 dark:hover:bg-amber-950/30 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Solicitudes
                                <span v-if="evento.solicitudes_pendientes > 0"
                                    class="inline-flex items-center justify-center min-w-[16px] h-4 px-1 rounded-full text-xs font-bold bg-guinda-600 text-white animate-pulse">
                                    {{ evento.solicitudes_pendientes }}
                                </span>
                            </Link>
                            <a :href="route('admin.exportar.evento', evento.id)"
                                class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Excel
                            </a>
                            <button v-if="evento.citas_count === 0" @click="eliminar(evento)"
                                class="px-3 py-1.5 text-red-400 dark:text-red-500 hover:text-red-500 dark:hover:text-red-400 text-xs font-medium rounded-lg hover:bg-red-50 dark:hover:bg-red-900/10 transition-colors">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal de Edición -->
        <Teleport to="body">
            <div v-if="modalEditar && eventoEditando" class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-16"
                style="background:rgba(0,0,0,0.5)" @click.self="modalEditar = false">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-2xl w-full max-w-2xl shadow-2xl max-h-[85vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800 sticky top-0 bg-white dark:bg-gray-900">
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">Editar evento: {{ eventoEditando.nombre }}</h2>
                        <button @click="modalEditar = false" class="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-400 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre *</label>
                                <input v-model="formEditar.nombre" type="text"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sector Económico</label>
                                <input v-model="formEditar.sector_economico" type="text"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Descripción</label>
                                <input v-model="formEditar.descripcion" type="text"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Imagen del evento <span class="text-gray-400">(opcional — deja vacío para conservar la actual, recomendado 1200×675px)</span></label>
                                <input type="file" accept="image/*" @change="onImagenEditar"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                <div v-if="previewEditar" class="mt-2">
                                    <img :src="previewEditar" alt="Preview" class="h-24 w-full object-cover rounded-lg border border-gray-200 dark:border-gray-700" />
                                </div>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Liga de la convocatoria <span class="text-red-500">*</span>
                                </label>
                                <input v-model="formEditar.convocatoria_url" type="url" placeholder="https://..."
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                <p class="text-[11px] text-gray-400 mt-1">Esta liga se mostrará en el aviso emergente que verán los usuarios antes de registrarse.</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                    Imagen para el carrusel <span class="text-gray-400">(opcional — deja vacío para conservar la actual, recomendado 1200×1500px)</span>
                                </label>
                                <input type="file" accept="image/*" @change="onImagenCarruselEditar"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                <div v-if="previewCarruselEditar" class="mt-2">
                                    <img :src="previewCarruselEditar" alt="Preview carrusel" class="h-24 w-full object-cover rounded-lg border border-gray-200 dark:border-gray-700" />
                                </div>
                            </div>
                        </div>
                        <div class="p-4 bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-800/30 rounded-xl">
                            <p class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 mb-3">
                                {{ formEditar.tipo_evento === 'bazar_exposicion' ? 'Fecha del evento' : 'Ventanas temporales' }}
                            </p>

                            <!-- Fechas de registro proveedores/compradores: solo encuentro de negocios -->
                            <div v-if="formEditar.tipo_evento === 'encuentro_negocios'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Inicio registro proveedores</label>
                                    <input v-model="formEditar.fecha_hora_inicio_proveedores" type="datetime-local"
                                        class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fin registro proveedores <span class="text-gray-400">(opt.)</span></label>
                                    <input v-model="formEditar.fecha_hora_fin_proveedores" type="datetime-local"
                                        class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Inicio agendado compradores</label>
                                    <input v-model="formEditar.fecha_hora_inicio_compradores" type="datetime-local"
                                        class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fin agendado compradores <span class="text-gray-400">(opt.)</span></label>
                                    <input v-model="formEditar.fecha_hora_fin_compradores" type="datetime-local"
                                        class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Inicio evento presencial</label>
                                    <input v-model="formEditar.fecha_hora_inicio" type="datetime-local"
                                        class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fin evento</label>
                                    <input v-model="formEditar.fecha_hora_fin" type="datetime-local"
                                        class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                </div>
                            </div>

                            <!-- Solo inicio/fin del evento: Bazar / Exposición -->
                            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha y Hora de Inicio *</label>
                                    <input v-model="formEditar.fecha_hora_inicio" type="datetime-local"
                                        class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Fecha y Hora de Finalización</label>
                                    <input v-model="formEditar.fecha_hora_fin" type="datetime-local"
                                        class="w-full text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                </div>
                            </div>

                            <div v-if="formEditar.tipo_evento === 'encuentro_negocios' && secuenciaValidaEditar" class="mt-3 flex items-center gap-1 flex-wrap text-xs">
                                <span class="px-2 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-gray-600 dark:text-gray-400">Ahora</span>
                                <span class="text-gray-400">→</span>
                                <span class="px-2 py-0.5 bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-300 rounded">Reg. Prov.</span>
                                <template v-if="formEditar.fecha_hora_fin_proveedores">
                                    <span class="text-gray-400">→</span>
                                    <span class="px-2 py-0.5 bg-guinda-50 text-guinda-600 dark:text-guinda-400 rounded">Fin Reg. Prov.</span>
                                </template>
                                <span class="text-gray-400">→</span>
                                <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 rounded">Reg. Comp.</span>
                                <template v-if="formEditar.fecha_hora_fin_compradores">
                                    <span class="text-gray-400">→</span>
                                    <span class="px-2 py-0.5 bg-emerald-50 text-emerald-600 dark:text-emerald-400 rounded">Fin Reg. Comp.</span>
                                </template>
                                <span class="text-gray-400">→</span>
                                <span class="px-2 py-0.5 bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-300 rounded">Evento</span>
                                <span class="text-gray-400">→</span>
                                <span class="px-2 py-0.5 bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-400 rounded">Fin</span>
                            </div>
                        </div>
                        <div v-if="formEditar.tipo_evento === 'encuentro_negocios'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Máx. citas/comprador</label>
                                <input v-model.number="formEditar.max_citas_por_comprador" type="number" min="1" max="50"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Tiempo entre citas (min)</label>
                                <input v-model.number="formEditar.tiempo_entre_citas_minutos" type="number" min="5" max="120" step="5"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                <p class="text-xs text-gray-400 mt-0.5">Debe ser múltiplo de 5 (ej: 10, 15, 30)</p>
                            </div>
                        </div>

                        <!-- Tipo de evento -->
                        <div class="p-4 bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700 rounded-xl">
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3">Tipo de evento</p>
                            <div class="flex gap-2 flex-wrap">
                                <button type="button"
                                    @click="formEditar.tipo_evento = 'encuentro_negocios'"
                                    :class="formEditar.tipo_evento === 'encuentro_negocios'
                                        ? 'bg-guinda-800 text-white border-guinda-800'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700'"
                                    class="px-4 py-2 text-sm font-semibold rounded-xl border transition-colors flex items-center gap-2">
                                    Encuentro de negocios
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Con citas</span>
                                </button>
                                <button type="button"
                                    @click="formEditar.tipo_evento = 'bazar_exposicion'"
                                    :class="formEditar.tipo_evento === 'bazar_exposicion'
                                        ? 'bg-guinda-800 text-white border-guinda-800'
                                        : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-700'"
                                    class="px-4 py-2 text-sm font-semibold rounded-xl border transition-colors flex items-center gap-2">
                                    Exposición / Bazar
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-red-100 text-red-700">Sin citas</span>
                                </button>
                            </div>

                            <div v-if="formEditar.tipo_evento === 'bazar_exposicion'" class="mt-4">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Número máximo de espacios (N)</label>
                                <input v-model.number="formEditar.max_espacios" type="number" min="1" max="9999" placeholder="Ej: 12"
                                    class="w-40 text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                            </div>

                            <!-- Apertura de solicitudes (solo bazar) -->
                            <div v-if="formEditar.tipo_evento === 'bazar_exposicion'">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Apertura de solicitudes de expositores
                                    <span class="text-xs text-gray-400 font-normal ml-1">(opcional — si no se define, el registro abre inmediatamente)</span>
                                </label>
                                <input
                                    type="datetime-local"
                                    v-model="formEditar.fecha_aceptacion_solicitudes"
                                    class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500"
                                />
                                <p class="text-xs text-gray-400 mt-1">
                                    Los usuarios no podrán registrarse antes de esta fecha/hora.
                                </p>
                            </div>

                            <div v-if="formEditar.tipo_evento === 'bazar_exposicion'" class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <label class="flex items-center gap-2 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3">
                                    <input type="checkbox" v-model="formEditar.con_criterios_evaluacion" class="rounded" />
                                    Habilitar criterios de evaluación
                                </label>

                                <div v-if="formEditar.con_criterios_evaluacion" class="space-y-2">
                                    <div v-for="(c, i) in formEditar.criterios" :key="i" class="flex items-center gap-2">
                                        <input v-model="c.nombre" placeholder="Nombre del criterio"
                                            class="flex-1 text-sm bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                        <input v-model.number="c.porcentaje" type="number" min="1" max="100" placeholder="%"
                                            class="w-20 text-sm text-center bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors" />
                                        <span class="text-gray-400 text-sm">%</span>
                                        <button type="button" @click="formEditar.criterios.splice(i, 1)"
                                            class="p-1.5 text-red-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors">✕</button>
                                    </div>

                                    <button type="button" @click="formEditar.criterios.push({ nombre: '', porcentaje: 0 })"
                                        class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 hover:underline">
                                        + Agregar criterio
                                    </button>

                                    <div v-if="formEditar.criterios.length > 0" class="flex items-center gap-2 text-xs font-medium mt-2"
                                        :class="totalPorcentajeEditar === 100 ? 'text-emerald-600' : 'text-amber-600'">
                                        <span>Total: {{ totalPorcentajeEditar }}%</span>
                                        <span v-if="totalPorcentajeEditar === 100">✓ Completo</span>
                                        <span v-else>⚠ Debe sumar 100%</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Errores de validación -->
                        <div v-if="Object.keys(formEditar.errors).length" class="p-4 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/50 rounded-xl">
                            <p class="text-sm font-semibold text-red-700 dark:text-red-400 mb-2 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0l-7.1 12.25A2 2 0 004.99 19z" /></svg>
                                No se pudieron guardar los cambios. Revisa lo siguiente:
                            </p>
                            <ul class="list-disc list-inside space-y-1 text-sm text-red-600 dark:text-red-300">
                                <li v-for="(mensaje, campo) in formEditar.errors" :key="campo">{{ mensaje }}</li>
                            </ul>
                        </div>

                        <div class="flex items-center gap-3 pt-2">
                            <button @click="modalEditar = false"
                                class="flex-1 sm:flex-none px-5 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-xl transition-colors">
                                Cancelar
                            </button>
                            <button @click="guardarEdicion" :disabled="formEditar.processing"
                                class="flex-1 sm:flex-none px-5 py-2.5 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-sm font-bold rounded-xl transition-colors">
                                {{ formEditar.processing ? 'Guardando…' : 'Guardar cambios' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
