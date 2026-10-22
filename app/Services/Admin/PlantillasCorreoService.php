<?php

namespace App\Services\Admin;

use App\Exceptions\ReglaDeNegocioException;
use App\Mail\PlantillaCorreoMail;
use App\Models\Evento;
use App\Models\PlantillaCorreo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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

    private const FORMATO_FECHA = 'd \\d\\e F \\d\\e Y';

    /**
     * Catalogo agrupado de variables para el editor. El agrupado es solo para
     * la interfaz: al sustituir, todas se tratan igual.
     *
     * OJO: anadir una variable aqui NO basta. Tiene que existir tambien en
     * variablesParaDestinatario() (envio manual) o la rellena el flujo que
     * dispara ese correo. Si nadie la rellena, sale vacia.
     */
    public const GRUPOS_PLACEHOLDERS = [
        'Usuario' => [
            '{{nombre_usuario}}'    => 'Nombre de la persona que recibe el correo',
            '{{email_usuario}}'     => 'Su correo electrónico',
            '{{telefono_usuario}}'  => 'Su teléfono',
            '{{empresa_usuario}}'   => 'Nombre de su empresa',
            '{{municipio_usuario}}' => 'Su municipio',
        ],
        'Proveedor' => [
            '{{nombre_proveedor}}' => 'Nombre comercial del proveedor',
            '{{nombre_empresa}}'   => 'Razón social de la empresa',
        ],
        'Evento' => [
            '{{nombre_evento}}'      => 'Nombre del evento activo',
            '{{descripcion_evento}}' => 'Descripción breve del evento',
            '{{fecha_evento}}'       => 'Fecha de inicio (ej. 18 de septiembre de 2026)',
            '{{hora_evento}}'        => 'Hora de inicio (ej. 10:00)',
            '{{fecha_fin_evento}}'   => 'Fecha de cierre del evento',
            '{{convocatoria_url}}'   => 'Liga a la convocatoria del evento',
        ],
        'Cita' => [
            '{{fecha_cita}}' => 'Fecha de la cita',
            '{{hora_cita}}'  => 'Hora de la cita',
            '{{mesa_cita}}'  => 'Número de mesa asignada',
        ],
        'Enlaces' => [
            '{{url_sitio}}'       => 'Página principal de Impúlsate',
            '{{url_mi_panel}}'    => 'Panel del participante',
            '{{url_proveedores}}' => 'Directorio de proveedores',
            '{{enlace_encuesta}}' => 'Liga a la encuesta (solo en correos de encuesta)',
            '{{url_evaluacion}}'  => 'Liga a la evaluación del bazar (solo en bazar)',
        ],
        'Otros' => [
            '{{motivo_rechazo}}' => 'Motivo escrito por el administrador',
            '{{fecha_hoy}}'      => 'Fecha de hoy',
            '{{anio_actual}}'    => 'Año actual',
        ],
    ];

    /** Lista plana del catalogo (la consume la API v1). */
    public static function placeholdersPlanos(): array
    {
        return array_merge(...array_values(self::GRUPOS_PLACEHOLDERS));
    }

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
        'nombre_usuario'     => 'María Pérez',
        'email_usuario'      => 'maria.perez@ejemplo.com',
        'telefono_usuario'   => '999 123 4567',
        'empresa_usuario'    => 'Salsas Doña Chela',
        'municipio_usuario'  => 'Mérida',
        'nombre_proveedor'   => 'Salsas Doña Chela',
        'nombre_empresa'     => 'Salsas Doña Chela S.A. de C.V.',
        'nombre_evento'      => 'Encuentro de Negocios Impúlsate',
        'descripcion_evento' => 'Conecta con proveedores de todo Yucatán',
        'fecha_evento'       => '18 de septiembre de 2026',
        'hora_evento'        => '10:00',
        'fecha_fin_evento'   => '18 de septiembre de 2026',
        'convocatoria_url'   => 'https://impulsate.iyemyucatan.com/convocatoria',
        'fecha_cita'         => '15 de octubre de 2026',
        'hora_cita'          => '10:30',
        'mesa_cita'          => '12',
        'motivo_rechazo'     => 'Motivo de ejemplo',
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

    public const REGLAS_PRUEBA = [
        'asunto'     => ['required', 'string', 'max:255'],
        'contenido'  => ['required', 'string'],
        'email'      => ['required', 'email'],
        'usuario_id' => ['nullable', 'integer', 'exists:users,id'],
    ];

    public const REGLAS_VISTA_PREVIA = [
        'asunto'     => ['required', 'string', 'max:255'],
        'contenido'  => ['required', 'string'],
        'usuario_id' => ['nullable', 'integer', 'exists:users,id'],
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

    /**
     * Encola un correo por destinatario, con las variables resueltas con los
     * datos de cada uno. Antes iba con un array vacio y los {{marcadores}}
     * llegaban literales. Devuelve cuantos.
     */
    public function enviar(PlantillaCorreo $plantilla, array $destinatarios): int
    {
        $evento = Evento::activo();
        $enviados = 0;
        foreach ($destinatarios as $email) {
            Mail::to($email)->queue(new PlantillaCorreoMail($plantilla, $this->variablesParaDestinatario($email, $evento)));
            $enviados++;
        }

        return $enviados;
    }

    /**
     * Variables de un destinatario: sus datos si el correo es de un usuario
     * registrado; si no, valores neutros ("Participante" o vacio).
     */
    public function variablesParaDestinatario(string $email, ?Evento $evento = null): array
    {
        $user = User::with('restaurantero')->where('email', $email)->first();

        return [
            'nombre_usuario'     => $user?->name ?? 'Participante',
            'email_usuario'      => $email,
            'telefono_usuario'   => $user?->telefono ?? '',
            'empresa_usuario'    => $user?->nombre_empresa ?? '',
            'municipio_usuario'  => $user?->municipio ?? '',
            'nombre_proveedor'   => $user?->restaurantero?->nombre_restaurante ?? ($user?->name ?? ''),
            'nombre_empresa'     => $user?->restaurantero?->razon_social ?? ($user?->nombre_empresa ?? ''),
            'nombre_evento'      => $evento?->nombre ?? '',
            'descripcion_evento' => $evento?->descripcion ?? '',
            'fecha_evento'       => $evento?->fecha_hora_inicio?->translatedFormat(self::FORMATO_FECHA) ?? '',
            'hora_evento'        => $evento?->fecha_hora_inicio?->format('H:i') ?? '',
            'fecha_fin_evento'   => $evento?->fecha_hora_fin?->translatedFormat(self::FORMATO_FECHA) ?? '',
            'convocatoria_url'   => $evento?->convocatoria_url ?? '',
        ] + $this->variablesGenerales();
    }

    /**
     * Envia un borrador (sin guardarlo) a un correo de prueba, con valores de
     * ejemplo y el asunto marcado [PRUEBA]. Va con sendNow() y no en cola:
     * PlantillaCorreoMail usa SerializesModels y un modelo sin id no se puede
     * restaurar en el worker. Ademas asi un fallo SMTP se ve en pantalla.
     * Con $usuarioId las variables salen con los datos de ese usuario.
     */
    public function enviarPrueba(string $asunto, string $contenido, string $email, ?int $usuarioId = null): void
    {
        $plantilla = new PlantillaCorreo(['asunto' => '[PRUEBA] ' . $asunto, 'contenido' => $contenido]);

        try {
            Mail::to($email)->sendNow(new PlantillaCorreoMail($plantilla, $this->variablesDePrueba($usuarioId)));
        } catch (\Throwable $e) {
            Log::error('Error correo de prueba de plantilla: ' . $e->getMessage());
            throw new ReglaDeNegocioException('No se pudo enviar el correo de prueba: ' . $e->getMessage(), 'email');
        }
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
        $vars = array_merge($this->variablesEjemplo(), array_filter($vars, fn ($v) => $v !== null));
        $plantilla = new PlantillaCorreo(['asunto' => $asunto, 'contenido' => $contenido]);
        $mail = new PlantillaCorreoMail($plantilla, $vars);

        return [
            'asunto' => $mail->envelope()->subject,
            'html'   => $mail->render(),
        ];
    }

    /**
     * Variables para probar un borrador. Sin usuario, todo de ejemplo. Con
     * usuario, sus datos reales (aunque esten vacios, como en un envio de
     * verdad) y de ejemplo solo las que rellena un flujo concreto (cita,
     * encuesta, motivo de rechazo...), para que la prueba no salga en blanco.
     */
    public function variablesDePrueba(?int $usuarioId): array
    {
        $user = $usuarioId ? User::find($usuarioId) : null;
        if (!$user) {
            return $this->variablesEjemplo();
        }

        return array_merge($this->variablesEjemplo(), $this->variablesParaDestinatario($user->email, Evento::activo()));
    }

    /** Usuarios para el buscador de la prueba: por nombre, correo o empresa. */
    public function buscarUsuarios(string $texto): Collection
    {
        $q = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($texto)) . '%';

        return User::query()
            ->where(fn ($w) => $w->where('name', 'like', $q)
                ->orWhere('email', 'like', $q)
                ->orWhere('nombre_empresa', 'like', $q))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email', 'nombre_empresa', 'active_role']);
    }

    /** Valores de ejemplo + enlaces reales: vista previa y envio de prueba. */
    private function variablesEjemplo(): array
    {
        return array_merge(self::EJEMPLOS, [
            'enlace_encuesta' => url('/encuesta/EJEMPLO'),
            'url_evaluacion'  => url('/evaluacion/EJEMPLO'),
        ], $this->variablesGenerales());
    }

    /** Enlaces y fechas que no dependen del destinatario. */
    private function variablesGenerales(): array
    {
        return [
            'url_sitio'       => url('/'),
            'url_mi_panel'    => url('/mi-panel'),
            'url_proveedores' => url('/proveedores'),
            'fecha_hoy'       => now()->translatedFormat(self::FORMATO_FECHA),
            'anio_actual'     => now()->format('Y'),
        ];
    }
}
