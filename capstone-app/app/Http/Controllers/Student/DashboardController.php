<?php

namespace App\Http\Controllers\Student;

use App\Services\StudentDashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** F19 — dashboard tim mahasiswa. Penyusunan data ada di StudentDashboard. */
class DashboardController extends StudentController
{
    public function index(Request $request, StudentDashboard $dashboard): View
    {
        return view('mahasiswa.dashboard', $dashboard->for($request->user()));
    }
}
