<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Horario;
use App\Models\Restaurantero;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole         = Role::firstOrCreate(['name' => 'admin']);
        $restauranteroRole = Role::firstOrCreate(['name' => 'restaurantero']);
        $clienteRole       = Role::firstOrCreate(['name' => 'cliente']);

        // Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@citas.com'],
            ['name' => 'Administrador', 'password' => Hash::make('password')]
        );
        $admin->assignRole($adminRole);

        // 15 Proveedores de insumos y servicios para restauranteros — Mérida, Yucatán
        $restauranterosData = [
            [
                'name'               => 'Carlos Montejo Ávila',
                'email'              => 'carlos@carniceriamontejo.com',
                'nombre_restaurante' => 'Carnicería y Distribuidora Montejo',
                'descripcion'        => 'Distribución mayorista de cortes finos, carnes de res, cerdo y embutidos artesanales para restaurantes y hoteles. Más de 20 años garantizando frescura y calidad en cada entrega directa a tu cocina.',
                'telefono'           => '999-123-4501',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_01.jpg',
            ],
            [
                'name'               => 'María Uc Dzul',
                'email'              => 'maria@mariscosuc.com',
                'nombre_restaurante' => 'Mariscos del Golfo Uc',
                'descripcion'        => 'Importadores directos de mariscos frescos y congelados del Golfo de México. Camarón, pulpo, calamar, jaiba y filete de pescado entregados diariamente. Certificados por SENASICA para máxima inocuidad alimentaria.',
                'telefono'           => '999-123-4502',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_02.jpg',
            ],
            [
                'name'               => 'Jorge Dzib Canul',
                'email'              => 'jorge@lacterosdzib.com',
                'nombre_restaurante' => 'Lácteos y Quesos Artesanales Dzib',
                'descripcion'        => 'Producción y distribución de quesos artesanales yucatecos, cremas, mantequillas y yogures. Elaborados con leche de libre pastoreo, ideales para restaurantes que buscan sabores auténticos y proveedores locales de confianza.',
                'telefono'           => '999-123-4503',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_03.jpg',
            ],
            [
                'name'               => 'Rosa Canul Pech',
                'email'              => 'rosa@frutascanul.com',
                'nombre_restaurante' => 'Frutas y Verduras Mayoristas Canul',
                'descripcion'        => 'Central de abastos con más de 250 variedades de frutas y verduras frescas de temporada. Entregas diarias a domicilio para restaurantes, hoteles y cocinas industriales. Precios de mayoreo y calidad garantizada en cada pedido.',
                'telefono'           => '999-123-4504',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_04.jpg',
            ],
            [
                'name'               => 'Luis Cárdenas Peón',
                'email'              => 'luis@vinosmayab.com',
                'nombre_restaurante' => 'Vinos y Licores del Mayab Cárdenas',
                'descripcion'        => 'Importadora y distribuidora de vinos nacionales e internacionales, cervezas artesanales y destilados premium. Asesoría de carta de vinos incluida. Representantes exclusivos de bodegas españolas, chilenas e italianas en Yucatán.',
                'telefono'           => '999-123-4505',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_05.jpg',
            ],
            [
                'name'               => 'Ana Cocom Balam',
                'email'              => 'ana@panificadoracocom.com',
                'nombre_restaurante' => 'Panificadora Industrial Cocom',
                'descripcion'        => 'Producción de pan artesanal e industrial: baguettes, panes de especialidad, bollería y pasteles para restaurantes, hoteles y servicios de catering. Producción nocturna para garantizar pan fresco en tu servicio del día.',
                'telefono'           => '999-123-4506',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_06.jpg',
            ],
            [
                'name'               => 'Pedro Cervantes Chan',
                'email'              => 'pedro@higieneycervantes.com',
                'nombre_restaurante' => 'Higiene y Sanidad Cervantes',
                'descripcion'        => 'Distribuidora de productos de limpieza profesional, desinfectantes HACCP, guantes, cubre bocas y artículos de higiene para cocinas certificadas. Cumplimos con todas las normas NOM para establecimientos de alimentos y bebidas.',
                'telefono'           => '999-123-4507',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_07.jpg',
            ],
            [
                'name'               => 'Mario Tamayo Cetz',
                'email'              => 'mario@equipotamayo.com',
                'nombre_restaurante' => 'Equipamiento Gastronómico Tamayo',
                'descripcion'        => 'Venta, instalación y mantenimiento de equipo de cocina profesional: hornos de convección, parrillas, freidoras industriales, cámaras de refrigeración y mobiliario inoxidable. Financiamiento disponible y servicio técnico 24/7 en Yucatán.',
                'telefono'           => '999-123-4508',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_08.jpg',
            ],
            [
                'name'               => 'Sandra Mena Góngora',
                'email'              => 'sandra@empaques mena.com',
                'nombre_restaurante' => 'Empaques y Desechables Premium Mena',
                'descripcion'        => 'Proveedor líder de envases biodegradables, bolsas kraft, contenedores de cartón, servilletas personalizadas y popotes de papel. Soluciones ecológicas para delivery y para llevar. Diseño de empaque con tu marca incluido en pedidos mayores.',
                'telefono'           => '999-123-4509',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_09.jpg',
            ],
            [
                'name'               => 'Roberto Esquivel May',
                'email'              => 'roberto@aceitesesquivel.com',
                'nombre_restaurante' => 'Aceites y Grasas Industriales Esquivel',
                'descripcion'        => 'Distribuidora de aceites vegetales de alta estabilidad térmica, manteca vegetal y de cerdo, mantequilla y grasas alimentarias de calidad industrial. Presentaciones desde 5 hasta 200 litros para cualquier volumen de operación.',
                'telefono'           => '999-123-4510',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_10.jpg',
            ],
            [
                'name'               => 'Patricia Cen Tuz',
                'email'              => 'patricia@especiascen.com',
                'nombre_restaurante' => 'Especias y Recados Yucatecos Cen',
                'descripcion'        => 'Productora artesanal y distribuidora de condimentos tradicionales yucatecos: achiote natural, recado negro, sazón de naranja, orégano seco y mezclas exclusivas para cochinita, relleno negro y poc chuc. Sin conservadores artificiales.',
                'telefono'           => '999-123-4511',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_11.jpg',
            ],
            [
                'name'               => 'Felipe Herrera Novelo',
                'email'              => 'felipe@cafeherrera.com',
                'nombre_restaurante' => 'Café y Bebidas Herrera',
                'descripcion'        => 'Tostadora de café de especialidad con origen en Chiapas y Veracruz. Distribuidora de máquinas espresso, molinos, té premium, jarabes artesanales y chocolates para cafeterías y restaurantes. Capacitación en barismo incluida con cada contrato.',
                'telefono'           => '999-123-4512',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_12.jpg',
            ],
            [
                'name'               => 'Diana Novelo Ucan',
                'email'              => 'diana@cristalerianovelo.com',
                'nombre_restaurante' => 'Cristalería y Menaje Novelo',
                'descripcion'        => 'Distribuidora de vajilla, cristalería, cubiertos y accesorios de mesa para hoteles, restaurantes y eventos. Representante autorizada de marcas europeas como Arcoroc y Steelite. Servicio de reposición rápida y cotizaciones sin costo.',
                'telefono'           => '999-123-4513',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_13.jpg',
            ],
            [
                'name'               => 'Rodrigo Cámara Rejón',
                'email'              => 'rodrigo@refrigeracioncamara.com',
                'nombre_restaurante' => 'Refrigeración y Clima Cámara',
                'descripcion'        => 'Instalación y mantenimiento preventivo y correctivo de cámaras de refrigeración, cuartos fríos, congeladores industriales y sistemas de aire acondicionado para cocinas. Respuesta de emergencia en menos de 2 horas en toda el área metropolitana de Mérida.',
                'telefono'           => '999-123-4514',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_14.jpg',
            ],
            [
                'name'               => 'Sofía Bastarrachea Ic',
                'email'              => 'sofia@marketingbastarrachea.com',
                'nombre_restaurante' => 'Diseño y Marketing Gastronómico Bastarrachea',
                'descripcion'        => 'Agencia especializada en branding para la industria restaurantera: diseño de menús físicos y digitales, identidad visual, fotografía profesional de alimentos, gestión de redes sociales y estrategias de publicidad digital para aumentar tus comensales.',
                'telefono'           => '999-123-4515',
                'municipio'          => 'Mérida',
                'logo_path'          => 'logos/proveedor_15.jpg',
            ],
        ];

        $restauranteroModels = [];
        foreach ($restauranterosData as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make('password')]
            );
            $user->assignRole($restauranteroRole);

            $restaurantero = Restaurantero::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'nombre_restaurante' => $data['nombre_restaurante'],
                    'descripcion'        => $data['descripcion'],
                    'telefono'           => $data['telefono'],
                    'municipio'          => $data['municipio'],
                    'logo_path'          => $data['logo_path'],
                    'activo'             => true,
                ]
            );

            // Único servicio: Mesa de Networking 30 min
            Servicio::firstOrCreate(
                ['restaurantero_id' => $restaurantero->id, 'nombre' => 'Mesa de Networking'],
                ['duracion_minutos' => 30, 'precio' => 0, 'activo' => true]
            );

            // Horario Lun–Vie 9:00–16:00
            for ($dia = 1; $dia <= 5; $dia++) {
                Horario::firstOrCreate(
                    ['restaurantero_id' => $restaurantero->id, 'dia_semana' => $dia],
                    ['hora_inicio' => '09:00:00', 'hora_fin' => '16:00:00', 'activo' => true]
                );
            }

            $restauranteroModels[] = $restaurantero;
        }

        // 5 clientes
        $clientes = [];
        for ($i = 1; $i <= 5; $i++) {
            $cliente = User::firstOrCreate(
                ['email' => "cliente{$i}@example.com"],
                ['name' => "Cliente Número {$i}", 'password' => Hash::make('password')]
            );
            $cliente->assignRole($clienteRole);
            $clientes[] = $cliente;
        }

        // Citas de ejemplo — solo para los primeros 5 restauranteros
        $estados = ['pendiente', 'confirmada', 'completada'];
        foreach (array_slice($restauranteroModels, 0, 5) as $restaurantero) {
            $servicio = $restaurantero->servicios->first();
            if (!$servicio) continue;
            for ($i = 0; $i < 6; $i++) {
                $inicio = now()->addDays(rand(-5, 15))
                    ->setHour(rand(9, 15))
                    ->setMinute($i % 2 === 0 ? 0 : 30)
                    ->setSecond(0);
                $fin = (clone $inicio)->addMinutes(30);
                Cita::create([
                    'restaurantero_id' => $restaurantero->id,
                    'servicio_id'      => $servicio->id,
                    'cliente_id'       => $clientes[array_rand($clientes)]->id,
                    'inicio'           => $inicio,
                    'fin'              => $fin,
                    'estado'           => $estados[array_rand($estados)],
                    'notas'            => null,
                ]);
            }
        }
    }
}
