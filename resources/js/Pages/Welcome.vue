<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const props = defineProps({
    restauranteros: Array,
    canLogin: Boolean,
    canRegister: Boolean,
});

const page = usePage();
const auth = computed(() => page.props.auth);

const mobileMenuOpen = ref(false);

const countProveedores = ref(0);
const countCitas = ref(0);
const countPorciento = ref(0);
const statsVisible = ref(false);

function runCountUp() {
    if (statsVisible.value) return;
    statsVisible.value = true;

    const totalP = props.restauranteros?.length || 47;
    const duration = 1500;
    const steps = 50;
    let step = 0;

    const timer = setInterval(() => {
        step++;
        const progress = step / steps;
        countProveedores.value = Math.round(totalP * progress);
        countCitas.value = Math.round(12 * progress);
        countPorciento.value = Math.round(100 * progress);
        if (step >= steps) {
            clearInterval(timer);
            countProveedores.value = totalP;
            countCitas.value = 12;
            countPorciento.value = 100;
        }
    }, duration / steps);
}

onMounted(() => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                if (entry.target.dataset.stats) runCountUp();
            }
        });
    }, { threshold: 0.15 });

    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
});

const pasos = [
    { icon: '📝', titulo: 'Regístrate gratis', descripcion: 'En segundos, solo necesitas tu nombre y correo electrónico. Sin pagos, sin compromisos.' },
    { icon: '🔍', titulo: 'Elige un proveedor', descripcion: 'Explora los perfiles de proveedores verificados de todo el Estado de Yucatán.' },
    { icon: '📅', titulo: 'Agenda tu cita', descripcion: 'Selecciona la fecha y hora que mejor te convenga en el calendario de disponibilidad.' },
    { icon: '🤝', titulo: 'Haz networking', descripcion: 'Conecta en nuestra oficina en Mérida, Yucatán y construye relaciones de valor.' },
];
</script>

