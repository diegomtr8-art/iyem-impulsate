<script setup>
import { useForm } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FormSection from '@/Components/FormSection.vue';
import ActionMessage from '@/Components/ActionMessage.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

const form = useForm({
    ine: null,
    csf: null,
    csf_fecha: user.value.csf_fecha ?? '',
});

const formatFecha = (f) => {
    if (!f) return null;
    return new Date(f).toLocaleDateString('es-MX', { year: 'numeric', month: 'long', day: 'numeric' });
};

// Verificar antigüedad de CSF (> 3 meses = vencida)
const csfVencida = computed(() => {
    if (!user.value.csf_fecha) return false;
    const fecha = new Date(user.value.csf_fecha);
    const limite = new Date();
    limite.setMonth(limite.getMonth() - 3);
    return fecha < limite;
});

const onIneChange = (e) => { form.ine = e.target.files[0] ?? null; };
const onCsfChange = (e) => { form.csf = e.target.files[0] ?? null; };

const submit = () => {
    form.post(route('perfil.documentos.subir'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { form.ine = null; form.csf = null; },
    });
};
</script>

<template>
    <FormSection @submitted="submit">
        <template #title>
            Documentos legales
        </template>

        <template #description>
            Sube tu INE y Constancia de Situación Fiscal (CSF). La CSF debe tener una antigüedad
            no mayor a 3 meses para poder registrarte a eventos de tipo Bazar.
        </template>

        <template #form>
            <!-- Estado actual -->
            <div class="col-span-6 flex flex-col sm:flex-row gap-4">
                <!-- INE -->
                <div class="flex-1 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">INE</p>
                    <div v-if="user.ine_path" class="flex items-center gap-3 mb-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold
                                     bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400">
                            ✓ Subido
                        </span>
                        <a :href="'/storage/' + user.ine_path" target="_blank"
                           class="text-xs text-guinda-700 dark:text-guinda-400 hover:underline">
                            Ver documento
                        </a>
                    </div>
                    <div v-else class="mb-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold
                                     bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                            ⚠ Sin subir
                        </span>
                    </div>
                    <label class="block">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ user.ine_path ? 'Reemplazar INE' : 'Subir INE' }} (PDF, JPG, PNG – máx 5 MB)</span>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                               @change="onIneChange"
                               class="mt-1 block w-full text-xs text-gray-500 dark:text-gray-400
                                      file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                                      file:text-xs file:font-semibold
                                      file:bg-guinda-50 dark:file:bg-guinda-900/30
                                      file:text-guinda-700 dark:file:text-guinda-400
                                      hover:file:bg-guinda-100 dark:hover:file:bg-guinda-900/50 cursor-pointer" />
                        <p v-if="form.errors.ine" class="mt-1 text-xs text-red-600">{{ form.errors.ine }}</p>
                    </label>
                </div>

                <!-- CSF -->
                <div class="flex-1 border border-gray-200 dark:border-gray-700 rounded-xl p-4">
                    <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Constancia de Situación Fiscal (CSF)</p>
                    <div v-if="user.csf_path" class="flex items-center gap-3 mb-2">
                        <span :class="csfVencida
                                ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                                : 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400'"
                              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold">
                            {{ csfVencida ? '⚠ CSF vencida' : '✓ Subida' }}
                        </span>
                        <a :href="'/storage/' + user.csf_path" target="_blank"
                           class="text-xs text-guinda-700 dark:text-guinda-400 hover:underline">
                            Ver documento
                        </a>
                    </div>
                    <div v-else class="mb-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold
                                     bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
                            ⚠ Sin subir
                        </span>
                    </div>

                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Fecha de emisión de la CSF *</label>
                        <input type="date" v-model="form.csf_fecha"
                               :max="new Date().toISOString().split('T')[0]"
                               class="block w-full rounded-lg border border-gray-300 dark:border-gray-600
                                      bg-white dark:bg-gray-800 text-gray-900 dark:text-white
                                      px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-guinda-500" />
                        <p v-if="form.errors.csf_fecha" class="mt-1 text-xs text-red-600">{{ form.errors.csf_fecha }}</p>
                    </div>

                    <label class="block">
                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ user.csf_path ? 'Reemplazar CSF' : 'Subir CSF' }} (PDF, JPG, PNG – máx 5 MB)</span>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                               @change="onCsfChange"
                               class="mt-1 block w-full text-xs text-gray-500 dark:text-gray-400
                                      file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0
                                      file:text-xs file:font-semibold
                                      file:bg-guinda-50 dark:file:bg-guinda-900/30
                                      file:text-guinda-700 dark:file:text-guinda-400
                                      hover:file:bg-guinda-100 dark:hover:file:bg-guinda-900/50 cursor-pointer" />
                        <p v-if="form.errors.csf" class="mt-1 text-xs text-red-600">{{ form.errors.csf }}</p>
                    </label>
                </div>
            </div>
        </template>

        <template #actions>
            <ActionMessage :on="form.recentlySuccessful || $page.props.flash?.documentos_success" class="mr-3">
                Guardado.
            </ActionMessage>
            <button type="submit"
                    :disabled="form.processing"
                    class="px-5 py-2 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-50 text-white text-sm font-semibold rounded-xl transition-colors">
                {{ form.processing ? 'Guardando...' : 'Guardar documentos' }}
            </button>
        </template>
    </FormSection>
</template>
