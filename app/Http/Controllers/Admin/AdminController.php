<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardService;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard(AdminDashboardService $dashboard)
    {
        return Inertia::render('Admin/Dashboard', $dashboard->kpis());
    }
}
