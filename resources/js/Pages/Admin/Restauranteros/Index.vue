<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { MUNICIPIOS_YUCATAN } from '@/constants/municipiosYucatan';

const props = defineProps({
    restauranteros: Object,
    categorias: Array,
    pendientesAprobacion: Number,
    proveedoresPendientes: Array,
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? '');
let searchTimer = null;
watch(search, (val) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(route('admin.restauranteros.index'), { search: val || undefined }, {
            preserveState: true, preserveScroll: true, replace: true,
        });
    }, 350);
});

const municipios = MUNICIPIOS_YUCATAN;

const showModal = ref(false);
const showRechazarModal = ref(false);
const proveedorARechazar = ref(null);
const motivoRechazo = ref('');

const form = useForm({
    name: '',
    email: '',
    password: '',
    nombre_restaurante: '',
    telefono: '',
    direccion: '',
    municipio: '',
    categoria: '',
    descripcion: '',
});

const openModal = () => {
    form.reset();
    showModal.value = true;
};

const submit = () => {
    form.post(route('admin.restauranteros.store'), {
        onSuccess: () => { showModal.value = false; },
    });
};

const actualizarCategoria = (restaurantero, valor) => {
    router.patch(route('admin.restauranteros.update-categoria', restaurantero.id), {
        categoria: valor || null,
    }, { preserveScroll: true });
};

const toggle = (restaurantero) => {
    router.patch(route('admin.restauranteros.toggle', restaurantero.id));
};

const destroy = (restaurantero) => {
    if (confirm(`¿Eliminar a "${restaurantero.nombre_restaurante}" y su usuario? Esta acción no se puede deshacer.`)) {
        router.delete(route('admin.restauranteros.destroy', restaurantero.id));
    }
};

const aprobar = (restaurantero) => {
    if (confirm(`¿Aprobar a "${restaurantero.nombre_restaurante}"? Se notificará al proveedor por correo.`)) {
        router.post(route('admin.restauranteros.aprobar', restaurantero.id));
    }
};

const abrirRechazar = (restaurantero) => {
    proveedorARechazar.value = restaurantero;
    motivoRechazo.value = '';
    showRechazarModal.value = true;
};

const submitRechazo = () => {
    if (!motivoRechazo.value.trim()) return;
    router.post(route('admin.restauranteros.rechazar-aprobacion', proveedorARechazar.value.id), {
        motivo: motivoRechazo.value,
    }, {
        onSuccess: () => { showRechazarModal.value = false; proveedorARechazar.value = null; },
    });
};

const formatFecha = (f) => f ? new Date(f).toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
</script>

