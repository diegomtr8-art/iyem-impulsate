<?php

namespace App\Console\Commands;

use App\Models\AgendaPropuesta;
use App\Models\AgendaPropuestaCita;
use App\Models\Cita;
use App\Models\EncuestaPlantilla;
use App\Models\EncuestaSatisfaccion;
use App\Models\Evento;
use App\Models\Horario;
use App\Models\Notificacion;
use App\Models\PlantillaCorreo;
use App\Models\Publicidad;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Datos de demostracion para desarrollar la app movil contra la BD LOCAL.
 * Idempotente: se puede correr las veces que sea (updateOrCreate por correo,
 * nombre de evento y combinacion comprador/proveedor/evento).
 */
class DemoMovilCommand extends Command
{
    protected $signature = 'impulsate:demo-movil';

    protected $description = 'Siembra eventos, proveedores, citas y notificaciones de demo para la app movil (solo entorno local)';

    private const CORREO_COMPRADOR = 'comprador@prueba.impulsate.mx';
    private const CORREO_PROVEEDOR = 'proveedor@prueba.impulsate.mx';
    private const CORREO_ADMIN     = 'admin@prueba.impulsate.mx';
    private const CORREO_PROVEEDOR_NUEVO = 'proveedor.nuevo@prueba.impulsate.mx';
    private const PASS_PROVEEDOR   = 'Proveedor2026!';
    private const PASS_COMPRADOR   = 'Prueba2026!';

    /** [nombre, categoria, municipio, productos] — categorias de la tabla categorias. */
    private const PROVEEDORES = [
        ['Miel Melipona del Mayab',      'Alimentos y Bebidas',      'Tizimín',    ['Miel melipona 250 g', 'Jabón de miel']],
        ['Café Puuc',                    'Alimentos y Bebidas',      'Oxkutzcab',  ['Café de altura molido', 'Café en grano 1 kg']],
        ['Textiles Xokbil',              'Textiles',                 'Mérida',     ['Mantelería bordada', 'Servilletas de henequén']],
        ['Artesanías Kanasín',           'Artesanías',               'Kanasín',    ['Jícaras decoradas', 'Hamacas']],
        ['Limpieza Peninsular',          'Limpieza e higiene',       'Umán',       ['Desengrasante industrial', 'Jabón líquido 20 L']],
        ['Cristalería Progreso',         'Cristalería, plásticos y utensilios', 'Progreso', ['Vasos templados', 'Charolas de acero']],
        ['Licores de Xtabentún Izamal',  'Vinos y licores',          'Izamal',     ['Xtabentún 750 ml', 'Licor de anís']],
        ['Refrigeración Yucatán',        'Equipos, mantenimiento y reparación', 'Mérida', ['Mantenimiento de cámaras frías', 'Refacciones']],
    ];

    public function handle(): int
    {
        if (!app()->environment('local')) {
            $this->error('impulsate:demo-movil solo corre con APP_ENV=local. Entorno actual: ' . app()->environment());
            return self::FAILURE;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['cliente', 'restaurantero', 'admin', 'super-admin'] as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }

        DB::transaction(function () {
            [$encuentro, $bazar, $finalizado] = $this->eventos();

            $admin = User::where('email', self::CORREO_ADMIN)->first();
            if ($admin && !$admin->hasRole('admin')) {
                $admin->assignRole('admin');
            }

            $proveedores = collect(self::PROVEEDORES)
                ->map(fn ($p, $i) => $this->proveedorDemo($i + 1, ...$p))
                ->each(fn (Restaurantero $r) => $this->inscribir($encuentro, $r->user, 'proveedor'));

            $principal = $this->proveedorPrincipal($encuentro);
            $this->inscribir($encuentro, $principal->user, 'proveedor');

            $comprador = $this->comprador();
            $this->inscribir($encuentro, $comprador, 'comprador');
            $this->inscribir($finalizado, $comprador, 'comprador');

            $otroComprador = $this->compradorDemo();
            $this->inscribir($encuentro, $otroComprador, 'comprador');

            $dia = $encuentro->fecha_hora_inicio->copy()->startOfDay();

            // Citas del comprador de prueba, una por estado.
            $this->cita($encuentro, $principal,       $comprador,     $dia->copy()->setTime(10, 0),  'confirmada', 'Mesa 3');
            $this->cita($encuentro, $proveedores[0],  $comprador,     $dia->copy()->setTime(10, 40), 'pendiente');
            $this->cita($encuentro, $proveedores[1],  $comprador,     $dia->copy()->setTime(11, 20), 'cancelada');
            $this->cita($finalizado, $proveedores[2], $comprador,     $finalizado->fecha_hora_inicio->copy()->setTime(12, 0), 'completada');

            // Citas recibidas por el proveedor de prueba (Fase 3): 2 pendientes,
            // 2 confirmadas (la de arriba y una mas), 1 rechazada y 1 con
            // propuesta de reagenda del proveedor esperando al comprador.
            $this->cita($encuentro, $principal,       $otroComprador, $dia->copy()->setTime(11, 0),  'pendiente');
            $recibidas = $this->compradoresParaProveedor($encuentro);
            $this->cita($encuentro, $principal, $recibidas[0], $dia->copy()->setTime(11, 30), 'pendiente');
            $this->cita($encuentro, $principal, $recibidas[1], $dia->copy()->setTime(12, 0),  'confirmada', 'Mesa 5');
            $this->cita($encuentro, $principal, $recibidas[2], $dia->copy()->setTime(12, 30), 'rechazada');
            $this->citaConPropuestaDeReagenda($encuentro, $principal, $recibidas[3], $dia, [13, 30], [15, 30]);

            $this->proveedorNuevo();

            // Fase 2: una reagenda por confirmar, una agenda propuesta por el
            // admin y una encuesta de satisfaccion por responder.
            $this->citaConPropuestaDeReagenda($encuentro, $proveedores[3], $comprador, $dia);
            $this->agendaPropuesta($encuentro, $comprador, $admin, [$proveedores[4], $proveedores[5], $proveedores[6]], $dia);
            $this->encuestaPendiente($finalizado, $comprador);

            $this->notificaciones($comprador, $encuentro, $principal);

            // Fase 4A (admin): solicitudes por decidir en el encuentro,
            // proveedores esperando aprobacion, expositores del bazar y un
            // evento HOY con citas en distintos estados para la torre.
            $this->superAdminDemo();
            $this->solicitudesPendientes($encuentro);
            $this->proveedoresPorAprobar();
            $this->expositoresBazar($bazar);
            $this->torreDeHoy($proveedores, array_merge($recibidas, [$otroComprador]));

            // Fase 4B (admin): encuestas respondidas para las graficas, un
            // anuncio vigente y las plantillas de correo del sistema.
            $this->encuestasRespondidas($finalizado, [$recibidas[0], $recibidas[1]], [$principal->user, $proveedores[0]->user]);
            $this->publicidadDemo();
            $this->plantillasCorreo();
        });

        $this->resumen();

        return self::SUCCESS;
    }

