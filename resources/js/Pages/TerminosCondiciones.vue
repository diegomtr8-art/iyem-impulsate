<script setup>
import { Head, Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const secciones = [
    { id: 'seccion-i', titulo: 'I. Disposiciones Generales' },
    { id: 'seccion-ii', titulo: 'II. Definiciones' },
    { id: 'seccion-iii', titulo: 'III. Objeto de la Plataforma' },
    { id: 'seccion-iv', titulo: 'IV. Alcance de los Servicios' },
    { id: 'seccion-v', titulo: 'V. Aceptación' },
    { id: 'seccion-vi', titulo: 'VI. Registro' },
    { id: 'seccion-vii', titulo: 'VII. Obligaciones de las Personas Usuarias' },
    { id: 'seccion-viii', titulo: 'VIII. Conductas Prohibidas' },
    { id: 'seccion-ix', titulo: 'IX. Publicación de Contenido' },
    { id: 'seccion-x', titulo: 'X. Vinculación Comercial' },
    { id: 'seccion-xi', titulo: 'XI. Exclusión y Limitación de Responsabilidad' },
    { id: 'seccion-xii', titulo: 'XII. Indemnización' },
    { id: 'seccion-xiii', titulo: 'XIII. Propiedad Intelectual' },
    { id: 'seccion-xiv', titulo: 'XIV. Uso de la Imagen Institucional' },
    { id: 'seccion-xv', titulo: 'XV. Protección de Datos Personales' },
    { id: 'seccion-xvi', titulo: 'XVI. Suspensión, Restricción y Cancelación' },
    { id: 'seccion-xvii', titulo: 'XVII. Retiro de Contenido' },
    { id: 'seccion-xviii', titulo: 'XVIII. Disponibilidad y Funcionamiento' },
    { id: 'seccion-xix', titulo: 'XIX. Modificaciones' },
    { id: 'seccion-xx', titulo: 'XX. Comunicaciones y Notificaciones' },
    { id: 'seccion-xxi', titulo: 'XXI. Inexistencia de Relación Jurídica' },
    { id: 'seccion-xxii', titulo: 'XXII. Caso Fortuito y Fuerza Mayor' },
    { id: 'seccion-xxiii', titulo: 'XXIII. Legislación Aplicable y Jurisdicción' },
    { id: 'seccion-xxiv', titulo: 'XXIV. Nulidad Parcial' },
    { id: 'seccion-xxv', titulo: 'XXV. No Renuncia de Derechos' },
    { id: 'seccion-xxvi', titulo: 'XXVI. Integridad' },
    { id: 'seccion-xxvii', titulo: 'XXVII. Contacto' },
    { id: 'seccion-xxviii', titulo: 'XXVIII. Vigencia' },
];
</script>

<template>
    <Head title="Términos y Condiciones — Impulsate" />

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
                <span class="text-gray-500 dark:text-gray-400">Términos y Condiciones</span>
            </nav>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 pb-20">

            <!-- Encabezado -->
            <div class="mb-10 text-center">
                <div class="inline-flex items-center justify-center w-14 h-14 bg-guinda-50 dark:bg-guinda-500/10 rounded-2xl mb-4">
                    <svg class="w-7 h-7 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-gray-900 dark:text-white mb-2">Términos y Condiciones</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400">Plataforma Digital Impulsate — Instituto Yucateco de Emprendedores (IYEM)</p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Gobierno del Estado de Yucatán</p>
                <div class="mt-4 h-1 w-16 bg-guinda-600 dark:bg-guinda-500 rounded-full mx-auto"></div>
            </div>

            <!-- Índice -->
            <div class="bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-100 dark:border-guinda-500/20 rounded-2xl p-6 mb-10">
                <h2 class="text-sm font-bold text-guinda-800 dark:text-guinda-300 uppercase tracking-widest mb-4">Índice</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                    <a v-for="s in secciones" :key="s.id" :href="`#${s.id}`"
                       class="text-xs text-guinda-700 dark:text-guinda-400 hover:text-guinda-900 dark:hover:text-guinda-200 transition-colors">
                        {{ s.titulo }}
                    </a>
                </div>
            </div>

            <!-- Contenido de secciones -->
            <div class="space-y-6">

                <section id="seccion-i" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">I. Disposiciones Generales</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            La Plataforma Digital denominada "Impulsate" (en adelante, "la Plataforma") es un espacio digital administrado por el Instituto Yucateco de Emprendedores (en adelante, "IYEM"), órgano desconcentrado del Gobierno del Estado de Yucatán.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El acceso y uso de la Plataforma implica la aceptación plena, expresa e irrestricta de los presentes Términos y Condiciones, así como del Aviso de Privacidad correspondiente, los cuales tienen carácter de contrato electrónico de adhesión.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Toda persona que acceda o utilice la Plataforma declara haber leído, comprendido y aceptado los presentes términos. En caso de no estar de acuerdo con alguna de las condiciones aquí establecidas, deberá abstenerse de utilizar la Plataforma.
                        </p>
                    </div>
                </section>

                <section id="seccion-ii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">II. Definiciones</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Para efectos de los presentes Términos y Condiciones, se entenderá por:
                        </p>
                        <ul class="list-disc pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li><strong class="text-gray-800 dark:text-gray-200">IYEM:</strong> Instituto Yucateco de Emprendedores, órgano desconcentrado del Gobierno del Estado de Yucatán responsable de administrar la Plataforma.</li>
                            <li><strong class="text-gray-800 dark:text-gray-200">Plataforma:</strong> El espacio digital "Impulsate", accesible a través de medios electrónicos, mediante el cual el IYEM facilita la conexión entre personas emprendedoras y/o empresarias.</li>
                            <li><strong class="text-gray-800 dark:text-gray-200">Persona Usuaria:</strong> Toda persona física o moral que acceda, se registre o haga uso de los servicios ofrecidos en la Plataforma.</li>
                            <li><strong class="text-gray-800 dark:text-gray-200">Cuenta:</strong> El perfil digital personalizado que cada Persona Usuaria crea al registrarse en la Plataforma.</li>
                            <li><strong class="text-gray-800 dark:text-gray-200">Contenido:</strong> Toda información, dato, texto, imagen, logotipo, descripción de productos o servicios y cualquier otro material que la Persona Usuaria publique o cargue en la Plataforma.</li>
                            <li><strong class="text-gray-800 dark:text-gray-200">Productos y Servicios:</strong> Los bienes, servicios o propuestas de valor que las Personas Usuarias ofrecen a través de la Plataforma.</li>
                            <li><strong class="text-gray-800 dark:text-gray-200">Vinculación Comercial:</strong> El proceso de conexión y contacto entre Personas Usuarias con fines de exploración de oportunidades de negocio.</li>
                        </ul>
                    </div>
                </section>

                <section id="seccion-iii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">III. Objeto de la Plataforma</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            La Plataforma tiene como objeto ser un espacio digital para la promoción y vinculación de emprendedores y empresarios del Estado de Yucatán, facilitando su visibilidad y acceso a oportunidades de negocio.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            La Plataforma tiene un carácter exclusivamente informativo y de vinculación, por lo que no constituye un portal de comercio electrónico ni garantiza la concreción de transacciones comerciales entre sus usuarios.
                        </p>
                    </div>
                </section>

                <section id="seccion-iv" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">IV. Alcance de los Servicios</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            A través de la Plataforma, el IYEM ofrece los siguientes servicios:
                        </p>
                        <ol class="list-decimal pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>Registro y creación de perfil empresarial o emprendedor.</li>
                            <li>Publicación de información sobre productos y servicios ofrecidos.</li>
                            <li>Acceso a herramientas de búsqueda y filtrado de potenciales socios o clientes.</li>
                            <li>Participación en convocatorias, eventos y programas impulsados por el IYEM.</li>
                            <li>Agendamiento de citas de vinculación entre Personas Usuarias.</li>
                            <li>Difusión de contenido relacionado con el ecosistema emprendedor yucateco.</li>
                            <li>Acceso a recursos, guías y materiales de apoyo al emprendimiento.</li>
                        </ol>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM se reserva el derecho de ampliar, modificar o eliminar cualquiera de los servicios descritos, con o sin previo aviso, según lo estime conveniente para el cumplimiento de sus objetivos institucionales.
                        </p>
                    </div>
                </section>

                <section id="seccion-v" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">V. Aceptación</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            La Persona Usuaria acepta los presentes Términos y Condiciones mediante los mecanismos electrónicos habilitados en la Plataforma, incluyendo, de manera enunciativa mas no limitativa, el acto de registro, inicio de sesión o cualquier acción que implique el uso activo de la misma.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Dicha aceptación tiene plenos efectos jurídicos conforme a la legislación vigente en materia de comercio electrónico y firma electrónica en los Estados Unidos Mexicanos.
                        </p>
                    </div>
                </section>

                <section id="seccion-vi" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">VI. Registro</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El registro en la Plataforma es de carácter personal e intransferible. Para llevar a cabo el registro, la Persona Usuaria deberá proporcionar información verídica, actualizada y completa.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM se reserva el derecho de denegar, suspender o cancelar el registro de cualquier Persona Usuaria en los siguientes supuestos:
                        </p>
                        <ol class="list-[lower-alpha] pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>Cuando se detecte que la información proporcionada es falsa, incompleta o engañosa.</li>
                            <li>Cuando la Persona Usuaria haya incumplido previamente los presentes Términos y Condiciones.</li>
                            <li>Cuando existan indicios de uso fraudulento o malintencionado de la Plataforma.</li>
                            <li>Cuando así lo requieran disposiciones legales o autoridades competentes.</li>
                            <li>Cuando, a juicio del IYEM, el registro no sea compatible con los objetivos de la Plataforma.</li>
                        </ol>
                    </div>
                </section>

                <section id="seccion-vii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">VII. Obligaciones de las Personas Usuarias</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Al utilizar la Plataforma, la Persona Usuaria se obliga a:
                        </p>
                        <ol class="list-decimal pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>Proporcionar información veraz, precisa y actualizada en su perfil y en cualquier interacción dentro de la Plataforma.</li>
                            <li>Mantener la confidencialidad de sus credenciales de acceso y notificar de inmediato al IYEM ante cualquier uso no autorizado de su cuenta.</li>
                            <li>Hacer un uso lícito, ético y responsable de la Plataforma y de los servicios que en ella se ofrecen.</li>
                            <li>No utilizar la Plataforma para fines distintos a los establecidos en los presentes Términos y Condiciones.</li>
                            <li>Respetar los derechos de propiedad intelectual del IYEM y de otros usuarios.</li>
                            <li>Abstenerse de realizar conductas que puedan dañar la imagen, reputación o funcionamiento de la Plataforma.</li>
                            <li>Tratar a las demás Personas Usuarias con respeto y cordialidad.</li>
                            <li>Cumplir con la normatividad vigente aplicable en materia fiscal, comercial, laboral y cualquier otra que resulte aplicable.</li>
                            <li>Informar al IYEM sobre cualquier falla, irregularidad o uso indebido que detecte en la Plataforma.</li>
                            <li>Actualizar periódicamente la información de su perfil para mantenerla vigente y precisa.</li>
                        </ol>
                    </div>
                </section>

                <section id="seccion-viii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">VIII. Conductas Prohibidas</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Queda estrictamente prohibido a las Personas Usuarias:
                        </p>
                        <ol class="list-[lower-alpha] pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>Proporcionar información falsa, engañosa o que induzca a error a otros usuarios o al IYEM.</li>
                            <li>Suplantar la identidad de otra persona, empresa o entidad.</li>
                            <li>Publicar contenido ilegal, ofensivo, discriminatorio, difamatorio, obsceno o que incite a la violencia.</li>
                            <li>Utilizar la Plataforma para actividades ilícitas, incluyendo lavado de dinero, fraude o evasión fiscal.</li>
                            <li>Realizar actividades de spam, phishing, o envío masivo de comunicaciones no solicitadas a otros usuarios.</li>
                            <li>Intentar acceder de manera no autorizada a sistemas, datos o cuentas de otros usuarios.</li>
                            <li>Introducir virus informáticos, malware o cualquier código malicioso en la Plataforma.</li>
                            <li>Reproducir, distribuir, modificar o explotar comercialmente los contenidos de la Plataforma sin autorización expresa del IYEM.</li>
                            <li>Utilizar robots, scrapers, crawlers u otros medios automatizados para extraer información de la Plataforma.</li>
                            <li>Interferir con el funcionamiento técnico de la Plataforma o de los servidores e infraestructura que la soportan.</li>
                            <li>Publicar contenido que viole derechos de terceros, incluyendo derechos de autor, marcas registradas, patentes o datos personales.</li>
                            <li>Realizar actos de competencia desleal o prácticas comerciales engañosas.</li>
                            <li>Cualquier otra conducta que contravenga las leyes mexicanas aplicables o los presentes Términos y Condiciones.</li>
                        </ol>
                    </div>
                </section>

                <section id="seccion-ix" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">IX. Publicación de Contenido</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            La Persona Usuaria es la única responsable del contenido que publique en la Plataforma, incluyendo su veracidad, licitud y pertinencia.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Al publicar contenido en la Plataforma, la Persona Usuaria otorga al IYEM una licencia no exclusiva, gratuita y revocable para usar, reproducir y mostrar dicho contenido con fines de operación y difusión de la Plataforma.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM se reserva el derecho de eliminar, sin previo aviso, cualquier contenido que, a su juicio, incumpla los presentes Términos y Condiciones, sea contrario a la ley, o resulte inapropiado para los fines de la Plataforma.
                        </p>
                    </div>
                </section>

                <section id="seccion-x" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">X. Vinculación Comercial</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM actúa exclusivamente como facilitador del contacto entre Personas Usuarias, sin ser parte de las negociaciones, acuerdos o transacciones comerciales que pudieran derivarse del uso de la Plataforma.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            En consecuencia, el IYEM no asume responsabilidad alguna por:
                        </p>
                        <ol class="list-[lower-alpha] pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>La calidad, seguridad o legalidad de los productos y servicios ofrecidos por las Personas Usuarias.</li>
                            <li>La veracidad de la información publicada en los perfiles de las Personas Usuarias.</li>
                            <li>La capacidad legal de las Personas Usuarias para celebrar contratos o acuerdos comerciales.</li>
                            <li>El incumplimiento de compromisos adquiridos entre Personas Usuarias a través de la Plataforma.</li>
                            <li>Cualquier daño o perjuicio derivado de las relaciones comerciales establecidas a través de la Plataforma.</li>
                        </ol>
                    </div>
                </section>

                <section id="seccion-xi" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XI. Exclusión y Limitación de Responsabilidad</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM no será responsable, en ningún caso, por:
                        </p>
                        <ol class="list-[lower-alpha] pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>Daños directos, indirectos, incidentales, especiales o consecuentes derivados del uso o imposibilidad de uso de la Plataforma.</li>
                            <li>Errores, omisiones o inexactitudes en el contenido publicado por las Personas Usuarias.</li>
                            <li>Pérdida de datos, información o contenido de la Persona Usuaria.</li>
                            <li>Fallas técnicas, interrupciones del servicio o ataques cibernéticos que afecten la disponibilidad de la Plataforma.</li>
                            <li>Actos u omisiones de terceros que afecten el funcionamiento de la Plataforma o los intereses de las Personas Usuarias.</li>
                            <li>Pérdidas económicas o daños al negocio derivados del uso o no uso de la Plataforma.</li>
                            <li>Decisiones comerciales o de negocio que la Persona Usuaria tome con base en la información disponible en la Plataforma.</li>
                            <li>Cualquier situación fuera del control razonable del IYEM.</li>
                        </ol>
                    </div>
                </section>

                <section id="seccion-xii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XII. Indemnización</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            La Persona Usuaria se obliga a indemnizar y mantener indemne al IYEM, sus funcionarios, empleados y colaboradores, frente a cualquier reclamación, demanda, pérdida, daño, responsabilidad, costo o gasto (incluyendo honorarios legales razonables) que se deriven de:
                        </p>
                        <ol class="list-[lower-alpha] pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>El incumplimiento de los presentes Términos y Condiciones.</li>
                            <li>El uso indebido de la Plataforma.</li>
                            <li>La violación de derechos de terceros.</li>
                            <li>La publicación de contenido ilegal o inapropiado.</li>
                            <li>Actos fraudulentos o dolosos realizados a través de la Plataforma.</li>
                            <li>Cualquier reclamación de terceros relacionada con las actividades de la Persona Usuaria en la Plataforma.</li>
                        </ol>
                    </div>
                </section>

                <section id="seccion-xiii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XIII. Propiedad Intelectual</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Todos los derechos de propiedad intelectual sobre la Plataforma, incluyendo su diseño, código fuente, logotipos, marcas, textos y demás elementos, son propiedad del IYEM o de sus licenciantes, y están protegidos por las leyes mexicanas e internacionales aplicables.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Queda prohibido a las Personas Usuarias:
                        </p>
                        <ol class="list-[lower-alpha] pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>Reproducir, distribuir o comunicar públicamente cualquier elemento de la Plataforma sin autorización expresa y escrita del IYEM.</li>
                            <li>Modificar, adaptar o crear obras derivadas basadas en los contenidos de la Plataforma.</li>
                            <li>Utilizar las marcas, logotipos o nombres del IYEM con fines comerciales o de cualquier otra índole sin autorización previa.</li>
                        </ol>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            La Persona Usuaria conserva la titularidad de los derechos de propiedad intelectual sobre el contenido que publique en la Plataforma, otorgando únicamente la licencia descrita en la Sección IX.
                        </p>
                    </div>
                </section>

                <section id="seccion-xiv" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XIV. Uso de la Imagen Institucional</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El registro o uso de la Plataforma no otorga a la Persona Usuaria ningún derecho para utilizar el nombre, logotipo, escudo o cualquier otro elemento de identidad gráfica del IYEM o del Gobierno del Estado de Yucatán.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Cualquier uso no autorizado de la imagen institucional podrá dar lugar a las acciones legales correspondientes.
                        </p>
                    </div>
                </section>

                <section id="seccion-xv" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XV. Protección de Datos Personales</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El tratamiento de los datos personales de las Personas Usuarias se realizará conforme a la legislación mexicana vigente en materia de protección de datos personales y en apego al
                            <Link :href="route('aviso.privacidad')" class="text-guinda-600 dark:text-guinda-400 hover:underline">Aviso de Privacidad</Link>
                            disponible en la Plataforma.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            La Persona Usuaria podrá ejercer sus derechos de Acceso, Rectificación, Cancelación u Oposición (derechos ARCO) a través de los canales de contacto institucionales del IYEM.
                        </p>
                    </div>
                </section>

                <section id="seccion-xvi" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XVI. Suspensión, Restricción y Cancelación de Cuentas</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM podrá suspender, restringir o cancelar la cuenta de una Persona Usuaria, de manera temporal o definitiva, en los siguientes casos:
                        </p>
                        <ol class="list-decimal pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>Incumplimiento de los presentes Términos y Condiciones.</li>
                            <li>Proporcionar información falsa o engañosa.</li>
                            <li>Realizar conductas prohibidas conforme a la Sección VIII.</li>
                            <li>Uso fraudulento de la Plataforma.</li>
                            <li>Solicitud expresa de la Persona Usuaria.</li>
                            <li>Disposición legal o requerimiento de autoridad competente.</li>
                            <li>Inactividad prolongada de la cuenta, conforme a los criterios definidos por el IYEM.</li>
                            <li>Detección de vulneraciones a la seguridad de la Plataforma atribuibles a la cuenta.</li>
                            <li>Publicación de contenido que viole derechos de terceros.</li>
                            <li>Realización de actividades ilícitas a través de la Plataforma.</li>
                            <li>Daño a la imagen o reputación del IYEM o del Gobierno del Estado de Yucatán.</li>
                            <li>Uso de la cuenta por persona distinta al titular registrado.</li>
                            <li>Cualquier otra causa que, a juicio del IYEM, justifique la medida.</li>
                        </ol>
                    </div>
                </section>

                <section id="seccion-xvii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XVII. Retiro de Contenido</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM podrá retirar o dar de baja el contenido publicado por una Persona Usuaria cuando:
                        </p>
                        <ol class="list-decimal pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>Contravenga los presentes Términos y Condiciones.</li>
                            <li>Sea contrario a la legislación aplicable.</li>
                            <li>Vulnere derechos de propiedad intelectual de terceros.</li>
                            <li>Contenga información falsa, engañosa o que induzca a error.</li>
                            <li>Sea de naturaleza ofensiva, discriminatoria o inapropiada.</li>
                            <li>Implique riesgos para la seguridad o integridad de otros usuarios.</li>
                            <li>Exista una orden de autoridad competente en tal sentido.</li>
                            <li>El IYEM lo estime necesario para el correcto funcionamiento de la Plataforma.</li>
                        </ol>
                    </div>
                </section>

                <section id="seccion-xviii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XVIII. Disponibilidad y Funcionamiento</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM no garantiza la disponibilidad ininterrumpida de la Plataforma, y podrá suspender temporal o definitivamente el acceso a la misma por causas que incluyen, de manera enunciativa:
                        </p>
                        <ol class="list-decimal pl-5 space-y-1.5 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            <li>Mantenimiento preventivo o correctivo.</li>
                            <li>Actualizaciones tecnológicas.</li>
                            <li>Fallas en los sistemas de telecomunicaciones.</li>
                            <li>Ataques cibernéticos o vulneraciones de seguridad.</li>
                            <li>Desastres naturales o emergencias.</li>
                            <li>Disposiciones legales o administrativas.</li>
                            <li>Fuerza mayor o caso fortuito.</li>
                            <li>Cualquier otra causa ajena a la voluntad del IYEM.</li>
                        </ol>
                    </div>
                </section>

                <section id="seccion-xix" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XIX. Modificaciones</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM se reserva el derecho de modificar, actualizar o sustituir los presentes Términos y Condiciones en cualquier momento, con o sin previo aviso.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Las modificaciones entrarán en vigor a partir de su publicación en la Plataforma. El uso continuado de la Plataforma después de la publicación de cualquier cambio se considerará como aceptación de los nuevos Términos y Condiciones.
                        </p>
                    </div>
                </section>

                <section id="seccion-xx" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XX. Comunicaciones y Notificaciones</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Todas las comunicaciones y notificaciones entre el IYEM y la Persona Usuaria se realizarán a través del correo electrónico registrado al momento del alta en la Plataforma.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            La Persona Usuaria es responsable de mantener actualizado su correo electrónico de contacto y de revisar periódicamente su bandeja de entrada, incluyendo la carpeta de correos no deseados o spam.
                        </p>
                    </div>
                </section>

                <section id="seccion-xxi" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XXI. Inexistencia de Relación Jurídica</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El registro y uso de la Plataforma no implica la existencia de ninguna relación jurídica entre la Persona Usuaria y el IYEM, más allá del acceso y uso del servicio digital en los términos aquí establecidos.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            En ningún caso podrá interpretarse que existe entre las partes una relación laboral, societaria, de mandato, agencia comercial o cualquier otra forma de asociación o representación.
                        </p>
                    </div>
                </section>

                <section id="seccion-xxii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XXII. Caso Fortuito y Fuerza Mayor</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El IYEM no será responsable por incumplimientos o retrasos en la prestación de los servicios que sean consecuencia de eventos de caso fortuito o fuerza mayor, incluyendo pero no limitado a: desastres naturales, pandemias, actos de autoridad, conflictos armados, huelgas, fallas generalizadas de telecomunicaciones o cualquier otra circunstancia ajena a su control.
                        </p>
                    </div>
                </section>

                <section id="seccion-xxiii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XXIII. Legislación Aplicable y Jurisdicción</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Los presentes Términos y Condiciones se regirán e interpretarán conforme a las leyes vigentes de los Estados Unidos Mexicanos y, en particular, del Estado de Yucatán.
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Para cualquier controversia derivada de la interpretación, cumplimiento o incumplimiento de los presentes Términos y Condiciones, las partes se someten expresamente a la jurisdicción y competencia de los tribunales competentes de la Ciudad de Mérida, Yucatán, renunciando a cualquier otro fuero que pudiera corresponderles en razón de sus domicilios presentes o futuros.
                        </p>
                    </div>
                </section>

                <section id="seccion-xxiv" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XXIV. Nulidad Parcial</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Si alguna disposición de los presentes Términos y Condiciones fuera declarada nula, inválida o inaplicable por autoridad competente, dicha nulidad no afectará la validez y vigencia de las demás disposiciones, las cuales continuarán en plena vigencia.
                        </p>
                    </div>
                </section>

                <section id="seccion-xxv" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XXV. No Renuncia de Derechos</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            El hecho de que el IYEM no exija el cumplimiento de alguna disposición de los presentes Términos y Condiciones, o no ejercite algún derecho previsto en los mismos, no constituirá una renuncia a dichas disposiciones o derechos, los cuales permanecerán vigentes.
                        </p>
                    </div>
                </section>

                <section id="seccion-xxvi" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XXVI. Integridad</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Los presentes Términos y Condiciones, conjuntamente con el Aviso de Privacidad de la Plataforma, constituyen el acuerdo íntegro entre el IYEM y la Persona Usuaria respecto al uso de la Plataforma, sustituyendo cualquier acuerdo, declaración o entendimiento previo, verbal o escrito, relacionado con el mismo objeto.
                        </p>
                    </div>
                </section>

                <section id="seccion-xxvii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XXVII. Contacto</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Para cualquier consulta, aclaración o comunicación relacionada con los presentes Términos y Condiciones, la Persona Usuaria podrá dirigirse al IYEM a través de los canales de contacto institucionales disponibles en el sitio web oficial del Gobierno del Estado de Yucatán.
                        </p>
                    </div>
                </section>

                <section id="seccion-xxviii" class="scroll-mt-20 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-guinda-700 dark:text-guinda-400 mb-4">XXVIII. Vigencia</h2>
                    <div class="space-y-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Los presentes Términos y Condiciones entran en vigor a partir de su publicación en la Plataforma y permanecerán vigentes hasta que sean modificados o sustituidos conforme a lo establecido en la Sección XIX.
                        </p>
                    </div>
                </section>

            </div>

            <!-- Botón volver -->
            <div class="text-center mt-10">
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
