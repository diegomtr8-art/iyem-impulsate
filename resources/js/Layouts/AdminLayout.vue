<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import NotificacionCampana from '@/Components/NotificacionCampana.vue';

defineProps({ title: String });

const sidebarOpen = ref(true);
const logout = () => router.post(route('logout'));

const navItems = [
    { label: 'Dashboard',    routeName: 'admin.dashboard',            icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { label: 'Proveedores',  routeName: 'admin.restauranteros.index',  icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
    { label: 'Citas',        routeName: 'admin.citas.index',          icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Usuarios',     routeName: 'admin.usuarios.index',       icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z' },
    { label: 'Calendario',   routeName: 'admin.calendario',           icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z' },
    { label: 'Metricas',     routeName: 'admin.metricas',             icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z' },
    { label: 'Ediciones',   routeName: 'admin.ediciones.index',      icon: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4' },
];
</script>

<template>
    <div class="flex h-screen bg-gray-100 dark:bg-gray-950 overflow-hidden transition-colors duration-300">
        <Head :title="title ? `${title} — Admin` : 'Admin'" />

        <!-- Sidebar -->
        <aside :class="sidebarOpen ? 'w-60' : 'w-16'"
            class="bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 flex flex-col transition-all duration-300 shrink-0 shadow-sm">

            <!-- Logo -->
            <div class="flex items-center gap-3 px-4 py-5 border-b border-gray-200 dark:border-gray-800">
                <img src="/images/logo_impulsate.png" alt="Impulsate" :class="sidebarOpen ? 'h-8 w-auto' : 'h-7 w-auto'" class="shrink-0" />
                <!-- <span v-if="sidebarOpen" class="text-guinda-600 dark:text-guinda-400 text-xs font-medium shrink-0">Panel Admin</span> -->
            </div>

            <!-- Navigation -->
            <nav class="flex-1 py-4 space-y-0.5 overflow-y-auto px-2">
                <Link v-for="item in navItems" :key="item.routeName"
                    :href="route(item.routeName)"
                    :class="[
                        'flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-xl transition-all duration-200',
                        route().current(item.routeName)
                            ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-800 dark:text-guinda-400 shadow-sm'
                            : 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-guinda-800 dark:hover:text-white'
                    ]">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span v-if="sidebarOpen" class="truncate">{{ item.label }}</span>
                </Link>
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
                        <span class="bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 text-xs font-bold px-2.5 py-1 rounded-full border border-guinda-200 dark:border-guinda-800">
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
