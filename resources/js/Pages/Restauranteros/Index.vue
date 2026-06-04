<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const props = defineProps({
    restauranteros: Object,
    filters: Object,
    categorias: Array,
});

const page = usePage();
const auth = computed(() => page.props.auth);

const search = ref(props.filters?.search || '');
const categoriaActiva = ref(props.filters?.categoria || '');

const aplicarFiltros = () => {
    router.get(route('restauranteros.index'), {
        search: search.value,
        categoria: categoriaActiva.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

let debounceTimer = null;
watch(search, () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(aplicarFiltros, 400);
});

const seleccionarCategoria = (cat) => {
    categoriaActiva.value = categoriaActiva.value === cat ? '' : cat;
    aplicarFiltros();
};
</script>

<template>
    <Head title="Proveedores — Impulsate" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white transition-colors duration-300">
        <!-- Navbar -->
        <nav class="sticky top-0 z-50 bg-white/95 dark:bg-gray-950/90 backdrop-blur border-b border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-none transition-colors">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
                <Link :href="route('home')" class="flex items-center shrink-0">
                    <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-9 w-auto" />
                </Link>

                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    <template v-if="auth.user">
                        <Link :href="route('user.dashboard')"
                              class="text-sm text-gray-600 dark:text-gray-300 hover:text-guinda-700 dark:hover:text-white transition-colors">
                            Mi Panel
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')"
                              class="text-sm text-gray-600 dark:text-gray-300 hover:text-guinda-700 dark:hover:text-white transition-colors">
                            Iniciar sesión
                        </Link>
                        <Link :href="route('register')"
                              class="text-sm bg-guinda-800 hover:bg-guinda-700 text-white px-4 py-1.5 rounded-lg transition-colors font-semibold shadow-sm">
                            Registrarse
                        </Link>
                    </template>
                </div>
            </div>
        </nav>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <!-- Header + Search -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-10">
                <div>
                    <h1 class="text-3xl font-black text-gray-900 dark:text-white">Proveedores</h1>
                    <p class="text-gray-500 dark:text-gray-400 mt-1">Explora y agenda citas con los mejores proveedores del sector.</p>
                </div>
                <div class="w-full sm:w-72">
                    <div class="relative">
                        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input v-model="search" type="text" placeholder="Buscar proveedor..."
                            class="w-full pl-9 pr-4 py-2.5 bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-500 rounded-xl text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors shadow-sm dark:shadow-none" />
                    </div>
                </div>
            </div>

            <!-- Filtro por categoría -->
            <div v-if="categorias && categorias.length > 0" class="flex flex-wrap gap-2 mb-8">
                <button
                    @click="seleccionarCategoria('')"
                    :class="categoriaActiva === ''
                        ? 'bg-guinda-800 text-white border-guinda-800'
                        : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-700 hover:border-guinda-400 dark:hover:border-guinda-600'"
                    class="px-4 py-1.5 rounded-full text-sm font-medium border transition-all duration-200"
                >
                    Todos
                </button>
                <button
                    v-for="cat in categorias"
                    :key="cat"
                    @click="seleccionarCategoria(cat)"
                    :class="categoriaActiva === cat
                        ? 'bg-guinda-800 text-white border-guinda-800'
                        : 'bg-white dark:bg-gray-900 text-gray-600 dark:text-gray-400 border-gray-300 dark:border-gray-700 hover:border-guinda-400 dark:hover:border-guinda-600'"
                    class="px-4 py-1.5 rounded-full text-sm font-medium border transition-all duration-200"
                >
                    {{ cat }}
                </button>
            </div>

            <!-- Grid -->
            <div v-if="restauranteros.data.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <Link v-for="r in restauranteros.data" :key="r.id"
                    :href="route('restauranteros.show', r.id)"
                    class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden hover:border-guinda-300 dark:hover:border-guinda-500/30 hover:-translate-y-1 hover:shadow-lg dark:hover:shadow-none transition-all duration-200 group">
                    <!-- Foto -->
                    <div class="h-40 bg-gradient-to-br from-gray-100 dark:from-gray-800 to-gray-200 dark:to-gray-900 flex items-center justify-center overflow-hidden">
                        <img v-if="r.logo_path" :src="'/storage/' + r.logo_path" :alt="r.nombre_restaurante"
                            class="w-full h-full object-cover" />
                        <svg v-else class="w-14 h-14 text-gray-300 dark:text-gray-700 group-hover:text-guinda-300/40 dark:group-hover:text-guinda-900/60 transition-colors" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                        </svg>
                    </div>

                    <div class="p-5">
                        <h3 class="font-bold text-gray-900 dark:text-white text-base mb-2 truncate">{{ r.nombre_restaurante }}</h3>

                        <div v-if="r.municipio" class="flex items-center gap-1.5 text-gray-500 dark:text-gray-500 text-xs mb-3">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="truncate">{{ r.municipio }}, Yucatán</span>
                        </div>
                        <div v-else-if="r.direccion" class="flex items-center gap-1.5 text-gray-500 dark:text-gray-500 text-xs mb-3">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <span class="truncate">{{ r.direccion }}</span>
                        </div>

                        <div class="flex items-center justify-between mt-3">
                            <span class="text-xs text-gray-400 dark:text-gray-600 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">{{ r.citas_count || 0 }} citas</span>
                            <span class="text-guinda-700 dark:text-guinda-500 text-xs font-semibold group-hover:text-guinda-600 dark:group-hover:text-guinda-400 transition-colors">Ver perfil →</span>
                        </div>
                    </div>
                </Link>
            </div>

            <!-- Empty state -->
            <div v-else class="text-center py-24">
                <svg class="w-16 h-16 mx-auto text-gray-300 dark:text-gray-700 mb-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                </svg>
                <p class="text-gray-500 dark:text-gray-500 text-lg font-medium">No se encontraron proveedores</p>
                <p v-if="search" class="text-gray-400 dark:text-gray-600 text-sm mt-1">Intenta con otro término de búsqueda</p>
            </div>

            <!-- Paginación -->
            <div v-if="restauranteros.links && restauranteros.last_page > 1" class="flex items-center justify-center gap-1 mt-12">
                <template v-for="link in restauranteros.links" :key="link.label">
                    <Link v-if="link.url" :href="link.url"
                        :class="[
                            'px-3 py-1.5 rounded-lg text-sm font-medium transition-colors',
                            link.active
                                ? 'bg-guinda-800 text-white font-semibold'
                                : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white'
                        ]"
                        v-html="link.label"
                    />
                    <span v-else
                        :class="['px-3 py-1.5 rounded-lg text-sm text-gray-400 dark:text-gray-600 cursor-not-allowed']"
                        v-html="link.label"
                    />
                </template>
            </div>
        </main>
    </div>
</template>
