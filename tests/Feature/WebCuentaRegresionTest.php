<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/**
 * Regresion de los controladores web cuya logica se movio a servicios
 * (OnboardingService, PerfilCompradorService, DocumentosUsuarioService,
 * CambioRolService, NotificacionService): mismos mensajes, redirecciones y
 * efectos que antes del cambio.
 */
class WebCuentaRegresionTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->crearEscenario(['destacado' => true]);
    }

    public function test_aceptar_aviso_valida_y_redirige_a_seleccionar_rol(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from('/aceptar-aviso')->post('/aceptar-aviso', [])
            ->assertRedirect('/aceptar-aviso')
            ->assertSessionHasErrors(['acepta' => 'Debes aceptar el Aviso de Privacidad para continuar.']);

        $this->actingAs($user)->post('/aceptar-aviso', ['acepta' => true])
            ->assertRedirect(route('rol.seleccionar'));

        $this->assertNotNull($user->fresh()->acepta_aviso_at);
    }

    public function test_seleccionar_rol_proveedor_y_ambos(): void
    {
        $user = User::factory()->create(['acepta_aviso_at' => now()]);

        $this->actingAs($user)->from('/seleccionar-rol')->post('/seleccionar-rol', ['rol' => 'nada'])
            ->assertRedirect('/seleccionar-rol')
            ->assertSessionHasErrors('rol');

        $this->actingAs($user)->post('/seleccionar-rol', ['rol' => 'ambos'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', '¡Bienvenido a Impulsate!');

        $user->refresh();
        $this->assertTrue($user->hasRole('cliente') && $user->hasRole('restaurantero'));
        $this->assertSame('comprador', $user->active_role);
        $this->assertTrue((bool) $user->rol_seleccionado);
        $this->assertSame(5, $user->restaurantero->horarios()->count());
        $this->assertSame('Mesa de Networking', $user->restaurantero->servicios()->first()->nombre);
    }

    public function test_completar_perfil_redirige_al_dashboard_e_inscribe_pendiente(): void
    {
        $user = $this->crearComprador(null, ['perfil_completo' => false]);

        $this->actingAs($user)->post('/completar-perfil', [
            'telefono' => '9990001111', 'municipio' => 'Mérida', 'nombre_empresa' => 'Mi Empresa',
            'camara_asociacion' => 'CANIRAC', 'nombre_establecimiento' => 'La Fonda',
            'necesidades' => 'Proveedores de miel', 'es_restaurantero' => true, 'curp' => 'abc',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', '¡Perfil completado! Ya puedes acceder a todas las funciones.');

        $user->refresh();
        $this->assertTrue((bool) $user->perfil_completo);
        $this->assertSame('ABC', $user->curp);
        $this->assertSame('La Fonda', $user->nombre_establecimiento);
        $this->assertDatabaseHas('evento_usuario', [
            'evento_id' => $this->evento->id, 'user_id' => $user->id, 'tipo' => 'comprador', 'estado' => 'pendiente',
        ]);
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $user->id,
            'mensaje' => 'Registramos tu solicitud para el evento "' . $this->evento->nombre . '". El equipo la revisará y te avisaremos por correo cuando sea aprobada.',
        ]);
    }

    public function test_actualizar_perfil_comprador_vuelve_con_mensaje(): void
    {
        $user = $this->crearComprador();

        $this->actingAs($user)->from('/user/profile')->post('/mi-panel/perfil', [
            'name' => 'Nuevo Nombre', 'nombre_empresa' => 'Empresa', 'camara_asociacion' => 'CANIRAC',
        ])
            ->assertRedirect('/user/profile')
            ->assertSessionHasErrors('nombre_establecimiento');

        $this->actingAs($user)->from('/user/profile')->post('/mi-panel/perfil', [
            'name' => 'Nuevo Nombre', 'nombre_empresa' => 'Empresa', 'camara_asociacion' => 'Ninguna',
            'genero' => '', 'rfc' => 'xaxx010101000',
        ])
            ->assertRedirect('/user/profile')
            ->assertSessionHas('success', 'Perfil actualizado correctamente.');

        $user->refresh();
        $this->assertSame('Nuevo Nombre', $user->name);
        $this->assertSame('XAXX010101000', $user->rfc);
        $this->assertNull($user->genero);
    }

    public function test_agregar_rol_vuelve_con_mensaje(): void
    {
        $user = $this->crearComprador();

        $this->actingAs($user)->from('/user/profile')->post('/perfil/agregar-rol', ['rol' => 'proveedor'])
            ->assertRedirect('/user/profile')
            ->assertSessionHas('success', 'Rol agregado correctamente. ¡Bienvenido!');

        $this->assertTrue($user->fresh()->hasRole('restaurantero'));
        $this->assertNotNull($user->fresh()->restaurantero);
    }

    public function test_subir_documentos_y_verlos(): void
    {
        Storage::fake('local');
        $user = $this->crearComprador();

        $this->actingAs($user)->from('/user/profile')->post('/perfil/documentos', [
            'ine' => UploadedFile::fake()->create('ine.pdf', 50, 'application/pdf'),
            'csf_fecha' => now()->toDateString(),
        ])
            ->assertRedirect('/user/profile')
            ->assertSessionHas('documentos_success', 'Documentos actualizados correctamente.');

        $user->refresh();
        Storage::disk('local')->assertExists($user->ine_path);

        $this->actingAs($user)->get("/documentos/{$user->id}/ine")->assertOk();
        $this->actingAs($user)->get("/documentos/{$user->id}/csf")->assertNotFound();
        $this->actingAs($this->crearComprador())->get("/documentos/{$user->id}/ine")->assertForbidden();
        $this->actingAs($this->crearAdmin())->get("/documentos/{$user->id}/ine")->assertOk();
    }

    public function test_switch_role_mensajes_y_cambio(): void
    {
        $user = $this->crearComprador();

        $this->actingAs($user)->from('/mi-panel')->post('/switch-role')
            ->assertRedirect('/mi-panel')
            ->assertSessionHasErrors(['role' => 'No tienes habilitado el modo dual.']);

        $user->assignRole('restaurantero');

        $this->actingAs($user)->post('/switch-role')->assertRedirect(route('dashboard'));
        $this->assertSame('proveedor', $user->fresh()->active_role);

        Cita::create([
            'edicion_id' => $this->evento->id, 'restaurantero_id' => $this->restaurantero->id,
            'servicio_id' => $this->servicio->id, 'cliente_id' => $user->id,
            'inicio' => now()->addMinutes(20), 'fin' => now()->addMinutes(50), 'estado' => 'pendiente',
        ]);

        $this->actingAs($user->fresh())->from('/mi-panel')->post('/switch-role')
            ->assertRedirect('/mi-panel')
            ->assertSessionHasErrors('role');
        $this->assertSame('proveedor', $user->fresh()->active_role);
    }

    public function test_polling_de_notificaciones_de_la_web_sin_cambios(): void
    {
        $user = $this->crearComprador();
        $propia = Notificacion::crear($user->id, 'general', 'Hola', 'x');
        $ajena = Notificacion::crear($this->crearComprador()->id, 'general', 'Ajena', 'x');

        $this->actingAs($user)->getJson('/api/notificaciones')
            ->assertOk()
            ->assertJsonPath('no_leidas', 1)
            ->assertJsonPath('notificaciones.0.id', $propia->id);

        $this->actingAs($user)->patchJson("/api/notificaciones/{$ajena->id}/leer")->assertForbidden();
        $this->actingAs($user)->patchJson("/api/notificaciones/{$propia->id}/leer")->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($user)->postJson('/api/notificaciones/leer-todas')->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($user)->deleteJson('/api/notificaciones')->assertOk();

        $this->assertNull($propia->fresh());
        $this->assertNotNull($ajena->fresh());
    }

    public function test_el_proveedor_recibe_recordatorio_de_30_min_al_consultar(): void
    {
        $proveedor = $this->restaurantero->user;
        $comprador = $this->crearComprador();
        Cita::create([
            'edicion_id' => $this->evento->id, 'restaurantero_id' => $this->restaurantero->id,
            'servicio_id' => $this->servicio->id, 'cliente_id' => $comprador->id,
            'inicio' => now()->addMinutes(20), 'fin' => now()->addMinutes(50), 'estado' => 'confirmada',
        ]);

        $this->actingAs($proveedor)->getJson('/api/notificaciones')->assertOk()->assertJsonPath('no_leidas', 1);
        $this->actingAs($proveedor)->getJson('/api/notificaciones')->assertJsonPath('no_leidas', 1);

        $this->assertDatabaseHas('notificaciones', ['user_id' => $proveedor->id, 'tipo' => 'recordatorio_30m']);
    }
}
