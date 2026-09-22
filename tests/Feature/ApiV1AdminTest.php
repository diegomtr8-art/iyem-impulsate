<?php

namespace Tests\Feature;

use App\Mail\CitaCancelada;
use App\Mail\CitaConfirmada;
use App\Mail\EventoSolicitudAprobadaMail;
use App\Mail\EventoSolicitudRechazadaMail;
use App\Mail\PlantillaCorreoMail;
use App\Mail\ProveedorAprobado;
use App\Mail\ProveedorRechazado;
use App\Models\AgendaPropuesta;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\EventoCriterio;
use App\Models\Notificacion;
use App\Models\PlantillaCorreo;
use App\Models\Restaurantero;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/** Fase 4A de la API: primera mitad del panel de administracion (/api/v1/admin/*). */
class ApiV1AdminTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    private User $admin;

    private const PROHIBIDOS = ['ine_path', 'csf_path', 'password', 'remember_token', 'two_factor_secret', 'logo_path', 'foto_path', 'tv_token', 'token'];

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

    private function superAdmin(): User
    {
        $u = $this->crearAdmin();
        $u->assignRole('super-admin');

        return $u;
    }

    private function cita(User $comprador, Carbon $inicio, string $estado = 'pendiente', ?Restaurantero $r = null, array $extra = []): Cita
    {
        $r ??= $this->restaurantero;

        return Cita::create(array_merge([
            'edicion_id'       => $this->evento->id,
            'restaurantero_id' => $r->id,
            'servicio_id'      => $r->servicios()->value('id'),
            'cliente_id'       => $comprador->id,
            'inicio'           => $inicio,
            'fin'              => $inicio->copy()->addMinutes(30),
            'estado'           => $estado,
        ], $extra));
    }

    private function registro(User $u, string $tipo, string $estado = 'pendiente', ?Evento $evento = null, array $extra = []): void
    {
        DB::table('evento_usuario')->insert(array_merge([
            'evento_id' => ($evento ?? $this->evento)->id, 'user_id' => $u->id, 'tipo' => $tipo,
            'estado' => $estado, 'created_at' => now(), 'updated_at' => now(),
        ], $extra));
    }

    private function estadoRegistro(User $u, string $tipo, ?Evento $evento = null): ?object
    {
        return DB::table('evento_usuario')->where('evento_id', ($evento ?? $this->evento)->id)
            ->where('user_id', $u->id)->where('tipo', $tipo)->first();
    }

    private function plantilla(string $clave): void
    {
        PlantillaCorreo::create([
            'clave' => $clave, 'nombre' => $clave, 'asunto' => 'Asunto', 'contenido' => 'Hola {{nombre_usuario}}',
            'tipo_destinatario' => 'ambos', 'es_sistema' => true, 'activo' => true,
        ]);
    }

    private function bazar(array $extra = []): Evento
    {
        return Evento::create(array_merge([
            'nombre' => 'Bazar Test', 'fecha_inicio' => now()->addWeek()->toDateString(), 'tipo_evento' => 'bazar_exposicion',
            'activa' => true, 'max_espacios' => 1, 'con_criterios_evaluacion' => true,
            'fecha_hora_inicio' => now()->addWeek(), 'fecha_hora_fin' => now()->addWeek()->addHours(8),
        ], $extra));
    }

    private function datosEvento(array $extra = []): array
    {
        return array_merge([
            'nombre'            => 'Evento API',
            'convocatoria_url'  => 'https://ejemplo.com/convocatoria.pdf',
            'tipo_evento'       => 'encuentro_negocios',
            'fecha_hora_inicio' => '2026-11-10 09:00:00',
            'fecha_hora_fin'    => '2026-11-10 18:00:00',
        ], $extra);
    }

    /** Ninguna clave prohibida en todo el JSON (a cualquier profundidad). */
    private function assertSinClavesProhibidas(array $json): void
    {
        array_walk_recursive($json, function () {});
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

    /* ─────────────── acceso ─────────────── */

    public function test_sin_token_401(): void
    {
        $this->getJson('/api/v1/admin/dashboard')->assertStatus(401)->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_comprador_y_proveedor_403(): void
    {
        Sanctum::actingAs($this->crearComprador());
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden()->assertJson(['message' => 'Esta sección es solo para administradores.']);
        $this->postJson("/api/v1/admin/eventos/{$this->evento->id}/activar")->assertForbidden();

        Sanctum::actingAs($this->restaurantero->user);
        $this->getJson('/api/v1/admin/proveedores')->assertForbidden();
        $this->getJson('/api/v1/admin/torre')->assertForbidden();
    }

    public function test_admin_sin_super_admin_no_entra_a_usuarios_gestion(): void
    {
        $this->comoAdmin()->getJson('/api/v1/admin/usuarios-gestion')
            ->assertForbidden()->assertJson(['message' => 'Acceso exclusivo del super-administrador.']);
    }

    /* ─────────────── dashboard y contexto ─────────────── */

    public function test_dashboard(): void
    {
        $c = $this->crearComprador('pendiente');
        $this->cita($c, now()->setTime(12, 0), 'confirmada');
        $this->cita($c, now()->subDays(2)->setTime(12, 0));

        $d = $this->comoAdmin()->getJson('/api/v1/admin/dashboard')->assertOk()->json('data');

        $this->assertSame(['usuarios' => User::count(), 'proveedores' => 1, 'citas' => 2, 'citas_hoy' => 1], $d['totales']);
        $this->assertSame(['confirmada' => 1, 'pendiente' => 1], collect($d['citas_por_estado'])->sortKeys()->all());
        $this->assertSame([['fecha' => '2026-10-05', 'total' => 1], ['fecha' => '2026-10-07', 'total' => 1]], $d['citas_ultimos_7_dias']);
        $this->assertSame(1, $d['pendientes']['solicitudes_eventos']);
    }

    public function test_contexto(): void
    {
        $otro = Evento::create(['nombre' => 'Otro activo', 'fecha_inicio' => now()->toDateString(), 'activa' => true, 'fecha_hora_inicio' => now()->addDays(3)]);

        $d = $this->comoAdmin()->getJson('/api/v1/admin/contexto')->assertOk()->json('data');
        $this->assertSame($this->evento->id, $d['predeterminado_id']);
        $this->assertContains($otro->id, collect($d['eventos_activos'])->pluck('id'));
    }

    /* ─────────────── eventos ─────────────── */

    public function test_eventos_lista_y_detalle(): void
    {
        $this->crearComprador('pendiente');
        EventoCriterio::create(['evento_id' => $this->evento->id, 'nombre' => 'Calidad', 'porcentaje' => 100, 'orden' => 0]);

        $r = $this->comoAdmin()->getJson('/api/v1/admin/eventos')->assertOk();
        $e = collect($r->json('data'))->firstWhere('id', $this->evento->id);
        $this->assertSame(['citas' => 0, 'proveedores' => 1, 'compradores_con_cita' => 0, 'solicitudes_pendientes' => 1], $e['contadores']);
        $this->assertSame('activo', $e['estado']);
        $this->assertTrue($e['destacado']);
        $this->assertSame('Calidad', $e['criterios'][0]['nombre']);
        $this->assertSinClavesProhibidas($r->json());

        $this->assertCount(0, $this->getJson('/api/v1/admin/eventos?tipo_evento=bazar_exposicion')->json('data'));
        $this->getJson('/api/v1/admin/eventos?estado=raro')->assertStatus(422);

        $this->getJson("/api/v1/admin/eventos/{$this->evento->id}")->assertOk()
            ->assertJsonPath('data.contadores.solicitudes_pendientes', 1);
    }

    public function test_crear_evento_422(): void
    {
        $this->comoAdmin()->postJson('/api/v1/admin/eventos', $this->datosEvento(['convocatoria_url' => 'no-es-url']))
            ->assertStatus(422)->assertJsonValidationErrors(['convocatoria_url']);

        $this->postJson('/api/v1/admin/eventos', $this->datosEvento([
            'con_criterios_evaluacion' => true,
            'criterios' => [['nombre' => 'A', 'porcentaje' => 50], ['nombre' => 'B', 'porcentaje' => 30]],
        ]))->assertStatus(422)->assertJson([
            'message' => 'Los porcentajes deben sumar exactamente 100%.',
            'errors'  => ['criterios' => ['Los porcentajes deben sumar exactamente 100%.']],
        ]);

        $this->postJson('/api/v1/admin/eventos', $this->datosEvento(['imagen' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')]))
            ->assertStatus(422)->assertJsonValidationErrors(['imagen']);

        $this->assertSame(0, Evento::where('nombre', 'Evento API')->count());
    }

    public function test_crear_y_editar_evento(): void
    {
        Storage::fake('public');

        $r = $this->comoAdmin()->post('/api/v1/admin/eventos', $this->datosEvento([
            'tipo_evento'              => 'bazar_exposicion',
            'max_espacios'             => 25,
            'destacado'                => '1',
            'imagen'                   => UploadedFile::fake()->image('e.jpg'),
            'imagen_carrusel'          => UploadedFile::fake()->image('c.png'),
            'con_criterios_evaluacion' => '1',
            'criterios'                => [['nombre' => 'Calidad', 'porcentaje' => 60], ['nombre' => 'Precio', 'porcentaje' => 40]],
        ]), ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('message', 'Evento creado. Actívalo cuando estés listo.')
            ->assertJsonPath('data.activa', false)
            ->assertJsonPath('data.destacado', true)
            ->assertJsonPath('data.max_espacios', 25);

        $id = $r->json('data.id');
        $this->assertNotNull($r->json('data.imagen_url'));
        $this->assertFalse($this->evento->fresh()->destacado);
        $this->assertSinClavesProhibidas($r->json());

        $criterio = EventoCriterio::where('evento_id', $id)->where('nombre', 'Calidad')->first();
        $this->post("/api/v1/admin/eventos/{$id}", $this->datosEvento([
            'nombre' => 'Bazar Editado', 'tipo_evento' => 'bazar_exposicion', 'max_espacios' => 30,
            'con_criterios_evaluacion' => '1',
            'criterios' => [['id' => $criterio->id, 'nombre' => 'Calidad', 'porcentaje' => 100]],
        ]), ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.nombre', 'Bazar Editado')
            ->assertJsonPath('data.destacado', false)
            ->assertJsonPath('data.criterios.0.id', $criterio->id)
            ->assertJsonCount(1, 'data.criterios');

        $this->putJson("/api/v1/admin/eventos/{$id}", ['nombre' => ''])->assertStatus(422);
    }

    public function test_activar_archivar_eliminar_y_tv(): void
    {
        $e = Evento::create(['nombre' => 'Inactivo', 'fecha_inicio' => now()->toDateString(), 'activa' => false]);

        $this->comoAdmin()->postJson("/api/v1/admin/eventos/{$e->id}/archivar")->assertStatus(422)
            ->assertJson(['errors' => ['error' => ['Este evento no está activo.']]]);
        $this->postJson("/api/v1/admin/eventos/{$e->id}/activar")->assertOk()->assertJsonPath('data.activa', true);
        $this->deleteJson("/api/v1/admin/eventos/{$e->id}")->assertStatus(422)
            ->assertJsonPath('message', 'No puedes eliminar el evento activo. Archívalo primero.');
        $this->postJson("/api/v1/admin/eventos/{$e->id}/archivar")->assertOk()->assertJsonPath('data.activa', false)
            ->assertJsonPath('data.fecha_corte', '2026-10-07');

        $tv = $this->getJson("/api/v1/admin/eventos/{$e->id}/tv")->assertOk()->json('data');
        $this->assertSame(48, strlen($tv['token']));
        $this->assertStringEndsWith('/tv/' . $tv['token'], $tv['url']);
        $nuevo = $this->postJson("/api/v1/admin/eventos/{$e->id}/tv/rotar")->assertOk()->json('data.token');
        $this->assertNotSame($tv['token'], $nuevo);

        $this->postJson("/api/v1/admin/eventos/{$e->id}/enviar-encuestas")->assertStatus(422)
            ->assertJsonPath('message', 'No hay plantilla de encuesta activa. Activa una plantilla primero.');

        $this->deleteJson("/api/v1/admin/eventos/{$e->id}")->assertOk()->assertJsonPath('message', 'Evento eliminado correctamente.');
        $this->assertNull($e->fresh());
        $this->getJson('/api/v1/admin/eventos/999999')->assertNotFound();
    }

    /* ─────────────── solicitudes ─────────────── */

    public function test_solicitudes_lista_filtros_y_perfil(): void
    {
        $canirac = $this->crearComprador('pendiente', ['camara_asociacion' => 'CANIRAC', 'nombre_empresa' => 'Rest Canirac']);
        $canirac->forceFill(['ine_path' => 'documentos/x/ine.pdf'])->save();
        $this->crearComprador('rechazado');
        $this->registro($this->restaurantero->user, 'proveedor', 'pendiente');

        $r = $this->comoAdmin()->getJson("/api/v1/admin/eventos/{$this->evento->id}/solicitudes?estado=pendiente")->assertOk();
        $this->assertSame(2, $r->json('meta.total'));
        $this->assertSame(['comprador' => 1, 'proveedor' => 1], collect($r->json('meta.contadores.pendiente'))->sortKeys()->all());
        $this->assertSinClavesProhibidas($r->json());
        $prov = collect($r->json('data'))->firstWhere('tipo', 'proveedor');
        $this->assertSame('Negocio Test', $prov['negocio']['nombre_negocio']);

        $this->assertSame([$canirac->id], collect($this->getJson("/api/v1/admin/eventos/{$this->evento->id}/solicitudes?canirac=1")->json('data'))->pluck('user_id')->all());
        $this->assertSame([$canirac->id], collect($this->getJson("/api/v1/admin/eventos/{$this->evento->id}/solicitudes?q=canirac")->json('data'))->pluck('user_id')->all());
        $this->assertSame(1, $this->getJson("/api/v1/admin/eventos/{$this->evento->id}/solicitudes?estado=rechazado&tipo=comprador")->json('meta.total'));
        $this->getJson("/api/v1/admin/eventos/{$this->evento->id}/solicitudes?tipo=otro")->assertStatus(422);

        $p = $this->getJson("/api/v1/admin/eventos/{$this->evento->id}/solicitudes/{$canirac->id}")->assertOk();
        $this->assertTrue($p->json('data.documentos.ine.subido'));
        $this->assertStringContainsString('signature=', $p->json('data.documentos.ine.url_temporal'));
        $this->assertNull($p->json('data.perfil_proveedor'));
        $this->assertSinClavesProhibidas($p->json());
        $this->assertNotNull($this->getJson("/api/v1/admin/eventos/{$this->evento->id}/solicitudes/{$this->restaurantero->user_id}")->json('data.perfil_proveedor.negocio'));
        $this->getJson("/api/v1/admin/eventos/{$this->evento->id}/solicitudes/{$this->admin->id}")->assertNotFound();

        $c = $this->getJson("/api/v1/admin/eventos/{$this->evento->id}/solicitudes/candidatos")->assertOk()->json('data');
        $this->assertNotContains($this->restaurantero->id, collect($c['proveedores'])->pluck('id'));
        $this->assertNotContains($this->admin->id, collect($c['compradores'])->pluck('id'));
    }

    public function test_aprobar_y_rechazar_solicitud_con_correo_y_aviso(): void
    {
        $c = $this->crearComprador('pendiente');
        $base = "/api/v1/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}";

        $this->comoAdmin()->postJson("{$base}/aprobar")->assertStatus(422)->assertJsonValidationErrors(['tipo']);
        $this->postJson("{$base}/aprobar", ['tipo' => 'proveedor'])->assertStatus(422)->assertJsonPath('message', 'Solicitud no encontrada.');
        Mail::assertNothingQueued();

        $this->postJson("{$base}/aprobar", ['tipo' => 'comprador'])->assertOk()
            ->assertJsonPath('message', 'Solicitud aprobada.')
            ->assertJsonPath('data.estado', 'aprobado');
        Mail::assertQueued(EventoSolicitudAprobadaMail::class, fn ($m) => $m->hasTo($c->email));
        $this->assertSame('Solicitud aprobada', Notificacion::where('user_id', $c->id)->value('titulo'));

        $this->postJson("{$base}/rechazar", ['tipo' => 'comprador'])->assertStatus(422)->assertJsonValidationErrors(['motivo_rechazo']);
        $this->postJson("{$base}/rechazar", ['tipo' => 'comprador', 'motivo_rechazo' => 'Cupo lleno'])->assertOk()
            ->assertJsonPath('data.estado', 'rechazado')
            ->assertJsonPath('data.motivo_rechazo', 'Cupo lleno');
        Mail::assertQueued(EventoSolicitudRechazadaMail::class, fn ($m) => $m->hasTo($c->email));
        $this->assertStringContainsString('Motivo: Cupo lleno', Notificacion::where('user_id', $c->id)->where('titulo', 'Solicitud rechazada')->value('mensaje'));

        $this->postJson("{$base}/revertir", ['tipo' => 'comprador'])->assertOk()->assertJsonPath('data.estado', 'pendiente');
        $this->postJson("{$base}/revertir", ['tipo' => 'comprador'])->assertStatus(422);
    }

    public function test_aprobar_todos_alta_manual_y_eliminar(): void
    {
        $a = $this->crearComprador('pendiente');
        $b = $this->crearComprador('pendiente');
        $base = "/api/v1/admin/eventos/{$this->evento->id}/solicitudes";

        $this->comoAdmin()->postJson("{$base}/aprobar-todos", ['tipo' => 'comprador'])->assertOk()
            ->assertJson(['data' => ['aprobadas' => 2], 'message' => '2 solicitudes aprobadas.']);
        Mail::assertQueued(EventoSolicitudAprobadaMail::class, 2);
        $this->postJson("{$base}/aprobar-todos", ['tipo' => 'comprador'])->assertOk()->assertJsonPath('data.aprobadas', 0);

        $nuevo = $this->crearComprador(null, ['nombre_empresa' => 'Hotel Nuevo']);
        $this->postJson("{$base}/agregar-comprador", ['user_id' => $nuevo->id])->assertCreated()
            ->assertJsonPath('message', 'Comprador "Hotel Nuevo" agregado al evento correctamente.')
            ->assertJsonPath('data.estado', 'aprobado');
        $this->postJson("{$base}/agregar-comprador", ['user_id' => $nuevo->id])->assertStatus(422)
            ->assertJsonPath('message', 'Este comprador ya está registrado en el evento.');
        $prov = $this->crearProveedor('Manual');
        $this->postJson("{$base}/agregar-proveedor", ['restaurantero_id' => $prov->id])->assertCreated()->assertJsonPath('data.tipo', 'proveedor');
        $this->postJson("{$base}/agregar-proveedor", ['restaurantero_id' => 999999])->assertStatus(422);

        $cita = $this->cita($a, now()->addDay()->setTime(10, 0), 'confirmada');
        $this->postJson("{$base}/{$a->id}/eliminar", ['tipo' => 'comprador'])->assertStatus(422)->assertJsonValidationErrors(['motivo_eliminacion']);
        $this->postJson("{$base}/{$a->id}/eliminar", ['tipo' => 'comprador', 'motivo_eliminacion' => 'Duplicado'])->assertOk()
            ->assertJsonPath('message', 'Usuario eliminado del evento.');
        $this->assertNull($this->estadoRegistro($a, 'comprador'));
        $this->assertSame('cancelada', $cita->fresh()->estado);
        $this->assertNotNull($this->estadoRegistro($b, 'comprador'));
    }

    /* ─────────────── bazar ─────────────── */

    public function test_bazar(): void
    {
        $this->comoAdmin()->getJson("/api/v1/admin/eventos/{$this->evento->id}/bazar")->assertNotFound();

        $bazar = $this->bazar();
        $crit = EventoCriterio::create(['evento_id' => $bazar->id, 'nombre' => 'Calidad', 'porcentaje' => 100, 'orden' => 0]);
        $ajeno = EventoCriterio::create(['evento_id' => $this->evento->id, 'nombre' => 'Ajeno', 'porcentaje' => 100, 'orden' => 0]);
        $u = User::factory()->create(['csf_fecha' => now()->subMonth()->toDateString()]);
        $u->forceFill(['csf_path' => 'documentos/x/csf.pdf'])->save();
        $v = User::factory()->create();
        $this->registro($u, 'expositor', 'pendiente', $bazar);
        $this->registro($v, 'expositor', 'aprobado', $bazar);
        $extrano = $this->crearComprador(null);
        $base = "/api/v1/admin/eventos/{$bazar->id}/bazar";

        $r = $this->getJson($base)->assertOk();
        $this->assertSame(2, $r->json('meta.kpis.total'));
        $this->assertSame('Calidad', $r->json('meta.criterios.0.nombre'));
        $this->assertSinClavesProhibidas($r->json());
        $this->assertArrayNotHasKey('ine_url', $r->json('data.0'));

        $this->postJson("{$base}/{$extrano->id}/aprobar")->assertNotFound();
        $this->postJson("{$base}/{$extrano->id}/evaluar", ['evaluaciones' => [['criterio_id' => $crit->id, 'puntaje' => 50]]])->assertNotFound();

        $this->postJson("{$base}/{$u->id}/aprobar")->assertOk()->assertJsonPath('data.estado', 'aprobado')
            ->assertJsonPath('meta.kpis.en_evaluacion', 2);
        $this->postJson("{$base}/{$u->id}/evaluar", ['evaluaciones' => [['criterio_id' => $ajeno->id, 'puntaje' => 90]]])
            ->assertStatus(422)->assertJsonValidationErrors(['evaluaciones.0.criterio_id']);
        $this->postJson("{$base}/{$u->id}/evaluar", ['evaluaciones' => [['criterio_id' => $crit->id, 'puntaje' => 80]]])
            ->assertOk()->assertJsonPath('data.puntaje_total', 80)->assertJsonPath('data.evaluado', true);

        $this->postJson("{$base}/seleccion", ['user_id' => $u->id, 'seleccionado' => true])->assertOk()->assertJsonPath('data.seleccionado', true);
        $this->postJson("{$base}/seleccion", ['user_id' => $v->id, 'seleccionado' => true])->assertStatus(422)
            ->assertJsonPath('message', 'Ya alcanzaste el límite de 1 espacios.');
        $this->postJson("{$base}/{$v->id}/notas-rechazo", ['notas_rechazo' => 'Mejorar empaque'])->assertOk()->assertJsonPath('data.notas_rechazo', 'Mejorar empaque');

        $d = $this->getJson("{$base}/{$u->id}")->assertOk()->json('data');
        $this->assertTrue($d['csf_vigente']);
        $this->assertTrue($d['documentos']['csf']['subido']);

        $this->postJson("{$base}/enviar-aprobacion")->assertStatus(422)->assertJsonPath('message', 'Plantilla "bazar_seleccionado" no encontrada.');
        $this->plantilla('bazar_seleccionado');
        $this->plantilla('bazar_rechazado');
        $this->postJson("{$base}/enviar-aprobacion")->assertOk()->assertJsonPath('data.enviados', 1);
        $this->postJson("{$base}/enviar-rechazo")->assertOk()->assertJsonPath('data.enviados', 1);
        Mail::assertQueued(PlantillaCorreoMail::class, 2);
        $this->assertSame('Seleccionado en el bazar', Notificacion::where('user_id', $u->id)->latest('id')->value('titulo'));
        $this->assertSame('Resultado del bazar', Notificacion::where('user_id', $v->id)->latest('id')->value('titulo'));
    }

    /* ─────────────── proveedores ─────────────── */

    public function test_proveedores_lista_ficha_y_edicion(): void
    {
        $pend = $this->crearProveedor('Pendiente SA');
        $pend->forceFill(['aprobado' => false, 'rechazado' => false, 'solicitado_aprobacion_at' => now()])->saveQuietly();
        $this->restaurantero->forceFill(['aprobado' => true, 'logo_path' => 'logos/x.png'])->saveQuietly();

        $r = $this->comoAdmin()->getJson('/api/v1/admin/proveedores')->assertOk();
        $this->assertSame(2, $r->json('meta.total'));
        $this->assertSame(1, $r->json('meta.pendientes_aprobacion'));
        $this->assertSinClavesProhibidas($r->json());
        $this->assertSame([$pend->id], collect($this->getJson('/api/v1/admin/proveedores?estado=pendiente')->json('data'))->pluck('id')->all());
        $this->assertSame([$pend->id], collect($this->getJson('/api/v1/admin/proveedores?search=Pendiente')->json('data'))->pluck('id')->all());
        $this->assertSame([$pend->id], collect($this->getJson('/api/v1/admin/proveedores/pendientes')->json('data'))->pluck('id')->all());

        $this->cita($this->crearComprador(), now()->addDay()->setTime(10, 0), 'confirmada');
        $f = $this->getJson("/api/v1/admin/proveedores/{$this->restaurantero->id}")->assertOk();
        $this->assertSame(['total' => 1, 'aceptadas' => 1, 'pendientes' => 0], $f->json('data.stats'));
        $this->assertStringEndsWith('/storage/logos/x.png', $f->json('data.proveedor.logo_url'));
        $this->assertArrayHasKey('secciones', $f->json('data.perfil'));
        $this->assertSinClavesProhibidas($f->json());
        $this->assertSame(1, $this->getJson("/api/v1/admin/proveedores/{$this->restaurantero->id}/citas")->json('meta.total'));

        $this->postJson("/api/v1/admin/proveedores/{$this->restaurantero->id}", ['nombre_restaurante' => ''])->assertStatus(422);
        $this->post("/api/v1/admin/proveedores/{$this->restaurantero->id}", [
            'nombre_restaurante' => 'Negocio API', 'cobertura_entrega' => 'regional',
            'logo' => UploadedFile::fake()->image('logo.png'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.nombre_negocio', 'Negocio API');
        $logo = $this->restaurantero->fresh()->logo_path;
        $this->assertStringStartsWith('logos/logo_' . $this->restaurantero->id . '_', $logo);
        @unlink(storage_path('app/public/' . $logo));

        $this->patchJson("/api/v1/admin/proveedores/{$this->restaurantero->id}/categoria", ['categoria' => 'Textiles'])->assertOk()->assertJsonPath('data.categoria', 'Textiles');
        $this->postJson("/api/v1/admin/proveedores/{$this->restaurantero->id}/activo", ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);
        $this->postJson("/api/v1/admin/proveedores/{$this->restaurantero->id}/activo", ['activo' => false])->assertOk()->assertJsonPath('data.activo', false);
        $this->postJson("/api/v1/admin/proveedores/{$this->restaurantero->id}/activo")->assertOk()->assertJsonPath('data.activo', true);
    }

    public function test_aprobar_y_rechazar_proveedor(): void
    {
        $r = $this->crearProveedor('Por revisar');
        $r->forceFill(['aprobado' => false, 'rechazado' => false, 'activo' => false])->saveQuietly();

        $this->comoAdmin()->postJson("/api/v1/admin/proveedores/{$r->id}/rechazar")->assertStatus(422)->assertJsonValidationErrors(['motivo']);
        $this->postJson("/api/v1/admin/proveedores/{$r->id}/rechazar", ['motivo' => 'Falta RFC'])->assertOk()
            ->assertJsonPath('data.estado', 'rechazado')->assertJsonPath('data.motivo_rechazo', 'Falta RFC');
        Mail::assertQueued(ProveedorRechazado::class, fn ($m) => $m->hasTo($r->user->email));

        $this->postJson("/api/v1/admin/proveedores/{$r->id}/aprobar")->assertOk()
            ->assertJsonPath('message', 'Proveedor aprobado y notificado.')
            ->assertJsonPath('data.estado', 'aprobado')->assertJsonPath('data.activo', true);
        Mail::assertQueued(ProveedorAprobado::class, fn ($m) => $m->hasTo($r->user->email));
        $this->assertSame(2, Notificacion::where('user_id', $r->user_id)->count());
    }

    /* ─────────────── usuarios ─────────────── */

    public function test_usuarios_admin(): void
    {
        $c = $this->crearComprador('aprobado', ['name' => 'Zoila Buscada']);
        $c->forceFill(['ine_path' => 'documentos/x/ine.pdf'])->save();
        $otroAdmin = $this->crearAdmin();

        $r = $this->comoAdmin()->getJson('/api/v1/admin/usuarios')->assertOk();
        $this->assertNotContains($otroAdmin->id, collect($r->json('data'))->pluck('id'));
        $this->assertSinClavesProhibidas($r->json());
        $this->assertSame([$c->id], collect($this->getJson('/api/v1/admin/usuarios?search=Zoila')->json('data'))->pluck('id')->all());

        $this->cita($c, now()->addDay()->setTime(10, 0), 'confirmada');
        $f = $this->getJson("/api/v1/admin/usuarios/{$c->id}")->assertOk();
        $this->assertSame(['total' => 1, 'aceptadas' => 1, 'pendientes' => 0], $f->json('data.stats'));
        $this->assertSame('comprador', $f->json('data.eventos.0.tipo'));
        $this->assertTrue($f->json('data.usuario.tiene_ine'));
        $this->assertSinClavesProhibidas($f->json());
        $this->assertTrue($this->getJson("/api/v1/admin/usuarios/{$c->id}/documentos")->json('data.ine.subido'));

        $this->putJson("/api/v1/admin/usuarios/{$c->id}", ['name' => 'X', 'email' => $otroAdmin->email])->assertStatus(422)->assertJsonValidationErrors(['email']);
        $this->putJson("/api/v1/admin/usuarios/{$c->id}", ['name' => 'Nombre API', 'email' => 'api@example.com'])->assertOk()
            ->assertJsonPath('data.nombre', 'Nombre API')->assertJsonPath('message', 'Comprador actualizado correctamente.');

        $this->putJson("/api/v1/admin/usuarios/{$otroAdmin->id}", ['name' => 'Hack', 'email' => 'hack@example.com'])->assertForbidden()
            ->assertJsonPath('message', 'Las cuentas de administrador solo las gestiona el super-administrador.');
        $this->deleteJson("/api/v1/admin/usuarios/{$otroAdmin->id}")->assertForbidden();
        $this->assertSame($otroAdmin->email, $otroAdmin->fresh()->email);

        $this->deleteJson("/api/v1/admin/usuarios/{$c->id}")->assertOk();
        $this->assertNull($c->fresh());
    }

    public function test_usuarios_gestion_super_admin(): void
    {
        $sa = $this->superAdmin();
        $u = $this->crearComprador(null, ['camara_asociacion' => 'CANIRAC', 'nombre_establecimiento' => 'La Casa']);

        $r = $this->comoAdmin($sa)->getJson('/api/v1/admin/usuarios-gestion?canirac=si')->assertOk();
        $this->assertSame([$u->id], collect($r->json('data'))->pluck('id')->all());
        $this->assertContains('super-admin', $r->json('meta.roles'));
        $this->assertSinClavesProhibidas($r->json());
        $this->getJson("/api/v1/admin/usuarios-gestion/{$u->id}")->assertOk()->assertJsonPath('data.roles', ['cliente']);

        $this->putJson("/api/v1/admin/usuarios-gestion/{$u->id}", ['name' => 'U', 'email' => $u->email, 'camara_asociacion' => 'Ninguna', 'nombre_establecimiento' => 'La Casa'])
            ->assertOk()->assertJsonPath('data.nombre_establecimiento', null);
        $this->putJson("/api/v1/admin/usuarios-gestion/{$u->id}", ['name' => 'U', 'email' => $u->email, 'camara_asociacion' => 'OTRA'])->assertStatus(422);

        $this->postJson("/api/v1/admin/usuarios-gestion/{$u->id}/password", ['password' => 'NuevaClave1', 'password_confirmation' => 'x'])->assertStatus(422);
        $this->postJson("/api/v1/admin/usuarios-gestion/{$u->id}/password", ['password' => 'NuevaClave1', 'password_confirmation' => 'NuevaClave1'])->assertOk();
        $this->assertTrue(Hash::check('NuevaClave1', $u->fresh()->password));

        $this->postJson("/api/v1/admin/usuarios-gestion/{$sa->id}/roles", ['roles' => ['admin']])->assertStatus(422)
            ->assertJson(['errors' => ['roles' => ['No puedes quitarte el rol super-admin a ti mismo.']]]);
        $this->postJson("/api/v1/admin/usuarios-gestion/{$u->id}/roles", ['roles' => ['cliente', 'restaurantero']])->assertOk()
            ->assertJsonPath('data.roles', ['cliente', 'restaurantero']);
        $this->postJson("/api/v1/admin/usuarios-gestion/{$u->id}/roles", ['roles' => ['inventado']])->assertStatus(422);

        $this->deleteJson("/api/v1/admin/usuarios-gestion/{$sa->id}")->assertStatus(422)->assertJson(['errors' => ['general' => ['No puedes eliminar tu propia cuenta.']]]);
        $this->deleteJson("/api/v1/admin/usuarios-gestion/{$u->id}")->assertOk();
        $this->assertNull($u->fresh());

        // El super-admin si puede editar a otro admin (como en la web).
        $otroAdmin = $this->crearAdmin();
        $this->putJson("/api/v1/admin/usuarios-gestion/{$otroAdmin->id}", ['name' => 'Admin Dos', 'email' => $otroAdmin->email])->assertOk();
    }

    /* ─────────────── citas ─────────────── */

    public function test_citas_admin(): void
    {
        $c = $this->crearComprador('aprobado', ['name' => 'Ana Filtro']);
        $this->cita($c, now()->addDay()->setTime(10, 0), 'confirmada');

        $r = $this->comoAdmin()->getJson('/api/v1/admin/citas?estado=confirmada')->assertOk();
        $this->assertSame(1, $r->json('meta.total'));
        $this->assertSame('Ana Filtro', $r->json('data.0.comprador.nombre'));
        $this->assertSame(0, $this->getJson('/api/v1/admin/citas?search=nadie')->json('meta.total'));
        $this->assertSame(1, $this->getJson('/api/v1/admin/citas?fecha=2026-10-08')->json('meta.total'));
        $this->assertContains($c->id, collect($this->getJson('/api/v1/admin/citas/formulario')->json('data.clientes'))->pluck('id'));

        $otro = $this->crearComprador();
        $datos = ['cliente_id' => $otro->id, 'restaurantero_id' => $this->restaurantero->id, 'fecha' => '2026-10-08', 'hora' => '12:00', 'duracion' => 20];
        $this->postJson('/api/v1/admin/citas', array_merge($datos, ['hora' => '25:00']))->assertStatus(422)->assertJsonValidationErrors(['hora']);
        $this->postJson('/api/v1/admin/citas', array_merge($datos, ['cliente_id' => $this->admin->id]))->assertStatus(422)
            ->assertJson(['errors' => ['cliente' => ['No se puede agendar una cita para un administrador.']]]);
        $inactivo = Evento::create(['nombre' => 'Inactivo', 'fecha_inicio' => now()->toDateString(), 'activa' => false]);
        $this->postJson('/api/v1/admin/citas', array_merge($datos, ['evento_id' => $inactivo->id]))->assertStatus(422)
            ->assertJson(['errors' => ['evento_id' => ['Ese evento ya no está activo.']]]);

        $id = $this->postJson('/api/v1/admin/citas', array_merge($datos, ['evento_id' => $this->evento->id]))->assertCreated()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.evento.id', $this->evento->id)
            ->json('data.id');
        $this->postJson('/api/v1/admin/citas', $datos)->assertStatus(422)->assertJsonValidationErrors(['fecha']);

        $this->patchJson("/api/v1/admin/citas/{$id}/estado", ['estado' => 'rara'])->assertStatus(422);
        $this->patchJson("/api/v1/admin/citas/{$id}/estado", ['estado' => 'cancelada'])->assertOk()->assertJsonPath('data.estado', 'cancelada');
        Mail::assertQueued(CitaCancelada::class, fn ($m) => $m->hasTo($otro->email));
        $this->patchJson("/api/v1/admin/citas/{$id}/estado", ['estado' => 'confirmada'])->assertOk();
        Mail::assertQueued(CitaConfirmada::class, 1);
        $this->getJson("/api/v1/admin/citas/{$id}")->assertOk()->assertJsonPath('data.proveedor.nombre_negocio', 'Negocio Test');
    }

    /* ─────────────── agenda ─────────────── */

    public function test_agenda_propuesta(): void
    {
        $this->plantilla('agenda_propuesta');
        $c = $this->crearComprador('aprobado');
        $this->registro($this->restaurantero->user, 'proveedor', 'aprobado');

        $f = $this->comoAdmin()->getJson('/api/v1/admin/agenda/formulario')->assertOk()->json('data');
        $this->assertSame($this->evento->id, $f['evento']['id']);
        $this->assertSame([$c->id], collect($f['compradores'])->pluck('id')->all());
        $this->assertSame([$this->restaurantero->id], collect($f['proveedores'])->pluck('id')->all());
        $this->assertCount(48, $f['slots']);

        $slot = ['restaurantero_id' => $this->restaurantero->id, 'slot_inicio' => $f['slots'][6]['inicio'], 'slot_fin' => $f['slots'][6]['fin']];
        $this->postJson('/api/v1/admin/agenda', ['user_id' => $c->id, 'citas' => []])->assertStatus(422)->assertJsonValidationErrors(['citas']);
        $this->postJson('/api/v1/admin/agenda', ['user_id' => $c->id, 'citas' => [$slot, $slot]])->assertStatus(422)
            ->assertJsonPath('message', 'No puedes asignar el mismo proveedor dos veces en la misma propuesta.');

        $r = $this->postJson('/api/v1/admin/agenda', ['user_id' => $c->id, 'citas' => [$slot], 'enviar' => true])->assertCreated()
            ->assertJsonPath('message', 'Propuesta de agenda guardada y enviada al comprador.')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.enviada', true);
        $this->assertSinClavesProhibidas($r->json());
        Mail::assertQueued(PlantillaCorreoMail::class, fn ($m) => $m->hasTo($c->email));
        $id = $r->json('data.id');

        $this->postJson('/api/v1/admin/agenda', ['user_id' => $c->id, 'citas' => [$slot]])->assertStatus(422)
            ->assertJsonPath('message', 'Ya existe una propuesta pendiente para este comprador.');

        $this->assertSame(1, $this->getJson('/api/v1/admin/agenda?estado=pendiente')->json('meta.total'));
        $this->getJson("/api/v1/admin/agenda/{$id}")->assertOk()->assertJsonPath('meta.citas_confirmadas', []);
        $this->postJson("/api/v1/admin/agenda/{$id}/enviar")->assertOk()->assertJsonPath('message', 'Correo enviado al comprador.');
        Mail::assertQueued(PlantillaCorreoMail::class, 2);

        AgendaPropuesta::find($id)->update(['estado' => 'rechazada']);
        $this->postJson("/api/v1/admin/agenda/{$id}/enviar")->assertStatus(422)->assertJsonPath('message', 'Solo se pueden enviar propuestas pendientes.');
        $this->deleteJson("/api/v1/admin/agenda/{$id}")->assertOk()->assertJsonPath('message', 'Propuesta de agenda eliminada correctamente.');
        $this->assertNull(AgendaPropuesta::find($id));
    }

    /* ─────────────── torre ─────────────── */

    public function test_torre_polling_y_acciones(): void
    {
        $c = $this->crearComprador('aprobado', ['telefono' => '9991112222']);
        $hoy = $this->cita($c, now()->setTime(11, 0), 'confirmada');
        $this->cita($this->crearComprador(), now()->addDay()->setTime(11, 0), 'confirmada');
        // desde es inclusivo (precision de segundos): un cambio en el mismo
        // segundo de la respuesta anterior cuenta como cambio.
        $this->travel(1)->seconds();

        $r = $this->comoAdmin()->getJson('/api/v1/admin/torre')->assertOk();
        $this->assertSame([$hoy->id], collect($r->json('data.citas'))->pluck('id')->all());
        $this->assertSame('9991112222', $r->json('data.citas.0.comprador_tel'));
        $this->assertSame(50, $r->json('data.num_mesas'));
        $this->assertFalse($r->json('meta.sin_cambios'));
        $servidorAt = $r->json('meta.servidor_at');

        $this->travel(5)->seconds();
        $r = $this->getJson('/api/v1/admin/torre?desde=' . urlencode($servidorAt))->assertOk();
        $this->assertTrue($r->json('meta.sin_cambios'));
        $this->assertNull($r->json('data.citas'));

        $this->postJson("/api/v1/admin/torre/citas/{$hoy->id}/llamar")->assertStatus(422)->assertJsonValidationErrors(['mesa']);
        $this->postJson("/api/v1/admin/torre/citas/{$hoy->id}/llamar", ['mesa' => '7'])->assertOk()
            ->assertJsonPath('data.estado_tv', 'llamando')->assertJsonPath('data.mesa', '7');

        $r = $this->getJson('/api/v1/admin/torre?desde=' . urlencode($servidorAt))->assertOk();
        $this->assertFalse($r->json('meta.sin_cambios'));
        $this->assertSame(['7'], $r->json('data.mesas_ocupadas'));
        $this->assertSame(now()->toIso8601String(), $r->json('meta.actualizado_at'));

        $this->postJson("/api/v1/admin/torre/citas/{$hoy->id}/iniciar")->assertOk()->assertJsonPath('data.estado_tv', 'en_curso');
        $this->travel(20)->minutes();
        $this->postJson("/api/v1/admin/torre/citas/{$hoy->id}/finalizar")->assertOk()
            ->assertJsonPath('data.estado', 'completada')->assertJsonPath('data.duracion_real', 20);
        $this->postJson("/api/v1/admin/torre/citas/{$hoy->id}/retrasar", ['minutos' => 7])->assertStatus(422);
        $this->postJson("/api/v1/admin/torre/citas/{$hoy->id}/retrasar", ['minutos' => 5])->assertOk()->assertJsonPath('data.hora_prog', '11:05');
        $this->postJson("/api/v1/admin/torre/citas/{$hoy->id}/mesa", ['mesa' => '9'])->assertOk()->assertJsonPath('data.mesa', '9');
        $this->postJson("/api/v1/admin/torre/citas/{$hoy->id}/repetir")->assertOk()->assertJsonPath('data.estado_tv', 'llamando');
        $this->postJson("/api/v1/admin/torre/citas/{$hoy->id}/ausente")->assertOk()->assertJsonPath('data.estado_tv', 'ausente');

        $this->patchJson('/api/v1/admin/torre/mesas', ['num_mesas' => 0])->assertStatus(422);
        $this->patchJson('/api/v1/admin/torre/mesas', ['num_mesas' => 30])->assertOk()->assertJsonPath('data.num_mesas', 30);

        $otro = Evento::create(['nombre' => 'Otro', 'fecha_inicio' => now()->toDateString(), 'activa' => true]);
        $this->assertSame([], $this->getJson("/api/v1/admin/torre?evento_id={$otro->id}")->json('data.citas'));
    }

    /* ─────────────── calendario ─────────────── */

    public function test_calendario(): void
    {
        $c = $this->crearComprador();
        $this->cita($c, now()->addDay()->setTime(10, 0), 'confirmada');

        $this->comoAdmin()->getJson('/api/v1/admin/calendario')->assertStatus(422)->assertJsonValidationErrors(['start', 'end']);
        $this->getJson('/api/v1/admin/calendario?start=2026-01-01&end=2026-12-31')->assertStatus(422)->assertJsonValidationErrors(['end']);

        $r = $this->getJson('/api/v1/admin/calendario?start=2026-10-07&end=2026-10-09')->assertOk();
        $this->assertSame(1, $r->json('meta.total'));
        $this->assertSame('#22c55e', $r->json('data.0.color'));
        $this->assertSame('Negocio Test', $r->json('data.0.extendedProps.restaurantero'));
        $this->assertSame([$this->restaurantero->id], collect($this->getJson('/api/v1/admin/calendario/proveedores')->json('data'))->pluck('id')->all());
    }
}
