<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted } from 'vue';
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
const navSolido = ref(false);
const statsVisible = ref(false);

const countProveedores = ref(0);
const countCitas = ref(0);
const countPorciento = ref(0);

let scrollHandler = null;

function runCountUp() {
    if (statsVisible.value) return;
    statsVisible.value = true;
    const totalP = props.restauranteros?.length || 47;
    const duration = 1800;
    const steps = 60;
    let step = 0;
    const timer = setInterval(() => {
        step++;
        const ease = 1 - Math.pow(2, -10 * (step / steps));
        countProveedores.value = Math.round(totalP * ease);
        countCitas.value = Math.round(6 * ease);
        countPorciento.value = Math.round(100 * ease);
        if (step >= steps) {
            clearInterval(timer);
            countProveedores.value = totalP;
            countCitas.value = 6;
            countPorciento.value = 100;
        }
    }, duration / steps);
}

onMounted(() => {
    scrollHandler = () => { navSolido.value = window.scrollY > 72; };
    window.addEventListener('scroll', scrollHandler, { passive: true });

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

onUnmounted(() => {
    if (scrollHandler) window.removeEventListener('scroll', scrollHandler);
});

const proveedoresDestacados = computed(() => (props.restauranteros || []).slice(0, 6));

const pasos = [
    {
        titulo: 'Regístrate gratis',
        descripcion: 'En segundos, solo necesitas tu nombre y correo electrónico. Sin pagos, sin compromisos.',
        path: 'M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10',
    },
    {
        titulo: 'Elige un proveedor',
        descripcion: 'Explora los perfiles de proveedores verificados de todo el Estado de Yucatán.',
        path: 'M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z',
    },
    {
        titulo: 'Agenda tu cita',
        descripcion: 'Selecciona la fecha y hora que mejor te convenga en el calendario de disponibilidad.',
        path: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
    },
    {
        titulo: 'Haz networking',
        descripcion: 'Conecta en nuestra oficina en Mérida, Yucatán y construye relaciones de valor.',
        path: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    },
];

const queEsCards = [
    {
        titulo: 'Programa Gubernamental',
        desc: 'Respaldado por el Gobierno del Estado de Yucatán para fortalecer el ecosistema empresarial local.',
        path: 'M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z',
    },
    {
        titulo: 'Networking Real',
        desc: 'Citas presenciales en nuestra oficina en Mérida. Conexiones de valor que trascienden lo digital.',
        path: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    },
    {
        titulo: 'Impacto Directo',
        desc: 'Cada cita está diseñada para generar oportunidades reales de negocio, colaboración y crecimiento.',
        path: 'M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941',
    },
];
</script>

<template>
    <Head title="Encuentro de Negocios Impulsate — Yucatán" />

    <div class="min-h-screen bg-white dark:bg-gray-950 text-gray-900 dark:text-white">

        <!-- ═══════════════════════════ NAVBAR ═══════════════════════════ -->
        <nav :class="['fixed top-0 left-0 right-0 z-50 transition-all duration-300',
            navSolido
                ? 'bg-white/95 dark:bg-gray-950/95 backdrop-blur-md border-b border-gray-200 dark:border-gray-800/80 shadow-sm'
                : 'bg-transparent']">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <Link :href="route('home')" class="flex items-center shrink-0">
                        <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-9 w-auto" />
                    </Link>

                    <div class="hidden md:flex items-center gap-6">
                        <Link :href="route('restauranteros.index')"
                              :class="['text-sm font-medium transition-colors',
                                  navSolido ? 'text-gray-600 dark:text-gray-300 hover:text-guinda-600 dark:hover:text-guinda-400'
                                            : 'text-guinda-800 dark:text-white/80 hover:text-guinda-600 dark:hover:text-white']">
                            Proveedores
                        </Link>
                        <a href="#como-funciona"
                           :class="['text-sm font-medium transition-colors',
                               navSolido ? 'text-gray-600 dark:text-gray-300 hover:text-guinda-600 dark:hover:text-guinda-400'
                                         : 'text-guinda-800 dark:text-white/80 hover:text-guinda-600 dark:hover:text-white']">
                            Cómo funciona
                        </a>
                        <a href="#ubicacion"
                           :class="['text-sm font-medium transition-colors',
                               navSolido ? 'text-gray-600 dark:text-gray-300 hover:text-guinda-600 dark:hover:text-guinda-400'
                                         : 'text-guinda-800 dark:text-white/80 hover:text-guinda-600 dark:hover:text-white']">
                            Contacto
                        </a>
                    </div>

                    <div class="hidden md:flex items-center gap-3">
                        <ThemeToggle />
                        <template v-if="auth.user">
                            <Link :href="route('user.dashboard')"
                                  class="text-sm bg-guinda-700 hover:bg-guinda-600 text-white font-bold px-4 py-1.5 rounded-lg transition-all duration-200 hover:-translate-y-0.5">
                                Mi Panel
                            </Link>
                        </template>
                        <template v-else>
                            <Link v-if="canLogin" :href="route('login')"
                                  :class="['text-sm font-medium px-4 py-1.5 rounded-lg border transition-all duration-200',
                                      navSolido
                                          ? 'border-gray-300 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:border-guinda-500 hover:text-guinda-600 dark:hover:text-guinda-400'
                                          : 'border-guinda-300 dark:border-white/30 text-guinda-700 dark:text-white/90 hover:border-guinda-500 dark:hover:border-white hover:text-guinda-600 dark:hover:text-white']">
                                Iniciar sesión
                            </Link>
                            <Link v-if="canRegister" :href="route('register')"
                                  class="text-sm bg-guinda-700 hover:bg-guinda-600 text-white font-bold px-4 py-1.5 rounded-lg transition-all duration-200 hover:-translate-y-0.5">
                                Registrarse gratis
                            </Link>
                        </template>
                    </div>

                    <div class="md:hidden flex items-center gap-2">
                        <ThemeToggle />
                        <button @click="mobileMenuOpen = !mobileMenuOpen"
                                :class="['p-2 rounded-lg transition-colors',
                                    navSolido ? 'text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800'
                                              : 'text-guinda-700 dark:text-white/80 hover:text-guinda-600 dark:hover:text-white']">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div v-if="mobileMenuOpen" class="md:hidden bg-white dark:bg-gray-950 border-t border-gray-200 dark:border-gray-800 py-3 space-y-1">
                    <Link :href="route('restauranteros.index')" class="block text-sm font-medium text-gray-700 dark:text-gray-300 px-4 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">Proveedores</Link>
                    <a href="#como-funciona" @click="mobileMenuOpen=false" class="block text-sm font-medium text-gray-700 dark:text-gray-300 px-4 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">Cómo funciona</a>
                    <a href="#ubicacion" @click="mobileMenuOpen=false" class="block text-sm font-medium text-gray-700 dark:text-gray-300 px-4 py-2.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800">Contacto</a>
                    <div class="pt-3 px-4 flex flex-col gap-2 border-t border-gray-100 dark:border-gray-800 mt-2">
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

        <!-- ═══════════════════════════ HERO ═══════════════════════════ -->
        <section class="hero-section relative min-h-screen flex items-center justify-center overflow-hidden">
            <!-- Decoraciones geométricas SVG (sin emojis) -->
            <div class="absolute inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
                <!-- Círculos flotantes -->
                <div class="shape-a absolute top-[14%] left-[5%] w-80 h-80 rounded-full border border-guinda-500/20 dark:border-guinda-400/10"></div>
                <div class="shape-b absolute bottom-[18%] right-[6%] w-60 h-60 rounded-full border-2 border-guinda-600/15 dark:border-guinda-400/8"></div>
                <div class="shape-c absolute top-[55%] right-[14%] w-5 h-5 rounded-full bg-guinda-600/25 dark:bg-guinda-400/15"></div>
                <div class="shape-d absolute top-[32%] left-[16%] w-3.5 h-3.5 rounded-full bg-guinda-500/20 dark:bg-guinda-400/12"></div>
                <!-- Hexágono decorativo izq -->
                <div class="shape-e absolute bottom-[28%] left-[7%] opacity-10 dark:opacity-5">
                    <svg width="88" height="100" viewBox="0 0 88 100" fill="none">
                        <path d="M44 2L85.7 24V68L44 90L2.3 68V24L44 2Z" stroke="#8b1028" stroke-width="2"/>
                    </svg>
                </div>
                <!-- Hexágono decorativo der -->
                <div class="shape-f absolute top-[18%] right-[4%] opacity-10 dark:opacity-5">
                    <svg width="58" height="66" viewBox="0 0 58 66" fill="none">
                        <path d="M29 2L55.7 17V47L29 62L2.3 47V17L29 2Z" stroke="#8b1028" stroke-width="1.5"/>
                    </svg>
                </div>
                <!-- Patrón de puntos (fondo) -->
                <svg class="absolute inset-0 w-full h-full opacity-[0.18] dark:opacity-[0.06]" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <pattern id="dotgrid" x="0" y="0" width="28" height="28" patternUnits="userSpaceOnUse">
                            <circle cx="2" cy="2" r="1.2" fill="#8b1028"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#dotgrid)"/>
                </svg>
            </div>

            <!-- Orbes de luz -->
            <div class="absolute inset-0 pointer-events-none" aria-hidden="true">
                <div class="absolute top-[-8%] left-[-8%] w-[55%] h-[55%] rounded-full opacity-20 dark:opacity-10"
                     style="background: radial-gradient(circle at center, #8b1028 0%, transparent 70%); filter: blur(70px)"></div>
                <div class="absolute bottom-[-8%] right-[-4%] w-[45%] h-[45%] rounded-full opacity-15 dark:opacity-8"
                     style="background: radial-gradient(circle at center, #8b1028 0%, transparent 70%); filter: blur(80px)"></div>
            </div>

            <!-- Contenido central -->
            <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center pt-24 pb-20">
                <div class="hero-badge inline-flex items-center gap-2 border border-guinda-400/40 dark:border-guinda-500/30 bg-guinda-50/80 dark:bg-guinda-500/5 backdrop-blur text-guinda-700 dark:text-guinda-400 text-xs font-semibold px-4 py-1.5 rounded-full mb-8 tracking-wide">
                    <span class="w-1.5 h-1.5 rounded-full bg-guinda-600 dark:bg-guinda-400 animate-pulse"></span>
                    Programa Oficial &bull; Gobierno de Yucatán &bull; 2026
                </div>

                <h1 class="hero-title text-4xl sm:text-5xl lg:text-6xl xl:text-7xl font-black leading-[1.08] mb-5 text-gray-900 dark:text-white">
                    Conecta, negocia y<br />
                    <span class="text-transparent bg-clip-text hero-gradient-text block mt-2">
                        haz crecer tu empresa
                    </span>
                </h1>

                <!-- Línea decorativa animada -->
                <div class="hero-line w-16 h-1 bg-gradient-to-r from-guinda-600 to-guinda-400 rounded-full mx-auto mb-8"></div>

                <p class="hero-text text-base sm:text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto mb-10 leading-relaxed">
                    Conecta con los mejores proveedores de Yucatán de manera gratuita y genera nuevas oportunidades de negocio. Impúlsate es una iniciativa que fortalece el ecosistema empresarial y emprendedor del estado.
                </p>

                <div class="hero-ctas flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link :href="route('restauranteros.index')"
                          class="px-8 py-3.5 bg-guinda-700 hover:bg-guinda-600 text-white font-bold rounded-xl transition-all duration-200 shadow-lg shadow-guinda-900/25 hover:-translate-y-0.5 text-sm w-full sm:w-auto text-center">
                        Explorar Proveedores
                    </Link>
                    <Link v-if="canRegister" :href="route('register')"
                          class="px-8 py-3.5 border border-guinda-300/60 dark:border-white/20 hover:border-guinda-500 dark:hover:border-white/40 text-guinda-700 dark:text-white/85 hover:bg-guinda-50/80 dark:hover:bg-white/5 font-semibold rounded-xl transition-all duration-200 hover:-translate-y-0.5 text-sm w-full sm:w-auto text-center">
                        Registrarse gratis
                    </Link>
                </div>
            </div>

            <!-- Flecha de scroll -->
            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce text-guinda-400/50 dark:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </section>

        <!-- ═══════════════════════════ STATS ═══════════════════════════ -->
        <section class="bg-guinda-900 dark:bg-guinda-950 py-16">
            <div data-stats="true" class="fade-up max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-10 sm:gap-0 text-center text-white">
                    <div class="sm:border-r sm:border-guinda-700/40">
                        <div class="text-5xl sm:text-6xl font-black mb-2 tabular-nums">{{ countProveedores }}+</div>
                        <div class="text-guinda-300 text-xs font-semibold tracking-widest uppercase">Proveedores activos</div>
                    </div>
                    <div class="sm:border-r sm:border-guinda-700/40">
                        <div class="text-5xl sm:text-6xl font-black mb-2 tabular-nums">{{ countCitas }}</div>
                        <div class="text-guinda-300 text-xs font-semibold tracking-widest uppercase">Número de eventos realizados</div>
                    </div>
                    <div>
                        <div class="text-5xl sm:text-6xl font-black mb-2 tabular-nums">{{ countPorciento }}%</div>
                        <div class="text-guinda-300 text-xs font-semibold tracking-widest uppercase">Completamente gratuito</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════ QUÉ ES ═══════════════════════════ -->
        <section class="py-24 bg-gray-50 dark:bg-gray-900/30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
                    <!-- Texto + Cards -->
                    <div class="fade-up">
                        <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-6 leading-tight">
                            ¿Qué es
                            <span class="text-transparent bg-clip-text" style="background-image: linear-gradient(90deg, #c8113b, #f36178)">
                                Impúlsate-Encuentros de Negocios?
                            </span>
                        </h2>
                        <p class="text-gray-600 dark:text-gray-400 leading-relaxed text-base mb-10">
                            "Impúlsate-Encuentros de Negocios" es la plataforma de citas de 1-1 del IYEM, creada para ayudar a emprendedores y empresas a conectar con clientes potenciales, proveedores y aliados estratégicos. A través de reuniones previamente programadas, los participantes pueden presentar sus productos y servicios directamente a las empresas que buscan, generando oportunidades reales de venta, colaboración y crecimiento.
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div v-for="(card, i) in queEsCards" :key="i"
                                 class="bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 hover:border-guinda-500/30 hover:shadow-md transition-all duration-300"
                                 :style="`transition-delay: ${i * 80}ms`">
                                <div class="w-10 h-10 rounded-xl bg-guinda-50 dark:bg-guinda-500/10 flex items-center justify-center mb-4">
                                    <svg class="w-5 h-5 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="card.path" />
                                    </svg>
                                </div>
                                <h3 class="font-bold text-gray-900 dark:text-white text-sm mb-2">{{ card.titulo }}</h3>
                                <p class="text-gray-500 dark:text-gray-400 text-xs leading-relaxed">{{ card.desc }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Ilustración SVG -->
                    <div class="fade-up hidden lg:flex items-center justify-center">
                        <svg viewBox="0 0 420 340" fill="none" xmlns="http://www.w3.org/2000/svg" class="w-full max-w-md opacity-90">
                            <!-- Tarjeta central -->
                            <rect x="110" y="110" width="200" height="120" rx="20" fill="#8b1028" fill-opacity="0.07" stroke="#8b1028" stroke-opacity="0.18" stroke-width="1.5"/>
                            <!-- Líneas conectoras punteadas -->
                            <line x1="78" y1="82" x2="158" y2="132" stroke="#8b1028" stroke-opacity="0.25" stroke-width="1.5" stroke-dasharray="5 4"/>
                            <line x1="342" y1="82" x2="262" y2="132" stroke="#8b1028" stroke-opacity="0.25" stroke-width="1.5" stroke-dasharray="5 4"/>
                            <line x1="78" y1="258" x2="158" y2="208" stroke="#8b1028" stroke-opacity="0.25" stroke-width="1.5" stroke-dasharray="5 4"/>
                            <line x1="342" y1="258" x2="262" y2="208" stroke="#8b1028" stroke-opacity="0.25" stroke-width="1.5" stroke-dasharray="5 4"/>
                            <!-- Avatar comprador arr-izq -->
                            <circle cx="60" cy="70" r="30" fill="#8b1028" fill-opacity="0.10" stroke="#8b1028" stroke-opacity="0.28" stroke-width="1.5"/>
                            <circle cx="60" cy="61" r="11" fill="#8b1028" fill-opacity="0.35"/>
                            <path d="M38 92 Q60 80 82 92" stroke="#8b1028" stroke-opacity="0.35" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                            <!-- Avatar proveedor arr-der -->
                            <circle cx="360" cy="70" r="30" fill="#8b1028" fill-opacity="0.10" stroke="#8b1028" stroke-opacity="0.28" stroke-width="1.5"/>
                            <circle cx="360" cy="61" r="11" fill="#8b1028" fill-opacity="0.35"/>
                            <path d="M338 92 Q360 80 382 92" stroke="#8b1028" stroke-opacity="0.35" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                            <!-- Avatar abajo-izq -->
                            <circle cx="60" cy="270" r="30" fill="#8b1028" fill-opacity="0.10" stroke="#8b1028" stroke-opacity="0.28" stroke-width="1.5"/>
                            <circle cx="60" cy="261" r="11" fill="#8b1028" fill-opacity="0.35"/>
                            <path d="M38 292 Q60 280 82 292" stroke="#8b1028" stroke-opacity="0.35" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                            <!-- Avatar abajo-der -->
                            <circle cx="360" cy="270" r="30" fill="#8b1028" fill-opacity="0.10" stroke="#8b1028" stroke-opacity="0.28" stroke-width="1.5"/>
                            <circle cx="360" cy="261" r="11" fill="#8b1028" fill-opacity="0.35"/>
                            <path d="M338 292 Q360 280 382 292" stroke="#8b1028" stroke-opacity="0.35" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                            <!-- Ícono de calendario central -->
                            <rect x="170" y="148" width="80" height="44" rx="10" fill="#8b1028" fill-opacity="0.12" stroke="#8b1028" stroke-opacity="0.35" stroke-width="1.5"/>
                            <line x1="170" y1="162" x2="250" y2="162" stroke="#8b1028" stroke-opacity="0.35" stroke-width="1.5"/>
                            <circle cx="188" cy="178" r="3.5" fill="#8b1028" fill-opacity="0.4"/>
                            <circle cx="210" cy="178" r="3.5" fill="#8b1028" fill-opacity="0.4"/>
                            <circle cx="232" cy="178" r="3.5" fill="#8b1028" fill-opacity="0.4"/>
                            <!-- Etiqueta "Cita 1-1" -->
                            <text x="210" y="132" text-anchor="middle" font-size="11" fill="#8b1028" fill-opacity="0.45" font-weight="600" font-family="sans-serif">Cita 1-1</text>
                            <!-- Puntos decorativos bordes -->
                            <circle cx="210" cy="28" r="5" fill="#8b1028" fill-opacity="0.18"/>
                            <circle cx="210" cy="312" r="5" fill="#8b1028" fill-opacity="0.18"/>
                            <circle cx="16" cy="170" r="5" fill="#8b1028" fill-opacity="0.18"/>
                            <circle cx="404" cy="170" r="5" fill="#8b1028" fill-opacity="0.18"/>
                        </svg>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════ CÓMO FUNCIONA ═══════════════════════════ -->
        <section id="como-funciona" class="py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16 fade-up">
                    <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-4">¿Cómo funciona?</h2>
                    <p class="text-gray-500 dark:text-gray-400 max-w-lg mx-auto">Cuatro simples pasos para comenzar a hacer networking empresarial en Yucatán.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div v-for="(paso, i) in pasos" :key="i"
                        class="fade-up relative bg-white dark:bg-gray-900/50 border border-gray-200 dark:border-gray-800 rounded-2xl p-7 hover:border-guinda-500/30 hover:-translate-y-1 hover:shadow-xl hover:shadow-guinda-500/5 transition-all duration-300 group"
                        :style="`transition-delay: ${i * 100}ms`">
                        <div class="text-6xl font-black text-guinda-500/15 group-hover:text-guinda-500/25 transition-colors mb-4 leading-none select-none">
                            0{{ i + 1 }}
                        </div>
                        <div class="w-11 h-11 rounded-xl bg-guinda-50 dark:bg-guinda-500/10 flex items-center justify-center mb-5">
                            <svg class="w-6 h-6 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="paso.path" />
                            </svg>
                        </div>
                        <h3 class="font-bold text-gray-900 dark:text-white mb-3 text-base">{{ paso.titulo }}</h3>
                        <p class="text-gray-500 dark:text-gray-400 text-sm leading-relaxed">{{ paso.descripcion }}</p>
                        <div v-if="i < 3" class="hidden lg:block absolute -right-3 top-1/2 -translate-y-1/2 z-10">
                            <div class="w-6 h-6 rounded-full bg-white dark:bg-gray-950 border border-gray-200 dark:border-guinda-500/30 flex items-center justify-center shadow-sm">
                                <svg class="w-3 h-3 text-guinda-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════ PARA QUIÉN ES ═══════════════════════════ -->
        <section class="py-24 bg-gray-50 dark:bg-gray-900/30">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14 fade-up">
                    <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-4">¿Para quién es?</h2>
                    <p class="text-gray-500 dark:text-gray-400 max-w-lg mx-auto">Impúlsate conecta a dos tipos de participantes del ecosistema empresarial yucateco.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Compradores -->
                    <div class="fade-up bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 hover:border-guinda-500/30 hover:shadow-xl hover:shadow-guinda-500/5 transition-all duration-300">
                        <div class="w-14 h-14 rounded-2xl bg-guinda-50 dark:bg-guinda-500/10 flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-black text-gray-900 dark:text-white mb-2">Soy Comprador</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Busco proveedores de calidad para mi empresa o proyecto.</p>
                        <ul class="space-y-3 mb-8">
                            <li v-for="item in ['Encuentra proveedores verificados por el IYEM', 'Agenda citas sin costo ni intermediarios', 'Conecta presencialmente en Mérida, Yucatán', 'Descubre nuevas oportunidades de negocio']"
                                :key="item" class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-400">
                                <svg class="w-5 h-5 text-guinda-600 dark:text-guinda-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ item }}
                            </li>
                        </ul>
                        <Link :href="route('restauranteros.index')"
                              class="inline-flex items-center gap-2 bg-guinda-700 hover:bg-guinda-600 text-white font-bold px-5 py-2.5 rounded-xl text-sm transition-all duration-200 hover:-translate-y-0.5">
                            Explorar proveedores
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>

                    <!-- Proveedores -->
                    <div class="fade-up bg-white dark:bg-gray-900/60 border border-gray-200 dark:border-gray-800 rounded-2xl p-8 hover:border-guinda-500/30 hover:shadow-xl hover:shadow-guinda-500/5 transition-all duration-300">
                        <div class="w-14 h-14 rounded-2xl bg-guinda-50 dark:bg-guinda-500/10 flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016 2.993 2.993 0 002.25-1.016 3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                            </svg>
                        </div>
                        <h3 class="text-xl font-black text-gray-900 dark:text-white mb-2">Soy Proveedor</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Ofrezco productos o servicios y quiero llegar a nuevos clientes.</p>
                        <ul class="space-y-3 mb-8">
                            <li v-for="item in ['Presenta tu empresa a compradores calificados', 'Recibe solicitudes de citas directamente', 'Expande tu red de negocios en Yucatán', 'Registro y participación completamente gratis']"
                                :key="item" class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-400">
                                <svg class="w-5 h-5 text-guinda-600 dark:text-guinda-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                {{ item }}
                            </li>
                        </ul>
                        <Link v-if="canRegister" :href="route('register')"
                              class="inline-flex items-center gap-2 border border-guinda-500/40 hover:border-guinda-600 hover:bg-guinda-50 dark:hover:bg-guinda-500/10 text-guinda-700 dark:text-guinda-400 font-bold px-5 py-2.5 rounded-xl text-sm transition-all duration-200 hover:-translate-y-0.5">
                            Registrarse como proveedor
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════ PROVEEDORES DESTACADOS ═══════════════════════════ -->
        <section class="py-24">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16 fade-up">
                    <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-4">Proveedores Destacados</h2>
                    <p class="text-gray-500 dark:text-gray-400 max-w-xl mx-auto">Perfiles verificados registrados en el evento activo del programa Impúlsate.</p>
                </div>

                <div v-if="proveedoresDestacados.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Link v-for="(r, i) in proveedoresDestacados" :key="r.id"
                        :href="route('restauranteros.show', r.id)"
                        class="fade-up bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden hover:border-guinda-500/40 hover:-translate-y-1 hover:shadow-xl hover:shadow-guinda-500/5 transition-all duration-300 group"
                        :style="`transition-delay: ${i * 80}ms`">
                        <div class="h-44 bg-gradient-to-br from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-900 flex items-center justify-center relative overflow-hidden">
                            <img v-if="r.logo_path" :src="'/storage/' + r.logo_path" :alt="r.nombre_restaurante"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                            <svg v-else class="w-16 h-16 text-gray-300 dark:text-gray-700" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016 2.993 2.993 0 002.25-1.016 3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                            </svg>
                            <div v-if="r.municipio" class="absolute top-3 left-3 flex items-center gap-1 bg-white/90 dark:bg-gray-950/80 text-guinda-700 dark:text-guinda-400 text-xs font-medium px-2.5 py-1 rounded-full backdrop-blur">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                </svg>
                                {{ r.municipio }}
                            </div>
                        </div>
                        <div class="p-5">
                            <h3 class="font-bold text-gray-900 dark:text-white text-base mb-2 leading-snug">{{ r.nombre_restaurante }}</h3>
                            <p v-if="r.descripcion" class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed line-clamp-2 mb-3">{{ r.descripcion }}</p>
                            <div class="flex items-center justify-between mt-2">
                                <span class="text-xs text-gray-400 dark:text-gray-500 bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded-full">{{ r.citas_count || 0 }} citas</span>
                                <span class="text-guinda-600 dark:text-guinda-400 text-sm font-medium group-hover:text-guinda-500 dark:group-hover:text-guinda-300 transition-colors flex items-center gap-1">
                                    Ver perfil
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>

                <div v-else class="text-center py-16 fade-up">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-200 dark:text-gray-800" fill="none" stroke="currentColor" stroke-width="1" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016 2.993 2.993 0 002.25-1.016 3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />
                    </svg>
                    <p class="text-lg font-medium text-gray-400 dark:text-gray-600">Aún no hay proveedores registrados.</p>
                </div>

                <div class="text-center mt-12 fade-up">
                    <Link :href="route('restauranteros.index')"
                          class="inline-flex items-center gap-2 border border-guinda-500/30 hover:border-guinda-500 text-guinda-600 dark:text-guinda-400 hover:text-guinda-500 dark:hover:text-guinda-300 font-semibold px-6 py-3 rounded-xl transition-all duration-200 hover:-translate-y-0.5">
                        Ver todos los proveedores
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </Link>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════ CTA FINAL ═══════════════════════════ -->
        <section class="py-24 bg-guinda-900 dark:bg-guinda-950">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center fade-up">
                <div class="w-16 h-16 rounded-2xl bg-white/10 flex items-center justify-center mx-auto mb-8">
                    <svg class="w-9 h-9 text-white" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                    </svg>
                </div>
                <h2 class="text-3xl sm:text-4xl font-black text-white mb-5">
                    ¿Listo para hacer networking<br class="hidden sm:block" /> empresarial?
                </h2>
                <p class="text-guinda-200/75 mb-10 text-lg leading-relaxed max-w-xl mx-auto">
                    Únete al programa gubernamental que está transformando el ecosistema empresarial yucateco. Registra tu cuenta y agenda tu primera cita hoy mismo.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link v-if="canRegister" :href="route('register')"
                          class="px-8 py-4 bg-white hover:bg-guinda-50 text-guinda-800 font-bold rounded-xl transition-all duration-200 shadow-lg shadow-black/20 hover:-translate-y-0.5 text-sm w-full sm:w-auto text-center">
                        Comenzar ahora — Es gratuito
                    </Link>
                    <Link :href="route('restauranteros.index')"
                          class="px-8 py-4 border border-guinda-600/60 hover:border-white/40 text-guinda-200 hover:text-white font-semibold rounded-xl transition-all duration-200 hover:-translate-y-0.5 text-sm w-full sm:w-auto text-center">
                        Explorar proveedores
                    </Link>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════ UBICACIÓN ═══════════════════════════ -->
        <section id="ubicacion" class="py-24">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14 fade-up">
                    <h2 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-4">Nuestra Oficina</h2>
                    <p class="text-gray-500 dark:text-gray-400 max-w-xl mx-auto">Todas las citas se realizan de forma presencial en nuestras instalaciones en Mérida, Yucatán.</p>
                </div>

                <div class="fade-up bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl overflow-hidden shadow-sm">
                    <div class="grid grid-cols-1 lg:grid-cols-5">
                        <!-- Panel de datos -->
                        <div class="lg:col-span-2 p-8 lg:p-10 flex flex-col justify-center gap-6">
                            <h3 class="font-black text-gray-900 dark:text-white text-lg">Encuentro de Negocios Impúlsate</h3>
                            <div class="space-y-5">
                                <div class="flex items-start gap-4">
                                    <div class="w-9 h-9 rounded-xl bg-guinda-50 dark:bg-guinda-500/10 flex items-center justify-center shrink-0 mt-0.5">
                                        <svg class="w-5 h-5 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Dirección</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">Av. Industrias No Contaminantes Tab 13613<br />Col. Sodzil Norte, C.P. 97110<br />Mérida, Yucatán</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4">
                                    <div class="w-9 h-9 rounded-xl bg-guinda-50 dark:bg-guinda-500/10 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Horario</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Lunes a Viernes: 9:00 — 16:00 h</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-4">
                                    <div class="w-9 h-9 rounded-xl bg-guinda-50 dark:bg-guinda-500/10 flex items-center justify-center shrink-0">
                                        <svg class="w-5 h-5 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-[11px] font-bold text-gray-700 dark:text-gray-300 uppercase tracking-wider mb-1">Correo</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">impulsate@iyemyucatan.com</p>
                                    </div>
                                </div>
                            </div>
                            <a href="https://maps.google.com/maps?q=Av+Industrias+No+Contaminantes+Tab+13613+Sodzil+Norte+Merida+Yucatan"
                               target="_blank" rel="noopener noreferrer"
                               class="inline-flex items-center gap-2 bg-guinda-700 hover:bg-guinda-600 text-white font-bold px-5 py-2.5 rounded-xl text-sm transition-all duration-200 hover:-translate-y-0.5 w-fit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
                                </svg>
                                Cómo llegar
                            </a>
                            <p class="text-xs text-gray-400 dark:text-gray-600 -mt-2">Programa operado por el Gobierno del Estado de Yucatán.</p>
                        </div>

                        <!-- Mapa -->
                        <div class="lg:col-span-3 h-72 lg:h-auto min-h-[320px] relative">
                            <iframe
                                src="https://maps.google.com/maps?q=Av+Industrias+No+Contaminantes+13613+Sodzil+Norte+Merida+Yucatan+Mexico&t=&z=16&ie=UTF8&iwloc=&output=embed"
                                width="100%" height="100%"
                                style="border:0; filter: grayscale(25%);"
                                allowfullscreen loading="lazy"
                                class="absolute inset-0 w-full h-full"
                                title="Ubicación de Impúlsate — Mérida, Yucatán"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ═══════════════════════════ FOOTER ═══════════════════════════ -->
        <footer class="bg-gray-50 dark:bg-gray-950 border-t border-gray-200 dark:border-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">

                    <!-- Marca -->
                    <div>
                        <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-10 w-auto mb-5" />
                        <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed mb-5">
                            Programa oficial del Gobierno del Estado de Yucatán para impulsar el ecosistema empresarial a través de citas estratégicas de networking.
                        </p>
                        <div class="inline-flex items-center gap-2 bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 text-guinda-700 dark:text-guinda-400 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                            </svg>
                            Gobierno de Yucatán · 2026
                        </div>
                    </div>

                    <!-- Navegación -->
                    <div>
                        <h4 class="font-bold text-gray-700 dark:text-gray-300 text-xs uppercase tracking-wider mb-5">Navegación</h4>
                        <ul class="space-y-3">
                            <li><Link :href="route('restauranteros.index')" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">Proveedores</Link></li>
                            <li><a href="#como-funciona" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">Cómo funciona</a></li>
                            <li><a href="#ubicacion" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">Nuestra oficina</a></li>
                            <li v-if="canRegister"><Link :href="route('register')" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">Registrarse gratis</Link></li>
                            <li v-if="canLogin"><Link :href="route('login')" class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">Iniciar sesión</Link></li>
                        </ul>
                    </div>

                    <!-- Sitios Aliados -->
                    <div>
                        <h4 class="font-bold text-gray-700 dark:text-gray-300 text-xs uppercase tracking-wider mb-5">Sitios Aliados</h4>
                        <ul class="space-y-4">
                            <li v-for="aliado in [
                                { nombre: 'Instituto Yucateco de Emprendedores', siglas: 'IYEM', url: 'https://iyem.yucatan.gob.mx/' },
                                { nombre: 'Nodico', siglas: null, url: 'https://www.nodico.com.mx/' },
                                { nombre: 'Herencia Viva', siglas: null, url: 'https://www.herenciaviva.com/' },
                            ]" :key="aliado.url">
                                <a :href="aliado.url" target="_blank" rel="noopener noreferrer"
                                   class="flex items-start gap-2 group">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-gray-500 dark:text-gray-400 group-hover:text-guinda-600 dark:group-hover:text-guinda-400 transition-colors leading-snug">
                                            {{ aliado.nombre }}
                                            <span v-if="aliado.siglas" class="text-xs text-gray-400 dark:text-gray-500">({{ aliado.siglas }})</span>
                                        </p>
                                    </div>
                                    <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-600 group-hover:text-guinda-500 transition-colors shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            </li>
                        </ul>
                    </div>

                    <!-- Contacto -->
                    <div>
                        <h4 class="font-bold text-gray-700 dark:text-gray-300 text-xs uppercase tracking-wider mb-5">Contacto</h4>
                        <ul class="space-y-4">
                            <li class="flex items-start gap-3">
                                <svg class="w-4 h-4 text-guinda-600 dark:text-guinda-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Av. Industrias No Contaminantes Tab 13613<br />Col. Sodzil Norte, C.P. 97110<br />Mérida, Yucatán</p>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-4 h-4 text-guinda-600 dark:text-guinda-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Lunes a Viernes: 9:00 — 16:00 h</p>
                            </li>
                            <li class="flex items-center gap-3">
                                <svg class="w-4 h-4 text-guinda-600 dark:text-guinda-400 shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                                </svg>
                                <p class="text-sm text-gray-500 dark:text-gray-400">impulsate@iyemyucatan.com</p>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-800">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-2">
                    <p class="text-xs text-gray-400 dark:text-gray-600 text-center">
                        © {{ new Date().getFullYear() }} Encuentro de Negocios Impúlsate — Instituto Yucateco de Emprendedores
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
/* Animaciones hero */
@keyframes heroFadeUp {
    from { opacity: 0; transform: translateY(28px); }
    to   { opacity: 1; transform: translateY(0); }
}

