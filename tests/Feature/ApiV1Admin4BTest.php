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
use App\Models\PlantillaCorreo;
use App\Models\Publicidad;
use App\Models\Restaurantero;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/** Fase 4B de la API: segunda mitad del panel de administracion (/api/v1/admin/*). */
class ApiV1Admin4BTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    private User $admin;

    private const PROHIBIDOS = ['ine_path', 'csf_path', 'password', 'remember_token', 'two_factor_secret', 'logo_path', 'foto_path', 'tv_token', 'token', 'imagen'];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00'));
        $this->crearEscenario(['destacado' => true]);
        $this->admin = $this->crearAdmin();
    }

    /* ─────────────── helpers ─────────────── */

    private function comoAdmin(?User $u = null): static
    {
        Sanctum::actingAs($u ?? $this->admin);

        return $this;
    }

    private function comoSuper(): static
    {
        $u = $this->crearAdmin();
        $u->assignRole('super-admin');
        Sanctum::actingAs($u);

        return $this;
    }

    private function assertSinClavesProhibidas(array $json): void
    {
        $claves = [];
        $recorrer = function ($v) use (&$recorrer, &$claves) {
            if (is_array($v)) {
                foreach ($v as $k => $x) {
                    if (is_string($k)) $claves[] = $k;
                    $recorrer($x);
                }
            }
        };
        $recorrer($json);
        foreach (self::PROHIBIDOS as $p) {
            $this->assertNotContains($p, $claves, "La respuesta expone '{$p}'");
        }
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

    private function encuesta(User $u, string $tipo, ?array $respuestas, ?EncuestaPlantilla $p): EncuestaSatisfaccion
    {
        $e = EncuestaSatisfaccion::create([
            'evento_id' => $this->evento->id, 'user_id' => $u->id, 'tipo' => $tipo, 'segmento' => 'todos',
            'token' => Str::random(40), 'encuesta_plantilla_id' => $p?->id,
            'completada_at' => $respuestas ? now()->subHour() : null,
        ]);
        foreach ($respuestas ?? [] as [$pregunta, $t, $valor]) {
            EncuestaRespuesta::create(['encuesta_satisfaccion_id' => $e->id, 'pregunta' => $pregunta, 'tipo' => $t, 'respuesta' => $valor]);
        }

        return $e;
    }

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

    /** Ruta relativa (con query) de una URL firmada absoluta. */
    private function relativa(string $url): string
    {
        $u = parse_url($url);

        return $u['path'] . '?' . $u['query'];
    }

    /* ─────────────── acceso ─────────────── */

    public function test_acceso_admin_y_super_admin(): void
    {
        $this->getJson('/api/v1/admin/encuestas')->assertStatus(401);

        Sanctum::actingAs($this->crearComprador());
        $this->getJson('/api/v1/admin/encuestas')->assertForbidden()->assertJson(['message' => 'Esta sección es solo para administradores.']);
        $this->getJson('/api/v1/admin/metricas')->assertForbidden();
        $this->postJson('/api/v1/admin/exportaciones/citas')->assertForbidden();

        $this->comoAdmin();
        foreach (['plantillas-correo', 'correo-masivo/formulario', 'publicidad', 'categorias'] as $ruta) {
            $this->getJson("/api/v1/admin/{$ruta}")->assertForbidden()->assertJson(['message' => 'Acceso exclusivo del super-administrador.']);
        }
        $this->postJson('/api/v1/admin/correo-masivo/enviar', [])->assertForbidden();
        $this->getJson('/api/v1/admin/encuestas')->assertOk();

        $this->comoSuper();
        foreach (['plantillas-correo', 'correo-masivo/formulario', 'publicidad', 'categorias'] as $ruta) {
            $this->getJson("/api/v1/admin/{$ruta}")->assertOk();
        }
    }

    /* ─────────────── encuestas ─────────────── */

    public function test_encuestas_resumen_lista_y_detalle(): void
    {
        $x = $this->escenarioEncuestas();

        $r = $this->comoAdmin()->getJson('/api/v1/admin/encuestas/resumen')->assertOk()->json('data');
        $this->assertSame(3, $r['metricas']['total_enviadas']);
        $this->assertSame(2, $r['metricas']['total_respondidas']);
        $this->assertSame(66.7, $r['metricas']['tasa_respuesta']);
        $this->assertEquals(3, collect($r['metricas']['escala'])->firstWhere('pregunta', 'Calificacion')['promedio']);
        $this->assertEquals(50, collect($r['metricas']['binario'])->firstWhere('pregunta', 'Recomienda')['porcentaje_si']);
        $this->assertSame(['id' => $x['p']->id, 'nombre' => 'Satisfaccion'], $r['plantilla_activa']);
        $this->assertCount(2, $r['ultimas_respondidas']);
        $this->assertArrayHasKey('respuestas', $r['ultimas_respondidas'][0]);
        $this->assertSinClavesProhibidas($r);

        $l = $this->getJson('/api/v1/admin/encuestas?per_page=2')->assertOk();
        $this->assertSame(3, $l->json('meta.total'));
        $this->assertCount(2, $l->json('data'));
        $this->assertArrayNotHasKey('respuestas', $l->json('data.0'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/encuestas?estado=pendiente')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/encuestas?tipo=proveedor')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/encuestas?search=' . urlencode($x['c2']->email))->json('meta.total'));
        $this->getJson('/api/v1/admin/encuestas?estado=otra')->assertStatus(422)->assertJsonValidationErrors('estado');
        $this->assertSinClavesProhibidas($l->json());

        $d = $this->getJson("/api/v1/admin/encuestas/{$x['e1']->id}")->assertOk()->json('data');
        $this->assertSame($x['c1']->email, $d['usuario']['email']);
        $this->assertSame(['Calificacion', 'Recomienda', 'Comentarios'], collect($d['respuestas'])->pluck('pregunta')->all());
        $this->assertSame(['id' => $x['p']->id, 'nombre' => 'Satisfaccion'], $d['plantilla']);
        $this->getJson('/api/v1/admin/encuestas/999999')->assertNotFound();
    }

    public function test_graficas_datos_agregados(): void
    {
        $x = $this->escenarioEncuestas();

        $g = $this->comoAdmin()->getJson('/api/v1/admin/encuestas/graficas')->assertOk()->json('data');
        $this->assertSame(['total_enviadas' => 3, 'total_respondidas' => 2, 'tasa' => 66.7, 'compradores' => 1, 'proveedores' => 1, 'canirac' => 1], $g['kpis']);
        $cal = collect($g['preguntas'])->firstWhere('pregunta', 'Calificacion');
        $this->assertEquals(3, $cal['promedio']);
        $this->assertSame('escala', $cal['tipo']);
        $rec = collect($g['preguntas'])->firstWhere('pregunta', 'Recomienda');
        $this->assertEquals(['valor' => 'No', 'count' => 1, 'porcentaje' => 50], collect($rec['opciones'])->firstWhere('valor', 'No'));
        $this->assertSame(['Muy bien'], collect($g['preguntas'])->firstWhere('pregunta', 'Comentarios')['textos']);
        $this->assertCount(2, $g['respondientes']);
        $this->assertCount(3, $g['plantilla']['preguntas']);

        $this->assertSame(1, $this->getJson('/api/v1/admin/encuestas/graficas?segmento=proveedor')->json('data.kpis.total_respondidas'));
        $c = $this->getJson('/api/v1/admin/encuestas/graficas?canirac=si')->json('data');
        $this->assertSame($x['c1']->email, $c['respondientes'][0]['email']);
        $this->assertSame(0, $this->getJson('/api/v1/admin/encuestas/graficas?desde=2026-10-08')->json('data.kpis.total_respondidas'));
        $this->getJson('/api/v1/admin/encuestas/graficas?desde=ayer&segmento=x')->assertStatus(422)->assertJsonValidationErrors(['desde', 'segmento']);
    }

    public function test_eliminar_respuestas_y_participantes(): void
    {
        $x = $this->escenarioEncuestas();
        $nuevo = $this->crearComprador('aprobado');

        $p = $this->comoAdmin()->getJson('/api/v1/admin/encuestas/participantes?evento_id=' . $this->evento->id)->assertOk();
        $this->assertSame(4, $p->json('meta.total'));
        $this->assertSame(1, $p->json('meta.pendientes'));
        $this->assertSame(['user_id', 'nombre', 'email', 'tipo', 'nombre_negocio', 'ya_enviada'], array_keys($p->json('data.0')));
        $this->assertFalse(collect($p->json('data'))->firstWhere('user_id', $nuevo->id)['ya_enviada']);
        $this->getJson('/api/v1/admin/encuestas/participantes')->assertStatus(422)->assertJsonValidationErrors('evento_id');

        $this->deleteJson("/api/v1/admin/encuestas/{$x['e1']->id}")->assertOk()
            ->assertJson(['data' => null, 'message' => "Respuestas de {$x['c1']->name} eliminadas. Puede volver a recibir la encuesta."]);
        $this->assertNull($x['e1']->fresh());
    }

    public function test_envios_de_encuesta(): void
    {
        $p = $this->plantillaEncuesta();
        $c = $this->crearComprador('aprobado');
        $otro = $this->crearComprador('aprobado');
        $this->crearComprador('pendiente');

        $this->comoAdmin()->postJson('/api/v1/admin/encuestas/enviar-evento', ['evento_id' => $this->evento->id, 'segmento' => 'compradores'])
            ->assertOk()->assertJson(['data' => ['enviadas' => 2], 'message' => 'Encuestas enviadas a 2 participante(s).']);
        Mail::assertQueued(EncuestaSatisfaccionMail::class, 2);
        $this->postJson('/api/v1/admin/encuestas/enviar-evento', ['evento_id' => $this->evento->id, 'segmento' => 'compradores'])
            ->assertJson(['data' => ['enviadas' => 0]]);
        $this->postJson('/api/v1/admin/encuestas/enviar-evento', ['segmento' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['evento_id', 'segmento']);

        EncuestaSatisfaccion::query()->delete();
        $this->postJson('/api/v1/admin/encuestas/enviar-seleccion', ['evento_id' => $this->evento->id, 'user_ids' => [$c->id]])
            ->assertOk()->assertJson(['data' => ['enviadas' => 1], 'message' => 'Encuesta enviada a 1 participante(s) seleccionado(s).']);
        $this->assertFalse(EncuestaSatisfaccion::where('user_id', $otro->id)->exists());

        $r = $this->postJson('/api/v1/admin/encuestas/enviar-prueba', ['email' => 'qa@prueba.impulsate.mx'])->assertOk();
        $this->assertTrue(EncuestaSatisfaccion::find($r->json('data.encuesta_id'))->es_prueba);
        $this->postJson('/api/v1/admin/encuestas/enviar-recordatorio-prueba', ['email' => 'qa@prueba.impulsate.mx'])
            ->assertOk()->assertJson(['message' => 'Correo de recordatorio de prueba enviado a qa@prueba.impulsate.mx.']);
        Mail::assertQueued(EncuestaRecordatorioMail::class, 1);

        $p->update(['activa' => false]);
        $this->postJson('/api/v1/admin/encuestas/enviar-evento', ['evento_id' => $this->evento->id, 'segmento' => 'todos'])
            ->assertStatus(422)->assertJson(['message' => 'No hay plantilla activa. Activa una plantilla primero.', 'errors' => ['error' => ['No hay plantilla activa. Activa una plantilla primero.']]]);
        $this->postJson('/api/v1/admin/encuestas/enviar-seleccion', ['evento_id' => $this->evento->id, 'user_ids' => [$otro->id]])
            ->assertStatus(422)->assertJsonPath('message', 'No hay plantilla activa. Activa una primero.');
    }

    public function test_recordatorio(): void
    {
        Bus::fake([SendRecordatorioJob::class]);
        $x = $this->escenarioEncuestas();

        $this->comoAdmin()->postJson('/api/v1/admin/encuestas/enviar-recordatorio', ['evento_id' => $this->evento->id])
            ->assertOk()->assertExactJson(['data' => [
                'enviados' => 1, 'ya_contestaron' => 2,
                'personas' => [['nombre' => $x['c2']->name, 'email' => $x['c2']->email, 'tipo' => 'comprador']],
            ], 'message' => 'Recordatorio enviado a 1 persona(s) pendiente(s).']);
        Bus::assertDispatched(SendRecordatorioJob::class, fn ($j) => $j->encuestaId === $x['e3']->id && $j->queue === 'encuestas');
    }

    public function test_plantillas_de_encuesta(): void
    {
        $activa = $this->plantillaEncuesta(['nombre' => 'Activa']);
        $datos = [
            'nombre' => 'Nueva', 'segmento' => 'compradores',
            'preguntas' => [
                ['id' => 'a', 'tipo' => 'opciones', 'texto' => 'Elige', 'opciones' => ['X', 'Y']],
                ['id' => 'b', 'tipo' => 'texto', 'texto' => 'Por que', 'condicion' => ['pregunta_id' => 'a', 'operador' => 'igual', 'valor' => 'X']],
            ],
        ];

        $l = $this->comoAdmin()->getJson('/api/v1/admin/encuestas/plantillas')->assertOk();
        $this->assertSame(3, $l->json('data.0.total_preguntas'));
        $this->assertArrayNotHasKey('preguntas', $l->json('data.0'));
        $this->assertContains('nps', $l->json('meta.tipos_pregunta'));

        $n = $this->postJson('/api/v1/admin/encuestas/plantillas', $datos)->assertCreated()
            ->assertJsonPath('message', 'Plantilla guardada correctamente.')->json('data');
        $this->assertFalse($n['activa']);
        $this->assertSame('a', $n['preguntas'][1]['condicion']['pregunta_id']);

        $this->putJson("/api/v1/admin/encuestas/plantillas/{$n['id']}", array_merge($datos, ['nombre' => 'Editada']))->assertOk()->assertJsonPath('data.nombre', 'Editada');
        $this->assertSame(2, EncuestaPlantilla::count());
        $this->getJson("/api/v1/admin/encuestas/plantillas/{$n['id']}")->assertOk()->assertJsonCount(2, 'data.preguntas');

        $this->postJson('/api/v1/admin/encuestas/plantillas', ['nombre' => '', 'segmento' => 'x', 'preguntas' => [['id' => 'a', 'tipo' => 'raro', 'texto' => 'x']]])
            ->assertStatus(422)->assertJsonValidationErrors(['nombre', 'segmento', 'preguntas.0.tipo']);

        $this->postJson("/api/v1/admin/encuestas/plantillas/{$n['id']}/activar")->assertOk()
            ->assertJson(['data' => ['activa' => true], 'message' => 'Plantilla "Editada" activada. Se usará en los próximos envíos.']);
        $this->assertFalse($activa->fresh()->activa);

        $this->deleteJson("/api/v1/admin/encuestas/plantillas/{$n['id']}")->assertStatus(422)
            ->assertJsonPath('errors.error.0', 'No puedes eliminar la plantilla activa. Activa otra primero.');
        $this->deleteJson("/api/v1/admin/encuestas/plantillas/{$activa->id}")->assertOk()->assertJson(['message' => 'Plantilla eliminada.']);
    }

    /* ─────────────── metricas ─────────────── */

    public function test_metricas(): void
    {
        Storage::fake('public');
        $this->restaurantero->update(['municipio' => 'Mérida', 'categoria' => 'Alimentos', 'rfc' => 'XAXX010101000', 'logo_path' => 'logos/x.png']);
        $c = $this->crearComprador('aprobado', ['municipio' => 'Progreso', 'genero' => 'mujer', 'necesidades' => 'Busco tortillas y tortillas']);
        $sinGenero = $this->crearComprador('aprobado', ['name' => 'Aaa Sin Genero']);
        DB::table('page_visits')->insert([
            ['restaurantero_id' => $this->restaurantero->id, 'url' => '/proveedores/1', 'device_type' => 'mobile', 'created_at' => now(), 'updated_at' => now()],
            ['restaurantero_id' => null, 'url' => '/', 'device_type' => 'desktop', 'created_at' => now()->subDays(3), 'updated_at' => now()],
        ]);

        $d = $this->comoAdmin()->getJson('/api/v1/admin/metricas')->assertOk()->json('data');
        $this->assertSame(['sin_datos', 'total', 'hoy', 'semana', 'por_dia', 'por_pagina', 'por_dispositivo', 'proveedores_top'], array_keys($d['visitas']));
        $this->assertSame(2, $d['visitas']['total']);
        $this->assertSame(1, $d['visitas']['hoy']);
        $this->assertSame([['fecha' => '2026-10-04', 'total' => 1], ['fecha' => '2026-10-07', 'total' => 1]], $d['visitas']['por_dia']);
        $this->assertContains(['dispositivo' => 'mobile', 'total' => 1], $d['visitas']['por_dispositivo']);
        $this->assertSame('Negocio Test', $d['visitas']['proveedores_top'][0]['nombre']);
        $this->assertStringEndsWith('logos/x.png', $d['visitas']['proveedores_top'][0]['logo_url']);
        $this->assertSame([['municipio' => 'Mérida', 'total' => 1]], $d['top']['municipios_proveedores']);
        $this->assertSame([['municipio' => 'Progreso', 'total' => 1]], $d['top']['municipios_compradores']);
        $this->assertSame([['categoria' => 'Alimentos', 'total' => 1]], $d['top']['categorias_proveedores']);
        $this->assertSame(['necesidad' => 'tortillas', 'total' => 2], $d['top']['necesidades_compradores'][0]);
        $this->assertSame(['hombre' => 0, 'mujer' => 1, 'no_identificado' => 2, 'total' => 3], $d['genero']);
        $this->assertSame(['con_rfc' => 1, 'sin_rfc' => 0], $d['rfc']['proveedores']);
        $this->assertSame(3, $d['rfc']['total']);
        $this->assertSinClavesProhibidas($d);

        $n = $this->getJson('/api/v1/admin/metricas/no-identificados?per_page=1')->assertOk();
        $this->assertSame(2, $n->json('meta.total'));
        $this->assertSame(['id' => $sinGenero->id, 'nombre' => 'Aaa Sin Genero', 'email' => $sinGenero->email, 'roles' => ['cliente']], $n->json('data.0'));

        $this->patchJson("/api/v1/admin/usuarios/{$sinGenero->id}/genero", ['genero' => 'hombre'])->assertOk()
            ->assertExactJson(['data' => ['id' => $sinGenero->id, 'genero' => 'hombre'], 'message' => 'Género actualizado.']);
        $this->patchJson("/api/v1/admin/usuarios/{$c->id}/genero", ['genero' => 'otro'])->assertStatus(422)->assertJsonValidationErrors('genero');
    }

    /* ─────────────── exportaciones ─────────────── */

    public function test_exportaciones_con_url_firmada(): void
    {
        Storage::fake('local');
        $this->escenarioEncuestas();

        $cat = $this->comoAdmin()->getJson('/api/v1/admin/exportaciones')->assertOk();
        $this->assertCount(8, $cat->json('data'));
        $this->assertSame(15, $cat->json('meta.minutos_url'));

        $slug = Str::slug($this->evento->nombre);
        $esperados = [
            'evento'            => "evento_{$slug}_2026-10-07.xlsx",
            'compradores'       => 'compradores_2026-10-07.xlsx',
            'proveedores'       => 'proveedores_2026-10-07.xlsx',
            'citas'             => 'citas_2026-10-07.xlsx',
            'metricas'          => 'metricas_impulsate_2026-10-07.xlsx',
            'encuestas'         => 'encuestas_todos_2026-10-07.xlsx',
            'encuestas-reporte' => 'encuestas-2026-10-07.xlsx',
            'encuestas-pdf'     => 'reporte-encuestas-2026-10-07.pdf',
        ];
        foreach ($esperados as $tipo => $nombre) {
            $d = $this->comoAdmin()->postJson("/api/v1/admin/exportaciones/{$tipo}")->assertCreated()->json('data');
            $this->assertSame($nombre, $d['nombre'], $tipo);
            $this->assertSame(now()->addMinutes(15)->toIso8601String(), $d['expira_at']);
            $this->assertStringContainsString('/api/v1/exportaciones/', $d['url']);
            $this->assertStringContainsString('signature=', $d['url']);

            // Sin token: la firma basta (el navegador de la app no manda Bearer).
            $this->app['auth']->forgetGuards();
            $this->get($this->relativa($d['url']))->assertOk()->assertDownload($nombre);
        }

        $this->comoAdmin()->postJson('/api/v1/admin/exportaciones/encuestas', ['evento_id' => 999999])->assertStatus(422)->assertJsonValidationErrors('evento_id');
        $this->postJson('/api/v1/admin/exportaciones/otra')->assertNotFound();
    }

    public function test_url_firmada_se_rechaza_si_cambia_expira_o_ya_no_es_admin(): void
    {
        Storage::fake('local');
        $d = $this->comoAdmin()->postJson('/api/v1/admin/exportaciones/citas')->assertCreated()->json('data');
        $url = $this->relativa($d['url']);
        $this->app['auth']->forgetGuards();

        $this->get(str_replace('citas_', 'otro_', $url))->assertForbidden();
        $this->get(preg_replace('/solicitante=\d+/', 'solicitante=1', $url))->assertForbidden();

        $this->admin->removeRole('admin');
        $this->get($url)->assertForbidden();
        $this->admin->assignRole('admin');
        $this->get($url)->assertOk();

        $this->travel(16)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_reporte_sin_plantilla_422_y_archivo_viejo_se_purga(): void
    {
        Storage::fake('local');
        $this->comoAdmin()->postJson('/api/v1/admin/exportaciones/encuestas-pdf')->assertStatus(422)
            ->assertJson(['message' => 'No hay plantilla activa. Activa una primero.']);

        $disco = Storage::disk('local');
        $aHoy = fn () => collect($disco->files('exportaciones'))->each(fn ($f) => touch($disco->path($f), now()->getTimestamp()));

        $this->postJson('/api/v1/admin/exportaciones/citas')->assertCreated();
        $aHoy();
        $this->postJson('/api/v1/admin/exportaciones/compradores')->assertCreated();
        $aHoy();
        $this->assertCount(2, $disco->files('exportaciones'));

        // Pasados 30 min (el doble de la vigencia) se borran al generar otro.
        $this->travel(31)->minutes();
        $this->postJson('/api/v1/admin/exportaciones/proveedores')->assertCreated();
        $this->assertCount(1, $disco->files('exportaciones'));
    }

    /* ─────────────── plantillas de correo (super-admin) ─────────────── */

    public function test_plantillas_de_correo(): void
    {
        $sis = PlantillaCorreo::create(['clave' => 'cita_agendada', 'nombre' => 'Cita agendada', 'asunto' => 'Cita con {{nombre_proveedor}}', 'contenido' => '<p>Hola {{nombre_usuario}}</p>', 'tipo_destinatario' => 'ambos', 'es_sistema' => true, 'activo' => true]);

        $l = $this->comoSuper()->getJson('/api/v1/admin/plantillas-correo')->assertOk();
        $this->assertArrayNotHasKey('contenido', $l->json('data.0'));
        $this->assertSame(['comprador', 'proveedor', 'ambos'], $l->json('meta.tipos_destinatario'));

        $nueva = $this->getJson('/api/v1/admin/plantillas-correo/nueva')->assertOk()->json('data');
        $this->assertStringContainsString('{{nombre_usuario}}', $nueva['html_base']);
        $this->assertSame('{{nombre_usuario}}', $nueva['placeholders'][0]['clave']);

        $d = $this->getJson("/api/v1/admin/plantillas-correo/{$sis->id}")->assertOk();
        $this->assertSame('<p>Hola {{nombre_usuario}}</p>', $d->json('data.contenido'));
        $this->assertContains('{{fecha_cita}}', collect($d->json('meta.placeholders'))->pluck('clave'));

        $v = $this->getJson("/api/v1/admin/plantillas-correo/{$sis->id}/vista-previa")->assertOk()->json('data');
        $this->assertSame('Cita con Salsas Doña Chela', $v['asunto']);
        $this->assertStringContainsString('<p>Hola María Pérez</p>', $v['html']);
        $this->assertStringContainsString('<!DOCTYPE html>', $v['html']);
        $this->postJson('/api/v1/admin/plantillas-correo/vista-previa', ['asunto' => 'A {{nombre_evento}}', 'contenido' => 'x'])
            ->assertOk()->assertJsonPath('data.asunto', 'A Encuentro de Negocios Impúlsate');
        Mail::assertNothingQueued();

        $this->postJson('/api/v1/admin/plantillas-correo', ['nombre' => 'X', 'clave' => 'Mal Clave', 'asunto' => 'a', 'contenido' => 'b', 'tipo_destinatario' => 'nadie'])
            ->assertStatus(422)->assertJsonValidationErrors(['clave', 'tipo_destinatario']);
        $c = $this->postJson('/api/v1/admin/plantillas-correo', ['nombre' => 'Aviso', 'clave' => 'aviso_general', 'asunto' => 'Hola', 'contenido' => '<p>x</p>', 'tipo_destinatario' => 'ambos'])
            ->assertCreated()->assertJsonPath('message', 'Plantilla creada correctamente.')->json('data');
        $this->assertFalse($c['es_sistema']);
        $this->assertTrue($c['activo']);

        $this->putJson("/api/v1/admin/plantillas-correo/{$c['id']}", ['asunto' => 'Nuevo', 'contenido' => 'y', 'clave' => 'hack'])
            ->assertOk()->assertJson(['data' => ['asunto' => 'Nuevo', 'clave' => 'aviso_general'], 'message' => 'Plantilla actualizada correctamente.']);
        $this->postJson("/api/v1/admin/plantillas-correo/{$c['id']}/activo")->assertOk()->assertJson(['data' => ['activo' => false], 'message' => 'Plantilla desactivada.']);

        $this->postJson("/api/v1/admin/plantillas-correo/{$c['id']}/enviar", ['destinatarios' => ['a@prueba.impulsate.mx', 'b@prueba.impulsate.mx']])
            ->assertOk()->assertJson(['data' => ['encolados' => 2]]);
        Mail::assertQueued(PlantillaCorreoMail::class, 2);
        $this->postJson("/api/v1/admin/plantillas-correo/{$c['id']}/enviar", ['destinatarios' => ['malo']])->assertStatus(422)->assertJsonValidationErrors('destinatarios.0');

        $this->postJson('/api/v1/admin/plantillas-correo/restablecer')->assertOk();
        $this->assertTrue(PlantillaCorreo::where('clave', 'cita_aceptada')->exists());

        $this->deleteJson("/api/v1/admin/plantillas-correo/{$sis->id}")->assertStatus(422)->assertJsonPath('message', 'No se pueden eliminar plantillas del sistema.');
        $this->deleteJson("/api/v1/admin/plantillas-correo/{$c['id']}")->assertOk()->assertJson(['message' => 'Plantilla eliminada.']);
    }

    /* ─────────────── correo masivo (super-admin) ─────────────── */

    public function test_correo_masivo(): void
    {
        Categoria::create(['nombre' => 'Alimentos', 'orden' => 1, 'activo' => true]);
        $this->restaurantero->update(['categoria' => 'Alimentos', 'aprobado' => true]);
        $c1 = $this->crearComprador('aprobado', ['camara_asociacion' => 'CANIRAC']);
        $this->crearComprador(null);

        $f = $this->comoSuper()->getJson('/api/v1/admin/correo-masivo/formulario')->assertOk()->json('data');
        $this->assertSame(['Alimentos'], $f['categorias']);
        $this->assertSame(['todos', 'compradores', 'proveedores'], $f['filtros']['tipo']);

        $d = $this->postJson('/api/v1/admin/correo-masivo/destinatarios', ['tipo' => 'compradores'])->assertOk();
        $this->assertSame(['total' => 2, 'mostrados' => 2], $d->json('meta'));
        $this->assertSame(['id', 'name', 'email', 'rol', 'canirac'], array_keys($d->json('data.0')));
        $this->assertSame(1, $this->postJson('/api/v1/admin/correo-masivo/destinatarios', ['tipo' => 'compradores', 'canirac' => 'si'])->json('meta.total'));
        $this->assertSame(1, $this->postJson('/api/v1/admin/correo-masivo/destinatarios', ['tipo' => 'proveedores', 'categoria' => 'Alimentos', 'estado_proveedor' => 'aprobado'])->json('meta.total'));
        $this->postJson('/api/v1/admin/correo-masivo/destinatarios', ['tipo' => 'otros', 'evento_id' => 999999])->assertStatus(422)->assertJsonValidationErrors(['tipo', 'evento_id']);

        $this->postJson('/api/v1/admin/correo-masivo/vista-previa', ['asunto' => 'Hola', 'contenido' => '<b>{{nombre_usuario}}</b>'])
            ->assertOk()->assertJsonPath('data.asunto', 'Hola');

        $this->postJson('/api/v1/admin/correo-masivo/prueba', ['email_prueba' => 'qa@prueba.impulsate.mx', 'asunto' => 'Hola', 'contenido' => 'x'])
            ->assertOk()->assertJson(['message' => 'Correo de prueba enviado a qa@prueba.impulsate.mx.']);
        Mail::assertQueued(PlantillaCorreoMail::class, fn ($m) => $m->hasTo('qa@prueba.impulsate.mx') && $m->plantilla->asunto === '[PRUEBA] Hola');

        $masivo = ['tipo' => 'compradores', 'canirac' => 'si', 'asunto' => 'Masivo', 'contenido' => 'Hola {{nombre_usuario}}'];
        $this->postJson('/api/v1/admin/correo-masivo/enviar', $masivo)->assertStatus(422)->assertJsonValidationErrors('total_esperado');
        $this->postJson('/api/v1/admin/correo-masivo/enviar', $masivo + ['total_esperado' => 5])->assertStatus(422)
            ->assertJsonPath('errors.total_esperado.0', 'La lista cambió: ahora son 1 destinatario(s). Revisa los destinatarios y vuelve a confirmar.');
        $this->postJson('/api/v1/admin/correo-masivo/enviar', $masivo + ['total_esperado' => 1])
            ->assertOk()->assertJson(['data' => ['encolados' => 1], 'message' => 'Correos en cola para 1 destinatarios.']);
        Mail::assertQueued(PlantillaCorreoMail::class, fn ($m) => $m->hasTo($c1->email) && $m->vars === ['nombre_usuario' => $c1->name]);
        Mail::assertQueued(PlantillaCorreoMail::class, 2);
    }

    /* ─────────────── publicidad (super-admin) ─────────────── */

    public function test_publicidad(): void
    {
        Storage::fake('public');

        $p = $this->comoSuper()->post('/api/v1/admin/publicidad', [
            'titulo' => 'Promo', 'imagen' => UploadedFile::fake()->image('p.jpg'), 'enlace' => 'https://impulsate.mx',
            'abre_en_nueva_pestana' => '1', 'fecha_inicio' => '2026-10-01 00:00', 'fecha_fin' => '2026-10-31 23:59', 'activa' => '1',
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('message', 'Anuncio creado correctamente.')->json('data');
        $this->assertSame('Vigente', $p['estado']);
        $this->assertTrue($p['activa']);
        $this->assertStringContainsString('/storage/publicidad/', $p['imagen_url']);
        Storage::disk('public')->assertExists(Publicidad::find($p['id'])->imagen);
        $this->assertSinClavesProhibidas($p);

        $l = $this->getJson('/api/v1/admin/publicidad')->assertOk();
        $this->assertSame($p['id'], $l->json('meta.vigente_id'));

        $this->postJson('/api/v1/admin/publicidad', ['titulo' => 'X', 'imagen' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml'), 'fecha_inicio' => '2026-10-01', 'fecha_fin' => '2026-09-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['imagen', 'fecha_fin']);

        $vieja = Publicidad::find($p['id'])->imagen;
        $this->post("/api/v1/admin/publicidad/{$p['id']}", [
            'titulo' => 'Promo 2', 'imagen' => UploadedFile::fake()->image('q.png'), 'fecha_inicio' => '2026-11-01 00:00', 'fecha_fin' => '2026-11-30 00:00',
        ], ['Accept' => 'application/json'])->assertOk()->assertJson(['data' => ['titulo' => 'Promo 2', 'estado' => 'Programada']]);
        Storage::disk('public')->assertMissing($vieja);

        $this->postJson("/api/v1/admin/publicidad/{$p['id']}/activa")->assertOk()->assertJson(['data' => ['activa' => false, 'estado' => 'Inactiva'], 'message' => 'Anuncio desactivado.']);
        $this->getJson("/api/v1/admin/publicidad/{$p['id']}")->assertOk()->assertJsonPath('data.titulo', 'Promo 2');

        $this->deleteJson("/api/v1/admin/publicidad/{$p['id']}")->assertOk()->assertJson(['data' => null, 'message' => 'Anuncio eliminado.']);
        $this->assertSame(0, Publicidad::count());
    }

    /* ─────────────── categorias (super-admin) ─────────────── */

    public function test_categorias(): void
    {
        $a = Categoria::create(['nombre' => 'Alimentos', 'orden' => 1, 'activo' => true]);
        $b = Categoria::create(['nombre' => 'Bebidas', 'orden' => 2, 'activo' => true]);
        $this->restaurantero->update(['categoria' => 'Alimentos']);

        $l = $this->comoSuper()->getJson('/api/v1/admin/categorias')->assertOk();
        $this->assertSame(['id' => $a->id, 'nombre' => 'Alimentos', 'orden' => 1, 'activo' => true, 'proveedores_activos' => 1], $l->json('data.0'));

        $this->postJson('/api/v1/admin/categorias', ['nombre' => 'Bebidas'])->assertStatus(422)->assertJsonValidationErrors('nombre');
        $s = $this->postJson('/api/v1/admin/categorias', ['nombre' => 'Servicios'])->assertCreated()
            ->assertJson(['data' => ['nombre' => 'Servicios', 'orden' => 3, 'activo' => true], 'message' => 'Categoría agregada.'])->json('data');

        $this->putJson("/api/v1/admin/categorias/{$b->id}", ['nombre' => 'Bebidas y licores'])->assertOk()->assertJsonPath('data.nombre', 'Bebidas y licores');
        $this->postJson("/api/v1/admin/categorias/{$b->id}/activo")->assertOk()->assertJson(['data' => ['activo' => false], 'message' => 'Categoría desactivada.']);

        $this->deleteJson("/api/v1/admin/categorias/{$a->id}")->assertStatus(422)->assertJsonPath('message', 'No se puede eliminar: hay proveedores con esta categoría.');
        $this->deleteJson("/api/v1/admin/categorias/{$b->id}")->assertOk()->assertJson(['message' => 'Categoría eliminada.']);

        $r = $this->postJson('/api/v1/admin/categorias/reordenar', ['ids' => [$s['id'], $a->id]])->assertOk()->assertJsonPath('message', 'Orden actualizado.');
        $this->assertSame([$s['id'], $a->id], collect($r->json('data'))->pluck('id')->all());
        $this->postJson('/api/v1/admin/categorias/reordenar', [])->assertStatus(422)->assertJsonValidationErrors('ids');
    }

    /* ─────────────── alta y baja de proveedor ─────────────── */

    public function test_alta_y_baja_de_proveedor_con_evento_explicito(): void
    {
        $otro = Evento::create(['nombre' => 'Otro activo', 'fecha_inicio' => now()->addDays(5)->toDateString(), 'activa' => true, 'fecha_hora_inicio' => now()->addDays(5)]);
        $inactivo = Evento::create(['nombre' => 'Inactivo', 'fecha_inicio' => now()->addDays(5)->toDateString(), 'activa' => false]);
        $datos = ['name' => 'Dueña', 'email' => 'nueva@prueba.impulsate.mx', 'password' => 'Secreta2026!', 'nombre_restaurante' => 'Cocina Nueva', 'categoria' => 'Alimentos'];

        $this->comoAdmin()->postJson('/api/v1/admin/proveedores', ['email' => $this->restaurantero->user->email])
            ->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'password', 'nombre_restaurante']);
        $this->postJson('/api/v1/admin/proveedores', $datos + ['evento_id' => $inactivo->id])
            ->assertStatus(422)->assertJsonPath('errors.evento_id.0', 'Ese evento ya no está activo.');

        $r = $this->postJson('/api/v1/admin/proveedores', $datos + ['evento_id' => $otro->id])->assertCreated()
            ->assertJsonPath('message', 'Proveedor creado correctamente.')->json('data');
        $this->assertSame('Cocina Nueva', $r['nombre_negocio']);
        $this->assertTrue($r['activo']);
        $this->assertSinClavesProhibidas($r);
        $rest = Restaurantero::findOrFail($r['id']);
        $this->assertSame($otro->id, $rest->edicion_id);
        $this->assertSame('aprobado', DB::table('evento_usuario')->where('user_id', $rest->user_id)->where('evento_id', $otro->id)->value('estado'));
        $this->assertFalse(DB::table('evento_usuario')->where('user_id', $rest->user_id)->where('evento_id', $this->evento->id)->exists());
        $this->assertSame(1, $rest->servicios()->count());
        $this->assertSame(5, $rest->horarios()->count());

        // Sin evento_id: el evento activo principal.
        $r2 = $this->postJson('/api/v1/admin/proveedores', array_merge($datos, ['email' => 'otra@prueba.impulsate.mx']))->assertCreated()->json('data');
        $this->assertSame($this->evento->id, Restaurantero::find($r2['id'])->edicion_id);

        $userId = $rest->user_id;
        $this->deleteJson("/api/v1/admin/proveedores/{$rest->id}")->assertOk()->assertJson(['data' => null, 'message' => 'Restaurantero eliminado correctamente.']);
        $this->assertNull(Restaurantero::find($rest->id));
        $this->assertNull(User::find($userId));
    }

    /* ─────────────── pantalla TV ─────────────── */

    public function test_datos_de_la_pantalla_tv(): void
    {
        $c = $this->crearComprador('aprobado', ['nombre_empresa' => 'Empresa Compradora']);
        Cita::create([
            'edicion_id' => $this->evento->id, 'restaurantero_id' => $this->restaurantero->id, 'servicio_id' => $this->servicio->id,
            'cliente_id' => $c->id, 'inicio' => now()->setTime(9, 30), 'fin' => now()->setTime(10, 0), 'estado' => 'confirmada',
            'estado_tv' => 'en_curso', 'mesa' => '2', 'hora_real_inicio' => now()->setTime(9, 35),
        ]);

        $d = $this->comoAdmin()->getJson("/api/v1/admin/eventos/{$this->evento->id}/tv/datos")->assertOk()->json('data');
        $publico = $this->getJson('/api/tv/' . $this->evento->fresh()->tokenTv() . '/publico')->json();
        $this->assertSame($publico, $d);
        $this->assertSame('Empresa Compradora', $d['en_curso'][0]['comprador']);
        $this->getJson('/api/v1/admin/eventos/999999/tv/datos')->assertNotFound();
    }
}
