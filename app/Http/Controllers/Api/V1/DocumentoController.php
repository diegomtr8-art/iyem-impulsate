<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DocumentosUsuarioService;
use Illuminate\Http\Request;

/**
 * Sirve INE / CSF por URL firmada temporal (la genera DocumentosUsuarioResource).
 * La firma cubre user, tipo y solicitante; ademas se vuelve a comprobar que el
 * solicitante siga siendo el dueño o un admin (p.ej. si le quitaron el rol).
 */
class DocumentoController extends Controller
{
    public function ver(Request $request, User $user, string $tipo, DocumentosUsuarioService $documentos)
    {
        abort_unless(in_array($tipo, DocumentosUsuarioService::TIPOS, true), 404);

        $solicitante = User::find((int) $request->query('solicitante'));

        abort_unless($solicitante && $documentos->puedeVer($solicitante, $user), 403, 'No tienes permiso para ver este documento.');

        return $documentos->respuestaArchivo($user, $tipo);
    }
}