<template>
    <Head title="Encuentro de Negocios Impulsate — Yucatán" />

    <div class="min-h-screen bg-white dark:bg-gray-950 text-gray-900 dark:text-white">

        <!-- NAVBAR -->
        <nav class="fixed top-0 left-0 right-0 z-50 bg-white/90 dark:bg-gray-950/90 backdrop-blur border-b border-gray-200 dark:border-guinda-500/20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <Link :href="route('home')" class="flex items-center shrink-0">
                        <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-9 w-auto" />
                    </Link>

                    <div class="hidden md:flex items-center gap-6">
                        <Link :href="route('restauranteros.index')" class="text-sm text-gray-600 dark:text-gray-300 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                            Proveedores
                        </Link>
                        <a href="#como-funciona" class="text-sm text-gray-600 dark:text-gray-300 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                            Cómo funciona
                        </a>
                        <a href="#ubicacion" class="text-sm text-gray-600 dark:text-gray-300 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                            Contacto
                        </a>
                    </div>

                    <div class="hidden md:flex items-center gap-3">
                        <ThemeToggle />
                        <template v-if="auth.user">
                            <Link :href="route('user.dashboard')"
                                  class="text-sm bg-guinda-700 hover:bg-guinda-600 text-white font-bold px-4 py-1.5 rounded-lg transition-colors">
                                Mi Panel
                            </Link>
                        </template>
                        <template v-else>
                            <Link v-if="canLogin" :href="route('login')"
                                  class="text-sm border border-gray-300 dark:border-gray-600 hover:border-guinda-500 text-gray-600 dark:text-gray-300 hover:text-guinda-600 dark:hover:text-guinda-400 px-4 py-1.5 rounded-lg transition-colors">
                                Iniciar sesión
                            </Link>
                            <Link v-if="canRegister" :href="route('register')"
                                  class="text-sm bg-guinda-700 hover:bg-guinda-600 text-white font-bold px-4 py-1.5 rounded-lg transition-colors">
                                Registrarse
                            </Link>
                        </template>
                    </div>

                    <button @click="mobileMenuOpen = !mobileMenuOpen" class="md:hidden p-2 text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div v-if="mobileMenuOpen" class="md:hidden border-t border-gray-200 dark:border-gray-800 py-4 space-y-3">
                    <Link :href="route('restauranteros.index')" class="block text-sm text-gray-700 dark:text-gray-300 py-2">Proveedores</Link>
                    <a href="#como-funciona" class="block text-sm text-gray-700 dark:text-gray-300 py-2">Cómo funciona</a>
                    <a href="#ubicacion" class="block text-sm text-gray-700 dark:text-gray-300 py-2">Contacto</a>
                    <div class="pt-2 flex flex-col gap-2">
                        <Link v-if="!auth.user && canLogin" :href="route('login')"
                              class="text-center text-sm border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 px-4 py-2.5 rounded-lg">
                            Iniciar sesión
                        </Link>
                        <Link v-if="!auth.user && canRegister" :href="route('register')"
                              class="text-center text-sm bg-guinda-700 text-white font-bold px-4 py-2.5 rounded-lg">
                            Registrarse gratis
                        </Link>
                        <Link v-if="auth.user" :href="route('user.dashboard')"
                              class="text-center text-sm bg-guinda-700 text-white font-bold px-4 py-2.5 rounded-lg">
                            Mi Panel
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- HERO -->
        <section class="hero-section relative min-h-screen flex items-center pt-16 overflow-hidden">
            <div class="absolute top-20 left-10 w-96 h-96 rounded-full blur-3xl opacity-20 dark:opacity-10 animate-pulse"
                 style="background: radial-gradient(circle, #8b1028, transparent)"></div>
            <div class="absolute bottom-20 right-10 w-80 h-80 rounded-full blur-3xl opacity-20 dark:opacity-10 animate-pulse"
                 style="background: radial-gradient(circle, #8b1028, transparent); animation-delay: 1.5s"></div>

            <div class="absolute inset-0 pointer-events-none select-none overflow-hidden">
                <span class="absolute text-4xl opacity-10" style="top: 15%; left: 8%; animation: float 6s ease-in-out infinite">💼</span>
                <span class="absolute text-5xl opacity-10" style="top: 60%; left: 5%; animation: float 8s ease-in-out infinite; animation-delay: 1s">🤝</span>
                <span class="absolute text-3xl opacity-10" style="top: 25%; right: 8%; animation: float 7s ease-in-out infinite; animation-delay: 2s">📊</span>
                <span class="absolute text-4xl opacity-10" style="top: 70%; right: 12%; animation: float 5s ease-in-out infinite; animation-delay: 0.5s">🏛️</span>
                <span class="absolute text-3xl opacity-10" style="top: 45%; left: 15%; animation: float 9s ease-in-out infinite; animation-delay: 3s">🎯</span>
                <span class="absolute text-4xl opacity-10" style="top: 80%; left: 45%; animation: float 6s ease-in-out infinite; animation-delay: 1.5s">🌟</span>
            </div>

            <div class="relative z-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center py-20">
                <div class="inline-flex items-center gap-2 border border-guinda-400/40 dark:border-guinda-500/30 bg-guinda-50 dark:bg-guinda-500/5 text-guinda-700 dark:text-guinda-400 text-xs font-semibold px-4 py-1.5 rounded-full mb-8 tracking-wide">
                    <span class="w-1.5 h-1.5 rounded-full bg-guinda-600 dark:bg-guinda-400 animate-pulse"></span>
                    Programa Oficial &bull; Gobierno de Yucatán &bull; 2026
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-7xl font-black leading-tight mb-6 text-gray-900 dark:text-white">
                    Conecta, negocia y<br />
                    <span class="text-transparent bg-clip-text" style="background-image: linear-gradient(90deg, #c8113b, #f36178, #c8113b)">
                        haz crecer tu empresa
                    </span>
                </h1>

                <p class="text-lg sm:text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto mb-10 leading-relaxed">
                    Conecta con los mejores proveedores de Yucatán de manera gratuita y genera nuevas oportunidades de negocio. Impúlsate es una iniciativa que fortalece el ecosistema empresarial y emprendedor del estado.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-14">
                    <Link :href="route('restauranteros.index')"
                          class="px-8 py-3.5 bg-guinda-700 hover:bg-guinda-600 text-white font-bold rounded-xl transition-all duration-200 shadow-lg shadow-guinda-900/20 dark:shadow-guinda-900/30 hover:-translate-y-0.5 text-sm">
                        Explorar Proveedores
                    </Link>
                    <Link v-if="canRegister" :href="route('register')"
                          class="px-8 py-3.5 border border-guinda-300 dark:border-gray-600 hover:border-guinda-500 dark:hover:border-guinda-500 text-guinda-700 dark:text-gray-300 hover:text-guinda-600 dark:hover:text-guinda-400 font-semibold rounded-xl transition-all duration-200 hover:-translate-y-0.5 text-sm">
                        Registrarse gratis
                    </Link>
                </div>

                <div class="text-sm text-gray-500 dark:text-gray-500 mb-14">
                    📍 Av. Industrias No Contaminantes Tab 13613, Col. Sodzil Norte, C.P. 97110, Mérida, Yucatán
                </div>

                <div data-stats="true" class="fade-up flex flex-col sm:flex-row items-center justify-center gap-8 sm:gap-12 text-center">
                    <div>
                        <div class="text-4xl font-black text-guinda-800 dark:text-white">{{ countProveedores }}+</div>
                        <div class="text-sm text-gray-500 mt-1">Proveedores activos</div>
                    </div>
                    <div class="hidden sm:block w-px h-12 bg-guinda-200 dark:bg-gray-700"></div>
                    <div>
                        <div class="text-4xl font-black text-guinda-800 dark:text-white">{{ countCitas }}</div>
                        <div class="text-sm text-gray-500 mt-1">Citas máx. por usuario</div>
                    </div>
                    <div class="hidden sm:block w-px h-12 bg-guinda-200 dark:bg-gray-700"></div>
                    <div>
                        <div class="text-4xl font-black text-guinda-800 dark:text-white">{{ countPorciento }}%</div>
                        <div class="text-sm text-gray-500 mt-1">Gratuito</div>
                    </div>
                </div>
            </div>

            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce text-guinda-300 dark:text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </section>

        <!-- QUÉ ES IMPULSATE -->
        <section class="py-24 bg-gray-100/70 dark:bg-gray-900/30">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16 fade-up">
                    <h2 class="text-3xl sm:text-4xl font-black mb-4">
                        <span class="text-transparent bg-clip-text" style="background-image: linear-gradient(90deg, #c8113b, #f36178)">
                            ¿Qué es Impúlsate-Encuentros de Negocios?
                        </span>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-400 max-w-3xl mx-auto leading-relaxed">
                        "Impúlsate-Encuentros de Negocios" es la plataforma de citas de 1-1 del IYEM, creada para ayudar a emprendedores y empresas a conectar con clientes potenciales, proveedores y aliados estratégicos. A través de reuniones previamente programadas, los participantes pueden presentar sus productos y servicios directamente a las empresas que buscan, generando oportunidades reales de venta, colaboración y crecimiento.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div v-for="(card, i) in [
                        { icon: '🏛️', titulo: 'Programa Gubernamental', desc: 'Respaldado por el Gobierno del Estado de Yucatán con el objetivo de fortalecer el ecosistema empresarial local.' },
                        { icon: '🤝', titulo: 'Networking Real', desc: 'Citas presenciales en nuestra oficina en Mérida, Yucatán. Conexiones de valor que trascienden lo digital.' },
                        { icon: '🎯', titulo: 'Impacto Directo', desc: 'Cada cita está diseñada para generar oportunidades reales de negocio, colaboración y crecimiento para ambas partes.' },
                    ]" :key="i"
                         class="fade-up bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-7 hover:border-guinda-500/30 transition-all duration-300"
                         :style="`transition-delay: ${i * 100}ms`">
                        <div class="text-4xl mb-5">{{ card.icon }}</div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-lg mb-3">{{ card.titulo }}</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">{{ card.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CÓMO FUNCIONA -->
        <section id="como-funciona" class="py-24">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16 fade-up">
                    <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-4">¿Cómo funciona?</h2>
                    <p class="text-gray-600 dark:text-gray-400 max-w-xl mx-auto">Cuatro simples pasos para comenzar a hacer networking empresarial en Yucatán.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="(paso, i) in pasos" :key="i"
                        class="fade-up relative bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-7 hover:border-guinda-500/30 transition-all duration-300 group"
                        :style="`transition-delay: ${i * 120}ms`">
                        <div class="text-5xl font-black text-guinda-500/20 group-hover:text-guinda-500/30 transition-colors mb-4 leading-none">
                            0{{ i + 1 }}
                        </div>
                        <div class="text-3xl mb-4">{{ paso.icon }}</div>
                        <h3 class="font-bold text-gray-900 dark:text-white mb-3">{{ paso.titulo }}</h3>
                        <p class="text-gray-600 dark:text-gray-400 text-sm leading-relaxed">{{ paso.descripcion }}</p>

                        <div v-if="i < 3" class="hidden lg:block absolute -right-3 top-1/2 -translate-y-1/2 z-10">
                            <div class="w-6 h-6 rounded-full bg-white dark:bg-gray-950 border border-gray-300 dark:border-guinda-500/30 flex items-center justify-center">
                                <svg class="w-3 h-3 text-guinda-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- PROVEEDORES DESTACADOS -->
        <section class="py-24 bg-gray-50 dark:bg-gray-900/20">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16 fade-up">
                    <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-4">Proveedores Destacados</h2>
                    <p class="text-gray-600 dark:text-gray-400 max-w-xl mx-auto">Los perfiles más recientes verificados por el programa Impulsate.</p>
                </div>

                <div v-if="restauranteros && restauranteros.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Link v-for="(r, i) in restauranteros" :key="r.id"
                        :href="route('restauranteros.show', r.id)"
                        class="fade-up bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden hover:border-guinda-500/50 hover:-translate-y-1 hover:shadow-lg hover:shadow-guinda-500/5 transition-all duration-300 group"
                        :style="`transition-delay: ${i * 80}ms`">
                        <div class="h-44 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 flex items-center justify-center relative overflow-hidden">
                            <img v-if="r.logo_path" :src="'/storage/' + r.logo_path" :alt="r.nombre_restaurante"
                                class="w-full h-full object-cover" />
                            <div v-else class="text-6xl group-hover:scale-110 transition-transform duration-300">🏢</div>
                            <div v-if="r.municipio" class="absolute top-3 left-3 bg-white/90 dark:bg-gray-950/80 text-guinda-700 dark:text-guinda-400 text-xs font-medium px-2.5 py-1 rounded-full backdrop-blur">
                                📍 {{ r.municipio }}
                            </div>
                        </div>
                        <div class="p-5">
                            <h3 class="font-bold text-gray-900 dark:text-white text-base mb-2 leading-snug">{{ r.nombre_restaurante }}</h3>
                            <p v-if="r.descripcion" class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed line-clamp-2 mb-3">
                                {{ r.descripcion }}
                            </p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">{{ r.citas_count || 0 }} citas</span>
                                <span class="text-guinda-600 dark:text-guinda-400 text-sm font-medium group-hover:text-guinda-500 dark:group-hover:text-guinda-300 transition-colors">Ver perfil →</span>
                            </div>
                        </div>
                    </Link>
                </div>

                <div v-else class="text-center py-16 text-gray-500 dark:text-gray-600 fade-up">
                    <div class="text-6xl mb-4">🏢</div>
                    <p class="text-lg">Aún no hay proveedores registrados.</p>
                </div>

                <div class="text-center mt-12 fade-up">
                    <Link :href="route('restauranteros.index')"
                          class="inline-flex items-center gap-2 border border-guinda-500/30 hover:border-guinda-500 text-guinda-600 dark:text-guinda-400 hover:text-guinda-500 dark:hover:text-guinda-300 font-semibold px-6 py-3 rounded-xl transition-all duration-200">
                        Ver todos los proveedores
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>
        </section>

        <!-- UBICACIÓN -->
        <section id="ubicacion" class="py-24 bg-gray-100 dark:bg-gray-900/30">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12 fade-up">
                    <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-4">Nuestra Oficina</h2>
                    <p class="text-gray-600 dark:text-gray-400 max-w-xl mx-auto">Todas las citas se realizan de forma presencial en nuestras instalaciones en Mérida, Yucatán.</p>
                </div>

                <div class="fade-up bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2">
                        <div class="p-8 space-y-6">
                            <div>
                                <h3 class="font-bold text-gray-900 dark:text-white text-lg mb-4">Encuentro de Negocios Impulsate</h3>
                                <div class="space-y-4 text-sm text-gray-600 dark:text-gray-400">
                                    <div class="flex items-start gap-3">
                                        <span class="text-guinda-600 dark:text-guinda-400 mt-0.5">📍</span>
                                        <div>
                                            <p class="text-gray-800 dark:text-white font-medium">Dirección</p>
                                            <p>Av. Industrias No Contaminantes Tab 13613<br />Col. Sodzil Norte, C.P. 97110<br />Mérida, Yucatán</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <span class="text-guinda-600 dark:text-guinda-400 mt-0.5">🕐</span>
                                        <div>
                                            <p class="text-gray-800 dark:text-white font-medium">Horario de atención</p>
                                            <p>Lunes a Viernes: 9:00 am — 4:00 pm</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <span class="text-guinda-600 dark:text-guinda-400 mt-0.5">✉️</span>
                                        <div>
                                            <p class="text-gray-800 dark:text-white font-medium">Correo</p>
                                            <p>impulsate@iyemyucatan.com</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pt-4 border-t border-gray-200 dark:border-gray-800">
                                <p class="text-xs text-gray-500 dark:text-gray-600">
                                    Programa operado por el Gobierno del Estado de Yucatán.
                                </p>
                            </div>
                        </div>

                        <div class="h-72 md:h-auto bg-gray-200 dark:bg-gray-800 flex items-center justify-center relative overflow-hidden">
                            <iframe
                                src="https://maps.google.com/maps?q=Av+Industrias+No+Contaminantes+13613+Sodzil+Norte+Merida+Yucatan+Mexico&t=&z=16&ie=UTF8&iwloc=&output=embed"
                                width="100%" height="100%"
                                style="border:0; filter: grayscale(50%) invert(8%);"
                                allowfullscreen="" loading="lazy"
                                class="absolute inset-0"
                            ></iframe>
                            <div class="relative z-10 text-center p-8 pointer-events-none">
                                <div class="text-5xl mb-3">🗺️</div>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">Col. Sodzil Norte, Mérida, Yucatán</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA FINAL -->
        <section class="py-24">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <div class="fade-up border border-guinda-500/20 rounded-3xl p-12"
                     style="background: linear-gradient(135deg, #150307 0%, #0d0204 100%)">
                    <div class="text-5xl mb-6">🤝</div>
                    <h2 class="text-3xl sm:text-4xl font-black text-white mb-5">
                        ¿Listo para hacer networking<br />empresarial?
                    </h2>
                    <p class="text-gray-400 mb-10 text-lg leading-relaxed">
                        Únete al programa gubernamental que está transformando el ecosistema empresarial yucateco. Registra tu cuenta y agenda tu primera cita hoy mismo.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link v-if="canRegister" :href="route('register')"
                              class="px-8 py-4 bg-guinda-700 hover:bg-guinda-600 text-white font-bold rounded-xl transition-all duration-200 shadow-lg shadow-guinda-900/30 hover:-translate-y-0.5 text-sm">
                            Comenzar ahora — Es gratuito
                        </Link>
                        <Link :href="route('restauranteros.index')"
                              class="px-8 py-4 border border-gray-700 hover:border-guinda-500/50 text-gray-300 hover:text-guinda-400 font-semibold rounded-xl transition-all duration-200 hover:-translate-y-0.5 text-sm">
                            Explorar proveedores
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- FOOTER -->
        <footer class="bg-gray-100 dark:bg-gray-950 border-t border-gray-200 dark:border-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-10">

                    <!-- Brand + descripción -->
                    <div>
                        <div class="flex items-center mb-5">
                            <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-10 w-auto" />
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed mb-5">
                            Programa oficial del Gobierno del Estado de Yucatán para impulsar el ecosistema empresarial a través de citas estratégicas de networking.
                        </p>
                        <div class="inline-flex items-center gap-2 bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 text-guinda-700 dark:text-guinda-400 text-xs font-semibold px-3 py-1.5 rounded-full">
                            🏛️ Gobierno de Yucatán · 2026
                        </div>
                    </div>

                    <!-- Navegación -->
                    <div>
                        <h4 class="font-bold text-gray-700 dark:text-gray-300 text-xs uppercase tracking-wider mb-5">Navegación</h4>
                        <ul class="space-y-3">
                            <li>
                                <Link :href="route('restauranteros.index')" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                                    Proveedores
                                </Link>
                            </li>
                            <li>
                                <a href="#como-funciona" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                                    Cómo funciona
                                </a>
                            </li>
                            <li>
                                <a href="#ubicacion" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                                    Nuestra oficina
                                </a>
                            </li>
                            <li v-if="canRegister">
                                <Link :href="route('register')" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                                    Registrarse gratis
                                </Link>
                            </li>
                            <li v-if="canLogin">
                                <Link :href="route('login')" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">
                                    Iniciar sesión
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Contacto -->
                    <div>
                        <h4 class="font-bold text-gray-700 dark:text-gray-300 text-xs uppercase tracking-wider mb-5">Contacto</h4>
                        <ul class="space-y-4">
                            <li class="flex items-start gap-3">
                                <span class="text-guinda-600 dark:text-guinda-400 text-base mt-0.5 shrink-0">📍</span>
                                <div>
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-0.5">Dirección</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                                        Av. Industrias No Contaminantes Tab 13613<br />
                                        Col. Sodzil Norte, C.P. 97110<br />
                                        Mérida, Yucatán
                                    </p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-guinda-600 dark:text-guinda-400 text-base mt-0.5 shrink-0">🕐</span>
                                <div>
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-0.5">Horario</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Lunes a Viernes: 9:00 — 16:00 h</p>
                                </div>
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="text-guinda-600 dark:text-guinda-400 text-base mt-0.5 shrink-0">✉️</span>
                                <div>
                                    <p class="text-xs font-semibold text-gray-700 dark:text-gray-300 mb-0.5">Correo</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">impulsate@iyemyucatan.com</p>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Bottom bar -->
            <div class="border-t border-gray-200 dark:border-gray-800">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-xs text-gray-400 dark:text-gray-600 text-center">
                        © {{ new Date().getFullYear() }} Encuentro de Negocios Impulsate — Instituto Yucateco de Emprendedores
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Desarrollado por <span class="font-semibold text-guinda-700 dark:text-guinda-400">Diego Martínez</span>
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    33% { transform: translateY(-15px) rotate(3deg); }
    66% { transform: translateY(-8px) rotate(-2deg); }
}

.fade-up {
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.65s ease, transform 0.65s ease;
}

.fade-up.visible {
    opacity: 1;
    transform: translateY(0);
}


</style>

<style>
.hero-section {
    background: linear-gradient(135deg, #fff5f6 0%, #ffffff 50%, #fdf4f5 100%);
}

.dark .hero-section {
    background: linear-gradient(135deg, #0d0204 0%, #150307 40%, #1a0608 100%);
}
</style>
