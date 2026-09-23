<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\PlantillaCorreoMail;
use App\Models\PlantillaCorreo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

/**
 * Plantillas de correo (solo super-admin): lista, alta, edicion de asunto y
 * contenido, activar/desactivar, restablecer, envio manual (en cola),
 * baja y vista previa. Lo usan SuperAdmin\PlantillasCorreoController (web) y
 * la API v1.
 */
class PlantillasCorreoService
{
    public const TIPOS_DESTINATARIO = ['comprador', 'proveedor', 'ambos'];

    public const PLACEHOLDERS_NUEVA = [
        '{{nombre_usuario}}'   => 'Nombre del usuario destinatario',
        '{{nombre_proveedor}}' => 'Nombre del proveedor / empresa',
        '{{nombre_evento}}'    => 'Nombre del evento',
        '{{fecha_cita}}'       => 'Fecha de la cita',
        '{{hora_cita}}'        => 'Hora de la cita',
    ];

    public const HTML_BASE = <<<HTML
<div style="background:#f9fafb;padding:40px 20px;font-family:'Segoe UI',Arial,sans-serif;">
<div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">
  <div style="background:linear-gradient(135deg,#8b1028,#45060f);padding:32px 40px;text-align:center;">
    <h1 style="color:#ffffff;margin:0;font-size:24px;font-weight:800;">Encuentro de Negocios Impulsate</h1>
    <p style="color:#fbc4cd;margin:8px 0 0;font-size:14px;">Instituto Yucateco de Emprendedores</p>
  </div>
  <div style="padding:32px 40px;">
    <p style="margin:0 0 8px;font-size:16px;color:#374151;">Hola, <strong>{{nombre_usuario}}</strong></p>
    <!-- ESCRIBE TU CONTENIDO AQUÍ -->
    <p style="margin:0;font-size:15px;color:#374151;">Mensaje del correo.</p>
  </div>
  <div style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
    <p style="margin:0;font-size:12px;color:#9ca3af;">Encuentro de Negocios Impulsate · Instituto Yucateco de Emprendedores</p>
  </div>
</div>
</div>
HTML;

    /** Valores de ejemplo para la vista previa (no se envian). */
    public const EJEMPLOS = [
        'nombre_usuario'   => 'María Pérez',
        'nombre_proveedor' => 'Salsas Doña Chela',
        'nombre_empresa'   => 'Salsas Doña Chela S.A. de C.V.',
        'nombre_evento'    => 'Encuentro de Negocios Impúlsate',
        'fecha_cita'       => '15 de octubre de 2026',
        'hora_cita'        => '10:30',
        'motivo_rechazo'   => 'Motivo de ejemplo',
    ];

    public static function reglasCrear(): array
    {
        return [
            'nombre'            => ['required', 'string', 'max:255'],
            'clave'             => ['required', 'string', 'max:100', 'unique:plantillas_correo,clave', 'regex:/^[a-z0-9_]+$/'],
            'asunto'            => ['required', 'string', 'max:255'],
            'contenido'         => ['required', 'string'],
            'tipo_destinatario' => ['required', Rule::in(self::TIPOS_DESTINATARIO)],
        ];
    }

    public const REGLAS_EDITAR = [
        'asunto'    => ['required', 'string', 'max:255'],
        'contenido' => ['required', 'string'],
    ];

    public const REGLAS_ENVIAR = [
        'destinatarios'   => ['required', 'array', 'min:1'],
        'destinatarios.*' => ['email'],
    ];

    public function listar(): Collection
    {
        return PlantillaCorreo::orderBy('nombre')->get();
    }

    public function crear(array $data): PlantillaCorreo
    {
        $data['es_sistema'] = false;
        $data['activo'] = true;

        return PlantillaCorreo::create($data);
    }

    public function actualizar(PlantillaCorreo $plantilla, array $data): PlantillaCorreo
    {
        $plantilla->update($data);

        return $plantilla;
    }

    public function alternar(PlantillaCorreo $plantilla): PlantillaCorreo
    {
        $plantilla->update(['activo' => !$plantilla->activo]);

        return $plantilla;
    }

    /**
     * Corre PlantillasCorreoSeeder. OJO: el seeder usa firstOrCreate, asi que
     * solo vuelve a crear las plantillas de sistema que falten; NO restablece
     * el asunto/contenido de las existentes (ver reporte de la fase 4B).
     */
    public function restablecer(): void
    {
        Artisan::call('db:seed', ['--class' => 'PlantillasCorreoSeeder', '--force' => true]);
    }

    /** Encola un correo por destinatario, sin variables. Devuelve cuantos. */
    public function enviar(PlantillaCorreo $plantilla, array $destinatarios): int
    {
        $enviados = 0;
        foreach ($destinatarios as $email) {
            Mail::to($email)->queue(new PlantillaCorreoMail($plantilla, []));
            $enviados++;
        }

        return $enviados;
    }

    public function eliminar(PlantillaCorreo $plantilla): void
    {
        if ($plantilla->es_sistema) {
            throw new ReglaDeNegocioException('No se pueden eliminar plantillas del sistema.');
        }
        $plantilla->delete();
    }

    public function placeholdersParaClave(string $clave): array
    {
        $comunes = [
            '{{nombre_usuario}}' => 'Nombre del usuario destinatario',
            '{{nombre_proveedor}}' => 'Nombre del proveedor / empresa',
            '{{fecha_cita}}' => 'Fecha de la cita (ej: 15 de junio de 2026)',
            '{{hora_cita}}' => 'Hora de la cita (ej: 10:30)',
        ];

        $extra = match ($clave) {
            'proveedor_aprobado', 'proveedor_rechazado' => [
                '{{nombre_empresa}}' => 'Nombre de la empresa del proveedor',
            ],
            'encuesta_satisfaccion' => [
                '{{nombre_evento}}' => 'Nombre del evento',
                '{{enlace_encuesta}}' => 'URL de la encuesta',
            ],
            'evento_solicitud_aprobada' => [
                '{{nombre_evento}}' => 'Nombre del evento al que fue aprobado',
            ],
            'evento_solicitud_rechazada' => [
                '{{nombre_evento}}' => 'Nombre del evento',
                '{{motivo_rechazo}}' => 'Motivo del rechazo ingresado por el administrador',
            ],
            'bazar_solicitud_recibida', 'bazar_seleccionado' => [
                '{{nombre_evento}}' => 'Nombre del evento',
            ],
            'bazar_rechazado' => [
                '{{nombre_evento}}' => 'Nombre del evento',
                '{{url_evaluacion}}' => 'Link a la página pública con el detalle de la evaluación',
            ],
            default => [],
        };

        return array_merge($comunes, $extra);
    }

    /**
     * Vista previa: asunto y HTML completo del correo (layout
     * mail.plantilla-generica) con variables de ejemplo. No guarda ni envia.
     * Sirve para una plantilla guardada o para un borrador (asunto/contenido).
     *
     * @return array{asunto: string, html: string}
     */
    public function vistaPrevia(string $asunto, string $contenido, array $vars = []): array
    {
        $vars = array_merge(self::EJEMPLOS, [
            'enlace_encuesta' => url('/encuesta/EJEMPLO'),
            'url_evaluacion'  => url('/evaluacion/EJEMPLO'),
        ], array_filter($vars, fn ($v) => $v !== null));
        $plantilla = new PlantillaCorreo(['asunto' => $asunto, 'contenido' => $contenido]);
        $mail = new PlantillaCorreoMail($plantilla, $vars);

        return [
            'asunto' => $mail->envelope()->subject,
            'html'   => $mail->render(),
        ];
    }
}
