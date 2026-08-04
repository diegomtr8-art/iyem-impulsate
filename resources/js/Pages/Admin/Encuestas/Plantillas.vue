<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    plantillas: Array,
});

// ── Estado del formulario de plantilla ──────────────────────────────────────
const mostrarFormulario = ref(false);
const form = reactive({
    id: null,
    nombre: '',
    descripcion: '',
    segmento: 'todos',
    preguntas: [],
});

const tiposDisponibles = [
    { value: 'escala', label: 'Escala numérica' },
    { value: 'nps', label: 'NPS (0-10)' },
    { value: 'binario', label: 'Sí / No' },
    { value: 'texto', label: 'Texto libre' },
    { value: 'opciones', label: 'Opción única' },
    { value: 'multiple', label: 'Selección múltiple' },
];

const segmentosDisponibles = [
    { value: 'todos', label: 'Todos los participantes' },
    { value: 'proveedores', label: 'Solo proveedores (plataforma)' },
    { value: 'compradores', label: 'Solo compradores (plataforma)' },
    { value: 'proveedores_evento', label: 'Proveedores del evento' },
    { value: 'compradores_evento', label: 'Compradores del evento' },
];

const etiquetaTipo = (tipo) => tiposDisponibles.find(t => t.value === tipo)?.label ?? tipo;

const generarId = () => `pregunta_${Date.now()}_${Math.floor(Math.random() * 1000)}`;
const generarSaltoId = () => `salto_${Date.now()}_${Math.floor(Math.random() * 1000)}`;

const numeroDe = (id) => form.preguntas.findIndex(p => p.id === id) + 1;

// ── Preguntas ────────────────────────────────────────────────────────────────
const agregarPregunta = () => {
    form.preguntas.push({
        id: generarId(),
        tipo: 'escala',
        texto: '',
        requerida: true,
        opciones: [],
        escala_min: 1,
        escala_max: 5,
        condicion: null,
        saltos: [],
    });
};

const eliminarPregunta = (idx) => {
    form.preguntas.splice(idx, 1);
};

const moverPregunta = (idx, dir) => {
    const newIdx = idx + dir;
    if (newIdx < 0 || newIdx >= form.preguntas.length) return;
    const tmp = form.preguntas[idx];
    form.preguntas[idx] = form.preguntas[newIdx];
    form.preguntas[newIdx] = tmp;
};

// ── Opciones (tipo opciones/multiple) ──────────────────────────────────────
const agregarOpcion = (pregunta) => {
    if (!pregunta.opciones) pregunta.opciones = [];
    pregunta.opciones.push('');
};
const eliminarOpcion = (pregunta, oi) => {
    pregunta.opciones.splice(oi, 1);
};

// ── Condicionales — "saltos" configurados desde la pregunta de origen ───────
// El admin configura, en la PREGUNTA 4 (por ejemplo), la regla "si la
// respuesta es Comprador, salta a las preguntas 5, 6 y 7". Por debajo esto
// se traduce a la propiedad `condicion` que ya guarda cada pregunta destino
// (mismo formato que ya consume Responder.vue en producción); el admin ya
// no tiene que ir pregunta por pregunta a configurarlo desde el otro lado.

const operadoresLabel = {
    igual: 'es igual a',
    diferente: 'es diferente de',
    mayor: 'es mayor que',
    menor: 'es menor que',
};

// Preguntas que pueden recibirse como destino de un salto: solo las
// posteriores a la de origen (así se lee como "salta hacia adelante").
const preguntasDestinoDisponibles = (idx) =>
    form.preguntas
        .map((p, i) => ({ id: p.id, texto: p.texto || '(sin texto)', numero: i + 1 }))
        .filter((_, i) => i > idx);

// Valores posibles de la pregunta de origen, para ofrecerlos en un select
// en vez de que el admin tenga que escribirlos a mano.
const valoresDisponibles = (pregunta) => {
    if (pregunta.tipo === 'opciones' || pregunta.tipo === 'multiple') return pregunta.opciones ?? [];
    if (pregunta.tipo === 'binario') return ['Sí', 'No'];
    return null; // escala/nps/texto → input libre
};

const agregarSalto = (pregunta) => {
    if (!pregunta.saltos) pregunta.saltos = [];
    const valores = valoresDisponibles(pregunta);
    pregunta.saltos.push({
        id: generarSaltoId(),
        operador: 'igual',
        valor: valores?.[0] ?? '',
        destinos: [],
    });
    sincronizarCondiciones();
};

