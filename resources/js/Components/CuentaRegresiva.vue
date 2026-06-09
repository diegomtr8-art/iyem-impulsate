<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    segundos: { type: Number, required: true },
    label: { type: String, default: 'Apertura en:' },
    labelCero: { type: String, default: '¡Ya está abierto!' },
});

const emit = defineEmits(['completed']);

const remaining = ref(props.segundos);
let timer = null;

const completado = ref(false);

const dias = computed(() => Math.floor(remaining.value / 86400));
const horas = computed(() => Math.floor((remaining.value % 86400) / 3600));
const minutos = computed(() => Math.floor((remaining.value % 3600) / 60));
const segs = computed(() => remaining.value % 60);

const urgente = computed(() => remaining.value < 3600 && remaining.value > 0);

const pad = (n) => String(n).padStart(2, '0');

onMounted(() => {
    timer = setInterval(() => {
        if (remaining.value <= 0) {
            clearInterval(timer);
            completado.value = true;
            emit('completed');
            setTimeout(() => router.reload(), 3000);
            return;
        }
        remaining.value--;
    }, 1000);
});

onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div class="rounded-xl border p-4"
        :class="completado
            ? 'bg-emerald-50 dark:bg-emerald-950/20 border-emerald-200 dark:border-emerald-800/40'
            : 'bg-guinda-50 dark:bg-guinda-950/20 border-guinda-200 dark:border-guinda-800/40'">

        <p v-if="!completado" class="text-xs font-semibold text-guinda-700 dark:text-guinda-400 mb-3 text-center">
            {{ label }}
        </p>
        <p v-else class="text-sm font-bold text-emerald-700 dark:text-emerald-400 text-center">
            {{ labelCero }}
        </p>

        <div v-if="!completado" class="flex items-center justify-center gap-2 sm:gap-3">
            <div v-for="(val, etiqueta) in { dias, horas, min: minutos, seg: segs }" :key="etiqueta"
                class="flex flex-col items-center">
                <span class="font-bold tabular-nums leading-none"
                    :class="[
                        urgente ? 'text-red-600 dark:text-red-400' : 'text-guinda-800 dark:text-guinda-300',
                        'text-2xl sm:text-3xl'
                    ]">
                    {{ pad(val) }}
                </span>
                <span class="text-[10px] text-gray-500 dark:text-gray-500 mt-0.5">{{ etiqueta }}</span>
            </div>
        </div>
    </div>
</template>
