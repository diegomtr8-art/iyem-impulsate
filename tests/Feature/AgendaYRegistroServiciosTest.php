<?php

namespace Tests\Feature;

use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/**
 * Reglas de AgendaCitaService y RegistroEventoService vistas desde la web:
 * la extraccion no debe cambiar lo que el usuario ve.
 */
class AgendaYRegistroServiciosTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->crearEscenario(['tiempo_entre_citas_minutos' => 20]);
    }

    private function agendar($comprador, $restauranteroId, string $hora)
    {
        return $this->actingAs($comprador)->post(route('citas.store'), [
            'restaurantero_id' => $restauranteroId,
            'fecha'            => now()->addDay()->toDateString(),
            'hora'             => $hora,
        ]);
    }

    public function test_la_duracion_publica_es_la_del_evento_no_la_del_servicio(): void
    {
        $this->agendar($this->crearComprador(), $this->restaurantero->id, '10:00')
            ->assertRedirect(route('user.dashboard'));

        $cita = Cita::first();
        $this->assertSame(20, (int) $cita->inicio->diffInMinutes($cita->fin));
    }

    public function test_el_admin_conserva_la_duracion_que_elige(): void
    {
        $this->actingAs($this->crearAdmin())->post(route('admin.citas.store'), [
            'cliente_id'       => $this->crearComprador()->id,
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => now()->addDay()->toDateString(),
            'hora'             => '10:00',
            'duracion'         => 45,
        ])->assertSessionHasNoErrors();

        $cita = Cita::first();
        $this->assertSame(45, (int) $cita->inicio->diffInMinutes($cita->fin));
        $this->assertSame('confirmada', $cita->estado);
    }

    public function test_horarios_contiguos_del_proveedor_se_pueden_agendar(): void
    {
        // Sin colchon en el proveedor: 10:00-10:20 y 10:20-10:40 con dos compradores.
        $this->agendar($this->crearComprador(), $this->restaurantero->id, '10:00')->assertSessionHasNoErrors();
        $this->agendar($this->crearComprador(), $this->restaurantero->id, '10:20')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('citas', 2);
    }

    public function test_el_comprador_necesita_10_minutos_entre_citas(): void
    {
        $comprador = $this->crearComprador();
        $otro = $this->crearProveedor('Otro negocio');

        $this->agendar($comprador, $this->restaurantero->id, '10:00')->assertSessionHasNoErrors();
        $this->agendar($comprador, $otro->id, '10:25')->assertSessionHasErrors('fecha');
        $this->agendar($comprador, $otro->id, '10:30')->assertSessionHasNoErrors();
    }

    public function test_el_admin_tampoco_cuenta_canceladas_ni_rechazadas_para_el_cupo(): void
    {
        $comprador = $this->crearComprador();
        $dia = now()->addDay()->toDateString();
        foreach (['09:00' => 'cancelada', '10:00' => 'rechazada', '11:00' => 'confirmada'] as $hora => $estado) {
            Cita::create([
                'edicion_id'       => $this->evento->id,
                'restaurantero_id' => $this->restaurantero->id,
                'servicio_id'      => $this->servicio->id,
                'cliente_id'       => $comprador->id,
                'inicio'           => Carbon::parse("$dia $hora"),
                'fin'              => Carbon::parse("$dia $hora")->addMinutes(20),
                'estado'           => $estado,
            ]);
        }

        $this->actingAs($this->crearAdmin())->post(route('admin.citas.store'), [
            'cliente_id'       => $comprador->id,
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => $dia,
            'hora'             => '12:00',
            'duracion'         => 20,
        ])->assertSessionHasNoErrors();
    }

    public function test_confirmar_reagenda_no_pisa_un_horario_ya_ocupado(): void
    {
        $this->agendar($this->crearComprador(), $this->restaurantero->id, '10:00');
        $cita = Cita::first();

        $inicioPropuesto = Carbon::parse(now()->addDay()->toDateString() . ' 12:00');
        $cita->update([
            'estado'             => 'pendiente_reconfirmacion',
            'propuesta_inicio'   => $inicioPropuesto,
            'propuesta_fin'      => $inicioPropuesto->copy()->addMinutes(20),
            'token_confirmacion' => 'tok-123',
        ]);

        // Otro comprador toma ese hueco mientras la propuesta espera respuesta.
        $this->agendar($this->crearComprador(), $this->restaurantero->id, '12:00')->assertSessionHasNoErrors();

        $this->post(route('citas.confirmar-token.post', [$cita->id, 'tok-123']))
            ->assertOk()
            ->assertSee('ya no está disponible');

        $cita->refresh();
        $this->assertSame('pendiente_reconfirmacion', $cita->estado);
        $this->assertSame('10:00', $cita->inicio->format('H:i'));
    }

    public function test_registro_web_de_comprador_queda_pendiente_y_no_se_duplica(): void
    {
        $comprador = $this->crearComprador(null);

        $this->actingAs($comprador)->post(route('evento.registrar-comprador', $this->evento))
            ->assertSessionHas('success', 'Tu solicitud de registro fue enviada. El administrador la revisará pronto.');

        $this->actingAs($comprador)->post(route('evento.registrar-comprador', $this->evento))
            ->assertSessionHas('success', 'Tu solicitud ya está en revisión. Te avisaremos cuando sea aprobada.');

        $this->assertSame(1, DB::table('evento_usuario')->where('user_id', $comprador->id)->count());
        $this->assertSame('pendiente', DB::table('evento_usuario')->where('user_id', $comprador->id)->value('estado'));
    }

    public function test_registro_web_fuera_de_ventana_devuelve_el_error_de_siempre(): void
    {
        $this->evento->update(['fecha_hora_inicio_compradores' => now()->addDays(5)]);

        $this->actingAs($this->crearComprador(null))
            ->post(route('evento.registrar-comprador', $this->evento))
            ->assertSessionHasErrors('error');

        $this->assertDatabaseCount('evento_usuario', 0);
    }
}