    /** @return array{0: Evento, 1: Evento, 2: Evento} */
    private function eventos(): array
    {
        $inicio = now()->addWeeks(2)->setTime(9, 0);

        $encuentro = Evento::updateOrCreate(['nombre' => 'Encuentro de Negocios Impúlsate (demo app)'], [
            'sector_economico'              => 'Alimentos y Bebidas',
            'descripcion'                   => 'Encuentro de negocios entre compradores del sector restaurantero y proveedores locales de Yucatán.',
            'tipo_evento'                   => 'encuentro_negocios',
            'activa'                        => true,
            'destacado'                     => true,
            'fecha_inicio'                  => $inicio->toDateString(),
            'fecha_hora_inicio'             => $inicio,
            'fecha_hora_fin'                => $inicio->copy()->setTime(18, 0),
            'fecha_hora_inicio_proveedores' => now()->subDays(5),
            'fecha_hora_fin_proveedores'    => $inicio->copy()->subDays(2),
            'fecha_hora_inicio_compradores' => now()->subDays(5),
            'fecha_hora_fin_compradores'    => $inicio->copy()->subDay(),
            'tiempo_entre_citas_minutos'    => 30,
            'max_citas_por_comprador'       => 5,
        ]);

        $inicioBazar = now()->addWeeks(3)->setTime(10, 0);
        $bazar = Evento::updateOrCreate(['nombre' => 'Bazar Hecho en Yucatán (demo app)'], [
            'sector_economico'             => 'Artesanías',
            'descripcion'                  => 'Bazar y exposición de productos locales. Requiere INE y CSF vigente.',
            'tipo_evento'                  => 'bazar_exposicion',
            'activa'                       => true,
            'destacado'                    => false,
            'fecha_inicio'                 => $inicioBazar->toDateString(),
            'fecha_hora_inicio'            => $inicioBazar,
            'fecha_hora_fin'               => $inicioBazar->copy()->addDay()->setTime(19, 0),
            'fecha_aceptacion_solicitudes' => now()->subDay(),
            'max_espacios'                 => 30,
        ]);

        $inicioPasado = now()->subMonth()->setTime(9, 0);
        $finalizado = Evento::updateOrCreate(['nombre' => 'Encuentro de Negocios Julio (demo app)'], [
            'sector_economico'           => 'Alimentos y Bebidas',
            'descripcion'                => 'Edición anterior del encuentro (finalizada).',
            'tipo_evento'                => 'encuentro_negocios',
            'activa'                     => false,
            'destacado'                  => false,
            'fecha_inicio'               => $inicioPasado->toDateString(),
            'fecha_hora_inicio'          => $inicioPasado,
            'fecha_hora_fin'             => $inicioPasado->copy()->setTime(18, 0),
            'tiempo_entre_citas_minutos' => 30,
            'max_citas_por_comprador'    => 5,
        ]);

        return [$encuentro, $bazar, $finalizado];
    }

