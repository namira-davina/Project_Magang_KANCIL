
@extends('layouts.app')

@section('title', 'Laporan Bulanan')
@section('breadcrumb', 'Laporan')

@section('content')

    {{-- HEADER HALAMAN --}}
    <div class="page-heading-action">
        <div>
            <h1>Laporan Bulanan</h1>
            <p>Rekap aktivitas layanan informasi dan pengaduan masyarakat.</p>
        </div>
    </div>

    {{-- BARIS FILTER BULAN & TAHUN SESUAI DESAIN HALAMAN 10 --}}
    <div class="laporan-filter-bar">
        <div class="filter-group-inline">
            <label for="filterBulan">BULAN:</label>
            <select id="filterBulan" class="select-clean-inline">
                @foreach ($monthNames as $number => $name)
                    <option value="{{ $number }}" @selected($number === $month)>{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-group-inline">
            <label for="filterTahun">TAHUN:</label>
            <select id="filterTahun" class="select-clean-inline">
                @foreach ($years as $option)
                    <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
                @endforeach
            </select>
        </div>

        <button type="button" class="btn-primary-dark" id="btnTampilkanLaporan">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="21" x2="4" y2="14"></line>
                <line x1="4" y1="10" x2="4" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12" y2="3"></line>
                <line x1="20" y1="21" x2="20" y2="16"></line>
                <line x1="20" y1="12" x2="20" y2="3"></line>
                <line x1="1" y1="14" x2="7" y2="14"></line>
                <line x1="9" y1="8" x2="15" y2="8"></line>
                <line x1="17" y1="16" x2="23" y2="16"></line>
            </svg>
            <span>Tampilkan Laporan</span>
        </button>
    </div>

    {{-- 4 STAT CARDS LAPORAN (SAMA DENGAN DASHBOARD) --}}
    <div class="stat-grid-4">

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">Total Pertanyaan</span>
                <span class="stat-metric-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value" id="laporanPercakapan">{{ number_format($totalPertanyaan, 0, ',', '.') }}</div>
        </div>

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">Terjawab Otomatis</span>
                <span class="stat-metric-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                        <circle cx="12" cy="5" r="2"></circle>
                        <path d="M12 7v4"></path>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value text-green" id="laporanFaq">{{ number_format($terjawabOtomatis, 0, ',', '.') }}</div>
        </div>

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">Dieskalasi ke Petugas</span>
                <span class="stat-metric-icon stat-icon-amber">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value text-orange" id="laporanEskalasi">{{ number_format($totalEskalasi, 0, ',', '.') }}</div>
        </div>

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">Total Pengaduan</span>
                <span class="stat-metric-icon alert-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value" id="laporanPengaduan">{{ number_format($totalPengaduan, 0, ',', '.') }}</div>
        </div>

    </div>

    {{-- GRAFIK BATANG PERTANYAAN PER BULAN --}}
    <div class="content-panel chart-panel-large">
        <div class="panel-heading-row">
            <div>
                <h3>Grafik Batang Jumlah Pertanyaan per Bulan</h3>
                <p>Tren volume konsultasi dan pertanyaan masyarakat (Tahun {{ $year }})</p>
            </div>
            <span class="panel-badge-pill">Tahun {{ $year }}</span>
        </div>

        <div class="chart-wrapper-canvas large-height">
            <canvas id="laporanBarChartCanvas"></canvas>
        </div>
    </div>

    {{-- TABEL PERTANYAAN PALING SERING DITANYAKAN --}}
    <div class="content-panel table-panel-with-export">
        <div class="panel-heading-row">
            <div>
                <h3>Daftar Pertanyaan Paling Sering Ditanyakan</h3>
                <p>Topik informasi yang paling banyak dikonsultasikan oleh pemohon ({{ $periodLabel }})</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="clean-data-table" id="tableFaqLaporan">
                <thead>
                    <tr>
                        <th width="70">NO</th>
                        <th>PERTANYAAN</th>
                        <th width="240">KATEGORI</th>
                        <th width="180" style="text-align: right;">JUMLAH DITANYAKAN</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($topFaq as $i => $faq)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="font-medium">{{ $faq->question }}@if ($faq->code) <span class="text-muted">({{ $faq->code }})</span>@endif</td>
                            <td class="text-muted">{{ $faq->category ?? 'Tanpa Kategori' }}</td>
                            <td style="text-align: right;" class="font-medium">{{ number_format($faq->jumlah, 0, ',', '.') }} kali</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted" style="text-align: center; padding: 24px;">
                                Belum ada pertanyaan pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel-heading-row" style="margin-top: 28px;">
            <div>
                <h3>Rekap Eskalasi per Kode</h3>
                <p>Matriks Eskalasi A1–C6 ({{ $periodLabel }}) • Tiket selesai {{ $tiket['selesai'] }} (tepat waktu {{ $tiket['tepatWaktu'] }}) • Naik ke Pimpinan {{ $tiket['level2'] }}</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="clean-data-table" id="tableEskalasiLaporan">
                <thead>
                    <tr>
                        <th width="70">KODE</th>
                        <th>KETERANGAN</th>
                        <th width="160">PRIORITAS</th>
                        <th width="180" style="text-align: right;">JUMLAH</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($eskalasiPerKode as $row)
                        <tr>
                            <td class="font-medium">{{ $row->kode }}</td>
                            <td>{{ $row->keterangan }}</td>
                            <td class="text-muted">{{ $row->prioritas }}</td>
                            <td style="text-align: right;" class="font-medium">{{ number_format($row->jumlah, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- EXPORT BUTTONS DI KANAN BAWAH SESUAI DESAIN --}}
        <div class="laporan-export-footer-bar">
            <button type="button" class="btn-export-outline pdf" id="btnExportPDF">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Export PDF</span>
            </button>

            <button type="button" class="btn-export-outline excel" id="btnExportExcel">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="8" y1="13" x2="16" y2="17"></line>
                    <line x1="16" y1="13" x2="8" y2="17"></line>
                </svg>
                <span>Export Excel</span>
            </button>
        </div>

    </div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    window.KANCIL_LAPORAN = {
        labels: @json($chart['labels']),
        values: @json($chart['values']),
        selectedIndex: {{ $month - 1 }},
        periodLabel: @json($periodLabel),
        url: @json(route('laporan')),
    };
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script src="{{ asset('js/laporan.js') }}"></script>
@endpush
