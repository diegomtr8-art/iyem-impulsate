<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * INE y Constancia de Situacion Fiscal del usuario.
 *
 * Disco 'local' = storage/app/private (NO servido por el servidor web), con
 * nombre aleatorio para que la ruta no sea adivinable ni enumerable. Lo usan
 * CompletarPerfilController::subirDocumentos, DocumentoController y la API v1.
 */
class DocumentosUsuarioService
{
    public const TIPOS = ['ine', 'csf'];
    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png'];
    public const MAX_KB = 5120;
    /** Una CSF con mas antiguedad que esto no sirve para registrarse al bazar. */
    public const CSF_VIGENCIA_MESES = 3;

    public static function reglas(): array
    {
        return [
            'ine'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'csf'      => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'csf_fecha'=> 'nullable|date|before_or_equal:today',
        ];
    }

    public static function mensajes(): array
    {
        return [
            'ine.max'       => 'El INE no debe pesar más de 5 MB.',
            'csf.max'       => 'La CSF no debe pesar más de 5 MB.',
            'csf_fecha.before_or_equal' => 'La fecha de la CSF no puede ser futura.',
        ];
    }

    /** @param array<string, UploadedFile|null> $archivos  ['ine' => ..., 'csf' => ...] */
    public function subir(User $user, array $archivos, ?string $csfFecha): void
    {
        $datos = [];

        foreach (self::TIPOS as $campo) {
            $archivo = $archivos[$campo] ?? null;
            if (!$archivo instanceof UploadedFile) continue;

            $columna = $campo . '_path';

            // Borrar el anterior, este donde este (publico viejo o privado nuevo)
            if ($user->$columna) {
                foreach (['local', 'public'] as $disco) {
                    if (Storage::disk($disco)->exists($user->$columna)) {
                        Storage::disk($disco)->delete($user->$columna);
                    }
                }
            }

            $extension = strtolower($archivo->extension());
            $nombre    = $campo . '_' . Str::random(40) . '.' . $extension;

            $datos[$columna] = $archivo->storeAs("documentos/{$user->id}", $nombre, 'local');
        }

        if (filled($csfFecha)) {
            $datos['csf_fecha'] = $csfFecha;
        }

        if (!empty($datos)) {
            $user->update($datos);
        }
    }

    /** Solo el dueño del documento o un administrador pueden verlo. */
    public function puedeVer(User $solicitante, User $dueno): bool
    {
        return $solicitante->id === $dueno->id
            || $solicitante->hasRole('admin')
            || $solicitante->hasRole('super-admin');
    }

    public function ruta(User $user, string $tipo): ?string
    {
        return $tipo === 'ine' ? $user->ine_path : $user->csf_path;
    }

    /** Respuesta inline del archivo. Aborta 404 si no esta subido o no existe. */
    public function respuestaArchivo(User $user, string $tipo)
    {
        $ruta = $this->ruta($user, $tipo);

        abort_if(!$ruta, 404, 'El documento no ha sido subido.');
        abort_if(!Storage::disk('local')->exists($ruta), 404, 'El documento no se encuentra en el servidor.');

        // Inline para que el navegador lo muestre en una pestaña nueva.
        return Storage::disk('local')->response($ruta, null, [
            'Content-Disposition' => 'inline; filename="' . $tipo . '-' . $user->id . '.' . pathinfo($ruta, PATHINFO_EXTENSION) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function csfVigente(User $user): bool
    {
        return (bool) $user->csf_fecha
            && !Carbon::parse($user->csf_fecha)->lt(Carbon::now()->subMonths(self::CSF_VIGENCIA_MESES));
    }
}
