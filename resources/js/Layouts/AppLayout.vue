<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Banner from '@/Components/Banner.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import NotificacionCampana from '@/Components/NotificacionCampana.vue';
import PublicidadPopup from '@/Components/PublicidadPopup.vue';

defineProps({ title: String });

const page = usePage();
const showingMenu = ref(false);
const switchingRole = ref(false);

const logout = () => router.post(route('logout'));

const authUser = computed(() => page.props.auth?.user);
const tieneDualRol = computed(() => authUser.value?.tiene_dual_rol ?? false);
const activeRole = computed(() => authUser.value?.active_role ?? 'comprador');

const switchRole = () => {
    if (switchingRole.value) return;
    switchingRole.value = true;
    router.post(route('switch.role'), {}, {
        onSuccess: () => { switchingRole.value = false; },
        onError: () => { switchingRole.value = false; },
        onFinish: () => { switchingRole.value = false; },
    });
};
</script>

<template>
    <div>
        <Head :title="title" />
        <Banner />
        <PublicidadPopup />

        <div class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white transition-colors duration-300">

            <!-- Navbar -->
            <nav class="bg-white/95 dark:bg-gray-950/95 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800/60 sticky top-0 z-50 transition-colors duration-300">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between h-16">

                        <!-- Logo -->
                        <Link :href="route('home')" class="flex items-center shrink-0">
                            <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-9 w-auto" />
                        </Link>

                        <!-- Desktop nav -->
                        <div class="hidden sm:flex items-center gap-1">
                            <Link :href="route('proveedores.index')"
                                class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                Proveedores
                            </Link>
                            <!-- Modo Comprador: Mis Citas -->
                            <Link v-if="$page.props.auth?.user && activeRole !== 'proveedor'"
                                :href="route('user.dashboard')"
                                :class="route().current('user.dashboard') ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-800 dark:text-guinda-400' : 'text-gray-600 dark:text-gray-300 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800'"
                                class="px-3 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-guinda-500 dark:bg-guinda-400"></span>
                                Mis Citas
                            </Link>
                            <!-- Modo Proveedor: Mi Negocio -->
                            <Link v-if="$page.props.auth?.user && activeRole === 'proveedor'"
                                :href="route('restaurantero.panel')"
                                :class="route().current('restaurantero.panel') ? 'bg-guinda-100 dark:bg-guinda-900/30 text-guinda-800 dark:text-guinda-400' : 'text-gray-600 dark:text-gray-300 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800'"
                                class="px-3 py-2 text-sm font-medium rounded-lg transition-colors flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-guinda-500 dark:bg-guinda-400"></span>
                                Mi Negocio
                            </Link>
                            <Link v-if="$page.props.auth?.user && $page.props.auth.user.roles?.includes('admin')"
                                :href="route('admin.dashboard')"
                                class="px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                Admin
                            </Link>
                        </div>

                        <!-- Right: theme toggle + user menu -->
                        <div class="hidden sm:flex items-center gap-2">
                            <!-- Switch de Rol Dual — solo visible si el usuario tiene ambos roles -->
                            <button v-if="tieneDualRol" @click="switchRole" :disabled="switchingRole"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition-all"
                                :class="activeRole === 'proveedor'
                                    ? 'bg-guinda-50 dark:bg-guinda-900/20 border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-300 hover:bg-guinda-100 dark:hover:bg-guinda-900/40'
                                    : 'bg-guinda-50 dark:bg-guinda-900/20 border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-300 hover:bg-guinda-100 dark:hover:bg-guinda-900/40'"
                                :title="activeRole === 'comprador' ? 'Cambiar a modo Proveedor' : 'Cambiar a modo Comprador'">
                                <svg v-if="!switchingRole" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 3M21 7.5H7.5"/>
                                </svg>
                                <svg v-else class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span>{{ activeRole === 'comprador' ? 'Comprador' : 'Proveedor' }}</span>
                            </button>
                            <ThemeToggle />
                            <NotificacionCampana v-if="$page.props.auth?.user" />
                            <div class="relative" v-if="$page.props.auth?.user">
                                <button @click="showingMenu = !showingMenu"
                                    class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm text-gray-600 dark:text-gray-300 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800 transition-colors">
                                    <div class="w-7 h-7 rounded-full bg-guinda-100 dark:bg-guinda-900/40 border border-guinda-300 dark:border-guinda-700 flex items-center justify-center">
                                        <span class="text-guinda-700 dark:text-guinda-400 text-xs font-bold">{{ $page.props.auth.user.name?.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <span class="max-w-[120px] truncate">{{ $page.props.auth.user.name }}</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div v-if="showingMenu" @click.outside="showingMenu = false"
                                    class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-xl shadow-black/10 dark:shadow-black/40 py-1 z-50">
                                    <Link :href="route('profile.show')"
                                        class="block px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800 transition-colors">
                                        Mi Perfil
                                    </Link>
                                    <div class="border-t border-gray-200 dark:border-gray-700 my-1"></div>
                                    <button @click="logout"
                                        class="block w-full text-left px-4 py-2 text-sm text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-gray-800 transition-colors">
                                        Cerrar sesión
                                    </button>
                                </div>
                            </div>
                            <template v-else>
                                <Link :href="route('login')"
                                    class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300 hover:text-guinda-800 dark:hover:text-white transition-colors">
                                    Iniciar sesión
                                </Link>
                                <Link :href="route('register')"
                                    class="px-4 py-2 text-sm font-semibold bg-guinda-800 hover:bg-guinda-700 text-white rounded-xl transition-colors shadow-sm shadow-guinda-900/20">
                                    Registrarse
                                </Link>
                            </template>
                        </div>

                        <!-- Mobile hamburger -->
                        <div class="sm:hidden flex items-center gap-2">
                            <ThemeToggle />
                            <button class="p-2 rounded-lg text-gray-500 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800 transition-colors"
                                @click="showingMenu = !showingMenu">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path v-if="!showingMenu" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile menu -->
                <div v-if="showingMenu" class="sm:hidden border-t border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-3 space-y-1 transition-colors duration-300">
                    <Link :href="route('proveedores.index')" @click="showingMenu=false"
                        class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800 rounded-xl">
                        Proveedores
                    </Link>
                    <Link v-if="$page.props.auth?.user && activeRole !== 'proveedor'" :href="route('user.dashboard')" @click="showingMenu=false"
                        class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:text-guinda-800 dark:hover:text-white hover:bg-guinda-50 dark:hover:bg-gray-800 rounded-xl">
                        Mis Citas (Comprador)
                    </Link>
                    <Link v-if="$page.props.auth?.user && activeRole === 'proveedor'"
                        :href="route('restaurantero.panel')" @click="showingMenu=false"
                        class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-guinda-50 dark:hover:bg-gray-800 rounded-xl">
                        Mi Negocio (Proveedor)
                    </Link>
                    <div v-if="$page.props.auth?.user" class="border-t border-gray-200 dark:border-gray-700 pt-2 mt-2 space-y-1">
                        <!-- Switch de rol en mobile -->
                        <button v-if="tieneDualRol" @click="switchRole(); showingMenu=false" :disabled="switchingRole"
                            class="flex items-center gap-2 w-full px-3 py-2 text-sm font-semibold rounded-xl border transition-colors"
                            :class="activeRole === 'proveedor'
                                ? 'bg-guinda-50 dark:bg-guinda-900/20 border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-300'
                                : 'bg-guinda-50 dark:bg-guinda-900/20 border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-300'">
                            <svg class="w-4 h-4 shrink-0" :class="switchingRole ? 'animate-spin' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 3M21 7.5H7.5"/>
                            </svg>
                            Cambiar a {{ activeRole === 'comprador' ? 'Proveedor' : 'Comprador' }}
                        </button>
                        <Link :href="route('profile.show')" @click="showingMenu=false"
                            class="block px-3 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-guinda-50 dark:hover:bg-gray-800 rounded-xl">
                            Mi Perfil
                        </Link>
                        <button @click="logout"
                            class="block w-full text-left px-3 py-2 text-sm text-red-500 dark:text-red-400 hover:bg-red-50 dark:hover:bg-gray-800 rounded-xl">
                            Cerrar sesión
                        </button>
                    </div>
                    <div v-else class="border-t border-gray-200 dark:border-gray-700 pt-2 mt-2 flex gap-2">
                        <Link :href="route('login')" class="flex-1 text-center px-3 py-2 text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-xl">
                            Iniciar sesión
                        </Link>
                        <Link :href="route('register')" class="flex-1 text-center px-3 py-2 text-sm font-semibold bg-guinda-800 text-white rounded-xl">
                            Registrarse
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- Page Header -->
            <header v-if="$slots.header" class="bg-white dark:bg-gray-900/60 border-b border-gray-200 dark:border-gray-800/50 transition-colors duration-300">
                <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                    <slot name="header" />
                </div>
            </header>

            <!-- Page Content -->
            <main>
                <slot />
            </main>

            <!-- Footer mejorado -->
            <footer class="bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 mt-16 transition-colors duration-300">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">

                        <!-- Columna 1: Marca -->
                        <div class="lg:col-span-1">
                            <div class="mb-4">
                                <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-10 w-auto" />
                            </div>
                            <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                Encuentro de Negocios Impulsate — conectando empresarios y proveedores de Yucatán a través de citas de networking efectivas.
                            </p>
                            <!-- Redes sociales (placeholder) -->
                            <div class="flex gap-3 mt-4">
                                <span class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 cursor-pointer hover:bg-guinda-100 dark:hover:bg-guinda-900/30 hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                </span>
                                <span class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-gray-400 cursor-pointer hover:bg-guinda-100 dark:hover:bg-guinda-900/30 hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                                </span>
                            </div>
                        </div>

                        <!-- Columna 2: Navegación -->
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-widest text-guinda-700 dark:text-guinda-400 mb-4">Navegación</h4>
                            <ul class="space-y-2.5">
                                <li><Link :href="route('home')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-guinda-800 dark:hover:text-white transition-colors">Inicio</Link></li>
                                <li><Link :href="route('proveedores.index')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-guinda-800 dark:hover:text-white transition-colors">Proveedores</Link></li>
                                <li v-if="$page.props.auth?.user"><Link :href="route('user.dashboard')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-guinda-800 dark:hover:text-white transition-colors">Mis Citas</Link></li>
                                <li v-if="!$page.props.auth?.user"><Link :href="route('register')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-guinda-800 dark:hover:text-white transition-colors">Agendar Cita</Link></li>
                                <li v-if="!$page.props.auth?.user"><Link :href="route('login')" class="text-sm text-gray-600 dark:text-gray-400 hover:text-guinda-800 dark:hover:text-white transition-colors">Iniciar sesión</Link></li>
                            </ul>
                        </div>

                        <!-- Columna 3: Contacto -->
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-widest text-guinda-700 dark:text-guinda-400 mb-4">Contacto</h4>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-2.5">
                                    <svg class="w-4 h-4 text-guinda-600 dark:text-guinda-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Av. Industrias No Contaminantes Tab 13613, Col. Sodzil Norte, C.P. 97110, Mérida, Yucatán</span>
                                </li>
                                <li class="flex items-center gap-2.5">
                                    <svg class="w-4 h-4 text-guinda-600 dark:text-guinda-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">Lun – Vie, 9:00 am – 4:00 pm</span>
                                </li>
                            </ul>
                        </div>

                        <!-- Columna 4: Programa -->
                        <div>
                            <h4 class="text-xs font-bold uppercase tracking-widest text-guinda-700 dark:text-guinda-400 mb-4">Programa</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed mb-3">
                                Iniciativa del Gobierno del Estado de Yucatán para impulsar la economía local mediante el networking empresarial.
                            </p>
                            <div class="inline-flex items-center gap-2 bg-guinda-50 dark:bg-guinda-950/50 border border-guinda-200 dark:border-guinda-900 rounded-xl px-3 py-2">
                                <svg class="w-4 h-4 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <span class="text-xs font-semibold text-guinda-700 dark:text-guinda-400">Servicio gratuito</span>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom bar -->
                    <div class="border-t border-gray-200 dark:border-gray-800 mt-10 pt-6 flex flex-col sm:flex-row justify-between items-center gap-2">
                        <p class="text-xs text-gray-500 dark:text-gray-500">
                            © {{ new Date().getFullYear() }} Encuentro de Negocios Impulsate · Gobierno del Estado de Yucatán
                        </p>
                        <p class="text-xs text-gray-400 dark:text-gray-600">
                            Desarrollado por <span class="font-semibold text-guinda-700 dark:text-guinda-400">Diego Martínez</span>
                        </p>
                    </div>
                </div>
            </footer>
        </div>
    </div>
</template>
