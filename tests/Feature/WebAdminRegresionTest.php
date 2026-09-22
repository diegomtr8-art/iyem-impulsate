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
use App\Models\AgendaPropuestaCita;
use App\Models\Cita;
use App\Models\Evento;
use App\Models\EventoCriterio;
use App\Models\EventoEvaluacion;
use App\Models\Notificacion;
use App\Models\PlantillaCorreo;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/**
 * Regresion de los controladores web del ADMIN cuya logica se movio a
 * servicios en la Fase 4A de la API (dashboard, eventos, solicitudes, bazar,
 * proveedores, usuarios, gestion de usuarios, citas, agenda, calendario y
 * torre de control). Se escribio y paso contra el codigo ANTERIOR al refactor.
 */
class WebAdminRegresionTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Miercoles a media manana: "hoy" no depende de la hora a la que corra la suite.
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00'));
        // Destacado: es el principal de Evento::activo() aunque exista la
        // "Edicion Inicial" que siembra la migracion.
        $this->crearEscenario(['destacado' => true]);
        $this->admin = $this->crearAdmin();
    }

    /* ─────────────── helpers ─────────────── */

    private function props($response): array
    {
        // Ida y vuelta por JSON: las filas de DB::table() llegan como stdClass.
        return json_decode(json_encode($response->assertOk()->viewData('page')['props']), true);
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
            'evento_id'  => ($evento ?? $this->evento)->id,
            'user_id'    => $u->id,
            'tipo'       => $tipo,
            'estado'     => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ], $extra));
    }

    private function estadoRegistro(User $u, string $tipo, ?Evento $evento = null): ?object
    {
        return DB::table('evento_usuario')
            ->where('evento_id', ($evento ?? $this->evento)->id)
            ->where('user_id', $u->id)
            ->where('tipo', $tipo)
            ->first();
    }

    private function datosEvento(array $extra = []): array
    {
        return array_merge([
            'nombre'            => 'Evento Nuevo',
            'convocatoria_url'  => 'https://ejemplo.com/convocatoria.pdf',
            'tipo_evento'       => 'encuentro_negocios',
            'fecha_hora_inicio' => '2026-11-10 09:00:00',
            'fecha_hora_fin'    => '2026-11-10 18:00:00',
        ], $extra);
    }

    private function bazar(array $extra = []): Evento
    {
        return Evento::create(array_merge([
            'nombre'                   => 'Bazar Test',
            'fecha_inicio'             => now()->addWeek()->toDateString(),
            'tipo_evento'              => 'bazar_exposicion',
            'activa'                   => true,
            'max_espacios'             => 2,
            'con_criterios_evaluacion' => true,
            'fecha_hora_inicio'        => now()->addWeek(),
            'fecha_hora_fin'           => now()->addWeek()->addHours(8),
        ], $extra));
    }

    private function expositor(Evento $bazar, string $estado = 'pendiente', array $extra = []): User
    {
        $u = User::factory()->create(['nombre_empresa' => 'Expo ' . uniqid()]);
        $u->assignRole('cliente');
        $this->registro($u, 'expositor', $estado, $bazar, $extra);

        return $u;
    }

    private function plantilla(string $clave): PlantillaCorreo
    {
        return PlantillaCorreo::create([
            'clave' => $clave, 'nombre' => $clave, 'asunto' => 'Asunto {{nombre_evento}}',
            'contenido' => 'Hola {{nombre_usuario}}', 'tipo_destinatario' => 'ambos',
            'es_sistema' => true, 'activo' => true,
        ]);
    }

    private function superAdmin(): User
    {
        $u = $this->crearAdmin();
        $u->assignRole('super-admin');

        return $u;
    }

    /* ─────────────── acceso ─────────────── */

    public function test_un_comprador_no_entra_al_panel_admin(): void
    {
        $this->actingAs($this->crearComprador())->get('/admin')->assertForbidden();
    }

    /* ─────────────── dashboard ─────────────── */

    public function test_dashboard_kpis(): void
    {
        $c = $this->crearComprador();
        $this->cita($c, now()->setTime(12, 0), 'confirmada');
        $this->cita($c, now()->subDays(2)->setTime(12, 0), 'pendiente');
        $this->cita($c, now()->subDays(20)->setTime(12, 0), 'cancelada');

        $p = $this->props($this->actingAs($this->admin)->get('/admin'));

        $this->assertSame(User::count(), $p['stats']['totalUsuarios']);
        $this->assertSame(1, $p['stats']['totalRestauranteros']);
        $this->assertSame(3, $p['stats']['totalCitas']);
        $this->assertSame(1, $p['stats']['citasHoy']);
        $this->assertEquals(['cancelada' => 1, 'confirmada' => 1, 'pendiente' => 1], collect($p['citasPorEstado'])->sortKeys()->all());
        $this->assertSame(['2026-10-05', '2026-10-07'], collect($p['citasUltimos7Dias'])->pluck('fecha')->all());
        $this->assertSame([1, 1], collect($p['citasUltimos7Dias'])->pluck('total')->map(fn ($t) => (int) $t)->all());
    }

    /* ─────────────── eventos ─────────────── */

    public function test_eventos_index(): void
    {
        $c = $this->crearComprador();
        $this->crearComprador('pendiente');
        $this->cita($c, now()->addDay()->setTime(10, 0));
        EventoCriterio::create(['evento_id' => $this->evento->id, 'nombre' => 'Calidad', 'porcentaje' => 100, 'orden' => 0]);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/eventos'));

        $e = collect($p['eventos'])->firstWhere('id', $this->evento->id);
        $this->assertSame(1, $e['citas_count']);
        $this->assertSame(1, $e['restauranteros_count']);
        $this->assertSame(1, $e['usuarios_count']);
        $this->assertSame(1, $e['solicitudes_pendientes']);
        $this->assertNull($e['imagen_url']);
        $this->assertSame('Calidad', $e['criterios'][0]['nombre']);
        $this->assertArrayHasKey('categorias', $p);
    }

    public function test_crear_evento_validacion_y_suma_de_criterios(): void
    {
        $this->actingAs($this->admin)->post('/admin/eventos', $this->datosEvento(['convocatoria_url' => '']))
            ->assertSessionHasErrors(['convocatoria_url' => 'La liga de la convocatoria es obligatoria.']);

        $this->actingAs($this->admin)->post('/admin/eventos', $this->datosEvento([
            'con_criterios_evaluacion' => true,
            'criterios' => [['nombre' => 'A', 'porcentaje' => 50], ['nombre' => 'B', 'porcentaje' => 30]],
        ]))->assertSessionHasErrors(['criterios' => 'Los porcentajes deben sumar exactamente 100%.']);

        $this->assertSame(0, Evento::where('nombre', 'Evento Nuevo')->count());
    }

    public function test_crear_evento_con_imagen_criterios_y_destacado(): void
    {
        Storage::fake('public');
        $this->evento->update(['destacado' => true]);

        $this->actingAs($this->admin)->post('/admin/eventos', $this->datosEvento([
            'tipo_evento'              => 'bazar_exposicion',
            'max_espacios'             => 40,
            'destacado'                => true,
            'imagen'                   => UploadedFile::fake()->image('e.jpg'),
            'imagen_carrusel'          => UploadedFile::fake()->image('c.png'),
            'con_criterios_evaluacion' => true,
            'criterios'                => [['nombre' => 'Calidad', 'porcentaje' => 60], ['nombre' => 'Precio', 'porcentaje' => 40]],
        ]))->assertRedirect()->assertSessionHas('success', 'Evento creado. Actívalo cuando estés listo.');

        $e = Evento::where('nombre', 'Evento Nuevo')->firstOrFail();
        $this->assertFalse($e->activa);
        $this->assertTrue($e->destacado);
        $this->assertFalse($this->evento->fresh()->destacado);
        $this->assertSame(40, (int) $e->max_espacios);
        $this->assertSame('2026-11-10', $e->fecha_inicio->toDateString());
        $this->assertSame(3, (int) $e->max_citas_por_comprador);
        $this->assertSame(30, (int) $e->tiempo_entre_citas_minutos);
        Storage::disk('public')->assertExists($e->imagen);
        Storage::disk('public')->assertExists($e->imagen_carrusel);
        $this->assertSame(['Calidad', 'Precio'], $e->criterios()->pluck('nombre')->all());
    }

    public function test_encuentro_ignora_max_espacios(): void
    {
        $this->actingAs($this->admin)->post('/admin/eventos', $this->datosEvento(['max_espacios' => 12]))->assertRedirect();
        $this->assertNull(Evento::where('nombre', 'Evento Nuevo')->value('max_espacios'));
    }

    public function test_editar_evento_conserva_criterios_y_evaluaciones(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('eventos/vieja.jpg', 'x');
        $this->evento->update(['imagen' => 'eventos/vieja.jpg', 'con_criterios_evaluacion' => true]);
        $cal = EventoCriterio::create(['evento_id' => $this->evento->id, 'nombre' => 'Calidad', 'porcentaje' => 50, 'orden' => 0]);
        $pre = EventoCriterio::create(['evento_id' => $this->evento->id, 'nombre' => 'Precio', 'porcentaje' => 50, 'orden' => 1]);
        $u = $this->crearComprador();
        EventoEvaluacion::create(['evento_id' => $this->evento->id, 'user_id' => $u->id, 'criterio_id' => $cal->id, 'puntaje' => 80]);

        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}", $this->datosEvento([
            'nombre'                   => 'Encuentro Editado',
            'imagen'                   => UploadedFile::fake()->image('n.jpg'),
            'con_criterios_evaluacion' => true,
            'criterios'                => [['id' => $cal->id, 'nombre' => 'Calidad total', 'porcentaje' => 70], ['nombre' => 'Servicio', 'porcentaje' => 30]],
        ]))->assertSessionHas('success', 'Evento actualizado correctamente.');

        $e = $this->evento->fresh();
        $this->assertSame('Encuentro Editado', $e->nombre);
        Storage::disk('public')->assertMissing('eventos/vieja.jpg');
        Storage::disk('public')->assertExists($e->imagen);
        $this->assertSame('Calidad total', $cal->fresh()->nombre);
        $this->assertNull($pre->fresh());
        $this->assertSame(1, EventoEvaluacion::where('criterio_id', $cal->id)->count());
        $this->assertSame(['Calidad total', 'Servicio'], $e->criterios()->pluck('nombre')->all());
    }

    public function test_activar_archivar_y_eliminar_evento(): void
    {
        $inactivo = Evento::create(['nombre' => 'Inactivo', 'fecha_inicio' => now()->toDateString(), 'activa' => false, 'tiempo_entre_citas_minutos' => 20]);
        $u = $this->restaurantero->user;
        $this->registro($u, 'proveedor', 'aprobado', $inactivo);

        $this->actingAs($this->admin)->post("/admin/eventos/{$inactivo->id}/activar")
            ->assertSessionHas('success', 'Evento "Inactivo" activado.');
        $this->assertTrue($inactivo->fresh()->activa);
        $this->assertSame(20, (int) $this->servicio->fresh()->duracion_minutos);

        $this->actingAs($this->admin)->withSession(['admin_evento_id' => $inactivo->id])
            ->post("/admin/eventos/{$inactivo->id}/archivar")
            ->assertSessionHas('success', 'Evento "Inactivo" archivado correctamente.')
            ->assertSessionMissing('admin_evento_id');
        $this->assertFalse($inactivo->fresh()->activa);
        $this->assertSame('2026-10-07', $inactivo->fresh()->fecha_corte->toDateString());

        $this->actingAs($this->admin)->post("/admin/eventos/{$inactivo->id}/archivar")
            ->assertSessionHasErrors(['error' => 'Este evento no está activo.']);

        $this->actingAs($this->admin)->delete("/admin/eventos/{$this->evento->id}")
            ->assertSessionHasErrors(['error' => 'No puedes eliminar el evento activo. Archívalo primero.']);

        $conCitas = Evento::create(['nombre' => 'Con citas', 'fecha_inicio' => now()->toDateString(), 'activa' => false]);
        $this->cita($this->crearComprador(), now()->addDay(), 'pendiente', null, ['edicion_id' => $conCitas->id]);
        $this->actingAs($this->admin)->delete("/admin/eventos/{$conCitas->id}")
            ->assertSessionHasErrors(['error' => 'No puedes eliminar un evento que tiene citas registradas.']);

        $this->actingAs($this->admin)->delete("/admin/eventos/{$inactivo->id}")
            ->assertSessionHas('success', 'Evento eliminado correctamente.');
        $this->assertNull($inactivo->fresh());
    }

    public function test_contexto_del_admin(): void
    {
        $inactivo = Evento::create(['nombre' => 'Inactivo', 'fecha_inicio' => now()->toDateString(), 'activa' => false]);

        $this->actingAs($this->admin)->post('/admin/eventos/contexto', ['evento_id' => $inactivo->id])
            ->assertSessionHasErrors(['evento_id' => 'Ese evento ya no está activo.']);

        $this->actingAs($this->admin)->post('/admin/eventos/contexto', ['evento_id' => $this->evento->id])
            ->assertSessionHas('admin_evento_id', $this->evento->id);
    }

    public function test_enviar_encuestas_sin_plantilla(): void
    {
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/enviar-encuestas")
            ->assertSessionHasErrors(['error' => 'No hay plantilla de encuesta activa. Activa una plantilla primero.']);
    }

    /* ─────────────── solicitudes ─────────────── */

    public function test_solicitudes_index(): void
    {
        $pend = $this->crearComprador('pendiente', ['telefono' => '999', 'camara_asociacion' => 'CANIRAC']);
        $pend->forceFill(['ine_path' => 'documentos/x/ine.pdf'])->save();
        $this->registro($this->restaurantero->user, 'proveedor', 'aprobado');
        $rech = $this->crearComprador('rechazado');
        $this->cita($pend, now()->addDay()->setTime(10, 0));
        $sinRegistro = $this->crearProveedor('Sin registro');

        $p = $this->props($this->actingAs($this->admin)->get("/admin/eventos/{$this->evento->id}/solicitudes"));

        $this->assertSame(['id' => $this->evento->id, 'nombre' => 'Encuentro Test', 'activa' => true, 'tipo_evento' => 'encuentro_negocios'], $p['evento']);
        $this->assertCount(1, $p['pendientes']);
        $s = $p['pendientes'][0];
        $this->assertSame(['id', 'user_id', 'tipo', 'estado', 'motivo_rechazo', 'respondido_at', 'created_at', 'user', 'restaurantero', 'citas_activas'], array_keys($s));
        $this->assertSame($pend->id, $s['user']['id']);
        $this->assertTrue($s['user']['tiene_ine']);
        $this->assertFalse($s['user']['tiene_csf']);
        $this->assertArrayNotHasKey('ine_path', $s['user']);
        $this->assertSame(1, $s['user']['citas_historico']);
        $this->assertSame(1, $s['citas_activas']);
        $this->assertNull($s['restaurantero']);

        $prov = collect($p['aprobados'])->firstWhere('tipo', 'proveedor');
        $this->assertSame('Negocio Test', $prov['restaurantero']['nombre_restaurante']);
        $this->assertSame(1, $prov['restaurantero']['citas_count']);
        $this->assertCount(1, $p['rechazados']);
        $this->assertSame($rech->id, $p['rechazados'][0]['user_id']);

        $this->assertContains($sinRegistro->id, collect($p['todosProveedores'])->pluck('id'));
        $this->assertNotContains($this->restaurantero->id, collect($p['todosProveedores'])->pluck('id'));
        $this->assertNotContains($this->admin->id, collect($p['todosCompradores'])->pluck('id'));
        $this->assertNotContains($pend->id, collect($p['todosCompradores'])->pluck('id'));
    }

    public function test_aprobar_solicitud_de_comprador_y_proveedor(): void
    {
        $c = $this->crearComprador('pendiente');
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}/aprobar", ['tipo' => 'comprador'])
            ->assertSessionHas('success', 'Solicitud aprobada.');
        $this->assertSame('aprobado', $this->estadoRegistro($c, 'comprador')->estado);
        $this->assertNotNull($this->estadoRegistro($c, 'comprador')->respondido_at);
        $this->assertSame('¡Fuiste aprobado en el evento "Encuentro Test"! Ya puedes agendar citas con los proveedores.',
            Notificacion::where('user_id', $c->id)->value('mensaje'));
        Mail::assertQueued(EventoSolicitudAprobadaMail::class, fn ($m) => $m->hasTo($c->email));

        $otro = Evento::create(['nombre' => 'Otro', 'fecha_inicio' => now()->toDateString(), 'activa' => true]);
        $prov = $this->crearProveedor('Proveedor Dos');
        $this->registro($prov->user, 'proveedor', 'pendiente', $otro);
        $this->actingAs($this->admin)->post("/admin/eventos/{$otro->id}/solicitudes/{$prov->user_id}/aprobar", ['tipo' => 'proveedor'])
            ->assertSessionHas('success');
        $this->assertSame($otro->id, $prov->fresh()->edicion_id);
        $this->assertStringContainsString('como proveedor', Notificacion::where('user_id', $prov->user_id)->value('mensaje'));
    }

    public function test_aprobar_valida_tipo_y_existencia(): void
    {
        $c = $this->crearComprador(null);
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}/aprobar", ['tipo' => 'otro'])
            ->assertSessionHasErrors('tipo');
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}/aprobar", ['tipo' => 'comprador'])
            ->assertSessionHasErrors(['error' => 'Solicitud no encontrada.']);
        Mail::assertNothingQueued();
    }

    public function test_rechazar_solicitud(): void
    {
        $c = $this->crearComprador('pendiente');
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}/rechazar", ['tipo' => 'comprador'])
            ->assertSessionHasErrors('motivo_rechazo');

        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}/rechazar", ['tipo' => 'comprador', 'motivo_rechazo' => 'Cupo lleno'])
            ->assertSessionHas('success', 'Solicitud rechazada.');
        $r = $this->estadoRegistro($c, 'comprador');
        $this->assertSame('rechazado', $r->estado);
        $this->assertSame('Cupo lleno', $r->motivo_rechazo);
        $this->assertSame('Tu solicitud para el evento "Encuentro Test" fue rechazada. Motivo: Cupo lleno',
            Notificacion::where('user_id', $c->id)->value('mensaje'));
        Mail::assertQueued(EventoSolicitudRechazadaMail::class, fn ($m) => $m->hasTo($c->email));

        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}/revertir", ['tipo' => 'comprador'])
            ->assertSessionHas('success', 'Solicitud regresada a pendiente.');
        $r = $this->estadoRegistro($c, 'comprador');
        $this->assertSame('pendiente', $r->estado);
        $this->assertNull($r->motivo_rechazo);
    }

    public function test_aprobar_todos(): void
    {
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/aprobar-todos", ['tipo' => 'comprador'])
            ->assertSessionHas('success', 'No hay solicitudes pendientes de ese tipo.');

        $a = $this->crearComprador('pendiente');
        $b = $this->crearComprador('pendiente');
        $this->crearComprador('rechazado');

        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/aprobar-todos", ['tipo' => 'comprador'])
            ->assertSessionHas('success', '2 solicitudes aprobadas.');
        $this->assertSame('aprobado', $this->estadoRegistro($a, 'comprador')->estado);
        $this->assertSame('aprobado', $this->estadoRegistro($b, 'comprador')->estado);
        Mail::assertQueued(EventoSolicitudAprobadaMail::class, 2);
        $this->assertSame(2, Notificacion::where('tipo', 'solicitud_aprobada')->count());
    }

    public function test_agregar_proveedor_y_comprador_manual(): void
    {
        $prov = $this->crearProveedor('Manual');
        $prov->update(['edicion_id' => null]);
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/agregar-proveedor", ['restaurantero_id' => $prov->id])
            ->assertSessionHas('success', 'Proveedor "Manual" agregado al evento correctamente.');
        $this->assertSame('aprobado', $this->estadoRegistro($prov->user, 'proveedor')->estado);
        $this->assertSame($this->evento->id, $prov->fresh()->edicion_id);
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/agregar-proveedor", ['restaurantero_id' => $prov->id])
            ->assertSessionHasErrors(['error' => 'Este proveedor ya está registrado en el evento.']);

        $c = $this->crearComprador(null, ['nombre_empresa' => 'Hotel X']);
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/agregar-comprador", ['user_id' => $c->id])
            ->assertSessionHas('success', 'Comprador "Hotel X" agregado al evento correctamente.');
        $this->assertSame('aprobado', $this->estadoRegistro($c, 'comprador')->estado);
        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/agregar-comprador", ['user_id' => $c->id])
            ->assertSessionHasErrors(['error' => 'Este comprador ya está registrado en el evento.']);

        Mail::assertQueued(EventoSolicitudAprobadaMail::class, 2);
        $this->assertSame('Agregado al evento', Notificacion::where('user_id', $c->id)->value('titulo'));
    }

    public function test_eliminar_del_evento_cancela_citas(): void
    {
        $c = $this->crearComprador('aprobado');
        $viva = $this->cita($c, now()->addDay()->setTime(10, 0), 'confirmada');
        $hecha = $this->cita($c, now()->addDay()->setTime(11, 0), 'completada', $this->crearProveedor('Otro'));

        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}/eliminar", ['tipo' => 'comprador'])
            ->assertSessionHasErrors('motivo_eliminacion');

        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}/eliminar", ['tipo' => 'comprador', 'motivo_eliminacion' => 'Duplicado'])
            ->assertSessionHas('success', 'Usuario eliminado del evento.');
        $this->assertNull($this->estadoRegistro($c, 'comprador'));
        $this->assertSame('cancelada', $viva->fresh()->estado);
        $this->assertSame('completada', $hecha->fresh()->estado);
        $this->assertSame('Eliminado del evento', Notificacion::where('user_id', $c->id)->value('titulo'));

        $this->actingAs($this->admin)->post("/admin/eventos/{$this->evento->id}/solicitudes/{$c->id}/eliminar", ['tipo' => 'comprador', 'motivo_eliminacion' => 'x'])
            ->assertSessionHasErrors(['error' => 'Registro no encontrado o no está aprobado.']);
    }

    /* ─────────────── bazar ─────────────── */

    public function test_bazar_index_y_404_en_encuentro(): void
    {
        $this->actingAs($this->admin)->get("/admin/eventos/{$this->evento->id}/bazar")->assertNotFound();

        $bazar = $this->bazar();
        $crit = EventoCriterio::create(['evento_id' => $bazar->id, 'nombre' => 'Calidad', 'porcentaje' => 100, 'orden' => 0]);
        $pend = $this->expositor($bazar);
        $pend->forceFill(['ine_path' => 'documentos/x/ine.pdf', 'csf_fecha' => now()->subMonth()->toDateString()])->save();
        $apro = $this->expositor($bazar, 'aprobado', ['seleccionado' => true, 'puntaje_total' => 90]);
        EventoEvaluacion::create(['evento_id' => $bazar->id, 'user_id' => $apro->id, 'criterio_id' => $crit->id, 'puntaje' => 90]);
        $this->expositor($bazar, 'rechazado');

        $p = $this->props($this->actingAs($this->admin)->get("/admin/eventos/{$bazar->id}/bazar"));

        $this->assertSame(['total' => 3, 'pendientes' => 1, 'en_evaluacion' => 1, 'seleccionados' => 1, 'no_seleccionados' => 0,
            'rechazados' => 1, 'max_espacios' => 2, 'disponibles' => 1], $p['kpis']);
        $s = collect($p['solicitudes'])->firstWhere('user_id', $pend->id);
        $this->assertTrue($s['tiene_ine']);
        $this->assertStringContainsString("/documentos/{$pend->id}/ine", $s['ine_url']);
        $this->assertArrayNotHasKey('ine_path', $s);
        $this->assertTrue($s['csf_vigente']);
        $this->assertFalse($s['evaluado']);
        $a = collect($p['solicitudes'])->firstWhere('user_id', $apro->id);
        $this->assertTrue($a['evaluado']);
        $this->assertEquals(90, $a['evaluaciones'][0]['puntaje']);
        $this->assertSame('pendiente', $p['solicitudes'][0]['estado']);
        $this->assertSame(['id', 'nombre', 'max_espacios', 'con_criterios_evaluacion'], array_keys($p['evento']));
    }

    public function test_bazar_aprobar_rechazar_evaluar_y_notas(): void
    {
        $bazar = $this->bazar();
        $c1 = EventoCriterio::create(['evento_id' => $bazar->id, 'nombre' => 'Calidad', 'porcentaje' => 60, 'orden' => 0]);
        $c2 = EventoCriterio::create(['evento_id' => $bazar->id, 'nombre' => 'Precio', 'porcentaje' => 40, 'orden' => 1]);
        $ajeno = EventoCriterio::create(['evento_id' => $this->evento->id, 'nombre' => 'Ajeno', 'porcentaje' => 100, 'orden' => 0]);
        $u = $this->expositor($bazar);
        $r = $this->expositor($bazar);

        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/aprobar/{$u->id}")
            ->assertSessionHas('success', 'Solicitud aprobada para evaluación.');
        $this->assertSame('aprobado', $this->estadoRegistro($u, 'expositor', $bazar)->estado);
        $this->assertSame('Solicitud en revisión', Notificacion::where('user_id', $u->id)->value('titulo'));

        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/rechazar/{$r->id}")->assertSessionHasErrors('motivo_rechazo');
        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/rechazar/{$r->id}", ['motivo_rechazo' => 'Sin CSF'])
            ->assertSessionHas('success', 'Solicitud rechazada.');
        $this->assertSame('Sin CSF', $this->estadoRegistro($r, 'expositor', $bazar)->motivo_rechazo);

        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/evaluar/{$u->id}", [
            'evaluaciones' => [['criterio_id' => $ajeno->id, 'puntaje' => 100]],
        ])->assertSessionHasErrors('evaluaciones.0.criterio_id');

        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/evaluar/{$u->id}", [
            'evaluaciones' => [['criterio_id' => $c1->id, 'puntaje' => 80], ['criterio_id' => $c2->id, 'puntaje' => 50]],
        ])->assertSessionHas('success', 'Evaluación guardada.');
        $this->assertEquals(68.0, (float) $this->estadoRegistro($u, 'expositor', $bazar)->puntaje_total);
        $this->assertSame(2, EventoEvaluacion::where('evento_id', $bazar->id)->where('user_id', $u->id)->count());

        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/notas-rechazo/{$u->id}", ['notas_rechazo' => 'Mejorar empaque'])
            ->assertSessionHas('success', 'Notas guardadas.');
        $this->assertSame('Mejorar empaque', $this->estadoRegistro($u, 'expositor', $bazar)->notas_rechazo);
    }

    public function test_bazar_seleccion_con_limite(): void
    {
        $bazar = $this->bazar(['max_espacios' => 1]);
        $pend = $this->expositor($bazar);
        $a = $this->expositor($bazar, 'aprobado');
        $b = $this->expositor($bazar, 'aprobado');

        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/toggle-seleccion", ['user_id' => $pend->id, 'seleccionado' => true])
            ->assertSessionHasErrors(['error' => 'Solo puedes seleccionar participantes que ya pasaron la revision inicial.']);
        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/toggle-seleccion", ['user_id' => $this->admin->id, 'seleccionado' => true])
            ->assertSessionHasErrors(['error' => 'Ese participante no tiene una solicitud de expositor en este evento.']);
        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/toggle-seleccion", ['user_id' => $a->id, 'seleccionado' => true])
            ->assertSessionHas('success', 'Participante seleccionado.');
        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/toggle-seleccion", ['user_id' => $b->id, 'seleccionado' => true])
            ->assertSessionHasErrors(['error' => 'Ya alcanzaste el límite de 1 espacios.']);
        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/toggle-seleccion", ['user_id' => $a->id, 'seleccionado' => false])
            ->assertSessionHas('success', 'Participante deseleccionado.');
        $this->assertSame(0, (int) $this->estadoRegistro($a, 'expositor', $bazar)->seleccionado);
    }

    public function test_bazar_envio_de_correos(): void
    {
        $bazar = $this->bazar();
        $sel = $this->expositor($bazar, 'aprobado', ['seleccionado' => true]);
        $no = $this->expositor($bazar, 'aprobado');

        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/enviar-aprobacion")
            ->assertSessionHasErrors(['error' => 'Plantilla "bazar_seleccionado" no encontrada.']);
        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/enviar-rechazo")
            ->assertSessionHasErrors(['error' => 'Plantilla "bazar_rechazado" no encontrada.']);

        $this->plantilla('bazar_seleccionado');
        $this->plantilla('bazar_rechazado');

        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/enviar-aprobacion")
            ->assertSessionHas('success', 'Correo de aprobación enviado a 1 participante(s).');
        $this->assertSame(1, (int) $this->estadoRegistro($sel, 'expositor', $bazar)->correo_aprobacion_enviado);
        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/enviar-aprobacion")
            ->assertSessionHasErrors(['error' => 'No hay participantes seleccionados pendientes de notificar.']);

        $this->actingAs($this->admin)->post("/admin/eventos/{$bazar->id}/bazar/enviar-rechazo")
            ->assertSessionHas('success', 'Correo de resultado enviado a 1 participante(s).');
        $reg = $this->estadoRegistro($no, 'expositor', $bazar);
        $this->assertSame(1, (int) $reg->correo_rechazo_enviado);
        $this->assertSame(48, strlen($reg->token_evaluacion));

        Mail::assertQueued(PlantillaCorreoMail::class, 2);
        $this->assertSame('Seleccionado en el bazar', Notificacion::where('user_id', $sel->id)->value('titulo'));
        $this->assertSame('Resultado del bazar', Notificacion::where('user_id', $no->id)->value('titulo'));
    }

    /* ─────────────── proveedores ─────────────── */

    public function test_proveedores_index_y_busqueda(): void
    {
        $pend = $this->crearProveedor('Tortilleria Pendiente');
        $pend->forceFill(['aprobado' => false, 'rechazado' => false, 'solicitado_aprobacion_at' => now()])->saveQuietly();
        $this->restaurantero->forceFill(['aprobado' => true])->saveQuietly();

        $p = $this->props($this->actingAs($this->admin)->get('/admin/restauranteros'));
        $this->assertSame(2, $p['restauranteros']['total']);
        $this->assertSame(1, $p['pendientesAprobacion']);
        $this->assertSame([$pend->id], collect($p['proveedoresPendientes'])->pluck('id')->all());
        $this->assertArrayHasKey('citas_count', $p['restauranteros']['data'][0]);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/restauranteros?search=Tortill'));
        $this->assertSame([$pend->id], collect($p['restauranteros']['data'])->pluck('id')->all());
        $this->assertSame(['search' => 'Tortill'], $p['filters']);
    }

    public function test_proveedor_show(): void
    {
        $c = $this->crearComprador();
        $this->cita($c, now()->addDay()->setTime(10, 0), 'confirmada');
        $this->cita($c, now()->addDay()->setTime(11, 0), 'pendiente');
        $this->registro($this->restaurantero->user, 'proveedor', 'aprobado');

        $p = $this->props($this->actingAs($this->admin)->get("/admin/restauranteros/{$this->restaurantero->id}"));
        $this->assertSame(['total' => 2, 'aceptadas' => 1, 'pendientes' => 1], $p['stats']);
        $this->assertSame(2, $p['citas']['total']);
        $this->assertCount(2, $p['citasCalendario']);
        $this->assertSame('#22c55e', collect($p['citasCalendario'])->firstWhere('extendedProps.estado', 'confirmada')['color']);
        $this->assertSame($this->evento->id, $p['eventosParticipados'][0]['id']);
        $this->assertSame('aprobado', $p['eventosParticipados'][0]['estado']);
    }

    public function test_proveedor_update_toggle_y_categoria(): void
    {
        $this->actingAs($this->admin)->post("/admin/restauranteros/{$this->restaurantero->id}/update", ['nombre_restaurante' => ''])
            ->assertSessionHasErrors('nombre_restaurante');

        $this->actingAs($this->admin)->post("/admin/restauranteros/{$this->restaurantero->id}/update", [
            'nombre_restaurante' => 'Negocio Editado',
            'telefono'           => '9990000000',
            'cobertura_entrega'  => 'regional',
            'user_name'          => 'Nuevo Contacto',
            'user_email'         => 'nuevo.contacto@example.com',
        ])->assertSessionHas('success', 'Perfil actualizado correctamente.');
        $r = $this->restaurantero->fresh();
        $this->assertSame('Negocio Editado', $r->nombre_restaurante);
        $this->assertSame('regional', $r->cobertura_entrega);
        $this->assertSame('nuevo.contacto@example.com', $r->user->email);
        $this->assertSame('Nuevo Contacto', $r->user->name);

        $this->actingAs($this->admin)->post("/admin/restauranteros/{$this->restaurantero->id}/update", [
            'nombre_restaurante' => 'Negocio Editado',
            'logo'               => UploadedFile::fake()->image('logo.png'),
        ])->assertSessionHas('success');
        $logo = $this->restaurantero->fresh()->logo_path;
        $this->assertMatchesRegularExpression('#^logos/logo_' . $this->restaurantero->id . '_[a-z0-9]+\.png$#', $logo);
        $this->assertFileExists(storage_path('app/public/' . $logo));
        @unlink(storage_path('app/public/' . $logo));

        $this->actingAs($this->admin)->patch("/admin/restauranteros/{$this->restaurantero->id}/toggle")
            ->assertSessionHas('success', 'Estado actualizado correctamente.');
        $this->assertFalse((bool) $this->restaurantero->fresh()->activo);

        $this->actingAs($this->admin)->patch("/admin/restauranteros/{$this->restaurantero->id}/categoria", ['categoria' => ''])
            ->assertSessionHas('success', 'Categoría actualizada correctamente.');
        $this->assertNull($this->restaurantero->fresh()->categoria);
    }

    public function test_aprobar_y_rechazar_proveedor(): void
    {
        $r = $this->crearProveedor('Pendiente');
        $r->forceFill(['aprobado' => false, 'rechazado' => false, 'activo' => false])->saveQuietly();

        $this->actingAs($this->admin)->post("/admin/restauranteros/{$r->id}/rechazar")->assertSessionHasErrors('motivo');
        $this->actingAs($this->admin)->post("/admin/restauranteros/{$r->id}/rechazar", ['motivo' => 'Falta RFC'])
            ->assertSessionHas('success', 'Perfil rechazado y proveedor notificado.');
        $r->refresh();
        $this->assertTrue((bool) $r->rechazado);
        $this->assertFalse((bool) $r->activo);
        $this->assertSame('Falta RFC', $r->motivo_rechazo);
        Mail::assertQueued(ProveedorRechazado::class, fn ($m) => $m->hasTo($r->user->email));
        $this->assertSame('Resultado de revisión de perfil', Notificacion::where('user_id', $r->user_id)->value('titulo'));

        $this->actingAs($this->admin)->post("/admin/restauranteros/{$r->id}/aprobar")
            ->assertSessionHas('success', 'Proveedor aprobado y notificado.');
        $r->refresh();
        $this->assertTrue((bool) $r->aprobado);
        $this->assertFalse((bool) $r->rechazado);
        $this->assertNull($r->motivo_rechazo);
        $this->assertTrue((bool) $r->activo);
        Mail::assertQueued(ProveedorAprobado::class, fn ($m) => $m->hasTo($r->user->email));
        $this->assertSame(2, Notificacion::where('user_id', $r->user_id)->count());
    }

    /* ─────────────── usuarios (admin) ─────────────── */

    public function test_usuarios_index_show_editar(): void
    {
        $c = $this->crearComprador('aprobado', ['name' => 'Zacarias Buscado']);
        $this->cita($c, now()->addDay()->setTime(10, 0), 'confirmada');

        $p = $this->props($this->actingAs($this->admin)->get('/admin/usuarios'));
        $ids = collect($p['usuarios']['data'])->pluck('id');
        $this->assertNotContains($this->admin->id, $ids);
        $this->assertContains($c->id, $ids);
        $this->assertSame(1, collect($p['usuarios']['data'])->firstWhere('id', $c->id)['citas_count']);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/usuarios?search=Zacarias'));
        $this->assertSame([$c->id], collect($p['usuarios']['data'])->pluck('id')->all());

        $p = $this->props($this->actingAs($this->admin)->get("/admin/clientes/{$c->id}"));
        $this->assertSame(['total' => 1, 'aceptadas' => 1, 'pendientes' => 0], $p['stats']);
        $this->assertSame('comprador', $p['eventosAsistidos'][0]['tipo']);
        $this->assertCount(1, $p['cliente']['citas_como_cliente']);

        $p = $this->props($this->actingAs($this->admin)->get("/admin/clientes/{$c->id}/editar"));
        $this->assertSame(['id', 'name', 'email', 'telefono', 'rfc', 'municipio', 'nombre_empresa', 'sitio_web', 'camara_asociacion', 'nombre_establecimiento'], array_keys($p['cliente']));
    }

    public function test_usuarios_actualizar_y_eliminar(): void
    {
        $c = $this->crearComprador();
        $this->actingAs($this->admin)->put("/admin/clientes/{$c->id}", ['name' => 'X', 'email' => $this->admin->email])
            ->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->put("/admin/clientes/{$c->id}", ['name' => 'Nombre Nuevo', 'email' => 'nuevo@example.com', 'municipio' => 'Mérida'])
            ->assertRedirect("/admin/clientes/{$c->id}/editar")
            ->assertSessionHas('success', 'Comprador actualizado correctamente.');
        $this->assertSame('Nombre Nuevo', $c->fresh()->name);
        $this->assertSame('Mérida', $c->fresh()->municipio);

        $otroAdmin = $this->crearAdmin();
        $this->actingAs($this->admin)->delete("/admin/usuarios/{$otroAdmin->id}")
            ->assertSessionHasErrors(['error' => 'No puedes eliminar un administrador.']);
        $this->actingAs($this->admin)->delete("/admin/usuarios/{$c->id}")
            ->assertSessionHas('success', 'Usuario eliminado correctamente.');
        $this->assertNull($c->fresh());
    }

    /* ─────────────── usuarios-gestion (super-admin) ─────────────── */

    public function test_usuarios_gestion_solo_super_admin(): void
    {
        $this->actingAs($this->admin)->get('/admin/usuarios-gestion')->assertForbidden();
    }

    public function test_usuarios_gestion_index_y_edit(): void
    {
        $sa = $this->superAdmin();
        $canirac = $this->crearComprador(null, ['camara_asociacion' => 'CANIRAC', 'nombre_empresa' => 'Rest CANIRAC']);

        $p = $this->props($this->actingAs($sa)->get('/admin/usuarios-gestion?canirac=si'));
        $this->assertSame([$canirac->id], collect($p['usuarios']['data'])->pluck('id')->all());
        $this->assertSame(['cliente'], $p['usuarios']['data'][0]['roles_nombres']);
        $this->assertContains('super-admin', $p['roles']);

        $p = $this->props($this->actingAs($sa)->get('/admin/usuarios-gestion?rol=restaurantero'));
        $this->assertSame([$this->restaurantero->user_id], collect($p['usuarios']['data'])->pluck('id')->all());

        $p = $this->props($this->actingAs($sa)->get("/admin/usuarios-gestion/{$canirac->id}/edit"));
        $this->assertSame(['cliente'], $p['usuario']['roles']);
        $this->assertSame('Rest CANIRAC', $p['usuario']['nombre_empresa']);
    }

    public function test_usuarios_gestion_update_password_roles_destroy(): void
    {
        $sa = $this->superAdmin();
        $u = $this->crearComprador(null, ['camara_asociacion' => 'CANIRAC', 'nombre_establecimiento' => 'La Casa']);

        $this->actingAs($sa)->put("/admin/usuarios-gestion/{$u->id}", ['name' => 'U', 'email' => $u->email, 'camara_asociacion' => 'Ninguna', 'nombre_establecimiento' => 'La Casa'])
            ->assertSessionHas('success', 'Datos del usuario actualizados correctamente.');
        $this->assertNull($u->fresh()->nombre_establecimiento);

        $this->actingAs($sa)->post("/admin/usuarios-gestion/{$u->id}/password", ['password' => 'NuevaClave1', 'password_confirmation' => 'otra'])
            ->assertSessionHasErrors('password');
        $this->actingAs($sa)->post("/admin/usuarios-gestion/{$u->id}/password", ['password' => 'NuevaClave1', 'password_confirmation' => 'NuevaClave1'])
            ->assertSessionHas('success', 'Contraseña actualizada correctamente.');
        $this->assertTrue(Hash::check('NuevaClave1', $u->fresh()->password));

        $this->actingAs($sa)->post("/admin/usuarios-gestion/{$sa->id}/roles", ['roles' => ['admin']])
            ->assertSessionHasErrors(['roles' => 'No puedes quitarte el rol super-admin a ti mismo.']);
        $this->actingAs($sa)->post("/admin/usuarios-gestion/{$u->id}/roles", ['roles' => ['cliente', 'restaurantero']])
            ->assertSessionHas('success', 'Roles actualizados correctamente.');
        $this->assertEqualsCanonicalizing(['cliente', 'restaurantero'], $u->fresh()->getRoleNames()->all());

        $this->actingAs($sa)->delete("/admin/usuarios-gestion/{$sa->id}")
            ->assertSessionHasErrors(['general' => 'No puedes eliminar tu propia cuenta.']);
        $this->actingAs($sa)->delete("/admin/usuarios-gestion/{$u->id}")
            ->assertRedirect('/admin/usuarios-gestion')
            ->assertSessionHas('success', "Usuario {$u->email} eliminado correctamente.");
        $this->assertNull($u->fresh());
    }

    /* ─────────────── citas ─────────────── */

    public function test_citas_index_con_filtros(): void
    {
        $a = $this->crearComprador('aprobado', ['name' => 'Ana Filtro']);
        $b = $this->crearComprador();
        $this->cita($a, now()->addDay()->setTime(10, 0), 'confirmada');
        $this->cita($b, now()->addDay()->setTime(11, 0), 'pendiente');

        $p = $this->props($this->actingAs($this->admin)->get('/admin/citas?estado=confirmada'));
        $this->assertSame(1, $p['citas']['total']);
        $this->assertSame('confirmada', $p['citas']['data'][0]['estado']);
        $this->assertSame(['estado' => 'confirmada'], $p['filters']);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/citas?search=Ana%20Filtro'));
        $this->assertSame([$a->id], collect($p['citas']['data'])->pluck('cliente_id')->all());
        $this->assertContains($a->id, collect($p['clientes'])->pluck('id'));
        $this->assertSame([$this->restaurantero->id], collect($p['restauranteros'])->pluck('id')->all());
    }

    public function test_crear_cita_desde_admin(): void
    {
        $c = $this->crearComprador();
        $datos = ['cliente_id' => $c->id, 'restaurantero_id' => $this->restaurantero->id, 'fecha' => '2026-10-08', 'hora' => '10:00', 'duracion' => 20, 'notas' => 'Por admin'];

        $this->actingAs($this->admin)->post('/admin/citas', array_merge($datos, ['cliente_id' => $this->admin->id]))
            ->assertSessionHasErrors(['cliente' => 'No se puede agendar una cita para un administrador.']);

        $this->actingAs($this->admin)->post('/admin/citas', $datos)
            ->assertSessionHas('success', 'Cita agendada correctamente para ' . $c->name . '.');
        $cita = Cita::where('cliente_id', $c->id)->firstOrFail();
        $this->assertSame('confirmada', $cita->estado);
        $this->assertSame(20, (int) $cita->inicio->diffInMinutes($cita->fin));
        $this->assertSame('Por admin', $cita->notas);

        $this->actingAs($this->admin)->post('/admin/citas', $datos)->assertSessionHasErrors('fecha');

        Evento::query()->update(['activa' => false]);
        $this->actingAs($this->admin)->post('/admin/citas', $datos)->assertSessionHasErrors(['edicion' => 'No hay un evento activo.']);
    }

    public function test_cambiar_estado_de_cita(): void
    {
        $c = $this->crearComprador();
        $cita = $this->cita($c, now()->addDay()->setTime(10, 0));

        $this->actingAs($this->admin)->patch("/admin/citas/{$cita->id}/estado", ['estado' => 'rara'])->assertSessionHasErrors('estado');
        $this->actingAs($this->admin)->patch("/admin/citas/{$cita->id}/estado", ['estado' => 'confirmada'])
            ->assertSessionHas('success', 'Estado de la cita actualizado.');
        Mail::assertQueued(CitaConfirmada::class, 1);
        $this->actingAs($this->admin)->patch("/admin/citas/{$cita->id}/estado", ['estado' => 'confirmada']);
        Mail::assertQueued(CitaConfirmada::class, 1);
        $this->actingAs($this->admin)->patch("/admin/citas/{$cita->id}/estado", ['estado' => 'cancelada']);
        Mail::assertQueued(CitaCancelada::class, fn ($m) => $m->hasTo($c->email));
        $this->actingAs($this->admin)->patch("/admin/citas/{$cita->id}/estado", ['estado' => 'completada']);
        $this->assertSame('completada', $cita->fresh()->estado);
        Mail::assertQueued(CitaCancelada::class, 1);
    }

    /* ─────────────── agenda propuesta ─────────────── */

    public function test_agenda_crear_datos(): void
    {
        $c = $this->crearComprador('aprobado');
        $this->crearComprador('pendiente');
        $this->registro($this->restaurantero->user, 'proveedor', 'aprobado');

        $p = $this->props($this->actingAs($this->admin)->get('/admin/agenda/crear'));
        $this->assertSame(['id' => $this->evento->id, 'nombre' => 'Encuentro Test'], $p['evento']);
        $this->assertSame([$c->id], collect($p['compradores'])->pluck('id')->all());
        $this->assertSame([$this->restaurantero->id], collect($p['proveedores'])->pluck('id')->all());
        $this->assertCount(48, $p['slots']);
        $this->assertSame('09:00', $p['slots'][0]['label']);
        $this->assertSame('2026-10-08T09:10:00-06:00', $p['slots'][0]['fin']);

        Evento::query()->update(['activa' => false]);
        $this->actingAs($this->admin)->get('/admin/agenda/crear')
            ->assertRedirect('/admin/agenda')->assertSessionHas('error', 'No hay un evento activo para crear agendas.');
    }

    public function test_agenda_store_reglas_y_envio(): void
    {
        $this->plantilla('agenda_propuesta');
        $c = $this->crearComprador('aprobado');
        $otro = $this->crearProveedor('Otro');
        $slot = fn (Restaurantero $r, string $h) => ['restaurantero_id' => $r->id, 'slot_inicio' => "2026-10-08 {$h}:00", 'slot_fin' => "2026-10-08 {$h}:10"];

        $this->actingAs($this->admin)->post('/admin/agenda', ['user_id' => $c->id, 'citas' => [$slot($otro, '10:00'), $slot($otro, '10:20')]])
            ->assertSessionHasErrors(['error' => 'No puedes asignar el mismo proveedor dos veces en la misma propuesta.']);

        $aceptada = AgendaPropuesta::create(['evento_id' => $this->evento->id, 'user_id' => $this->crearComprador()->id, 'admin_id' => $this->admin->id, 'estado' => 'aceptada']);
        AgendaPropuestaCita::create(['agenda_propuesta_id' => $aceptada->id, 'restaurantero_id' => $otro->id, 'slot_inicio' => '2026-10-08 10:00:00', 'slot_fin' => '2026-10-08 10:10:00']);
        $this->actingAs($this->admin)->post('/admin/agenda', ['user_id' => $c->id, 'citas' => [$slot($otro, '10:00')]])
            ->assertSessionHasErrors(['error' => 'El proveedor Otro ya tiene una cita confirmada en ese horario.']);

        $this->actingAs($this->admin)->post('/admin/agenda', ['user_id' => $c->id, 'citas' => [$slot($this->restaurantero, '10:00'), $slot($otro, '11:00')], 'enviar' => true])
            ->assertRedirect('/admin/agenda')
            ->assertSessionHas('success', 'Propuesta de agenda guardada y enviada al comprador.');
        $prop = AgendaPropuesta::where('user_id', $c->id)->firstOrFail();
        $this->assertSame('pendiente', $prop->estado);
        $this->assertSame($this->admin->id, $prop->admin_id);
        $this->assertNotNull($prop->enviada_at);
        $this->assertNotNull($prop->token);
        $this->assertSame(2, $prop->citas()->count());
        Mail::assertQueued(PlantillaCorreoMail::class, fn ($m) => $m->hasTo($c->email));

        $this->actingAs($this->admin)->post('/admin/agenda', ['user_id' => $c->id, 'citas' => [$slot($this->restaurantero, '12:00')]])
            ->assertSessionHasErrors(['error' => 'Ya existe una propuesta pendiente para este comprador.']);
    }

    public function test_agenda_index_show_enviar_y_borrar(): void
    {
        $this->plantilla('agenda_propuesta');
        $c = $this->crearComprador('aprobado');
        $prop = AgendaPropuesta::create(['evento_id' => $this->evento->id, 'user_id' => $c->id, 'admin_id' => $this->admin->id, 'estado' => 'pendiente']);
        AgendaPropuestaCita::create(['agenda_propuesta_id' => $prop->id, 'restaurantero_id' => $this->restaurantero->id, 'slot_inicio' => '2026-10-08 10:00:00', 'slot_fin' => '2026-10-08 10:10:00']);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/agenda'));
        $this->assertSame([$prop->id], collect($p['propuestas']['data'])->pluck('id')->all());
        $this->assertSame('Negocio Test', $p['propuestas']['data'][0]['citas'][0]['proveedor']['nombre_restaurante']);

        $this->actingAs($this->admin)->post("/admin/agenda/{$prop->id}/enviar")->assertSessionHas('success', 'Correo enviado al comprador.');
        $this->assertNotNull($prop->fresh()->enviada_at);
        Mail::assertQueued(PlantillaCorreoMail::class, 1);

        $cita = $this->cita($c, Carbon::parse('2026-10-08 10:00'), 'confirmada');
        $prop->update(['estado' => 'aceptada']);
        $prop->citas()->update(['cita_id' => $cita->id]);
        $this->actingAs($this->admin)->post("/admin/agenda/{$prop->id}/enviar")
            ->assertSessionHasErrors(['error' => 'Solo se pueden enviar propuestas pendientes.']);

        $p = $this->props($this->actingAs($this->admin)->get("/admin/agenda/{$prop->id}"));
        $this->assertSame($prop->id, $p['propuesta']['id']);
        $this->assertSame([$cita->id], collect($p['citasConfirmadas'])->pluck('id')->all());

        $this->actingAs($this->admin)->delete("/admin/agenda/{$prop->id}")
            ->assertRedirect('/admin/agenda')
            ->assertSessionHas('success', 'Propuesta eliminada. Se cancelaron 1 cita(s) generadas por ella.');
        $this->assertSame('cancelada', $cita->fresh()->estado);
        $this->assertNull($prop->fresh());
        $this->assertSame('Cita cancelada', Notificacion::where('user_id', $this->restaurantero->user_id)->value('titulo'));
    }

    /* ─────────────── calendario ─────────────── */

    public function test_calendario(): void
    {
        $c = $this->crearComprador();
        $otro = $this->crearProveedor('Otro');
        $this->cita($c, now()->addDay()->setTime(10, 0), 'confirmada');
        $this->cita($c, now()->addDays(10)->setTime(10, 0), 'pendiente', $otro);

        $p = $this->props($this->actingAs($this->admin)->get('/admin/calendario'));
        $this->assertSame(['Negocio Test', 'Otro'], collect($p['restauranteros'])->pluck('nombre_restaurante')->all());

        $j = $this->actingAs($this->admin)->getJson('/admin/calendario/eventos?start=2026-10-07&end=2026-10-09')->assertOk()->json();
        $this->assertCount(1, $j);
        $this->assertSame('#22c55e', $j[0]['color']);
        $this->assertSame('Negocio Test', $j[0]['extendedProps']['restaurantero']);
        $this->assertSame($c->email, $j[0]['extendedProps']['clienteEmail']);

        $j = $this->actingAs($this->admin)->getJson("/admin/calendario/eventos?restaurantero_id={$otro->id}")->json();
        $this->assertCount(1, $j);
        $this->assertSame('#f59e0b', $j[0]['color']);
    }

    /* ─────────────── torre de control ─────────────── */

    public function test_torre_estado_y_acciones(): void
    {
        $c = $this->crearComprador('aprobado', ['telefono' => '9991112222']);
        $hoy = $this->cita($c, now()->setTime(11, 0), 'confirmada');
        $this->cita($this->crearComprador(), now()->setTime(12, 0), 'cancelada');
        $this->cita($this->crearComprador(), now()->addDay()->setTime(12, 0), 'confirmada');

        $p = $this->props($this->actingAs($this->admin)->get('/admin/torre'));
        $this->assertSame(50, $p['numMesas']);

        $j = $this->actingAs($this->admin)->getJson('/admin/torre/estado')->assertOk()->json();
        $this->assertSame([$hoy->id], collect($j['citas'])->pluck('id')->all());
        $this->assertSame([
            'id' => $hoy->id, 'hora_prog' => '11:00', 'hora_fin_prog' => '11:30', 'hora_real_inicio' => null, 'hora_real_fin' => null,
            'llamada_at' => null, 'llamada_ts' => null, 'mesa' => null, 'estado_tv' => 'pendiente', 'estado' => 'confirmada',
            'duracion_real' => null, 'comprador' => $c->name, 'comprador_tel' => '9991112222', 'proveedor' => 'Negocio Test', 'categoria' => '',
        ], $j['citas'][0]);
        $this->assertSame([], $j['mesas_ocupadas']);
        $this->assertSame('10:00:00', $j['timestamp']);

        $this->actingAs($this->admin)->postJson("/admin/torre/citas/{$hoy->id}/llamar", [])->assertStatus(422);
        $j = $this->actingAs($this->admin)->postJson("/admin/torre/citas/{$hoy->id}/llamar", ['mesa' => '7'])->assertOk()->json();
        $this->assertTrue($j['ok']);
        $this->assertSame('llamando', $j['cita']['estado_tv']);
        $this->assertSame('7', $j['cita']['mesa']);
        $this->assertSame('10:00:00', $j['cita']['llamada_at']);
        $this->assertSame(['7'], $this->actingAs($this->admin)->getJson('/admin/torre/estado')->json('mesas_ocupadas'));

        $this->travel(2)->minutes();
        $this->assertSame('10:02:00', $this->actingAs($this->admin)->postJson("/admin/torre/citas/{$hoy->id}/repetir")->json('cita.llamada_at'));
        $this->assertSame('8', $this->actingAs($this->admin)->postJson("/admin/torre/citas/{$hoy->id}/mesa", ['mesa' => '8'])->json('cita.mesa'));
        $j = $this->actingAs($this->admin)->postJson("/admin/torre/citas/{$hoy->id}/iniciar")->json('cita');
        $this->assertSame('en_curso', $j['estado_tv']);
        $this->assertSame('10:02', $j['hora_real_inicio']);
        $this->travel(25)->minutes();
        $j = $this->actingAs($this->admin)->postJson("/admin/torre/citas/{$hoy->id}/finalizar")->json('cita');
        $this->assertSame('finalizada', $j['estado_tv']);
        $this->assertSame('completada', $j['estado']);
        $this->assertSame(25, $j['duracion_real']);

        $this->actingAs($this->admin)->postJson("/admin/torre/citas/{$hoy->id}/retrasar", ['minutos' => 7])->assertStatus(422);
        $j = $this->actingAs($this->admin)->postJson("/admin/torre/citas/{$hoy->id}/retrasar", ['minutos' => 10])->json('cita');
        $this->assertSame('11:10', $j['hora_prog']);
        $this->assertSame('11:40', $j['hora_fin_prog']);
        $this->assertSame('ausente', $this->actingAs($this->admin)->postJson("/admin/torre/citas/{$hoy->id}/ausente")->json('cita.estado_tv'));

        $this->actingAs($this->admin)->patch('/admin/config/mesas', ['num_mesas' => 0])->assertSessionHasErrors('num_mesas');
        $this->actingAs($this->admin)->patch('/admin/config/mesas', ['num_mesas' => 30])
            ->assertSessionHas('success', 'Número de mesas actualizado a 30.');
        $this->assertSame(30, $this->props($this->actingAs($this->admin)->get('/admin/torre'))['numMesas']);
    }

    /* ─────────────── pantalla TV ─────────────── */

    public function test_tv_token_y_rotacion(): void
    {
        $this->actingAs($this->admin)->get('/admin/tv')->assertRedirect();
        $token = $this->evento->fresh()->tv_token;
        $this->assertSame(48, strlen($token));

        $this->actingAs($this->admin)->post('/admin/tv/rotar-token')
            ->assertSessionHas('success', 'Token de la pantalla TV rotado. Las pantallas abiertas dejaran de funcionar.');
        $this->assertNotSame($token, $this->evento->fresh()->tv_token);
    }
}
