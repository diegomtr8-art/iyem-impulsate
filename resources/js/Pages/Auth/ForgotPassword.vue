<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

defineProps({
    status: String,
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Recuperar contraseña — Encuentro de Negocios Impulsate" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex transition-colors duration-300">
        <!-- Panel izquierdo — solo desktop -->
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden flex-col items-center justify-center px-12"
             style="background: linear-gradient(135deg, #45060f 0%, #710d21 50%, #8b1028 100%)">
            <div class="absolute top-0 left-0 w-96 h-96 rounded-full opacity-20 blur-3xl"
                 style="background: radial-gradient(circle, #f89aa9, transparent)"></div>
            <div class="absolute bottom-0 right-0 w-80 h-80 rounded-full opacity-15 blur-3xl"
                 style="background: radial-gradient(circle, #fbc4cd, transparent)"></div>

            <div class="relative z-10 text-center max-w-sm">
                <div class="flex items-center justify-center mb-8">
                    <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-16 w-auto drop-shadow-2xl" />
                </div>

                <div class="mb-2 text-guinda-200 text-xs font-bold tracking-[0.25em] uppercase">
                    Recuperación de cuenta
                </div>

                <h1 class="text-3xl font-black text-white mb-3 leading-tight">
                    Encuentro de<br />
                    <span class="text-guinda-200">Negocios</span><br />
                    Impulsate
                </h1>

                <p class="text-white/70 text-sm leading-relaxed mb-8">
                    No te preocupes, te enviaremos un enlace a tu correo para restablecer tu contraseña.
                </p>

                <div class="space-y-3 text-left">
                    <div v-for="(paso, i) in [
                        { num: '1', texto: 'Ingresa tu correo electrónico' },
                        { num: '2', texto: 'Revisa tu bandeja de entrada' },
                        { num: '3', texto: 'Haz clic en el enlace de recuperación' },
                        { num: '4', texto: 'Crea una nueva contraseña' },
                    ]" :key="i" class="flex items-center gap-3 bg-white/10 rounded-xl px-4 py-3 border border-white/10 backdrop-blur-sm">
                        <div class="w-6 h-6 rounded-full bg-guinda-200/30 border border-guinda-200/40 flex items-center justify-center text-xs font-bold text-white shrink-0">
                            {{ paso.num }}
                        </div>
                        <span class="text-white/80 text-sm">{{ paso.texto }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel derecho — formulario -->
        <div class="w-full lg:w-1/2 flex items-center justify-center px-6 py-12 bg-white dark:bg-gray-950 transition-colors">
            <div class="w-full max-w-md">
                <!-- Logo mobile -->
                <div class="lg:hidden flex items-center justify-center mb-8">
                    <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-10 w-auto" />
                </div>

                <!-- Icono de recuperación -->
                <div class="flex items-center justify-center mb-6">
                    <div class="w-14 h-14 rounded-2xl bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 flex items-center justify-center">
                        <svg class="w-7 h-7 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                        </svg>
                    </div>
                </div>

                <h2 class="text-2xl font-black text-gray-900 dark:text-white mb-2 text-center">Recuperar contraseña</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-8 text-center">
                    Ingresa tu correo y te enviaremos un enlace para restablecer tu contraseña.
                </p>

                <!-- Status -->
                <div v-if="status" class="mb-6 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-300 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-400 text-sm font-medium px-4 py-3 rounded-xl">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Correo electrónico
                        </label>
                        <input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="tu@email.com"
                        />
                        <InputError class="mt-1.5" :message="form.errors.email" />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-colors text-sm shadow-sm shadow-guinda-900/20"
                    >
                        {{ form.processing ? 'Enviando...' : 'Enviar enlace de recuperación' }}
                    </button>

                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        <Link :href="route('login')" class="text-guinda-700 dark:text-guinda-400 hover:text-guinda-600 dark:hover:text-guinda-300 font-medium transition-colors inline-flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                            Regresar al inicio de sesión
                        </Link>
                    </p>
                </form>

                <div class="mt-10 text-center">
                    <p class="text-xs text-gray-400 dark:text-gray-600">
                        Programa Gubernamental • Gobierno del Estado de Yucatán
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
