<?php

namespace App\Http\Controllers;

use App\Services\DashboardStats;

class DashboardController extends Controller
{
    public function index(DashboardStats $stats)
    {
        return view('dashboard.index', $stats->all());
    }
}
