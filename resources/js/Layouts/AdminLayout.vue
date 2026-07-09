<script setup>
// v3
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import NotificacionCampana from '@/Components/NotificacionCampana.vue';

defineProps({ title: String });

const page = usePage();
const isSuperAdmin = computed(() => page.props.auth?.user?.is_super_admin === true);

const sidebarOpen = ref(false);
const isMobile = ref(false);
const logout = () => router.post(route('logout'));

const checkMobile = () => {
    isMobile.value = window.innerWidth < 640;
    if (!isMobile.value) sidebarOpen.value = true;
    else sidebarOpen.value = false;
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});
onUnmounted(() => window.removeEventListener('resize', checkMobile));

const closeSidebar = () => { if (isMobile.value) sidebarOpen.value = false; };

const adminNavItems = [
    { label: 'Dashboard',         routeName: 'admin.dashboard',            icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { label: 'Proveedores',       routeName: 'admin.restauranteros.index',  icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4', badge: true },
    { label: 'Citas',             routeName: 'admin.citas.index',          icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Compradores',       routeName: 'admin.usuarios.index',       icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { label: 'Calendario',        routeName: 'admin.calendario',           icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Métricas',          routeName: 'admin.metricas',             icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { label: 'Eventos',           routeName: 'admin.eventos.index',        icon: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4' },
    { label: 'Torre de Control',  routeName: 'admin.torre.index',          icon: 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2' },
    { label: 'Pantalla TV',       routeName: 'admin.tv.index',             icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { label: 'Encuestas',         routeName: 'admin.encuestas.index',      icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    { label: 'Exportar',          routeName: 'admin.exportar.index',       icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
];

const superAdminNavItems = [
    { label: 'Gestión Usuarios', routeName: 'admin.usuarios-gestion.index', icon: 'M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z' },
    { label: 'Plantillas Correo', routeName: 'admin.plantillas.index',      icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { label: 'Correo Masivo',     routeName: 'admin.correo-masivo.index',   icon: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z' },
    { label: 'Categorías',        routeName: 'admin.categorias.index',
      icon: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z' },
    { label: 'Publicidad',        routeName: 'admin.publicidad.index',      icon: 'M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z' },
];
</script>

<template>
    <div class="flex h-screen bg-gray-100 dark:bg-gray-950 overflow-hidden transition-colors duration-300">
        <Head :title="title ? `${title} — Admin` : 'Admin'" />

        <!-- Mobile overlay -->
        <div v-if="isMobile && sidebarOpen"
            class="fixed inset-0 z-40 bg-black/50"
            @click="sidebarOpen = false">
        </div>

        <!-- Sidebar -->
        <aside :class="[
                sidebarOpen ? 'w-60 translate-x-0' : (isMobile ? '-translate-x-full w-60' : 'w-16 translate-x-0'),
                isMobile ? 'fixed inset-y-0 left-0 z-50' : 'relative'
            ]"
            class="bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col transition-all duration-300 shrink-0 shadow-sm">

            <!-- Logo -->
            <div class="flex items-center gap-3 px-4 py-5 border-b border-gray-200 dark:border-gray-800">
                <img src="/images/logo_impulsate.png" alt="Impulsate" :class="sidebarOpen ? 'h-8 w-auto' : 'h-7 w-auto'" class="shrink-0" />
                <!-- <span v-if="sidebarOpen" class="text-guinda-600 dark:text-guinda-400 text-xs font-medium shrink-0">Panel Admin</span> -->
            </div>

            <!-- Navigation -->
            <nav class="flex-1 py-4 space-y-0.5 overflow-y-auto px-2">
                <Link v-for="item in adminNavItems" :key="item.routeName"
                    :href="route(item.routeName)"
                    @click="closeSidebar"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200',
                        route().current(item.routeName)
                            ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-800 dark:text-guinda-400 shadow-sm'
                            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-guinda-800 dark:hover:text-white'
                    ]">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span v-if="sidebarOpen" class="truncate flex-1">{{ item.label }}</span>
                    <span v-if="item.badge && sidebarOpen && $page.props.pendientesAprobacion > 0"
                        class="ml-auto min-w-[1.25rem] h-5 px-1 rounded-full bg-red-500 text-white text-xs font-bold flex items-center justify-center">
                        {{ $page.props.pendientesAprobacion }}
                    </span>
                    <span v-if="item.badge && !sidebarOpen && $page.props.pendientesAprobacion > 0"
                        class="w-2 h-2 rounded-full bg-red-500 shrink-0"></span>
                </Link>

                <!-- Sección Super Admin -->
                <template v-if="isSuperAdmin">
                    <div class="my-2 px-3">
                        <div v-if="sidebarOpen" class="flex items-center gap-2">
                            <div class="flex-1 h-px bg-guinda-200 dark:bg-guinda-900/50"></div>
                            <span class="text-[10px] font-bold uppercase tracking-widest text-guinda-400 dark:text-guinda-600 whitespace-nowrap">Super Admin</span>
                            <div class="flex-1 h-px bg-guinda-200 dark:bg-guinda-900/50"></div>
                        </div>
                        <div v-else class="h-px bg-guinda-200 dark:bg-guinda-900/50"></div>
                    </div>
                    <Link v-for="item in superAdminNavItems" :key="item.routeName"
                        :href="route(item.routeName)"
                        @click="closeSidebar"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200',
                            route().current(item.routeName)
                                ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-800 dark:text-guinda-400 shadow-sm'
                                : 'text-guinda-500 dark:text-guinda-500 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 hover:text-guinda-800 dark:hover:text-guinda-300'
                        ]">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                        </svg>
                        <span v-if="sidebarOpen" class="truncate flex-1">{{ item.label }}</span>
                    </Link>
                </template>
            </nav>

            <!-- Bottom -->
            <div class="border-t border-gray-200 dark:border-gray-800 px-2 py-4 space-y-1">
                <Link :href="route('home')"
                    class="flex items-center gap-3 px-3 py-2 text-sm text-gray-400 dark:text-gray-500 hover:text-guinda-700 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-xl transition-colors">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span v-if="sidebarOpen">Ir al sitio</span>
                </Link>
                <button @click="logout"
                    class="flex items-center gap-3 w-full px-3 py-2 text-sm font-medium text-red-500 dark:text-red-400 hover:text-red-600 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-gray-800 rounded-xl transition-colors">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span v-if="sidebarOpen">Cerrar sesión</span>
                </button>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Topbar with animated gradient -->
            <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 shrink-0 shadow-sm transition-colors duration-300">
                <!-- Animated gradient stripe -->
                <div class="h-0.5 bg-gradient-to-r from-guinda-800 via-guinda-500 to-guinda-800 bg-[length:200%_100%] animate-gradient"></div>
                <div class="flex items-center justify-between px-6 py-3.5">
                    <button @click="sidebarOpen = !sidebarOpen"
                        class="text-gray-400 dark:text-gray-500 hover:text-guinda-700 dark:hover:text-gray-300 focus:outline-none p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="flex items-center gap-3">
                        <NotificacionCampana />
                        <ThemeToggle />
                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ $page.props.auth.user?.name }}</div>
                        <span v-if="isSuperAdmin"
                            class="bg-guinda-700 dark:bg-guinda-800 text-white text-xs font-bold px-2.5 py-1 rounded-full border border-guinda-800 dark:border-guinda-700">
                            Super Admin
                        </span>
                        <span v-else
                            class="bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 text-xs font-bold px-2.5 py-1 rounded-full border border-guinda-200 dark:border-guinda-800">
                            Admin
                        </span>
                    </div>
                </div>
                <div v-if="$slots.header" class="px-6 pb-4 border-t border-gray-100 dark:border-gray-800/50 pt-3">
                    <slot name="header" />
                </div>
            </header>

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success"
                class="bg-emerald-50 dark:bg-emerald-500/10 border-l-4 border-emerald-500 text-emerald-700 dark:text-emerald-400 px-6 py-3 text-sm font-medium transition-colors">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error"
                class="bg-red-50 dark:bg-red-500/10 border-l-4 border-red-500 text-red-700 dark:text-red-400 px-6 py-3 text-sm font-medium transition-colors">
                {{ $page.props.flash.error }}
            </div>

            <!-- Content -->
            <main class="flex-1 overflow-y-auto bg-gray-100 dark:bg-gray-950 p-6 transition-colors duration-300">
                <slot />
            </main>
        </div>
    </div>
</template>

<style>
@keyframes gradient-shift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}
.animate-gradient {
    animation: gradient-shift 4s ease infinite;
}
</style>
