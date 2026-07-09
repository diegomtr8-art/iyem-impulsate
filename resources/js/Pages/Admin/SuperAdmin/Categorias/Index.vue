<script setup>
import { ref, computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    categorias: Array,
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

// ── Nueva categoría ──────────────────────────────────────────
const form = useForm({ nombre: '' });

const agregar = () => {
    if (!form.nombre.trim()) return;
    form.post(route('admin.categorias.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('nombre'),
    });
};

// ── Editar nombre inline ─────────────────────────────────────
const editandoId = ref(null);
const nombreEdicion = ref('');

const iniciarEdicion = (cat) => {
    editandoId.value = cat.id;
    nombreEdicion.value = cat.nombre;
};

const cancelarEdicion = () => {
    editandoId.value = null;
    nombreEdicion.value = '';
};

const guardarEdicion = (cat) => {
    if (!nombreEdicion.value.trim()) return;
    router.put(route('admin.categorias.update', cat.id), { nombre: nombreEdicion.value }, {
        preserveScroll: true,
        onSuccess: () => cancelarEdicion(),
    });
};

// ── Toggle activo ─────────────────────────────────────────────
const toggleActivo = (cat) => router.patch(route('admin.categorias.toggle', cat.id), {}, { preserveScroll: true });

// ── Eliminar ──────────────────────────────────────────────────
const eliminar = (cat) => {
    if (!confirm(`¿Eliminar la categoría "${cat.nombre}"? Esta acción no se puede deshacer.`)) return;
    router.delete(route('admin.categorias.destroy', cat.id), { preserveScroll: true });
};
</script>

<template>
    <AdminLayout title="Categorías">
        <Head title="Categorías" />

        <div class="space-y-6 max-w-3xl">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Categorías</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Gestiona las categorías disponibles para los proveedores.
                </p>
            </div>

            <!-- Flash -->
            <div v-if="flash.success"
                class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-xl px-4 py-3 text-sm text-emerald-700 dark:text-emerald-400">
                {{ flash.success }}
            </div>
            <div v-if="page.props.errors?.error"
                class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl px-4 py-3 text-sm text-red-700 dark:text-red-400">
                {{ page.props.errors.error }}
            </div>

            <!-- Nueva categoría -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Nueva categoría</h2>
                <form @submit.prevent="agregar" class="flex gap-2">
                    <input v-model="form.nombre" type="text" placeholder="Nombre de la categoría"
                        class="flex-1 text-sm px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400" />
                    <button type="submit" :disabled="!form.nombre.trim() || form.processing"
                        class="px-4 py-2 text-sm font-semibold rounded-xl bg-[#8b1028] hover:bg-[#6f0c1f] text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                        Agregar
                    </button>
                </form>
                <p v-if="form.errors.nombre" class="text-xs text-red-500 mt-1.5">{{ form.errors.nombre }}</p>
            </div>

            <!-- Lista de categorías -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                <div v-if="categorias.length === 0" class="p-8 text-center text-sm text-gray-400 dark:text-gray-500">
                    No hay categorías registradas.
                </div>
                <div v-else class="divide-y divide-gray-50 dark:divide-gray-800">
                    <div v-for="cat in categorias" :key="cat.id"
                        class="flex items-center gap-3 px-5 py-3.5 hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors">

                        <!-- Nombre / edición inline -->
                        <div class="flex-1 min-w-0">
                            <div v-if="editandoId === cat.id" class="flex items-center gap-2">
                                <input v-model="nombreEdicion" type="text" @keyup.enter="guardarEdicion(cat)" @keyup.escape="cancelarEdicion"
                                    class="flex-1 text-sm px-2.5 py-1.5 rounded-lg border border-guinda-300 dark:border-guinda-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-guinda-400" />
                                <button @click="guardarEdicion(cat)" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 px-2">Guardar</button>
                                <button @click="cancelarEdicion" class="text-xs text-gray-400 hover:text-gray-600 px-2">Cancelar</button>
                            </div>
                            <div v-else class="flex items-center gap-2">
                                <span :class="cat.activo ? 'text-gray-900 dark:text-white' : 'text-gray-400 dark:text-gray-600 line-through'"
                                    class="text-sm font-medium">
                                    {{ cat.nombre }}
                                </span>
                                <span class="text-xs text-gray-400 dark:text-gray-600">
                                    {{ cat.restauranteros_count ?? 0 }} proveedor{{ (cat.restauranteros_count ?? 0) !== 1 ? 'es' : '' }}
                                </span>
                            </div>
                        </div>

                        <!-- Toggle activo -->
                        <button @click="toggleActivo(cat)"
                            :class="['relative inline-flex h-5 w-9 rounded-full transition-colors focus:outline-none shrink-0',
                                cat.activo ? 'bg-guinda-600' : 'bg-gray-300 dark:bg-gray-600']">
                            <span :class="['inline-block h-4 w-4 rounded-full bg-white shadow transform transition-transform mt-0.5',
                                cat.activo ? 'translate-x-4' : 'translate-x-0.5']"></span>
                        </button>

                        <!-- Acciones -->
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="iniciarEdicion(cat)"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-guinda-50 dark:bg-guinda-900/20 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-100 dark:hover:bg-guinda-900/40 transition-colors">
                                Editar
                            </button>
                            <button @click="eliminar(cat)" :disabled="cat.restauranteros_count > 0"
                                :title="cat.restauranteros_count > 0 ? 'No se puede eliminar: hay proveedores con esta categoría' : ''"
                                class="px-3 py-1.5 text-xs font-semibold rounded-lg bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 hover:bg-red-100 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
