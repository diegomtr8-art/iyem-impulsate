<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DocumentosUsuarioService;
use Illuminate\Http\Request;

class DocumentoController extends Controller
{
    /**
     * Descarga autenticada de INE / CSF.
     * Solo el dueño del documento o un administrador pueden verlo.
     */
    public function mostrar(Request $request, User $user, string $tipo, DocumentosUsuarioService $documentos)
    {
        abort_unless(in_array($tipo, DocumentosUsuarioService::TIPOS, true), 404);

        abort_unless($documentos->puedeVer($request->user(), $user), 403, 'No tienes permiso para ver este documento.');

        return $documentos->respuestaArchivo($user, $tipo);
    }
}