@keyframes growLine {
    from { transform: scaleX(0); }
    to   { transform: scaleX(1); }
}

.hero-badge  { animation: heroFadeUp 0.55s ease both; }
.hero-title  { animation: heroFadeUp 0.65s 0.1s ease both; }
.hero-line   { transform-origin: center; animation: growLine 0.7s 0.45s ease both; transform: scaleX(0); }
.hero-text   { animation: heroFadeUp 0.65s 0.25s ease both; }
.hero-ctas   { animation: heroFadeUp 0.65s 0.4s ease both; }

/* Gradiente animado en el texto del H1 */
.hero-gradient-text {
    background-image: linear-gradient(90deg, #c8113b 0%, #f36178 40%, #c8113b 80%);
    background-size: 200% auto;
    animation: gradientFlow 5s ease infinite;
}

@keyframes gradientFlow {
    0%   { background-position: 0% center; }
    50%  { background-position: 100% center; }
    100% { background-position: 0% center; }
}

/* Secciones con fade-up al entrar en viewport */
.fade-up {
    opacity: 0;
    transform: translateY(24px);
    transition: opacity 0.6s ease, transform 0.6s ease;
}
.fade-up.visible {
    opacity: 1;
    transform: translateY(0);
}

/* Decoraciones flotantes */
@keyframes floatShape {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    40%       { transform: translateY(-14px) rotate(2deg); }
    70%       { transform: translateY(-7px) rotate(-1.5deg); }
}

.shape-a { animation: floatShape 11s ease-in-out infinite; }
.shape-b { animation: floatShape 14s ease-in-out infinite; animation-delay: -4s; }
.shape-c { animation: floatShape  8s ease-in-out infinite; animation-delay: -1.5s; }
.shape-d { animation: floatShape 10s ease-in-out infinite; animation-delay: -6s; }
.shape-e { animation: floatShape 15s ease-in-out infinite; animation-delay: -3s; }
.shape-f { animation: floatShape  9s ease-in-out infinite; animation-delay: -7s; }
</style>

<style>
.hero-section {
    background: linear-gradient(145deg, #fff5f6 0%, #ffffff 55%, #fdf4f5 100%);
}
.dark .hero-section {
    background: linear-gradient(145deg, #0d0204 0%, #150307 45%, #1a0608 100%);
}
</style>
