<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import esLocale from '@fullcalendar/core/locales/es';

const props = defineProps({
    restaurantero: Object,
    citas: Object,
    citasCalendario: Array,
    categorias: Array,
    eventosParticipados: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ total: 0, aceptadas: 0, pendientes: 0 }) },
});

const municipios = [
    'Mérida','Valladolid','Tizimín','Progreso','Motul','Ticul','Umán','Izamal',
    'Maxcanú','Hunucmá','Tekax','Chemax','Espita','Oxkutzcab','Peto','Temozón',
    'Baca','Bokobá','Cacalchén','Calotmul','Cantamayec','Celestún','Cenotillo',
    'Conkal','Cuncunul','Cuzamá','Chacsinkín','Chankom','Chapab','Chichimilá',
    'Chikindzonot','Chocholá','Chumayel','Dzan','Dzemul','Dzilam de Bravo',
    'Dzilam González','Dzitás','Dzoncauich','Halachó','Hocabá','Hoctún',
    'Homún','Huhí','Ichmul','Ixil','Kanasín','Kantunil','Kaua','Kinchil',
    'Kopomá','Mama','Maní','Mayapán','Mocochá','Molché','Muna','Muxupip',
    'Opichén','Panabá','Sanahcat','San Felipe','Santa Elena','Seyé','Sinanché',
    'Sotuta','Sucilá','Sudzal','Suma','Tahdziú','Tahmek','Teabo','Tecoh',
    'Tekal de Venegas','Tekantó','Tekit','Telchac Pueblo','Telchac Puerto',
    'Temax','Teoponte','Tetiz','Teya','Tixkokob','Tixméhuac','Tixpéhual',
    'Tunkás','Tzucacab','Uayma','Ucú','Xocchel','Yaxcabá','Yaxkukul','Yobaín',
];

const categoriaSeleccionada = ref(props.restaurantero.categoria || '');

const guardarCategoria = () => {
    router.patch(route('admin.restauranteros.update-categoria', props.restaurantero.id), {
        categoria: categoriaSeleccionada.value || null,
    });
};

// --- Formulario edición de perfil ---
const editForm = useForm({
    nombre_restaurante: props.restaurantero.nombre_restaurante || '',
    telefono:           props.restaurantero.telefono || '',
    direccion:          props.restaurantero.direccion || '',
    municipio:          props.restaurantero.municipio || '',
    descripcion:        props.restaurantero.descripcion || '',
    logo:               null,
});

const logoPreview = ref(
    props.restaurantero.logo_path ? '/storage/' + props.restaurantero.logo_path : null
);

const onLogoChange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    editForm.logo = file;
    logoPreview.value = URL.createObjectURL(file);
};

const submitPerfil = () => {
    editForm.post(route('admin.restauranteros.update', props.restaurantero.id));
};

const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

const estadoEventoClases = {
    aprobado:  'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
    pendiente: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    rechazado: 'bg-red-500/15 text-red-600 dark:text-red-400',
};

const estadoEventoLabel = { aprobado: 'Aprobado', pendiente: 'Pendiente', rechazado: 'Rechazado' };

const formatFechaEvento = (fecha) => {
    if (!fecha) return '—';
    return new Date(fecha).toLocaleDateString('es-MX', { year: 'numeric', month: 'short', day: 'numeric' });
};

const esEventoActivo = (ev) => {
    if (!ev.activa) return false;
    const fin = ev.fecha_hora_fin ? new Date(ev.fecha_hora_fin) : null;
    return fin ? fin >= new Date() : true;
};

const estadoClases = {
    pendiente:  'bg-guinda-500/15 text-guinda-600 dark:text-guinda-400 border-guinda-500/20',
    confirmada: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
    cancelada:  'bg-gray-500/15 text-gray-500 dark:text-gray-400 border-gray-500/20',
    completada: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
};

const calendarOptions = ref({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'timeGridWeek',
    locale: esLocale,
    headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay',
    },
    events: props.citasCalendario,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false },
    allDaySlot: false,
    slotMinTime: '08:00:00',
    slotMaxTime: '18:00:00',
    height: 'auto',
    eventColor: '#8b1028',
});
</script>

