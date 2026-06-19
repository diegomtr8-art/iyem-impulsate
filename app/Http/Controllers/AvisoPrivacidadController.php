<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class AvisoPrivacidadController extends Controller
{
    public function index()
    {
        return Inertia::render('AvisoPrivacidad');
    }
}
