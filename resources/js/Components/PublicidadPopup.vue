<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const pub = page.props.publicidadActiva;

const visible = ref(false);

onMounted(() => {
    if (!pub) return;
    const key = `publicidad_vista_${pub.id}`;
    if (!sessionStorage.getItem(key)) {
        visible.value = true;
    }
});

const cerrar = () => {
    if (pub) sessionStorage.setItem(`publicidad_vista_${pub.id}`, '1');
    visible.value = false;
};

const onEsc = (e) => { if (e.key === 'Escape') cerrar(); };
onMounted(() => document.addEventListener('keydown', onEsc));
onUnmounted(() => document.removeEventListener('keydown', onEsc));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0">
            <div v-if="visible && pub"
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm"
                @click.self="cerrar">

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95">
                    <div v-if="visible"
                        class="relative bg-white dark:bg-gray-900 rounded-2xl shadow-2xl overflow-hidden w-full max-w-md">

                        <!-- Botón cerrar -->
                        <button @click="cerrar"
                            class="absolute top-3 right-3 z-10 w-8 h-8 flex items-center justify-center rounded-full bg-black/40 text-white hover:bg-black/60 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <!-- Imagen (clickeable si tiene enlace) -->
                        <component
                            :is="pub.enlace ? 'a' : 'div'"
                            :href="pub.enlace || undefined"
                            :target="pub.abre_en_nueva_pestana ? '_blank' : undefined"
                            :rel="pub.abre_en_nueva_pestana ? 'noopener noreferrer' : undefined"
                            :class="['block', pub.enlace ? 'cursor-pointer' : 'cursor-default']"
                            @click="pub.enlace && cerrar()">
                            <img v-if="pub.imagen_url" :src="pub.imagen_url" :alt="pub.titulo"
                                class="w-full max-h-[70vh] object-contain bg-gray-50 dark:bg-gray-800" />
                            <div v-else class="p-8 text-center">
                                <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ pub.titulo }}</h2>
                            </div>
                        </component>

                        <!-- Footer del popup -->
                        <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200 flex-1 truncate">
                                {{ pub.titulo }}
                            </span>
                            <button @click="cerrar"
                                class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors whitespace-nowrap">
                                Cerrar
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
