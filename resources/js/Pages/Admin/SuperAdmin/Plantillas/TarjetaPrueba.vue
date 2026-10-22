<script setup>
import { ref, watch } from 'vue';

/**
 * Envio de prueba del borrador. Se puede elegir un usuario registrado: su
 * correo se pone como destino y sus datos rellenan las variables (tambien en
 * la vista previa). El correo se puede cambiar despues sin perder el usuario.
 */
const props = defineProps({
    titulo:   String,
    texto:    String,
    email:    String,
    usuario:  Object,
    enviando: Boolean,
    aviso:    String,
    avisoOk:  Boolean,
});
const emit = defineEmits(['update:email', 'update:usuario', 'enviar']);

const roles = { comprador: 'Comprador', proveedor: 'Proveedor' };

const busqueda = ref('');
const resultados = ref([]);
const buscando = ref(false);
const abierto = ref(false);

let temporizador = null;
let ultima = 0;
watch(busqueda, (q) => {
    clearTimeout(temporizador);
    if (q.trim().length < 2) { resultados.value = []; buscando.value = false; return; }
    buscando.value = true;
    temporizador = setTimeout(async () => {
        const turno = ++ultima;
        try {
            const res = await fetch(route('admin.plantillas.buscar-usuarios', { q: q.trim() }), {
                headers: { 'Accept': 'application/json' },
            });
            const data = res.ok ? await res.json() : [];
            // Solo cuenta la respuesta de la ultima busqueda
            if (turno === ultima) resultados.value = data;
        } finally {
            if (turno === ultima) buscando.value = false;
        }
    }, 300);
});

const elegir = (u) => {
    emit('update:usuario', u);
    emit('update:email', u.email);
    busqueda.value = '';
    abierto.value = false;
};

const quitar = () => emit('update:usuario', null);
</script>

<template>
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-5">
        <div class="flex items-center gap-2 mb-1">
            <svg class="w-4 h-4 text-guinda-700 dark:text-guinda-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ titulo }}</h3>
        </div>
        <p class="text-xs text-gray-400 mb-3">{{ texto }}</p>

        <!-- Usuario elegido -->
        <div v-if="usuario"
            class="flex items-center gap-3 p-2.5 mb-2 rounded-xl bg-guinda-50/60 dark:bg-guinda-900/10 border border-guinda-100 dark:border-guinda-900/40">
            <span class="w-8 h-8 shrink-0 rounded-full bg-guinda-700 text-white text-xs font-bold flex items-center justify-center uppercase">
                {{ (usuario.name || '?').charAt(0) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-xs font-semibold text-gray-900 dark:text-white truncate">{{ usuario.name }}</p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">Sus datos rellenan las variables</p>
            </div>
            <button type="button" @click="quitar" title="Usar datos de ejemplo"
                class="p-1 rounded-lg text-gray-400 hover:text-red-600 hover:bg-white dark:hover:bg-gray-800">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <!-- Buscador -->
        <div v-else class="relative mb-2">
            <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            <input v-model="busqueda" type="search" placeholder="Buscar usuario (nombre, correo, empresa)"
                @focus="abierto = true" @keydown.enter.prevent @keydown.esc="abierto = false"
                class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400" />

            <div v-if="abierto && busqueda.trim().length >= 2" class="fixed inset-0 z-10" @click="abierto = false"></div>
            <div v-if="abierto && busqueda.trim().length >= 2"
                class="absolute left-0 right-0 top-full mt-1 z-20 max-h-64 overflow-y-auto py-1 rounded-xl bg-white dark:bg-gray-800 shadow-lg border border-gray-100 dark:border-gray-700">
                <p v-if="buscando" class="px-3 py-2 text-xs text-gray-400">Buscando...</p>
                <p v-else-if="!resultados.length" class="px-3 py-2 text-xs text-gray-400">Sin resultados</p>
                <button v-for="u in resultados" :key="u.id" type="button" @click="elegir(u)"
                    class="w-full text-left px-3 py-2 hover:bg-guinda-50 dark:hover:bg-guinda-900/20">
                    <span class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ u.name }}</span>
                        <span v-if="roles[u.active_role]" class="shrink-0 text-[10px] font-semibold text-gray-400 uppercase">{{ roles[u.active_role] }}</span>
                    </span>
                    <span class="block text-[11px] text-gray-400 truncate">{{ u.email }}<template v-if="u.nombre_empresa"> · {{ u.nombre_empresa }}</template></span>
                </button>
            </div>
        </div>

        <input :value="email" @input="emit('update:email', $event.target.value)" type="email" placeholder="tucorreo@ejemplo.com"
            @keydown.enter.prevent="emit('enviar')"
            class="w-full px-3 py-2 text-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:ring-2 focus:ring-guinda-400 mb-1" />
        <p class="text-[11px] text-gray-400 mb-2">
            {{ usuario ? 'Puedes cambiar el correo para recibirlo tú con los datos de este usuario.' : 'Sin usuario se envía con datos de ejemplo.' }}
        </p>
        <button type="button" @click="emit('enviar')" :disabled="enviando || !email"
            class="w-full px-3 py-2 text-sm font-semibold rounded-xl border border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-50 dark:hover:bg-guinda-950/20 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
            {{ enviando ? 'Enviando...' : 'Enviar prueba' }}
        </button>
        <p v-if="aviso" class="mt-2 text-xs" :class="avisoOk ? 'text-emerald-600' : 'text-red-600'">
            {{ aviso }}
        </p>
    </div>
</template>
