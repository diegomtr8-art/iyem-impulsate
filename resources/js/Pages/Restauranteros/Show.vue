<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const props = defineProps({
    restaurantero: Object,
    citasCount: Number,
    citasOcupadas: Array,
    evento: Object,
});

const loginUrl = computed(() => route('login') + '?redirect=' + encodeURIComponent(window.location.pathname));
const registerUrl = computed(() => route('register'));

const page = usePage();
const auth = computed(() => page.props.auth);
const isAdmin = computed(() => auth.value.user?.is_admin ?? false);
const maxCitas = computed(() => props.evento?.max_citas_por_comprador ?? 3);

// Redirige al panel correcto según el rol activo del usuario
const panelRoute = computed(() => {
    const user = auth.value.user;
    if (!user) return route('login');
    if (user.is_admin) return route('admin.dashboard');
    if (user.active_role === 'proveedor' || (user.is_restaurantero && !user.is_cliente)) {
        return route('restaurantero.panel');
    }
    return route('user.dashboard');
});
const limiteAlcanzado = computed(() => auth.value.user && props.citasCount >= maxCitas.value);

// ── DÍAS DEL EVENTO PRESENCIAL ─────────────────────────────────────────────
const diasEvento = computed(() => {
    if (!props.evento?.fecha_hora_inicio) return [];
    // Si el evento dura más de un día, listar todos los días del rango
    const inicio = new Date(props.evento.fecha_hora_inicio);
    inicio.setHours(0, 0, 0, 0);
    const fin = props.evento.fecha_hora_fin
        ? new Date(props.evento.fecha_hora_fin)
        : new Date(inicio);
    fin.setHours(23, 59, 59, 999);

    const dias = [];
    const cursor = new Date(inicio);
    while (cursor <= fin) {
        dias.push(new Date(cursor));
        cursor.setDate(cursor.getDate() + 1);
    }
    return dias;
});

// ── RANGO DE FECHAS DEL EVENTO ──────────────────────────────────────────────
const edicionInicioAgenda = computed(() => {
    if (props.evento?.fecha_hora_inicio) {
        const d = new Date(props.evento.fecha_hora_inicio);
        d.setHours(0, 0, 0, 0);
        return d;
    }
    return props.evento?.fecha_inicio_agenda
        ? new Date(props.evento.fecha_inicio_agenda + 'T00:00:00') : null;
});
const edicionFinAgenda = computed(() => {
    if (props.evento?.fecha_hora_fin) {
        const d = new Date(props.evento.fecha_hora_fin);
        return d;
    }
    return props.evento?.fecha_fin_agenda
        ? new Date(props.evento.fecha_fin_agenda + 'T23:59:59') : null;
});

function esFueraDeRangoEdicion(date) {
    if (diasEvento.value.length > 0) {
        // Solo permitir los días exactos del evento
        const dKey = formatFechaKey(date);
        return !diasEvento.value.some(d => formatFechaKey(d) === dKey);
    }
    // Fallback original
    const d = new Date(date); d.setHours(0, 0, 0, 0);
    if (edicionInicioAgenda.value && d < edicionInicioAgenda.value) return true;
    if (edicionFinAgenda.value    && d > edicionFinAgenda.value)    return true;
    return false;
}

const puedeAvanzarSemana = computed(() => {
    if (!edicionFinAgenda.value) return true;
    const nextMonday = semana.value[0] ? new Date(semana.value[4]) : null;
    if (!nextMonday) return true;
    nextMonday.setDate(nextMonday.getDate() + 7);
    nextMonday.setHours(0, 0, 0, 0);
    return nextMonday <= edicionFinAgenda.value;
});

const puedeRetrocederSemana = computed(() => weekOffset.value > 0);

// ── SEMANA ─────────────────────────────────────────────────────────────────
const weekOffset = ref(0);

const intervalo = computed(() => {
    const t = props.evento?.tiempo_entre_citas_minutos ?? 30;
    return Math.max(5, Math.round(t / 5) * 5);
});

const timeSlots = computed(() => {
    const slots = [];
    // Usar horas del evento presencial si están disponibles
    let inicioMin = 9 * 60;  // fallback: 09:00
    let finMin    = 16 * 60; // fallback: 16:00
    if (props.evento?.fecha_hora_inicio) {
        const partes = props.evento.fecha_hora_inicio.split(' ');
        if (partes[1]) {
            const [h, m] = partes[1].split(':').map(Number);
            inicioMin = h * 60 + (m || 0);
        }
    }
    if (props.evento?.fecha_hora_fin) {
        const partes = props.evento.fecha_hora_fin.split(' ');
        if (partes[1]) {
            const [h, m] = partes[1].split(':').map(Number);
            finMin = h * 60 + (m || 0);
        }
    }
    const paso = intervalo.value;
    for (let min = inicioMin; min < finMin; min += paso) {
        const hh = String(Math.floor(min / 60)).padStart(2, '0');
        const mm = String(min % 60).padStart(2, '0');
        slots.push(`${hh}:${mm}`);
    }
    return slots;
});