const eliminarSalto = (pregunta, idx) => {
    pregunta.saltos.splice(idx, 1);
    sincronizarCondiciones();
};

const toggleDestino = (salto, preguntaId) => {
    const i = salto.destinos.indexOf(preguntaId);
    if (i >= 0) salto.destinos.splice(i, 1);
    else salto.destinos.push(preguntaId);
    sincronizarCondiciones();
};

// Frase en lenguaje natural de lo que hará la regla, para verla de un
// vistazo sin tener que interpretar los selects.
const explicacionSalto = (salto) => {
    if (!salto.valor || !salto.destinos.length) {
        return 'Elige un valor y al menos una pregunta destino para ver aquí qué hará el salto.';
    }
    const operador = operadoresLabel[salto.operador] ?? salto.operador;
    const nums = salto.destinos.map(numeroDe).sort((a, b) => a - b);
    const listaNums = nums.length === 1
        ? `la pregunta ${nums[0]}`
        : `las preguntas ${nums.slice(0, -1).join(', ')} y ${nums[nums.length - 1]}`;
    return `Cuando la respuesta ${operador} "${salto.valor}", se mostrará${nums.length > 1 ? 'n' : ''} ${listaNums}. `
         + 'En cualquier otro caso permanecen ocultas y no se guardan.';
};

// Vuelca todas las reglas `saltos` (definidas en las preguntas de origen)
// hacia la propiedad `condicion` de cada pregunta destino — que es lo único
// que el backend valida/guarda y lo único que Responder.vue lee en runtime.
const sincronizarCondiciones = () => {
    form.preguntas.forEach(p => { p.condicion = null; });
    form.preguntas.forEach(origen => {
        (origen.saltos ?? []).forEach(salto => {
            salto.destinos.forEach(destinoId => {
                const destino = form.preguntas.find(p => p.id === destinoId);
                if (destino) {
                    destino.condicion = { pregunta_id: origen.id, operador: salto.operador, valor: salto.valor };
                }
            });
        });
    });
};

// Al abrir una plantilla ya guardada, reconstruye las reglas `saltos` a
// partir de las `condicion` que ya trae cada pregunta destino, agrupando
// por (origen, operador, valor) — así se ve igual de donde se dejó.
const derivarSaltosDesdeCondiciones = () => {
    form.preguntas.forEach(p => { p.saltos = []; });
    form.preguntas.forEach(destino => {
        const c = destino.condicion;
        if (!c?.pregunta_id) return;
        const origen = form.preguntas.find(p => p.id === c.pregunta_id);
        if (!origen) return;
        if (!origen.saltos) origen.saltos = [];
        let salto = origen.saltos.find(s => s.operador === c.operador && s.valor === c.valor);
        if (!salto) {
            salto = { id: generarSaltoId(), operador: c.operador, valor: c.valor, destinos: [] };
            origen.saltos.push(salto);
        }
        salto.destinos.push(destino.id);
    });
};

// ── CRUD de plantilla ────────────────────────────────────────────────────────
const abrirNueva = () => {
    form.id = null;
    form.nombre = '';
    form.descripcion = '';
    form.segmento = 'todos';
    form.preguntas = [];
    mostrarFormulario.value = true;
};

const abrirEditar = (p) => {
    form.id = p.id;
    form.nombre = p.nombre;
    form.descripcion = p.descripcion ?? '';
    form.segmento = p.segmento ?? 'todos';
    form.preguntas = JSON.parse(JSON.stringify(p.preguntas ?? []));
    derivarSaltosDesdeCondiciones();
    mostrarFormulario.value = true;
};

const cerrar = () => { mostrarFormulario.value = false; };

const guardar = () => {
    sincronizarCondiciones();
    const payload = {
        ...form,
        // `saltos` es solo un ayudante de edición en el frontend — el backend
        // guarda `condicion`, que ya quedó sincronizada arriba.
        preguntas: form.preguntas.map(({ saltos, ...resto }) => resto),
    };
    router.post(route('admin.encuestas.plantillas.guardar'), payload, {
        preserveScroll: true,
        onSuccess: () => cerrar(),
    });
};

const activar = (p) => {
    router.post(route('admin.encuestas.plantillas.activar', p.id), {}, { preserveScroll: true });
};

const eliminar = (p) => {
    if (!confirm(`¿Eliminar la plantilla "${p.nombre}"? Esta acción no se puede deshacer.`)) return;
    router.delete(route('admin.encuestas.plantillas.eliminar', p.id), { preserveScroll: true });
};
</script>

