<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Controllers\Controller;
use App\Services\CambioRolService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SwitchRoleController extends Controller
{
    public function __invoke(Request $request, CambioRolService $cambioRol)
    {
        try {
            $cambioRol->alternar($request->user());
        } catch (ReglaDeNegocioException $e) {
            return back()->withErrors([$e->campo => $e->getMessage()]);
        }

        $request->session()->save();
        return Inertia::location(route('dashboard'));
    }
}
