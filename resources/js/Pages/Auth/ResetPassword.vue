<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    email: String,
    token: String,
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.update'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Nueva contraseña — Encuentro de Negocios Impulsate" />

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
                    Nueva contraseña
                </div>

                <h1 class="text-3xl font-black text-white mb-3 leading-tight">
                    Encuentro de<br />
                    <span class="text-guinda-200">Negocios</span><br />
                    Impulsate
                </h1>

                <p class="text-white/70 text-sm leading-relaxed mb-8">
                    Elige una contraseña segura para proteger tu cuenta.
                </p>

                <!-- Tips de contraseña segura -->
                <div class="space-y-3 text-left">
                    <div v-for="(tip, i) in [
                        { icon: '🔒', texto: 'Mínimo 8 caracteres' },
                        { icon: '🔢', texto: 'Combina letras y números' },
                        { icon: '✨', texto: 'Usa mayúsculas y símbolos' },
                        { icon: '🚫', texto: 'No uses datos personales obvios' },
                    ]" :key="i"
                         class="flex items-center gap-3 bg-white/10 rounded-xl px-4 py-3 border border-white/10 backdrop-blur-sm">
                        <span class="text-base">{{ tip.icon }}</span>
                        <span class="text-white/80 text-sm">{{ tip.texto }}</span>
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

                <!-- Ícono -->
                <div class="flex items-center justify-center mb-6">
                    <div class="w-14 h-14 rounded-2xl bg-guinda-50 dark:bg-guinda-500/10 border border-guinda-200 dark:border-guinda-500/20 flex items-center justify-center">
                        <svg class="w-7 h-7 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                        </svg>
                    </div>
                </div>

                <h2 class="text-2xl font-black text-gray-900 dark:text-white mb-2 text-center">Crear nueva contraseña</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-8 text-center">
                    Ingresa y confirma tu nueva contraseña para restablecer el acceso.
                </p>

                <form @submit.prevent="submit" class="space-y-5">

                    <!-- Correo (readonly) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Correo electrónico
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            readonly
                            class="w-full bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 rounded-xl px-4 py-3 text-sm cursor-not-allowed"
                        />
                        <InputError class="mt-1.5" :message="form.errors.email" />
                    </div>

                    <!-- Nueva contraseña -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Nueva contraseña
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            autofocus
                            autocomplete="new-password"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="Mínimo 8 caracteres"
                        />
                        <InputError class="mt-1.5" :message="form.errors.password" />
                    </div>

                    <!-- Confirmar contraseña -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Confirmar contraseña
                        </label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="Repite tu nueva contraseña"
                        />
                        <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-colors text-sm shadow-sm shadow-guinda-900/20"
                    >
                        {{ form.processing ? 'Guardando...' : 'Guardar nueva contraseña' }}
                    </button>

                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        <Link :href="route('login')"
                              class="text-guinda-700 dark:text-guinda-400 hover:text-guinda-600 dark:hover:text-guinda-300 font-medium transition-colors inline-flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                            Regresar al inicio de sesión
                        </Link>
                    </p>
                </form>

                <div class="mt-10 text-center">
                    <p class="text-xs text-gray-400 dark:text-gray-600">
                        Programa Gubernamental • Instituto Yucateco de Emprendedores
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
