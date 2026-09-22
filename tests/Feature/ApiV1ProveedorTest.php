<?php

namespace Tests\Feature;

use App\Mail\CitaAceptada;
use App\Mail\CitaReagendada;
use App\Mail\CitaRechazada;
use App\Mail\ProveedorAprobado;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\User;
use App\Services\PerfilProveedorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaEscenarioEvento;
use Tests\TestCase;

/** Fase 3 de la API: todo lo del proveedor (panel, perfil de negocio, productos, citas recibidas, calendario). */
class ApiV1ProveedorTest extends TestCase
{
    use RefreshDatabase;
    use CreaEscenarioEvento;

    private User $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->crearEscenario(['destacado' => true]);

        $this->proveedor = $this->restaurantero->user;
        $this->proveedor->forceFill([
            'acepta_aviso_at'  => now(),
            'rol_seleccionado' => true,
            'active_role'      => 'proveedor',
        ])->save();
    }

    /* ─────────────── helpers ─────────────── */

    private function dia(int $h, int $m = 0): Carbon
    {
        return $this->evento->fecha_hora_inicio->copy()->setTime($h, $m);
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

    /** Deja el negocio del proveedor de prueba con perfil completo (y aprobado). */
    private function completarNegocio(array $extra = []): Restaurantero
    {
        $this->restaurantero->forceFill(array_merge([
            'descripcion'       => 'Salsas yucatecas',
            'telefono'          => '9991112233',
            'municipio'         => 'Mérida',
            'logo_path'         => 'proveedores/fotos/logo-viejo.png',
            'productos_top'     => [
                ['nombre' => 'Recado rojo', 'descripcion' => 'Achiote', 'foto_path' => 'proveedores/productos/a.jpg', 'capacidad_cantidad' => 500, 'capacidad_unidad' => 'kilogramos'],
                ['nombre' => 'Salsa habanero', 'descripcion' => '', 'foto_path' => 'proveedores/productos/b.jpg', 'capacidad_cantidad' => 2000, 'capacidad_unidad' => 'piezas'],
            ],
            'entrega_domicilio' => false,
            'factura'           => true,
            'rfc'               => 'SDC200101AB1',
            'regimen_fiscal'    => 'Persona Moral',
            'aprobado'          => true,
            'activo'            => true,
        ], $extra))->save();

        return $this->restaurantero->fresh();
    }

    private function comoProveedor(): static
    {
        Sanctum::actingAs($this->proveedor);

        return $this;
    }

    /* ─────────────── acceso ─────────────── */

    public function test_endpoints_de_proveedor_responden_401_sin_token(): void
    {
        foreach ([
            ['GET', 'panel'], ['GET', 'perfil'], ['GET', 'perfil/catalogos'], ['PUT', 'perfil'], ['PUT', 'perfil/negocio'],
            ['POST', 'logo'], ['GET', 'productos'], ['POST', 'productos'], ['POST', 'productos/0'], ['DELETE', 'productos/0'],
            ['GET', 'citas'], ['GET', 'citas/1'], ['GET', 'citas/1/disponibilidad'], ['POST', 'citas/1/aceptar'],
            ['POST', 'citas/1/rechazar'], ['POST', 'citas/1/reagendar'], ['GET', 'calendario'],
        ] as [$metodo, $ruta]) {
            $this->json($metodo, "/api/v1/proveedor/{$ruta}")->assertUnauthorized();
        }
    }

    public function test_un_comprador_no_entra_a_rutas_de_proveedor(): void
    {
        $comprador = $this->crearComprador();
        $cita = $this->cita($comprador, $this->dia(10));
        Sanctum::actingAs($comprador);

        $this->getJson('/api/v1/proveedor/panel')->assertForbidden()->assertJson(['message' => 'Esta sección es solo para proveedores.']);
        $this->getJson('/api/v1/proveedor/perfil')->assertForbidden();
        $this->putJson('/api/v1/proveedor/perfil/negocio', ['nombre_restaurante' => 'X'])->assertForbidden();
        $this->getJson("/api/v1/proveedor/citas/{$cita->id}")->assertForbidden();
        $this->postJson("/api/v1/proveedor/citas/{$cita->id}/aceptar")->assertForbidden();
        $this->assertSame('pendiente', $cita->fresh()->estado);
    }

    /* ─────────────── panel ─────────────── */

    public function test_panel_resume_negocio_perfil_citas_y_eventos(): void
    {
        $c1 = $this->crearComprador('aprobado', ['telefono' => '9990001111', 'necesidades' => 'Busco salsas', 'nombre_empresa' => 'La Tradición']);
        $c2 = $this->crearComprador();
        $this->cita($c1, $this->dia(10), 'pendiente');
        $this->cita($c2, $this->dia(11), 'confirmada');
        $this->cita($c2, $this->dia(12), 'rechazada');
        $this->cita($c1, $this->dia(13), 'cancelada');
        DB::table('evento_usuario')->insert([
            'evento_id' => $this->evento->id, 'user_id' => $this->proveedor->id, 'tipo' => 'proveedor',
            'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $d = $this->comoProveedor()->getJson('/api/v1/proveedor/panel')->assertOk()->json('data');

        $this->assertSame('pendiente', $d['negocio']['estado']);
        $this->assertFalse($d['perfil']['completo']);
        $this->assertContains('contacto', $d['perfil']['secciones_faltantes']);
        $this->assertContains('logo', collect($d['perfil']['faltantes'])->pluck('campo')->all());
        $this->assertSame(['pendientes' => 1, 'confirmadas' => 1, 'rechazadas' => 2], $d['contadores']);
        $this->assertSame(1, $d['kpis']['citas_pendientes']);
        $this->assertEquals(50, $d['kpis']['tasa_aceptacion']);
        $this->assertSame(2, $d['kpis']['total_en_evento']);
        $this->assertCount(2, $d['proximas_citas']);
        $this->assertSame('Busco salsas', $d['proximas_citas'][0]['comprador']['necesidades']);
        $this->assertTrue($d['proximas_citas'][0]['puede_aceptar']);

        $ev = collect($d['eventos'])->firstWhere('id', $this->evento->id);
        $this->assertSame('proveedor', $ev['tipo_registro']);
        $this->assertSame('aprobado', $ev['mi_registro']['proveedor']['estado']);
        $this->assertSame(['tiene_ine' => false, 'tiene_csf' => false, 'csf_fecha' => null, 'csf_vigente' => false, 'csf_vigencia_meses' => 3], $d['requisitos_bazar']);

        $json = json_encode($d);
        $this->assertStringNotContainsString('_path', $json);
    }

    public function test_panel_con_dos_roles_ve_su_registro_de_proveedor_aunque_este_en_modo_comprador(): void
    {
        $this->proveedor->assignRole('cliente');
        $this->proveedor->forceFill(['active_role' => 'comprador', 'perfil_completo' => true])->save();

        $d = $this->comoProveedor()->getJson('/api/v1/proveedor/panel')->assertOk()->json('data');
        $this->assertSame('proveedor', collect($d['eventos'])->firstWhere('id', $this->evento->id)['tipo_registro']);

        // /eventos sigue la regla de active_role salvo ?vista=proveedor
        $this->assertSame('comprador', $this->getJson("/api/v1/eventos/{$this->evento->id}")->json('data.tipo_registro'));
        $this->assertSame('proveedor', $this->getJson("/api/v1/eventos/{$this->evento->id}?vista=proveedor")->json('data.tipo_registro'));

        // Registrarse como proveedor funciona sin cambiar de rol activo y responde en vista proveedor.
        $this->completarNegocio();
        $r = $this->postJson("/api/v1/eventos/{$this->evento->id}/registro", ['tipo' => 'proveedor'])->assertCreated();
        $this->assertSame('proveedor', $r->json('data.tipo_registro'));
        $this->assertSame('pendiente', $r->json('data.mi_registro.proveedor.estado'));
        $this->assertSame('en_revision', $r->json('data.accion'));
    }

    public function test_panel_crea_el_negocio_si_no_existe_como_la_web(): void
    {
        $nuevo = User::factory()->create(['acepta_aviso_at' => now(), 'rol_seleccionado' => true, 'active_role' => 'proveedor']);
        $nuevo->assignRole('restaurantero');
        Sanctum::actingAs($nuevo);

        $d = $this->getJson('/api/v1/proveedor/panel')->assertOk()->json('data');

        $this->assertSame($nuevo->name . ' — Negocio', $d['negocio']['nombre_negocio']);
        $this->assertSame('pendiente', $d['negocio']['estado']);
        $this->assertSame(1, $nuevo->fresh()->restaurantero->servicios()->count());
    }

    /* ─────────────── perfil de negocio ─────────────── */

    public function test_perfil_devuelve_secciones_valores_y_faltantes_sin_rutas_de_disco(): void
    {
        $this->completarNegocio(['sitio_web' => 'https://salsas.mx', 'credito_monto_maximo' => 1500]);

        $d = $this->comoProveedor()->getJson('/api/v1/proveedor/perfil')->assertOk()->json('data');

        $this->assertSame('aprobado', $d['negocio']['estado']);
        $this->assertTrue($d['negocio']['perfil_completo']);
        $this->assertSame([], $d['faltantes']);
        $this->assertSame(['negocio', 'oferta', 'entrega', 'contacto'], collect($d['secciones'])->pluck('clave')->all());
        $this->assertSame('Negocio Test', $d['valores']['negocio']['nombre_restaurante']);
        $this->assertSame('producto', $d['valores']['oferta']['tipo_oferta']);
        $this->assertTrue($d['valores']['entrega']['factura']);
        $this->assertSame('dias', $d['valores']['entrega']['credito_tiempo_unidad']);
        $this->assertSame('https://salsas.mx', $d['valores']['contacto']['sitio_web']);
        $this->assertSame(Storage::disk('public')->url('proveedores/fotos/logo-viejo.png'), $d['logo_url']);
        $this->assertStringStartsWith('http', $d['logo_url']);
        $this->assertSame(0, $d['productos'][0]['indice']);
        $this->assertEquals(500, $d['productos'][0]['capacidad_cantidad']);
        $this->assertStringStartsWith('http', $d['productos'][0]['foto_url']);
        $this->assertStringNotContainsString('_path', json_encode($d));
    }

    public function test_faltantes_coincide_con_calcular_perfil_completo(): void
    {
        $servicio = app(PerfilProveedorService::class);
        $casos = [
            [],
            ['factura' => true, 'rfc' => null],
            ['acepta_credito' => true, 'credito_a_negociar' => false, 'credito_monto_maximo' => null],
            ['acepta_credito' => true, 'credito_a_negociar' => true],
            ['entrega_domicilio' => true, 'cobertura_entrega' => null],
            ['entrega_domicilio' => null],
            ['productos_top' => []],
            ['productos_top' => ['Nombre viejo']],
            ['logo_path' => null],
        ];
        foreach ($casos as $cambios) {
            $r = $this->completarNegocio($cambios);
            $this->assertSame($r->calcularPerfilCompleto(), $servicio->faltantes($r) === [], json_encode($cambios));
        }
    }

    public function test_catalogos_del_perfil(): void
    {
        $d = $this->comoProveedor()->getJson('/api/v1/proveedor/perfil/catalogos')->assertOk()->json('data');

        $this->assertSame(['local', 'regional', 'nacional'], collect($d['coberturas_entrega'])->pluck('valor')->all());
        $this->assertSame(['piezas', 'cajas', 'litros', 'kilogramos'], $d['capacidad_unidades']);
        $this->assertSame(['max_productos' => 5, 'foto_max_kb' => 8192, 'foto_formatos' => ['jpg', 'jpeg', 'png', 'webp']], $d['limites']);
        $this->assertContains('Mérida', $d['municipios']);
        $this->assertCount(5, $d['certificaciones']);
        $this->assertSame('negocio', $d['secciones'][0]['clave']);
    }

    public function test_guardar_una_seccion_valida_como_la_web(): void
    {
        $this->comoProveedor();

        $this->putJson('/api/v1/proveedor/perfil/entrega', ['entrega_domicilio' => true])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cobertura_entrega', 'forma_entrega']);

        $this->putJson('/api/v1/proveedor/perfil/negocio', ['nombre_restaurante' => '', 'num_empleados' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre_restaurante', 'num_empleados'])
            ->assertJsonStructure(['message', 'errors']);

        $this->putJson('/api/v1/proveedor/perfil/oferta', ['tipo_oferta' => 'otro'])
            ->assertUnprocessable()->assertJsonValidationErrors(['tipo_oferta']);
    }

    public function test_guardar_una_seccion_conserva_las_demas_y_limpia_como_la_web(): void
    {
        $this->completarNegocio(['acepta_credito' => true, 'credito_monto_maximo' => 5000, 'credito_tiempo_cantidad' => 30, 'credito_tiempo_unidad' => 'dias']);
        $this->comoProveedor();

        $r = $this->putJson('/api/v1/proveedor/perfil/contacto', [
            'telefono'  => '9990009999',
            'sitio_web' => 'salsas.mx',
            'razon_social' => 'Ignorado: es de otra seccion',
        ])->assertOk();

        $n = $this->restaurantero->fresh();
        $this->assertSame('9990009999', $n->telefono);
        $this->assertSame('https://salsas.mx', $n->sitio_web);
        $this->assertNull($n->razon_social);
        // Lo que no se toco sigue igual (productos con su foto, factura, credito).
        $this->assertSame('proveedores/productos/b.jpg', $n->productos_top[1]['foto_path']);
        $this->assertSame('Persona Moral', $n->regimen_fiscal);
        $this->assertEquals(5000, $n->credito_monto_maximo);
        $this->assertTrue($n->perfil_completo);
        $this->assertSame('9990009999', $r->json('data.valores.contacto.telefono'));
        $this->assertStringStartsWith('¡Perfil completo!', $r->json('message'));
        Mail::assertQueued(ProveedorAprobado::class);

        // Quitar el credito limpia monto y plazo (misma limpieza que la web).
        $this->putJson('/api/v1/proveedor/perfil', ['acepta_credito' => false])->assertOk();
        $n = $this->restaurantero->fresh();
        $this->assertFalse($n->acepta_credito);
        $this->assertNull($n->credito_monto_maximo);
        $this->assertNull($n->credito_tiempo_cantidad);
    }

    public function test_subir_logo_completa_el_perfil_y_devuelve_url_absoluta(): void
    {
        Storage::fake('public');
        $this->completarNegocio(['logo_path' => null, 'aprobado' => false, 'activo' => false]);
        Storage::disk('public')->put('proveedores/fotos/otro.png', 'x');
        $this->comoProveedor();

        $this->postJson('/api/v1/proveedor/logo', [])->assertUnprocessable()->assertJsonValidationErrors(['foto']);
        $this->postJson('/api/v1/proveedor/logo', ['foto' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors(['foto']);
        $this->postJson('/api/v1/proveedor/logo', ['foto' => UploadedFile::fake()->image('grande.jpg')->size(9000)])
            ->assertUnprocessable()->assertJsonValidationErrors(['foto']);

        $r = $this->postJson('/api/v1/proveedor/logo', ['foto' => UploadedFile::fake()->image('logo.png')])->assertOk();

        $n = $this->restaurantero->fresh();
        $this->assertStringStartsWith('proveedores/fotos/', $n->logo_path);
        Storage::disk('public')->assertExists($n->logo_path);
        $this->assertSame(Storage::disk('public')->url($n->logo_path), $r->json('data.logo_url'));
        $this->assertTrue($r->json('data.negocio.perfil_completo'));
        $this->assertSame('aprobado', $r->json('data.negocio.estado'));
        Mail::assertQueued(ProveedorAprobado::class);

        // Reemplazar borra el anterior, como la web.
        $viejo = $n->logo_path;
        $this->postJson('/api/v1/proveedor/logo', ['foto' => UploadedFile::fake()->image('nuevo.webp')])->assertOk();
        Storage::disk('public')->assertMissing($viejo);
    }

    /* ─────────────── productos ─────────────── */

    public function test_productos_alta_edicion_y_baja_con_foto_y_capacidad(): void
    {
        Storage::fake('public');
        $this->completarNegocio();
        Storage::disk('public')->put('proveedores/productos/a.jpg', 'a');
        Storage::disk('public')->put('proveedores/productos/b.jpg', 'b');
        $this->comoProveedor();

        $this->getJson('/api/v1/proveedor/productos')->assertOk()
            ->assertJsonPath('meta.restantes', 3)
            ->assertJsonPath('data.1.nombre', 'Salsa habanero');

        // alta
        $this->postJson('/api/v1/proveedor/productos', ['descripcion' => 'sin nombre'])
            ->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
        $this->postJson('/api/v1/proveedor/productos', ['nombre' => 'Miel', 'capacidad_unidad' => 'toneladas'])
            ->assertUnprocessable()->assertJsonValidationErrors(['capacidad_unidad']);
        $this->postJson('/api/v1/proveedor/productos', ['nombre' => 'Miel', 'foto' => UploadedFile::fake()->create('x.gif', 5, 'image/gif')])
            ->assertUnprocessable()->assertJsonValidationErrors(['foto']);

        $r = $this->postJson('/api/v1/proveedor/productos', [
            'nombre' => 'Miel melipona', 'descripcion' => '250 g', 'capacidad_cantidad' => 80, 'capacidad_unidad' => 'piezas',
            'foto' => UploadedFile::fake()->image('miel.jpg'),
        ])->assertCreated();
        $this->assertSame(2, $r->json('data.indice'));
        $this->assertEquals(80, $r->json('data.capacidad_cantidad'));
        $fotoMiel = $this->restaurantero->fresh()->productos_top[2]['foto_path'];
        $this->assertSame(Storage::disk('public')->url($fotoMiel), $r->json('data.foto_url'));
        Storage::disk('public')->assertExists($fotoMiel);

        // edicion parcial: cambia la capacidad, conserva nombre y foto
        $this->putJson('/api/v1/proveedor/productos/2', ['capacidad_cantidad' => 120])->assertOk()
            ->assertJsonPath('data.nombre', 'Miel melipona');
        $this->assertEquals(120, $this->restaurantero->fresh()->productos_top[2]['capacidad_cantidad']);
        $this->assertSame($fotoMiel, $this->restaurantero->fresh()->productos_top[2]['foto_path']);
        $this->putJson('/api/v1/proveedor/productos/2', ['nombre' => ''])->assertUnprocessable()->assertJsonValidationErrors(['nombre']);
        $this->putJson('/api/v1/proveedor/productos/7', ['nombre' => 'X'])->assertNotFound();

        // baja del primero: las fotos NO se corren de producto y la foto borrada se elimina del disco
        $r = $this->deleteJson('/api/v1/proveedor/productos/0')->assertOk();
        $this->assertSame(['Salsa habanero', 'Miel melipona'], collect($r->json('data'))->pluck('nombre')->all());
        $p = $this->restaurantero->fresh()->productos_top;
        $this->assertSame('proveedores/productos/b.jpg', $p[0]['foto_path']);
        $this->assertSame($fotoMiel, $p[1]['foto_path']);
        Storage::disk('public')->assertMissing('proveedores/productos/a.jpg');
        $this->deleteJson('/api/v1/proveedor/productos/5')->assertNotFound();

        // maximo 5
        foreach (['P3', 'P4', 'P5'] as $n) {
            $this->postJson('/api/v1/proveedor/productos', ['nombre' => $n])->assertCreated();
        }
        $this->postJson('/api/v1/proveedor/productos', ['nombre' => 'P6'])
            ->assertUnprocessable()->assertJsonValidationErrors(['productos']);
    }

    public function test_un_proveedor_solo_toca_sus_productos(): void
    {
        $otro = $this->crearProveedor('Otro');
        $otro->update(['productos_top' => [['nombre' => 'Del otro']]]);
        $this->comoProveedor();

        // Las rutas no reciben el negocio: siempre operan sobre el del usuario autenticado.
        $this->deleteJson('/api/v1/proveedor/productos/0')->assertNotFound(); // el suyo no tiene productos
        $this->assertSame('Del otro', $otro->fresh()->productos_top[0]['nombre']);
    }

    /* ─────────────── citas recibidas ─────────────── */

    public function test_citas_por_pestana_con_contadores_y_detalle(): void
    {
        $c = $this->crearComprador('aprobado', ['telefono' => '9990001111', 'necesidades' => 'Salsas', 'nombre_empresa' => 'La Tradición', 'curp' => 'CURPSECRETA', 'ine_path' => 'documentos/x/ine.pdf']);
        $pend = $this->cita($c, $this->dia(9), 'pendiente', null, ['productos_interes' => ['Recado rojo']]);
        $this->cita($c, $this->dia(10), 'confirmada');
        $this->cita($c, $this->dia(11), 'pendiente_reconfirmacion', null, ['propuesta_inicio' => $this->dia(15), 'propuesta_fin' => $this->dia(15, 30)]);
        $this->cita($c, $this->dia(12), 'rechazada');
        $this->cita($c, $this->dia(13), 'cancelada');
        $this->cita($c, $this->dia(14), 'completada');
        $this->comoProveedor();

        $r = $this->getJson('/api/v1/proveedor/citas')->assertOk();
        $this->assertSame('pendientes', $r->json('meta.tab'));
        $this->assertSame(['pendientes' => 1, 'confirmadas' => 2, 'rechazadas' => 2], $r->json('meta.contadores'));
        $this->assertSame(['pendiente'], collect($r->json('data'))->pluck('estado')->all());
        $this->assertSame(1, $r->json('meta.total'));

        $this->assertSame(['pendiente_reconfirmacion', 'confirmada'], collect($this->getJson('/api/v1/proveedor/citas?tab=confirmadas')->json('data'))->pluck('estado')->all());
        $this->assertSame(['cancelada', 'rechazada'], collect($this->getJson('/api/v1/proveedor/citas?tab=rechazadas')->json('data'))->pluck('estado')->all());
        $this->assertCount(6, $this->getJson('/api/v1/proveedor/citas?tab=todas')->json('data'));
        $this->getJson('/api/v1/proveedor/citas?tab=otra')->assertUnprocessable()->assertJsonValidationErrors(['tab']);

        $d = $this->getJson("/api/v1/proveedor/citas/{$pend->id}")->assertOk()->json('data');
        $this->assertSame(['id' => $c->id, 'nombre' => $c->name, 'email' => $c->email, 'telefono' => '9990001111', 'nombre_empresa' => 'La Tradición', 'necesidades' => 'Salsas'], $d['comprador']);
        $this->assertSame(['Recado rojo'], $d['productos_interes']);
        $this->assertTrue($d['puede_aceptar']);
        $this->assertTrue($d['puede_rechazar']);
        $this->assertTrue($d['puede_reagendar']);
        $json = json_encode($d);
        $this->assertStringNotContainsString('CURPSECRETA', $json);
        $this->assertStringNotContainsString('ine_path', $json);
        $this->assertStringNotContainsString('documentos/', $json);

        $reag = collect($this->getJson('/api/v1/proveedor/citas?tab=confirmadas')->json('data'))->firstWhere('estado', 'pendiente_reconfirmacion');
        $this->assertSame($this->dia(15)->toIso8601String(), $reag['propuesta_reagenda']['inicio']);
        $this->assertFalse($reag['puede_reagendar']);
    }

    public function test_cita_de_otro_proveedor_da_403(): void
    {
        $c = $this->crearComprador();
        $ajena = $this->cita($c, $this->dia(10), 'pendiente', $this->crearProveedor('Ajeno'));
        $this->comoProveedor();

        $this->getJson("/api/v1/proveedor/citas/{$ajena->id}")->assertForbidden();
        $this->getJson("/api/v1/proveedor/citas/{$ajena->id}/disponibilidad")->assertForbidden();
        $this->postJson("/api/v1/proveedor/citas/{$ajena->id}/aceptar")->assertForbidden();
        $this->postJson("/api/v1/proveedor/citas/{$ajena->id}/rechazar")->assertForbidden();
        $this->postJson("/api/v1/proveedor/citas/{$ajena->id}/reagendar", ['propuesta_fecha' => $this->dia(9)->toDateString(), 'propuesta_hora' => '12:00'])->assertForbidden();
        $this->getJson('/api/v1/proveedor/citas/999999')->assertNotFound();
        $this->assertSame('pendiente', $ajena->fresh()->estado);
        Mail::assertNothingQueued();
    }

    public function test_aceptar_notifica_y_manda_correo_como_la_web(): void
    {
        $c = $this->crearComprador();
        $cita = $this->cita($c, $this->dia(10));
        $this->comoProveedor();

        $r = $this->postJson("/api/v1/proveedor/citas/{$cita->id}/aceptar")->assertOk()
            ->assertJsonPath('message', 'Cita aceptada. El comprador fue notificado.')
            ->assertJsonPath('data.estado', 'confirmada')
            ->assertJsonPath('data.puede_aceptar', false)
            ->assertJsonPath('data.puede_reagendar', true);

        Mail::assertQueued(CitaAceptada::class, fn ($m) => $m->hasTo($c->email));
        $n = Notificacion::where('user_id', $c->id)->where('tipo', 'cita_aceptada')->firstOrFail();
        $this->assertSame("Tu cita con Negocio Test el {$cita->inicio->format('d/m/Y H:i')} fue confirmada.", $n->mensaje);

        // Ya no esta pendiente: 422 (la web no lo comprueba en el servidor).
        $this->postJson("/api/v1/proveedor/citas/{$cita->id}/aceptar")->assertUnprocessable()
            ->assertJsonValidationErrors(['estado' => 'Solo puedes aceptar citas pendientes.']);
        $this->postJson("/api/v1/proveedor/citas/{$cita->id}/rechazar")->assertUnprocessable()->assertJsonValidationErrors(['estado']);
        Mail::assertQueued(CitaAceptada::class, 1);
    }

    public function test_rechazar_notifica_y_manda_correo_como_la_web(): void
    {
        $c = $this->crearComprador();
        $cita = $this->cita($c, $this->dia(10));
        $this->comoProveedor();

        $this->postJson("/api/v1/proveedor/citas/{$cita->id}/rechazar")->assertOk()
            ->assertJsonPath('message', 'Cita rechazada. El comprador fue notificado.')
            ->assertJsonPath('data.estado', 'rechazada')
            ->assertJsonPath('data.puede_reagendar', false);

        Mail::assertQueued(CitaRechazada::class, fn ($m) => $m->hasTo($c->email));
        $this->assertTrue(Notificacion::where('user_id', $c->id)->where('tipo', 'cita_rechazada')->where('titulo', 'Cita rechazada')->exists());
        $this->postJson("/api/v1/proveedor/citas/{$cita->id}/reagendar", ['propuesta_fecha' => $this->dia(9)->toDateString(), 'propuesta_hora' => '12:00'])
            ->assertUnprocessable()->assertJsonValidationErrors(['estado']);
    }

    public function test_reagendar_valida_rechaza_hueco_ocupado_y_el_comprador_acepta(): void
    {
        $c = $this->crearComprador();
        $otro = $this->crearComprador();
        $cita = $this->cita($c, $this->dia(10), 'confirmada');
        $this->cita($otro, $this->dia(11), 'confirmada');                                   // ocupa al proveedor
        $this->cita($c, $this->dia(13), 'pendiente', $this->crearProveedor('Otro'));       // ocupa al comprador
        $this->comoProveedor();
        $fecha = $this->dia(9)->toDateString();

        $this->postJson("/api/v1/proveedor/citas/{$cita->id}/reagendar", ['propuesta_fecha' => '2020-01-01', 'propuesta_hora' => '9'])
            ->assertUnprocessable()->assertJsonValidationErrors(['propuesta_fecha', 'propuesta_hora']);

        $this->postJson("/api/v1/proveedor/citas/{$cita->id}/reagendar", ['propuesta_fecha' => $fecha, 'propuesta_hora' => '11:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fecha' => 'Este horario ya no está disponible con el proveedor. Por favor elige otro.']);
        $this->postJson("/api/v1/proveedor/citas/{$cita->id}/reagendar", ['propuesta_fecha' => $fecha, 'propuesta_hora' => '13:05'])
            ->assertUnprocessable()->assertJsonValidationErrors(['fecha']);
        $this->assertSame('confirmada', $cita->fresh()->estado);
        Mail::assertNothingQueued();

        $this->postJson("/api/v1/proveedor/citas/{$cita->id}/reagendar", ['propuesta_fecha' => $fecha, 'propuesta_hora' => '12:00'])
            ->assertOk()
            ->assertJsonPath('message', 'Propuesta de reagendamiento enviada al comprador.')
            ->assertJsonPath('data.estado', 'pendiente_reconfirmacion')
            ->assertJsonPath('data.propuesta_reagenda.inicio', $this->dia(12)->toIso8601String())
            ->assertJsonPath('data.inicio', $this->dia(10)->toIso8601String());

        Mail::assertQueued(CitaReagendada::class, fn ($m) => $m->hasTo($c->email));
        $this->assertTrue(Notificacion::where('user_id', $c->id)->where('tipo', 'cita_reagendada')->where('titulo', 'Propuesta de cambio de horario')->exists());

        // El comprador acepta desde su app (Fase 2): la cita se mueve.
        Sanctum::actingAs($c);
        $this->postJson("/api/v1/citas/{$cita->id}/reagenda/aceptar")->assertOk();
        $this->assertSame($this->dia(12)->toDateTimeString(), $cita->fresh()->inicio->toDateTimeString());
    }

    public function test_disponibilidad_para_reagendar_desde_el_punto_de_vista_del_proveedor(): void
    {
        $c = $this->crearComprador();
        $otro = $this->crearComprador();
        $cita = $this->cita($c, $this->dia(10), 'pendiente');
        $this->cita($otro, $this->dia(11), 'confirmada');
        $this->cita($c, $this->dia(13), 'pendiente', $this->crearProveedor('Otro'));
        $this->comoProveedor();

        $d = $this->getJson("/api/v1/proveedor/citas/{$cita->id}/disponibilidad?fecha=" . $this->dia(9)->toDateString())->assertOk()->json('data');

        $this->assertTrue($d['puede_reagendar']);
        $this->assertSame(30, $d['duracion_cita_minutos']);
        $horarios = collect($d['dias'][0]['horarios'])->keyBy('hora');
        $this->assertSame('libre', $horarios['10:00']['estado']);          // su propio horario no choca consigo
        $this->assertTrue($horarios['10:00']['es_horario_actual']);
        $this->assertSame('ocupado_proveedor', $horarios['11:00']['estado']);
        $this->assertSame('libre', $horarios['11:30']['estado']);          // pegado: sin colchon para el proveedor
        $this->assertSame('ocupado_comprador', $horarios['13:00']['estado']);
        $this->assertSame('ocupado_comprador', $horarios['12:30']['estado']); // colchon del comprador
        $this->assertSame('libre', $horarios['14:00']['estado']);

        $this->getJson("/api/v1/proveedor/citas/{$cita->id}/disponibilidad?fecha=hoy")->assertUnprocessable();
    }

    /* ─────────────── calendario ─────────────── */

    public function test_calendario_como_el_feed_de_la_web_con_rango(): void
    {
        $c = $this->crearComprador('aprobado', ['nombre_empresa' => 'La Tradición']);
        $a = $this->cita($c, $this->dia(10), 'pendiente');
        $b = $this->cita($c, $this->dia(11), 'confirmada');
        $this->cita($c, $this->dia(12), 'cancelada');
        $lejos = $this->cita($c, $this->dia(10)->addDays(10), 'confirmada');
        $this->comoProveedor();

        $r = $this->getJson('/api/v1/proveedor/calendario')->assertOk();
        $this->assertSame([$a->id, $b->id, $lejos->id], collect($r->json('data'))->pluck('id')->all());
        $this->assertSame('#f59e0b', $r->json('data.0.color'));
        $this->assertSame($c->name, $r->json('data.0.titulo'));
        $this->assertSame('La Tradición', $r->json('data.0.comprador.nombre_empresa'));

        $dia = $this->dia(9)->toDateString();
        $r = $this->getJson("/api/v1/proveedor/calendario?desde={$dia}&hasta={$dia}")->assertOk();
        $this->assertSame([$a->id, $b->id], collect($r->json('data'))->pluck('id')->all());
        $this->getJson("/api/v1/proveedor/calendario?desde={$dia}&hasta=2020-01-01")->assertUnprocessable()->assertJsonValidationErrors(['hasta']);
    }

    /* ─────────────── inicio ─────────────── */

    public function test_inicio_agrega_pendientes_del_proveedor_sin_romper_lo_existente(): void
    {
        $c = $this->crearComprador();
        $this->cita($c, $this->dia(10), 'pendiente');
        $this->cita($c, $this->dia(11), 'pendiente_reconfirmacion', null, ['propuesta_inicio' => $this->dia(15), 'propuesta_fin' => $this->dia(15, 30)]);
        $this->comoProveedor();

        $d = $this->getJson('/api/v1/inicio')->assertOk()->json('data');
        $this->assertSame('proveedor', $d['vista']);
        $this->assertArrayHasKey('pendientes', $d);
        $this->assertArrayHasKey('proxima_cita', $d);
        $this->assertSame(1, $d['proveedor']['citas_por_responder']);
        $this->assertSame(1, $d['proveedor']['reagendas_esperando_comprador']);
        $this->assertFalse($d['proveedor']['perfil_completo']);
        $this->assertContains('contacto', $d['proveedor']['secciones_faltantes']);
        $this->assertSame('pendiente', $d['proveedor']['negocio']['estado']);

        Sanctum::actingAs($c);
        $this->assertNull($this->getJson('/api/v1/inicio')->json('data.proveedor'));
    }
}
