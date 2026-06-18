<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    usuario: Object,
    rolesDisponibles: Array,
});

const page = usePage();
const esMiCuenta = props.usuario.id === page.props.auth.user.id;

// Formulario de datos generales
const formDatos = useForm({
    name:                   props.usuario.name ?? '',
    email:                  props.usuario.email ?? '',
    telefono:               props.usuario.telefono ?? '',
    curp:                   props.usuario.curp ?? '',
    rfc:                    props.usuario.rfc ?? '',
    municipio:              props.usuario.municipio ?? '',
    nombre_empresa:         props.usuario.nombre_empresa ?? '',
    sitio_web:              props.usuario.sitio_web ?? '',
    camara_asociacion:      props.usuario.camara_asociacion ?? '',
    nombre_establecimiento: props.usuario.nombre_establecimiento ?? '',
});

// Formulario de contraseña
const formPwd = useForm({
    password:               '',
    password_confirmation:  '',
});

// Formulario de roles
const rolesSeleccionados = ref([...props.usuario.roles]);
const formRoles = useForm({ roles: rolesSeleccionados });

const guardarDatos = () =>
    formDatos.put(route('admin.usuarios-gestion.update', props.usuario.id));

const guardarPassword = () =>
    formPwd.post(route('admin.usuarios-gestion.password', props.usuario.id), {
        onSuccess: () => formPwd.reset(),
    });

const guardarRoles = () => {
    formRoles.roles = [...rolesSeleccionados.value];
    formRoles.post(route('admin.usuarios-gestion.roles', props.usuario.id));
};

const toggleRol = (rol) => {
    if (rolesSeleccionados.value.includes(rol)) {
        rolesSeleccionados.value = rolesSeleccionados.value.filter(r => r !== rol);
    } else {
        rolesSeleccionados.value.push(rol);
    }
};

const badgeClass = (rol) => {
    const map = {
        'super-admin':   'bg-guinda-100 text-guinda-800 dark:bg-guinda-900/30 dark:text-guinda-300',
        'admin':         'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'restaurantero': 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
        'cliente':       'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300',
    };
    return map[rol] ?? 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300';
};
</script>

