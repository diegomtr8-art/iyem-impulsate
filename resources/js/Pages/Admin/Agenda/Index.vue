<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({ propuestas: Object });

const badgeClase = (estado) => ({
    pendiente: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
    aceptada:  'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    rechazada: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
}[estado] || 'bg-gray-100 text-gray-600');

const enviar = (agenda) => {
    router.post(route('admin.agenda.enviar', agenda.id), {}, { preserveScroll: true });
};

const modalBorrado     = ref(false);
const propuestaABorrar = ref(null);
const borrando         = ref(false);

const confirmarBorrado = (agenda) => {
    propuestaABorrar.value = agenda;
    modalBorrado.value = true;
};

const cancelarBorrado = () => {
    modalBorrado.value = false;
    propuestaABorrar.value = null;
};

const ejecutarBorrado = () => {
    if (!propuestaABorrar.value) return;
    borrando.value = true;
    router.delete(route('admin.agenda.destroy', propuestaABorrar.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            modalBorrado.value = false;
            propuestaABorrar.value = null;
        },
        onFinish: () => {
            borrando.value = false;
        },
    });
};
</script>

<template>
    <AdminLayout title="Agenda">
        <div class="p-6 max-w-6xl mx-auto">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 dark:text-white">Agenda</h1>
                    <p class="text-gray-500 dark:text-gray-400 text-sm mt-1">
                        Propuestas de agenda enviadas a compradores por correo.
                    </p>
                </div>
                <Link :href="route('admin.agenda.crear')"
                    class="px-4 py-2.5 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-bold rounded-xl shadow-sm transition-colors">
                    + Nueva propuesta de agenda
                </Link>
            </div>

            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800/50 text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3">Comprador</th>
                            <th class="px-5 py-3">Citas propuestas</th>
                            <th class="px-5 py-3">Enviada</th>
                            <th class="px-5 py-3">Respondida</th>
                            <th class="px-5 py-3">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        <tr v-for="p in propuestas.data" :key="p.id"
                            :class="['hover:bg-gray-50 dark:hover:bg-gray-800/40', p.estado === 'rechazada' ? 'bg-red-50/50 dark:bg-red-900/10' : '']">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full overflow-hidden bg-gray-100 dark:bg-gray-800 flex items-center justify-center flex-shrink-0">
                                        <span class="text-sm">🧑‍💼</span>
                                    </div>
                                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ p.comprador?.nombre_empresa || p.comprador?.name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap gap-1.5">
                                    <span v-for="c in p.citas" :key="c.id"
                                        class="px-2 py-0.5 bg-guinda-50 dark:bg-guinda-900/20 text-guinda-700 dark:text-guinda-400 text-xs font-semibold rounded-full">
                                        {{ c.proveedor?.nombre_restaurante }}
                                    </span>
                                    <span v-if="!p.citas?.length" class="text-gray-400 text-xs">—</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-500 text-xs">
                                {{ p.enviada_at ? new Date(p.enviada_at).toLocaleString('es-MX') : '— sin enviar —' }}
                            </td>
                            <td class="px-5 py-3 text-gray-500 dark:text-gray-500 text-xs">
                                {{ p.respondida_at ? new Date(p.respondida_at).toLocaleString('es-MX') : '—' }}
                            </td>
                            <td class="px-5 py-3">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold" :class="badgeClase(p.estado)">
                                    {{ p.estado === 'pendiente' && p.enviada_at ? '⏳ Esperando respuesta' : p.estado === 'aceptada' ? '✅ Aceptada' : p.estado === 'rechazada' ? '❌ Rechazada — re-propuesta' : 'Borrador' }}
                                </span>
                                <p v-if="p.estado === 'rechazada'" class="text-xs text-red-500 dark:text-red-400 mt-1">
                                    El comprador rechazó. Haz una re-propuesta.
                                </p>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button v-if="p.estado === 'pendiente' && !p.enviada_at" @click="enviar(p)"
                                        class="px-3 py-1.5 bg-guinda-800 hover:bg-guinda-700 text-white text-xs font-bold rounded-lg transition-colors">
                                        📧 Enviar correo
                                    </button>
                                    <span v-else-if="p.estado === 'pendiente'" class="text-xs text-amber-600 dark:text-amber-400 font-medium">
                                        Esperando respuesta…
                                    </span>
                                    <Link v-if="p.estado === 'rechazada'" :href="route('admin.agenda.crear')"
                                        class="px-3 py-1.5 border border-guinda-500 text-guinda-700 dark:text-guinda-400 text-xs font-bold rounded-lg">
                                        🔄 Nueva propuesta
                                    </Link>
                                    <Link :href="route('admin.agenda.show', p.id)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold bg-gray-100 text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700 transition-colors">
                                        👁 Ver
                                    </Link>
                                    <button @click="confirmarBorrado(p)"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-bold bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-900/40 transition-colors">
                                        🗑 Borrar
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!propuestas.data?.length">
                            <td colspan="6" class="px-5 py-12 text-center text-gray-400 dark:text-gray-600">
                                No hay propuestas de agenda todavía.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Teleport to="body">
            <div v-if="modalBorrado"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm px-4">
                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 w-full max-w-md p-6">
                    <div class="text-center mb-5">
                        <div class="text-5xl mb-3">🗑️</div>
                        <h2 class="text-lg font-black text-gray-900 dark:text-white mb-2">
                            ¿Borrar esta propuesta?
                        </h2>
                        <p class="text-sm text-gray-600 dark:text-gray-400 leading-relaxed">
                            Se eliminará la propuesta de agenda del comprador
                            <strong class="text-gray-900 dark:text-white">
                                {{ propuestaABorrar?.comprador?.nombre_empresa ?? propuestaABorrar?.comprador?.name }}
                            </strong>
                            y todas sus citas asociadas.
                            <span v-if="propuestaABorrar?.estado === 'aceptada'"
                                class="block mt-2 text-red-600 dark:text-red-400 font-semibold">
                                ⚠️ Esta propuesta fue aceptada — también se borrarán las citas confirmadas del comprador.
                            </span>
                        </p>
                    </div>
                    <div class="flex gap-3">
                        <button @click="cancelarBorrado"
                            class="flex-1 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-xl transition-colors">
                            Cancelar
                        </button>
                        <button @click="ejecutarBorrado" :disabled="borrando"
                            class="flex-1 py-2.5 text-sm font-bold text-white bg-red-600 hover:bg-red-700 rounded-xl transition-colors disabled:opacity-60">
                            {{ borrando ? 'Borrando...' : 'Sí, borrar todo' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AdminLayout>
</template>
