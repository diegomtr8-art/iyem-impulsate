import { ref, computed, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Logica compartida por Create.vue y Edit.vue: vista previa generada por el
 * servidor, envio de prueba del borrador, insertar variables en el cursor y
 * aviso de variables que no existen en el catalogo.
 *
 * @param form   useForm con asunto y contenido
 * @param tab    ref con la vista activa ('editor' | 'dividido' | 'preview')
 * @param conocidas  () => lista de placeholders validos ('{{x}}')
 */
export function usePlantillaEditor(form, tab, conocidas) {
    // ── Vista previa ─────────────────────────────────────────
    // La arma el servidor: aplica el layout del correo y sustituye las
    // variables con valores de ejemplo. Antes se pintaba el HTML crudo y se
    // veia "{{nombre_usuario}}" en vez de "María Pérez".
    const previewHtml = ref('');
    const previewAsunto = ref('');
    const cargandoPreview = ref(false);
    const errorPreview = ref('');

    const generarPreview = async () => {
        if (!form.asunto || !form.contenido) {
            errorPreview.value = 'Escribe el asunto y el contenido para ver la vista previa.';
            return;
        }
        cargandoPreview.value = true;
        errorPreview.value = '';
        try {
            const res = await fetch(route('admin.plantillas.vista-previa'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ asunto: form.asunto, contenido: form.contenido }),
            });
            if (!res.ok) throw new Error('No se pudo generar la vista previa.');
            const data = await res.json();
            previewHtml.value = data.html;
            previewAsunto.value = data.asunto;
        } catch (e) {
            errorPreview.value = e.message;
        } finally {
            cargandoPreview.value = false;
        }
    };

    // Cualquier vista que no sea solo el editor muestra la vista previa
    watch(tab, (t) => { if (t !== 'editor') generarPreview(); });

    // Con la vista previa visible, se regenera sola al dejar de escribir
    let temporizador = null;
    watch(() => [form.asunto, form.contenido], () => {
        if (tab.value === 'editor' || !form.asunto || !form.contenido) return;
        clearTimeout(temporizador);
        temporizador = setTimeout(generarPreview, 800);
    });

    // ── Envio de prueba del borrador (no guarda) ─────────────
    const emailPrueba = ref('');
    const enviandoPrueba = ref(false);
    const avisoPrueba = ref('');
    const avisoPruebaOk = ref(false);

    const enviarPrueba = () => {
        if (!form.asunto || !form.contenido) {
            avisoPruebaOk.value = false;
            avisoPrueba.value = 'Escribe el asunto y el contenido antes de probar.';
            return;
        }
        enviandoPrueba.value = true;
        avisoPrueba.value = '';
        const email = emailPrueba.value;

        router.post(route('admin.plantillas.enviar-prueba'), {
            asunto: form.asunto,
            contenido: form.contenido,
            email,
        }, {
            // Sin preserveState Inertia rehace la pagina y el borrador se pierde
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                avisoPruebaOk.value = true;
                avisoPrueba.value = `Enviado a ${email}. Revisa la bandeja (y spam).`;
            },
            onError: (e) => {
                avisoPruebaOk.value = false;
                avisoPrueba.value = e.email || e.asunto || e.contenido || 'No se pudo enviar.';
            },
            onFinish: () => { enviandoPrueba.value = false; },
        });
    };

    // ── Insertar variable donde esta el cursor ───────────────
    // Antes hacia `form.contenido += ph`, que la pegaba despues del </div>
    // final, fuera del cuerpo del correo.
    // Las variables van al ultimo campo que tuvo el foco (asunto o contenido);
    // los bloques HTML siempre al contenido.
    const textareaContenido = ref(null);
    const inputAsunto = ref(null);
    const campoActivo = ref('contenido');

    // Hasta que el editor recibe el foco su cursor no significa nada (el
    // navegador lo deja al final del texto, fuera del cuerpo del correo).
    const editorTocado = ref(false);
    const MARCADOR = '<!-- ESCRIBE TU CONTENIDO AQUÍ -->';
    watch(textareaContenido, (el) => {
        el?.addEventListener('focus', () => { editorTocado.value = true; }, { once: true });
    });

    const insertarEn = (campo, texto) => {
        const el = campo === 'asunto' ? inputAsunto.value : textareaContenido.value;
        const actual = form[campo] || '';
        if (!el) { form[campo] = actual + texto; return; }

        let inicio = el.selectionStart ?? actual.length;
        let fin = el.selectionEnd ?? inicio;

        // Si nunca se ha hecho clic en el editor el cursor esta en 0, antes del
        // encabezado: se usa el marcador de la base o, si no esta, el final.
        if (campo === 'contenido' && !editorTocado.value) {
            const i = actual.indexOf(MARCADOR);
            inicio = fin = i >= 0 ? i + MARCADOR.length : actual.length;
        }
        form[campo] = actual.slice(0, inicio) + texto + actual.slice(fin);

        if (campo === 'contenido') editorTocado.value = true;

        nextTick(() => {
            el.focus();
            el.setSelectionRange(inicio + texto.length, inicio + texto.length);
        });
    };

    const insertarPlaceholder = (ph) => insertarEn(inputAsunto.value ? campoActivo.value : 'contenido', ph);
    const insertarBloque = (html) => insertarEn('contenido', `\n${html}\n`);

    // ── Variables mal escritas (aviso, no bloquea) ───────────
    // Algunos flujos inyectan variables propias (lista_citas en la propuesta
    // de agenda), por eso solo se avisa y se deja guardar.
    const variablesDesconocidas = computed(() => {
        const normalizar = (v) => v.replace(/[{}\s]/g, '').toLowerCase();
        const validas = new Set(conocidas().map(normalizar));
        const usadas = [...`${form.asunto || ''} ${form.contenido || ''}`.matchAll(/\{\{\s*([a-z0-9_]+)\s*\}\}/gi)]
            .map((m) => m[1].toLowerCase());

        return [...new Set(usadas.filter((u) => !validas.has(u)))].map((v) => `{{${v}}}`);
    });

    return {
        previewHtml, previewAsunto, cargandoPreview, errorPreview, generarPreview,
        emailPrueba, enviandoPrueba, avisoPrueba, avisoPruebaOk, enviarPrueba,
        textareaContenido, inputAsunto, campoActivo, insertarPlaceholder, insertarBloque,
        variablesDesconocidas,
    };
}