<template>
    <AdminLayout title="Plantillas de encuesta">
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-xl font-bold text-gray-900 dark:text-white">Plantillas de encuesta</h1>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-0.5">Crea, edita y activa las plantillas que se enviarán a participantes</p>
                </div>
                <div class="flex items-center gap-2">
                    <Link :href="route('admin.encuestas.index')"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-guinda-700 dark:hover:text-guinda-400 transition-colors">
                        ← Volver
                    </Link>
                    <button v-if="!mostrarFormulario" @click="abrirNueva"
                        class="px-4 py-2 bg-guinda-800 hover:bg-guinda-700 text-white text-sm font-semibold rounded-xl transition-colors">
                        + Nueva plantilla
                    </button>
                </div>
            </div>
        </template>

        <div class="space-y-6 max-w-4xl">

            <!-- ── LISTA DE PLANTILLAS ─────────────────────────────────── -->
            <div v-if="!mostrarFormulario" class="space-y-3">
                <div v-if="!plantillas.length"
                    class="bg-white dark:bg-gray-900 border border-dashed border-gray-200 dark:border-gray-800 rounded-2xl p-10 text-center text-gray-400 dark:text-gray-600">
                    <svg class="w-12 h-12 mx-auto mb-3 opacity-40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p class="text-sm">No hay plantillas todavía. Crea la primera.</p>
                </div>

                <div v-for="p in plantillas" :key="p.id"
                    class="bg-white dark:bg-gray-900 border rounded-2xl p-4 flex items-center justify-between gap-4"
                    :class="p.activa ? 'border-guinda-400 dark:border-guinda-700 ring-1 ring-guinda-300 dark:ring-guinda-800' : 'border-gray-200 dark:border-gray-800'">

                    <div class="flex items-center gap-3 min-w-0">
                        <span v-if="p.activa"
                            class="flex-shrink-0 text-xs font-bold px-2.5 py-1 rounded-full bg-guinda-100 dark:bg-guinda-900/40 text-guinda-700 dark:text-guinda-300">
                            ✓ Activa
                        </span>
                        <span v-else
                            class="flex-shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">
                            Inactiva
                        </span>
                        <div class="min-w-0">
                            <p class="font-bold text-gray-900 dark:text-white truncate">{{ p.nombre }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-600 mt-0.5">
                                {{ p.preguntas?.length ?? 0 }} pregunta{{ (p.preguntas?.length ?? 0) !== 1 ? 's' : '' }}
                                · Segmento: {{ segmentosDisponibles.find(s => s.value === (p.segmento ?? 'todos'))?.label ?? p.segmento }}
                                <span v-if="p.descripcion"> · {{ p.descripcion }}</span>
                            </p>
                            <ol class="mt-2 space-y-0.5 text-xs text-gray-500 dark:text-gray-400 list-decimal list-inside">
                                <li v-for="q in p.preguntas" :key="q.id" class="truncate">
                                    {{ q.texto }} <span class="text-gray-400 dark:text-gray-600">({{ etiquetaTipo(q.tipo) }})</span>
                                    <span v-if="q.condicion?.pregunta_id" class="text-guinda-600 dark:text-guinda-400" title="Tiene condicional">🔀</span>
                                </li>
                            </ol>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button v-if="!p.activa" @click="activar(p)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-guinda-300 dark:border-guinda-700 text-guinda-700 dark:text-guinda-400 hover:bg-guinda-50 dark:hover:bg-guinda-900/20 transition-colors">
                            Activar
                        </button>
                        <button @click="abrirEditar(p)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            Editar
                        </button>
                        <button v-if="!p.activa" @click="eliminar(p)"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-red-200 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>

            <!-- ── CONSTRUCTOR DE PLANTILLA ────────────────────────────── -->
            <div v-if="mostrarFormulario"
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6">

                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-black text-guinda-800 dark:text-white">
                        {{ form.id ? 'Editar plantilla' : 'Nueva plantilla' }}
                    </h2>
                    <button @click="cerrar" class="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 transition-colors">
                        ✕ Cancelar
                    </button>
                </div>

                <!-- Datos generales -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="label-admin">Nombre de la plantilla *</label>
                        <input v-model="form.nombre" type="text" placeholder="Ej: Satisfacción post-evento" class="input-admin" />
                    </div>
                    <div>
                        <label class="label-admin">Segmento de envío *</label>
                        <select v-model="form.segmento" class="input-admin">
                            <option v-for="s in segmentosDisponibles" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label-admin">Descripción (opcional)</label>
                        <input v-model="form.descripcion" type="text" placeholder="Descripción interna de esta plantilla" class="input-admin" />
                    </div>
                </div>

                <!-- Preguntas -->
                <div class="border-t border-gray-100 dark:border-gray-800 pt-5 mb-2">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                            Preguntas ({{ form.preguntas.length }})
                        </h3>
                        <button @click="agregarPregunta" type="button"
                            class="px-3 py-1.5 text-xs font-bold bg-guinda-800 hover:bg-guinda-700 text-white rounded-lg transition-colors">
                            + Agregar pregunta
                        </button>
                    </div>

                    <div v-if="!form.preguntas.length"
                        class="text-center py-8 text-sm text-gray-400 dark:text-gray-600 border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
                        Haz clic en "Agregar pregunta" para comenzar.
                    </div>

                    <div v-for="(pregunta, idx) in form.preguntas" :key="pregunta.id"
                        class="mb-4 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">

                        <!-- Cabecera de la pregunta -->
                        <div class="bg-gray-50 dark:bg-gray-800/60 px-4 py-2.5 flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-black text-guinda-600 dark:text-guinda-400 w-5">{{ idx + 1 }}</span>
                                <select v-model="pregunta.tipo"
                                    class="text-xs border border-gray-200 dark:border-gray-600 rounded-lg px-2 py-1 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 font-semibold">
                                    <option v-for="t in tiposDisponibles" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                                <label class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 cursor-pointer">
                                    <input v-model="pregunta.requerida" type="checkbox" class="w-3.5 h-3.5 accent-guinda-700" />
                                    Requerida
                                </label>
                            </div>
                            <div class="flex items-center gap-1">
                                <button @click="moverPregunta(idx, -1)" type="button" :disabled="idx === 0"
                                    class="px-2 py-1 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-30 transition-colors">▲</button>
                                <button @click="moverPregunta(idx, 1)" type="button" :disabled="idx === form.preguntas.length - 1"
                                    class="px-2 py-1 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 disabled:opacity-30 transition-colors">▼</button>
                                <button @click="eliminarPregunta(idx)" type="button"
                                    class="px-2 py-1 text-xs text-red-400 hover:text-red-600 transition-colors">✕</button>
                            </div>
                        </div>

                        <!-- Cuerpo de la pregunta -->
                        <div class="p-4 space-y-3">
                            <!-- Badge de solo-lectura si ESTA pregunta depende de otra -->
                            <p v-if="pregunta.condicion?.pregunta_id"
                                class="text-xs font-medium text-guinda-600 dark:text-guinda-400 bg-guinda-50 dark:bg-guinda-900/20 border border-guinda-100 dark:border-guinda-800 rounded-lg px-3 py-2">
                                🔀 Esta pregunta se muestra solo si la pregunta {{ numeroDe(pregunta.condicion.pregunta_id) }}
                                {{ operadoresLabel[pregunta.condicion.operador] ?? pregunta.condicion.operador }}
                                "{{ pregunta.condicion.valor }}". Se configura desde esa pregunta, abajo en "Saltos condicionales".
                            </p>

                            <textarea v-model="pregunta.texto" rows="2" placeholder="Escribe la pregunta aquí..."
                                class="input-admin resize-none"></textarea>

                            <!-- Escala: min/max -->
                            <div v-if="pregunta.tipo === 'escala'" class="flex items-center gap-3">
                                <label class="text-xs text-gray-500 dark:text-gray-400">Rango:</label>
                                <input v-model.number="pregunta.escala_min" type="number" min="0" max="9"
                                    class="w-16 input-admin text-center text-xs" />
                                <span class="text-gray-400 text-xs">a</span>
                                <input v-model.number="pregunta.escala_max" type="number" min="2" max="10"
                                    class="w-16 input-admin text-center text-xs" />
                            </div>

                            <!-- Opciones -->
                            <div v-if="pregunta.tipo === 'opciones' || pregunta.tipo === 'multiple'" class="space-y-2">
                                <div v-for="(opcion, oi) in pregunta.opciones" :key="oi" class="flex items-center gap-2">
                                    <input v-model="pregunta.opciones[oi]" type="text" :placeholder="`Opción ${oi + 1}`"
                                        class="input-admin flex-1" />
                                    <button @click="eliminarOpcion(pregunta, oi)" type="button" class="text-red-400 hover:text-red-600 text-xs px-1">✕</button>
                                </div>
                                <button @click="agregarOpcion(pregunta)" type="button"
                                    class="text-xs text-guinda-600 dark:text-guinda-400 hover:underline font-semibold">
                                    + Agregar opción
                                </button>
                            </div>

                            <!-- Saltos condicionales: "si la respuesta es X, salta a las preguntas..." -->
                            <div v-if="idx < form.preguntas.length - 1" class="border-t border-gray-100 dark:border-gray-800 pt-3">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                        🔀 Saltos condicionales de esta pregunta
                                    </span>
                                    <button @click="agregarSalto(pregunta)" type="button"
                                        class="text-xs font-semibold text-guinda-600 dark:text-guinda-400 hover:underline">
                                        + Agregar salto
                                    </button>
                                </div>

                                <p v-if="!pregunta.saltos?.length" class="text-xs text-gray-400 dark:text-gray-600">
                                    Sin saltos. Ej: "si la pregunta {{ idx + 1 }} es igual a Comprador, salta a las preguntas 5, 6 y 7".
                                </p>

                                <div v-for="(salto, si) in pregunta.saltos" :key="salto.id"
                                    class="mt-2 p-3 bg-guinda-50 dark:bg-guinda-900/20 border border-guinda-100 dark:border-guinda-800 rounded-xl space-y-3">

                                    <div class="grid grid-cols-1 sm:grid-cols-[auto_1fr_1fr_auto] gap-2 items-end">
                                        <div>
                                            <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">Si la pregunta {{ idx + 1 }}</label>
                                            <select v-model="salto.operador" @change="sincronizarCondiciones" class="input-admin text-xs">
                                                <option value="igual">es igual a</option>
                                                <option value="diferente">es diferente de</option>
                                                <option value="mayor">es mayor que</option>
                                                <option value="menor">es menor que</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">Valor</label>
                                            <select v-if="valoresDisponibles(pregunta)" v-model="salto.valor"
                                                @change="sincronizarCondiciones" class="input-admin text-xs">
                                                <option value="">— Selecciona —</option>
                                                <option v-for="op in valoresDisponibles(pregunta)" :key="op" :value="op">{{ op }}</option>
                                            </select>
                                            <input v-else v-model="salto.valor" @input="sincronizarCondiciones" type="text"
                                                placeholder="Ej: 4" class="input-admin text-xs" />
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="text-xs text-gray-500 dark:text-gray-400 block mb-1">Salta a las preguntas</label>
                                            <div class="flex flex-wrap gap-1.5">
                                                <button v-for="pd in preguntasDestinoDisponibles(idx)" :key="pd.id"
                                                    @click="toggleDestino(salto, pd.id)" type="button"
                                                    :title="pd.texto"
                                                    class="px-2.5 py-1 text-xs font-bold rounded-lg border transition-colors"
                                                    :class="salto.destinos.includes(pd.id)
                                                        ? 'bg-guinda-800 border-guinda-800 text-white'
                                                        : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-guinda-300'">
                                                    {{ pd.numero }}
                                                </button>
                                            </div>
                                        </div>
                                        <button @click="eliminarSalto(pregunta, si)" type="button"
                                            class="justify-self-end sm:justify-self-auto text-xs text-red-400 hover:text-red-600 transition-colors px-1">
                                            ✕
                                        </button>
                                    </div>

                                    <div class="flex items-start gap-2 pt-2 border-t border-guinda-200/60 dark:border-guinda-800/60">
                                        <span class="text-sm leading-none mt-0.5">💡</span>
                                        <p class="text-xs leading-relaxed">
                                            <span class="font-bold text-guinda-700 dark:text-guinda-400">¿Qué hace este salto? </span>
                                            <span class="text-gray-600 dark:text-gray-300">{{ explicacionSalto(salto) }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Botones guardar -->
                <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <button @click="cerrar" type="button"
                        class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                        Cancelar
                    </button>
                    <button @click="guardar" type="button"
                        :disabled="!form.nombre || !form.preguntas.length"
                        class="px-6 py-2 bg-guinda-800 hover:bg-guinda-700 disabled:opacity-40 text-white font-bold text-sm rounded-xl transition-colors">
                        Guardar plantilla
                    </button>
                </div>
            </div>

        </div>
    </AdminLayout>
</template>

<style scoped>
.label-admin {
    @apply block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1;
}
.input-admin {
    @apply w-full border border-gray-300 dark:border-gray-600 rounded-lg px-3 py-2
           text-sm bg-white dark:bg-gray-800 text-gray-900 dark:text-white
           focus:outline-none focus:ring-2 focus:ring-guinda-500 transition;
}
</style>
