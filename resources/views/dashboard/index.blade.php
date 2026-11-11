@extends('layouts.app')

@section('title', 'Dashboard')
@section('breadcrumb', 'Dashboard')

@php
    /**
     * @var int    $totalPertanyaan
     * @var int    $terjawabOtomatis
     * @var int    $totalEskalasi
     * @var int    $totalPengaduan
     * @var int    $pengaduanSelesai
     * @var int    $pengaduanDiproses
     * @var int    $pengaduanMenunggu
     * @var array  $chartLabels
     * @var array  $chartBot
     * @var array  $chartPetugas
     * @var \Illuminate\Support\Collection $kategoriPopuler
     * @var \Illuminate\Support\Collection $percakapanTerbaru
     */
@endphp

@section('content')

    {{-- HEADER HALAMAN --}}
    <div class="page-heading-action">
        <div>
            <h1>Dashboard</h1>
            <p>Ringkasan statistik operasional layanan informasi dan pengaduan masyarakat.</p>
        </div>

        <div class="heading-buttons">
            <button type="button" class="btn-outline" onclick="window.print()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                <span>Cetak</span>
            </button>

            @if (auth()->user()?->hasRole('admin', 'pimpinan'))
            <a href="{{ route('laporan') }}" class="btn-primary-dark">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Ekspor PDF</span>
            </a>
            @endif
        </div>
    </div>

    {{-- 4 STAT CARDS --}}
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
            <div class="stat-metric-value">{{ number_format($totalPertanyaan) }}</div>
            <div class="stat-metric-footer trend-up">
                Total pesan masuk dari pengguna
            </div>
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
            <div class="stat-metric-value">{{ number_format($terjawabOtomatis) }}</div>
            <div class="stat-metric-footer">
                @if($totalPertanyaan > 0)
                    {{ round(($terjawabOtomatis / $totalPertanyaan) * 100) }}% rasio respon otomatis bot
                @else
                    Belum ada data pertanyaan
                @endif
            </div>
        </div>

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">Dieskalasi ke Petugas</span>
                <span class="stat-metric-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
                        <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value">{{ number_format($totalEskalasi) }}</div>
            <div class="stat-metric-footer trend-warning">
                @if($totalPertanyaan > 0)
                    {{ round(($totalEskalasi / $totalPertanyaan) * 100) }}% eskalasi ke helpdesk
                @else
                    Belum ada data eskalasi
                @endif
            </div>
        </div>

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">Total Pengaduan</span>
                <span class="stat-metric-icon alert-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value">{{ number_format($totalPengaduan) }}</div>
            <div class="stat-metric-footer">
                {{ $pengaduanSelesai }} selesai • {{ $pengaduanDiproses }} diproses • {{ $pengaduanMenunggu }} menunggu
            </div>
        </div>

    </div>

    {{-- MIDDLE SECTION: 2 COLUMNS (CHART & CATEGORIES) --}}
    <div class="dashboard-middle-grid">

        {{-- LEFT COLUMN: DUAL-LINE CHART --}}
        <section class="content-panel line-chart-panel">
            <div class="panel-heading-row">
                <div>
                    <h3>Statistik Pertanyaan Bulanan</h3>
                    <p>Tren pertanyaan harian bot vs penanganan petugas</p>
                </div>

                <div class="chart-legend-box">
                    <div class="legend-item"><span class="legend-color-box bot-box"></span> Bot Kancil</div>
                    <div class="legend-item"><span class="legend-color-box staff-box"></span> Petugas</div>
                </div>
            </div>

            <div class="chart-wrapper-canvas">
                <canvas id="monthlyTrendChart"></canvas>
            </div>
        </section>

        {{-- RIGHT COLUMN: POPULAR CATEGORIES --}}
        <section class="content-panel categories-rank-panel">
            <div class="panel-heading-row">
                <div>
                    <h3>Kategori Paling Sering Ditanyakan</h3>
                    <p>Persentase distribusi topik konsultasi masyarakat</p>
                </div>
            </div>

            <div class="category-bars-list">
                @forelse ($kategoriPopuler as $i => $kategori)
                    <div class="category-bar-item">
                        <div class="category-bar-label-row">
                            <span>{{ $i + 1 }}. {{ $kategori->name }}</span>
                            <strong>{{ $kategori->persentase }}% ({{ $kategori->jumlah }})</strong>
                        </div>
                        <div class="category-progress-track">
                            <div class="category-progress-fill" style="width:{{ $kategori->persentase }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted">Belum ada data kategori.</p>
                @endforelse
            </div>
        </section>

    </div>

    {{-- BOTTOM SECTION: PERCAKAPAN TERBARU --}}
    <section class="content-panel recent-conversations-panel">

        <div class="panel-heading-row">
            <div>
                <h3>Percakapan Terbaru</h3>
                <p>Log interaksi warga terbaru melalui WhatsApp Gateway</p>
            </div>

            <a href="{{ route('percakapan') }}" class="panel-text-link">
                Lihat Semua Percakapan →
            </a>
        </div>

        <div class="table-responsive">
            <table class="clean-data-table">
                <thead>
                    <tr>
                        <th>No. WhatsApp</th>
                        <th>Topik / Pertanyaan</th>
                        <th>Status</th>
                        <th>Waktu</th>
                        <th style="text-align: right;">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($percakapanTerbaru as $conv)
                        @php
                            $badgeClass = match($conv->pengirim_terakhir ?? '') {
                                'bot', 'ai' => 'bot',
                                'petugas'   => 'agent',
                                default     => 'bot',
                            };
                            $badgeLabel = match($conv->pengirim_terakhir ?? '') {
                                'bot', 'ai' => 'Otomatis Bot',
                                'petugas'   => 'Ditangani Petugas',
                                default     => 'Otomatis Bot',
                            };
                        @endphp
                        <tr>
                            <td class="font-medium">{{ $conv->whatsapp_number ?? ($conv->user_name ?? '-') }}</td>
                            <td>{{ Str::limit($conv->pesan_terakhir ?? '-', 60) }}</td>
                            <td><span class="badge-status-pill {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                            <td class="text-muted">
                                {{ $conv->last_message_at ? \Carbon\Carbon::parse($conv->last_message_at)->diffForHumans() : '-' }}
                            </td>
                            <td style="text-align: right;">
                                <a href="{{ route('percakapan', ['id' => $conv->id]) }}" class="btn-table-action">
                                    Buka Percakapan
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-muted" style="text-align: center; padding: 24px;">
                                Belum ada percakapan terbaru.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

    </section>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const trendCanvas = document.getElementById('monthlyTrendChart');

        if (trendCanvas && typeof Chart !== 'undefined') {
            new Chart(trendCanvas, {
                type: 'line',
                data: {
                    labels: {!! json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!},
                    datasets: [
                        {
                            label: 'Bot Kancil',
                            data: {!! json_encode($chartBot, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!},
                            borderColor: '#0b1a30',
                            backgroundColor: 'transparent',
                            borderWidth: 2.2,
                            tension: 0.35,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        },
                        {
                            label: 'Petugas',
                            data: {!! json_encode($chartPetugas, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!},
                            borderColor: '#718096',
                            borderDash: [5, 5],
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            tension: 0.35,
                            pointRadius: 0,
                            pointHoverRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: '#0b1a30',
                            titleFont: { family: 'Poppins', size: 12 },
                            bodyFont: { family: 'Poppins', size: 12 },
                            padding: 10,
                            cornerRadius: 6
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            ticks: {
                                stepSize: 20,
                                font: { family: 'Poppins', size: 11 },
                                color: '#94a3b8'
                            },
                            grid: {
                                color: '#f1f5f9',
                                drawBorder: false
                            }
                        },
                        x: {
                            ticks: {
                                font: { family: 'Poppins', size: 11 },
                                color: '#94a3b8'
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }
    });
</script>
@endpush
