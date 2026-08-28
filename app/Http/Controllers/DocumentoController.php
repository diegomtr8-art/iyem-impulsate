<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    /**
     * Descarga autenticada de INE / CSF.
     * Solo el dueño del documento o un administrador pueden verlo.
     */
    public function mostrar(Request $request, User $user, string $tipo)
    {
        abort_unless(in_array($tipo, ['ine', 'csf'], true), 404);

        $solicitante = $request->user();

        $autorizado = $solicitante->id === $user->id
            || $solicitante->hasRole('admin')
            || $solicitante->hasRole('super-admin');

        abort_unless($autorizado, 403, 'No tienes permiso para ver este documento.');

        $ruta = $tipo === 'ine' ? $user->ine_path : $user->csf_path;

        abort_if(!$ruta, 404, 'El documento no ha sido subido.');
        abort_if(!Storage::disk('local')->exists($ruta), 404, 'El documento no se encuentra en el servidor.');

        // Inline para que el navegador lo muestre en una pestaña nueva.
        return Storage::disk('local')->response($ruta, null, [
            'Content-Disposition' => 'inline; filename="' . $tipo . '-' . $user->id . '.' . pathinfo($ruta, PATHINFO_EXTENSION) . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
