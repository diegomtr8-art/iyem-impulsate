<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

class CitaDobleBookingTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    protected function setUp(): void
    {
        parent::setUp();

        // El modelo Edicion ya no existe desde el renombre a Evento (junio);
        // estas pruebas llevaban rotas desde entonces.
        $this->crearEscenario();
    }

    public function test_cliente_can_book_available_slot(): void
    {
        $this->withoutExceptionHandling();

        $cliente = $this->crearComprador();

        $response = $this->actingAs($cliente)->post(route('citas.store'), [
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => now()->addDay()->toDateString(),
            'hora'             => '10:00',
        ]);

        $response->assertRedirect(route('user.dashboard'));
        $this->assertDatabaseCount('citas', 1);
        $this->assertDatabaseHas('citas', [
            'restaurantero_id' => $this->restaurantero->id,
            'cliente_id'       => $cliente->id,
            'estado'           => 'pendiente',
        ]);
    }

    public function test_same_slot_cannot_be_booked_twice(): void
    {
        $clienteA = $this->crearComprador();

        $clienteB = $this->crearComprador();

        $payload = [
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => now()->addDay()->toDateString(),
            'hora'             => '10:00',
        ];

        // First booking succeeds
        $responseA = $this->actingAs($clienteA)->post(route('citas.store'), $payload);
        $responseA->assertRedirect(route('user.dashboard'));

        // Second booking for the same slot must fail
        $responseB = $this->actingAs($clienteB)->post(route('citas.store'), $payload);
        $responseB->assertSessionHasErrors('fecha');

        // Only one cita must exist in the database
        $this->assertDatabaseCount('citas', 1);
    }

    public function test_overlapping_slot_within_service_duration_is_blocked(): void
    {
        $clienteA = $this->crearComprador();

        $clienteB = $this->crearComprador();

        $fecha = now()->addDay()->toDateString();

        // First booking: 10:00–10:30
        $this->actingAs($clienteA)->post(route('citas.store'), [
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => $fecha,
            'hora'             => '10:00',
        ]);

        // Second booking: 10:15 — overlaps with the first (10:00–10:30)
        $responseB = $this->actingAs($clienteB)->post(route('citas.store'), [
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => $fecha,
            'hora'             => '10:15',
        ]);

        $responseB->assertSessionHasErrors('fecha');
        $this->assertDatabaseCount('citas', 1);
    }

    public function test_cancelled_slot_can_be_rebooked(): void
    {
        $clienteA = $this->crearComprador();

        $clienteB = $this->crearComprador();

        $payload = [
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => now()->addDay()->toDateString(),
            'hora'             => '10:00',
        ];

        // First booking
        $this->actingAs($clienteA)->post(route('citas.store'), $payload);

        // Cancel it
        $cita = Cita::first();
        $cita->update(['estado' => 'cancelada']);

        // Another client books the same slot — must succeed now
        $responseB = $this->actingAs($clienteB)->post(route('citas.store'), $payload);
        $responseB->assertRedirect(route('user.dashboard'));

        $this->assertDatabaseCount('citas', 2);
        $this->assertDatabaseHas('citas', [
            'cliente_id' => $clienteB->id,
            'estado'     => 'pendiente',
        ]);
    }

    public function test_admin_cannot_double_book_same_slot(): void
    {
        $admin = $this->crearAdmin();

        $clienteA = $this->crearComprador();

        $clienteB = $this->crearComprador();

        $fecha = now()->addDay()->toDateString();

        $this->actingAs($admin)->post(route('admin.citas.store'), [
            'cliente_id'       => $clienteA->id,
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => $fecha,
            'hora'             => '10:00',
            'duracion'         => 30,
        ]);

        $responseB = $this->actingAs($admin)->post(route('admin.citas.store'), [
            'cliente_id'       => $clienteB->id,
            'restaurantero_id' => $this->restaurantero->id,
            'fecha'            => $fecha,
            'hora'             => '10:00',
            'duracion'         => 30,
        ]);

        $responseB->assertSessionHasErrors('fecha');
        $this->assertDatabaseCount('citas', 1);
    }
}
