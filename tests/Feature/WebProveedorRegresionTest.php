<?php

namespace Tests\Feature;

use App\Mail\CitaAceptada;
use App\Mail\CitaReagendada;
use App\Mail\CitaRechazada;
use App\Mail\ProveedorAprobado;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/**
 * Regresion de los controladores web del PROVEEDOR cuya logica se movio a
 * servicios en la Fase 3 de la API (panel, feed del calendario, citas
 * recibidas y completar perfil de negocio). Se escribio y paso contra el
 * codigo ANTERIOR al refactor.
 */
class WebProveedorRegresionTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    private User $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // Miercoles a media manana: "hoy" y "esta semana" no dependen de la hora a la que corra la suite.
        $this->travelTo(Carbon::parse('2026-10-07 10:00:00'));
        $this->crearEscenario();

        $this->proveedor = $this->restaurantero->user;
        $this->proveedor->forceFill([
            'acepta_aviso_at'  => now(),
            'rol_seleccionado' => true,
            'active_role'      => 'proveedor',
        ])->save();
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

    private function usuarioProveedorSinNegocio(): User
    {
        $u = User::factory()->create([
            'acepta_aviso_at'  => now(),
            'rol_seleccionado' => true,
            'active_role'      => 'proveedor',
        ]);
        $u->assignRole('restaurantero');

        return $u;
    }

    private function perfilCompleto(array $extra = []): array
    {
        return array_merge([
            'nombre_restaurante' => 'Negocio Completo',
            'descripcion'        => 'Vendemos salsas',
            'telefono'           => '9991234567',
            'municipio'          => 'Mérida',
            'entrega_domicilio'  => '0',
            'productos'          => [['nombre' => 'Salsa verde', 'descripcion' => 'Picante', 'capacidad_cantidad' => '100', 'capacidad_unidad' => 'piezas']],
            'foto'               => UploadedFile::fake()->image('logo.png'),
        ], $extra);
    }

    // ── Panel ─────────────────────────────────────────────────────────────

    public function test_panel_crea_el_negocio_si_no_existe(): void
    {
        $u = $this->usuarioProveedorSinNegocio();

        $props = $this->actingAs($u)->get('/proveedor/panel')->assertOk()->viewData('page')['props'];

        $r = $u->fresh()->restaurantero;
        $this->assertNotNull($r);
        $this->assertSame($u->name . ' — Negocio', $r->nombre_restaurante);
        $this->assertFalse($r->aprobado);
        $this->assertFalse($r->activo);
        $this->assertSame(\App\Models\Evento::activo()->id, $r->edicion_id);
        $this->assertSame(1, $r->servicios()->where('nombre', 'Mesa de Networking')->where('duracion_minutos', 30)->count());
        $this->assertSame(5, Horario::where('restaurantero_id', $r->id)->count());
        $this->assertSame(0, $props['citasPendientes']);
        $this->assertSame([], $props['todasLasCitas']);
        $this->assertNull($props['tasaAceptacion']);
        $this->assertSame($r->id, $props['restaurantero']['id']);
    }

    public function test_panel_kpis_citas_y_eventos(): void
    {
        $c1 = $this->crearComprador('aprobado', ['telefono' => '9990001111', 'necesidades' => 'Busco salsas']);
        $c2 = $this->crearComprador();
        $c3 = $this->crearComprador();

        $this->cita($c1, now()->addHour(), 'pendiente');                  // hoy, proxima 2h
        $this->cita($c2, now()->addDay()->setTime(10, 0), 'confirmada'); // esta semana
        $this->cita($c3, now()->addDays(8)->setTime(10, 0), 'rechazada');
        $this->cita($c3, now()->addDays(9)->setTime(10, 0), 'cancelada');
        $otroProveedor = $this->crearProveedor('Otro');
        $this->cita($c1, now()->addDay()->setTime(12, 0), 'pendiente', $otroProveedor);

        DB::table('evento_usuario')->insert([
            'evento_id' => $this->evento->id, 'user_id' => $this->proveedor->id, 'tipo' => 'proveedor',
            'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $props = $this->actingAs($this->proveedor)->get('/proveedor/panel')->assertOk()->viewData('page')['props'];

        $this->assertSame(1, $props['citasHoy']);
        $this->assertSame(2, $props['citasSemana']);
        $this->assertSame(1, $props['citasPendientes']);
        $this->assertSame(2, $props['totalEnEvento']);
        $this->assertEquals(50, $props['tasaAceptacion']);
        $this->assertSame(['pendiente', 'confirmada', 'cancelada', 'rechazada'], collect($props['todasLasCitas'])->pluck('estado')->all());

        $primera = $props['todasLasCitas'][0];
        $this->assertSame($c1->name, $primera['cliente']['name']);
        $this->assertSame($c1->email, $primera['cliente']['email']);
        $this->assertSame('9990001111', $primera['cliente']['telefono']);
        $this->assertSame('Busco salsas', $primera['cliente']['necesidades']);
        $this->assertSame($c1->name, $props['citaProxima2h']['cliente_name']);

        $ev = collect($props['eventos'])->firstWhere('id', $this->evento->id);
        $this->assertSame('aprobado', $ev['mi_registro']['proveedor']->estado ?? $ev['mi_registro']['proveedor']['estado']);
        $this->assertNull($ev['mi_registro']['comprador']);
    }

    public function test_feed_del_calendario_del_panel(): void
    {
        $c1 = $this->crearComprador();
        $c2 = $this->crearComprador();
        $pend = $this->cita($c1, now()->addDay()->setTime(10, 0), 'pendiente');
        $conf = $this->cita($c2, now()->addDay()->setTime(11, 0), 'confirmada');
        $this->cita($c2, now()->addDay()->setTime(12, 0), 'cancelada');
        $rech = $this->cita($c1, now()->addDay()->setTime(13, 0), 'rechazada');

        $json = $this->actingAs($this->proveedor)->getJson('/proveedor/panel/eventos')->assertOk()->json();

        $this->assertCount(3, $json); // la cancelada no sale; la rechazada si (color gris)
        $p = collect($json)->firstWhere('id', $pend->id);
        $this->assertSame($c1->name, $p['title']);
        $this->assertSame('#f59e0b', $p['backgroundColor']);
        $this->assertSame('#111827', $p['textColor']);
        $this->assertSame('#10b981', collect($json)->firstWhere('id', $conf->id)['borderColor']);
        $this->assertSame('#6b7280', collect($json)->firstWhere('id', $rech->id)['backgroundColor']);

        $sin = $this->usuarioProveedorSinNegocio();
        $this->actingAs($sin)->getJson('/proveedor/panel/eventos')->assertOk()->assertExactJson([]);
    }

    // ── Citas recibidas ───────────────────────────────────────────────────

    public function test_citas_index_por_pestana_con_contadores(): void
    {
        $c = $this->crearComprador();
        $this->cita($c, now()->addDay()->setTime(9, 0), 'pendiente');
        $this->cita($c, now()->addDay()->setTime(10, 0), 'confirmada');
        $this->cita($c, now()->addDay()->setTime(11, 0), 'pendiente_reconfirmacion');
        $this->cita($c, now()->addDay()->setTime(12, 0), 'rechazada');
        $this->cita($c, now()->addDay()->setTime(13, 0), 'cancelada');
        $this->cita($c, now()->addDay()->setTime(14, 0), 'completada');

        $props = $this->actingAs($this->proveedor)->get('/proveedor/citas')->assertOk()->viewData('page')['props'];
        $this->assertSame('pendientes', $props['tab']);
        $this->assertSame(['pendientes' => 1, 'confirmadas' => 2, 'rechazadas' => 2], $props['counts']);
        $this->assertSame(['pendiente'], collect($props['citas']['data'])->pluck('estado')->all());

        $props = $this->actingAs($this->proveedor)->get('/proveedor/citas?tab=confirmadas')->viewData('page')['props'];
        $this->assertSame(['pendiente_reconfirmacion', 'confirmada'], collect($props['citas']['data'])->pluck('estado')->all());

        $props = $this->actingAs($this->proveedor)->get('/proveedor/citas?tab=rechazadas')->viewData('page')['props'];
        $this->assertSame(['cancelada', 'rechazada'], collect($props['citas']['data'])->pluck('estado')->all());

        $props = $this->actingAs($this->proveedor)->get('/proveedor/citas?tab=todas')->viewData('page')['props'];
        $this->assertCount(6, $props['citas']['data']);

        $this->actingAs($this->usuarioProveedorSinNegocio())->get('/proveedor/citas')->assertForbidden();
    }

    public function test_aceptar_cita_notifica_y_manda_correo(): void
    {
        $c = $this->crearComprador();
        $cita = $this->cita($c, now()->addDay()->setTime(10, 0));

        $this->actingAs($this->proveedor)->from('/proveedor/panel')
            ->patch("/proveedor/citas/{$cita->id}/aceptar")
            ->assertRedirect('/proveedor/panel')
            ->assertSessionHas('success', 'Cita aceptada. El comprador fue notificado.');

        $this->assertSame('confirmada', $cita->fresh()->estado);
        Mail::assertQueued(CitaAceptada::class, fn ($m) => $m->hasTo($c->email));
        $n = Notificacion::where('user_id', $c->id)->where('tipo', 'cita_aceptada')->firstOrFail();
        $this->assertSame('Cita confirmada', $n->titulo);
        $this->assertSame("Tu cita con Negocio Test el {$cita->inicio->format('d/m/Y H:i')} fue confirmada.", $n->mensaje);
        $this->assertSame($cita->id, $n->cita_id);

        // Cita de otro proveedor: 403
        $ajena = $this->cita($c, now()->addDay()->setTime(12, 0), 'pendiente', $this->crearProveedor('Ajeno'));
        $this->actingAs($this->proveedor)->patch("/proveedor/citas/{$ajena->id}/aceptar")->assertForbidden();
        $this->assertSame('pendiente', $ajena->fresh()->estado);
    }

    public function test_rechazar_cita_notifica_y_manda_correo(): void
    {
        $c = $this->crearComprador();
        $cita = $this->cita($c, now()->addDay()->setTime(10, 0));

        $this->actingAs($this->proveedor)->from('/proveedor/citas')
            ->patch("/proveedor/citas/{$cita->id}/rechazar")
            ->assertRedirect('/proveedor/citas')
            ->assertSessionHas('success', 'Cita rechazada. El comprador fue notificado.');

        $this->assertSame('rechazada', $cita->fresh()->estado);
        Mail::assertQueued(CitaRechazada::class, fn ($m) => $m->hasTo($c->email));
        $n = Notificacion::where('user_id', $c->id)->where('tipo', 'cita_rechazada')->firstOrFail();
        $this->assertSame('Cita rechazada', $n->titulo);
        $this->assertSame("Tu cita con Negocio Test el {$cita->inicio->format('d/m/Y H:i')} fue rechazada.", $n->mensaje);
    }

    public function test_reagendar_valida_rechaza_hueco_ocupado_y_propone(): void
    {
        $c = $this->crearComprador();
        $otro = $this->crearComprador();
        $dia = $this->evento->fecha_hora_inicio->copy();
        $cita = $this->cita($c, $dia->copy()->setTime(10, 0));
        $this->cita($otro, $dia->copy()->setTime(11, 0), 'confirmada');

        $this->actingAs($this->proveedor)->from('/proveedor/panel')
            ->post("/proveedor/citas/{$cita->id}/reagendar", ['propuesta_fecha' => '', 'propuesta_hora' => '9'])
            ->assertRedirect('/proveedor/panel')
            ->assertSessionHasErrors(['propuesta_fecha', 'propuesta_hora']);

        $this->actingAs($this->proveedor)->from('/proveedor/panel')
            ->post("/proveedor/citas/{$cita->id}/reagendar", ['propuesta_fecha' => $dia->toDateString(), 'propuesta_hora' => '11:00'])
            ->assertRedirect('/proveedor/panel')
            ->assertSessionHasErrors(['fecha' => 'Este horario ya no está disponible con el proveedor. Por favor elige otro.']);
        $this->assertSame('pendiente', $cita->fresh()->estado);

        $this->actingAs($this->proveedor)->from('/proveedor/panel')
            ->post("/proveedor/citas/{$cita->id}/reagendar", ['propuesta_fecha' => $dia->toDateString(), 'propuesta_hora' => '12:00'])
            ->assertRedirect('/proveedor/panel')
            ->assertSessionHas('success', 'Propuesta de reagendamiento enviada al comprador.');

        $cita->refresh();
        $this->assertSame('pendiente_reconfirmacion', $cita->estado);
        $this->assertSame($dia->copy()->setTime(12, 0)->toDateTimeString(), $cita->propuesta_inicio->toDateTimeString());
        $this->assertSame($dia->copy()->setTime(12, 30)->toDateTimeString(), $cita->propuesta_fin->toDateTimeString());
        $this->assertSame(64, strlen($cita->token_confirmacion));
        $this->assertSame($dia->copy()->setTime(10, 0)->toDateTimeString(), $cita->inicio->toDateTimeString());
        Mail::assertQueued(CitaReagendada::class, fn ($m) => $m->hasTo($c->email));
        $n = Notificacion::where('user_id', $c->id)->where('tipo', 'cita_reagendada')->firstOrFail();
        $this->assertSame('Propuesta de cambio de horario', $n->titulo);
        $this->assertStringContainsString($dia->copy()->setTime(12, 0)->format('d/m/Y H:i'), $n->mensaje);

        $ajena = $this->cita($c, $dia->copy()->setTime(15, 0), 'pendiente', $this->crearProveedor('Ajeno'));
        $this->actingAs($this->proveedor)
            ->post("/proveedor/citas/{$ajena->id}/reagendar", ['propuesta_fecha' => $dia->toDateString(), 'propuesta_hora' => '16:00'])
            ->assertForbidden();
    }

    // ── Completar perfil de negocio ───────────────────────────────────────

    public function test_completar_perfil_valida_como_la_web(): void
    {
        $this->actingAs($this->proveedor)->from('/user/profile')
            ->post('/proveedor/completar-perfil', [
                'nombre_restaurante' => '',
                'entrega_domicilio'  => '1',
                'cobertura_entrega'  => '',
                'tipo_oferta'        => 'otro',
                'productos'          => [['nombre' => 'X', 'capacidad_unidad' => 'toneladas']],
                'foto'               => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect('/user/profile')
            ->assertSessionHasErrors(['nombre_restaurante', 'cobertura_entrega', 'forma_entrega', 'tipo_oferta', 'productos.0.capacidad_unidad', 'foto']);
    }

    public function test_completar_perfil_guarda_normaliza_y_limpia(): void
    {
        $r = $this->restaurantero;
        $r->update(['rechazado' => true, 'motivo_rechazo' => 'Falta logo', 'requisitos_alimentos' => ['x']]);

        $this->actingAs($this->proveedor)->from('/user/profile')
            ->post('/proveedor/completar-perfil', [
                'nombre_restaurante'      => 'Nuevo Nombre',
                'sitio_web'               => 'misalsa.mx',
                'tipo_oferta'             => 'servicio',
                'tiempo_vida_anaquel'     => '6 meses',
                'requiere_refrigeracion'  => '1',
                'certificaciones_servicio_detalle' => 'ISO',
                'tiene_certificaciones_servicio'   => '1',
                'acepta_credito'          => '0',
                'credito_monto_maximo'    => '5000',
                'factura'                 => '0',
                'regimen_fiscal'          => 'RESICO',
                'entrega_domicilio'       => '1',
                'cobertura_entrega'       => 'local',
                'forma_entrega'           => 'flexible',
                'categorias_json'         => ['Textiles'],
                'productos'               => [['nombre' => ''], ['nombre' => 'Asesoria', 'capacidad_cantidad' => '']],
            ])
            ->assertRedirect('/user/profile')
            ->assertSessionHas('success', 'Progreso guardado. Completa todos los campos requeridos para que tu perfil se apruebe automáticamente.');

        $r->refresh();
        $this->assertSame('Nuevo Nombre', $r->nombre_restaurante);
        $this->assertSame('https://misalsa.mx', $r->sitio_web);
        $this->assertSame('servicio', $r->tipo_oferta);
        $this->assertNull($r->tiempo_vida_anaquel);
        $this->assertFalse($r->requiere_refrigeracion);
        $this->assertTrue($r->tiene_certificaciones_servicio);
        $this->assertSame('ISO', $r->certificaciones_servicio_detalle);
        $this->assertFalse($r->acepta_credito);
        $this->assertNull($r->credito_monto_maximo);
        $this->assertFalse($r->factura);
        $this->assertNull($r->regimen_fiscal);
        $this->assertTrue($r->entrega_domicilio);
        $this->assertSame('local', $r->cobertura_entrega);
        $this->assertSame(['Textiles'], $r->categorias_json);
        $this->assertSame([], $r->requisitos_alimentos);
        $this->assertSame([['nombre' => 'Asesoria', 'descripcion' => '', 'foto_path' => null, 'capacidad_cantidad' => null, 'capacidad_unidad' => null]], $r->productos_top);
        $this->assertFalse($r->rechazado);
        $this->assertNull($r->motivo_rechazo);
        $this->assertNotNull($r->solicitado_aprobacion_at);
        $this->assertFalse($r->perfil_completo);
    }

    public function test_completar_perfil_con_logo_y_foto_autoaprueba(): void
    {
        Storage::fake('public');
        $admin = $this->crearAdmin();

        $this->actingAs($this->proveedor)->from('/user/profile')
            ->post('/proveedor/completar-perfil', $this->perfilCompleto([
                'producto_foto_0' => UploadedFile::fake()->image('p.jpg'),
            ]))
            ->assertRedirect('/user/profile')
            ->assertSessionHas('success', '¡Perfil completo! Fuiste aprobado automáticamente y ya apareces en el directorio del evento activo.');

        $r = $this->restaurantero->fresh();
        $this->assertTrue($r->perfil_completo);
        $this->assertTrue($r->aprobado);
        $this->assertTrue($r->activo);
        $this->assertStringStartsWith('proveedores/fotos/', $r->logo_path);
        Storage::disk('public')->assertExists($r->logo_path);
        $this->assertStringStartsWith('proveedores/productos/', $r->productos_top[0]['foto_path']);
        $this->assertEquals(100, $r->productos_top[0]['capacidad_cantidad']);
        $this->assertSame('piezas', $r->productos_top[0]['capacidad_unidad']);
        Mail::assertQueued(ProveedorAprobado::class);
        $this->assertTrue(Notificacion::where('user_id', $admin->id)->where('titulo', 'Nuevo proveedor con perfil completo')->exists());
        $this->assertSame('pendiente', DB::table('evento_usuario')->where('user_id', $this->proveedor->id)->where('tipo', 'proveedor')->value('estado'));

        // Editar despues: la foto del producto se conserva por posicion y el logo viejo se borra al reemplazarlo.
        $logoViejo = $r->logo_path;
        $fotoProducto = $r->productos_top[0]['foto_path'];
        $this->actingAs($this->proveedor)->from('/user/profile')
            ->post('/proveedor/completar-perfil', $this->perfilCompleto([
                'productos' => [['nombre' => 'Salsa roja']],
            ]))->assertSessionHasNoErrors();
        $r->refresh();
        Storage::disk('public')->assertMissing($logoViejo);
        $this->assertSame($fotoProducto, $r->productos_top[0]['foto_path']);
        $this->assertSame('Salsa roja', $r->productos_top[0]['nombre']);
        // BUG preexistente (se documenta, no se arregla aqui): al editar un perfil
        // YA aprobado, store() pone aprobado=false y el hook saved() llama a
        // autoAprobar(), cuyo saveQuietly() anidado no ve cambios (el original
        // del modelo aun dice aprobado=true) y no escribe nada. En BD el
        // proveedor queda aprobado=false/activo=false aunque el perfil siga
        // completo y el mensaje diga "Fuiste aprobado automaticamente".
        $this->assertTrue($r->perfil_completo);
        $this->assertFalse($r->aprobado);
        $this->assertFalse($r->activo);
    }

    public function test_editar_perfil_aprobado_incompleto_pierde_la_aprobacion(): void
    {
        $this->restaurantero->update(['aprobado' => true, 'activo' => true]);

        $this->actingAs($this->proveedor)->from('/user/profile')
            ->post('/proveedor/completar-perfil', ['nombre_restaurante' => 'Solo nombre'])
            ->assertSessionHasNoErrors();

        $r = $this->restaurantero->fresh();
        $this->assertFalse($r->aprobado);
        $this->assertFalse($r->activo);
    }

    public function test_foto_de_producto_sin_nombre_avisa(): void
    {
        Storage::fake('public');

        $this->actingAs($this->proveedor)->from('/user/profile')
            ->post('/proveedor/completar-perfil', [
                'nombre_restaurante' => 'X',
                'productos'          => [['nombre' => '']],
                'producto_foto_0'    => UploadedFile::fake()->image('p.jpg'),
            ])
            ->assertRedirect('/user/profile')
            ->assertSessionHasErrors(['producto_foto_0' => 'Ponle nombre al producto 1 antes de subirle una foto.']);

        $this->assertSame('Negocio Test', $this->restaurantero->fresh()->nombre_restaurante);
    }

    public function test_completar_perfil_crea_el_negocio_y_servicio_si_no_existe(): void
    {
        $u = $this->usuarioProveedorSinNegocio();

        $this->actingAs($u)->from('/user/profile')
            ->post('/proveedor/completar-perfil', ['nombre_restaurante' => 'Recien Llegado'])
            ->assertSessionHasNoErrors();

        $r = $u->fresh()->restaurantero;
        $this->assertSame('Recien Llegado', $r->nombre_restaurante);
        $this->assertSame(1, $r->servicios()->count());
        $this->assertSame(5, $r->horarios()->count());

        $this->actingAs($u)->get('/proveedor/completar-perfil')->assertRedirect(route('restaurantero.panel'));
    }

    public function test_comprador_no_entra_a_rutas_de_proveedor(): void
    {
        $c = $this->crearComprador();
        $this->actingAs($c)->get('/proveedor/panel')->assertForbidden();
        $this->actingAs($c)->post('/proveedor/completar-perfil', ['nombre_restaurante' => 'X'])->assertForbidden();
    }
}
