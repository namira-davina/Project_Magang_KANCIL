<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReport;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    public function index(Request $request, MonthlyReport $report)
    {
        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:2020,2100'],
        ]);

        $month = (int) ($validated['bulan'] ?? now()->month);
        $year = (int) ($validated['tahun'] ?? now()->year);

        return view('laporan.index', [
            ...$report->build($month, $year),
            'monthNames' => MonthlyReport::MONTHS,
            'years' => range(min($year, (int) now()->year) - 1, max($year, (int) now()->year) + 1),
        ]);
    }
}
