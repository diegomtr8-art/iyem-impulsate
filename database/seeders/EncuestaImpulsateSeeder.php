<?php

namespace Database\Seeders;

use App\Models\EncuestaPlantilla;
use Illuminate\Database\Seeder;

class EncuestaImpulsateSeeder extends Seeder
{
    public function run(): void
    {
        // Desactivar todas las plantillas existentes antes de activar la nueva
        EncuestaPlantilla::query()->update(['activa' => false]);

        EncuestaPlantilla::updateOrCreate(
            ['nombre' => 'Satisfacción e Impacto — Encuentro de Negocios Impúlsate'],
            [
                'descripcion' => 'Encuesta oficial con flujo condicional Comprador/Proveedor. '
                               . '19 preguntas: datos generales, sección específica por perfil y logística.',
                'segmento'    => 'todos',
                'activa'      => true,
                'preguntas'   => [
                    [
                        'id'        => 'correo',
                        'tipo'      => 'texto',
                        'texto'     => 'Correo electrónico',
                        'requerida' => true,
                    ],
                    [
                        'id'        => 'nombre_negocio',
                        'tipo'      => 'texto',
                        'texto'     => 'Nombre del negocio o empresa',
                        'requerida' => false,
                    ],
                    [
                        'id'        => 'nombre_representante',
                        'tipo'      => 'texto',
                        'texto'     => 'Nombre del emprendedor o representante',
                        'requerida' => false,
                    ],
                    [
                        'id'        => 'perfil',
                        'tipo'      => 'opciones',
                        'texto'     => 'Perfil de participación en este encuentro:',
                        'requerida' => true,
                        'opciones'  => ['Comprador', 'Proveedor'],
                    ],
                    [
                        'id'        => 'c_oferta_coincide',
                        'tipo'      => 'opciones',
                        'texto'     => '¿La oferta de los proveedores asignados coincidió con las necesidades actuales de tu negocio?',
                        'requerida' => false,
                        'opciones'  => [
                            'Sí, totalmente.',
                            'Parcialmente (algunos perfiles no encajaban).',
                            'No, no era lo que buscaba.',
                        ],
                        'condicion' => ['pregunta_id' => 'perfil', 'operador' => 'igual', 'valor' => 'Comprador'],
                    ],
                    [
                        'id'        => 'c_intenciones_compra',
                        'tipo'      => 'opciones',
                        'texto'     => '¿Cuántas intenciones de compra o alianzas comerciales estimas concretar a raíz de este evento?',
                        'requerida' => false,
                        'opciones'  => [
                            'Ninguna por el momento.',
                            'De 1 a 3 alianzas/compras.',
                            'Más de 3 alianzas/compras.',
                        ],
                        'condicion' => ['pregunta_id' => 'perfil', 'operador' => 'igual', 'valor' => 'Comprador'],
                    ],
                    [
                        'id'        => 'c_calidad_proveedores',
                        'tipo'      => 'opciones',
                        'texto'     => 'Califica la calidad y presentación de los productos de los proveedores locales:',
                        'requerida' => false,
                        'opciones'  => [
                            '⭐⭐⭐⭐⭐ (Excelente nivel, listos para comercializar)',
                            '⭐⭐⭐⭐ (Bueno, solo detalles menores)',
                            '⭐⭐⭐ (Regular, les falta desarrollo/certificaciones)',
                            '⭐ (Deficiente)',
                        ],
                        'condicion' => ['pregunta_id' => 'perfil', 'operador' => 'igual', 'valor' => 'Comprador'],
                    ],
                    [
                        'id'        => 'p_perfil_compradores',
                        'tipo'      => 'opciones',
                        'texto'     => '¿El perfil de los compradores con los que te entrevistaste correspondía a tu cliente ideal?',
                        'requerida' => false,
                        'opciones'  => [
                            'Sí, todos tenían poder de decisión o interés real.',
                            'Solo algunos.',
                            'No, ninguno se alineaba a mi giro.',
                        ],
                        'condicion' => ['pregunta_id' => 'perfil', 'operador' => 'igual', 'valor' => 'Proveedor'],
                    ],
                    [
                        'id'        => 'p_seguimiento',
                        'tipo'      => 'opciones',
                        'texto'     => '¿Lograste agendar un seguimiento formal (visita, envío de muestras o cotización) con algún comprador?',
                        'requerida' => true,
                        'opciones'  => [
                            'Sí, con la mayoría.',
                            'Sí, con 1 o 2.',
                            'No se concretó seguimiento.',
                        ],
                        'condicion' => ['pregunta_id' => 'perfil', 'operador' => 'igual', 'valor' => 'Proveedor'],
                    ],
                    [
                        'id'        => 'p_proyeccion',
                        'tipo'      => 'opciones',
                        'texto'     => 'Proyección económica: ¿A cuánto asciende el monto estimado de ventas que esperas cerrar a mediano plazo derivado de estas citas? (Dato confidencial para estadística interna del IYEM)',
                        'requerida' => true,
                        'opciones'  => [
                            '$0 (No se prevén cierres)',
                            'Menos de $10,000 MXN',
                            'De $10,000 a $50,000 MXN',
                            'Más de $50,000 MXN',
                        ],
                        'condicion' => ['pregunta_id' => 'perfil', 'operador' => 'igual', 'valor' => 'Proveedor'],
                    ],
                    [
                        'id'        => 'p_monto_aproximado',
                        'tipo'      => 'texto',
                        'texto'     => 'En caso de venta, ¿nos podría compartir un monto aproximado?',
                        'requerida' => true,
                        'condicion' => ['pregunta_id' => 'p_proyeccion', 'operador' => 'diferente', 'valor' => '$0 (No se prevén cierres)'],
                    ],
                    [
                        'id'        => 'p_experiencia_compradores',
                        'tipo'      => 'opciones',
                        'texto'     => '¿Cómo fue su experiencia con los compradores participantes?',
                        'requerida' => true,
                        'opciones'  => ['Excelente', 'Buena', 'Regular', 'Mala'],
                        'condicion' => ['pregunta_id' => 'perfil', 'operador' => 'igual', 'valor' => 'Proveedor'],
                    ],
                    [
                        'id'        => 'tiempo_cita',
                        'tipo'      => 'opciones',
                        'texto'     => '¿Qué te pareció la dinámica del tiempo por cita (10 minutos)?',
                        'requerida' => false,
                        'opciones'  => [
                            'Tiempo suficiente y adecuado.',
                            'Muy corto, faltó tiempo para negociar.',
                            'Muy largo.',
                        ],
                    ],
                    [
                        'id'        => 'cal_org_staff',
                        'tipo'      => 'opciones',
                        'texto'     => 'Califica: Organización del staff',
                        'requerida' => false,
                        'opciones'  => ['Mala', 'Regular', 'Buena', 'Excelente'],
                    ],
                    [
                        'id'        => 'cal_instalaciones',
                        'tipo'      => 'opciones',
                        'texto'     => 'Califica: Instalaciones / Sede',
                        'requerida' => false,
                        'opciones'  => ['Mala', 'Regular', 'Buena', 'Excelente'],
                    ],
                    [
                        'id'        => 'cal_claridad_agenda',
                        'tipo'      => 'opciones',
                        'texto'     => 'Califica: Claridad en su agenda',
                        'requerida' => false,
                        'opciones'  => ['Mala', 'Regular', 'Buena', 'Excelente'],
                    ],
                    [
                        'id'        => 'sectores_futuros',
                        'tipo'      => 'texto',
                        'texto'     => '¿Qué otros sectores te gustaría que abordemos en los próximos Encuentros de Negocio? (Ej. Textil, Tecnológico, Alimentos Gourmet, etc.)',
                        'requerida' => false,
                    ],
                    [
                        'id'        => 'comentarios_adicionales',
                        'tipo'      => 'texto',
                        'texto'     => 'Comentarios adicionales sobre su experiencia',
                        'requerida' => false,
                    ],
                    [
                        'id'        => 'mejoras_futuras',
                        'tipo'      => 'texto',
                        'texto'     => '¿Qué podríamos mejorar para futuros encuentros de negocios?',
                        'requerida' => false,
                    ],
                ],
            ]
        );

        $this->command->info('Plantilla de encuesta IMPULSATE creada y activada.');
    }
}