<template>
    <AdminLayout :title="restaurantero.nombre_restaurante">
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('admin.restauranteros.index')"
                    class="text-gray-400 dark:text-gray-500 hover:text-guinda-700 dark:hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </Link>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ restaurantero.nombre_restaurante }}</h1>
                <span :class="restaurantero.activo
                        ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20'
                        : 'bg-red-50 dark:bg-red-500/15 text-red-600 dark:text-red-400 border-red-200 dark:border-red-500/20'"
                    class="text-xs font-semibold px-2.5 py-1 rounded-full border">
                    {{ restaurantero.activo ? 'Activo' : 'Inactivo' }}
                </span>
                <span :class="restaurantero.user?.perfil_completo
                        ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20'
                        : 'bg-amber-50 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/20'"
                    class="text-xs font-semibold px-2.5 py-1 rounded-full border">
                    {{ restaurantero.user?.perfil_completo ? 'Perfil completo' : 'Perfil incompleto' }}
                </span>
                <div class="flex flex-wrap gap-2 mt-1">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border bg-gray-50 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700">
                        {{ stats.total }} citas
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20">
                        {{ stats.aceptadas }} confirmadas
                    </span>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold border bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-200 dark:border-amber-500/20">
                        {{ stats.pendientes }} pendientes
                    </span>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Aviso perfil incompleto -->
            <div v-if="!restaurantero.user?.perfil_completo"
                class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-amber-700 dark:text-amber-400 text-sm rounded-xl px-4 py-3">
                Este proveedor todavía no ha completado su perfil (nombre de empresa, cámara/asociación, etc.).
                No aparecerá en la sección pública "Explora los proveedores de la plataforma" hasta que lo complete.
            </div>

            <!-- Info cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 space-y-3 shadow-sm dark:shadow-none transition-colors animate-fadeInUp">
                    <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">Información general</h2>
                    <div class="text-sm space-y-2">
                        <div><span class="text-gray-500 dark:text-gray-500">Propietario:</span> <span class="text-gray-900 dark:text-gray-200 font-medium">{{ restaurantero.user?.name }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-500">Email:</span> <span class="text-gray-700 dark:text-gray-200">{{ restaurantero.user?.email }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-500">Teléfono:</span> <span class="text-gray-700 dark:text-gray-200">{{ restaurantero.telefono ?? '—' }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-500">Municipio:</span> <span class="text-gray-700 dark:text-gray-200">{{ restaurantero.municipio ?? '—' }}</span></div>
                        <div><span class="text-gray-500 dark:text-gray-500">Registrado:</span> <span class="text-gray-700 dark:text-gray-200">{{ new Date(restaurantero.created_at).toLocaleDateString('es-MX') }}</span></div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.1s">
                    <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-3">Servicios</h2>
                    <ul class="text-sm space-y-2">
                        <li v-for="s in restaurantero.servicios" :key="s.id" class="flex justify-between items-center">
                            <span class="text-gray-700 dark:text-gray-300">{{ s.nombre }}</span>
                            <span class="text-xs text-guinda-700 dark:text-guinda-400 font-medium">{{ s.duracion_minutos }} min</span>
                        </li>
                        <li v-if="!restaurantero.servicios?.length" class="text-gray-400 dark:text-gray-600">Sin servicios.</li>
                    </ul>
                </div>

                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.2s">
                    <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-3">Horarios</h2>
                    <ul class="text-sm space-y-2">
                        <li v-for="h in restaurantero.horarios" :key="h.id" class="flex justify-between">
                            <span class="text-gray-700 dark:text-gray-300 font-medium">{{ diasSemana[h.dia_semana] }}</span>
                            <span class="text-gray-500 dark:text-gray-500">{{ h.hora_inicio.slice(0, 5) }} – {{ h.hora_fin.slice(0, 5) }}</span>
                        </li>
                        <li v-if="!restaurantero.horarios?.length" class="text-gray-400 dark:text-gray-600">Sin horarios.</li>
                    </ul>
                </div>
            </div>

            <!-- Datos del Representante y Empresa -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.28s">
                <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-4">Datos del Representante y Empresa</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Razón social</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.razon_social || '—' }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Nombre del representante</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.nombre_representante || '—' }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">CURP del representante</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200 font-mono text-xs">{{ restaurantero.curp_representante || '—' }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">RFC</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200 font-mono text-xs">{{ restaurantero.rfc || '—' }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Fecha de inicio de operaciones</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">
                            {{ restaurantero.fecha_inicio_operaciones
                                ? new Date(restaurantero.fecha_inicio_operaciones).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' })
                                : '—' }}
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Número de empleados</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.num_empleados != null ? restaurantero.num_empleados + ' empleados' : '—' }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Domicilio en Yucatán</span>
                        <span v-if="restaurantero.domicilio_en_yucatan != null"
                            :class="restaurantero.domicilio_en_yucatan
                                ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400'"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold self-start">
                            {{ restaurantero.domicilio_en_yucatan ? 'Sí' : 'No' }}
                        </span>
                        <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Sitio web</span>
                        <a v-if="restaurantero.sitio_web" :href="restaurantero.sitio_web" target="_blank"
                            class="text-guinda-700 dark:text-guinda-400 underline text-xs truncate">
                            {{ restaurantero.sitio_web.replace(/^https?:\/\//, '') }}
                        </a>
                        <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                    </div>
                    <div class="flex flex-col sm:col-span-2">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Redes sociales</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">
                            {{ restaurantero.redes_sociales?.length ? restaurantero.redes_sociales.join(', ') : '—' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Características del Producto/Servicio -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.3s">
                <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-4">Características del Producto / Servicio</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Mercado meta</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.mercado_meta || '—' }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Tiempo de vida en anaquel</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.tiempo_vida_anaquel || '—' }}</span>
                    </div>
                    <div class="flex flex-col sm:col-span-2">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Requisitos alimentarios</span>
                        <span class="font-medium text-gray-800 dark:text-gray-200">
                            {{ restaurantero.requisitos_alimentos?.length ? restaurantero.requisitos_alimentos.join(', ') : '—' }}
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Apoyo en trámites de requisitos</span>
                        <span v-if="restaurantero.apoyo_requisitos != null"
                            :class="restaurantero.apoyo_requisitos?.length
                                ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold self-start">
                            {{ restaurantero.apoyo_requisitos?.length ? 'Sí' : 'No' }}
                        </span>
                        <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Requiere refrigeración</span>
                        <span v-if="restaurantero.requiere_refrigeracion != null"
                            :class="restaurantero.requiere_refrigeracion
                                ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold self-start">
                            {{ restaurantero.requiere_refrigeracion ? 'Sí' : 'No' }}
                        </span>
                        <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Requiere congelación</span>
                        <span v-if="restaurantero.requiere_congelacion != null"
                            :class="restaurantero.requiere_congelacion
                                ? 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold self-start">
                            {{ restaurantero.requiere_congelacion ? 'Sí' : 'No' }}
                        </span>
                        <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                    </div>
                </div>
            </div>

            <!-- Participación en Eventos -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.32s">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                    <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">Participación en Eventos</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                        {{ eventosParticipados.length }} eventos
                    </span>
                </div>
                <div v-if="eventosParticipados.length > 0" class="p-4 space-y-3">
                    <div v-for="ev in eventosParticipados" :key="ev.id"
                        class="bg-gray-50 dark:bg-gray-800/40 rounded-xl p-4 flex flex-wrap gap-3 items-start justify-between">
                        <div>
                            <p class="font-semibold text-gray-900 dark:text-white text-sm mb-1">{{ ev.nombre }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                {{ formatFechaEvento(ev.fecha_hora_inicio) }}
                                <span v-if="ev.fecha_hora_fin"> — {{ formatFechaEvento(ev.fecha_hora_fin) }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
                                Proveedor
                            </span>
                            <span :class="estadoEventoClases[ev.estado] ?? 'bg-gray-100 dark:bg-gray-700 text-gray-500'"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                {{ estadoEventoLabel[ev.estado] ?? ev.estado }}
                            </span>
                            <span :class="esEventoActivo(ev)
                                ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold">
                                {{ esEventoActivo(ev) ? 'Activo' : 'Finalizado' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div v-else class="px-6 py-10 text-center text-gray-400 dark:text-gray-600">
                    <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z"/>
                    </svg>
                    <p class="text-sm">No ha participado en ningún evento</p>
                </div>
            </div>

            <!-- Productos Top -->
            <div v-if="restaurantero.productos_top && restaurantero.productos_top.length > 0"
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.25s">
                <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-4">Productos / Servicios destacados</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="(prod, i) in restaurantero.productos_top" :key="i"
                        class="border border-gray-100 dark:border-gray-800 rounded-xl overflow-hidden">
                        <div v-if="prod.foto_path" class="w-full h-36 overflow-hidden bg-gray-100 dark:bg-gray-800">
                            <img :src="`/storage/${prod.foto_path}`" class="w-full h-full object-cover" />
                        </div>
                        <div v-else class="w-full h-20 bg-guinda-50 dark:bg-guinda-950/20 flex items-center justify-center">
                            <svg class="w-8 h-8 text-guinda-300 dark:text-guinda-800" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909"/>
                            </svg>
                        </div>
                        <div class="p-3">
                            <p class="font-semibold text-sm text-gray-900 dark:text-white">
                                {{ typeof prod === 'string' ? prod : prod.nombre }}
                            </p>
                            <p v-if="prod.descripcion" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ prod.descripcion }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categoría -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.3s">
                <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-3">Categoría del proveedor</h2>
                <div class="flex items-end gap-3">
                    <div class="flex-1">
                        <select
                            v-model="categoriaSeleccionada"
                            class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors"
                        >
                            <option value="">Sin categoría</option>
                            <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>
                    <button
                        @click="guardarCategoria"
                        class="px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-lg transition-colors whitespace-nowrap"
                    >
                        Guardar
                    </button>
                </div>
            </div>

            <!-- Editar perfil -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors animate-fadeInUp" style="animation-delay:0.35s">
                <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-4">Editar perfil del proveedor</h2>
                <form @submit.prevent="submitPerfil" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Logo -->
                        <div class="md:col-span-2 flex items-start gap-5">
                            <div class="shrink-0">
                                <div class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 dark:border-gray-600 overflow-hidden bg-gray-50 dark:bg-gray-800 flex items-center justify-center">
                                    <img v-if="logoPreview" :src="logoPreview" class="w-full h-full object-cover" alt="Logo" />
                                    <svg v-else class="w-8 h-8 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Logo / Foto</label>
                                <input type="file" accept="image/*" @change="onLogoChange"
                                    class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-guinda-50 dark:file:bg-guinda-900/30 file:text-guinda-700 dark:file:text-guinda-400 hover:file:bg-guinda-100 dark:hover:file:bg-guinda-900/50 cursor-pointer" />
                                <p class="text-xs text-gray-400 dark:text-gray-600 mt-1">JPG, PNG o WebP · máx. 2 MB</p>
                                <p v-if="editForm.errors.logo" class="text-xs text-red-500 mt-1">{{ editForm.errors.logo }}</p>
                            </div>
                        </div>

                        <!-- Nombre del negocio -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del negocio</label>
                            <input v-model="editForm.nombre_restaurante" type="text"
                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border rounded-xl px-3 py-2.5 focus:outline-none transition-colors"
                                :class="editForm.errors.nombre_restaurante ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700 focus:border-guinda-500 dark:focus:border-guinda-500'" />
                            <p v-if="editForm.errors.nombre_restaurante" class="text-xs text-red-500 mt-1">{{ editForm.errors.nombre_restaurante }}</p>
                        </div>

                        <!-- Descripción -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
                            <textarea v-model="editForm.descripcion" rows="4"
                                placeholder="Describe el negocio, servicios o productos que ofrece..."
                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors resize-none" />
                        </div>

                        <!-- Teléfono -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
                            <input v-model="editForm.telefono" type="tel" maxlength="10" placeholder="9991234567"
                                @input="editForm.telefono = editForm.telefono.replace(/\D/g, '').slice(0, 10)"
                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors" />
                        </div>

                        <!-- Municipio -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Municipio</label>
                            <select v-model="editForm.municipio"
                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors">
                                <option value="">— Seleccionar —</option>
                                <option v-for="mun in municipios" :key="mun" :value="mun">{{ mun }}</option>
                            </select>
                        </div>

                        <!-- Dirección -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
                            <input v-model="editForm.direccion" type="text" placeholder="Calle y número"
                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors" />
                        </div>
                    </div>

                    <div class="flex justify-end pt-2 border-t border-gray-100 dark:border-gray-800">
                        <button type="submit" :disabled="editForm.processing"
                            class="px-5 py-2 text-sm font-semibold text-white bg-guinda-800 hover:bg-guinda-700 disabled:opacity-60 rounded-xl transition-colors">
                            {{ editForm.processing ? 'Guardando...' : 'Guardar cambios' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Calendar -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none transition-colors">
                <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider mb-4">Calendario de citas</h2>
                <FullCalendar :options="calendarOptions" />
            </div>

            <!-- Citas table -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/30">
                    <h2 class="font-semibold text-gray-500 dark:text-gray-400 text-xs uppercase tracking-wider">Historial de citas</h2>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                            <th class="text-left px-5 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Comprador</th>
                            <th class="text-left px-5 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden md:table-cell">Servicio</th>
                            <th class="text-left px-5 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden lg:table-cell">Evento</th>
                            <th class="text-left px-5 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Inicio</th>
                            <th class="text-left px-5 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden xl:table-cell">Fin</th>
                            <th class="text-center px-5 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in citas.data" :key="c.id"
                            class="border-b border-gray-100 dark:border-gray-800/40 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors duration-150">
                            <td class="px-5 py-4">
                                <div class="font-medium text-gray-900 dark:text-gray-200">{{ c.cliente?.name }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-500">{{ c.cliente?.email }}</div>
                            </td>
                            <td class="px-5 py-4 text-gray-500 dark:text-gray-400 hidden md:table-cell text-sm">{{ c.servicio?.nombre }}</td>
                            <td class="px-5 py-4 hidden lg:table-cell">
                                <span v-if="c.evento" class="text-xs text-guinda-700 dark:text-guinda-400 font-medium">{{ c.evento.nombre }}</span>
                                <span v-else class="text-gray-400 dark:text-gray-600">—</span>
                            </td>
                            <td class="px-5 py-4 text-gray-500 dark:text-gray-400 text-xs whitespace-nowrap">{{ new Date(c.inicio).toLocaleString('es-MX') }}</td>
                            <td class="px-5 py-4 text-gray-500 dark:text-gray-400 hidden xl:table-cell text-xs whitespace-nowrap">{{ new Date(c.fin).toLocaleString('es-MX') }}</td>
                            <td class="px-5 py-4 text-center">
                                <span :class="estadoClases[c.estado]" class="text-xs font-semibold px-2.5 py-1 rounded-full border capitalize">
                                    {{ c.estado }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!citas.data.length">
                            <td colspan="6" class="px-6 py-16 text-center text-gray-400 dark:text-gray-600">Sin citas registradas.</td>
                        </tr>
                    </tbody>
                </table>

                <div v-if="citas.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-800 flex justify-end gap-2">
                    <Link v-for="link in citas.links" :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1.5 text-xs rounded-lg transition-colors',
                            link.active ? 'bg-guinda-800 text-white font-semibold' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700',
                            !link.url ? 'opacity-40 cursor-not-allowed' : ''
                        ]" />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style>
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fadeInUp { animation: fadeInUp 0.5s ease forwards; opacity: 0; }
/* Light mode FC */
.fc { --fc-border-color: #e5e7eb; --fc-today-bg-color: rgba(139,16,40,0.04); }
.fc-theme-standard td, .fc-theme-standard th { border-color: #e5e7eb; }
.fc-col-header-cell-cushion, .fc-timegrid-axis-cushion, .fc-timegrid-slot-label-cushion { color: #6b7280; }
.fc-toolbar-title { color: #111827 !important; font-size: 1rem !important; font-weight: 700 !important; }
.fc-button { background: #f3f4f6 !important; border-color: #d1d5db !important; color: #374151 !important; font-size: 0.75rem !important; }
.fc-button-active, .fc-button:hover { background: #8b1028 !important; border-color: #710d21 !important; color: #fff !important; }
.fc-event-title, .fc-event-time { color: #fff !important; }
.fc-scrollgrid { border-color: #e5e7eb !important; }
/* Dark mode FC */
.dark .fc { --fc-border-color: #1f2937; --fc-today-bg-color: rgba(139,16,40,0.08); }
.dark .fc-theme-standard td, .dark .fc-theme-standard th { border-color: #1f2937; }
.dark .fc-toolbar-title { color: #f9fafb !important; }
.dark .fc-button { background: #374151 !important; border-color: #4b5563 !important; color: #d1d5db !important; }
.dark .fc-button-active, .dark .fc-button:hover { background: #8b1028 !important; border-color: #710d21 !important; color: #fff !important; }
.dark .fc-scrollgrid { border-color: #1f2937 !important; }
</style>
