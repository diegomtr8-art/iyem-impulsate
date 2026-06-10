<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Evento;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    private array $nombresEmpresas = [
        'Sabores Yucatecos del Sur',
        'Tecnología Peninsular S.A.',
        'Mérida Health & Wellness',
        'Consultores del Mayab',
        'Manufacturas del Sureste',
        'Comercializadora Itzamná',
        'Constructora Kukulcán',
        'EduYucatán Centro de Formación',
        'Xibalbá Turismo y Aventura',
        'Logística Express Yucatán',
    ];

    private array $municipios = [
        'Mérida', 'Valladolid', 'Tizimín', 'Progreso', 'Ticul',
        'Izamal', 'Motul', 'Umán', 'Kanasín', 'Maxcanú',
    ];

    private array $categorias = [
        'Alimentos y Bebidas', 'Tecnología', 'Salud y Bienestar',
        'Servicios Profesionales', 'Manufactura', 'Comercio',
        'Construcción', 'Educación', 'Turismo', 'Logística',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $this->command->info('Sembrando datos de demostración...');

        $ahora = Carbon::now();

        // ── 3 Eventos ──────────────────────────────────────────────────────────
        $eventoActivo = Evento::create([
            'nombre'                         => 'Impúlsate 2026 — Encuentro Alimentos',
            'sector_economico'               => 'Alimentos y Bebidas',
            'descripcion'                    => 'Encuentro de negocios para conectar proveedores del sector alimentario con compradores estratégicos de Yucatán.',
            'activa'                         => true,
            'fecha_inicio'                   => $ahora->toDateString(),
            'fecha_hora_inicio_proveedores'  => $ahora->copy()->subDays(30),
            'fecha_hora_fin_proveedores'     => $ahora->copy()->addDays(5),
            'fecha_hora_inicio_compradores'  => $ahora->copy()->subDays(15),
            'fecha_hora_fin_compradores'     => $ahora->copy()->addDays(7),
            'fecha_hora_inicio'              => $ahora->copy()->addDays(3)->setHour(9)->setMinute(0)->setSecond(0),
            'fecha_hora_fin'                 => $ahora->copy()->addDays(3)->setHour(16)->setMinute(0)->setSecond(0),
            'max_citas_por_comprador'        => 3,
            'tiempo_entre_citas_minutos'     => 30,
        ]);

        $eventoPasado = Evento::create([
            'nombre'                         => 'Impúlsate 2025 — Tecnología',
            'sector_economico'               => 'Tecnología',
            'descripcion'                    => 'Evento pasado del sector tecnológico de Yucatán.',
            'activa'                         => false,
            'fecha_inicio'                   => $ahora->copy()->subMonths(6)->toDateString(),
            'fecha_corte'                    => $ahora->copy()->subMonths(5)->toDateString(),
            'fecha_hora_inicio_proveedores'  => $ahora->copy()->subMonths(7),
            'fecha_hora_fin_proveedores'     => $ahora->copy()->subMonths(6)->subDays(5),
            'fecha_hora_inicio_compradores'  => $ahora->copy()->subMonths(6)->subDays(10),
            'fecha_hora_fin_compradores'     => $ahora->copy()->subMonths(6)->subDays(2),
            'fecha_hora_inicio'              => $ahora->copy()->subMonths(6)->setHour(9)->setMinute(0)->setSecond(0),
            'fecha_hora_fin'                 => $ahora->copy()->subMonths(6)->setHour(16)->setMinute(0)->setSecond(0),
            'max_citas_por_comprador'        => 3,
            'tiempo_entre_citas_minutos'     => 30,
        ]);

        Evento::create([
            'nombre'                         => 'Impúlsate 2026 — Salud y Bienestar',
            'sector_economico'               => 'Salud y Bienestar',
            'descripcion'                    => 'Próximo encuentro de negocios para el sector salud y bienestar de Yucatán.',
            'activa'                         => false,
            'fecha_inicio'                   => $ahora->copy()->addMonths(2)->toDateString(),
            'fecha_hora_inicio_proveedores'  => $ahora->copy()->addMonths(1),
            'fecha_hora_fin_proveedores'     => $ahora->copy()->addMonths(2)->subDays(5),
            'fecha_hora_inicio_compradores'  => $ahora->copy()->addMonths(1)->addDays(15),
            'fecha_hora_fin_compradores'     => $ahora->copy()->addMonths(2)->subDays(2),
            'fecha_hora_inicio'              => $ahora->copy()->addMonths(2)->setHour(9)->setMinute(0)->setSecond(0),
            'fecha_hora_fin'                 => $ahora->copy()->addMonths(2)->setHour(16)->setMinute(0)->setSecond(0),
            'max_citas_por_comprador'        => 3,
            'tiempo_entre_citas_minutos'     => 30,
        ]);

        $this->command->info('  ✅ 3 eventos creados.');

        // ── 10 Proveedores ─────────────────────────────────────────────────────
        $proveedores = [];
        foreach ($this->nombresEmpresas as $i => $nombre) {
            $municipio = $this->municipios[$i];
            $categoria = $this->categorias[$i];

            $userProv = User::create([
                'name'              => 'Rep. ' . $nombre,
                'email'             => 'proveedor' . ($i + 1) . '@demo.impulsate.test',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'active_role'       => 'proveedor',
                'nombre_empresa'    => $nombre,
                'municipio'         => $municipio,
                'telefono'          => '999' . str_pad(($i + 1) * 100, 7, '0', STR_PAD_LEFT),
            ]);
            $userProv->assignRole('restaurantero');

            $restaurantero = Restaurantero::create([
                'user_id'               => $userProv->id,
                'edicion_id'            => $eventoActivo->id,
                'nombre_restaurante'    => $nombre,
                'razon_social'          => $nombre . ' S.A. de C.V.',
                'nombre_representante'  => 'Rep. ' . $nombre,
                'telefono'              => '999' . str_pad(($i + 1) * 100, 7, '0', STR_PAD_LEFT),
                'municipio'             => $municipio,
                'categoria'             => $categoria,
                'descripcion'           => "Empresa líder en {$categoria} con sede en {$municipio}, Yucatán.",
                'activo'                => true,
                'aprobado'              => true,
                'num_empleados'         => rand(5, 80),
                'domicilio_en_yucatan'  => true,
                'rfc'                   => strtoupper(Str::random(4)) . '800101' . strtoupper(Str::random(3)),
            ]);

            $servicio = Servicio::create([
                'restaurantero_id'  => $restaurantero->id,
                'nombre'            => 'Mesa de Networking — ' . $nombre,
                'descripcion'       => 'Presentación de productos y servicios de ' . $categoria,
                'duracion_minutos'  => $eventoActivo->tiempo_entre_citas_minutos,
                'precio'            => 0,
                'activo'            => true,
            ]);

            $proveedores[] = ['user' => $userProv, 'restaurantero' => $restaurantero, 'servicio' => $servicio];
        }
        $this->command->info('  ✅ 10 proveedores creados.');

        // ── 15 Compradores ─────────────────────────────────────────────────────
        $nombresComp = [
            'Ana García López', 'Carlos Mendoza Pérez', 'María Ramírez Díaz',
            'José Hernández Torres', 'Laura Jiménez Castro', 'Miguel Ángel Ruiz Flores',
            'Patricia Morales Sánchez', 'Roberto Cruz Vega', 'Sandra Torres Reyes',
            'Fernando López Ortiz', 'Gabriela Martínez Luna', 'Alejandro Romero Gutiérrez',
            'Verónica Castillo Méndez', 'Eduardo Navarro Serrano', 'Claudia Vargas Espinoza',
        ];

        $compradores = [];
        foreach ($nombresComp as $j => $nombre) {
            $municipio = $this->municipios[$j % count($this->municipios)];
            $userComp  = User::create([
                'name'              => $nombre,
                'email'             => 'comprador' . ($j + 1) . '@demo.impulsate.test',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'active_role'       => 'comprador',
                'municipio'         => $municipio,
                'telefono'          => '999' . str_pad(($j + 200) * 10, 7, '0', STR_PAD_LEFT),
                'rfc'               => strtoupper(Str::random(4)) . '900101' . strtoupper(Str::random(3)),
                'nombre_empresa'    => 'Empresa ' . ($j + 1) . ' — ' . $municipio,
            ]);
            $userComp->assignRole('cliente');
            $compradores[] = $userComp;
        }
        $this->command->info('  ✅ 15 compradores creados.');

        // ── Registrar todos en evento activo ────────────────────────────────────
        foreach ($proveedores as $p) {
            DB::table('evento_usuario')->insert([
                'evento_id'  => $eventoActivo->id,
                'user_id'    => $p['user']->id,
                'tipo'       => 'proveedor',
                'estado'     => 'aprobado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach ($compradores as $c) {
            DB::table('evento_usuario')->insert([
                'evento_id'  => $eventoActivo->id,
                'user_id'    => $c->id,
                'tipo'       => 'comprador',
                'estado'     => 'aprobado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // ── Registrar algunos en evento pasado ─────────────────────────────────
        $provPasados = array_slice($proveedores, 0, 5);
        $compPasados = array_slice($compradores, 0, 8);

        foreach ($provPasados as $p) {
            DB::table('evento_usuario')->insert([
                'evento_id'  => $eventoPasado->id,
                'user_id'    => $p['user']->id,
                'tipo'       => 'proveedor',
                'estado'     => 'aprobado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        foreach ($compPasados as $c) {
            DB::table('evento_usuario')->insert([
                'evento_id'  => $eventoPasado->id,
                'user_id'    => $c->id,
                'tipo'       => 'comprador',
                'estado'     => 'aprobado',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('  ✅ Participantes registrados en eventos.');

        // ── Citas para evento activo ───────────────────────────────────────────
        $estados = ['pendiente', 'aceptada', 'rechazada', 'pendiente', 'aceptada'];
        $inicioEvento = $ahora->copy()->addDays(3)->setHour(9)->setMinute(0)->setSecond(0);
        $intervalo    = $eventoActivo->tiempo_entre_citas_minutos;
        $citasCreadas = 0;

        foreach ($compradores as $compIdx => $comprador) {
            $maxCitas = $eventoActivo->max_citas_por_comprador;

            for ($c = 0; $c < $maxCitas; $c++) {
                $provIdx  = ($compIdx * $maxCitas + $c) % count($proveedores);
                $prov     = $proveedores[$provIdx];
                $estado   = $estados[$c % count($estados)];

                $slotOffset = ($compIdx * $maxCitas + $c) * $intervalo;
                $inicio = $inicioEvento->copy()->addMinutes($slotOffset);
                $fin    = $inicio->copy()->addMinutes($intervalo);

                Cita::create([
                    'edicion_id'       => $eventoActivo->id,
                    'restaurantero_id' => $prov['restaurantero']->id,
                    'servicio_id'      => $prov['servicio']->id,
                    'cliente_id'       => $comprador->id,
                    'inicio'           => $inicio,
                    'fin'              => $fin,
                    'estado'           => $estado,
                    'mesa'             => rand(1, 10),
                ]);
                $citasCreadas++;
            }
        }

        // ── Citas para evento pasado ───────────────────────────────────────────
        $inicioEvPasado = $ahora->copy()->subMonths(6)->setHour(9)->setMinute(0)->setSecond(0);
        foreach ($compPasados as $ci => $comp) {
            $prov   = $provPasados[$ci % count($provPasados)];
            $inicio = $inicioEvPasado->copy()->addMinutes($ci * 30);

            Cita::create([
                'edicion_id'       => $eventoPasado->id,
                'restaurantero_id' => $prov['restaurantero']->id,
                'servicio_id'      => $prov['servicio']->id,
                'cliente_id'       => $comp->id,
                'inicio'           => $inicio,
                'fin'              => $inicio->copy()->addMinutes(30),
                'estado'           => 'aceptada',
                'mesa'             => rand(1, 5),
            ]);
        }

        $this->command->info("  ✅ {$citasCreadas} citas en evento activo, " . count($compPasados) . " en evento pasado.");
        $this->command->info('✅ DemoDataSeeder completado.');
        $this->command->info('   Admin: impulsate@iyemyucatan.com / password');
        $this->command->info('   Proveedores: proveedor1..10@demo.impulsate.test / password');
        $this->command->info('   Compradores: comprador1..15@demo.impulsate.test / password');
    }
}
