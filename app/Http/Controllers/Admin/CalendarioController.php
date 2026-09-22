<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\CalendarioAdminService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** La logica vive en CalendarioAdminService (compartido con la API v1). */
class CalendarioController extends Controller
{
    public function __construct(private CalendarioAdminService $calendario) {}

    public function index()
    {
        return Inertia::render('Admin/Calendario/Index', [
            'restauranteros' => $this->calendario->proveedores(),
        ]);
    }

    public function events(Request $request)
    {
        return response()->json($this->calendario->eventos($request->only(['restaurantero_id', 'start', 'end'])));
    }
}
