<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Edicion;
use App\Models\Horario;
use App\Models\Notificacion;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 1. Preservar admin y roles ─────────────────────────────────────
        $admin = User::whereHas('roles', fn($q) => $q->where('name', 'admin'))->first();
        if (!$admin) {
            $this->command->error('No se encontró usuario administrador. Aborting.');
            return;
        }

        // ── 2. Limpiar datos ───────────────────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('citas')->truncate();
        DB::table('notificaciones')->truncate();
        DB::table('page_visits')->truncate();
        DB::table('servicios')->truncate();
        DB::table('horarios')->truncate();
        DB::table('restauranteros')->truncate();
        // Borrar users excepto admin
        DB::table('model_has_roles')
            ->where('model_type', 'App\Models\User')
            ->where('model_id', '!=', $admin->id)
            ->delete();
        DB::table('users')->where('id', '!=', $admin->id)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->command->info('Datos limpios. Creando demo...');

        // ── 3. Roles ───────────────────────────────────────────────────────
        $rolCliente       = Role::firstOrCreate(['name' => 'cliente',       'guard_name' => 'web']);
        $rolRestaurantero = Role::firstOrCreate(['name' => 'restaurantero', 'guard_name' => 'web']);

        // ── 4. Edición activa ──────────────────────────────────────────────
        $edicion = Edicion::where('activa', true)->first() ?? Edicion::create([
            'nombre'              => 'Impulsate 2026 — Encuentro de Negocios',
            'sector'              => 'General',
            'descripcion'         => 'Edición especial de encuentros de networking empresarial para Yucatán.',
            'fecha_inicio'        => now()->subDays(5)->toDateString(),
            'fecha_inicio_agenda' => now()->subDays(5)->toDateString(),
            'fecha_fin_agenda'    => now()->addDays(30)->toDateString(),
            'fecha_corte'         => now()->addDays(30)->toDateString(),
            'activa'              => true,
        ]);

        // ── 5. 10 Compradores ──────────────────────────────────────────────
        $compradoresData = [
            ['name' => 'Alejandro Cetina Mézquita',    'email' => 'alex.cetina@mail.com',   'telefono' => '999-201-0001'],
            ['name' => 'Valentina Dzul Canul',          'email' => 'vdzul@correo.mx',        'telefono' => '999-201-0002'],
            ['name' => 'Ricardo Pérez Góngora',         'email' => 'rperez.gon@email.com',   'telefono' => '999-201-0003'],
            ['name' => 'Claudia Balam Ek',              'email' => 'cbalam@business.mx',     'telefono' => '999-201-0004'],
            ['name' => 'Fernando Tun Cocom',            'email' => 'ftun.cocom@gmail.com',   'telefono' => '999-201-0005'],
            ['name' => 'Mariana Rejón Poot',            'email' => 'mrejon@empresa.com',     'telefono' => '999-201-0006'],
            ['name' => 'Jorge Luis Caamal Canche',      'email' => 'jcaamal@negocio.mx',     'telefono' => '999-201-0007'],
            ['name' => 'Sofía Medina Aké',              'email' => 'smedina.ake@mail.com',   'telefono' => '999-201-0008'],
            ['name' => 'Eduardo Novelo Chuc',           'email' => 'enovelo@pyme.mx',        'telefono' => '999-201-0009'],
            ['name' => 'Patricia Esquivel Uicab',       'email' => 'pesquivel@empresa.com',  'telefono' => '999-201-0010'],
        ];

        $compradores = [];
        foreach ($compradoresData as $d) {
            $u = User::create([
                'name'            => $d['name'],
                'email'           => $d['email'],
                'password'        => Hash::make('password'),
                'telefono'        => $d['telefono'],
                'active_role'     => 'comprador',
                'perfil_completo' => true,
            ]);
            // Bypass fillable para email_verified_at
            $u->forceFill(['email_verified_at' => now()])->save();
            $u->assignRole($rolCliente);
            $compradores[] = $u;
        }
        $this->command->info('  10 compradores creados.');

        // ── 6. 20 Proveedores ──────────────────────────────────────────────
        $proveedoresData = [
            ['name' => 'Carlos Montejo Ávila',     'email' => 'carlos@tecyucatan.mx',      'empresa' => 'Tech Solutions Yucatán',        'categoria' => 'Tecnología',            'municipio' => 'Mérida',     'desc' => 'Desarrollo de software, apps móviles y transformación digital para PyMEs yucatecas.'],
            ['name' => 'María Uc Dzul',             'email' => 'maria@ecofrutas.mx',        'empresa' => 'EcoFrutas del Sureste',          'categoria' => 'Alimentos y Bebidas',   'municipio' => 'Mérida',     'desc' => 'Distribución de frutas y verduras orgánicas de la región, frescas y de temporada.'],
            ['name' => 'Jorge Dzib Canul',          'email' => 'jorge@legalyuc.mx',         'empresa' => 'Consultora Legal Yucatán',       'categoria' => 'Servicios Profesionales','municipio' => 'Mérida',    'desc' => 'Asesoría jurídica empresarial, contratos, trámites y protección de marca en México.'],
            ['name' => 'Rosa Canul Pech',           'email' => 'rosa@wellnessmerida.mx',    'empresa' => 'Wellness Mérida',                'categoria' => 'Salud y Bienestar',     'municipio' => 'Mérida',     'desc' => 'Programas de bienestar corporativo, salud ocupacional y talleres de mindfulness.'],
            ['name' => 'Luis Cárdenas Peón',        'email' => 'luis@textilespenisula.mx',  'empresa' => 'Textiles Península',             'categoria' => 'Manufactura',           'municipio' => 'Mérida',     'desc' => 'Fabricación de uniformes corporativos, ropa de trabajo y textiles industriales.'],
            ['name' => 'Ana Cocom Balam',           'email' => 'ana@tourhaciendas.mx',      'empresa' => 'Tour Haciendas Yucatán',         'categoria' => 'Turismo',               'municipio' => 'Mérida',     'desc' => 'Servicios de turismo corporativo, team building en haciendas y eventos empresariales.'],
            ['name' => 'Pedro Cervantes Chan',      'email' => 'pedro@buildyucatan.mx',     'empresa' => 'BuildYucatán Construcción',      'categoria' => 'Construcción',          'municipio' => 'Mérida',     'desc' => 'Remodelación, adecuación de locales comerciales y oficinas corporativas en Yucatán.'],
            ['name' => 'Mario Tamayo Cetz',         'email' => 'mario@academiadi.mx',       'empresa' => 'Academia Digital Impulsate',     'categoria' => 'Educación',             'municipio' => 'Mérida',     'desc' => 'Capacitación en marketing digital, comercio electrónico y habilidades gerenciales.'],
            ['name' => 'Sandra Mena Góngora',       'email' => 'sandra@freshmarket.mx',     'empresa' => 'FreshMarket Yucatán',            'categoria' => 'Comercio',              'municipio' => 'Mérida',     'desc' => 'Distribución mayorista de productos de consumo masivo para tiendas y restaurantes.'],
            ['name' => 'Roberto Esquivel May',      'email' => 'roberto@softdevmerida.mx',  'empresa' => 'SoftDev Mérida',                 'categoria' => 'Tecnología',            'municipio' => 'Mérida',     'desc' => 'Sistemas ERP, facturación electrónica y automatización de procesos empresariales.'],
            ['name' => 'Patricia Cen Tuz',          'email' => 'patricia@bioherbs.mx',      'empresa' => 'Bioherbs del Sureste',           'categoria' => 'Salud y Bienestar',     'municipio' => 'Mérida',     'desc' => 'Suplementos naturales, herbolaria medicinal y productos de bienestar con plantas de Yucatán.'],
            ['name' => 'Felipe Herrera Novelo',     'email' => 'felipe@contaexpress.mx',    'empresa' => 'Contaduría Express MX',          'categoria' => 'Servicios Profesionales','municipio' => 'Mérida',    'desc' => 'Contabilidad, declaraciones fiscales, nómina y auditoría para empresas de todos los tamaños.'],
            ['name' => 'Diana Novelo Ucan',         'email' => 'diana@cafedzibal.mx',       'empresa' => 'Café Artesanal Dzitbalché',      'categoria' => 'Alimentos y Bebidas',   'municipio' => 'Dzitbalché', 'desc' => 'Café de especialidad tostado localmente, infusiones y bebidas artesanales para cafeterías.'],
            ['name' => 'Rodrigo Cámara Rejón',      'email' => 'rodrigo@empaquesgreen.mx',  'empresa' => 'Empaques Sustentables Green',    'categoria' => 'Manufactura',           'municipio' => 'Mérida',     'desc' => 'Empaques biodegradables, envases de papel kraft y soluciones de packaging ecológico.'],
            ['name' => 'Sofía Bastarrachea Ic',     'email' => 'sofia@distrimaya.mx',       'empresa' => 'Distribuidora Maya',             'categoria' => 'Comercio',              'municipio' => 'Mérida',     'desc' => 'Distribución de insumos de oficina, papelería y artículos de limpieza al mayoreo.'],
            ['name' => 'Antonio Chan Ku',           'email' => 'antonio@innovti.mx',        'empresa' => 'Innovación TI Península',        'categoria' => 'Tecnología',            'municipio' => 'Mérida',     'desc' => 'Ciberseguridad, redes corporativas, soporte técnico y cloud computing para empresas.'],
            ['name' => 'Lucía Poot Dzib',           'email' => 'lucia@nutricionmerida.mx',  'empresa' => 'Clínica Nutrición Empresarial',  'categoria' => 'Salud y Bienestar',     'municipio' => 'Mérida',     'desc' => 'Programas de nutrición corporativa, comedores industriales saludables y asesoría dietética.'],
            ['name' => 'Manuel Tec Canche',         'email' => 'manuel@asesoresfis.mx',     'empresa' => 'Asesores Fiscales Yucatán',      'categoria' => 'Servicios Profesionales','municipio' => 'Mérida',    'desc' => 'Planeación fiscal, ISR, IVA, IMSS y trámites ante el SAT para personas físicas y morales.'],
            ['name' => 'Gabriela Covarrubias Ic',   'email' => 'gabriela@saboresyuc.mx',    'empresa' => 'Restaurantes & Sabores Yucatán', 'categoria' => 'Alimentos y Bebidas',   'municipio' => 'Mérida',     'desc' => 'Consultoría gastronómica, recetas certificadas y capacitación para cocinas de autor.'],
            ['name' => 'Héctor Rejón Novelo',       'email' => 'hector@henequen.mx',        'empresa' => 'Henequén Products S.A.',         'categoria' => 'Alimentos y Bebidas',   'municipio' => 'Valladolid', 'desc' => 'Productos derivados del henequén: bebidas, suplementos y artesanías para exportación.'],
        ];

        $proveedoresList = [];
        foreach ($proveedoresData as $i => $d) {
            $u = User::create([
                'name'             => $d['name'],
                'email'            => $d['email'],
                'password'         => Hash::make('password'),
                'active_role'      => 'proveedor',
                'perfil_completo'  => true,
            ]);
            $u->forceFill(['email_verified_at' => now()])->save();
            $u->assignRole($rolRestaurantero);

            $restaurantero = Restaurantero::create([
                'edicion_id'         => $edicion->id,
                'user_id'            => $u->id,
                'nombre_restaurante' => $d['empresa'],
                'descripcion'        => $d['desc'],
                'telefono'           => '999-3' . str_pad($i + 1, 2, '0', STR_PAD_LEFT) . '-00' . ($i + 10),
                'municipio'          => $d['municipio'],
                'categoria'          => $d['categoria'],
                'activo'             => true,
                'aprobado'           => true,
            ]);

            $servicio = Servicio::create([
                'restaurantero_id' => $restaurantero->id,
                'nombre'           => 'Mesa de Networking',
                'duracion_minutos' => 30,
                'precio'           => 0,
                'activo'           => true,
            ]);

            for ($dia = 1; $dia <= 5; $dia++) {
                Horario::create([
                    'restaurantero_id' => $restaurantero->id,
                    'dia_semana'       => $dia,
                    'hora_inicio'      => '09:00:00',
                    'hora_fin'         => '16:00:00',
                    'activo'           => true,
                ]);
            }

            $proveedoresList[] = ['restaurantero' => $restaurantero, 'servicio' => $servicio];
        }
        $this->command->info('  20 proveedores creados.');

        // ── 7. Citas para HOY ──────────────────────────────────────────────
        // Horarios 9:00–15:30 en slots de 30 min (14 slots)
        // Cada slot: proveedor diferente (en orden), comprador aleatorio
        $slots = [
            [9,  0,  'completada'],
            [9,  30, 'completada'],
            [10, 0,  'completada'],
            [10, 30, 'completada'],
            [11, 0,  'confirmada'],
            [11, 30, 'confirmada'],
            [12, 0,  'confirmada'],
            [12, 30, 'confirmada'],
            [13, 0,  'confirmada'],
            [13, 30, 'confirmada'],
            [14, 0,  'confirmada'],
            [14, 30, 'confirmada'],
            [15, 0,  'confirmada'],
            [15, 30, 'confirmada'],
        ];

        $hoy = now()->setTimezone('America/Mexico_City')->toDateString();
        $citasCreadas = 0;
        foreach ($slots as $idx => [$h, $m, $estado]) {
            $proveedor = $proveedoresList[$idx % count($proveedoresList)];
            $comprador = $compradores[array_rand($compradores)];

            $inicio = Carbon::parse("{$hoy} {$h}:{$m}:00", 'America/Mexico_City');
            $fin    = $inicio->copy()->addMinutes(30);

            Cita::create([
                'edicion_id'       => $edicion->id,
                'restaurantero_id' => $proveedor['restaurantero']->id,
                'servicio_id'      => $proveedor['servicio']->id,
                'cliente_id'       => $comprador->id,
                'inicio'           => $inicio,
                'fin'              => $fin,
                'estado'           => $estado,
                'notas'            => null,
            ]);
            $citasCreadas++;
        }

        // Citas adicionales para los próximos 5 días laborales (pendientes/confirmadas)
        $diasLaborales = collect();
        $d = Carbon::now('America/Mexico_City')->addDay();
        while ($diasLaborales->count() < 5) {
            if (!in_array($d->dayOfWeek, [0, 6])) {
                $diasLaborales->push($d->copy());
            }
            $d->addDay();
        }

        foreach ($diasLaborales as $dia) {
            $diaStr = $dia->toDateString();
            $slotsManana = [[9,0],[10,0],[11,0],[12,0],[13,0],[14,0]];
            foreach ($slotsManana as $si => [$h, $m]) {
                $prov = $proveedoresList[($si * 3 + $diasLaborales->search($dia)) % count($proveedoresList)];
                $comp = $compradores[array_rand($compradores)];
                $inicio = Carbon::parse("{$diaStr} {$h}:{$m}:00", 'America/Mexico_City');
                Cita::create([
                    'edicion_id'       => $edicion->id,
                    'restaurantero_id' => $prov['restaurantero']->id,
                    'servicio_id'      => $prov['servicio']->id,
                    'cliente_id'       => $comp->id,
                    'inicio'           => $inicio,
                    'fin'              => $inicio->copy()->addMinutes(30),
                    'estado'           => $si < 2 ? 'pendiente' : 'confirmada',
                    'notas'            => null,
                ]);
                $citasCreadas++;
            }
        }

        $this->command->info("  {$citasCreadas} citas creadas (hoy + próximos 5 días).");
        $this->command->info('Demo data creado correctamente.');
        $this->command->info('  Admin:     admin@citas.com / password');
        $this->command->info('  Compradores: cliente.xxx@mail.com / password');
        $this->command->info('  Proveedores: xxx@empresa.mx / password');
    }
}
