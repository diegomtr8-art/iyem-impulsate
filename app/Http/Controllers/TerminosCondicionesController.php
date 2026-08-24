<?php

namespace App\Http\Controllers;

use Inertia\Inertia;

class TerminosCondicionesController extends Controller
{
    public function index()
    {
        return Inertia::render('TerminosCondiciones');
    }
}
