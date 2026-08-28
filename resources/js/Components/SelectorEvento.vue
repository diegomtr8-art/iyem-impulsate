<script setup>
import { computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage();

// Eventos activos disponibles. eventosSidebar ya viene en las props compartidas
// y trae la bandera `activa`, así que no hace falta otra consulta.
const activos = computed(() => (page.props.eventosSidebar ?? []).filter(e => e.activa));
const contexto = computed(() => page.props.eventoContexto ?? null);

// Con un solo evento activo el selector no aporta nada: se oculta.
const visible = computed(() => activos.value.length > 1);

const cambiar = (event) => {
    router.post(
        route('admin.eventos.contexto'),
        { evento_id: event.target.value || null },
        { preserveScroll: true, preserveState: false },
    );
};
</script>

<template>
    <div v-if="visible"
         class="flex items-center gap-3 flex-wrap bg-guinda-50 dark:bg-guinda-950/20 border border-guinda-200 dark:border-guinda-800/40 rounded-xl px-4 py-3 mb-5">
        <div class="flex items-center gap-2 text-guinda-700 dark:text-guinda-400">
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span class="text-xs font-semibold uppercase tracking-wider">Trabajando sobre</span>
        </div>

        <select
            :value="contexto?.id ?? ''"
            @change="cambiar"
            class="flex-1 min-w-[14rem] text-sm bg-white dark:bg-gray-800 border border-guinda-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-lg px-3 py-2 focus:outline-none focus:border-guinda-500 transition-colors"
        >
            <option v-for="ev in activos" :key="ev.id" :value="ev.id">
                {{ ev.nombre }}
            </option>
        </select>

        <p class="text-[11px] text-guinda-700/70 dark:text-guinda-400/70 basis-full sm:basis-auto">
            Hay {{ activos.length }} eventos activos. Esta pantalla opera solo sobre el seleccionado.
        </p>
    </div>
</template>