const duracionTexto = computed(() => {
    return 'Sin costo · Gobierno de Yucatán';
});

const diasNombre = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie'];
function nombreDia(d) {
    return d.toLocaleDateString('es-MX', { weekday: 'short' }).replace(/^./, c => c.toUpperCase());
}

function padZero(n) { return String(n).padStart(2, '0'); }
function formatFechaKey(d) { return `${d.getFullYear()}-${padZero(d.getMonth()+1)}-${padZero(d.getDate())}`; }

const semana = computed(() => {
    // Si hay días de evento definidos, mostrar solo esos días
    if (diasEvento.value.length > 0) return diasEvento.value;
    // Fallback: semana actual
    const hoy = new Date();
    const dow = hoy.getDay();
    const monday = new Date(hoy);
    monday.setDate(hoy.getDate() + (dow === 0 ? -6 : 1 - dow) + weekOffset.value * 7);
    monday.setHours(0, 0, 0, 0);
    return Array.from({length: 5}, (_, i) => {
        const d = new Date(monday); d.setDate(monday.getDate() + i); return d;
    });
});

const semanaLabel = computed(() => {
    if (diasEvento.value.length === 1) {
        const d = diasEvento.value[0];
        return d.toLocaleDateString('es-MX', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }
    if (!semana.value.length) return '';
    const ini = semana.value[0];
    const fin = semana.value[semana.value.length - 1];
    return `${ini.toLocaleDateString('es-MX', {day:'numeric',month:'short'})} — ${fin.toLocaleDateString('es-MX', {day:'numeric',month:'short',year:'numeric'})}`;
});

function esOcupado(date, time) {
    const key = `${formatFechaKey(date)} ${time}`;
    return props.citasOcupadas?.some(c => c.inicio === key) ?? false;
}

function esPasado(date, time) {
    const [h, m] = time.split(':').map(Number);
    const d = new Date(date); d.setHours(h, m, 0, 0);
    return d <= new Date();
}

// ── MODAL PRODUCTO ─────────────────────────────────────────────────────────
const modalProducto = ref(false);
const productoActivo = ref(null);

function abrirProductoModal(prod) {
    productoActivo.value = prod;
    modalProducto.value = true;
    document.body.style.overflow = 'hidden';
}

function cerrarProductoModal() {
    modalProducto.value = false;
    productoActivo.value = null;
    document.body.style.overflow = '';
}

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            cerrarProductoModal();
            cerrarModal();
        }
    });
}

// ── MODAL ──────────────────────────────────────────────────────────────────
const slotSeleccionado = ref(null);
const mostrarModal = ref(false);

function seleccionarSlot(date, time) {
    if (esOcupado(date, time) || esPasado(date, time) || esFueraDeRangoEdicion(date) || !auth.value.user || isAdmin.value || limiteAlcanzado.value) return;
    slotSeleccionado.value = { date, time };
    form.fecha = formatFechaKey(date);
    form.hora = time;
    mostrarModal.value = true;
}

const slotLegible = computed(() => {
    if (!slotSeleccionado.value) return '';
    const d = slotSeleccionado.value.date;
    return `${d.toLocaleDateString('es-MX', {weekday:'long',day:'numeric',month:'long'})} a las ${slotSeleccionado.value.time}`;
});

// ── FORMULARIO ────────────────────────────────────────────────────────────
const form = useForm({ restaurantero_id: props.restaurantero.id, fecha: '', hora: '', notas: '', productos_interes: [] });

function cerrarModal() {
    mostrarModal.value = false;
    slotSeleccionado.value = null;
    form.reset('notas', 'productos_interes');
}

const submit = () => form.post(route('citas.store'), {
    onSuccess: () => {
        mostrarModal.value = false;
        slotSeleccionado.value = null;
        form.reset('notas', 'productos_interes');
    },
});

// ── PRODUCTOS DE INTERÉS ──────────────────────────────────────────────────
const nombreProducto = (p) => (typeof p === 'string' ? p : p.nombre);

const toggleProducto = (nombre) => {
    const idx = form.productos_interes.indexOf(nombre);
    if (idx === -1) {
        form.productos_interes.push(nombre);
    } else {
        form.productos_interes.splice(idx, 1);
    }
};

