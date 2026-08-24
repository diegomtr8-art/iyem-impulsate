<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Edicion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CitaDobleBookingTest extends TestCase
{
    use RefreshDatabase;

    private Edicion $edicion;
    private Restaurantero $restaurantero;
    private Servicio $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        // Spatie Permission caches role data in memory across tests.
        // RefreshDatabase rolls back the DB but not the cache, which causes
        // ModelNotFoundException on hasRole() calls — resulting in 404 responses.
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Ensure required roles exist
        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'web']);

        $this->edicion = Edicion::create([
            'nombre'       => 'Edición Test',
            'fecha_inicio' => now()->toDateString(),
            'activa'       => true,
        ]);

        $providerUser = User::factory()->create();

        $this->restaurantero = Restaurantero::create([
            'edicion_id'        => $this->edicion->id,
            'user_id'           => $providerUser->id,
            'nombre_restaurante' => 'Negocio Test',
            'activo'            => true,
        ]);

        $this->servicio = Servicio::create([
            'restaurantero_id' => $this->restaurantero->id,
            'nombre'           => 'Servicio Test',
            'duracion_minutos' => 30,
            'activo'           => true,
        ]);
    }

    public function test_cliente_can_book_available_slot(): void
    {
        $this->withoutExceptionHandling();

        $cliente = User::factory()->create();
        $cliente->assignRole('cliente');

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
        $clienteA = User::factory()->create();
        $clienteA->assignRole('cliente');

        $clienteB = User::factory()->create();
        $clienteB->assignRole('cliente');

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
        $clienteA = User::factory()->create();
        $clienteA->assignRole('cliente');

        $clienteB = User::factory()->create();
        $clienteB->assignRole('cliente');

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
        $clienteA = User::factory()->create();
        $clienteA->assignRole('cliente');

        $clienteB = User::factory()->create();
        $clienteB->assignRole('cliente');

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
        $admin = User::factory()->create();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');

        $clienteA = User::factory()->create();
        $clienteA->assignRole('cliente');

        $clienteB = User::factory()->create();
        $clienteB->assignRole('cliente');

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
