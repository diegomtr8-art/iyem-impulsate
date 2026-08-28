<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({ cita: Object, token: String });
const enviando = ref(false);

// confirmarToken()/rechazarToken() responden con una vista Blade, no con una
// respuesta Inertia: por eso se envia con formularios HTML normales y no con
// router.post (Inertia rechazaria la respuesta).
const csrf = computed(
    () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
);

const fmt = (f) => f
    ? new Date(f).toLocaleString('es-MX', { dateStyle: 'full', timeStyle: 'short' })
    : '—';

const confirmarRechazo = (e) => {
    if (!window.confirm('Se cancelara la cita. Quieres continuar?')) {
        e.preventDefault();
        return;
    }
    enviando.value = true;
};
</script>

<template>
    <Head title="Nueva fecha propuesta" />

    <div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
        <div class="w-full max-w-lg bg-white rounded-2xl shadow-sm border border-gray-200 p-8">
            <h1 class="text-xl font-bold text-gray-900 mb-1">Nueva fecha propuesta</h1>
            <p class="text-sm text-gray-600 mb-6">
                <strong>{{ cita.proveedor }}</strong> propuso mover tu cita.
            </p>

            <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 mb-6 space-y-3">
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold mb-0.5">Fecha original</p>
                    <p class="text-sm text-gray-500 line-through">{{ fmt(cita.inicio_original) }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold mb-0.5">Nueva propuesta</p>
                    <p class="text-base font-bold text-guinda-800">{{ fmt(cita.propuesta_inicio) }}</p>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <form method="POST"
                      :action="route('citas.confirmar-token.post', { cita: cita.id, token })"
                      class="flex-1"
                      @submit="enviando = true">
                    <input type="hidden" name="_token" :value="csrf" />
                    <button type="submit" :disabled="enviando"
                        class="w-full py-3 rounded-xl bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50
                               text-white text-sm font-bold transition-colors">
                        Aceptar la nueva fecha
                    </button>
                </form>

                <form method="POST"
                      :action="route('citas.rechazar-token.post', { cita: cita.id, token })"
                      class="flex-1"
                      @submit="confirmarRechazo">
                    <input type="hidden" name="_token" :value="csrf" />
                    <button type="submit" :disabled="enviando"
                        class="w-full py-3 rounded-xl bg-gray-100 hover:bg-gray-200 disabled:opacity-50
                               text-gray-700 text-sm font-bold transition-colors">
                        No puedo, cancelar la cita
                    </button>
                </form>
            </div>

            <p class="text-xs text-gray-400 mt-5 text-center">
                Este enlace es de un solo uso.
            </p>
        </div>
    </div>
</template>
