<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ status: String });

const form = useForm({});
const submit = () => form.post(route('verification.send'));
const enviado = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Verifica tu correo — Impulsate" />

    <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex transition-colors duration-300">

        <!-- Panel izquierdo (desktop) -->
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
                <div class="mb-2 text-guinda-200 text-xs font-bold tracking-[0.25em] uppercase">Programa Oficial 2026</div>
                <h1 class="text-3xl font-black text-white mb-3 leading-tight">
                    Encuentro de<br />
                    <span class="text-guinda-200">Negocios</span><br />
                    Impulsate
                </h1>
                <p class="text-white/70 text-sm leading-relaxed mb-8">
                    Un paso más para conectar con los mejores proveedores de Yucatán.
                </p>
                <div class="bg-white/10 border border-white/20 rounded-xl px-5 py-4 backdrop-blur-sm">
                    <p class="text-white/80 text-sm leading-relaxed">
                        Revisa tu bandeja de entrada y también la carpeta de <strong class="text-white">spam o correo no deseado</strong>.
                    </p>
                </div>
            </div>
        </div>

        <!-- Panel derecho -->
        <div class="w-full lg:w-1/2 flex items-center justify-center px-6 py-12 bg-white dark:bg-gray-950 transition-colors">
            <div class="w-full max-w-md">

                <!-- Logo mobile -->
                <div class="lg:hidden flex items-center justify-center mb-8">
                    <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-10 w-auto" />
                </div>

                <!-- Icono -->
                <div class="flex items-center justify-center w-16 h-16 rounded-2xl bg-guinda-50 dark:bg-guinda-900/30 border border-guinda-200 dark:border-guinda-800 mb-6 mx-auto">
                    <svg class="w-8 h-8 text-guinda-600 dark:text-guinda-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </div>

                <h2 class="text-2xl font-black text-gray-900 dark:text-white mb-2 text-center">Verifica tu correo</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-8 text-center leading-relaxed">
                    Te enviamos un enlace de verificación. Revisa tu bandeja de entrada y haz clic en el enlace para activar tu cuenta.
                </p>

                <!-- Confirmación de envío -->
                <div v-if="enviado"
                     class="mb-6 flex items-start gap-3 bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-500/30 text-emerald-700 dark:text-emerald-400 text-sm px-4 py-3 rounded-xl">
                    <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Nuevo enlace enviado. Revisa tu bandeja de entrada.</span>
                </div>

                <!-- Pasos -->
                <div class="space-y-3 mb-8">
                    <div v-for="(paso, i) in [
                        { n: '1', texto: 'Abre tu correo electrónico' },
                        { n: '2', texto: 'Busca el mensaje de Impulsate' },
                        { n: '3', texto: 'Haz clic en el enlace de verificación' },
                    ]" :key="i"
                        class="flex items-center gap-4 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl px-4 py-3">
                        <div class="w-7 h-7 rounded-full bg-guinda-700 text-white text-xs font-black flex items-center justify-center shrink-0">
                            {{ paso.n }}
                        </div>
                        <span class="text-sm text-gray-700 dark:text-gray-300">{{ paso.texto }}</span>
                    </div>
                </div>

                <!-- Reenviar -->
                <form @submit.prevent="submit">
                    <button type="submit" :disabled="form.processing"
                        class="w-full py-3 bg-guinda-700 hover:bg-guinda-600 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-colors text-sm shadow-sm">
                        {{ form.processing ? 'Enviando...' : 'Reenviar correo de verificación' }}
                    </button>
                </form>

                <div class="mt-6 flex items-center justify-center gap-6">
                    <Link :href="route('profile.show')"
                        class="text-sm text-gray-500 dark:text-gray-400 hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors">
                        Editar perfil
                    </Link>
                    <span class="text-gray-300 dark:text-gray-700">|</span>
                    <Link :href="route('logout')" method="post" as="button"
                        class="text-sm text-gray-500 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors">
                        Cerrar sesión
                    </Link>
                </div>

                <div class="mt-10 text-center">
                    <p class="text-xs text-gray-400 dark:text-gray-600">
                        Programa Gubernamental • Gobierno del Estado de Yucatán
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
