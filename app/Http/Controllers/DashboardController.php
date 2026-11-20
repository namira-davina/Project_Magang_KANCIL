<?php

namespace App\Http\Controllers;

use App\Services\DashboardStats;
use Illuminate\Contracts\View\View;

/**
 * Ringkasan metrik operasional layanan dan kinerja bot KANCIL SAPA.
 */
class DashboardController extends Controller
{
    public function index(DashboardStats $stats): View
    {
        return view('dashboard.index', $stats->all());
    }
}
