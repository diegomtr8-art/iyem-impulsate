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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoDataSeeder extends Seeder
{
    // ── Datos de empresa por categoría ─────────────────────────────────────────
    private array $empresas = [
        [
            'nombre'    => 'Sabores Yucatecos del Sur',
            'municipio' => 'Mérida',
            'categoria' => 'Alimentos y Bebidas',
            'desc'      => 'Empresa líder en producción y exportación de alimentos tradicionales yucatecos. Especializados en cochinita pibil, chorizos regionales y derivados del cerdo con calidad certificada.',
            'seed_logo' => 'food-business-yucatan',
            'productos'  => [
                ['nombre' => 'Cochinita Pibil Premium', 'descripcion' => 'Cochinita marinada en achiote yucateco, lista para calentar. Presentación de 1 y 5 kg para distribución mayorista y restaurantes.', 'seed' => 'cochinita-pibil-premium'],
                ['nombre' => 'Chorizo Yucateco Artesanal', 'descripcion' => 'Chorizo elaborado con técnicas tradicionales mayas, ideal para exportación y distribución en cadenas comerciales.', 'seed' => 'chorizo-yucateco'],
                ['nombre' => 'Miel de Abeja Melipona', 'descripcion' => 'Miel pura de abeja meliponini, sin preservantes. Producto de exportación con certificación orgánica internacional.', 'seed' => 'miel-melipona-yucatan'],
            ],
        ],
        [
            'nombre'    => 'Tecnología Peninsular S.A.',
            'municipio' => 'Mérida',
            'categoria' => 'Tecnología',
            'desc'      => 'Desarrollo de software empresarial y soluciones digitales para PyMEs del sureste. Especialistas en ERP, apps móviles y transformación digital para empresas yucatecas.',
            'seed_logo' => 'tech-office-startup',
            'productos'  => [
                ['nombre' => 'ERP PyMEs Yucatán', 'descripcion' => 'Sistema de gestión empresarial adaptado a la normativa mexicana. Módulos de inventario, facturación electrónica, nómina y contabilidad.', 'seed' => 'erp-software-dashboard'],
                ['nombre' => 'App Móvil a Medida', 'descripcion' => 'Desarrollo de aplicaciones iOS/Android para optimizar procesos comerciales. Entrega en 60 días con soporte técnico incluido.', 'seed' => 'mobile-app-development'],
                ['nombre' => 'Consultoría TI', 'descripcion' => 'Diagnóstico y hoja de ruta de transformación digital. Incluye análisis de infraestructura, seguridad informática y migración a la nube.', 'seed' => 'it-consulting-meeting'],
            ],
        ],
        [
            'nombre'    => 'Mérida Health & Wellness',
            'municipio' => 'Mérida',
            'categoria' => 'Salud y Bienestar',
            'desc'      => 'Distribuidora de equipos médicos y suplementos naturales para clínicas, consultorios y cadenas de farmacias en todo Yucatán. Proveedores certificados ante COFEPRIS.',
            'seed_logo' => 'health-wellness-clinic',
            'productos'  => [
                ['nombre' => 'Suplementos Naturales IYEM', 'descripcion' => 'Línea de suplementos con extractos de plantas medicinales de la Península. Certificación sanitaria vigente. Ideal para farmacias y clínicas.', 'seed' => 'natural-supplements-health'],
                ['nombre' => 'Equipo de Diagnóstico', 'descripcion' => 'Tensiómetros, glucómetros y oxímetros de pulso de marcas certificadas. Precios competitivos para distribución mayorista en sector salud.', 'seed' => 'medical-equipment-diagnostic'],
                ['nombre' => 'Consultoría Nutricional', 'descripcion' => 'Asesoría en nutrición clínica y deportiva para empresas, clínicas y cadenas de bienestar. Programas personalizados con seguimiento mensual.', 'seed' => 'nutrition-consulting-wellness'],
            ],
        ],
        [
            'nombre'    => 'Consultores del Mayab',
            'municipio' => 'Valladolid',
            'categoria' => 'Servicios Profesionales',
            'desc'      => 'Firma de consultoría empresarial y fiscal con 15 años de experiencia en el sureste. Atendemos PyMEs, despachos y empresas familiares en planeación estratégica y cumplimiento fiscal.',
            'seed_logo' => 'business-consulting-firm',
            'productos'  => [
                ['nombre' => 'Auditoría Fiscal Preventiva', 'descripcion' => 'Revisión integral de obligaciones fiscales, declaraciones y comprobantes. Reducimos riesgos ante el SAT para su empresa.', 'seed' => 'accounting-audit-office'],
                ['nombre' => 'Consultoría Empresarial', 'descripcion' => 'Diagnóstico organizacional, plan estratégico y acompañamiento en la implementación. Metodología probada en más de 200 empresas yucatecas.', 'seed' => 'business-strategy-consulting'],
                ['nombre' => 'Capacitación Corporativa', 'descripcion' => 'Talleres in-company de liderazgo, finanzas para no financieros y ventas consultivas. Grupos de 5 a 50 personas.', 'seed' => 'corporate-training-workshop'],
            ],
        ],
        [
            'nombre'    => 'Manufacturas del Sureste',
            'municipio' => 'Umán',
            'categoria' => 'Manufactura',
            'desc'      => 'Planta de manufactura especializada en metalmecánica y ensamblaje de precisión. Fabricamos piezas industriales bajo especificación para sectores automotriz, maquilador y agroindustrial.',
            'seed_logo' => 'manufacturing-factory-industrial',
            'productos'  => [
                ['nombre' => 'Piezas CNC a Especificación', 'descripcion' => 'Mecanizado CNC de alta precisión en aluminio, acero y latón. Tolerancias de ±0.01 mm. Entregas desde 5 días hábiles.', 'seed' => 'cnc-machining-parts'],
                ['nombre' => 'Ensamblaje Industrial', 'descripcion' => 'Línea de ensamblaje subcontratada para componentes mecánicos, electrónicos y electromecánicos. Capacidad de 500 a 10,000 piezas/mes.', 'seed' => 'industrial-assembly-line'],
                ['nombre' => 'Control de Calidad ISO', 'descripcion' => 'Inspección de calidad con certificación ISO 9001. Reportes de medición tridimensional y liberación de lotes para exportación.', 'seed' => 'quality-control-iso'],
            ],
        ],
        [
            'nombre'    => 'Comercializadora Itzamná',
            'municipio' => 'Progreso',
            'categoria' => 'Comercio',
            'desc'      => 'Distribuidora mayorista con cobertura en todo el estado de Yucatán. Importación directa de Asia y Europa. Más de 3,000 SKU en catálogo activo para retail y tiendas departamentales.',
            'seed_logo' => 'wholesale-commerce-warehouse',
            'productos'  => [
                ['nombre' => 'Distribución Mayorista', 'descripcion' => 'Representación y distribución exclusiva de marcas internacionales en Yucatán. Crédito a 30/60 días para clientes establecidos.', 'seed' => 'wholesale-distribution-store'],
                ['nombre' => 'Importación Directa', 'descripcion' => 'Gestión de importación desde Asia y Europa: logística, aduana, almacén y last-mile. Reducimos hasta 30% en costos frente al intermediario.', 'seed' => 'import-commerce-shipping'],
                ['nombre' => 'Plataforma E-commerce B2B', 'descripcion' => 'Catálogo digital con pedido en línea, seguimiento de envíos y facturación automática para compradores corporativos.', 'seed' => 'ecommerce-b2b-platform'],
            ],
        ],
        [
            'nombre'    => 'Constructora Kukulcán',
            'municipio' => 'Mérida',
            'categoria' => 'Construcción',
            'desc'      => 'Empresa constructora con especialidad en proyectos residenciales, comerciales e industriales en Mérida y zona metropolitana. Más de 50 proyectos entregados en los últimos 5 años.',
            'seed_logo' => 'construction-building-architect',
            'productos'  => [
                ['nombre' => 'Proyectos Residenciales', 'descripcion' => 'Construcción de casas y condominios en Mérida Norte y zona poniente. Entrega llave en mano con garantía estructural de 10 años.', 'seed' => 'residential-construction-home'],
                ['nombre' => 'Remodelación Integral', 'descripcion' => 'Remodelación de oficinas, locales comerciales y espacios industriales. Coordinamos obra civil, eléctrica, plomería y acabados.', 'seed' => 'office-renovation-remodeling'],
                ['nombre' => 'Supervisión de Obra', 'descripcion' => 'Servicio de supervisión técnica independiente para proyectos en ejecución. Reportes semanales y control de avance contra presupuesto.', 'seed' => 'construction-supervision-engineering'],
            ],
        ],
        [
            'nombre'    => 'EduYucatán Centro de Formación',
            'municipio' => 'Mérida',
            'categoria' => 'Educación',
            'desc'      => 'Centro de formación empresarial y capacitación profesional en línea y presencial. Ofrecemos diplomados, certificaciones y talleres para equipos de trabajo de empresas yucatecas.',
            'seed_logo' => 'education-learning-center',
            'productos'  => [
                ['nombre' => 'Diplomado en Liderazgo', 'descripcion' => 'Programa de 80 horas para líderes y mandos medios. Modalidad híbrida. Incluye certificación reconocida ante la SEP.', 'seed' => 'leadership-diploma-education'],
                ['nombre' => 'Cursos en Línea', 'descripcion' => 'Catálogo de +200 cursos en línea: finanzas, ventas, marketing digital, RRHH y más. Licencias corporativas desde 10 usuarios.', 'seed' => 'online-courses-elearning'],
                ['nombre' => 'Talleres In-company', 'descripcion' => 'Talleres prácticos impartidos en las instalaciones de su empresa. Adaptamos el contenido a su industria y objetivos de negocio.', 'seed' => 'corporate-workshop-training'],
            ],
        ],
        [
            'nombre'    => 'Xibalbá Turismo y Aventura',
            'municipio' => 'Valladolid',
            'categoria' => 'Turismo',
            'desc'      => 'Operadora turística especializada en turismo cultural y de aventura en la Península de Yucatán. Diseñamos paquetes para grupos corporativos, agencias receptoras y turismo de incentivo.',
            'seed_logo' => 'tourism-adventure-yucatan',
            'productos'  => [
                ['nombre' => 'Tours Arqueológicos', 'descripcion' => 'Recorridos guiados a Chichén Itzá, Uxmal y Tulum con arqueólogos certificados. Opciones en español, inglés y francés para grupos de 10 a 50 personas.', 'seed' => 'archaeological-tour-chichen'],
                ['nombre' => 'Paquetes Corporativos', 'descripcion' => 'Experiencias de team building y turismo de incentivo en cenotes, haciendas y reservas naturales. Cotización personalizada para grupos empresariales.', 'seed' => 'corporate-travel-incentive'],
                ['nombre' => 'Experiencias Culturales', 'descripcion' => 'Vivencias auténticas en comunidades mayas: gastronomía, artesanías y rituales. Ideal para turismo internacional de alto valor.', 'seed' => 'maya-cultural-experience'],
            ],
        ],
        [
            'nombre'    => 'Logística Express Yucatán',
            'municipio' => 'Mérida',
            'categoria' => 'Logística',
            'desc'      => 'Empresa de logística y transporte con flota propia de 45 unidades. Cobertura en toda la Península. Especialistas en cadena de frío, carga general y distribución de último kilómetro.',
            'seed_logo' => 'logistics-transport-fleet',
            'productos'  => [
                ['nombre' => 'Transporte Terrestre', 'descripcion' => 'Flota de camiones de 3.5 a 10 toneladas para carga general y refrigerada. Rastreo GPS en tiempo real. Tarifas por rutas establecidas o km recorrido.', 'seed' => 'truck-transport-logistics'],
                ['nombre' => 'Almacenaje Especializado', 'descripcion' => 'Bodega de 4,000 m² en zona industrial de Mérida. Cámaras frías, rack selectivo y gestión de inventario. Servicio de fulfillment disponible.', 'seed' => 'warehouse-storage-logistics'],
                ['nombre' => 'Distribución Regional', 'descripcion' => 'Reparto capilar en Mérida, Progreso, Valladolid, Cancún y Chetumal. Entregas D+1 garantizadas para clientes corporativos con contrato.', 'seed' => 'regional-distribution-delivery'],
            ],
        ],
    ];

    // ── Semillas de imágenes para eventos ─────────────────────────────────────
    private array $eventosSeeds = [
        'impulsate-alimentos-2026',
        'impulsate-tecnologia-2025',
        'impulsate-salud-bienestar-2026',
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
            'descripcion'                    => 'Encuentro de negocios para conectar proveedores del sector alimentario con compradores estratégicos de Yucatán. Agenda citas de 30 min cara a cara.',
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
            'descripcion'                    => 'Evento pasado del sector tecnológico. Más de 120 citas agendadas entre proveedores de software, hardware y servicios TI de Yucatán.',
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
            'descripcion'                    => 'Próximo encuentro de negocios para el sector salud. Conectaremos proveedores de suplementos, equipos médicos y consultoría con compradores estratégicos.',
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

        // Descargar imágenes para los eventos
        $this->command->info('  Descargando imágenes de eventos...');
        $todosEventos = Evento::orderBy('id')->get();
        foreach ($todosEventos as $idx => $ev) {
            $seed = $this->eventosSeeds[$idx] ?? 'business-event-' . ($idx + 1);
            $imgPath = $this->descargarImagen($seed, 'eventos', 1200, 500);
            if ($imgPath) {
                $ev->update(['imagen' => $imgPath]);
            }
        }

        $this->command->info('  ✅ 3 eventos creados con imágenes.');

        // ── 10 Proveedores ─────────────────────────────────────────────────────
        $proveedores = [];
        foreach ($this->empresas as $i => $datos) {
            $userProv = User::create([
                'name'              => 'Rep. ' . $datos['nombre'],
                'email'             => 'proveedor' . ($i + 1) . '@demo.impulsate.test',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
                'active_role'       => 'proveedor',
                'nombre_empresa'    => $datos['nombre'],
                'municipio'         => $datos['municipio'],
                'telefono'          => '999' . str_pad(($i + 1) * 100, 7, '0', STR_PAD_LEFT),
            ]);
            $userProv->assignRole('restaurantero');

            // Descargar logo del proveedor
            $logoPath = $this->descargarImagen($datos['seed_logo'], 'restauranteros/logos', 800, 500);

            // Construir productos_top con imágenes
            $productosTop = [];
            foreach ($datos['productos'] as $prod) {
                $fotoProd = $this->descargarImagen($prod['seed'], 'restauranteros/productos', 600, 400);
                $productosTop[] = [
                    'nombre'      => $prod['nombre'],
                    'descripcion' => $prod['descripcion'],
                    'foto_path'   => $fotoProd,
                ];
            }

            $restaurantero = Restaurantero::create([
                'user_id'               => $userProv->id,
                'edicion_id'            => $eventoActivo->id,
                'nombre_restaurante'    => $datos['nombre'],
                'razon_social'          => $datos['nombre'] . ' S.A. de C.V.',
                'nombre_representante'  => 'Rep. ' . $datos['nombre'],
                'telefono'              => '999' . str_pad(($i + 1) * 100, 7, '0', STR_PAD_LEFT),
                'municipio'             => $datos['municipio'],
                'categoria'             => $datos['categoria'],
                'descripcion'           => $datos['desc'],
                'activo'                => true,
                'aprobado'              => true,
                'num_empleados'         => rand(5, 80),
                'domicilio_en_yucatan'  => true,
                'rfc'                   => strtoupper(Str::random(4)) . '800101' . strtoupper(Str::random(3)),
                'logo_path'             => $logoPath,
                'productos_top'         => $productosTop,
            ]);

            $servicio = Servicio::create([
                'restaurantero_id' => $restaurantero->id,
                'nombre'           => 'Mesa de Networking — ' . $datos['nombre'],
                'descripcion'      => 'Presentación de productos y servicios: ' . implode(', ', array_column($datos['productos'], 'nombre')) . '.',
                'duracion_minutos' => $eventoActivo->tiempo_entre_citas_minutos,
                'precio'           => 0,
                'activo'           => true,
            ]);

            $proveedores[] = ['user' => $userProv, 'restaurantero' => $restaurantero, 'servicio' => $servicio];
            $this->command->info("    ✓ Proveedor " . ($i + 1) . ": {$datos['nombre']}");
        }
        $this->command->info('  ✅ 10 proveedores creados con imágenes y productos.');

        // ── 15 Compradores ─────────────────────────────────────────────────────
        $nombresComp = [
            'Ana García López', 'Carlos Mendoza Pérez', 'María Ramírez Díaz',
            'José Hernández Torres', 'Laura Jiménez Castro', 'Miguel Ángel Ruiz Flores',
            'Patricia Morales Sánchez', 'Roberto Cruz Vega', 'Sandra Torres Reyes',
            'Fernando López Ortiz', 'Gabriela Martínez Luna', 'Alejandro Romero Gutiérrez',
            'Verónica Castillo Méndez', 'Eduardo Navarro Serrano', 'Claudia Vargas Espinoza',
        ];
        $municipiosComp = ['Mérida', 'Valladolid', 'Tizimín', 'Progreso', 'Ticul',
                           'Izamal', 'Motul', 'Umán', 'Kanasín', 'Maxcanú'];

        $compradores = [];
        foreach ($nombresComp as $j => $nombre) {
            $municipio = $municipiosComp[$j % count($municipiosComp)];
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
        $estados       = ['pendiente', 'confirmada', 'cancelada', 'pendiente', 'confirmada'];
        $inicioEvento  = $ahora->copy()->addDays(3)->setHour(9)->setMinute(0)->setSecond(0);
        $intervalo     = $eventoActivo->tiempo_entre_citas_minutos;
        $citasCreadas  = 0;

        foreach ($compradores as $compIdx => $comprador) {
            $maxCitas = $eventoActivo->max_citas_por_comprador;
            for ($c = 0; $c < $maxCitas; $c++) {
                $provIdx = ($compIdx * $maxCitas + $c) % count($proveedores);
                $prov    = $proveedores[$provIdx];
                $inicio  = $inicioEvento->copy()->addMinutes(($compIdx * $maxCitas + $c) * $intervalo);

                Cita::create([
                    'edicion_id'       => $eventoActivo->id,
                    'restaurantero_id' => $prov['restaurantero']->id,
                    'servicio_id'      => $prov['servicio']->id,
                    'cliente_id'       => $comprador->id,
                    'inicio'           => $inicio,
                    'fin'              => $inicio->copy()->addMinutes($intervalo),
                    'estado'           => $estados[$c % count($estados)],
                    'mesa'             => rand(1, 10),
                ]);
                $citasCreadas++;
            }
        }

        // ── Citas para evento pasado ───────────────────────────────────────────
        $inicioEvPasado = $ahora->copy()->subMonths(6)->setHour(9)->setMinute(0)->setSecond(0);
        foreach ($compPasados as $ci => $comp) {
            $prov = $provPasados[$ci % count($provPasados)];
            $inicio = $inicioEvPasado->copy()->addMinutes($ci * 30);
            Cita::create([
                'edicion_id'       => $eventoPasado->id,
                'restaurantero_id' => $prov['restaurantero']->id,
                'servicio_id'      => $prov['servicio']->id,
                'cliente_id'       => $comp->id,
                'inicio'           => $inicio,
                'fin'              => $inicio->copy()->addMinutes(30),
                'estado'           => 'confirmada',
                'mesa'             => rand(1, 5),
            ]);
        }

        $this->command->info("  ✅ {$citasCreadas} citas en evento activo, " . count($compPasados) . " en evento pasado.");
        $this->command->info('');
        $this->command->info('✅ DemoDataSeeder completado.');
        $this->command->info('   Admin:       impulsate@iyemyucatan.com / password');
        $this->command->info('   Proveedores: proveedor1..10@demo.impulsate.test / password');
        $this->command->info('   Compradores: comprador1..15@demo.impulsate.test / password');
    }

    private function descargarImagen(string $seed, string $carpeta, int $ancho = 800, int $alto = 500): ?string
    {
        try {
            $url      = "https://picsum.photos/seed/{$seed}/{$ancho}/{$alto}";
            $response = Http::timeout(20)
                ->withOptions(['allow_redirects' => true])
                ->get($url);

            if ($response->successful() && strlen($response->body()) > 1000) {
                $path = "{$carpeta}/{$seed}.jpg";
                Storage::disk('public')->put($path, $response->body());
                return $path;
            }
        } catch (\Exception $e) {
            // Continúa sin imagen si falla la descarga
        }
        return null;
    }
}
