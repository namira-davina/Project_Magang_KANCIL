<?php

namespace App\Http\Controllers;

use App\Services\MonthlyReport;
use Illuminate\Http\Request;

/**
 * Laporan rekapitulasi bulanan antrean layanan, pengaduan, dan performa bot.
 */
class LaporanController extends Controller
{
    private const MIN_YEAR = 2020;

    private const MAX_YEAR = 2100;

    public function index(Request $request, MonthlyReport $report)
    {
        $validated = $request->validate([
            'bulan' => ['nullable', 'integer', 'between:1,12'],
            'tahun' => ['nullable', 'integer', 'between:'.self::MIN_YEAR.','.self::MAX_YEAR],
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
