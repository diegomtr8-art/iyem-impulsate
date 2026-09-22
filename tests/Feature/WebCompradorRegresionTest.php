<?php

namespace Tests\Feature;

use App\Mail\CitaCancelada;
use App\Mail\CitaReagendada;
use App\Models\AgendaPropuesta;
use App\Models\AgendaPropuestaCita;
use App\Models\Cita;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/**
 * Regresion de los controladores web del COMPRADOR cuya logica se movio a
 * servicios en la Fase 2 de la API (directorio, ficha, alta/cancelacion/notas
 * de citas, reagenda por token, agenda propuesta por token y encuestas por
 * token). Se escribio y paso contra el codigo ANTERIOR al refactor.
 */
class WebCompradorRegresionTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->crearEscenario(['destacado' => true]);
    }

    private function inscribirProveedor(Restaurantero $r, ?Evento $evento = null, string $estado = 'aprobado'): void
    {
        DB::table('evento_usuario')->insert([
            'evento_id'  => ($evento ?? $this->evento)->id,
            'user_id'    => $r->user_id,
            'tipo'       => 'proveedor',
            'estado'     => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function proveedorCompleto(string $nombre, array $datos = []): Restaurantero
    {
        $user = User::factory()->create();
        $user->assignRole('restaurantero');

        $r = Restaurantero::create(array_merge([
            'user_id'            => $user->id,
            'nombre_restaurante' => $nombre,
            'descripcion'        => "Descripcion de {$nombre}",
            'telefono'           => '9990000000',
            'municipio'          => 'Mérida',
            'direccion'          => 'Calle secreta 123',
            'categoria'          => 'Alimentos y Bebidas',
            'logo_path'          => 'logos/x.png',
            'productos_top'      => [['nombre' => 'Producto de ' . $nombre]],
            'entrega_domicilio'  => false,
            'activo'             => true,
            'aprobado'           => true,
        ], $datos));

        Servicio::create(['restaurantero_id' => $r->id, 'nombre' => 'Mesa', 'duracion_minutos' => 30, 'activo' => true]);

        return $r;
    }

    private function dia(int $h, int $m = 0): Carbon
    {
        return $this->evento->fecha_hora_inicio->copy()->setTime($h, $m);
    }

    private function cita(User $comprador, Restaurantero $r, Carbon $inicio, string $estado = 'pendiente', array $extra = []): Cita
    {
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

    // ── Directorio y ficha ────────────────────────────────────────────────

    public function test_directorio_por_evento_con_filtros_y_categorias(): void
    {
        $miel  = $this->proveedorCompleto('Miel Maya', ['categoria' => 'Alimentos y Bebidas', 'municipio' => 'Tizimín']);
        $telas = $this->proveedorCompleto('Telas Xokbil', ['categoria' => 'Textiles']);
        $fuera = $this->proveedorCompleto('Fuera del evento', ['categoria' => 'Artesanías']);
        $this->inscribirProveedor($miel);
        $this->inscribirProveedor($telas);
        $this->inscribirProveedor($fuera, null, 'pendiente');

        $props = $this->get('/proveedores')->assertOk()->viewData('page')['props'];
        $this->assertFalse($props['sin_evento']);
        $seccion = collect($props['eventos'])->firstWhere('id', $this->evento->id);
        $this->assertEqualsCanonicalizing(['Miel Maya', 'Telas Xokbil'], collect($seccion['proveedores'])->pluck('nombre_restaurante')->all());
        $this->assertSame(['Alimentos y Bebidas', 'Textiles'], array_values(array_intersect($props['categorias'], ['Alimentos y Bebidas', 'Textiles', 'Artesanías'])));
        $this->assertNotNull($props['proveedoresPlataforma']);

        $props = $this->get('/proveedores?search=tizim')->viewData('page')['props'];
        $seccion = collect($props['eventos'])->firstWhere('id', $this->evento->id);
        $this->assertSame(['Miel Maya'], collect($seccion['proveedores'])->pluck('nombre_restaurante')->all());
        $this->assertSame(['search' => 'tizim'], $props['filters']);

        $props = $this->get('/proveedores?categoria=Textiles')->viewData('page')['props'];
        $seccion = collect($props['eventos'])->firstWhere('id', $this->evento->id);
        $this->assertSame(['Telas Xokbil'], collect($seccion['proveedores'])->pluck('nombre_restaurante')->all());
    }

    public function test_directorio_sin_encuentros_activos_muestra_la_plataforma(): void
    {
        Evento::query()->update(['activa' => false]);
        $this->proveedorCompleto('Completo Uno');
        $this->proveedorCompleto('Completo Dos', ['categoria' => 'Textiles']);

        $props = $this->get('/proveedores')->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['sin_evento']);
        $this->assertNull($props['proveedoresPlataforma']);
        $nombres = collect($props['restauranteros']['data'])->pluck('nombre_restaurante');
        $this->assertTrue($nombres->contains('Completo Uno'));
        $this->assertFalse($nombres->contains('Negocio Test')); // perfil incompleto
        $this->assertContains('Textiles', $props['categorias']);
    }

    public function test_ficha_publica_oculta_direccion_registra_visita_y_404_si_inactivo(): void
    {
        $r = $this->proveedorCompleto('Ficha');
        $comprador = $this->crearComprador();
        $this->cita($comprador, $r, $this->dia(10));

        $props = $this->actingAs($comprador)->get('/proveedores/' . $r->id)->assertOk()->viewData('page')['props'];
        $this->assertArrayNotHasKey('direccion', $props['restaurantero']);
        $this->assertSame('9990000000', $props['restaurantero']['telefono']);
        $this->assertSame(1, $props['citasCount']);
        $inicios = collect($props['citasOcupadas'])->pluck('inicio');
        $this->assertTrue($inicios->contains($this->dia(10)->format('Y-m-d H:i')));
        $this->assertTrue($inicios->contains($this->dia(9, 50)->format('Y-m-d H:i'))); // colchon del comprador
        $this->assertSame(30, $props['evento']['tiempo_entre_citas_minutos']);
        $this->assertSame(1, DB::table('page_visits')->where('restaurantero_id', $r->id)->count());

        $r->update(['activo' => false]);
        $this->actingAs($comprador)->get('/proveedores/' . $r->id)->assertNotFound();
    }

    public function test_mi_panel_ordena_proveedores_por_necesidades(): void
    {
        $cafe = $this->proveedorCompleto('Café Puuc', ['descripcion' => 'Cafe de altura']);
        $miel = $this->proveedorCompleto('Miel Maya', ['descripcion' => 'Miel melipona']);
        $this->inscribirProveedor($cafe);
        $this->inscribirProveedor($miel);
        $comprador = $this->crearComprador('aprobado', ['necesidades' => 'Busco miel melipona']);

        $props = $this->actingAs($comprador)->get('/mi-panel')->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['compradorAprobado']);
        $this->assertTrue($props['tieneKeywords']);
        $this->assertSame('Miel Maya', $props['proveedores'][0]['nombre_restaurante']);
    }

    // ── Alta, cancelacion y notas ─────────────────────────────────────────

    public function test_agendar_por_web(): void
    {
        $comprador = $this->crearComprador();

        $this->actingAs($comprador)->post('/citas', [
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => $this->dia(10)->toDateString(),
            'hora'             => '10:00',
            'notas'            => 'Hola',
            'productos_interes'=> ['Salsa'],
        ])->assertRedirect(route('user.dashboard'))->assertSessionHas('success', 'Cita solicitada correctamente.');

        $cita = Cita::where('cliente_id', $comprador->id)->firstOrFail();
        $this->assertSame('pendiente', $cita->estado);
        $this->assertSame(['Salsa'], $cita->productos_interes);

        // mismo horario con otro comprador: ocupado
        $otro = $this->crearComprador();
        $this->actingAs($otro)->from('/proveedores/' . $this->restaurantero->id)->post('/citas', [
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => $this->dia(10)->toDateString(),
            'hora'             => '10:00',
        ])->assertRedirect('/proveedores/' . $this->restaurantero->id)
          ->assertSessionHasErrors(['fecha' => 'Este horario ya no está disponible con el proveedor. Por favor elige otro.']);

        // validacion
        $this->actingAs($otro)->post('/citas', [])->assertSessionHasErrors(['restaurantero_id', 'fecha', 'hora']);

        // admin
        $admin = $this->crearAdmin();
        $this->actingAs($admin)->post('/citas', [])->assertSessionHasErrors([
            'error' => 'El administrador no puede agendar citas para sí mismo. Usa el panel de administración para gestionar citas de clientes.',
        ]);
    }

    public function test_cancelar_por_web(): void
    {
        $comprador = $this->crearComprador();
        $ajeno = $this->crearComprador();
        $cita = $this->cita($comprador, $this->restaurantero, $this->dia(10));
        $completada = $this->cita($comprador, $this->restaurantero, $this->dia(12), 'completada');

        $this->actingAs($ajeno)->delete('/citas/' . $cita->id)->assertForbidden();

        $this->actingAs($comprador)->delete('/citas/' . $completada->id)
            ->assertSessionHasErrors(['error' => 'No puedes cancelar esta cita.']);

        $this->actingAs($comprador)->delete('/citas/' . $cita->id)
            ->assertSessionHas('success', 'Cita cancelada correctamente.');
        $this->assertSame('cancelada', $cita->fresh()->estado);
        Mail::assertQueued(CitaCancelada::class, fn ($m) => $m->hasTo($comprador->email));
    }

    public function test_notas_por_web(): void
    {
        $comprador = $this->crearComprador();
        $ajeno = $this->crearComprador();
        $cita = $this->cita($comprador, $this->restaurantero, $this->dia(10));

        $this->actingAs($ajeno)->patch("/citas/{$cita->id}/notas", ['notas' => 'x'])->assertForbidden();
        $this->actingAs($comprador)->patch("/citas/{$cita->id}/notas", ['notas' => str_repeat('a', 1001)])
            ->assertSessionHasErrors('notas');
        $this->actingAs($comprador)->patch("/citas/{$cita->id}/notas", ['notas' => 'Nuevas'])
            ->assertSessionHas('success', 'Notas actualizadas.');
        $this->assertSame('Nuevas', $cita->fresh()->notas);
    }

    // ── Reagenda por token ────────────────────────────────────────────────

    private function citaConPropuesta(User $comprador, Carbon $inicio, Carbon $propuesta): Cita
    {
        return $this->cita($comprador, $this->restaurantero, $inicio, 'pendiente_reconfirmacion', [
            'propuesta_inicio'   => $propuesta,
            'propuesta_fin'      => $propuesta->copy()->addMinutes(30),
            'token_confirmacion' => Str::random(64),
        ]);
    }

    public function test_reagenda_por_token_aceptar(): void
    {
        $comprador = $this->crearComprador();
        $cita = $this->citaConPropuesta($comprador, $this->dia(10), $this->dia(12));

        $this->get("/citas/{$cita->id}/confirmar/{$cita->token_confirmacion}")->assertOk();
        $this->get("/citas/{$cita->id}/confirmar/malo")->assertNotFound();

        $this->post("/citas/{$cita->id}/confirmar/{$cita->token_confirmacion}")
            ->assertOk()->assertSee('¡Cita confirmada con la nueva fecha! Nos vemos pronto.');

        $cita->refresh();
        $this->assertSame('confirmada', $cita->estado);
        $this->assertTrue($cita->inicio->equalTo($this->dia(12)));
        $this->assertNull($cita->token_confirmacion);
        Mail::assertQueued(CitaReagendada::class, fn ($m) => $m->hasTo($this->restaurantero->user->email));
    }

    public function test_reagenda_por_token_con_choque_deja_la_cita_intacta(): void
    {
        $comprador = $this->crearComprador();
        $otro = $this->crearComprador();
        $cita = $this->citaConPropuesta($comprador, $this->dia(10), $this->dia(12));
        $this->cita($otro, $this->restaurantero, $this->dia(12), 'confirmada');
        $token = $cita->token_confirmacion;

        $this->post("/citas/{$cita->id}/confirmar/{$token}")
            ->assertOk()->assertSee('Horario ya no disponible');

        $cita->refresh();
        $this->assertSame('pendiente_reconfirmacion', $cita->estado);
        $this->assertTrue($cita->inicio->equalTo($this->dia(10)));
        $this->assertTrue(Notificacion::where('user_id', $this->restaurantero->user_id)->where('titulo', 'Reagenda no aplicada')->exists());
    }

    public function test_reagenda_por_token_rechazar(): void
    {
        $comprador = $this->crearComprador();
        $cita = $this->citaConPropuesta($comprador, $this->dia(10), $this->dia(12));

        $this->post("/citas/{$cita->id}/rechazar/malo")->assertNotFound();
        $this->post("/citas/{$cita->id}/rechazar/{$cita->token_confirmacion}")
            ->assertOk()->assertSee('Has rechazado la propuesta. La cita quedó cancelada.');

        $this->assertSame('cancelada', $cita->fresh()->estado);
        Mail::assertQueued(CitaCancelada::class, fn ($m) => $m->hasTo($this->restaurantero->user->email));
        $this->assertTrue(Notificacion::where('user_id', $this->restaurantero->user_id)->where('titulo', 'Propuesta de reagenda rechazada')->exists());
    }

    // ── Agenda propuesta por token ────────────────────────────────────────

    private function propuesta(User $comprador, array $proveedores): AgendaPropuesta
    {
        $admin = $this->crearAdmin();
        $p = AgendaPropuesta::create([
            'evento_id' => $this->evento->id, 'user_id' => $comprador->id, 'admin_id' => $admin->id,
            'estado' => 'pendiente', 'token' => Str::random(64), 'enviada_at' => now(),
        ]);
        foreach ($proveedores as $i => $r) {
            AgendaPropuestaCita::create([
                'agenda_propuesta_id' => $p->id, 'restaurantero_id' => $r->id,
                'slot_inicio' => $this->dia(10 + $i), 'slot_fin' => $this->dia(10 + $i, 30),
            ]);
        }

        return $p;
    }

    public function test_agenda_por_token_aceptar(): void
    {
        $comprador = $this->crearComprador();
        $otro = $this->crearProveedor('Otro');
        $p = $this->propuesta($comprador, [$this->restaurantero, $otro]);

        $this->get('/agenda/propuesta/' . $p->token)->assertOk();
        $this->get('/agenda/aceptar/' . $p->token)->assertRedirect(route('agenda.ver', $p->token));

        $props = $this->post('/agenda/aceptar/' . $p->token)->assertOk()->viewData('page')['props'];
        $this->assertSame('aceptada', $props['tipo']);
        $this->assertFalse($props['yaRespondida']);

        $this->assertSame(2, Cita::where('cliente_id', $comprador->id)->where('estado', 'confirmada')->count());
        $this->assertSame(2, $p->citas()->whereNotNull('cita_id')->count());
        $this->assertTrue(Notificacion::where('user_id', $otro->user_id)->where('titulo', '¡Nueva cita confirmada!')->exists());
        $this->assertTrue(Notificacion::where('user_id', $comprador->id)->where('titulo', '¡Agenda aceptada!')->exists());
        $this->assertSame('aceptada', $p->fresh()->estado);

        $props = $this->post('/agenda/aceptar/' . $p->token)->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['yaRespondida']);
        $this->assertSame(2, Cita::where('cliente_id', $comprador->id)->count());
    }

    public function test_agenda_por_token_rechazar_y_sin_servicio(): void
    {
        $comprador = $this->crearComprador();
        $admin = $this->crearAdmin();
        $p = $this->propuesta($comprador, [$this->restaurantero]);

        $props = $this->post('/agenda/rechazar/' . $p->token)->assertOk()->viewData('page')['props'];
        $this->assertSame('rechazada', $props['tipo']);
        $this->assertSame('rechazada', $p->fresh()->estado);
        $this->assertTrue(Notificacion::where('user_id', $admin->id)->where('titulo', 'Propuesta de agenda rechazada')->exists());
        $this->assertTrue(Notificacion::where('user_id', $comprador->id)->where('titulo', 'Propuesta rechazada')->exists());
        $this->assertSame(0, Cita::where('cliente_id', $comprador->id)->count());

        $sinServicio = $this->crearProveedor('Sin servicio');
        $sinServicio->servicios()->update(['activo' => false]);
        $p2 = $this->propuesta($comprador, [$sinServicio]);
        $this->post('/agenda/aceptar/' . $p2->token)->assertStatus(422);
        $this->assertSame('pendiente', $p2->fresh()->estado);
    }

    // ── Encuesta por token ────────────────────────────────────────────────

    public function test_encuesta_por_token(): void
    {
        $comprador = $this->crearComprador();
        $e = EncuestaSatisfaccion::create([
            'evento_id' => $this->evento->id, 'user_id' => $comprador->id, 'tipo' => 'comprador', 'token' => Str::random(40),
        ]);

        $props = $this->get('/encuesta/' . $e->token)->assertOk()->viewData('page')['props'];
        $this->assertSame(config('encuestas.preguntas'), $props['preguntas']);

        $this->post('/encuesta/' . $e->token, ['experiencia_general' => 9])
            ->assertSessionHasErrors('experiencia_general');
        $this->post('/encuesta/' . $e->token, ['recomendaria' => 'Tal vez'])
            ->assertSessionHasErrors('recomendaria');

        $props = $this->post('/encuesta/' . $e->token, [
            'experiencia_general' => 5, 'lo_mejor' => 'Todo', 'recomendaria' => 'Sí', 'mejoras' => '',
        ])->assertOk()->viewData('page')['props'];
        $this->assertSame('Encuentro Test', $props['evento']['nombre']);
        $this->assertNotNull($e->fresh()->completada_at);
        $this->assertSame(3, $e->respuestas()->count());

        $this->post('/encuesta/' . $e->token, ['experiencia_general' => 4])
            ->assertSessionHasErrors(['error' => 'Esta encuesta ya fue respondida.']);
        $this->assertSame(3, $e->respuestas()->count());
    }
}
