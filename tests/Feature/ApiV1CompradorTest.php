<?php

namespace Tests\Feature;

use App\Mail\CitaAgendada;
use App\Mail\CitaCancelada;
use App\Mail\CitaReagendada;
use App\Models\AgendaPropuesta;
use App\Models\AgendaPropuestaCita;
use App\Models\Cita;
use App\Models\EncuestaPlantilla;
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
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/** Fase 2 de la API: todo lo del comprador (directorio, disponibilidad, citas, reagendas, agendas, encuestas). */
class ApiV1CompradorTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->crearEscenario(['destacado' => true]);
    }

    /* ─────────────── helpers ─────────────── */

    private function proveedorCompleto(string $nombre, array $datos = [], bool $enEvento = true): Restaurantero
    {
        $user = User::factory()->create();
        $user->assignRole('restaurantero');

        $r = Restaurantero::create(array_merge([
            'user_id'              => $user->id,
            'nombre_restaurante'   => $nombre,
            'descripcion'          => "Descripcion de {$nombre}",
            'telefono'             => '9990000000',
            'municipio'            => 'Mérida',
            'direccion'            => 'Calle secreta 123',
            'categoria'            => 'Alimentos y Bebidas',
            'logo_path'            => 'logos/x.png',
            'productos_top'        => [['nombre' => 'Producto de ' . $nombre, 'foto_path' => 'productos/p.jpg', 'capacidad_cantidad' => 10, 'capacidad_unidad' => 'kg']],
            'entrega_domicilio'    => false,
            'activo'               => true,
            'aprobado'             => true,
            'motivo_rechazo'       => 'interno-no-debe-salir',
            'curp_representante'   => 'PEPG800101MYNCRR09',
        ], $datos));
        Servicio::create(['restaurantero_id' => $r->id, 'nombre' => 'Mesa', 'duracion_minutos' => 30, 'activo' => true]);

        if ($enEvento) {
            DB::table('evento_usuario')->insert([
                'evento_id' => $this->evento->id, 'user_id' => $user->id, 'tipo' => 'proveedor',
                'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $r;
    }

    private function dia(int $h, int $m = 0, int $dia = 0): Carbon
    {
        return $this->evento->fecha_hora_inicio->copy()->addDays($dia)->setTime($h, $m);
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

    private function horario(array $respuesta, string $fecha, string $hora): ?array
    {
        $dia = collect($respuesta['data']['dias'])->firstWhere('fecha', $fecha);

        return $dia ? collect($dia['horarios'])->firstWhere('hora', $hora) : null;
    }

    private function agendar(array $datos = [])
    {
        return $this->postJson('/api/v1/citas', array_merge([
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => $this->dia(10)->toDateString(),
            'hora'             => '10:00',
        ], $datos));
    }

    /* ─────────────── 401 ─────────────── */

    public function test_endpoints_de_la_fase_2_responden_401_sin_token(): void
    {
        $rutas = [
            ['get', '/api/v1/proveedores'], ['get', '/api/v1/proveedores/categorias'],
            ['get', '/api/v1/proveedores/1'], ['get', '/api/v1/proveedores/1/disponibilidad'],
            ['get', '/api/v1/citas'], ['post', '/api/v1/citas'], ['get', '/api/v1/citas/1'],
            ['post', '/api/v1/citas/1/cancelar'], ['patch', '/api/v1/citas/1/notas'],
            ['post', '/api/v1/citas/1/reagenda/aceptar'], ['post', '/api/v1/citas/1/reagenda/rechazar'],
            ['get', '/api/v1/agendas'], ['get', '/api/v1/agendas/1'],
            ['post', '/api/v1/agendas/1/aceptar'], ['post', '/api/v1/agendas/1/rechazar'],
            ['get', '/api/v1/encuestas/pendientes'], ['get', '/api/v1/encuestas/1'],
            ['post', '/api/v1/encuestas/1/respuestas'],
        ];

        foreach ($rutas as [$metodo, $ruta]) {
            $this->json($metodo, $ruta)->assertStatus(401, "$metodo $ruta");
        }
    }

    /* ─────────────── Directorio ─────────────── */

    public function test_directorio_por_evento_y_de_plataforma_con_filtros(): void
    {
        $miel  = $this->proveedorCompleto('Miel Maya', ['municipio' => 'Tizimín']);
        $telas = $this->proveedorCompleto('Telas Xokbil', ['categoria' => 'Textiles']);
        $fuera = $this->proveedorCompleto('Solo plataforma', ['categoria' => 'Artesanías'], enEvento: false);
        Sanctum::actingAs($this->crearComprador());

        $r = $this->getJson('/api/v1/proveedores?evento_id=' . $this->evento->id)->assertOk()
            ->assertJsonPath('meta.evento.id', $this->evento->id)
            ->assertJsonPath('meta.orden', 'recientes')
            ->assertJsonStructure(['data' => [['id', 'nombre_negocio', 'categoria', 'municipio', 'descripcion', 'logo_url', 'citas_count']],
                'meta' => ['current_page', 'total', 'per_page', 'eventos']]);
        $this->assertEqualsCanonicalizing(['Miel Maya', 'Telas Xokbil'], collect($r->json('data'))->pluck('nombre_negocio')->all());
        $this->assertContains($this->evento->id, collect($r->json('meta.eventos'))->pluck('id')->all());
        $this->assertStringNotContainsString('Calle secreta', $r->getContent());
        $this->assertStringStartsWith('http', $r->json('data.0.logo_url'));

        // Plataforma: todos los de perfil completo, esten o no en el evento.
        $nombres = collect($this->getJson('/api/v1/proveedores?per_page=50')->assertOk()->json('data'))->pluck('nombre_negocio');
        $this->assertTrue($nombres->contains('Solo plataforma'));
        $this->assertFalse($nombres->contains('Negocio Test')); // perfil incompleto

        $this->getJson('/api/v1/proveedores?evento_id=' . $this->evento->id . '&search=tizim')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $miel->id);
        $this->getJson('/api/v1/proveedores?evento_id=' . $this->evento->id . '&categoria=Textiles')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $telas->id);

        $this->getJson('/api/v1/proveedores/categorias?evento_id=' . $this->evento->id)->assertOk()
            ->assertExactJson(['data' => ['Alimentos y Bebidas', 'Textiles']]);
        $this->assertContains('Artesanías', $this->getJson('/api/v1/proveedores/categorias')->json('data'));

        // Evento inactivo o inexistente: 422
        $inactivo = Evento::create(['nombre' => 'Viejo', 'fecha_inicio' => now()->toDateString(), 'activa' => false, 'tipo_evento' => 'encuentro_negocios']);
        $this->getJson('/api/v1/proveedores?evento_id=' . $inactivo->id)->assertStatus(422)->assertJsonValidationErrors(['evento_id']);
        $this->getJson('/api/v1/proveedores?orden=raro')->assertStatus(422)->assertJsonValidationErrors(['orden']);
    }

    public function test_directorio_ordenado_por_coincidencia_con_necesidades(): void
    {
        $this->proveedorCompleto('Café Puuc', ['descripcion' => 'Cafe de altura']);
        $miel = $this->proveedorCompleto('Miel Maya', ['descripcion' => 'Miel melipona']);
        Sanctum::actingAs($this->crearComprador('aprobado', ['necesidades' => 'Busco miel melipona']));

        $this->getJson('/api/v1/proveedores?evento_id=' . $this->evento->id . '&orden=coincidencia')->assertOk()
            ->assertJsonPath('data.0.id', $miel->id)
            ->assertJsonPath('data.0.coincidencias', 2)
            ->assertJsonPath('data.1.coincidencias', 0)
            ->assertJsonPath('meta.tiene_necesidades', true)
            ->assertJsonPath('meta.total', 2);
    }

    /* ─────────────── Ficha ─────────────── */

    public function test_ficha_publica_como_la_web_sin_datos_ocultos(): void
    {
        $r = $this->proveedorCompleto('Salsas', ['rfc' => 'SDC200101AB1', 'factura' => true, 'regimen_fiscal' => '601',
            'acepta_credito' => true, 'credito_a_negociar' => true, 'sitio_web' => 'https://salsas.mx']);
        Sanctum::actingAs($this->crearComprador());

        $res = $this->getJson('/api/v1/proveedores/' . $r->id)->assertOk()
            ->assertJsonPath('data.nombre_negocio', 'Salsas')
            ->assertJsonPath('data.contacto.telefono', '9990000000')
            ->assertJsonPath('data.contacto.rfc', 'SDC200101AB1')
            ->assertJsonPath('data.condiciones_comerciales.credito_a_negociar', true)
            ->assertJsonPath('data.condiciones_comerciales.regimen_fiscal', '601')
            ->assertJsonPath('data.logistica.entrega_domicilio', false)
            ->assertJsonPath('data.productos.0.nombre', 'Producto de Salsas')
            ->assertJsonPath('data.productos.0.capacidad_cantidad', 10);

        $this->assertStringStartsWith('http', $res->json('data.productos.0.foto_url'));
        foreach (['Calle secreta', 'direccion', 'interno-no-debe-salir', 'user_id', 'logo_path', 'foto_path', $r->user->email] as $fuga) {
            $this->assertStringNotContainsString($fuga, $res->getContent());
        }
        $this->assertSame(1, DB::table('page_visits')->where('restaurantero_id', $r->id)->count());

        $r->update(['activo' => false]);
        $this->getJson('/api/v1/proveedores/' . $r->id)->assertNotFound()->assertJsonPath('message', 'Proveedor no encontrado.');
        $this->getJson('/api/v1/proveedores/' . $r->id . '/disponibilidad')->assertNotFound();
        $this->getJson('/api/v1/proveedores/999999')->assertNotFound();
    }

    /* ─────────────── Disponibilidad ─────────────── */

    public function test_disponibilidad_slot_contiguo_libre_colchon_del_comprador_y_ocupado(): void
    {
        $comprador = $this->crearComprador();
        $otro = $this->crearComprador();
        $otroProveedor = $this->crearProveedor('Otro');
        $this->cita($otro, $this->restaurantero, $this->dia(10));             // proveedor ocupado 10:00-10:30
        $propia = $this->cita($comprador, $otroProveedor, $this->dia(12), 'confirmada'); // comprador ocupado 12:00-12:30
        $this->cita($otro, $this->restaurantero, $this->dia(14), 'cancelada'); // no bloquea
        Sanctum::actingAs($comprador);

        $fecha = $this->dia(9)->toDateString();
        $res = $this->getJson("/api/v1/proveedores/{$this->restaurantero->id}/disponibilidad?fecha={$fecha}")->assertOk()
            ->assertJsonPath('data.puede_agendar', true)
            ->assertJsonPath('data.motivo', null)
            ->assertJsonPath('data.evento.id', $this->evento->id)
            ->assertJsonPath('data.evento.ventana_agendado.abierta', true)
            ->assertJsonPath('data.duracion_cita_minutos', 30)
            ->assertJsonPath('data.intervalo_minutos', 30)
            ->assertJsonPath('data.colchon_minutos', 10)
            ->assertJsonPath('data.citas_usadas', 1)
            ->assertJsonPath('data.max_citas', 3)
            ->assertJsonPath('data.citas_restantes', 2)
            ->assertJsonCount(1, 'data.dias')
            ->json();

        $this->assertSame('09:00', $res['data']['dias'][0]['horarios'][0]['hora']);
        $this->assertSame('17:30', collect($res['data']['dias'][0]['horarios'])->last()['hora']);
        $this->assertSame('ocupado_proveedor', $this->horario($res, $fecha, '10:00')['estado']);
        $this->assertSame('libre', $this->horario($res, $fecha, '09:30')['estado']);  // contiguo antes
        $this->assertSame('libre', $this->horario($res, $fecha, '10:30')['estado']);  // contiguo despues
        $this->assertSame('bloqueado_por_tus_citas', $this->horario($res, $fecha, '11:30')['estado']); // termina 12:00, colchon
        $this->assertSame('bloqueado_por_tus_citas', $this->horario($res, $fecha, '12:30')['estado']); // empieza 12:30, colchon
        $this->assertSame('libre', $this->horario($res, $fecha, '13:00')['estado']);
        $this->assertSame('libre', $this->horario($res, $fecha, '14:00')['estado']); // cita cancelada
        $this->assertSame('bloqueado_por_tus_citas', $this->horario($res, $fecha, '12:00')['estado']);
        $this->assertSame($propia->id, $this->horario($res, $fecha, '12:00')['mi_cita_id']);
        $this->assertNull($this->horario($res, $fecha, '12:30')['mi_cita_id']);

        // Lo que la disponibilidad dice "libre" se puede agendar; lo contiguo tambien.
        $this->agendar(['hora' => '10:30'])->assertCreated();
        $this->agendar(['hora' => '11:30'])->assertStatus(422)->assertJsonValidationErrors(['fecha']);

        // Todos los dias del evento sin filtro; rango con desde/hasta.
        $this->getJson("/api/v1/proveedores/{$this->restaurantero->id}/disponibilidad")->assertOk()->assertJsonCount(2, 'data.dias');
        $this->getJson("/api/v1/proveedores/{$this->restaurantero->id}/disponibilidad?desde=" . $this->dia(9, 0, 1)->toDateString())
            ->assertOk()->assertJsonCount(1, 'data.dias');
        $this->getJson("/api/v1/proveedores/{$this->restaurantero->id}/disponibilidad?fecha=2026-13-01")
            ->assertStatus(422)->assertJsonValidationErrors(['fecha']);
    }

    public function test_disponibilidad_fuera_de_ventana_cupo_y_no_aprobado(): void
    {
        $comprador = $this->crearComprador();
        Sanctum::actingAs($comprador);
        $url = "/api/v1/proveedores/{$this->restaurantero->id}/disponibilidad?fecha=" . $this->dia(9)->toDateString();

        // Ventana del comprador aun cerrada
        $this->evento->update(['fecha_hora_inicio_compradores' => now()->addHours(2)]);
        $res = $this->getJson($url)->assertOk()
            ->assertJsonPath('data.puede_agendar', false)
            ->assertJsonPath('data.evento.ventana_agendado.abierta', false)
            ->json();
        $this->assertStringContainsString('El período de agendado aún no ha comenzado', $res['data']['motivo']);
        $this->assertSame(['fuera_de_ventana'], collect($res['data']['dias'][0]['horarios'])->pluck('estado')->unique()->values()->all());

        // Cupo lleno
        $this->evento->update(['fecha_hora_inicio_compradores' => null, 'max_citas_por_comprador' => 1]);
        $this->cita($comprador, $this->crearProveedor('X'), $this->dia(15));
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.puede_agendar', false)
            ->assertJsonPath('data.motivo', 'Has alcanzado el límite de 1 citas en este evento.')
            ->assertJsonPath('data.citas_restantes', 0);

        // Comprador pendiente de aprobacion: ve los horarios pero no puede agendar
        Sanctum::actingAs($this->crearComprador('pendiente'));
        $this->getJson($url)->assertOk()
            ->assertJsonPath('data.puede_agendar', false)
            ->assertJsonPath('data.motivo', 'Tu solicitud de registro al evento está pendiente de aprobación. Recibirás una notificación cuando sea aprobada.');
    }

    /* ─────────────── Alta de citas ─────────────── */

    public function test_agendar_crea_la_cita_y_avisa_como_la_web(): void
    {
        $comprador = $this->crearComprador();
        Sanctum::actingAs($comprador);

        $res = $this->agendar(['notas' => 'Quiero muestras', 'productos_interes' => ['Salsa']])
            ->assertCreated()
            ->assertJsonPath('message', 'Cita solicitada correctamente.')
            ->assertJsonPath('data.estado', 'pendiente')
            ->assertJsonPath('data.estado_texto', 'Pendiente')
            ->assertJsonPath('data.notas', 'Quiero muestras')
            ->assertJsonPath('data.productos_interes', ['Salsa'])
            ->assertJsonPath('data.evento.id', $this->evento->id)
            ->assertJsonPath('data.proveedor.id', $this->restaurantero->id)
            ->assertJsonPath('data.puede_cancelar', true)
            ->assertJsonPath('data.propuesta_reagenda', null);

        $this->assertArrayNotHasKey('comprador', $res->json('data'));
        $this->assertSame($this->dia(10)->toIso8601String(), $res->json('data.inicio'));
        Mail::assertQueued(CitaAgendada::class, 2);
        $this->assertTrue(Notificacion::where('user_id', $this->restaurantero->user_id)->where('titulo', 'Nueva cita solicitada')->exists());
    }

    public function test_agendar_valida_y_aplica_las_reglas_del_servidor(): void
    {
        $comprador = $this->crearComprador();
        Sanctum::actingAs($comprador);

        $this->postJson('/api/v1/citas', [])->assertStatus(422)
            ->assertJsonValidationErrors(['restaurantero_id', 'fecha', 'hora']);
        $this->agendar(['hora' => '10am'])->assertStatus(422)->assertJsonValidationErrors(['hora']);
        $this->agendar(['fecha' => now()->subDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors(['fecha']);

        // Doble reserva del mismo horario con el proveedor
        $this->agendar()->assertCreated();
        Sanctum::actingAs($this->crearComprador());
        $this->agendar()->assertStatus(422)
            ->assertJsonPath('errors.fecha.0', 'Este horario ya no está disponible con el proveedor. Por favor elige otro.');
        $this->assertSame(1, Cita::where('restaurantero_id', $this->restaurantero->id)->count());

        // Cupo
        $this->evento->update(['max_citas_por_comprador' => 1]);
        Sanctum::actingAs($comprador);
        $this->agendar(['restaurantero_id' => $this->crearProveedor('Y')->id, 'hora' => '15:00'])
            ->assertStatus(422)->assertJsonPath('errors.limit.0', 'Has alcanzado el límite de 1 citas en este evento.');

        // No aprobado
        Sanctum::actingAs($this->crearComprador(null));
        $this->agendar(['hora' => '16:00'])->assertStatus(422)
            ->assertJsonPath('errors.error.0', 'Debes estar registrado y aprobado en el evento para agendar citas.');

        // Admin no agenda para si
        Sanctum::actingAs($this->crearAdmin());
        $this->agendar(['hora' => '16:00'])->assertStatus(422)
            ->assertJsonPath('errors.error.0', 'El administrador no puede agendar citas para sí mismo. Usa el panel de administración para gestionar citas de clientes.');
    }

    public function test_dos_altas_al_mismo_horario_dejan_una_sola_cita(): void
    {
        // Las altas se serializan con lockForUpdate sobre comprador y proveedor
        // dentro de la transaccion; aqui se comprueba el resultado de dos
        // compradores distintos compitiendo por el mismo hueco.
        $a = $this->crearComprador();
        $b = $this->crearComprador();

        Sanctum::actingAs($a);
        $ok = $this->agendar(['hora' => '11:00']);
        Sanctum::actingAs($b);
        $ko = $this->agendar(['hora' => '11:00']);

        $this->assertSame([201, 422], [$ok->status(), $ko->status()]);
        $this->assertSame(1, Cita::where('restaurantero_id', $this->restaurantero->id)
            ->where('inicio', $this->dia(11))->whereNotIn('estado', ['cancelada', 'rechazada'])->count());
    }

    /* ─────────────── Mis citas ─────────────── */

    public function test_mis_citas_filtros_orden_y_detalle(): void
    {
        $comprador = $this->crearComprador();
        $ajeno = $this->crearComprador();
        $p2 = $this->crearProveedor('Dos');
        $tarde  = $this->cita($comprador, $this->restaurantero, $this->dia(15), 'confirmada');
        $temprano = $this->cita($comprador, $p2, $this->dia(9), 'pendiente');
        $cancelada = $this->cita($comprador, $p2, $this->dia(11), 'cancelada');
        $deOtro = $this->cita($ajeno, $this->restaurantero, $this->dia(12));
        Sanctum::actingAs($comprador);

        $this->getJson('/api/v1/citas')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $temprano->id)
            ->assertJsonPath('data.2.id', $tarde->id)
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/citas?estado=pendiente,confirmada')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/citas?estado=cancelada')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.puede_cancelar', false);
        $this->getJson('/api/v1/citas?estado=inventado')->assertStatus(422)->assertJsonValidationErrors(['estado']);
        $this->getJson('/api/v1/citas?evento_id=999999')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/citas?desde=' . $this->dia(0, 0, 1)->toDateString())->assertOk()->assertJsonCount(0, 'data');

        $this->getJson('/api/v1/citas/' . $tarde->id)->assertOk()->assertJsonPath('data.id', $tarde->id);
        $this->getJson('/api/v1/citas/' . $deOtro->id)->assertForbidden();
        $this->getJson('/api/v1/citas/999999')->assertNotFound();

        // El proveedor de la cita si ve al comprador
        Sanctum::actingAs($this->restaurantero->user);
        $this->getJson('/api/v1/citas/' . $tarde->id)->assertOk()
            ->assertJsonPath('data.comprador.id', $comprador->id)
            ->assertJsonPath('data.puede_cancelar', false);
    }

    public function test_cancelar_y_notas(): void
    {
        $comprador = $this->crearComprador();
        $ajeno = $this->crearComprador();
        $cita = $this->cita($comprador, $this->restaurantero, $this->dia(10));
        $completada = $this->cita($comprador, $this->crearProveedor('Z'), $this->dia(12), 'completada');

        Sanctum::actingAs($ajeno);
        $this->postJson("/api/v1/citas/{$cita->id}/cancelar")->assertForbidden();
        $this->patchJson("/api/v1/citas/{$cita->id}/notas", ['notas' => 'x'])->assertForbidden();
        $this->assertSame('pendiente', $cita->fresh()->estado);

        Sanctum::actingAs($comprador);
        $this->postJson("/api/v1/citas/{$completada->id}/cancelar")->assertStatus(422)
            ->assertJsonPath('errors.error.0', 'No puedes cancelar esta cita.');

        $this->patchJson("/api/v1/citas/{$cita->id}/notas", ['notas' => str_repeat('a', 1001)])
            ->assertStatus(422)->assertJsonValidationErrors(['notas']);
        $this->patchJson("/api/v1/citas/{$cita->id}/notas", ['notas' => 'Llevar catálogo'])->assertOk()
            ->assertJsonPath('message', 'Notas actualizadas.')
            ->assertJsonPath('data.notas', 'Llevar catálogo');

        $this->postJson("/api/v1/citas/{$cita->id}/cancelar")->assertOk()
            ->assertJsonPath('message', 'Cita cancelada correctamente.')
            ->assertJsonPath('data.estado', 'cancelada')
            ->assertJsonPath('data.puede_cancelar', false);
        Mail::assertQueued(CitaCancelada::class, fn ($m) => $m->hasTo($comprador->email));
        $this->postJson("/api/v1/citas/{$cita->id}/cancelar")->assertStatus(422);
    }

    /* ─────────────── Reagendas ─────────────── */

    private function citaConPropuesta(User $comprador, Carbon $inicio, Carbon $propuesta): Cita
    {
        return $this->cita($comprador, $this->restaurantero, $inicio, 'pendiente_reconfirmacion', [
            'propuesta_inicio'   => $propuesta,
            'propuesta_fin'      => $propuesta->copy()->addMinutes(30),
            'token_confirmacion' => Str::random(64),
        ]);
    }

    public function test_reagenda_aceptar_desde_la_app(): void
    {
        $comprador = $this->crearComprador();
        $cita = $this->citaConPropuesta($comprador, $this->dia(10), $this->dia(13));
        Sanctum::actingAs($comprador);

        $this->getJson('/api/v1/citas/' . $cita->id)->assertOk()
            ->assertJsonPath('data.estado', 'pendiente_reconfirmacion')
            ->assertJsonPath('data.propuesta_reagenda.inicio', $this->dia(13)->toIso8601String())
            ->assertJsonPath('data.propuesta_reagenda.fin', $this->dia(13, 30)->toIso8601String())
            ->assertJsonPath('data.puede_responder_reagenda', true);

        Sanctum::actingAs($this->crearComprador());
        $this->postJson("/api/v1/citas/{$cita->id}/reagenda/aceptar")->assertForbidden();

        Sanctum::actingAs($comprador);
        $this->postJson("/api/v1/citas/{$cita->id}/reagenda/aceptar")->assertOk()
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.inicio', $this->dia(13)->toIso8601String())
            ->assertJsonPath('data.propuesta_reagenda', null);
        $this->assertNull($cita->fresh()->token_confirmacion);
        Mail::assertQueued(CitaReagendada::class, fn ($m) => $m->hasTo($this->restaurantero->user->email));

        $this->postJson("/api/v1/citas/{$cita->id}/reagenda/aceptar")->assertStatus(422)
            ->assertJsonPath('errors.error.0', 'Esta cita no tiene una propuesta de cambio de horario pendiente.');
    }

    public function test_reagenda_aceptar_con_choque_da_422_y_la_cita_queda_intacta(): void
    {
        $comprador = $this->crearComprador();
        $cita = $this->citaConPropuesta($comprador, $this->dia(10), $this->dia(13));
        $this->cita($this->crearComprador(), $this->restaurantero, $this->dia(13), 'confirmada');
        Sanctum::actingAs($comprador);

        $this->postJson("/api/v1/citas/{$cita->id}/reagenda/aceptar")->assertStatus(422)
            ->assertJsonPath('errors.error.0', 'El horario propuesto ya no está disponible. Tu cita conserva su horario original; el proveedor te propondrá otro.');

        $cita->refresh();
        $this->assertSame('pendiente_reconfirmacion', $cita->estado);
        $this->assertTrue($cita->inicio->equalTo($this->dia(10)));
        $this->assertNotNull($cita->token_confirmacion);
        $this->assertTrue(Notificacion::where('user_id', $this->restaurantero->user_id)->where('titulo', 'Reagenda no aplicada')->exists());
        Mail::assertNotQueued(CitaReagendada::class);
    }

    public function test_reagenda_rechazar_desde_la_app(): void
    {
        $comprador = $this->crearComprador();
        $cita = $this->citaConPropuesta($comprador, $this->dia(10), $this->dia(13));
        Sanctum::actingAs($comprador);

        $this->postJson("/api/v1/citas/{$cita->id}/reagenda/rechazar")->assertOk()
            ->assertJsonPath('message', 'Has rechazado la propuesta. La cita quedó cancelada.')
            ->assertJsonPath('data.estado', 'cancelada');
        Mail::assertQueued(CitaCancelada::class, fn ($m) => $m->hasTo($this->restaurantero->user->email));
        $this->assertTrue(Notificacion::where('user_id', $this->restaurantero->user_id)->where('titulo', 'Propuesta de reagenda rechazada')->exists());

        // El enlace del correo ya no sirve
        $this->post("/citas/{$cita->id}/rechazar/{$cita->token_confirmacion}")->assertNotFound();
    }

    /* ─────────────── Agendas propuestas ─────────────── */

    private function propuesta(User $comprador, array $proveedores, array $datos = []): AgendaPropuesta
    {
        $p = AgendaPropuesta::create(array_merge([
            'evento_id' => $this->evento->id, 'user_id' => $comprador->id, 'admin_id' => $this->crearAdmin()->id,
            'estado' => 'pendiente', 'token' => Str::random(64), 'enviada_at' => now(),
        ], $datos));
        foreach ($proveedores as $i => $r) {
            AgendaPropuestaCita::create([
                'agenda_propuesta_id' => $p->id, 'restaurantero_id' => $r->id,
                'slot_inicio' => $this->dia(10 + $i), 'slot_fin' => $this->dia(10 + $i, 30),
            ]);
        }

        return $p;
    }

    public function test_agendas_lista_detalle_y_ajenas(): void
    {
        $comprador = $this->crearComprador();
        $vieja = $this->propuesta($comprador, [$this->restaurantero], ['estado' => 'rechazada', 'enviada_at' => now()->subDay()]);
        $pendiente = $this->propuesta($comprador, [$this->restaurantero, $this->crearProveedor('Dos')], ['enviada_at' => now()->subDays(2)]);
        $borrador = $this->propuesta($comprador, [$this->restaurantero], ['enviada_at' => null, 'token' => null]);
        $ajena = $this->propuesta($this->crearComprador(), [$this->restaurantero]);
        Sanctum::actingAs($comprador);

        $this->getJson('/api/v1/agendas')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $pendiente->id)   // pendientes primero
            ->assertJsonPath('data.0.puede_responder', true)
            ->assertJsonPath('data.0.citas.0.proveedor.nombre_negocio', 'Negocio Test')
            ->assertJsonPath('data.1.id', $vieja->id)
            ->assertJsonPath('data.1.puede_responder', false);

        $res = $this->getJson('/api/v1/agendas/' . $pendiente->id)->assertOk()
            ->assertJsonCount(2, 'data.citas')
            ->assertJsonPath('data.citas.0.inicio', $this->dia(10)->toIso8601String());
        $this->assertStringNotContainsString('token', $res->getContent());

        $this->getJson('/api/v1/agendas/' . $ajena->id)->assertForbidden();
        $this->getJson('/api/v1/agendas/' . $borrador->id)->assertForbidden();
        $this->postJson('/api/v1/agendas/' . $ajena->id . '/aceptar')->assertForbidden();
        $this->assertSame('pendiente', $ajena->fresh()->estado);
    }

    public function test_agenda_aceptar_y_rechazar_desde_la_app(): void
    {
        $comprador = $this->crearComprador();
        $dos = $this->crearProveedor('Dos');
        $p = $this->propuesta($comprador, [$this->restaurantero, $dos]);
        Sanctum::actingAs($comprador);

        $this->postJson('/api/v1/agendas/' . $p->id . '/aceptar')->assertOk()
            ->assertJsonPath('data.estado', 'aceptada')
            ->assertJsonPath('data.puede_responder', false);
        $this->assertSame(2, Cita::where('cliente_id', $comprador->id)->where('estado', 'confirmada')->count());
        $this->assertSame(2, $p->citas()->whereNotNull('cita_id')->count());
        $this->assertTrue(Notificacion::where('user_id', $dos->user_id)->where('titulo', '¡Nueva cita confirmada!')->exists());

        $this->postJson('/api/v1/agendas/' . $p->id . '/aceptar')->assertStatus(422)
            ->assertJsonPath('errors.error.0', 'Esta propuesta de agenda ya fue respondida.');
        $this->postJson('/api/v1/agendas/' . $p->id . '/rechazar')->assertStatus(422);
        $this->assertSame(2, Cita::where('cliente_id', $comprador->id)->count());

        // El enlace del correo muestra "ya respondida"
        $props = $this->post('/agenda/aceptar/' . $p->token)->assertOk()->viewData('page')['props'];
        $this->assertTrue($props['yaRespondida']);

        $admin = $this->crearAdmin();
        $p2 = $this->propuesta($comprador, [$dos]);
        $this->postJson('/api/v1/agendas/' . $p2->id . '/rechazar')->assertOk()->assertJsonPath('data.estado', 'rechazada');
        $this->assertTrue(Notificacion::where('user_id', $admin->id)->where('titulo', 'Propuesta de agenda rechazada')->exists());

        $sinServicio = $this->crearProveedor('Sin servicio');
        $sinServicio->servicios()->update(['activo' => false]);
        $p3 = $this->propuesta($comprador, [$sinServicio]);
        $this->postJson('/api/v1/agendas/' . $p3->id . '/aceptar')->assertStatus(422)
            ->assertJsonPath('errors.error.0', 'Uno de los proveedores de esta agenda no tiene un servicio activo para agendar citas.');
        $this->assertSame('pendiente', $p3->fresh()->estado);
    }

    /* ─────────────── Encuestas ─────────────── */

    private function encuesta(User $user, array $datos = []): EncuestaSatisfaccion
    {
        return EncuestaSatisfaccion::create(array_merge([
            'evento_id' => $this->evento->id, 'user_id' => $user->id, 'tipo' => 'comprador', 'token' => Str::random(40),
        ], $datos));
    }

    public function test_encuestas_pendientes_detalle_y_ajenas(): void
    {
        $comprador = $this->crearComprador();
        $pendiente = $this->encuesta($comprador);
        $otroEvento = Evento::create(['nombre' => 'Otro', 'fecha_inicio' => now()->toDateString(), 'activa' => false]);
        $this->encuesta($comprador, ['evento_id' => $otroEvento->id, 'completada_at' => now()]);
        $prueba = $this->encuesta($comprador, ['tipo' => 'proveedor', 'es_prueba' => true]);
        $ajena = $this->encuesta($this->crearComprador());
        Sanctum::actingAs($comprador);

        $this->getJson('/api/v1/encuestas/pendientes')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $pendiente->id)
            ->assertJsonPath('data.0.evento.nombre', 'Encuentro Test')
            ->assertJsonMissingPath('data.0.preguntas');

        $res = $this->getJson('/api/v1/encuestas/' . $pendiente->id)->assertOk()
            ->assertJsonPath('data.completada', false)
            ->assertJsonPath('data.preguntas.0.id', 'experiencia_general')
            ->assertJsonPath('data.preguntas.0.escala_min', 1)
            ->assertJsonPath('data.preguntas.0.escala_max', 5)
            ->assertJsonPath('data.preguntas.0.requerida', true)
            ->assertJsonPath('data.preguntas.5.valores', ['Sí', 'No']);
        $this->assertStringNotContainsString($pendiente->token, $res->getContent());

        $this->getJson('/api/v1/encuestas/' . $ajena->id)->assertForbidden();
        $this->getJson('/api/v1/encuestas/' . $prueba->id)->assertForbidden();
        $this->postJson('/api/v1/encuestas/' . $ajena->id . '/respuestas', ['experiencia_general' => 5])->assertForbidden();
    }

    public function test_responder_encuesta_valida_requeridas_y_condiciones(): void
    {
        $plantilla = EncuestaPlantilla::create([
            'nombre' => 'P', 'activa' => true, 'segmento' => 'todos',
            'preguntas' => [
                ['id' => 'perfil', 'tipo' => 'opciones', 'texto' => 'Perfil', 'requerida' => true, 'opciones' => ['Comprador', 'Proveedor']],
                ['id' => 'c_oferta', 'tipo' => 'escala', 'texto' => 'Oferta', 'escala_min' => 1, 'escala_max' => 10,
                    'condicion' => ['pregunta_id' => 'perfil', 'operador' => 'igual', 'valor' => 'Comprador']],
                ['id' => 'comentario', 'tipo' => 'texto', 'texto' => 'Comentario', 'requerida' => false],
                ['id' => 'temas', 'tipo' => 'multiple', 'texto' => 'Temas', 'requerida' => false, 'opciones' => ['A', 'B']],
            ],
        ]);
        $comprador = $this->crearComprador();
        $e = $this->encuesta($comprador, ['encuesta_plantilla_id' => $plantilla->id]);
        Sanctum::actingAs($comprador);
        $url = '/api/v1/encuestas/' . $e->id . '/respuestas';

        $this->postJson($url, [])->assertStatus(422)->assertJsonPath('errors.perfil.0', 'Este campo es requerido.');
        $this->postJson($url, ['perfil' => 'Otro'])->assertStatus(422)->assertJsonValidationErrors(['perfil']);
        $this->postJson($url, ['perfil' => 'Comprador'])->assertStatus(422)->assertJsonPath('errors.c_oferta.0', 'Este campo es requerido.');
        $this->postJson($url, ['perfil' => 'Comprador', 'c_oferta' => 11])->assertStatus(422)->assertJsonValidationErrors(['c_oferta']);
        $this->postJson($url, ['perfil' => 'Comprador', 'c_oferta' => 8, 'temas' => ['C']])->assertStatus(422)->assertJsonValidationErrors(['temas.0']);
        $this->assertSame(0, $e->respuestas()->count());

        // Proveedor: la pregunta condicionada no aplica
        $this->postJson($url, ['perfil' => 'Proveedor', 'temas' => ['A', 'B']])->assertOk()
            ->assertJsonPath('message', '¡Gracias por responder la encuesta!')
            ->assertJsonPath('data.completada', true);
        $this->assertSame(['Perfil' => 'Proveedor', 'Temas' => 'A, B'], $e->respuestas()->pluck('respuesta', 'pregunta')->all());

        $this->postJson($url, ['perfil' => 'Comprador', 'c_oferta' => 5])->assertStatus(422)
            ->assertJsonPath('errors.error.0', 'Esta encuesta ya fue respondida.');
        $this->getJson('/api/v1/encuestas/pendientes')->assertOk()->assertJsonCount(0, 'data');
    }

    /* ─────────────── Inicio ─────────────── */

    public function test_inicio_trae_los_pendientes_del_comprador_sin_romper_lo_existente(): void
    {
        $comprador = $this->crearComprador();
        $this->citaConPropuesta($comprador, $this->dia(10), $this->dia(13));
        $this->propuesta($comprador, [$this->restaurantero]);
        $this->propuesta($comprador, [$this->restaurantero], ['enviada_at' => null]);
        $this->encuesta($comprador);
        Sanctum::actingAs($comprador);

        $this->getJson('/api/v1/inicio')->assertOk()
            ->assertJsonPath('data.pendientes.reagendas_por_confirmar', 1)
            ->assertJsonPath('data.pendientes.agendas_por_responder', 1)
            ->assertJsonPath('data.pendientes.encuestas_por_responder', 1)
            ->assertJsonStructure(['data' => ['usuario', 'onboarding', 'vista', 'proxima_cita', 'citas_proximas_count',
                'evento_destacado', 'notificaciones_sin_leer', 'publicidad']]);
    }
}
