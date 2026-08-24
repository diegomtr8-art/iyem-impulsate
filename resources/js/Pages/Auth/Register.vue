<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';

const form = useForm({
    name: '',
    email: '',
    telefono: '',
    curp: '',
    password: '',
    password_confirmation: '',
    terms: false,
    acepta_aviso: false,
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Registrarse — Encuentro de Negocios Impulsate" />

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

                <div class="mb-2 text-white/60 text-xs font-bold tracking-[0.25em] uppercase">Únete al programa</div>
                <h1 class="text-3xl font-black text-white mb-3 leading-tight">
                    Encuentro de<br />
                    <span class="text-guinda-200">Negocios</span><br />
                    Impulsate
                </h1>
                <p class="text-white/70 text-sm leading-relaxed mb-8">
                    Regístrate y accede a citas de networking con los mejores proveedores de Yucatán. Completamente gratuito.
                </p>

                <div class="space-y-3 text-left">
                    <div v-for="(b, i) in [
                        { icon: '✅', texto: 'Hasta 12 citas de networking' },
                        { icon: '📍', texto: 'Oficina en Mérida, Yucatán' },
                        { icon: '🤝', texto: 'Proveedores verificados' },
                        { icon: '🆓', texto: '100% gratuito, programa gubernamental' },
                    ]" :key="i"
                        class="flex items-center gap-3 bg-white/10 rounded-xl px-4 py-3 border border-white/10 backdrop-blur-sm">
                        <span class="text-lg">{{ b.icon }}</span>
                        <span class="text-white/80 text-sm">{{ b.texto }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel derecho — formulario -->
        <div class="w-full lg:w-1/2 flex items-center justify-center px-6 py-12 bg-white dark:bg-gray-950 overflow-y-auto transition-colors">
            <div class="w-full max-w-md">
                <!-- Logo mobile -->
                <div class="lg:hidden flex items-center justify-center mb-8">
                    <img src="/images/logo_impulsate.png" alt="Impulsate" class="h-10 w-auto" />
                </div>

                <h2 class="text-2xl font-black text-gray-900 dark:text-white mb-2">Crea tu cuenta</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">Es gratuito y tarda menos de un minuto. Podrás elegir tu rol al iniciar sesión.</p>

                <!-- Google OAuth button -->
                <a :href="route('auth.google')"
                   class="flex items-center justify-center gap-3 w-full border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-200 rounded-xl py-3 text-sm font-medium transition-colors mb-5 shadow-sm">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Continuar con Google
                </a>

                <div class="flex items-center gap-3 mb-5">
                    <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                    <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">o con tu correo</span>
                    <div class="flex-1 h-px bg-gray-200 dark:bg-gray-700"></div>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Nombre completo</label>
                        <input v-model="form.name" type="text" required autofocus autocomplete="name"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="Juan Perez Hernandez" />
                        <InputError class="mt-1.5" :message="form.errors.name" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Correo electrónico</label>
                        <input v-model="form.email" type="email" required autocomplete="username"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="tu@email.com" />
                        <InputError class="mt-1.5" :message="form.errors.email" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Teléfono <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input v-model="form.telefono" type="tel" autocomplete="tel" maxlength="10"
                            @input="form.telefono = form.telefono.replace(/\D/g, '').slice(0, 10)"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="9991234567" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            CURP <span class="text-gray-400 font-normal">(opcional)</span>
                        </label>
                        <input v-model="form.curp" type="text" maxlength="18"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors placeholder-gray-400 dark:placeholder-gray-600 uppercase"
                            placeholder="XXXX000000XXXXXXXX" style="text-transform:uppercase" />
                        <InputError class="mt-1.5" :message="form.errors.curp" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Contraseña</label>
                        <input v-model="form.password" type="password" required autocomplete="new-password"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="Mínimo 8 caracteres" />
                        <InputError class="mt-1.5" :message="form.errors.password" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Confirmar contraseña</label>
                        <input v-model="form.password_confirmation" type="password" required autocomplete="new-password"
                            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-guinda-500 dark:focus:border-guinda-500 transition-colors placeholder-gray-400 dark:placeholder-gray-600"
                            placeholder="••••••••" />
                        <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
                    </div>

                    <div class="pt-1">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <Checkbox id="acepta_aviso" v-model:checked="form.acepta_aviso" name="acepta_aviso" required class="mt-0.5" />
                            <span class="text-sm text-gray-500 dark:text-gray-400">
                                Al registrarme, acepto el
                                <Link :href="route('aviso.privacidad')" target="_blank"
                                    class="text-guinda-700 dark:text-guinda-400 underline">
                                    Aviso de Privacidad
                                </Link>
                                y los
                                <Link :href="route('terminos.condiciones')" target="_blank"
                                    class="text-guinda-700 dark:text-guinda-400 underline">
                                    Términos y Condiciones
                                </Link>
                                del Instituto Yucateco de Emprendedores (IYEM).
                            </span>
                        </label>
                        <InputError class="mt-1.5" :message="form.errors.acepta_aviso" />
                    </div>

                    <button type="submit" :disabled="form.processing || !form.acepta_aviso"
                        class="w-full py-3 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-xl transition-colors text-sm mt-2 shadow-sm shadow-guinda-900/20">
                        {{ form.processing ? 'Creando cuenta...' : 'Crear cuenta gratis' }}
                    </button>

                    <p class="text-center text-sm text-gray-500 dark:text-gray-400">
                        ¿Ya tienes cuenta?
                        <Link :href="route('login')" class="text-guinda-700 dark:text-guinda-400 hover:text-guinda-600 font-medium transition-colors">
                            Inicia sesión
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
