<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

class ApiV1Test extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->crearEscenario();
    }

    private function login(string $email, string $password = 'password')
    {
        return $this->postJson('/api/v1/auth/login', [
            'email' => $email, 'password' => $password, 'device_name' => 'test',
        ]);
    }

    public function test_login_devuelve_token_usuario_y_onboarding_sin_rutas_de_documentos(): void
    {
        $user = $this->crearComprador(null, [
            'perfil_completo' => false,
            'ine_path'        => 'documentos/ine.pdf',
            'csf_path'        => 'documentos/csf.pdf',
        ]);

        $respuesta = $this->login($user->email)
            ->assertOk()
            ->assertJsonPath('data.onboarding.siguiente_paso', 'completar_perfil')
            ->assertJsonPath('data.onboarding.rol_activo', 'comprador')
            ->assertJsonPath('data.user.tiene_ine', true);

        $this->assertNotEmpty($respuesta->json('data.token'));
        foreach (['ine_path', 'csf_path', 'documentos/'] as $fuga) {
            $this->assertStringNotContainsString($fuga, $respuesta->getContent());
        }
    }

    public function test_sin_token_la_api_responde_401_en_json_aunque_no_se_pida_json(): void
    {
        $this->get('/api/v1/eventos')
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_el_login_se_limita_a_5_intentos_por_minuto(): void
    {
        $user = $this->crearComprador();
        for ($i = 0; $i < 5; $i++) {
            $this->login($user->email, 'mala')->assertStatus(422);
        }

        $this->login($user->email, 'mala')->assertStatus(429);
    }

    public function test_cuenta_sin_verificar_no_obtiene_token(): void
    {
        $user = $this->crearComprador();
        $user->forceFill(['email_verified_at' => null])->save();

        $this->login($user->email)->assertStatus(403);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_revoca_el_token(): void
    {
        $user = $this->crearComprador();
        $token = $this->login($user->email)->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_eventos_traen_la_decision_del_servidor_y_el_registro_la_actualiza(): void
    {
        Sanctum::actingAs($this->crearComprador(null));

        $this->getJson('/api/v1/eventos')
            ->assertOk()
            ->assertJsonPath('data.0.id', $this->evento->id)
            ->assertJsonPath('data.0.tipo_registro', 'comprador')
            ->assertJsonPath('data.0.puede_registrarse', true)
            ->assertJsonPath('data.0.accion', 'registrate')
            ->assertJsonStructure([
                'data' => [['fases', 'mi_registro' => ['comprador', 'proveedor', 'expositor']]],
                'meta' => ['current_page', 'last_page'],
            ]);

        $this->postJson("/api/v1/eventos/{$this->evento->id}/registro", ['tipo' => 'comprador'])
            ->assertStatus(201)
            ->assertJsonPath('data.accion', 'en_revision')
            ->assertJsonPath('data.mi_registro.comprador.estado', 'pendiente');

        $this->postJson("/api/v1/eventos/{$this->evento->id}/registro", ['tipo' => 'comprador'])
            ->assertStatus(200)
            ->assertJsonPath('resultado', 'ya_pendiente');
    }

    public function test_la_api_bloquea_el_registro_con_perfil_incompleto_igual_que_la_web(): void
    {
        Sanctum::actingAs($this->crearComprador(null, ['perfil_completo' => false]));

        $this->getJson("/api/v1/eventos/{$this->evento->id}")
            ->assertJsonPath('data.puede_registrarse', false)
            ->assertJsonPath('data.accion', 'bloqueado')
            ->assertJsonPath('data.motivo_bloqueo', 'Completa tu perfil antes de continuar. Faltan datos obligatorios.');

        $this->postJson("/api/v1/eventos/{$this->evento->id}/registro", ['tipo' => 'comprador'])
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['error']]);

        $this->assertDatabaseCount('evento_usuario', 0);
    }

    public function test_un_usuario_no_ve_un_evento_inactivo_ajeno(): void
    {
        $this->evento->update(['activa' => false]);
        Sanctum::actingAs($this->crearComprador(null));

        $this->getJson("/api/v1/eventos/{$this->evento->id}")->assertForbidden();
        // La migracion de ediciones siembra un evento activo propio: se comprueba
        // que ESTE no aparezca, no que la lista este vacia.
        $ids = collect($this->getJson('/api/v1/eventos')->assertOk()->json('data'))->pluck('id');
        $this->assertNotContains($this->evento->id, $ids);
    }

    public function test_me_no_expone_campos_sensibles(): void
    {
        $user = $this->crearComprador();
        $user->forceFill(['two_factor_secret' => 'secreto'])->save();
        Sanctum::actingAs($user);

        $contenido = $this->getJson('/api/v1/auth/me')->assertOk()->getContent();
        foreach (['password', 'remember_token', 'two_factor', 'ine_path', 'csf_path'] as $campo) {
            $this->assertStringNotContainsString($campo, $contenido);
        }
    }
}