const seleccionarTodosProductos = () => {
    form.productos_interes = (props.restaurantero.productos_top || []).map(nombreProducto);
};
</script>

<template>
    <Head :title="restaurantero.nombre_restaurante + ' — Impulsate'" />

    <!-- MODAL PRODUCTO -->
    <Teleport to="body">
        <Transition name="producto-modal">
            <div v-if="modalProducto && productoActivo" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="cerrarProductoModal"></div>
                <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
                    <!-- Imagen -->
                    <div v-if="productoActivo.foto_path" class="w-full h-64 sm:h-80 overflow-hidden bg-gray-100 dark:bg-gray-800 shrink-0 group">
                        <img :src="'/storage/' + productoActivo.foto_path"
                             :alt="typeof productoActivo === 'string' ? productoActivo : productoActivo.nombre"
                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" />
                    </div>
                    <div v-else class="w-full h-40 bg-guinda-50 dark:bg-guinda-950/20 flex items-center justify-center shrink-0">
                        <svg class="w-14 h-14 text-guinda-300 dark:text-guinda-800" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909"/>
                        </svg>
                    </div>
                    <!-- Contenido -->
                    <div class="p-6 overflow-y-auto flex-1">
                        <div class="flex items-start justify-between gap-4 mb-4">
                            <h2 class="text-xl font-black text-gray-900 dark:text-white leading-tight">
                                {{ typeof productoActivo === 'string' ? productoActivo : (productoActivo.nombre || '—') }}
                            </h2>
                            <button @click="cerrarProductoModal"
                                class="shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="productoActivo.descripcion" class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed whitespace-pre-line">
                            {{ productoActivo.descripcion }}
                        </p>
                        <div v-if="productoActivo.capacidad_cantidad"
                             class="mt-4 inline-flex items-center gap-1.5
                                    bg-guinda-50 dark:bg-guinda-500/10
                                    border border-guinda-200 dark:border-guinda-500/20
                                    text-guinda-700 dark:text-guinda-400
                                    text-xs font-semibold px-3 py-1.5 rounded-full">
                            📦 Capacidad producción:
                            {{ Number(productoActivo.capacidad_cantidad).toLocaleString('es-MX') }}
                            {{ productoActivo.capacidad_unidad }}/mes
                        </div>
                        <p v-if="productoActivo.precio" class="mt-4 text-lg font-bold text-guinda-700 dark:text-guinda-400">
                            {{ productoActivo.precio }}
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>

    <!-- MODAL -->
    <Teleport to="body">
        <Transition name="modal-fade">
            <div v-if="mostrarModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="cerrarModal"></div>
                <div class="relative bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl w-full max-w-md shadow-2xl max-h-[92vh] flex flex-col">
                    <!-- Header fijo -->
                    <div class="flex items-center justify-between px-6 pt-6 pb-0 shrink-0">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Mesa de Networking</h3>
                            <p class="text-xs text-guinda-600 dark:text-guinda-400 mt-0.5">{{ duracionTexto }}</p>
                        </div>
                        <button @click="cerrarModal" class="text-gray-400 hover:text-gray-700 dark:hover:text-white transition-colors p-1">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <!-- Contenido scrollable -->
                    <div class="overflow-y-auto flex-1 px-6 pt-4 pb-6">
                    <div class="bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 rounded-xl px-4 py-3 mb-5">
                        <p class="text-guinda-700 dark:text-guinda-400 font-semibold text-sm capitalize">📅 {{ slotLegible }}</p>
                        <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">📍 Av. Industrias No Contaminantes Tab 13613, Col. Sodzil Norte, Mérida</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <!-- Productos de interés -->
                        <div v-if="restaurantero.productos_top && restaurantero.productos_top.length > 0">
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
                                ¿En qué productos estás interesado?
                                <span class="text-xs font-normal text-gray-400 ml-1">(Opcional — puedes seleccionar varios)</span>
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="producto in restaurantero.productos_top"
                                    :key="nombreProducto(producto)"
                                    type="button"
                                    @click="toggleProducto(nombreProducto(producto))"
                                    :class="[
                                        'px-3 py-1.5 rounded-full text-sm font-medium border transition-all',
                                        form.productos_interes.includes(nombreProducto(producto))
                                            ? 'bg-guinda-700 text-white border-guinda-700 dark:bg-guinda-600 dark:border-guinda-600'
                                            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:border-guinda-400 dark:hover:border-guinda-500'
                                    ]"
                                >
                                    {{ nombreProducto(producto) }}
                                </button>
                            </div>
                            <div v-if="restaurantero.productos_top.length > 2" class="mt-2 flex gap-3">
                                <button type="button" @click="seleccionarTodosProductos"
                                        class="text-xs text-guinda-600 dark:text-guinda-400 hover:underline">
                                    Seleccionar todos
                                </button>
                                <button type="button" @click="form.productos_interes = []"
                                        v-if="form.productos_interes.length > 0"
                                        class="text-xs text-gray-400 dark:text-gray-500 hover:underline">
                                    Limpiar selección
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">¿Qué buscas en esta reunión?</label>
                            <textarea v-model="form.notas" rows="3"
                                placeholder="Describe tu objetivo para este networking..."
                                class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors resize-none">
                            </textarea>
                            <p v-if="form.errors.notas" class="text-red-500 text-xs mt-1">{{ form.errors.notas }}</p>
                        </div>
                        <div v-if="form.errors.limit || form.errors.servicio || form.errors.fecha || form.errors.edicion || form.errors.error"
                            class="bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800/50 text-red-600 dark:text-red-400 text-xs rounded-lg px-3 py-2">
                            {{ form.errors.limit || form.errors.servicio || form.errors.fecha || form.errors.edicion || form.errors.error }}
                        </div>
                        <div class="flex gap-3">
                            <button type="button" @click="cerrarModal"
                                class="flex-1 py-2.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-medium rounded-xl transition-colors text-sm">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing"
                                class="flex-1 py-2.5 bg-guinda-700 hover:bg-guinda-600 disabled:opacity-50 text-white font-bold rounded-xl transition-colors text-sm">
                                {{ form.processing ? 'Confirmando...' : 'Confirmar cita' }}
                            </button>
                        </div>
                    </form>
                    </div><!-- /contenido scrollable -->
                </div>
            </div>
        </Transition>
    </Teleport>

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white transition-colors duration-300">

        <!-- NAVBAR -->
        <nav class="sticky top-0 z-50 bg-white/95 dark:bg-gray-950/90 backdrop-blur border-b border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-none transition-colors">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
                <Link :href="route('home')" class="flex items-center shrink-0">
                    <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-9 w-auto" />
                </Link>

                <div class="flex items-center gap-3">
                    <Link :href="route('proveedores.index')"
                          class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-700 dark:hover:text-white transition-colors hidden sm:inline">
                        ← Proveedores
                    </Link>
                    <ThemeToggle />
                    <template v-if="auth.user">
                        <Link :href="panelRoute"
                              class="text-sm bg-guinda-800 hover:bg-guinda-700 text-white px-4 py-1.5 rounded-lg transition-colors font-semibold shadow-sm">
                            Mi Panel
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="loginUrl"
                              class="text-sm text-gray-600 dark:text-gray-300 hover:text-guinda-700 dark:hover:text-white transition-colors">
                            Iniciar sesión
                        </Link>
                        <Link :href="registerUrl"
                              class="text-sm bg-guinda-800 hover:bg-guinda-700 text-white px-4 py-1.5 rounded-lg transition-colors font-semibold shadow-sm">
                            Registrarse
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8">

            <!-- Breadcrumb móvil -->
            <Link :href="route('proveedores.index')"
                  class="sm:hidden inline-flex items-center gap-1.5 text-sm text-guinda-700 dark:text-guinda-400 hover:text-guinda-600 dark:hover:text-guinda-300 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                Volver a Proveedores
            </Link>

            <!-- Perfil + Sidebar -->
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">

                <!-- Columna izquierda: foto + info -->
                <div class="lg:col-span-3 space-y-6">

                    <!-- Foto -->
                    <div class="aspect-video rounded-2xl overflow-hidden bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 flex items-center justify-center shadow-sm dark:shadow-none">
                        <img v-if="restaurantero.logo_path"
                             :src="'/storage/' + restaurantero.logo_path"
                             :alt="restaurantero.nombre_restaurante"
                             class="w-full h-full object-cover" />
                        <svg v-else class="w-20 h-20 text-gray-300 dark:text-gray-700" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>

                    <!-- Nombre + descripción + contacto -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none">
                        <div class="flex flex-wrap gap-2 mb-3">
                            <span v-if="restaurantero.municipio"
                                  class="inline-flex items-center gap-1 text-xs font-medium bg-guinda-50 dark:bg-guinda-500/10 text-guinda-700 dark:text-guinda-400 border border-guinda-200 dark:border-guinda-500/20 px-3 py-1 rounded-full">
                                📍 {{ restaurantero.municipio }}, Yucatán
                            </span>
                            <span v-if="restaurantero.categoria"
                                  class="inline-flex items-center gap-1 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 px-3 py-1 rounded-full">
                                {{ restaurantero.categoria }}
                            </span>
                        </div>

                        <h1 class="text-2xl sm:text-3xl font-black text-gray-900 dark:text-white mb-3">
                            {{ restaurantero.nombre_restaurante }}
                        </h1>

                        <p v-if="restaurantero.descripcion" class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed mb-5">
                            {{ restaurantero.descripcion }}
                        </p>

                        <!-- Productos / Servicios Top -->
                        <div v-if="restaurantero.productos_top && restaurantero.productos_top.length > 0" class="mb-5">
                            <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Productos / Servicios</h3>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <div v-for="(prod, i) in restaurantero.productos_top" :key="i"
                                    @click="abrirProductoModal(prod)"
                                    class="border border-gray-100 dark:border-gray-800 rounded-xl overflow-hidden bg-white dark:bg-gray-900 shadow-sm cursor-pointer hover:border-guinda-300 dark:hover:border-guinda-500/40 hover:shadow-md transition-all duration-200 group/prod">
                                    <div v-if="prod.foto_path" class="w-full h-28 overflow-hidden bg-gray-100 dark:bg-gray-800">
                                        <img :src="'/storage/' + prod.foto_path" class="w-full h-full object-cover"
                                             :alt="typeof prod === 'string' ? prod : prod.nombre" />
                                    </div>
                                    <div v-else class="w-full h-16 bg-guinda-50 dark:bg-guinda-950/20 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-guinda-300 dark:text-guinda-800" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909"/>
                                        </svg>
                                    </div>
                                    <div class="p-2.5">
                                        <p class="font-bold text-xs text-gray-900 dark:text-white">
                                            {{ typeof prod === 'string' ? prod : (prod.nombre || '—') }}
                                        </p>
                                        <p v-if="prod.descripcion" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ prod.descripcion }}</p>
                                        <p v-if="prod.capacidad_cantidad"
                                           class="text-xs text-guinda-600 dark:text-guinda-400 font-semibold mt-1">
                                            📦 {{ Number(prod.capacidad_cantidad).toLocaleString('es-MX') }} {{ prod.capacidad_unidad }}/mes
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Condiciones comerciales -->
                        <div v-if="restaurantero.acepta_credito || restaurantero.pago_contraentrega || restaurantero.factura" class="mb-5">
                            <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Condiciones comerciales</h3>
                            <div class="flex flex-wrap gap-2">
                                <span v-if="restaurantero.acepta_credito"
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                                    ✓ Acepta crédito
                                    <template v-if="restaurantero.credito_a_negociar">
                                        <span class="opacity-70 ml-0.5">· A negociar</span>
                                    </template>
                                    <template v-else>
                                        <span v-if="restaurantero.credito_monto_maximo" class="opacity-70 ml-0.5">· máx. ${{ Number(restaurantero.credito_monto_maximo).toLocaleString('es-MX') }}</span>
                                        <span v-if="restaurantero.credito_tiempo_cantidad && restaurantero.credito_tiempo_unidad" class="opacity-70">· {{ restaurantero.credito_tiempo_cantidad }} {{ restaurantero.credito_tiempo_unidad }}</span>
                                    </template>
                                </span>
                                <span v-if="restaurantero.pago_contraentrega"
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                                    ✓ Pago contraentrega
                                </span>
                                <span v-if="restaurantero.factura"
                                    class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20">
                                    ✓ Emite factura
                                    <span v-if="restaurantero.regimen_fiscal" class="opacity-70 ml-0.5">· {{ restaurantero.regimen_fiscal }}</span>
                                </span>
                            </div>
                        </div>

                        <!-- Logística y Distribución -->
                        <div v-if="restaurantero.entrega_domicilio !== null && restaurantero.entrega_domicilio !== undefined"
                             class="border-t border-gray-100 dark:border-gray-800 pt-4 mb-5">
                            <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">
                                Logística y Distribución
                            </h3>
                            <div class="flex flex-wrap gap-2">
                                <span v-if="restaurantero.entrega_domicilio"
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold
                                           bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400
                                           border border-emerald-200 dark:border-emerald-500/20">
                                    🚚 Entrega a domicilio
                                    <span v-if="restaurantero.cobertura_entrega" class="opacity-70 capitalize">
                                        · {{ restaurantero.cobertura_entrega }}
                                    </span>
                                    <span v-if="restaurantero.forma_entrega" class="opacity-70 capitalize">
                                        · {{ restaurantero.forma_entrega }}
                                    </span>
                                </span>
                                <span v-else
                                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold
                                           bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400
                                           border border-gray-200 dark:border-gray-700">
                                    📦 Sin entrega a domicilio
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2.5 text-sm border-t border-gray-100 dark:border-gray-800 pt-4">
                            <div v-if="restaurantero.user?.email" class="flex items-center gap-2.5 text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4 text-guinda-500 dark:text-guinda-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                {{ restaurantero.user.email }}
                            </div>
                            <div v-if="restaurantero.telefono" class="flex items-center gap-2.5 text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4 text-guinda-500 dark:text-guinda-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 002.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 01-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 00-1.091-.852H4.5A2.25 2.25 0 002.25 4.5v2.25z" /></svg>
                                {{ restaurantero.telefono }}
                            </div>
                            <div v-if="restaurantero.rfc" class="flex items-center gap-2.5 text-gray-500 dark:text-gray-400">
                                <svg class="w-4 h-4 text-guinda-500 dark:text-guinda-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                                <span>RFC: <span class="font-mono text-xs text-gray-700 dark:text-gray-200">{{ restaurantero.rfc }}</span></span>
                            </div>
                            <a v-if="restaurantero.sitio_web" :href="restaurantero.sitio_web" target="_blank" rel="noopener"
                               class="flex items-center gap-2.5 text-guinda-700 dark:text-guinda-400 hover:text-guinda-600 dark:hover:text-guinda-300 transition-colors">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                                <span class="text-xs truncate">{{ restaurantero.sitio_web.replace(/^https?:\/\//, '') }}</span>
                            </a>
                        </div>
                    </div>

                    <!-- Datos del Representante y Empresa -->
                    <div v-if="restaurantero.razon_social || restaurantero.nombre_representante || restaurantero.curp_representante || restaurantero.rfc || restaurantero.fecha_inicio_operaciones || restaurantero.num_empleados != null || restaurantero.domicilio_en_yucatan != null || restaurantero.redes_sociales?.length"
                         class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none">
                        <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                            Datos del Representante y Empresa
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                            <div v-if="restaurantero.razon_social" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Razón social</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.razon_social }}</span>
                            </div>
                            <div v-if="restaurantero.nombre_representante" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Nombre del representante</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.nombre_representante }}</span>
                            </div>
                            <div v-if="restaurantero.curp_representante" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">CURP del representante</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200 font-mono text-xs">{{ restaurantero.curp_representante }}</span>
                            </div>
                            <div v-if="restaurantero.rfc" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">RFC</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200 font-mono text-xs">{{ restaurantero.rfc }}</span>
                            </div>
                            <div v-if="restaurantero.fecha_inicio_operaciones" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Fecha de inicio de operaciones</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200">
                                    {{ new Date(restaurantero.fecha_inicio_operaciones).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                                </span>
                            </div>
                            <div v-if="restaurantero.num_empleados != null" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Número de empleados</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.num_empleados }} empleados</span>
                            </div>
                            <div v-if="restaurantero.domicilio_en_yucatan != null" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Domicilio en Yucatán</span>
                                <span :class="restaurantero.domicilio_en_yucatan
                                        ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        : 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold self-start">
                                    {{ restaurantero.domicilio_en_yucatan ? 'Sí' : 'No' }}
                                </span>
                            </div>
                            <div v-if="restaurantero.redes_sociales?.length" class="flex flex-col sm:col-span-2">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Redes sociales</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.redes_sociales.join(', ') }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Características del Producto / Servicio -->
                    <div v-if="restaurantero.mercado_meta || restaurantero.tiempo_vida_anaquel || restaurantero.requisitos_alimentos?.length || restaurantero.apoyo_requisitos != null || restaurantero.requiere_refrigeracion != null || restaurantero.requiere_congelacion != null"
                         class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none">
                        <h3 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-4">
                            Características del Producto / Servicio
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                            <div v-if="restaurantero.mercado_meta" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Mercado meta</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.mercado_meta }}</span>
                            </div>
                            <div v-if="restaurantero.tiempo_vida_anaquel" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Tiempo de vida en anaquel</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.tiempo_vida_anaquel }}</span>
                            </div>
                            <div v-if="restaurantero.requisitos_alimentos?.length" class="flex flex-col sm:col-span-2">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Requisitos alimentarios</span>
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ restaurantero.requisitos_alimentos.join(', ') }}</span>
                            </div>
                            <div v-if="restaurantero.apoyo_requisitos != null" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Apoyo en trámites de requisitos</span>
                                <span :class="restaurantero.apoyo_requisitos?.length
                                        ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold self-start">
                                    {{ restaurantero.apoyo_requisitos?.length ? 'Sí' : 'No' }}
                                </span>
                            </div>
                            <div v-if="restaurantero.requiere_refrigeracion != null" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Requiere refrigeración</span>
                                <span :class="restaurantero.requiere_refrigeracion
                                        ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold self-start">
                                    {{ restaurantero.requiere_refrigeracion ? 'Sí' : 'No' }}
                                </span>
                            </div>
                            <div v-if="restaurantero.requiere_congelacion != null" class="flex flex-col">
                                <span class="text-xs text-gray-400 dark:text-gray-500 mb-0.5">Requiere congelación</span>
                                <span :class="restaurantero.requiere_congelacion
                                        ? 'bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-400'
                                        : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400'"
                                      class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold self-start">
                                    {{ restaurantero.requiere_congelacion ? 'Sí' : 'No' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar: info de cita (sticky) -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 space-y-5 sticky top-24 shadow-sm dark:shadow-none">

                        <div>
                            <div class="inline-flex items-center gap-2 bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 rounded-full px-3 py-1 mb-3">
                                <span class="w-2 h-2 rounded-full bg-guinda-600 dark:bg-guinda-400 animate-pulse"></span>
                                <span class="text-guinda-700 dark:text-guinda-400 text-xs font-semibold">Servicio gratuito — Impulsate</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Mesa de Networking</h3>
                            <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">{{ duracionTexto }}</p>
                        </div>

                        <div class="text-sm text-gray-500 dark:text-gray-400 space-y-3 border-t border-gray-100 dark:border-gray-800 pt-5">
                            <div class="flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-guinda-500 dark:text-guinda-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                <span>Lunes a Viernes, 9:00 am — 4:00 pm</span>
                            </div>
                            <div class="flex items-start gap-2.5">
                                <svg class="w-4 h-4 text-guinda-500 dark:text-guinda-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <span>Av. Industrias No Contaminantes Tab 13613, Col. Sodzil Norte, C.P. 97110, Mérida, Yucatán</span>
                            </div>
                        </div>

                        <!-- Sin sesión -->
                        <div v-if="!auth.user" class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center space-y-2">
                            <p class="text-gray-600 dark:text-gray-400 text-sm mb-1">Necesitas cuenta para agendar</p>
                            <Link :href="registerUrl"
                                  class="block py-2.5 bg-guinda-700 hover:bg-guinda-600 text-white font-bold rounded-xl text-sm transition-colors">
                                Crear cuenta gratis
                            </Link>
                            <Link :href="loginUrl"
                                  class="block py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-xl text-sm transition-colors">
                                Ya tengo cuenta
                            </Link>
                        </div>

                        <!-- Límite alcanzado -->
                        <div v-else-if="limiteAlcanzado" class="bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/30 rounded-xl p-4 text-center">
                            <p class="text-red-600 dark:text-red-400 text-sm font-medium">Has alcanzado el límite de {{ maxCitas }} citas</p>
                        </div>

                        <!-- Contador de citas -->
                        <div v-else class="bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 rounded-xl p-3 flex items-center justify-between">
                            <span class="text-gray-600 dark:text-gray-400 text-sm">Citas usadas</span>
                            <span class="text-guinda-700 dark:text-guinda-400 font-bold text-sm">{{ citasCount }}/{{ maxCitas }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CALENDARIO SEMANAL -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-sm dark:shadow-none">
                <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Agenda tu cita</h2>
                        <p class="text-gray-500 dark:text-gray-400 text-sm mt-0.5 capitalize">{{ semanaLabel }}</p>
                    </div>
                    <div v-if="!diasEvento.length" class="flex items-center gap-2">
                        <button @click="puedeRetrocederSemana && weekOffset--"
                                :disabled="!puedeRetrocederSemana"
                                class="p-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                        </button>
                        <button @click="weekOffset = 0"
                                class="px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg text-xs text-gray-600 dark:text-gray-300 transition-colors font-medium">
                            Esta semana
                        </button>
                        <button @click="puedeAvanzarSemana && weekOffset++"
                                :disabled="!puedeAvanzarSemana"
                                class="p-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                            <svg class="w-4 h-4 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>
                </div>

                <!-- Rango de agenda del evento -->
                <div v-if="evento && (evento.fecha_inicio_agenda || evento.fecha_fin_agenda)" class="mb-5 bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-800/30 rounded-xl p-3 flex items-center gap-2 text-xs text-guinda-700 dark:text-guinda-400">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                    <span>Período de agenda:
                        <strong>{{ evento.fecha_inicio_agenda ? new Date(evento.fecha_inicio_agenda + 'T00:00:00').toLocaleDateString('es-MX', {day:'numeric',month:'long',year:'numeric'}) : '—' }}</strong>
                        al
                        <strong>{{ evento.fecha_fin_agenda ? new Date(evento.fecha_fin_agenda + 'T00:00:00').toLocaleDateString('es-MX', {day:'numeric',month:'long',year:'numeric'}) : '—' }}</strong>
                    </span>
                </div>

                <!-- Avisos -->
                <div v-if="isAdmin" class="mb-5 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/30 rounded-xl p-4 text-center text-sm">
                    <p class="text-amber-700 dark:text-amber-400 font-medium">Modo administrador — Solo puedes gestionar citas de otros usuarios.</p>
                    <p class="text-amber-600 dark:text-amber-500/80 text-xs mt-1">Para crear una cita para un cliente, ve al <a :href="route('admin.citas.index')" class="underline">panel de citas</a>.</p>
                </div>
                <div v-else-if="!auth.user" class="mb-5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-4 text-center text-sm">
                    <Link :href="loginUrl" class="text-guinda-700 dark:text-guinda-400 hover:text-guinda-600 dark:hover:text-guinda-300 font-medium">Inicia sesión</Link>
                    <span class="text-gray-500 dark:text-gray-400"> para seleccionar un horario</span>
                </div>
                <div v-else-if="limiteAlcanzado" class="mb-5 bg-red-50 dark:bg-red-950/30 border border-red-200 dark:border-red-800/30 rounded-xl p-4 text-center">
                    <p class="text-red-600 dark:text-red-400 text-sm font-medium">Has alcanzado el límite de {{ maxCitas }} citas</p>
                </div>

                <!-- Leyenda -->
                <div class="flex flex-wrap items-center gap-5 mb-5 text-xs text-gray-500 dark:text-gray-500">
                    <div class="flex items-center gap-1.5">
                        <div class="w-3 h-3 rounded bg-guinda-100 dark:bg-guinda-500/20 border border-guinda-300 dark:border-guinda-500/40"></div>
                        <span>Disponible</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="w-3 h-3 rounded bg-gray-200 dark:bg-gray-700"></div>
                        <span>Ocupado</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <div class="w-3 h-3 rounded bg-gray-100 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700/30"></div>
                        <span>Hora pasada</span>
                    </div>
                </div>

                <!-- Tabla de slots -->
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[550px] text-xs border-collapse">
                        <thead>
                            <tr>
                                <th class="w-14 text-right pr-3 pb-3 text-gray-400 dark:text-gray-600 font-normal"></th>
                                <th v-for="(dia, i) in semana" :key="i" class="pb-3 text-center">
                                    <span class="block text-gray-400 dark:text-gray-500 font-medium">{{ diasEvento.length ? nombreDia(dia) : diasNombre[i] }}</span>
                                    <span class="block text-gray-900 dark:text-white text-base font-bold mt-0.5">{{ dia.getDate() }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="time in timeSlots" :key="time">
                                <td class="pr-3 py-1 text-right text-gray-400 dark:text-gray-600 font-mono whitespace-nowrap">{{ time }}</td>
                                <td v-for="(dia, di) in semana" :key="di" class="py-1 px-1">
                                    <button
                                        @click="seleccionarSlot(dia, time)"
                                        :disabled="esOcupado(dia, time) || esPasado(dia, time) || esFueraDeRangoEdicion(dia) || !auth.user || isAdmin || limiteAlcanzado"
                                        class="w-full h-9 rounded-lg text-xs font-medium transition-all duration-150"
                                        :class="[
                                            esOcupado(dia, time)
                                                ? 'bg-gray-200 dark:bg-gray-700/60 text-gray-400 dark:text-gray-600 cursor-not-allowed'
                                            : esPasado(dia, time) || esFueraDeRangoEdicion(dia)
                                                ? 'bg-transparent text-gray-300 dark:text-gray-700 cursor-default'
                                            : (!auth.user || isAdmin || limiteAlcanzado)
                                                ? 'bg-guinda-50 dark:bg-guinda-500/5 text-guinda-200 dark:text-guinda-900/40 cursor-not-allowed'
                                            : 'bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/30 text-guinda-700 dark:text-guinda-300 hover:bg-guinda-100 dark:hover:bg-guinda-500/25 hover:border-guinda-400 dark:hover:border-guinda-400/60 cursor-pointer active:scale-95'
                                        ]"
                                    >
                                        <span v-if="esOcupado(dia, time)">●</span>
                                        <span v-else-if="esPasado(dia, time) || esFueraDeRangoEdicion(dia)">–</span>
                                        <span v-else>{{ time }}</span>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</template>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

.producto-modal-enter-active { transition: opacity 0.25s ease, transform 0.25s ease; }
.producto-modal-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.producto-modal-enter-from  { opacity: 0; }
.producto-modal-leave-to    { opacity: 0; }
.producto-modal-enter-from .relative,
.producto-modal-leave-to .relative { transform: scale(0.95); }
.producto-modal-enter-active .relative,
.producto-modal-leave-active .relative { transition: transform 0.25s ease; }
</style>
