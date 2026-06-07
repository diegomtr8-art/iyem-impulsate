<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head } from '@inertiajs/vue3';

const rolSeleccionado = ref(null);
const cargando = ref(false);

const roles = [
    {
        valor: 'comprador',
        icono: '🛒',
        titulo: 'Comprador',
        descripcion: 'Agenda citas con proveedores del evento y descubre productos y servicios.',
        color: 'from-guinda-500 to-guinda-700',
        hoverBorder: 'hover:border-guinda-500',
        activeBorder: 'border-guinda-500',
        activeBg: 'bg-guinda-50 dark:bg-guinda-900/20',
        checkColor: 'text-guinda-500',
    },
    {
        valor: 'proveedor',
        icono: '🏪',
        titulo: 'Proveedor',
        descripcion: 'Muestra tus productos y servicios, recibe compradores y gestiona tus citas.',
        color: 'from-guinda-600 to-guinda-800',
        hoverBorder: 'hover:border-guinda-500',
        activeBorder: 'border-guinda-500',
        activeBg: 'bg-guinda-50 dark:bg-guinda-900/20',
        checkColor: 'text-guinda-500',
    },
    {
        valor: 'ambos',
        icono: '⚡',
        titulo: 'Ambos',
        descripcion: 'Accede a ambos paneles con un solo clic. Vende y compra en el mismo evento.',
        color: 'from-purple-500 to-purple-700',
        hoverBorder: 'hover:border-purple-500',
        activeBorder: 'border-purple-500',
        activeBg: 'bg-purple-50 dark:bg-purple-900/20',
        checkColor: 'text-purple-500',
    },
];

const confirmar = () => {
    if (!rolSeleccionado.value || cargando.value) return;
    cargando.value = true;
    router.post(route('rol.seleccionar.store'), { rol: rolSeleccionado.value }, {
        onFinish: () => { cargando.value = false; },
    });
};
</script>

<template>
    <Head title="¿Cómo quieres usar la plataforma?" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-guinda-950 to-gray-900 px-4 py-12">

        <!-- Blobs de fondo -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-guinda-700/20 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-purple-700/20 rounded-full blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-guinda-700/10 rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 w-full max-w-3xl">

            <!-- Header -->
            <div class="text-center mb-10 animate-fade-in">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-white/10 rounded-2xl mb-5 backdrop-blur-sm">
                    <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-10 w-auto" />
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-white mb-3">
                    ¿Cómo quieres usar la plataforma?
                </h1>
                <p class="text-gray-400 text-base sm:text-lg">
                    Puedes cambiar esto más adelante desde tu perfil
                </p>
            </div>

            <!-- Tarjetas de rol -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
                <button
                    v-for="rol in roles"
                    :key="rol.valor"
                    @click="rolSeleccionado = rol.valor"
                    :class="[
                        'relative p-6 rounded-2xl border-2 text-left transition-all duration-300 cursor-pointer group',
                        rolSeleccionado === rol.valor
                            ? ['border-2', rol.activeBorder, rol.activeBg, 'shadow-xl scale-[1.02]']
                            : ['border-white/10 bg-white/5 hover:bg-white/10', rol.hoverBorder, 'hover:scale-[1.01]'],
                        'backdrop-blur-sm'
                    ]"
                >
                    <!-- Check indicator -->
                    <div v-if="rolSeleccionado === rol.valor"
                        class="absolute top-3 right-3 w-6 h-6 rounded-full bg-white flex items-center justify-center shadow-sm">
                        <svg :class="['w-4 h-4', rol.checkColor]" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>

                    <!-- Icono -->
                    <div class="text-4xl mb-4 transition-transform duration-300 group-hover:scale-110">
                        {{ rol.icono }}
                    </div>

                    <!-- Texto -->
                    <h3 class="font-bold text-white text-lg mb-2">{{ rol.titulo }}</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">{{ rol.descripcion }}</p>
                </button>
            </div>

            <!-- Botón confirmar -->
            <div class="flex justify-center">
                <button
                    @click="confirmar"
                    :disabled="!rolSeleccionado || cargando"
                    :class="[
                        'px-10 py-3.5 rounded-2xl font-bold text-base transition-all duration-300',
                        rolSeleccionado && !cargando
                            ? 'bg-white text-gray-900 hover:bg-gray-100 shadow-xl hover:shadow-2xl hover:scale-105 cursor-pointer'
                            : 'bg-white/20 text-white/50 cursor-not-allowed'
                    ]"
                >
                    <span v-if="cargando" class="flex items-center gap-2">
                        <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Configurando...
                    </span>
                    <span v-else>
                        {{ rolSeleccionado ? 'Continuar como ' + roles.find(r => r.valor === rolSeleccionado)?.titulo : 'Selecciona una opción' }}
                    </span>
                </button>
            </div>

        </div>
    </div>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeInUp 0.5s ease-out both;
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>