    private function usuario(string $correo, string $nombre, string $password, array $datos): User
    {
        $user = User::firstOrNew(['email' => $correo]);
        $user->fill(array_merge([
            'name'             => $nombre,
            'acepta_aviso_at'  => $user->acepta_aviso_at ?? now(),
            'rol_seleccionado' => true,
        ], $datos));
        if (!$user->exists) {
            $user->password = Hash::make($password);
        }
        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()])->save();

        return $user;
    }

    private function proveedorDemo(int $n, string $nombre, string $categoria, string $municipio, array $productos): Restaurantero
    {
        $user = $this->usuario("demo.proveedor{$n}@prueba.impulsate.mx", "Contacto {$nombre}", self::PASS_PROVEEDOR, [
            'active_role' => 'proveedor',
            'telefono'    => '99910000' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
            'municipio'   => $municipio,
        ]);
        if (!$user->hasRole('restaurantero')) {
            $user->assignRole('restaurantero');
        }

        return $this->negocio($user, [
            'nombre_restaurante' => $nombre,
            'descripcion'        => "{$nombre}: proveedor local de {$categoria} en {$municipio}.",
            'telefono'           => $user->telefono,
            'municipio'          => $municipio,
            'categoria'          => $categoria,
            'logo_path'          => null,
            'productos_top'      => collect($productos)->map(fn ($p) => [
                'nombre' => $p, 'descripcion' => '', 'foto_path' => null,
                'capacidad_cantidad' => 100, 'capacidad_unidad' => 'piezas',
            ])->all(),
            'entrega_domicilio'  => false,
            'factura'            => false,
            'acepta_credito'     => false,
        ]);
    }

    private function proveedorPrincipal(Evento $encuentro): Restaurantero
    {
        $user = $this->usuario(self::CORREO_PROVEEDOR, 'Proveedor de Prueba', self::PASS_PROVEEDOR, [
            'active_role' => 'proveedor',
            'telefono'    => '9991112233',
            'municipio'   => 'Mérida',
            'perfil_completo' => true,
        ]);
        if (!$user->hasRole('restaurantero')) {
            $user->assignRole('restaurantero');
        }

        // Un perfil completo exige logo: se deja una imagen minima en el disco publico.
        $logo = 'logos/demo-proveedor-prueba.png';
        if (!Storage::disk('public')->exists($logo)) {
            Storage::disk('public')->put($logo, base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
            ));
        }

        $r = $this->negocio($user, [
            'nombre_restaurante'   => 'Salsas Doña Chela',
            'razon_social'         => 'Salsas Doña Chela SA de CV',
            'nombre_representante' => 'Graciela Pech',
            'descripcion'          => 'Salsas y recados artesanales yucatecos para restaurantes.',
            'mercado_meta'         => 'Restaurantes y hoteles de la península.',
            'tipo_oferta'          => 'producto',
            'telefono'             => '9991112233',
            'municipio'            => 'Mérida',
            'direccion'            => 'Calle 60 x 55, Centro, Mérida',
            'categoria'            => 'Alimentos y Bebidas',
            'logo_path'            => $logo,
            'productos_top'        => [
                ['nombre' => 'Recado rojo 1 kg', 'descripcion' => 'Achiote artesanal', 'foto_path' => null, 'capacidad_cantidad' => 500, 'capacidad_unidad' => 'kilogramos'],
                ['nombre' => 'Salsa de habanero 250 ml', 'descripcion' => 'Picor alto', 'foto_path' => null, 'capacidad_cantidad' => 2000, 'capacidad_unidad' => 'piezas'],
            ],
            'rfc'                  => 'SDC200101AB1',
            'factura'              => true,
            'regimen_fiscal'       => '601 - General de Ley Personas Morales',
            'acepta_credito'       => true,
            'credito_a_negociar'   => true,
            'pago_contraentrega'   => true,
            'entrega_domicilio'    => true,
            'cobertura_entrega'    => 'regional',
            'forma_entrega'        => 'programada',
        ]);

        if (!$r->perfil_completo) {
            $this->warn('Aviso: el perfil del proveedor de prueba no quedó completo; revisa calcularPerfilCompleto().');
        }

        return $r;
    }

    /** Perfil de negocio + servicio + horarios. Se crea ya aprobado para no disparar la auto-aprobacion (correo). */
    private function negocio(User $user, array $datos): Restaurantero
    {
        $r = Restaurantero::firstOrNew(['user_id' => $user->id]);
        $r->fill(array_merge($datos, [
            'edicion_id'               => Evento::where('nombre', 'Encuentro de Negocios Impúlsate (demo app)')->value('id'),
            'activo'                   => true,
            'aprobado'                 => true,
            'rechazado'                => false,
            'solicitado_aprobacion_at' => $r->solicitado_aprobacion_at ?? now(),
        ]))->save();

        Servicio::firstOrCreate(
            ['restaurantero_id' => $r->id, 'nombre' => 'Mesa de Networking'],
            ['duracion_minutos' => 30, 'precio' => 0, 'activo' => true]
        );

        for ($dia = 1; $dia <= 5; $dia++) {
            Horario::firstOrCreate(
                ['restaurantero_id' => $r->id, 'dia_semana' => $dia],
                ['hora_inicio' => '09:00:00', 'hora_fin' => '16:00:00', 'activo' => true]
            );
        }

        return $r->load('user');
    }

    private function comprador(): User
    {
        $user = $this->usuario(self::CORREO_COMPRADOR, 'Comprador de Prueba', self::PASS_COMPRADOR, [
            'active_role' => User::where('email', self::CORREO_COMPRADOR)->value('active_role') ?? 'comprador',
        ]);
        if (!$user->hasRole('cliente')) {
            $user->assignRole('cliente');
        }

        return $user;
    }

    private function compradorDemo(): User
    {
        $user = $this->usuario('demo.comprador1@prueba.impulsate.mx', 'Restaurante La Tradición', self::PASS_COMPRADOR, [
            'active_role'       => 'comprador',
            'telefono'          => '9992223344',
            'municipio'         => 'Valladolid',
            'nombre_empresa'    => 'Restaurante La Tradición',
            'camara_asociacion' => 'CANIRAC',
            'nombre_establecimiento' => 'La Tradición',
            'necesidades'       => 'Salsas, recados y miel para el menú de temporada.',
            'es_restaurantero'  => true,
            'perfil_completo'   => true,
        ]);
        if (!$user->hasRole('cliente')) {
            $user->assignRole('cliente');
        }

        return $user;
    }

    /**
     * Compradores demo 2..5, aprobados en el encuentro, que le mandan citas al
     * proveedor de prueba (una cita por comprador/proveedor/evento).
     *
     * @return list<User>
     */
    private function compradoresParaProveedor(Evento $encuentro): array
    {
        $datos = [
            [2, 'Hotel Hacienda Xcanatún',    'Mérida',  'Recados y salsas para el restaurante del hotel.'],
            [3, 'Cocina Económica Doña Mary', 'Umán',    'Salsas picantes por mayoreo.'],
            [4, 'Taquería El Trompo',         'Kanasín', 'Salsa de habanero en galón.'],
            [5, 'Restaurante Chaya Maya',     'Mérida',  'Recado rojo y negro para cochinita y relleno negro.'],
        ];

        return array_map(function ($d) use ($encuentro) {
            [$n, $empresa, $municipio, $necesidades] = $d;
            $user = $this->usuario("demo.comprador{$n}@prueba.impulsate.mx", "Compras {$empresa}", self::PASS_COMPRADOR, [
                'active_role'       => 'comprador',
                'telefono'          => '99922200' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'municipio'         => $municipio,
                'nombre_empresa'    => $empresa,
                'camara_asociacion' => 'Ninguna',
                'necesidades'       => $necesidades,
                'perfil_completo'   => true,
            ]);
            if (!$user->hasRole('cliente')) {
                $user->assignRole('cliente');
            }
            $this->inscribir($encuentro, $user, 'comprador');

            return $user;
        }, $datos);
    }

    /**
     * Proveedor recien registrado con el perfil de negocio INCOMPLETO (para el
     * wizard de la app). Cada corrida lo deja otra vez incompleto y sin registro
     * a eventos.
     */
    private function proveedorNuevo(): Restaurantero
    {
        $user = $this->usuario(self::CORREO_PROVEEDOR_NUEVO, 'Proveedor Nuevo de Prueba', self::PASS_PROVEEDOR, [
            'active_role'     => 'proveedor',
            'telefono'        => '9993334455',
            'municipio'       => 'Progreso',
            'perfil_completo' => false,
        ]);
        if (!$user->hasRole('restaurantero')) {
            $user->assignRole('restaurantero');
        }

        $r = Restaurantero::firstOrNew(['user_id' => $user->id]);
        // Sin logo, descripcion ni productos: calcularPerfilCompleto() da false,
        // el hook saved() no lo auto-aprueba (no manda correo) y queda 'pendiente'.
        $r->forceFill([
            'edicion_id'               => null,
            'nombre_restaurante'       => 'Tortillería La Nueva (demo app)',
            'razon_social'             => null,
            'descripcion'              => null,
            'telefono'                 => null,
            'municipio'                => 'Progreso',
            'direccion'                => null,
            'categoria'                => null,
            'categorias_json'          => [],
            'logo_path'                => null,
            'productos_top'            => [],
            'tipo_oferta'              => 'producto',
            'entrega_domicilio'        => null,
            'cobertura_entrega'        => null,
            'forma_entrega'            => null,
            'factura'                  => false,
            'rfc'                      => null,
            'regimen_fiscal'           => null,
            'acepta_credito'           => false,
            'credito_a_negociar'       => false,
            'credito_monto_maximo'     => null,
            'credito_tiempo_cantidad'  => null,
            'credito_tiempo_unidad'    => null,
            'pago_contraentrega'       => false,
            'activo'                   => false,
            'aprobado'                 => false,
            'rechazado'                => false,
            'motivo_rechazo'           => null,
            'solicitado_aprobacion_at' => $r->solicitado_aprobacion_at ?? now(),
        ])->save();

        Servicio::firstOrCreate(
            ['restaurantero_id' => $r->id, 'nombre' => 'Mesa de Networking'],
            ['duracion_minutos' => 30, 'precio' => 0, 'activo' => true]
        );
        for ($dia = 1; $dia <= 5; $dia++) {
            Horario::firstOrCreate(
                ['restaurantero_id' => $r->id, 'dia_semana' => $dia],
                ['hora_inicio' => '09:00:00', 'hora_fin' => '16:00:00', 'activo' => true]
            );
        }

        DB::table('evento_usuario')->where('user_id', $user->id)->delete();

        return $r;
    }

    private function inscribir(Evento $evento, User $user, string $tipo): void
    {
        DB::table('evento_usuario')->updateOrInsert(
            ['evento_id' => $evento->id, 'user_id' => $user->id, 'tipo' => $tipo],
            ['estado' => 'aprobado', 'respondido_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );
    }

    private function cita(Evento $evento, Restaurantero $r, User $cliente, Carbon $inicio, string $estado, ?string $mesa = null): Cita
    {
        return Cita::updateOrCreate(
            ['edicion_id' => $evento->id, 'restaurantero_id' => $r->id, 'cliente_id' => $cliente->id],
            [
                'servicio_id' => $r->servicios()->value('id'),
                'inicio'      => $inicio,
                'fin'         => $inicio->copy()->addMinutes(30),
                'estado'      => $estado,
                'mesa'        => $mesa,
                'notas'       => 'Cita de demostración para la app.',
            ]
        );
    }

    /** Cita (por defecto a las 13:00) que el proveedor propone mover (por defecto a las 14:00): estado pendiente_reconfirmacion. */
    private function citaConPropuestaDeReagenda(Evento $encuentro, Restaurantero $r, User $comprador, Carbon $dia, array $hora = [13, 0], array $nuevaHora = [14, 0]): Cita
    {
        $cita = $this->cita($encuentro, $r, $comprador, $dia->copy()->setTime(...$hora), 'pendiente_reconfirmacion');
        $propuesta = $dia->copy()->setTime(...$nuevaHora);

        $cita->update([
            'propuesta_inicio'   => $propuesta,
            'propuesta_fin'      => $propuesta->copy()->addMinutes(30),
            'token_confirmacion' => $cita->token_confirmacion ?? Str::random(64),
        ]);

        return $cita;
    }

    /**
     * Agenda que el admin propone al comprador (15:00, 15:30 y 16:00), enviada
     * y pendiente de respuesta. Al volver a correr el comando se reinicia: se
     * borran las citas que hubiera generado una aceptacion anterior.
     */
    private function agendaPropuesta(Evento $encuentro, User $comprador, ?User $admin, array $proveedores, Carbon $dia): AgendaPropuesta
    {
        $propuesta = AgendaPropuesta::firstOrNew(['evento_id' => $encuentro->id, 'user_id' => $comprador->id]);

        if ($propuesta->exists) {
            Cita::whereIn('id', $propuesta->citas()->whereNotNull('cita_id')->pluck('cita_id'))->delete();
            $propuesta->citas()->delete();
        }

        $propuesta->fill([
            'admin_id'      => $admin?->id ?? User::role('admin')->value('id') ?? $comprador->id,
            'estado'        => 'pendiente',
            'enviada_at'    => now(),
            'respondida_at' => null,
        ]);
        $propuesta->token ??= Str::random(64);
        $propuesta->save();

        foreach ($proveedores as $i => $r) {
            $inicio = $dia->copy()->setTime(15, 0)->addMinutes(30 * $i);
            AgendaPropuestaCita::create([
                'agenda_propuesta_id' => $propuesta->id,
                'restaurantero_id'    => $r->id,
                'slot_inicio'         => $inicio,
                'slot_fin'            => $inicio->copy()->addMinutes(30),
            ]);
        }

        return $propuesta;
    }

    /** Encuesta de satisfaccion del evento finalizado, sin responder (plantilla activa si hay). */
    private function encuestaPendiente(Evento $finalizado, User $comprador): EncuestaSatisfaccion
    {
        $encuesta = EncuestaSatisfaccion::firstOrNew([
            'evento_id' => $finalizado->id, 'user_id' => $comprador->id, 'tipo' => 'comprador',
        ]);

        if ($encuesta->exists) {
            $encuesta->respuestas()->delete();
        }

        $encuesta->fill([
            'segmento'              => 'compradores_evento',
            'completada_at'         => null,
            'es_prueba'             => false,
            'encuesta_plantilla_id' => EncuestaPlantilla::where('activa', true)->value('id'),
        ]);
        $encuesta->token ??= Str::random(40);
        $encuesta->save();

        return $encuesta;
    }

    private function notificaciones(User $comprador, Evento $encuentro, Restaurantero $principal): void
    {
        $lista = [
            ['solicitud_aprobada', 'Solicitud aprobada', "Tu registro como comprador al evento \"{$encuentro->nombre}\" fue aprobado.", true],
            ['cita_aceptada',      'Cita confirmada',    "{$principal->nombre_restaurante} confirmó tu cita.", false],
            ['cita_nueva',         'Nueva cita asignada', 'Se te asignó una cita con Miel Melipona del Mayab.', false],
            ['cita_cancelada',     'Cita cancelada',     'Tu cita con Café Puuc fue cancelada.', true],
            ['info',               'Recuerda llevar tu INE', 'El día del evento presenta tu INE en el registro.', false],
        ];

        foreach ($lista as [$tipo, $titulo, $mensaje, $leida]) {
            Notificacion::updateOrCreate(
                ['user_id' => $comprador->id, 'titulo' => $titulo],
                ['tipo' => $tipo, 'mensaje' => $mensaje, 'leida' => $leida]
            );
        }
    }

    private function resumen(): void
    {
        $comprador = User::where('email', self::CORREO_COMPRADOR)->first();
        $proveedor = User::where('email', self::CORREO_PROVEEDOR)->first();
        $encuentro = Evento::where('nombre', 'Encuentro de Negocios Impúlsate (demo app)')->first();

        $this->table(['Dato', 'Valor'], [
            ['Eventos demo (activos / finalizados)', Evento::where('nombre', 'like', '%(demo app)')->where('activa', true)->count()
                . ' / ' . Evento::where('nombre', 'like', '%(demo app)')->where('activa', false)->count()],
            ['Evento::activo() principal', Evento::activo()?->nombre],
            ['Proveedores demo aprobados en el encuentro', DB::table('evento_usuario')->where('evento_id', $encuentro->id)
                ->where('tipo', 'proveedor')->where('estado', 'aprobado')->count()],
            ['Citas de ' . self::CORREO_COMPRADOR, Cita::where('cliente_id', $comprador->id)
                ->selectRaw('estado, count(*) n')->groupBy('estado')->pluck('n', 'estado')->map(fn ($n, $e) => "$e:$n")->implode(', ')],
            ['Citas recibidas por ' . self::CORREO_PROVEEDOR, Cita::where('restaurantero_id', $proveedor->restaurantero->id)
                ->selectRaw('estado, count(*) n')->groupBy('estado')->pluck('n', 'estado')->map(fn ($n, $e) => "$e:$n")->implode(', ')],
            ['Productos del proveedor (con capacidad)', count($proveedor->restaurantero->productos_top ?? []) . ' ('
                . collect($proveedor->restaurantero->productos_top ?? [])->whereNotNull('capacidad_cantidad')->count() . ')'],
            ['Negocio de ' . self::CORREO_PROVEEDOR_NUEVO, ($nuevo = User::where('email', self::CORREO_PROVEEDOR_NUEVO)->first()?->restaurantero)
                ? ($nuevo->perfil_completo ? 'completo' : 'INCOMPLETO') . ' (faltan: '
                    . collect(app(\App\Services\PerfilProveedorService::class)->faltantes($nuevo))->pluck('campo')->implode(', ') . ')'
                : '—'],
            ['Perfil de negocio del proveedor completo', $proveedor->restaurantero->perfil_completo ? 'sí' : 'no'],
            ['Reagendas por confirmar del comprador', Cita::where('cliente_id', $comprador->id)->where('estado', 'pendiente_reconfirmacion')->count()],
            ['Agendas propuestas pendientes (citas propuestas)', AgendaPropuesta::where('user_id', $comprador->id)->where('estado', 'pendiente')->whereNotNull('enviada_at')->count()
                . ' (' . AgendaPropuestaCita::whereIn('agenda_propuesta_id', AgendaPropuesta::where('user_id', $comprador->id)->where('estado', 'pendiente')->pluck('id'))->count() . ')'],
            ['Encuestas por responder del comprador', EncuestaSatisfaccion::where('user_id', $comprador->id)->whereNull('completada_at')->count()],
            ['Notificaciones del comprador (no leídas)', Notificacion::where('user_id', $comprador->id)->count()
                . ' (' . Notificacion::where('user_id', $comprador->id)->where('leida', false)->count() . ')'],
            ['Solicitudes PENDIENTES en el encuentro (por tipo)', DB::table('evento_usuario')->where('evento_id', $encuentro->id)
                ->where('estado', 'pendiente')->selectRaw('tipo, count(*) n')->groupBy('tipo')->pluck('n', 'tipo')->map(fn ($n, $t) => "$t:$n")->implode(', ')],
            ['Proveedores por aprobar (demo / total)', Restaurantero::whereHas('user', fn ($q) => $q->where('email', 'like', 'demo.por-aprobar%'))
                ->where('aprobado', false)->where('rechazado', false)->count()
                . ' / ' . Restaurantero::where('aprobado', false)->where('rechazado', false)->count()],
            ['Expositores del bazar demo (por estado)', DB::table('evento_usuario')->where('evento_id', Evento::where('nombre', 'Bazar Hecho en Yucatán (demo app)')->value('id'))
                ->where('tipo', 'expositor')->selectRaw('estado, count(*) n')->groupBy('estado')->pluck('n', 'estado')->map(fn ($n, $e) => "$e:$n")->implode(', ')],
            ['Encuestas del evento finalizado (respondidas / enviadas)', EncuestaSatisfaccion::where('evento_id', Evento::where('nombre', 'Encuentro de Negocios Julio (demo app)')->value('id'))
                ->whereNotNull('completada_at')->count() . ' / ' . EncuestaSatisfaccion::where('evento_id', Evento::where('nombre', 'Encuentro de Negocios Julio (demo app)')->value('id'))->count()],
            ['Publicidad vigente', Publicidad::vigente()->value('titulo') ?? '—'],
            ['Plantillas de correo (activas)', PlantillaCorreo::count() . ' (' . PlantillaCorreo::where('activo', true)->count() . ')'],
            ['Citas de HOY en la torre (por estado_tv)', Cita::whereDate('inicio', today())->whereNotIn('estado', ['cancelada', 'rechazada'])
                ->selectRaw("COALESCE(estado_tv, 'pendiente') e, count(*) n")->groupBy('e')->pluck('n', 'e')->map(fn ($n, $e) => "$e:$n")->implode(', ')],
        ]);
    }

    /* ───────────── Fase 4A: material para el panel admin ───────────── */

    /** Cuenta admin + super-admin SOLO local, para probar /admin/usuarios-gestion. */
    private function superAdminDemo(): void
    {
        $u = $this->usuario('superadmin@prueba.impulsate.mx', 'Super Admin de Prueba', 'SuperAdmin2026!', [
            'active_role'     => null,
            'perfil_completo' => true,
        ]);
        foreach (['admin', 'super-admin'] as $rol) {
            if (!$u->hasRole($rol)) {
                $u->assignRole($rol);
            }
        }
    }

    /**
     * 2 compradores y 2 proveedores con su solicitud PENDIENTE en el encuentro.
     * Cada corrida la regresa a pendiente (sirve despues de aprobar/rechazar).
     */
    private function solicitudesPendientes(Evento $encuentro): void
    {
        $compradores = [
            [1, 'Cafetería Los Almendros', 'Mérida',   'CANIRAC', 'Café de especialidad y miel local.'],
            [2, 'Hotel Casa Lecanda',      'Mérida',   'Ninguna', 'Amenidades artesanales y textiles.'],
        ];
        foreach ($compradores as [$n, $empresa, $municipio, $camara, $necesidades]) {
            $u = $this->usuario("demo.solicitud.comprador{$n}@prueba.impulsate.mx", "Compras {$empresa}", self::PASS_COMPRADOR, [
                'active_role'       => 'comprador',
                'telefono'          => '99933300' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'municipio'         => $municipio,
                'nombre_empresa'    => $empresa,
                'camara_asociacion' => $camara,
                'nombre_establecimiento' => $camara === 'CANIRAC' ? $empresa : null,
                'necesidades'       => $necesidades,
                'perfil_completo'   => true,
            ]);
            if (!$u->hasRole('cliente')) {
                $u->assignRole('cliente');
            }
            $this->inscribirPendiente($encuentro, $u, 'comprador');
        }

        $proveedores = [
            [1, 'Chocolates Ek Balam',   'Alimentos y Bebidas', 'Valladolid', ['Chocolate de mesa', 'Cacao en polvo']],
            [2, 'Hamacas Tixkokob Real', 'Artesanías',          'Tixkokob',   ['Hamaca matrimonial', 'Silla hamaca']],
        ];
        foreach ($proveedores as [$n, $nombre, $categoria, $municipio, $productos]) {
            $u = $this->usuario("demo.solicitud.proveedor{$n}@prueba.impulsate.mx", "Contacto {$nombre}", self::PASS_PROVEEDOR, [
                'active_role' => 'proveedor',
                'telefono'    => '99944400' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'municipio'   => $municipio,
            ]);
            if (!$u->hasRole('restaurantero')) {
                $u->assignRole('restaurantero');
            }
            $this->negocio($u, [
                'nombre_restaurante' => $nombre,
                'descripcion'        => "{$nombre}: {$categoria} de {$municipio}.",
                'telefono'           => $u->telefono,
                'municipio'          => $municipio,
                'categoria'          => $categoria,
                'logo_path'          => null,
                'productos_top'      => collect($productos)->map(fn ($p) => [
                    'nombre' => $p, 'descripcion' => '', 'foto_path' => null,
                    'capacidad_cantidad' => 50, 'capacidad_unidad' => 'piezas',
                ])->all(),
                'entrega_domicilio'  => false,
                'factura'            => false,
                'acepta_credito'     => false,
            ]);
            $this->inscribirPendiente($encuentro, $u, 'proveedor');
        }
    }

    private function inscribirPendiente(Evento $evento, User $user, string $tipo, array $extra = []): void
    {
        DB::table('evento_usuario')->updateOrInsert(
            ['evento_id' => $evento->id, 'user_id' => $user->id, 'tipo' => $tipo],
            array_merge([
                'estado' => 'pendiente', 'motivo_rechazo' => null, 'respondido_at' => null,
                'updated_at' => now(), 'created_at' => now(),
            ], $extra)
        );
    }

    /**
     * 2 proveedores recien registrados esperando la aprobacion del admin
     * (perfil incompleto: sin logo, para que el hook no los auto-apruebe).
     * Cada corrida los regresa a "pendiente de aprobacion".
     */
    private function proveedoresPorAprobar(): void
    {
        $datos = [
            [1, 'Panadería San Cristóbal (demo app)', 'Alimentos y Bebidas', 'Motul',   'Pan dulce y de sal para cafeterías.'],
            [2, 'Uniformes Península (demo app)',     'Textiles',            'Mérida',  'Uniformes para cocina y meseros.'],
        ];

        foreach ($datos as [$n, $nombre, $categoria, $municipio, $descripcion]) {
            $u = $this->usuario("demo.por-aprobar{$n}@prueba.impulsate.mx", "Contacto {$nombre}", self::PASS_PROVEEDOR, [
                'active_role' => 'proveedor',
                'telefono'    => '99955500' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'municipio'   => $municipio,
            ]);
            if (!$u->hasRole('restaurantero')) {
                $u->assignRole('restaurantero');
            }

            $r = Restaurantero::firstOrNew(['user_id' => $u->id]);
            $r->forceFill([
                'edicion_id'               => null,
                'nombre_restaurante'       => $nombre,
                'descripcion'              => $descripcion,
                'telefono'                 => $u->telefono,
                'municipio'                => $municipio,
                'categoria'                => $categoria,
                'logo_path'                => null,
                'productos_top'            => [['nombre' => 'Producto de muestra', 'descripcion' => '', 'foto_path' => null, 'capacidad_cantidad' => 10, 'capacidad_unidad' => 'piezas']],
                'entrega_domicilio'        => false,
                'activo'                   => false,
                'aprobado'                 => false,
                'rechazado'                => false,
                'motivo_rechazo'           => null,
                'solicitado_aprobacion_at' => now()->subHours($n),
            ])->save();

            Servicio::firstOrCreate(
                ['restaurantero_id' => $r->id, 'nombre' => 'Mesa de Networking'],
                ['duracion_minutos' => 30, 'precio' => 0, 'activo' => true]
            );

            // Si en una prueba anterior se completo su perfil (p. ej. subiendo
            // el logo), el hook lo auto-aprobo y lo inscribio al encuentro.
            DB::table('evento_usuario')->where('user_id', $u->id)->delete();
        }
    }

    /** 3 expositores en el bazar demo (pendiente, en evaluacion, seleccionado) y 2 criterios. */
    private function expositoresBazar(Evento $bazar): void
    {
        $bazar->forceFill(['con_criterios_evaluacion' => true])->save();
        $calidad = \App\Models\EventoCriterio::firstOrCreate(['evento_id' => $bazar->id, 'nombre' => 'Calidad del producto'], ['porcentaje' => 60, 'orden' => 0]);
        $precio  = \App\Models\EventoCriterio::firstOrCreate(['evento_id' => $bazar->id, 'nombre' => 'Presentación y precio'], ['porcentaje' => 40, 'orden' => 1]);

        $datos = [
            [1, 'Bordados Xmatkuil',  'pendiente', false, null],
            [2, 'Jabones Sac-Nité',   'aprobado',  false, [80, 70]],
            [3, 'Cerámica Ticul Sur', 'aprobado',  true,  [95, 90]],
        ];

        foreach ($datos as [$n, $empresa, $estado, $seleccionado, $puntajes]) {
            $u = $this->usuario("demo.expositor{$n}@prueba.impulsate.mx", "Expositor {$empresa}", self::PASS_COMPRADOR, [
                'active_role'    => 'comprador',
                'nombre_empresa' => $empresa,
                'municipio'      => 'Mérida',
                'telefono'       => '99966600' . str_pad((string) $n, 2, '0', STR_PAD_LEFT),
                'csf_fecha'      => now()->subMonth()->toDateString(),
                'perfil_completo' => true,
            ]);
            if (!$u->hasRole('cliente')) {
                $u->assignRole('cliente');
            }

            \App\Models\EventoEvaluacion::where('evento_id', $bazar->id)->where('user_id', $u->id)->delete();
            $total = null;
            if ($puntajes) {
                \App\Models\EventoEvaluacion::create(['evento_id' => $bazar->id, 'user_id' => $u->id, 'criterio_id' => $calidad->id, 'puntaje' => $puntajes[0]]);
                \App\Models\EventoEvaluacion::create(['evento_id' => $bazar->id, 'user_id' => $u->id, 'criterio_id' => $precio->id, 'puntaje' => $puntajes[1]]);
                $total = round($puntajes[0] * 0.6 + $puntajes[1] * 0.4, 2);
            }

            $this->inscribirPendiente($bazar, $u, 'expositor', [
                'estado'                    => $estado,
                'respondido_at'             => $estado === 'pendiente' ? null : now(),
                'seleccionado'              => $seleccionado,
                'puntaje_total'             => $total,
                'notas_rechazo'             => null,
                'correo_aprobacion_enviado' => false,
                'correo_rechazo_enviado'    => false,
            ]);
        }
    }

    /**
     * Evento "de hoy" para la torre de control con 6 citas en todos los
     * estados de sala. Cada corrida lo mueve a HOY y reinicia las citas.
     *
     * @param Collection<int, Restaurantero>|array $proveedores
     * @param list<User> $compradores
     */
    private function torreDeHoy($proveedores, array $compradores): void
    {
        $hoy = now()->startOfDay();

        $torre = Evento::updateOrCreate(['nombre' => 'Encuentro Torre de Control (demo app)'], [
            'sector_economico'           => 'Alimentos y Bebidas',
            'descripcion'                => 'Evento de HOY para probar la torre de control desde la app.',
            'tipo_evento'                => 'encuentro_negocios',
            'activa'                     => true,
            'destacado'                  => false,
            'fecha_inicio'               => $hoy->toDateString(),
            'fecha_hora_inicio'          => $hoy->copy()->setTime(9, 0),
            'fecha_hora_fin'             => $hoy->copy()->setTime(23, 59),
            'fecha_hora_inicio_proveedores' => $hoy->copy()->subDays(10),
            'fecha_hora_fin_proveedores'    => $hoy->copy()->subDays(2),
            'fecha_hora_inicio_compradores' => $hoy->copy()->subDays(10),
            'fecha_hora_fin_compradores'    => $hoy->copy()->subDay(),
            'tiempo_entre_citas_minutos' => 30,
            'max_citas_por_comprador'    => 5,
        ]);

        // [hora, minuto, estado, estado_tv, mesa]
        $plan = [
            [9, 30,  'completada', 'finalizada', '1'],
            [10, 0,  'confirmada', 'ausente',    null],
            [10, 30, 'confirmada', 'en_curso',   '2'],
            [11, 0,  'confirmada', 'llamando',   '4'],
            [11, 30, 'confirmada', 'pendiente',  null],
            [12, 0,  'confirmada', 'pendiente',  null],
        ];

        foreach ($plan as $i => [$h, $m, $estado, $estadoTv, $mesa]) {
            $r = $proveedores[$i];
            $c = $compradores[$i % count($compradores)];
            $this->inscribir($torre, $r->user, 'proveedor');
            $this->inscribir($torre, $c, 'comprador');

            $inicio = $hoy->copy()->setTime($h, $m);
            $cita = $this->cita($torre, $r, $c, $inicio, $estado, $mesa);
            $cita->forceFill([
                'estado_tv'        => $estadoTv,
                'llamada_at'       => in_array($estadoTv, ['llamando', 'en_curso', 'finalizada']) ? $inicio->copy()->subMinutes(2) : null,
                'hora_real_inicio' => in_array($estadoTv, ['en_curso', 'finalizada']) ? $inicio->copy()->addMinutes(1) : null,
                'hora_real_fin'    => $estadoTv === 'finalizada' ? $inicio->copy()->addMinutes(24) : null,
            ])->save();
        }
    }

    /* ───────────── Fase 4B: material para el panel admin ───────────── */

    /**
     * Encuestas RESPONDIDAS del evento finalizado (2 compradores y 2
     * proveedores) con la plantilla activa, contestadas con
     * EncuestaRespuestaService (respeta tipos y condiciones), para que
     * /admin/encuestas/resumen y /graficas tengan datos. Cada corrida borra
     * sus respuestas y las vuelve a contestar.
     */
    private function encuestasRespondidas(Evento $finalizado, array $compradores, array $proveedores): void
    {
        $plantilla = EncuestaPlantilla::where('activa', true)->first() ?? EncuestaPlantilla::create([
            'nombre'    => 'Encuesta de satisfacción (demo app)',
            'segmento'  => 'todos',
            'activa'    => true,
            'preguntas' => [
                ['id' => 'general', 'tipo' => 'escala', 'texto' => '¿Cómo calificas el encuentro?', 'escala_min' => 1, 'escala_max' => 5],
                ['id' => 'recomienda', 'tipo' => 'binario', 'texto' => '¿Recomendarías el encuentro?'],
                ['id' => 'nps', 'tipo' => 'nps', 'texto' => '¿Qué tan probable es que vuelvas a participar?'],
                ['id' => 'comentarios', 'tipo' => 'texto', 'texto' => 'Comentarios', 'requerida' => false],
            ],
        ]);

        $servicio = app(\App\Services\EncuestaRespuestaService::class);
        $personas = array_merge(
            array_map(fn ($u) => [$u, 'comprador'], $compradores),
            array_map(fn ($u) => [$u, 'proveedor'], $proveedores),
        );

        foreach ($personas as $n => [$user, $tipo]) {
            $this->inscribir($finalizado, $user, $tipo);

            $encuesta = EncuestaSatisfaccion::firstOrNew(['evento_id' => $finalizado->id, 'user_id' => $user->id, 'tipo' => $tipo]);
            if ($encuesta->exists) {
                $encuesta->respuestas()->delete();
            }
            $encuesta->fill([
                'segmento'              => $tipo === 'proveedor' ? 'proveedores_evento' : 'compradores_evento',
                'completada_at'         => null,
                'es_prueba'             => false,
                'encuesta_plantilla_id' => $plantilla->id,
            ]);
            $encuesta->token ??= Str::random(40);
            $encuesta->save();

            $servicio->responder($encuesta->fresh('plantilla'), $this->respuestasDemo($plantilla->preguntas, $user, $tipo, $n, $servicio));
            $encuesta->update(['completada_at' => $finalizado->fecha_hora_fin?->copy()->addDays($n + 1) ?? now()]);
        }
    }

    /** Una respuesta valida por pregunta visible, variando segun $n. */
    private function respuestasDemo(array $preguntas, User $user, string $tipo, int $n, $servicio): array
    {
        $textos = ['Muy buena organización.', 'Faltó más tiempo por cita.', 'Excelente oportunidad para vender.', 'Me gustaría más compradores de hoteles.'];
        $entrada = [];

        foreach ($preguntas as $p) {
            if (!$servicio->esVisible($p, $entrada)) {
                continue;
            }
            $ops = array_values($p['opciones'] ?? []);
            $clave = mb_strtolower($p['id'] . ' ' . $p['texto']);

            $entrada[$p['id']] = match ($p['tipo']) {
                'escala'   => max((int) ($p['escala_min'] ?? 1), (int) ($p['escala_max'] ?? 5) - ($n % 3)),
                'nps'      => 10 - ($n % 4),
                'binario'  => $n === 3 ? 'No' : 'Sí',
                'multiple' => $ops ? array_slice($ops, $n % count($ops), 2) : [],
                'opciones' => $ops
                    ? (collect($ops)->first(fn ($o) => mb_strtolower($o) === $tipo) ?? $ops[$n % count($ops)])
                    : 'Opción ' . ($n + 1),
                default    => match (true) {
                    str_contains($clave, 'correo')  => $user->email,
                    str_contains($clave, 'negocio') => $user->restaurantero?->nombre_restaurante ?? $user->nombre_empresa ?? $user->name,
                    str_contains($clave, 'nombre')  => $user->name,
                    default                         => $textos[$n % count($textos)],
                },
            };
        }

        return $entrada;
    }

    /** Un anuncio VIGENTE (ayer a +30 dias) con imagen generada. */
    private function publicidadDemo(): void
    {
        $ruta = 'publicidad/demo-app.png';
        if (!Storage::disk('public')->exists($ruta)) {
            $img = imagecreatetruecolor(800, 450);
            imagefill($img, 0, 0, imagecolorallocate($img, 0x8b, 0x10, 0x28));
            imagefilledrectangle($img, 40, 150, 760, 300, imagecolorallocate($img, 0xe0, 0xa9, 0x3e));
            imagestring($img, 5, 300, 215, 'Impulsate - demo app', imagecolorallocate($img, 0xff, 0xff, 0xff));
            ob_start();
            imagepng($img);
            Storage::disk('public')->put($ruta, ob_get_clean());
            imagedestroy($img);
        }

        Publicidad::updateOrCreate(['titulo' => 'Anuncio de prueba (demo app)'], [
            'imagen'                => $ruta,
            'enlace'                => url('/'),
            'abre_en_nueva_pestana' => false,
            'fecha_inicio'          => now()->subDay()->startOfDay(),
            'fecha_fin'             => now()->addDays(30)->endOfDay(),
            'activa'                => true,
        ]);
    }

    /** Plantillas de correo del sistema (firstOrCreate: no pisa las editadas). */
    private function plantillasCorreo(): void
    {
        (new \Database\Seeders\PlantillasCorreoSeeder())->setCommand($this)->run();
    }
}