<template>
    <AdminLayout title="Proveedores">
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Proveedores</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Gestión de proveedores registrados</p>
                </div>
                <button @click="openModal"
                    class="flex items-center gap-2 px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Agregar Proveedor
                </button>
            </div>
        </template>

        <!-- ── SECCIÓN: PENDIENTES DE APROBACIÓN ─────────────────────── -->
        <div v-if="proveedoresPendientes.length > 0" class="mb-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                    Pendientes de Aprobación
                </h2>
                <span class="bg-amber-100 dark:bg-amber-500/15 text-amber-700 dark:text-amber-400 text-xs font-bold px-2 py-0.5 rounded-full border border-amber-200 dark:border-amber-500/20">
                    {{ proveedoresPendientes.length }}
                </span>
            </div>

            <div class="space-y-3">
                <div v-for="r in proveedoresPendientes" :key="'pend-' + r.id"
                    class="bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center gap-4 transition-colors">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <span class="font-bold text-gray-900 dark:text-white text-base">{{ r.nombre_restaurante }}</span>
                            <span v-if="r.categoria" class="text-xs bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-2 py-0.5 rounded-full">{{ r.categoria }}</span>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            <span class="font-medium">{{ r.user?.name }}</span>
                            <span class="mx-1.5 text-gray-300 dark:text-gray-700">·</span>
                            {{ r.user?.email }}
                        </p>
                        <p v-if="r.municipio" class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">{{ r.municipio }}</p>
                        <p v-if="r.descripcion" class="text-xs text-gray-500 dark:text-gray-500 mt-1 line-clamp-2">{{ r.descripcion }}</p>
                        <p class="text-xs text-amber-600 dark:text-amber-500 mt-1.5">
                            Solicitó el {{ formatFecha(r.solicitado_aprobacion_at) }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <Link :href="route('admin.restauranteros.show', r.id)"
                            class="text-xs font-medium px-3 py-2 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl transition-colors">
                            Ver perfil
                        </Link>
                        <button @click="abrirRechazar(r)"
                            class="text-xs font-semibold px-3 py-2 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/20 rounded-xl transition-colors">
                            Rechazar
                        </button>
                        <button @click="aprobar(r)"
                            class="text-xs font-semibold px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl transition-colors shadow-sm">
                            ✓ Aprobar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── BUSCADOR ────────────────────────────────────────────────── -->
        <div class="flex items-center gap-3 mb-4">
            <div class="relative flex-1 max-w-sm">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500 pointer-events-none"
                     fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
                <input v-model="search" type="search" placeholder="Buscar por nombre, email, RFC, municipio…"
                       class="w-full pl-9 pr-3 py-2 text-sm bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 rounded-xl focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors" />
            </div>
            <span v-if="search" class="text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">
                {{ restauranteros.total }} resultado{{ restauranteros.total !== 1 ? 's' : '' }}
            </span>
        </div>

        <!-- ── TABLA DE TODOS LOS PROVEEDORES ─────────────────────────── -->
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm dark:shadow-none transition-colors overflow-x-auto">
            <table class="w-full text-sm min-w-[700px]">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                        <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Proveedor</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Propietario</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden md:table-cell">Teléfono</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden xl:table-cell">Categoría</th>
                        <th class="text-center px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider hidden lg:table-cell">Citas</th>
                        <th class="text-center px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Estado</th>
                        <th class="text-right px-6 py-3 text-xs text-gray-500 dark:text-gray-500 font-semibold uppercase tracking-wider">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in restauranteros.data" :key="r.id"
                        class="border-b border-gray-100 dark:border-gray-800/40 hover:bg-gray-50 dark:hover:bg-gray-800/30 transition-colors duration-150">
                        <td class="px-6 py-4">
                            <Link :href="route('admin.restauranteros.show', r.id)"
                                class="font-semibold text-gray-900 dark:text-white hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors">
                                {{ r.nombre_restaurante }}
                            </Link>
                            <span v-if="r.solicitado_aprobacion_at && !r.aprobado && !r.rechazado"
                                class="ml-2 text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 px-1.5 py-0.5 rounded-full">
                                Pend. aprobación
                            </span>
                            <span v-else-if="r.aprobado"
                                class="ml-2 text-xs bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 px-1.5 py-0.5 rounded-full">
                                Aprobado
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-gray-700 dark:text-gray-300">{{ r.user?.name }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-500">{{ r.user?.email }}</div>
                        </td>
                        <td class="px-6 py-4 text-gray-500 dark:text-gray-400 hidden md:table-cell">{{ r.telefono ?? '—' }}</td>
                        <td class="px-6 py-4 hidden xl:table-cell">
                            <select
                                :value="r.categoria || ''"
                                @change="actualizarCategoria(r, $event.target.value)"
                                class="text-xs bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg px-2 py-1.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors w-44"
                            >
                                <option value="">— Sin categoría —</option>
                                <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </td>
                        <td class="px-6 py-4 text-center text-guinda-700 dark:text-guinda-400 font-bold hidden lg:table-cell">{{ r.citas_count }}</td>
                        <td class="px-6 py-4 text-center">
                            <span :class="r.activo
                                    ? 'bg-emerald-50 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/20'
                                    : 'bg-red-50 dark:bg-red-500/15 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-500/20'"
                                class="text-xs font-semibold px-2.5 py-1 rounded-full">
                                {{ r.activo ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2 flex-wrap">
                                <Link :href="route('admin.restauranteros.show', r.id)"
                                    class="text-xs font-medium px-3 py-1.5 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg transition-colors">
                                    Ver
                                </Link>
                                <Link :href="route('admin.restauranteros.editar', r.id)"
                                    class="text-xs font-bold px-3 py-1.5 bg-guinda-50 dark:bg-guinda-900/20 hover:bg-guinda-100 dark:hover:bg-guinda-900/40 text-guinda-700 dark:text-guinda-400 border border-guinda-200 dark:border-guinda-800 rounded-lg transition-colors">
                                    ✏️ Editar
                                </Link>
                                <button v-if="r.solicitado_aprobacion_at && !r.aprobado && !r.rechazado"
                                    @click="aprobar(r)"
                                    class="text-xs font-medium px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors">
                                    Aprobar
                                </button>
                                <button @click="toggle(r)"
                                    :class="r.activo
                                        ? 'bg-amber-50 dark:bg-amber-500/10 hover:bg-amber-100 dark:hover:bg-amber-500/20 text-amber-700 dark:text-amber-400'
                                        : 'bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400'"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg transition-colors">
                                    {{ r.activo ? 'Desactivar' : 'Activar' }}
                                </button>
                                <button @click="destroy(r)"
                                    class="text-xs font-medium px-3 py-1.5 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 dark:hover:bg-red-500/20 text-red-600 dark:text-red-400 rounded-lg transition-colors">
                                    Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!restauranteros.data.length">
                        <td colspan="7" class="px-6 py-16 text-center text-gray-400 dark:text-gray-600">No hay proveedores registrados.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="restauranteros.last_page > 1" class="px-6 py-4 border-t border-gray-200 dark:border-gray-800 flex justify-end gap-2">
                <Link v-for="(link, idx) in restauranteros.links" :key="link.label"
                    :href="link.url ?? '#'"
                    :class="[
                        'px-3 py-1.5 text-xs rounded-lg transition-colors',
                        link.active ? 'bg-guinda-800 text-white font-semibold' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700',
                        !link.url ? 'opacity-40 cursor-not-allowed' : ''
                    ]">
                    <svg v-if="idx === 0" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    <svg v-else-if="idx === restauranteros.links.length - 1" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
                    <span v-else v-html="link.label" />
                </Link>
            </div>
        </div>

        <!-- Modal Agregar Proveedor -->
        <Teleport to="body">
            <div v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="showModal = false">
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl border border-gray-200 dark:border-gray-700 max-h-[90vh] overflow-y-auto">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white">Agregar Proveedor</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-500 mt-0.5">Se creará un usuario y perfil de proveedor</p>
                        </div>
                        <button @click="showModal = false"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="px-6 py-5 space-y-5">
                        <div>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-500 uppercase tracking-wider mb-3">Datos de acceso</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del propietario *</label>
                                    <input v-model="form.name" type="text" placeholder="Ej. Carlos Montejo"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border rounded-xl px-3 py-2.5 focus:outline-none transition-colors"
                                        :class="form.errors.name ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700 focus:border-guinda-500 dark:focus:border-guinda-500'" />
                                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Correo electrónico *</label>
                                    <input v-model="form.email" type="email" placeholder="correo@empresa.com"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border rounded-xl px-3 py-2.5 focus:outline-none transition-colors"
                                        :class="form.errors.email ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700 focus:border-guinda-500 dark:focus:border-guinda-500'" />
                                    <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contraseña *</label>
                                    <input v-model="form.password" type="password" placeholder="Mínimo 8 caracteres"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border rounded-xl px-3 py-2.5 focus:outline-none transition-colors"
                                        :class="form.errors.password ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700 focus:border-guinda-500 dark:focus:border-guinda-500'" />
                                    <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-semibold text-gray-500 dark:text-gray-500 uppercase tracking-wider mb-3">Datos del negocio</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre del negocio *</label>
                                    <input v-model="form.nombre_restaurante" type="text" placeholder="Ej. Distribuidora Montejo"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border rounded-xl px-3 py-2.5 focus:outline-none transition-colors"
                                        :class="form.errors.nombre_restaurante ? 'border-red-400 dark:border-red-500' : 'border-gray-200 dark:border-gray-700 focus:border-guinda-500 dark:focus:border-guinda-500'" />
                                    <p v-if="form.errors.nombre_restaurante" class="text-xs text-red-500 mt-1">{{ form.errors.nombre_restaurante }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Teléfono</label>
                                    <input v-model="form.telefono" type="tel" maxlength="10" placeholder="9991234567"
                                        @input="form.telefono = form.telefono.replace(/\D/g, '').slice(0, 10)"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoría</label>
                                    <select v-model="form.categoria"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors">
                                        <option value="">— Sin categoría —</option>
                                        <option v-for="cat in categorias" :key="cat" :value="cat">{{ cat }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Municipio</label>
                                    <select v-model="form.municipio"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors">
                                        <option value="">— Seleccionar —</option>
                                        <option v-for="mun in municipios" :key="mun" :value="mun">{{ mun }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Dirección</label>
                                    <input v-model="form.direccion" type="text" placeholder="Calle y número"
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors" />
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
                                    <textarea v-model="form.descripcion" rows="3" placeholder="Breve descripción del negocio o servicios que ofrece..."
                                        class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors resize-none" />
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                            <button type="button" @click="showModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-colors">
                                Cancelar
                            </button>
                            <button type="submit" :disabled="form.processing"
                                class="px-5 py-2 text-sm font-semibold text-white bg-guinda-800 hover:bg-guinda-700 disabled:opacity-60 rounded-xl transition-colors">
                                {{ form.processing ? 'Guardando...' : 'Crear Proveedor' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Modal Rechazar -->
        <Teleport to="body">
            <div v-if="showRechazarModal"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="showRechazarModal = false">
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-800">
                        <h2 class="text-lg font-bold text-gray-900 dark:text-white">Rechazar solicitud</h2>
                        <button @click="showRechazarModal = false"
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="px-6 py-5 space-y-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Se notificará a <strong class="text-gray-900 dark:text-white">{{ proveedorARechazar?.nombre_restaurante }}</strong> con el motivo del rechazo.
                        </p>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Motivo del rechazo *</label>
                            <textarea v-model="motivoRechazo" rows="4" placeholder="Indica el motivo para que el proveedor pueda corregir su información..."
                                class="w-full text-sm bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-3 py-2.5 focus:outline-none focus:border-red-500 dark:focus:border-red-500 transition-colors resize-none" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 px-6 pb-5">
                        <button @click="showRechazarModal = false"
                            class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-xl transition-colors">
                            Cancelar
                        </button>
                        <button @click="submitRechazo" :disabled="!motivoRechazo.trim()"
                            class="px-5 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-500 disabled:opacity-60 rounded-xl transition-colors">
                            Rechazar y notificar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
