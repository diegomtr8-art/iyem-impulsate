<?php

namespace Tests\Feature;

use App\Jobs\SendRecordatorioJob;
use App\Mail\EncuestaRecordatorioMail;
use App\Mail\EncuestaSatisfaccionMail;
use App\Mail\PlantillaCorreoMail;
use App\Models\Categoria;
use App\Models\Cita;
use App\Models\EncuestaPlantilla;
use App\Models\EncuestaRespuesta;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\PlantillaCorreo;
use App\Models\Publicidad;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/**
 * Regresion de los controladores web del ADMIN que se mueven a servicios en
 * la Fase 4B de la API: encuestas, plantillas de correo, correo masivo,
 * publicidad, categorias, metricas, exportaciones, alta/baja de proveedor y
 * datos publicos de la Pantalla TV. Se escribio y paso contra el codigo
 * ANTERIOR al refactor.
 */
class WebAdmin4BRegresionTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00'));
        $this->crearEscenario(['destacado' => true]);
        $this->admin = $this->crearAdmin();
    }

    /* ─────────────── helpers ─────────────── */

    private function props($response): array
    {
        return json_decode(json_encode($response->assertOk()->viewData('page')['props']), true);
    }

    private function superAdmin(): User
    {
        $u = $this->crearAdmin();
        $u->assignRole('super-admin');

        return $u;
    }

    private function plantillaEncuesta(array $extra = []): EncuestaPlantilla
    {
        return EncuestaPlantilla::create(array_merge([
            'nombre'    => 'Satisfaccion',
            'segmento'  => 'todos',
            'activa'    => true,
            'preguntas' => [
                ['id' => 'q1', 'tipo' => 'escala', 'texto' => 'Calificacion', 'escala_min' => 1, 'escala_max' => 5],
                ['id' => 'q2', 'tipo' => 'binario', 'texto' => 'Recomienda'],
                ['id' => 'q3', 'tipo' => 'texto', 'texto' => 'Comentarios'],
            ],
        ], $extra));
    }

    private function encuesta(User $u, string $tipo, ?array $respuestas = null, ?EncuestaPlantilla $p = null): EncuestaSatisfaccion
    {
        $e = EncuestaSatisfaccion::create([
            'evento_id'             => $this->evento->id,
            'user_id'               => $u->id,
            'tipo'                  => $tipo,
            'segmento'              => 'todos',
            'token'                 => Str::random(40),
            'encuesta_plantilla_id' => $p?->id,
            'completada_at'         => $respuestas ? now()->subHour() : null,
        ]);
        foreach ($respuestas ?? [] as [$pregunta, $tipoResp, $valor]) {
            EncuestaRespuesta::create(['encuesta_satisfaccion_id' => $e->id, 'pregunta' => $pregunta, 'tipo' => $tipoResp, 'respuesta' => $valor]);
        }

        return $e;
    }

    /** 2 respondidas (comprador CANIRAC y proveedor) + 1 pendiente. */
    private function escenarioEncuestas(): array
    {
        $p = $this->plantillaEncuesta();
        $c1 = $this->crearComprador('aprobado', ['camara_asociacion' => 'CANIRAC']);
        $c2 = $this->crearComprador('aprobado');
        $prov = $this->restaurantero->user;
        DB::table('evento_usuario')->insert(['evento_id' => $this->evento->id, 'user_id' => $prov->id, 'tipo' => 'proveedor', 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);

        $e1 = $this->encuesta($c1, 'comprador', [['Calificacion', 'escala', '4'], ['Recomienda', 'binario', 'Sí'], ['Comentarios', 'texto', 'Muy bien']], $p);
        $e2 = $this->encuesta($prov, 'proveedor', [['Calificacion', 'escala', '2'], ['Recomienda', 'binario', 'No']], $p);
        $e3 = $this->encuesta($c2, 'comprador', null, $p);

        return compact('p', 'c1', 'c2', 'prov', 'e1', 'e2', 'e3');
    }

    /* ─────────────── acceso ─────────────── */

    public function test_admin_normal_no_entra_a_modulos_super_admin(): void
    {
        foreach (['/admin/plantillas-correo', '/admin/correo-masivo', '/admin/categorias', '/admin/publicidad'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertForbidden();
        }
        $this->actingAs($this->crearComprador())->get('/admin/encuestas')->assertForbidden();
        $this->actingAs($this->crearComprador())->get('/admin/metricas')->assertForbidden();
    }

    /* ─────────────── encuestas ─────────────── */

    public function test_encuestas_index_metricas_y_lista(): void
    {
        $x = $this->escenarioEncuestas();
        // Las de prueba no cuentan.
        EncuestaSatisfaccion::create(['tipo' => 'comprador', 'token' => Str::random(40), 'es_prueba' => true, 'email_prueba' => 'x@prueba.impulsate.mx']);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/encuestas'));

        $this->assertSame(3, $p['metricas']['total_enviadas']);
        $this->assertSame(2, $p['metricas']['total_respondidas']);
        $this->assertSame(1, $p['metricas']['total_pendientes']);
        $this->assertEquals(66.7, $p['metricas']['tasa_respuesta']);
        $this->assertEquals(3.0, (float) collect($p['metricas']['escala'])->firstWhere('pregunta', 'Calificacion')['promedio']);
        $this->assertEquals(50, collect($p['metricas']['binario'])->firstWhere('pregunta', 'Recomienda')['porcentaje_si']);
        $this->assertCount(3, $p['encuestas']['data']);
        $this->assertCount(2, $p['ultimasRespondidas']);
        $this->assertSame($x['p']->id, $p['plantillaActiva']['id']);
        $this->assertSame((string) $this->evento->id, (string) $this->props($this->actingAs($this->admin)->get('/admin/encuestas?evento_id=' . $this->evento->id))['filtroEvento']);
        $this->assertSame(0, $this->props($this->actingAs($this->admin)->get('/admin/encuestas?evento_id=999999'))['metricas']['total_enviadas']);
    }

    public function test_ver_respuesta(): void
    {
        $x = $this->escenarioEncuestas();

        $p = $this->props($this->actingAs($this->admin)->get("/admin/encuestas/{$x['e1']->id}/ver"));

        $this->assertSame($x['c1']->email, $p['encuesta']['usuario']['email']);
        $this->assertSame(['Calificacion', 'Recomienda', 'Comentarios'], collect($p['encuesta']['respuestas'])->pluck('pregunta')->all());
        $this->assertSame($x['p']->id, $p['encuesta']['plantilla']['id']);
    }

    public function test_graficas_kpis_datos_y_filtros(): void
    {
        $x = $this->escenarioEncuestas();

        $p = $this->props($this->actingAs($this->admin)->get('/admin/encuestas/graficas'));

        $this->assertSame(['total_enviadas' => 3, 'total_respondidas' => 2, 'tasa' => 66.7, 'compradores' => 1, 'proveedores' => 1, 'canirac' => 1], $p['kpis']);
        $this->assertSame(3.0, (float) $p['datos']['Calificacion']['promedio']);
        $this->assertSame(1, $p['datos']['Recomienda']['opciones']['Sí']['count']);
        $this->assertEquals(50, $p['datos']['Recomienda']['opciones']['No']['porcentaje']);
        $this->assertSame(['Muy bien'], $p['datos']['Comentarios']['textos']);
        $this->assertCount(2, $p['respondientes']);
        $this->assertSame($x['p']->id, $p['plantilla']['id']);

        $s = $this->props($this->actingAs($this->admin)->get('/admin/encuestas/graficas?segmento=proveedor'));
        $this->assertSame(1, $s['kpis']['total_respondidas']);
        $this->assertSame('proveedor', $s['respondientes'][0]['tipo']);

        $c = $this->props($this->actingAs($this->admin)->get('/admin/encuestas/graficas?canirac=si'));
        $this->assertSame(1, $c['kpis']['total_respondidas']);
        $this->assertSame($x['c1']->email, $c['respondientes'][0]['email']);

        $d = $this->props($this->actingAs($this->admin)->get('/admin/encuestas/graficas?desde=2026-10-08'));
        $this->assertSame(0, $d['kpis']['total_respondidas']);
    }

    public function test_exportar_y_reportes(): void
    {
        $this->escenarioEncuestas();

        $this->actingAs($this->admin)->get('/admin/encuestas/exportar')->assertOk()->assertDownload('encuestas_todos_2026-10-07.xlsx');
        $this->actingAs($this->admin)->get('/admin/encuestas/exportar?evento_id=' . $this->evento->id)
            ->assertOk()->assertDownload("encuestas_evento_{$this->evento->id}_2026-10-07.xlsx");
        $this->actingAs($this->admin)->get('/admin/encuestas/reporte-excel')->assertOk()->assertDownload('encuestas-2026-10-07.xlsx');

        $pdf = $this->actingAs($this->admin)->get('/admin/encuestas/reporte-pdf')->assertOk();
        $this->assertStringContainsString('reporte-encuestas-2026-10-07.pdf', $pdf->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_reportes_sin_plantilla_activa(): void
    {
        $this->actingAs($this->admin)->from('/admin/encuestas')->get('/admin/encuestas/reporte-pdf')
            ->assertRedirect('/admin/encuestas')->assertSessionHasErrors(['error' => 'No hay plantilla activa. Activa una primero.']);
        $this->actingAs($this->admin)->from('/admin/encuestas')->get('/admin/encuestas/reporte-excel')
            ->assertSessionHasErrors(['error' => 'No hay plantilla activa. Activa una primero.']);
    }

    public function test_plantillas_de_encuesta_crud(): void
    {
        $activa = $this->plantillaEncuesta(['nombre' => 'Activa']);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/encuestas/plantillas'));
        $this->assertSame('Activa', $p['plantillas'][0]['nombre']);

        $datos = [
            'nombre'    => 'Nueva',
            'segmento'  => 'compradores',
            'preguntas' => [
                ['id' => 'a', 'tipo' => 'opciones', 'texto' => 'Elige', 'opciones' => ['X', 'Y']],
                ['id' => 'b', 'tipo' => 'texto', 'texto' => 'Por que', 'condicion' => ['pregunta_id' => 'a', 'operador' => 'igual', 'valor' => 'X']],
            ],
        ];
        $this->actingAs($this->admin)->post('/admin/encuestas/plantillas', $datos)
            ->assertRedirect()->assertSessionHas('success', 'Plantilla guardada correctamente.');
        $nueva = EncuestaPlantilla::where('nombre', 'Nueva')->firstOrFail();
        $this->assertFalse($nueva->activa);
        $this->assertSame('a', $nueva->preguntas[1]['condicion']['pregunta_id']);

        $this->actingAs($this->admin)->post('/admin/encuestas/plantillas', array_merge($datos, ['id' => $nueva->id, 'nombre' => 'Nueva editada']))->assertRedirect();
        $this->assertSame('Nueva editada', $nueva->fresh()->nombre);
        $this->assertSame(2, EncuestaPlantilla::count());

        $this->actingAs($this->admin)->post('/admin/encuestas/plantillas', ['nombre' => '', 'segmento' => 'x', 'preguntas' => []])
            ->assertSessionHasErrors(['nombre', 'segmento', 'preguntas']);
        $this->actingAs($this->admin)->post('/admin/encuestas/plantillas', array_merge($datos, ['preguntas' => [['id' => 'a', 'tipo' => 'raro', 'texto' => 'x']]]))
            ->assertSessionHasErrors(['preguntas.0.tipo']);

        $this->actingAs($this->admin)->post("/admin/encuestas/plantillas/{$nueva->id}/activar")
            ->assertSessionHas('success', 'Plantilla "Nueva editada" activada. Se usará en los próximos envíos.');
        $this->assertTrue($nueva->fresh()->activa);
        $this->assertFalse($activa->fresh()->activa);

        $this->actingAs($this->admin)->delete("/admin/encuestas/plantillas/{$nueva->id}")
            ->assertSessionHasErrors(['error' => 'No puedes eliminar la plantilla activa. Activa otra primero.']);
        $this->actingAs($this->admin)->delete("/admin/encuestas/plantillas/{$activa->id}")
            ->assertSessionHas('success', 'Plantilla eliminada.');
        $this->assertNull($activa->fresh());
    }

    public function test_enviar_para_evento_por_segmento(): void
    {
        $p = $this->plantillaEncuesta();
        $c = $this->crearComprador('aprobado');
        $this->crearComprador('pendiente');
        DB::table('evento_usuario')->insert(['evento_id' => $this->evento->id, 'user_id' => $this->restaurantero->user_id, 'tipo' => 'proveedor', 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-evento', ['evento_id' => $this->evento->id, 'segmento' => 'compradores'])
            ->assertSessionHas('success', 'Encuestas enviadas a 1 participante(s).');
        Mail::assertQueued(EncuestaSatisfaccionMail::class, 1);
        $this->assertSame($p->id, EncuestaSatisfaccion::where('user_id', $c->id)->value('encuesta_plantilla_id'));

        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-evento', ['evento_id' => $this->evento->id, 'segmento' => 'todos'])
            ->assertSessionHas('success', 'Encuestas enviadas a 1 participante(s).');
        $this->assertSame('proveedor', EncuestaSatisfaccion::where('user_id', $this->restaurantero->user_id)->value('tipo'));

        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-evento', ['segmento' => 'otro'])->assertSessionHasErrors(['evento_id', 'segmento']);

        $p->update(['activa' => false]);
        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-evento', ['evento_id' => $this->evento->id, 'segmento' => 'todos'])
            ->assertSessionHasErrors(['error' => 'No hay plantilla activa. Activa una plantilla primero.']);
    }

    public function test_enviar_prueba_y_recordatorio_prueba(): void
    {
        $p = $this->plantillaEncuesta();

        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-prueba', ['email' => 'qa@prueba.impulsate.mx'])
            ->assertSessionHas('success', 'Encuesta de prueba enviada a qa@prueba.impulsate.mx.');
        $e = EncuestaSatisfaccion::where('email_prueba', 'qa@prueba.impulsate.mx')->firstOrFail();
        $this->assertTrue($e->es_prueba);
        $this->assertSame('comprador', $e->tipo);
        $this->assertSame($p->id, $e->encuesta_plantilla_id);
        Mail::assertQueued(EncuestaSatisfaccionMail::class, fn ($m) => $m->hasTo('qa@prueba.impulsate.mx'));

        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-recordatorio-prueba', ['email' => 'qa2@prueba.impulsate.mx'])
            ->assertSessionHas('success', 'Correo de recordatorio de prueba enviado a qa2@prueba.impulsate.mx.');
        $this->assertSame('proveedor', EncuestaSatisfaccion::where('email_prueba', 'qa2@prueba.impulsate.mx')->value('tipo'));
        Mail::assertQueued(EncuestaRecordatorioMail::class, 1);

        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-prueba', ['email' => 'no-es-correo'])->assertSessionHasErrors('email');
    }

    public function test_enviar_recordatorio_a_pendientes(): void
    {
        Bus::fake([SendRecordatorioJob::class]);
        $x = $this->escenarioEncuestas();

        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-recordatorio', ['evento_id' => $this->evento->id])
            ->assertRedirect()
            ->assertSessionHas('recordatorio_resultado', [
                'enviados'       => 1,
                'ya_contestaron' => 2,
                'personas'       => [['nombre' => $x['c2']->name, 'email' => $x['c2']->email, 'tipo' => 'comprador']],
                'mensaje'        => 'Recordatorio enviado a 1 persona(s) pendiente(s).',
            ]);
        Bus::assertDispatched(SendRecordatorioJob::class, fn ($j) => $j->encuestaId === $x['e3']->id && $j->queue === 'encuestas');

        $x['e3']->update(['completada_at' => now()]);
        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-recordatorio', ['evento_id' => $this->evento->id])
            ->assertSessionHas('recordatorio_resultado', ['enviados' => 0, 'ya_contestaron' => 3, 'personas' => [], 'mensaje' => '¡Todos han contestado!']);
    }

    public function test_eliminar_respuestas(): void
    {
        $x = $this->escenarioEncuestas();

        $this->actingAs($this->admin)->delete("/admin/encuestas/{$x['e1']->id}/eliminar-respuestas")
            ->assertSessionHas('success', "Respuestas de {$x['c1']->name} eliminadas. Puede volver a recibir la encuesta.");
        $this->assertNull($x['e1']->fresh());
        $this->assertSame(0, EncuestaRespuesta::where('encuesta_satisfaccion_id', $x['e1']->id)->count());
    }

    public function test_participantes_y_enviar_seleccion(): void
    {
        $x = $this->escenarioEncuestas();
        $nuevo = $this->crearComprador('aprobado', ['name' => 'Zeta Nuevo']);
        $pendiente = $this->crearComprador('pendiente');

        $r = $this->actingAs($this->admin)->getJson('/admin/encuestas/participantes?evento_id=' . $this->evento->id)->assertOk()->json();
        $this->assertCount(4, $r);
        $this->assertSame(['comprador', 'comprador', 'comprador', 'proveedor'], collect($r)->pluck('tipo')->all());
        $fila = collect($r)->firstWhere('user_id', $nuevo->id);
        $this->assertFalse($fila['ya_enviada']);
        $this->assertTrue(collect($r)->firstWhere('user_id', $x['c1']->id)['ya_enviada']);
        $this->assertSame('Negocio Test', collect($r)->firstWhere('tipo', 'proveedor')['nombre_negocio']);
        $this->assertSame(['user_id', 'name', 'email', 'tipo', 'nombre_negocio', 'ya_enviada'], array_keys($fila));

        $this->actingAs($this->admin)->get('/admin/encuestas/participantes')->assertRedirect();

        $this->actingAs($this->admin)->post('/admin/encuestas/enviar-seleccion', [
            'evento_id' => $this->evento->id, 'user_ids' => [$nuevo->id, $x['c1']->id, $pendiente->id],
        ])->assertSessionHas('success', 'Encuesta enviada a 1 participante(s) seleccionado(s).');
        $this->assertSame('compradores', EncuestaSatisfaccion::where('user_id', $nuevo->id)->value('segmento'));
        $this->assertFalse(EncuestaSatisfaccion::where('user_id', $pendiente->id)->exists());
        Mail::assertQueued(EncuestaSatisfaccionMail::class, 1);
    }

    /* ─────────────── plantillas de correo (super-admin) ─────────────── */

    public function test_plantillas_correo_crud(): void
    {
        $sa = $this->superAdmin();
        $sis = PlantillaCorreo::create(['clave' => 'cita_agendada', 'nombre' => 'Cita agendada', 'asunto' => 'A', 'contenido' => 'C', 'tipo_destinatario' => 'ambos', 'es_sistema' => true, 'activo' => true]);

        $p = $this->props($this->actingAs($sa)->get('/admin/plantillas-correo'));
        $this->assertSame(['id', 'clave', 'nombre', 'asunto', 'tipo_destinatario', 'es_sistema', 'activo'], array_keys($p['plantillas'][0]));

        $c = $this->props($this->actingAs($sa)->get('/admin/plantillas-correo/crear'));
        $this->assertStringContainsString('{{nombre_usuario}}', $c['htmlBase']);
        $this->assertArrayHasKey('{{nombre_evento}}', $c['placeholders']);

        $this->actingAs($sa)->post('/admin/plantillas-correo', ['nombre' => 'Aviso', 'clave' => 'Mal Clave', 'asunto' => 'x', 'contenido' => 'y', 'tipo_destinatario' => 'nadie'])
            ->assertSessionHasErrors(['clave', 'tipo_destinatario']);
        $this->actingAs($sa)->post('/admin/plantillas-correo', ['nombre' => 'Aviso', 'clave' => 'aviso_general', 'asunto' => 'Hola', 'contenido' => '<p>Hola {{nombre_usuario}}</p>', 'tipo_destinatario' => 'ambos'])
            ->assertRedirect('/admin/plantillas-correo')->assertSessionHas('success', 'Plantilla creada correctamente.');
        $nueva = PlantillaCorreo::where('clave', 'aviso_general')->firstOrFail();
        $this->assertFalse($nueva->es_sistema);
        $this->assertTrue($nueva->activo);

        $e = $this->props($this->actingAs($sa)->get("/admin/plantillas-correo/{$sis->id}/edit"));
        $this->assertSame('cita_agendada', $e['plantilla']['clave']);
        $this->assertArrayHasKey('{{fecha_cita}}', $e['placeholders']);

        $this->actingAs($sa)->put("/admin/plantillas-correo/{$nueva->id}", ['asunto' => 'Nuevo asunto', 'contenido' => 'Nuevo'])
            ->assertSessionHas('success', 'Plantilla actualizada correctamente.');
        $this->assertSame('Nuevo asunto', $nueva->fresh()->asunto);

        $this->actingAs($sa)->patch("/admin/plantillas-correo/{$nueva->id}/toggle")->assertSessionHas('success', 'Plantilla desactivada.');
        $this->assertFalse($nueva->fresh()->activo);

        $this->actingAs($sa)->post("/admin/plantillas-correo/{$nueva->id}/enviar", ['destinatarios' => ['a@prueba.impulsate.mx', 'b@prueba.impulsate.mx']])
            ->assertSessionHas('success', 'Correo enviado a 2 destinatario(s). (Procesando en cola)');
        Mail::assertQueued(PlantillaCorreoMail::class, 2);
        $this->actingAs($sa)->post("/admin/plantillas-correo/{$nueva->id}/enviar", ['destinatarios' => ['malo']])->assertSessionHasErrors('destinatarios.0');

        $this->actingAs($sa)->delete("/admin/plantillas-correo/{$sis->id}")->assertSessionHasErrors(['error' => 'No se pueden eliminar plantillas del sistema.']);
        $this->actingAs($sa)->delete("/admin/plantillas-correo/{$nueva->id}")->assertRedirect('/admin/plantillas-correo')->assertSessionHas('success', 'Plantilla eliminada.');
        $this->assertNull($nueva->fresh());
    }

    public function test_plantillas_correo_restablecer_solo_crea_las_faltantes(): void
    {
        $sa = $this->superAdmin();
        $editada = PlantillaCorreo::create(['clave' => 'cita_agendada', 'nombre' => 'Cita agendada', 'asunto' => 'EDITADO', 'contenido' => 'C', 'tipo_destinatario' => 'ambos', 'es_sistema' => true, 'activo' => true]);

        $this->actingAs($sa)->post("/admin/plantillas-correo/{$editada->id}/restablecer")
            ->assertSessionHas('success', 'Todas las plantillas restablecidas a sus valores por defecto.');

        // Comportamiento actual (ver reporte): firstOrCreate no restablece las existentes.
        $this->assertSame('EDITADO', $editada->fresh()->asunto);
        $this->assertTrue(PlantillaCorreo::where('clave', 'cita_aceptada')->exists());
    }

    /* ─────────────── correo masivo (super-admin) ─────────────── */

    public function test_correo_masivo_index_y_preview(): void
    {
        $sa = $this->superAdmin();
        Categoria::create(['nombre' => 'Alimentos', 'orden' => 1, 'activo' => true]);
        $this->restaurantero->update(['categoria' => 'Alimentos', 'aprobado' => true]);
        $c1 = $this->crearComprador('aprobado', ['camara_asociacion' => 'CANIRAC']);
        $c2 = $this->crearComprador(null);
        $sinVerificar = $this->crearComprador(null, ['email_verified_at' => null]);

        $p = $this->props($this->actingAs($sa)->get('/admin/correo-masivo'));
        $this->assertSame(['Alimentos'], $p['categorias']);
        $this->assertArrayHasKey('plantillas', $p);
        $this->assertArrayHasKey('eventos', $p);

        $todos = $this->actingAs($sa)->postJson('/admin/correo-masivo/preview', ['tipo' => 'todos'])->assertOk()->json();
        $this->assertSame(3, $todos['total']);
        $this->assertSame(['id', 'name', 'email', 'rol', 'canirac'], array_keys($todos['destinatarios'][0]));

        $this->assertSame(2, $this->actingAs($sa)->postJson('/admin/correo-masivo/preview', ['tipo' => 'compradores'])->json('total'));
        $this->assertSame(1, $this->actingAs($sa)->postJson('/admin/correo-masivo/preview', ['tipo' => 'compradores', 'canirac' => 'si'])->json('total'));
        $this->assertSame(1, $this->actingAs($sa)->postJson('/admin/correo-masivo/preview', ['tipo' => 'compradores', 'evento_id' => $this->evento->id, 'estado_evento' => 'aprobado'])->json('total'));
        $this->assertSame(1, $this->actingAs($sa)->postJson('/admin/correo-masivo/preview', ['tipo' => 'proveedores', 'categoria' => 'Alimentos', 'estado_proveedor' => 'aprobado'])->json('total'));
        $this->assertSame(0, $this->actingAs($sa)->postJson('/admin/correo-masivo/preview', ['tipo' => 'proveedores', 'estado_proveedor' => 'rechazado'])->json('total'));
        $this->assertNotContains($sinVerificar->email, collect($todos['destinatarios'])->pluck('email'));
    }

    public function test_correo_masivo_prueba_y_envio(): void
    {
        $sa = $this->superAdmin();
        $c = $this->crearComprador('aprobado');

        $this->actingAs($sa)->post('/admin/correo-masivo/prueba', ['email_prueba' => 'qa@prueba.impulsate.mx', 'asunto' => 'Hola', 'contenido' => '<p>Hola {{nombre_usuario}}</p>'])
            ->assertSessionHas('success', 'Correo de prueba enviado a qa@prueba.impulsate.mx.');
        $tmp = PlantillaCorreo::where('clave', 'prueba_correo_masivo')->firstOrFail();
        $this->assertSame('[PRUEBA] Hola', $tmp->asunto);
        $this->assertFalse($tmp->activo);
        Mail::assertQueued(PlantillaCorreoMail::class, fn ($m) => $m->hasTo('qa@prueba.impulsate.mx') && $m->vars === ['nombre_usuario' => 'Usuario de Prueba']);

        $this->actingAs($sa)->post('/admin/correo-masivo/prueba', [])->assertSessionHasErrors(['email_prueba', 'asunto', 'contenido']);

        $this->actingAs($sa)->post('/admin/correo-masivo/enviar', ['tipo' => 'compradores', 'asunto' => 'Masivo', 'contenido' => 'Hola {{nombre_usuario}}'])
            ->assertSessionHas('success', 'Correos en cola para 1 destinatarios.');
        Mail::assertQueued(PlantillaCorreoMail::class, fn ($m) => $m->hasTo($c->email) && $m->vars === ['nombre_usuario' => $c->name]);
        $this->assertSame(1, PlantillaCorreo::where('clave', 'like', 'correo_masivo_%')->count());

        $this->actingAs($sa)->post('/admin/correo-masivo/enviar', ['tipo' => 'compradores', 'canirac' => 'si', 'asunto' => 'X', 'contenido' => 'Y'])
            ->assertSessionHasErrors(['error' => 'No hay destinatarios con los filtros seleccionados.']);
    }

    /* ─────────────── publicidad (super-admin) ─────────────── */

    public function test_publicidad_crud(): void
    {
        Storage::fake('public');
        $sa = $this->superAdmin();

        $this->actingAs($sa)->post('/admin/publicidad', [
            'titulo' => 'Promo', 'imagen' => UploadedFile::fake()->image('p.jpg'), 'enlace' => 'https://impulsate.mx',
            'abre_en_nueva_pestana' => true, 'fecha_inicio' => '2026-10-01 00:00', 'fecha_fin' => '2026-10-31 23:59', 'activa' => true,
        ])->assertRedirect('/admin/publicidad')->assertSessionHas('success', 'Anuncio creado correctamente.');
        $pub = Publicidad::firstOrFail();
        Storage::disk('public')->assertExists($pub->imagen);
        $this->assertSame('Vigente', $pub->estado_label);

        $p = $this->props($this->actingAs($sa)->get('/admin/publicidad'));
        $this->assertSame('Vigente', $p['publicidades'][0]['estado_label']);
        $this->assertStringContainsString('publicidad/', $p['publicidades'][0]['imagen_url']);

        $f = $this->props($this->actingAs($sa)->get("/admin/publicidad/{$pub->id}/edit"));
        $this->assertSame('2026-10-01T00:00', $f['publicidad']['fecha_inicio']);

        $this->actingAs($sa)->post('/admin/publicidad', ['titulo' => 'X', 'imagen' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'), 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-09-01'])
            ->assertSessionHasErrors(['imagen', 'fecha_fin']);

        $vieja = $pub->imagen;
        $this->actingAs($sa)->post("/admin/publicidad/{$pub->id}", [
            'titulo' => 'Promo 2', 'imagen' => UploadedFile::fake()->image('q.png'), 'fecha_inicio' => '2026-11-01 00:00', 'fecha_fin' => '2026-11-30 00:00',
        ])->assertRedirect('/admin/publicidad')->assertSessionHas('success', 'Anuncio actualizado correctamente.');
        $pub->refresh();
        $this->assertSame('Promo 2', $pub->titulo);
        $this->assertSame('Programada', $pub->estado_label);
        Storage::disk('public')->assertMissing($vieja);

        $this->actingAs($sa)->patch("/admin/publicidad/{$pub->id}/toggle")->assertSessionHas('success', 'Anuncio desactivado.');
        $this->assertFalse($pub->fresh()->activa);

        $img = $pub->imagen;
        $this->actingAs($sa)->delete("/admin/publicidad/{$pub->id}")->assertRedirect('/admin/publicidad')->assertSessionHas('success', 'Anuncio eliminado.');
        $this->assertSame(0, Publicidad::count());
        Storage::disk('public')->assertMissing($img);
    }

    /* ─────────────── categorias (super-admin) ─────────────── */

    public function test_categorias_crud_y_reordenar(): void
    {
        $sa = $this->superAdmin();
        $a = Categoria::create(['nombre' => 'Alimentos', 'orden' => 1, 'activo' => true]);
        $b = Categoria::create(['nombre' => 'Bebidas', 'orden' => 2, 'activo' => true]);
        $this->restaurantero->update(['categoria' => 'Alimentos']);

        $p = $this->props($this->actingAs($sa)->get('/admin/categorias'));
        $this->assertSame(['Alimentos', 'Bebidas'], collect($p['categorias'])->pluck('nombre')->all());
        $this->assertSame(1, $p['categorias'][0]['restauranteros_count']);

        $this->actingAs($sa)->post('/admin/categorias', ['nombre' => 'Bebidas'])->assertSessionHasErrors('nombre');
        $this->actingAs($sa)->post('/admin/categorias', ['nombre' => 'Servicios'])->assertSessionHas('success', 'Categoría agregada.');
        $this->assertSame(3, Categoria::where('nombre', 'Servicios')->value('orden'));

        $this->actingAs($sa)->put("/admin/categorias/{$b->id}", ['nombre' => 'Bebidas y licores'])->assertSessionHas('success', 'Categoría actualizada.');
        $this->assertSame('Bebidas y licores', $b->fresh()->nombre);
        $this->actingAs($sa)->put("/admin/categorias/{$b->id}", ['nombre' => 'Alimentos'])->assertSessionHasErrors('nombre');

        $this->actingAs($sa)->patch("/admin/categorias/{$b->id}/toggle")->assertSessionHas('success', 'Categoría desactivada.');
        $this->actingAs($sa)->patch("/admin/categorias/{$b->id}/toggle")->assertSessionHas('success', 'Categoría activada.');

        $this->actingAs($sa)->delete("/admin/categorias/{$a->id}")->assertSessionHasErrors(['error' => 'No se puede eliminar: hay proveedores con esta categoría.']);
        $this->actingAs($sa)->delete("/admin/categorias/{$b->id}")->assertSessionHas('success', 'Categoría eliminada.');
        $this->assertNull($b->fresh());

        $s = Categoria::where('nombre', 'Servicios')->first();
        $this->actingAs($sa)->post('/admin/categorias/reordenar', ['ids' => [$s->id, $a->id]])->assertSessionHas('success', 'Orden actualizado.');
        $this->assertSame(0, (int) $s->fresh()->orden);
        $this->assertSame(1, (int) $a->fresh()->orden);
        $this->actingAs($sa)->post('/admin/categorias/reordenar', [])->assertSessionHasErrors('ids');
    }

    /* ─────────────── metricas ─────────────── */

    public function test_metricas_index_exportar_y_genero(): void
    {
        $this->restaurantero->update(['municipio' => 'Mérida', 'categoria' => 'Alimentos', 'rfc' => 'XAXX010101000']);
        $c = $this->crearComprador('aprobado', ['municipio' => 'Progreso', 'genero' => 'mujer', 'necesidades' => 'Busco tortillas y tortillas de maíz']);
        $sinGenero = $this->crearComprador('aprobado');
        DB::table('page_visits')->insert([
            ['restaurantero_id' => $this->restaurantero->id, 'url' => '/proveedores/1', 'device_type' => 'mobile', 'created_at' => now(), 'updated_at' => now()],
            ['restaurantero_id' => null, 'url' => '/', 'device_type' => 'desktop', 'created_at' => now()->subDays(3), 'updated_at' => now()],
        ]);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/metricas'));

        $this->assertFalse($p['sinDatos']);
        $this->assertSame(2, $p['totalVisitas']);
        $this->assertSame(1, $p['visitasHoy']);
        $this->assertSame(1, $p['visitasSemana']);
        $this->assertSame('Negocio Test', $p['proveedoresTop'][0]['nombre']);
        $this->assertSame('Mérida', $p['topMunicipiosProveedores'][0]['municipio']);
        $this->assertSame('Progreso', $p['topMunicipiosCompradores'][0]['municipio']);
        $this->assertSame('Alimentos', $p['topCategoriasProveedores'][0]['categoria']);
        $this->assertSame(['necesidad' => 'tortillas', 'total' => 2], $p['topNecesidadesCompradores'][0]);
        $this->assertSame(1, $p['generoMujer']);
        $this->assertSame(3, $p['generoTotal']);
        $this->assertSame(1, $p['proveedorConRFC']);
        $this->assertSame(0, $p['compradorConRFC']);
        $this->assertSame(3, $p['rfcTotal']);
        $this->assertContains($sinGenero->id, collect($p['noIdentificados'])->pluck('id'));

        $this->actingAs($this->admin)->get('/admin/metricas/exportar')->assertOk()->assertDownload('metricas_impulsate_2026-10-07.xlsx');

        $this->actingAs($this->admin)->patch("/admin/usuarios/{$sinGenero->id}/genero", ['genero' => 'hombre'])->assertSessionHas('success', 'Género actualizado.');
        $this->assertSame('hombre', $sinGenero->fresh()->genero);
        $this->actingAs($this->admin)->patch("/admin/usuarios/{$c->id}/genero", ['genero' => 'otro'])->assertSessionHasErrors('genero');
    }

    /* ─────────────── exportaciones ─────────────── */

    public function test_exportaciones_excel(): void
    {
        $c = $this->crearComprador('aprobado');
        Cita::create(['edicion_id' => $this->evento->id, 'restaurantero_id' => $this->restaurantero->id, 'servicio_id' => $this->servicio->id, 'cliente_id' => $c->id, 'inicio' => now()->addDay()->setTime(10, 0), 'fin' => now()->addDay()->setTime(10, 30), 'estado' => 'confirmada']);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/exportar'));
        $this->assertSame($this->evento->id, $p['evento']['id']);

        $slug = Str::slug($this->evento->nombre);
        $this->actingAs($this->admin)->get("/admin/exportar/evento/{$this->evento->id}")->assertOk()->assertDownload("evento_{$slug}_2026-10-07.xlsx");
        $this->actingAs($this->admin)->get('/admin/exportar/evento-completo')->assertOk()->assertDownload("evento_{$slug}_2026-10-07.xlsx");
        $this->actingAs($this->admin)->get('/admin/exportar/compradores')->assertOk()->assertDownload('compradores_2026-10-07.xlsx');
        $this->actingAs($this->admin)->get('/admin/exportar/proveedores')->assertOk()->assertDownload('proveedores_2026-10-07.xlsx');
        $this->actingAs($this->admin)->get('/admin/exportar/citas')->assertOk()->assertDownload('citas_2026-10-07.xlsx');

        Evento::query()->update(['activa' => false]);
        $this->actingAs($this->admin)->from('/admin/exportar')->get('/admin/exportar/evento-completo')
            ->assertRedirect('/admin/exportar')->assertSessionHasErrors(['error' => 'No hay un evento activo para exportar.']);
    }

    /* ─────────────── alta y baja de proveedor ─────────────── */

    public function test_alta_de_proveedor_desde_el_admin(): void
    {
        $this->actingAs($this->admin)->post('/admin/restauranteros', ['name' => 'X', 'email' => $this->restaurantero->user->email, 'password' => 'corta'])
            ->assertSessionHasErrors(['email', 'password', 'nombre_restaurante']);

        $this->actingAs($this->admin)->post('/admin/restauranteros', [
            'name' => 'Dueña Nueva', 'email' => 'nueva@prueba.impulsate.mx', 'password' => 'Secreta2026!',
            'nombre_restaurante' => 'Cocina Nueva', 'telefono' => '9991234567', 'municipio' => 'Mérida', 'categoria' => 'Alimentos',
        ])->assertRedirect('/admin/restauranteros')->assertSessionHas('success', 'Proveedor creado correctamente.');

        $u = User::where('email', 'nueva@prueba.impulsate.mx')->firstOrFail();
        $this->assertTrue($u->hasRole('restaurantero'));
        $this->assertTrue(Hash::check('Secreta2026!', $u->password));
        $r = Restaurantero::where('user_id', $u->id)->firstOrFail();
        $this->assertSame($this->evento->id, $r->edicion_id);
        $this->assertTrue((bool) $r->activo);
        $this->assertSame('aprobado', DB::table('evento_usuario')->where('user_id', $u->id)->where('evento_id', $this->evento->id)->where('tipo', 'proveedor')->value('estado'));
        $this->assertSame('Mesa de Networking', Servicio::where('restaurantero_id', $r->id)->value('nombre'));
        $this->assertSame([1, 2, 3, 4, 5], Horario::where('restaurantero_id', $r->id)->orderBy('dia_semana')->pluck('dia_semana')->map(fn ($d) => (int) $d)->all());
    }

    public function test_alta_de_proveedor_usa_el_evento_de_contexto(): void
    {
        $otro = Evento::create(['nombre' => 'Otro', 'fecha_inicio' => now()->addDays(5)->toDateString(), 'activa' => true, 'fecha_hora_inicio' => now()->addDays(5)]);

        $this->actingAs($this->admin)->withSession(['admin_evento_id' => $otro->id])->post('/admin/restauranteros', [
            'name' => 'Ctx', 'email' => 'ctx@prueba.impulsate.mx', 'password' => 'Secreta2026!', 'nombre_restaurante' => 'Ctx',
        ])->assertRedirect();

        $u = User::where('email', 'ctx@prueba.impulsate.mx')->firstOrFail();
        $this->assertSame($otro->id, Restaurantero::where('user_id', $u->id)->value('edicion_id'));
        $this->assertTrue(DB::table('evento_usuario')->where('user_id', $u->id)->where('evento_id', $otro->id)->exists());
    }

    public function test_baja_de_proveedor(): void
    {
        $user = $this->restaurantero->user;

        $this->actingAs($this->admin)->delete("/admin/restauranteros/{$this->restaurantero->id}")
            ->assertRedirect('/admin/restauranteros')->assertSessionHas('success', 'Restaurantero eliminado correctamente.');

        $this->assertNull($this->restaurantero->fresh());
        $this->assertNull($user->fresh());
    }

    /* ─────────────── pantalla TV publica ─────────────── */

    public function test_datos_publicos_de_la_pantalla_tv(): void
    {
        $c = $this->crearComprador('aprobado', ['nombre_empresa' => 'Empresa Compradora']);
        $hoy = fn ($h, $m = 0) => now()->setTime($h, $m);
        $mk = fn ($inicio, $estado, $tv, $extra = []) => Cita::create(array_merge([
            'edicion_id' => $this->evento->id, 'restaurantero_id' => $this->restaurantero->id, 'servicio_id' => $this->servicio->id,
            'cliente_id' => $c->id, 'inicio' => $inicio, 'fin' => $inicio->copy()->addMinutes(30), 'estado' => $estado, 'estado_tv' => $tv,
        ], $extra));
        $mk($hoy(9, 30), 'confirmada', 'en_curso', ['mesa' => '2', 'hora_real_inicio' => $hoy(9, 35)]);
        $mk($hoy(9, 0), 'confirmada', 'llamando', ['mesa' => '4', 'llamada_at' => $hoy(9, 58)]);
        $mk($hoy(8, 0), 'completada', 'finalizada', ['mesa' => '1', 'hora_real_fin' => $hoy(8, 31)]);
        $mk($hoy(11, 0), 'confirmada', 'pendiente');
        $mk($hoy(12, 0), 'cancelada', 'pendiente');

        $token = $this->evento->tokenTv();
        $d = $this->getJson("/api/tv/{$token}/publico")->assertOk()->json();

        $this->assertSame(['en_curso', 'llamando', 'proximas', 'recientes', 'timestamp', 'fecha'], array_keys($d));
        $this->assertSame('2', $d['en_curso'][0]['mesa']);
        $this->assertSame('09:35', $d['en_curso'][0]['hora_real']);
        $this->assertSame(1500, $d['en_curso'][0]['elapsed_sec']);
        $this->assertSame('Empresa Compradora', $d['en_curso'][0]['comprador']);
        $this->assertSame('4', $d['llamando'][0]['mesa']);
        $this->assertSame(['11:00'], collect($d['proximas'])->pluck('hora')->all());
        $this->assertSame('08:31', $d['recientes'][0]['hora']);
        $this->assertSame('10:00:00', $d['timestamp']);
        $this->assertSame('miércoles 7 de octubre, 2026', $d['fecha']);

        $this->getJson('/api/tv/token-falso/publico')->assertNotFound();
    }
}
