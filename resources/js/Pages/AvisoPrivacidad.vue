<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const darkMode = ref(document.documentElement.classList.contains('dark'));
</script>

<template>
    <Head title="Aviso de Privacidad — Impulsate" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 text-gray-900 dark:text-white transition-colors duration-300">

        <!-- NAVBAR -->
        <nav class="sticky top-0 z-50 bg-white/95 dark:bg-gray-950/90 backdrop-blur border-b border-gray-200 dark:border-gray-800 shadow-sm dark:shadow-none transition-colors">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
                <Link :href="route('home')" class="flex items-center shrink-0">
                    <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-9 w-auto" />
                </Link>
                <div class="flex items-center gap-3">
                    <ThemeToggle />
                    <Link :href="route('home')"
                          class="text-sm text-gray-600 dark:text-gray-400 hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors font-medium">
                        ← Volver al inicio
                    </Link>
                </div>
            </div>
        </nav>

        <!-- BREADCRUMB -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
            <nav class="flex items-center gap-2 text-xs text-gray-400 dark:text-gray-600">
                <Link :href="route('home')" class="hover:text-guinda-600 dark:hover:text-guinda-400 transition-colors">Inicio</Link>
                <span>›</span>
                <span class="text-gray-500 dark:text-gray-400">Aviso de Privacidad</span>
            </nav>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-20">

            <!-- Encabezado -->
            <div class="mb-10">
                <div class="inline-flex items-center gap-2 bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 text-guinda-700 dark:text-guinda-400 text-xs font-semibold px-3 py-1.5 rounded-full mb-5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" />
                    </svg>
                    Gobierno de Yucatán · IYEM
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-3 leading-tight">
                    Aviso de Privacidad Integral
                </h1>
                <p class="text-base text-gray-500 dark:text-gray-400 leading-relaxed max-w-2xl">
                    Solicitud de participación en bazares, ferias y exposiciones para artesanos y emprendedores —
                    Dirección de Innovación y Competitividad Empresarial
                </p>
                <div class="mt-4 h-1 w-16 bg-guinda-600 dark:bg-guinda-500 rounded-full"></div>
            </div>

            <!-- Responsable -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 mb-6">
                <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-3">Responsable del tratamiento de datos</h2>
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                    El <strong class="text-gray-900 dark:text-white">Instituto Yucateco de Emprendedores (IYEM)</strong>, con domicilio en Avenida Principal
                    Industrias No Contaminantes, Tablaje Catastral 13613, Colonia Sodzil Norte, C.P. 97110, Mérida, Yucatán, México,
                    es responsable del tratamiento de los datos personales proporcionados por las personas físicas y representantes legales
                    de personas morales que soliciten participar en los bazares, ferias y exposiciones para artesanos y emprendedores que
                    realiza, organiza o participa el IYEM. Los datos serán tratados conforme a la Ley General de Protección de Datos Personales
                    en Posesión de Sujetos Obligados, los Lineamientos Generales del Sistema Nacional de Transparencia y demás disposiciones
                    jurídicas aplicables.
                </p>
            </div>

            <!-- Sección 1: Datos y fines -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 mb-6">
                <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">¿Qué datos personales recabamos y para qué fines?</h2>

                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Finalidades primarias</h3>
                <ul class="space-y-2 mb-5 pl-5">
                    <li class="list-disc text-gray-600 dark:text-gray-400 leading-relaxed">
                        Verificar la identidad y autenticidad de los participantes mediante los documentos oficiales proporcionados.
                    </li>
                    <li class="list-disc text-gray-600 dark:text-gray-400 leading-relaxed">
                        Evaluar, seleccionar y asignar espacios de participación en el bazar, feria o exposición, conforme a criterios institucionales.
                    </li>
                    <li class="list-disc text-gray-600 dark:text-gray-400 leading-relaxed">
                        Establecer comunicación directa con las personas participantes para informar sobre fechas, requisitos, logística y seguimiento del evento.
                    </li>
                </ul>

                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">Finalidades secundarias</h3>
                <ul class="space-y-2 mb-5 pl-5">
                    <li class="list-disc text-gray-600 dark:text-gray-400 leading-relaxed">Generación de informes estadísticos institucionales.</li>
                    <li class="list-disc text-gray-600 dark:text-gray-400 leading-relaxed">Análisis de carácter institucional.</li>
                    <li class="list-disc text-gray-600 dark:text-gray-400 leading-relaxed">Elaboración y difusión de publicaciones en redes sociales oficiales y medios institucionales.</li>
                    <li class="list-disc text-gray-600 dark:text-gray-400 leading-relaxed">La promoción de convocatorias, programas o actividades organizadas por el Instituto.</li>
                </ul>

                <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-5">
                    Si usted no desea que sus datos sean utilizados con estas finalidades secundarias, podrá manifestarlo al momento de su
                    registro o posteriormente de forma personal y por escrito en las oficinas del Instituto Yucateco de Emprendedores o a
                    través del siguiente correo electrónico:
                    <strong class="text-gray-800 dark:text-gray-200">solicitudes.iyem@transparenciayucatan.org.mx</strong>
                </p>

                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Datos personales recabados</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-gray-50 dark:bg-gray-800/60 rounded-xl p-4">
                        <p class="text-xs font-semibold text-guinda-600 dark:text-guinda-400 uppercase tracking-wider mb-2">Datos identificativos</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Nombre completo, Fecha de nacimiento, Edad, Sexo, Domicilio, Datos de identificación oficial vigente, CURP, RFC.
                        </p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800/60 rounded-xl p-4">
                        <p class="text-xs font-semibold text-guinda-600 dark:text-guinda-400 uppercase tracking-wider mb-2">Datos de contacto</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Teléfono particular, Correo electrónico, Redes sociales del negocio.
                        </p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800/60 rounded-xl p-4">
                        <p class="text-xs font-semibold text-guinda-600 dark:text-guinda-400 uppercase tracking-wider mb-2">Datos del negocio</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Nombre del negocio, Tipo de producto que produce, Foto del producto.
                        </p>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-800/60 rounded-xl p-4 border border-amber-200 dark:border-amber-800/40">
                        <p class="text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-2">Datos sensibles (origen)</p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Municipio, Localidad, Si es persona maya hablante.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Origen de datos y medidas de seguridad -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 mb-6">
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3">
                    Los datos personales se obtienen directamente de la persona solicitante.
                </p>
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                    Se informa que el Instituto ha implementado medidas de seguridad físicas, técnicas y administrativas para proteger
                    los datos personales contra daño, pérdida, alteración, destrucción o acceso no autorizado, garantizando su
                    confidencialidad, integridad y disponibilidad.
                </p>
            </div>

            <!-- Sección 2: Fundamento legal -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 mb-6">
                <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-3">Fundamento legal para el tratamiento de sus datos personales</h2>
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                    El tratamiento se realiza con fundamento en el artículo 5 fracciones I, IX, XIV, XV, XVI, XIX y XXVII, del
                    <strong class="text-gray-800 dark:text-gray-200">Decreto 229/1999 por el que se crea el Instituto Yucateco de Emprendedores</strong>,
                    publicado en el Diario Oficial del Gobierno del Estado el 17 de noviembre de 1999, cuya última reforma es del 31 de enero de 2022.
                </p>
            </div>

            <!-- Sección 3: Transferencia -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 mb-6">
                <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-3">Transferencia de datos personales</h2>
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                    No se realizarán transferencias de datos personales, salvo aquéllas que sean necesarias para atender requerimientos de información
                    de una autoridad competente, y en los casos legalmente previstos en los que no se requiere consentimiento del titular
                    (auditorías, fiscalizaciones, remisión de reportes a instancias gubernamentales).
                </p>
            </div>

            <!-- Sección 4: Derechos ARCO -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 mb-6">
                <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-3">¿Dónde puedo ejercer mis derechos ARCO?</h2>
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-4">
                    Podrá ejercer sus derechos de acceso, rectificación, cancelación u oposición conforme al artículo 16 párrafo segundo
                    de la Constitución Política de los Estados Unidos Mexicanos, Título Tercero de la Ley General de Protección de Datos
                    Personales en Posesión de Sujetos Obligados, y artículo 54 de la Ley de Protección de Datos Personales en Posesión
                    de Sujetos Obligados del Estado de Yucatán.
                </p>
                <div class="space-y-3">
                    <div class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-guinda-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span><strong class="text-gray-800 dark:text-gray-200">Unidad de Transparencia:</strong> Mismas oficinas del IYEM, lunes a viernes 9:00 a 15:00 horas.</span>
                    </div>
                    <div class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-guinda-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75" />
                        </svg>
                        <span><strong class="text-gray-800 dark:text-gray-200">Correo:</strong> solicitudes.iyem@transparenciayucatan.org.mx</span>
                    </div>
                    <div class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-guinda-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                        </svg>
                        <span><strong class="text-gray-800 dark:text-gray-200">Plataforma Nacional de Transparencia:</strong>
                            <a href="https://www.plataformadetransparencia.org.mx" target="_blank" rel="noopener noreferrer"
                               class="text-guinda-600 dark:text-guinda-400 hover:underline ml-1">
                                plataformadetransparencia.org.mx
                            </a>
                        </span>
                    </div>
                    <div class="flex items-start gap-3 text-sm text-gray-600 dark:text-gray-400">
                        <svg class="w-4 h-4 text-guinda-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 011.242 7.244l-4.5 4.5a4.5 4.5 0 01-6.364-6.364l1.757-1.757m13.35-.622l1.757-1.757a4.5 4.5 0 00-6.364-6.364l-4.5 4.5a4.5 4.5 0 001.242 7.244" />
                        </svg>
                        <span><strong class="text-gray-800 dark:text-gray-200">Trámites ARCO:</strong>
                            <a href="https://iyem.yucatan.gob.mx/transparencia/" target="_blank" rel="noopener noreferrer"
                               class="text-guinda-600 dark:text-guinda-400 hover:underline ml-1">
                                iyem.yucatan.gob.mx/transparencia
                            </a>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Sección 5: Domicilio -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 mb-6">
                <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-3">Domicilio de la Unidad de Transparencia</h2>
                <div class="flex items-start gap-3 text-gray-600 dark:text-gray-400">
                    <svg class="w-4 h-4 text-guinda-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                    <div class="space-y-1 text-sm">
                        <p>Avenida Principal Industrias No Contaminantes, Tablaje Catastral 13613</p>
                        <p>Colonia Sodzil Norte, C.P. 97110, Mérida, Yucatán, México</p>
                        <p>Lunes a viernes 9:00 a 15:00 horas</p>
                        <p>Teléfono: 9994615676 ext 29266</p>
                        <p>Correo: solicitudes.iyem@transparenciayucatan.org.mx</p>
                    </div>
                </div>
            </div>

            <!-- Sección 6: Cambios -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8 mb-8">
                <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-3">Cambios al aviso de privacidad</h2>
                <p class="text-gray-600 dark:text-gray-400 leading-relaxed mb-3">
                    Los cambios se harán del conocimiento en los medios oficiales del IYEM:
                    <a href="https://iyem.yucatan.gob.mx/transparencia/" target="_blank" rel="noopener noreferrer"
                       class="text-guinda-600 dark:text-guinda-400 hover:underline">
                        iyem.yucatan.gob.mx/transparencia
                    </a>
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-600">
                    Última fecha de actualización: <strong>08 de junio de 2026</strong>
                </p>
            </div>

            <!-- Botón volver -->
            <div class="text-center">
                <Link :href="route('home')"
                      class="inline-flex items-center gap-2 px-6 py-3 bg-guinda-700 hover:bg-guinda-600 text-white font-semibold rounded-xl text-sm transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Volver al inicio
                </Link>
            </div>
        </main>

        <!-- FOOTER -->
        <footer class="border-t border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-950">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-2">
                <p class="text-xs text-gray-400 dark:text-gray-600">
                    © {{ new Date().getFullYear() }} Encuentro de Negocios Impúlsate — Instituto Yucateco de Emprendedores
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Desarrollado por <span class="font-semibold text-guinda-700 dark:text-guinda-400">Diego Martínez</span>
                </p>
            </div>
        </footer>

    </div>
</template>
