<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\Publicidad;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/**
 * Fase 1 de la app: cuenta y onboarding bajo /api/v1.
 * Correr sobre MySQL (ver docs/api-v1.md).
 */
class ApiV1CuentaTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        // La migracion de ediciones siembra otro evento activo (hoy); el de la
        // prueba se marca destacado para ser el Evento::activo() principal.
        $this->crearEscenario(['destacado' => true]);
    }

    private function datosRegistro(array $cambios = []): array
    {
        return array_merge([
            'name'                  => 'Ana Compradora',
            'email'                 => 'ana@example.com',
            'telefono'              => '9991234567',
            'password'              => 'Secreta2026!',
            'password_confirmation' => 'Secreta2026!',
            'acepta_aviso'          => true,
        ], $cambios);
    }

    private function formularioPerfil(array $cambios = []): array
    {
        return array_merge([
            'name'              => 'Ana Compradora',
            'telefono'          => '9991234567',
            'curp'              => 'abcd800101hynrrr09',
            'rfc'               => 'abcd800101xx1',
            'municipio'         => 'Mérida',
            'genero'            => 'mujer',
            'nombre_empresa'    => 'Cocina Ana',
            'sitio_web'         => 'https://ana.example.com',
            'necesidades'       => 'Busco miel y cafe',
            'camara_asociacion' => 'Ninguna',
            'es_restaurantero'  => false,
        ], $cambios);
    }

    /** Ninguna respuesta puede llevar rutas de documentos ni secretos. */
    private function assertSinFugas(string $json, ?User $user = null): void
    {
        foreach (['ine_path', 'csf_path', 'remember_token', 'two_factor', '"password"'] as $fuga) {
            $this->assertStringNotContainsString($fuga, $json);
        }
        if ($user) {
            foreach (array_filter([$user->ine_path, $user->csf_path]) as $ruta) {
                $this->assertStringNotContainsString($ruta, $json);
                $this->assertStringNotContainsString(str_replace('/', '\/', $ruta), $json);
            }
        }
    }

    /* ─────────────── Registro, verificacion, contrasena olvidada ─────────────── */

    public function test_registro_crea_comprador_y_no_devuelve_token(): void
    {
        $respuesta = $this->postJson('/api/v1/auth/registro', $this->datosRegistro())
            ->assertCreated()
            ->assertJsonPath('data.email', 'ana@example.com')
            ->assertJsonStructure(['data' => ['email', 'verificacion_pendiente'], 'message']);

        $this->assertArrayNotHasKey('token', $respuesta->json('data'));
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('cliente'));
        $this->assertNotNull($user->acepta_aviso_at);
        $this->assertSame('seleccionar_rol', $user->onboarding()['siguiente_paso']);
        $this->assertSinFugas($respuesta->getContent());
    }

    public function test_registro_valida_como_la_web(): void
    {
        $this->postJson('/api/v1/auth/registro', $this->datosRegistro([
            'acepta_aviso'          => false,
            'password_confirmation' => 'otra',
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['acepta_aviso', 'password'])
            ->assertJsonPath('errors.acepta_aviso.0', 'Debes aceptar el Aviso de Privacidad para registrarte.');

        $this->assertDatabaseMissing('users', ['email' => 'ana@example.com']);
    }

    public function test_registro_con_correo_existente_no_toca_la_cuenta_ajena(): void
    {
        $proveedor = $this->restaurantero->user;

        $this->postJson('/api/v1/auth/registro', $this->datosRegistro(['email' => $proveedor->email]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertFalse($proveedor->fresh()->hasRole('cliente'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_reenviar_verificacion_responde_generico_y_solo_envia_a_cuentas_sin_verificar(): void
    {
        Notification::fake();
        $sinVerificar = User::factory()->unverified()->create();

        $a = $this->postJson('/api/v1/auth/reenviar-verificacion', ['email' => $sinVerificar->email])->assertOk();
        $b = $this->postJson('/api/v1/auth/reenviar-verificacion', ['email' => 'nadie@example.com'])->assertOk();

        $this->assertSame($a->json('message'), $b->json('message'));
        Notification::assertSentTo($sinVerificar, VerifyEmail::class);
        Notification::assertCount(1);

        $this->postJson('/api/v1/auth/reenviar-verificacion', ['email' => 'no-es-correo'])->assertStatus(422);
    }

    public function test_olvide_contrasena_responde_generico_y_envia_el_enlace(): void
    {
        Notification::fake();
        $user = $this->crearComprador();

        $a = $this->postJson('/api/v1/auth/olvide-contrasena', ['email' => $user->email])->assertOk();
        $b = $this->postJson('/api/v1/auth/olvide-contrasena', ['email' => 'nadie@example.com'])->assertOk();

        $this->assertSame($a->json('message'), $b->json('message'));
        Notification::assertSentTo($user, ResetPassword::class);

        $this->postJson('/api/v1/auth/olvide-contrasena', [])->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    /* ─────────────── 401 sin token ─────────────── */

    public function test_todos_los_endpoints_con_auth_responden_401_sin_token(): void
    {
        $rutas = [
            ['get', '/api/v1/aviso-privacidad'], ['post', '/api/v1/onboarding/aceptar-aviso'],
            ['post', '/api/v1/onboarding/rol'], ['get', '/api/v1/perfil'], ['put', '/api/v1/perfil'],
            ['post', '/api/v1/perfil/completar'], ['get', '/api/v1/perfil/documentos'],
            ['post', '/api/v1/perfil/documentos'], ['post', '/api/v1/perfil/cambiar-rol'],
            ['post', '/api/v1/perfil/agregar-rol'], ['put', '/api/v1/perfil/contrasena'],
            ['delete', '/api/v1/cuenta'], ['get', '/api/v1/notificaciones'],
            ['get', '/api/v1/notificaciones/contador'], ['patch', '/api/v1/notificaciones/1/leer'],
            ['post', '/api/v1/notificaciones/leer-todas'], ['delete', '/api/v1/notificaciones/1'],
            ['delete', '/api/v1/notificaciones'], ['get', '/api/v1/inicio'], ['get', '/api/v1/catalogos'],
        ];

        foreach ($rutas as [$metodo, $ruta]) {
            $this->json($metodo, $ruta)->assertStatus(401, "$metodo $ruta");
        }
    }

    /* ─────────────── Aviso y rol ─────────────── */

    public function test_aviso_de_privacidad_y_aceptacion(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/aviso-privacidad')
            ->assertOk()
            ->assertJsonPath('data.aceptado', false)
            ->assertJsonPath('data.url', route('aviso.privacidad'));

        $this->postJson('/api/v1/onboarding/aceptar-aviso', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.acepta.0', 'Debes aceptar el Aviso de Privacidad para continuar.');

        $this->postJson('/api/v1/onboarding/aceptar-aviso', ['acepta' => true])
            ->assertOk()
            ->assertJsonPath('data.onboarding.aviso_aceptado', true)
            ->assertJsonPath('data.onboarding.siguiente_paso', 'seleccionar_rol');

        $this->assertNotNull($user->fresh()->acepta_aviso_at);
    }

    public function test_seleccionar_rol_exige_aviso_y_valida(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/onboarding/rol', ['rol' => 'comprador'])
            ->assertStatus(422)->assertJsonValidationErrors(['onboarding']);

        $user->update(['acepta_aviso_at' => now()]);

        $this->postJson('/api/v1/onboarding/rol', ['rol' => 'jefe'])
            ->assertStatus(422)->assertJsonValidationErrors(['rol']);
    }

    public function test_seleccionar_rol_proveedor_crea_el_perfil_de_negocio(): void
    {
        $user = User::factory()->create(['acepta_aviso_at' => now()]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/onboarding/rol', ['rol' => 'proveedor'])
            ->assertOk()
            ->assertJsonPath('message', '¡Bienvenido a Impulsate!')
            ->assertJsonPath('data.user.es_proveedor', true)
            ->assertJsonPath('data.user.es_comprador', false)
            ->assertJsonPath('data.user.perfil_proveedor_completo', false)
            ->assertJsonPath('data.onboarding.rol_activo', 'proveedor')
            ->assertJsonPath('data.onboarding.siguiente_paso', 'completar_perfil');

        $restaurantero = $user->fresh()->restaurantero;
        $this->assertNotNull($restaurantero);
        $this->assertSame(1, $restaurantero->servicios()->count());
        $this->assertSame(5, $restaurantero->horarios()->count());
        // No se inscribe solo a ningun evento.
        $this->assertDatabaseMissing('evento_usuario', ['user_id' => $user->id]);
    }

    /* ─────────────── Perfil ─────────────── */

    public function test_ver_y_actualizar_perfil(): void
    {
        $user = $this->crearComprador();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/perfil')
            ->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonStructure(['data' => ['documentos' => ['ine', 'csf']]]);

        $this->putJson('/api/v1/perfil', $this->formularioPerfil(['camara_asociacion' => 'CANIRAC']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre_establecimiento']);

        $this->putJson('/api/v1/perfil', $this->formularioPerfil())
            ->assertOk()
            ->assertJsonPath('message', 'Perfil actualizado correctamente.')
            ->assertJsonPath('data.curp', 'ABCD800101HYNRRR09')
            ->assertJsonPath('data.genero', 'mujer')
            ->assertJsonPath('data.nombre_establecimiento', null);
    }

    public function test_marcar_restaurantero_en_perfil_lo_inscribe_pendiente_al_encuentro(): void
    {
        $user = $this->crearComprador(null);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/perfil', $this->formularioPerfil(['es_restaurantero' => true]))->assertOk();

        $this->assertDatabaseHas('evento_usuario', [
            'evento_id' => $this->evento->id, 'user_id' => $user->id, 'tipo' => 'comprador', 'estado' => 'pendiente',
        ]);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $user->id, 'tipo' => 'solicitud_registro']);
    }

    public function test_completar_perfil(): void
    {
        $user = $this->crearComprador(null, ['perfil_completo' => false]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/perfil/completar', ['camara_asociacion' => 'Otra'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['telefono', 'municipio', 'nombre_empresa', 'camara_asociacion', 'necesidades']);

        $this->postJson('/api/v1/perfil/completar', $this->formularioPerfil(['es_restaurantero' => true]))
            ->assertOk()
            ->assertJsonPath('message', '¡Perfil completado! Ya puedes acceder a todas las funciones.')
            ->assertJsonPath('data.onboarding.siguiente_paso', 'ninguno');

        $this->assertTrue((bool) $user->fresh()->perfil_completo);
        $this->assertDatabaseHas('evento_usuario', ['user_id' => $user->id, 'estado' => 'pendiente']);
    }

    public function test_completar_perfil_exige_rol_seleccionado_como_la_web(): void
    {
        Sanctum::actingAs($this->crearComprador(null, ['perfil_completo' => false, 'rol_seleccionado' => false]));

        $this->postJson('/api/v1/perfil/completar', $this->formularioPerfil())
            ->assertStatus(422)->assertJsonValidationErrors(['onboarding']);
    }

    /* ─────────────── Documentos ─────────────── */

    public function test_subir_documentos_guarda_en_disco_privado_y_no_expone_rutas(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = $this->crearComprador();
        Sanctum::actingAs($user);

        $respuesta = $this->post('/api/v1/perfil/documentos', [
            'ine'       => UploadedFile::fake()->image('ine.jpg'),
            'csf'       => UploadedFile::fake()->create('csf.pdf', 100, 'application/pdf'),
            'csf_fecha' => now()->subDays(10)->toDateString(),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('message', 'Documentos actualizados correctamente.')
            ->assertJsonPath('data.ine.subido', true)
            ->assertJsonPath('data.csf.subido', true)
            ->assertJsonPath('data.csf.vigente', true);

        $user->refresh();
        $this->assertStringStartsWith("documentos/{$user->id}/ine_", $user->ine_path);
        Storage::disk('local')->assertExists($user->ine_path);
        Storage::disk('local')->assertExists($user->csf_path);
        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertSinFugas($respuesta->getContent(), $user);

        $this->assertNotNull($respuesta->json('data.ine.url_temporal'));
    }

    public function test_documentos_validan_formato_peso_y_fecha(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->crearComprador());

        $this->post('/api/v1/perfil/documentos', [
            'ine'       => UploadedFile::fake()->create('ine.gif', 10, 'image/gif'),
            'csf'       => UploadedFile::fake()->create('csf.pdf', 6000, 'application/pdf'),
            'csf_fecha' => now()->addDay()->toDateString(),
        ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ine', 'csf', 'csf_fecha'])
            ->assertJsonPath('errors.csf.0', 'La CSF no debe pesar más de 5 MB.');
    }

    public function test_url_temporal_sirve_el_archivo_y_no_se_puede_manipular(): void
    {
        Storage::fake('local');
        $user = $this->crearComprador();
        $otro = $this->crearComprador();
        Sanctum::actingAs($user);

        $this->post('/api/v1/perfil/documentos', ['ine' => UploadedFile::fake()->image('ine.png')], ['Accept' => 'application/json'])
            ->assertOk();

        $url = $this->getJson('/api/v1/perfil/documentos')
            ->assertOk()
            ->assertJsonPath('data.csf.subido', false)
            ->assertJsonPath('data.csf.url_temporal', null)
            ->json('data.ine.url_temporal');

        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        // Cambiar el usuario o el solicitante rompe la firma.
        $this->get(str_replace("/documentos/{$user->id}/", "/documentos/{$otro->id}/", $url))->assertForbidden();
        $this->get(str_replace("solicitante={$user->id}", "solicitante={$otro->id}", $url))->assertForbidden();

        // Caducada.
        $this->travel(11)->minutes();
        $this->get($url)->assertForbidden();
    }

    public function test_url_firmada_no_sirve_si_el_solicitante_no_es_dueno_ni_admin(): void
    {
        Storage::fake('local');
        $dueno = $this->crearComprador(null, ['ine_path' => 'documentos/x/ine_a.pdf']);
        Storage::disk('local')->put('documentos/x/ine_a.pdf', 'pdf');
        $ajeno = $this->crearComprador();
        $admin = $this->crearAdmin();

        $firmar = fn (User $solicitante) => URL::temporarySignedRoute(
            'api.v1.documentos.ver', now()->addMinutes(5),
            ['user' => $dueno->id, 'tipo' => 'ine', 'solicitante' => $solicitante->id]
        );

        $this->get($firmar($ajeno))->assertForbidden();
        $this->get($firmar($admin))->assertOk();
    }

    /* ─────────────── Roles ─────────────── */

    public function test_cambiar_rol_sin_doble_rol_da_422(): void
    {
        Sanctum::actingAs($this->crearComprador());

        $this->postJson('/api/v1/perfil/cambiar-rol')
            ->assertStatus(422)
            ->assertJsonPath('errors.role.0', 'No tienes habilitado el modo dual.');
    }

    public function test_cambiar_rol_alterna_y_respeta_citas_proximas(): void
    {
        $user = $this->crearComprador();
        $user->assignRole('restaurantero');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/perfil/cambiar-rol')
            ->assertOk()
            ->assertJsonPath('data.onboarding.rol_activo', 'proveedor')
            ->assertJsonPath('message', 'Ahora estás en modo proveedor.');

        Cita::create([
            'edicion_id' => $this->evento->id, 'restaurantero_id' => $this->restaurantero->id,
            'servicio_id' => $this->servicio->id, 'cliente_id' => $user->id,
            'inicio' => now()->addMinutes(30), 'fin' => now()->addMinutes(60), 'estado' => 'confirmada',
        ]);

        $this->postJson('/api/v1/perfil/cambiar-rol')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
        $this->assertSame('proveedor', $user->fresh()->active_role);
    }

    public function test_agregar_rol_proveedor(): void
    {
        $user = $this->crearComprador();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/perfil/agregar-rol', ['rol' => 'otro'])->assertStatus(422)->assertJsonValidationErrors(['rol']);

        $this->postJson('/api/v1/perfil/agregar-rol', ['rol' => 'proveedor'])
            ->assertOk()
            ->assertJsonPath('message', 'Rol agregado correctamente. ¡Bienvenido!')
            ->assertJsonPath('data.user.tiene_dual_rol', true);

        $this->assertNotNull($user->fresh()->restaurantero);
    }

    /* ─────────────── Contrasena y baja ─────────────── */

    public function test_cambiar_contrasena(): void
    {
        $user = $this->crearComprador();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/perfil/contrasena', [
            'current_password' => 'mala', 'password' => 'NuevaClave2026!', 'password_confirmation' => 'NuevaClave2026!',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password']);

        $this->putJson('/api/v1/perfil/contrasena', [
            'current_password' => 'password', 'password' => 'NuevaClave2026!', 'password_confirmation' => 'distinta',
        ])->assertStatus(422)->assertJsonValidationErrors(['password']);

        $this->putJson('/api/v1/perfil/contrasena', [
            'current_password' => 'password', 'password' => 'NuevaClave2026!', 'password_confirmation' => 'NuevaClave2026!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NuevaClave2026!', $user->fresh()->password));
    }

    public function test_eliminar_cuenta_pide_contrasena(): void
    {
        $user = $this->crearComprador();
        $user->createToken('app');
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/cuenta', ['password' => 'mala'])
            ->assertStatus(422)->assertJsonValidationErrors(['password']);
        $this->assertNotNull($user->fresh());

        $this->deleteJson('/api/v1/cuenta', ['password' => 'password'])->assertOk();
        $this->assertNull(User::find($user->id));
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    /* ─────────────── Notificaciones ─────────────── */

    public function test_notificaciones_paginadas_filtro_y_contador(): void
    {
        $user = $this->crearComprador();
        foreach (range(1, 3) as $i) {
            Notificacion::crear($user->id, 'general', "N{$i}", 'Mensaje');
        }
        Notificacion::where('titulo', 'N1')->update(['leida' => true]);
        Notificacion::crear($this->crearComprador()->id, 'general', 'Ajena', 'x');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/notificaciones')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.no_leidas', 2)
            ->assertJsonStructure(['data' => [['id', 'tipo', 'titulo', 'mensaje', 'leida', 'cita_id', 'created_at']], 'meta' => ['current_page', 'last_page']]);

        $this->getJson('/api/v1/notificaciones?no_leidas=1')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/notificaciones/contador')->assertOk()->assertJsonPath('data.no_leidas', 2);

        $this->postJson('/api/v1/notificaciones/leer-todas')->assertOk()->assertJsonPath('data.actualizadas', 2);
        $this->getJson('/api/v1/notificaciones/contador')->assertJsonPath('data.no_leidas', 0);
    }

    public function test_no_se_pueden_leer_ni_borrar_notificaciones_ajenas(): void
    {
        $user = $this->crearComprador();
        $ajena = Notificacion::crear($this->crearComprador()->id, 'general', 'Ajena', 'x');
        $propia = Notificacion::crear($user->id, 'general', 'Propia', 'x');
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/notificaciones/{$ajena->id}/leer")->assertForbidden();
        $this->deleteJson("/api/v1/notificaciones/{$ajena->id}")->assertForbidden();
        $this->assertFalse($ajena->fresh()->leida);

        $this->patchJson("/api/v1/notificaciones/{$propia->id}/leer")->assertOk()->assertJsonPath('data.leida', true);
        $this->deleteJson("/api/v1/notificaciones/{$propia->id}")->assertOk();
        $this->assertNull($propia->fresh());

        Notificacion::crear($user->id, 'general', 'Otra', 'x');
        $this->deleteJson('/api/v1/notificaciones')->assertOk()->assertJsonPath('data.eliminadas', 1);
        $this->assertNotNull($ajena->fresh());
    }

    /* ─────────────── Inicio y catalogos ─────────────── */

    public function test_inicio_resume_cita_evento_notificaciones_y_publicidad(): void
    {
        $user = $this->crearComprador(null, ['ine_path' => 'documentos/1/ine_x.pdf', 'csf_path' => 'documentos/1/csf_x.pdf']);
        Cita::create([
            'edicion_id' => $this->evento->id, 'restaurantero_id' => $this->restaurantero->id,
            'servicio_id' => $this->servicio->id, 'cliente_id' => $user->id,
            'inicio' => now()->addDay()->setTime(10, 0), 'fin' => now()->addDay()->setTime(10, 30), 'estado' => 'confirmada',
        ]);
        Cita::create([
            'edicion_id' => $this->evento->id, 'restaurantero_id' => $this->restaurantero->id,
            'servicio_id' => $this->servicio->id, 'cliente_id' => $user->id,
            'inicio' => now()->addDay()->setTime(11, 0), 'fin' => now()->addDay()->setTime(11, 30), 'estado' => 'cancelada',
        ]);
        Notificacion::crear($user->id, 'general', 'Hola', 'x');
        Publicidad::create([
            'titulo' => 'Promo', 'imagen' => 'publicidad/p.jpg', 'activa' => true,
            'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDay(),
        ]);
        Sanctum::actingAs($user);

        $respuesta = $this->getJson('/api/v1/inicio')
            ->assertOk()
            ->assertJsonPath('data.vista', 'comprador')
            ->assertJsonPath('data.citas_proximas_count', 1)
            ->assertJsonPath('data.proxima_cita.estado', 'confirmada')
            ->assertJsonPath('data.proxima_cita.proveedor.nombre_negocio', 'Negocio Test')
            ->assertJsonPath('data.evento_destacado.id', $this->evento->id)
            ->assertJsonPath('data.notificaciones_sin_leer', 1)
            ->assertJsonPath('data.publicidad.titulo', 'Promo')
            ->assertJsonStructure(['data' => ['usuario', 'onboarding']]);

        $this->assertArrayNotHasKey('comprador', $respuesta->json('data.proxima_cita'));
        $this->assertSinFugas($respuesta->getContent(), $user);

        // Admin: sin publicidad, como en la web.
        Sanctum::actingAs($this->crearAdmin());
        $this->getJson('/api/v1/inicio')->assertOk()->assertJsonPath('data.publicidad', null);
    }

    public function test_catalogos(): void
    {
        Sanctum::actingAs($this->crearComprador());

        $datos = $this->getJson('/api/v1/catalogos')->assertOk()->json('data');

        $this->assertContains('Mérida', $datos['municipios']);
        $this->assertSame(['CANIRAC', 'Ninguna'], $datos['camaras_asociacion']);
        $this->assertSame(['comprador', 'proveedor', 'ambos'], array_column($datos['roles_seleccionables'], 'valor'));
        $this->assertSame(5120, $datos['documentos']['max_kb']);
    }

    public function test_el_seed_de_demo_se_niega_fuera_de_local(): void
    {
        $this->artisan('impulsate:demo-movil')->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'proveedor@prueba.impulsate.mx']);
    }

    public function test_me_y_perfil_no_exponen_rutas_de_documentos(): void
    {
        $user = $this->crearComprador(null, [
            'ine_path' => 'documentos/9/ine_secreto.pdf', 'csf_path' => 'documentos/9/csf_secreto.pdf', 'csf_fecha' => now()->toDateString(),
        ]);
        Sanctum::actingAs($user);

        foreach (['/api/v1/auth/me', '/api/v1/perfil', '/api/v1/perfil/documentos', '/api/v1/inicio'] as $ruta) {
            $this->assertSinFugas($this->getJson($ruta)->assertOk()->getContent(), $user);
        }

        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.user.es_comprador', true)
            ->assertJsonPath('data.user.es_proveedor', false)
            ->assertJsonPath('data.user.perfil_proveedor_completo', null);
    }
}