<template>
    <AdminLayout title="Editar Usuario">
        <Head :title="`Editar — ${usuario.name}`" />

        <div class="space-y-6 max-w-3xl">
            <!-- Back + Header -->
            <div class="flex items-center gap-3">
                <Link :href="route('admin.usuarios-gestion.index')"
                    class="p-1.5 rounded-lg text-gray-400 hover:text-guinda-700 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </Link>
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ usuario.name }}</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ usuario.email }}</p>
                </div>
                <span v-if="esMiCuenta"
                    class="ml-2 text-xs bg-guinda-100 dark:bg-guinda-900/30 text-guinda-700 dark:text-guinda-400 px-2 py-0.5 rounded-full font-medium">
                    Mi cuenta
                </span>
            </div>

            <!-- Datos generales -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-5">Datos generales</h2>
                <form @submit.prevent="guardarDatos" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre</label>
                            <input v-model="formDatos.name" type="text"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                            <p v-if="formDatos.errors.name" class="mt-1 text-xs text-red-500">{{ formDatos.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Correo electrónico</label>
                            <input v-model="formDatos.email" type="email"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                            <p v-if="formDatos.errors.email" class="mt-1 text-xs text-red-500">{{ formDatos.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Teléfono</label>
                            <input v-model="formDatos.telefono" type="text"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Empresa</label>
                            <input v-model="formDatos.nombre_empresa" type="text"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">CURP</label>
                            <input v-model="formDatos.curp" type="text" maxlength="18"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">RFC</label>
                            <input v-model="formDatos.rfc" type="text" maxlength="13"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Municipio</label>
                            <input v-model="formDatos.municipio" type="text"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Sitio web</label>
                            <input v-model="formDatos.sitio_web" type="url" placeholder="https://"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        </div>
                    </div>
                    <div class="col-span-full">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">¿Cámara o asociación?</label>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="opcion in ['CANIRAC', 'Ninguna']" :key="opcion"
                                class="flex items-center gap-2 cursor-pointer px-3 py-2 rounded-xl border transition-all text-sm"
                                :class="formDatos.camara_asociacion === opcion
                                    ? 'border-guinda-500 bg-guinda-50 dark:bg-guinda-900/20 dark:border-guinda-500'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-guinda-300'">
                                <input type="radio" :value="opcion" v-model="formDatos.camara_asociacion" class="accent-guinda-700" />
                                <span class="text-gray-800 dark:text-gray-200 font-medium">{{ opcion }}</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer px-3 py-2 rounded-xl border transition-all text-sm"
                                :class="formDatos.camara_asociacion === ''
                                    ? 'border-gray-300 bg-gray-50 dark:bg-gray-800 dark:border-gray-600'
                                    : 'border-gray-200 dark:border-gray-700 hover:border-gray-400'">
                                <input type="radio" value="" v-model="formDatos.camara_asociacion" class="accent-gray-500" />
                                <span class="text-gray-500 dark:text-gray-400">Sin especificar</span>
                            </label>
                        </div>
                    </div>
                    <div v-if="formDatos.camara_asociacion === 'CANIRAC'">
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nombre del establecimiento</label>
                        <input v-model="formDatos.nombre_establecimiento" type="text"
                            placeholder="Restaurante o establecimiento de alimentos"
                            class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="formDatos.processing"
                            class="px-5 py-2 text-sm font-semibold rounded-xl bg-guinda-700 hover:bg-guinda-800 text-white transition-colors disabled:opacity-60">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </div>

            <!-- Contraseña -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-5">Cambiar contraseña</h2>
                <form @submit.prevent="guardarPassword" class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Nueva contraseña</label>
                            <input v-model="formPwd.password" type="password" autocomplete="new-password"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                            <p v-if="formPwd.errors.password" class="mt-1 text-xs text-red-500">{{ formPwd.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Confirmar contraseña</label>
                            <input v-model="formPwd.password_confirmation" type="password" autocomplete="new-password"
                                class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 transition-colors" />
                        </div>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" :disabled="formPwd.processing"
                            class="px-5 py-2 text-sm font-semibold rounded-xl bg-guinda-700 hover:bg-guinda-800 text-white transition-colors disabled:opacity-60">
                            Actualizar contraseña
                        </button>
                    </div>
                </form>
            </div>

            <!-- Roles -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-2">Roles asignados</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500 mb-5">
                    Los roles controlan el acceso a los distintos paneles del sistema.
                    <span v-if="esMiCuenta" class="text-guinda-500 font-medium">No puedes quitarte el rol super-admin a ti mismo.</span>
                </p>
                <form @submit.prevent="guardarRoles">
                    <p v-if="formRoles.errors.roles" class="mb-3 text-xs text-red-500 font-medium">{{ formRoles.errors.roles }}</p>
                    <div class="flex flex-wrap gap-3 mb-5">
                        <button v-for="rol in rolesDisponibles" :key="rol"
                            type="button"
                            :disabled="esMiCuenta && rol === 'super-admin'"
                            @click="toggleRol(rol)"
                            :class="[
                                'px-4 py-2 text-sm rounded-xl border-2 font-medium transition-all',
                                rolesSeleccionados.includes(rol)
                                    ? 'border-guinda-600 bg-guinda-50 dark:bg-guinda-900/20 text-guinda-800 dark:text-guinda-300'
                                    : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400 hover:border-guinda-300',
                                esMiCuenta && rol === 'super-admin' ? 'opacity-50 cursor-not-allowed' : ''
                            ]">
                            <span v-if="rolesSeleccionados.includes(rol)" class="mr-1">✓</span>
                            {{ rol }}
                        </button>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="formRoles.processing"
                            class="px-5 py-2 text-sm font-semibold rounded-xl bg-guinda-700 hover:bg-guinda-800 text-white transition-colors disabled:opacity-60">
                            Guardar roles
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>
