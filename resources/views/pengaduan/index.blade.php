
@extends('layouts.app')

@section('title', 'Manajemen Pengaduan')
@section('breadcrumb', 'Pengaduan')

@section('content')

    {{-- HEADER HALAMAN & CATATAN JAM LAYANAN --}}
    <div class="pengaduan-header-wrap">
        <div>
            <h1>Manajemen Pengaduan</h1>
            <p>Kelola dan tindak lanjuti laporan serta pengaduan masyarakat.</p>
        </div>

        <div class="service-notice-banner">
            <span class="notice-info-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
            </span>
            <span>Catatan: Pengaduan di luar jam layanan (08.00 – 16.00 WIB) akan diproses pada hari kerja berikutnya.</span>
        </div>
    </div>

    {{-- 4 STAT CARDS PENGADUAN --}}
    <div class="stat-grid-4">

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">TOTAL PENGADUAN</span>
                <span class="stat-metric-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value" id="totalPengaduan">{{ number_format($stats['total'], 0, ',', '.') }}</div>
        </div>

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">MENUNGGU</span>
                <span class="stat-metric-icon stat-icon-amber">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 22h14"></path>
                        <path d="M5 2h14"></path>
                        <path d="M17 22v-4.172a2 2 0 0 0-.586-1.414L12 12l-4.414 4.414A2 2 0 0 0 7 17.828V22"></path>
                        <path d="M7 2v4.172a2 2 0 0 0 .586 1.414L12 12l4.414-4.414A2 2 0 0 0 17 6.172V2"></path>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value" id="totalMenunggu">{{ number_format($stats['menunggu'], 0, ',', '.') }}</div>
        </div>

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">SEDANG DIPROSES</span>
                <span class="stat-metric-icon stat-icon-blue">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value" id="totalDiproses">{{ number_format($stats['diproses'], 0, ',', '.') }}</div>
        </div>

        <div class="stat-metric-card">
            <div class="stat-metric-header">
                <span class="stat-metric-label">SELESAI</span>
                <span class="stat-metric-icon stat-icon-green">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </span>
            </div>
            <div class="stat-metric-value" id="totalSelesai">{{ number_format($stats['selesai'], 0, ',', '.') }}</div>
        </div>

    </div>

    {{-- MASTER-DETAIL LAYOUT: TABLE (LEFT) & DETAIL ACTION PANEL (RIGHT) --}}
    <div class="pengaduan-split-grid">

        {{-- LEFT COLUMN: TABLE WITH FILTER --}}
        <div class="content-panel pengaduan-list-card">

            <div class="pengaduan-toolbar-filter">
                <div class="search-input-box">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input
                        type="text"
                        id="pengaduanSearch"
                        placeholder="Cari nomor tiket, nama..."
                    >
                </div>

                <div class="select-dropdown-box">
                    <select id="pengaduanKategoriFilter">
                        <option value="">Semua Kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}">{{ $category }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="select-dropdown-box">
                    <select id="pengaduanStatusFilter">
                        <option value="">Semua Status</option>
                        <option value="Menunggu">Menunggu</option>
                        <option value="Diproses">Diproses</option>
                        <option value="Selesai">Selesai</option>
                    </select>
                </div>

                <button type="button" class="btn-filter-trigger" id="btnFilterPengaduan">
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
                    <span>Filter</span>
                </button>
            </div>

            <div class="table-responsive">
                <table class="clean-data-table pengaduan-data-table">
                    <thead>
                        <tr>
                            <th>NOMOR TIKET</th>
                            <th>NAMA PEMOHON</th>
                            <th>KATEGORI</th>
                            <th>TANGGAL MASUK</th>
                            <th>STATUS</th>
                            <th style="text-align: right;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody id="pengaduanTableBody"></tbody>
                </table>
            </div>

            <p id="pengaduanEmpty" class="table-empty-notice" style="display: none;">
                Data pengaduan tidak ditemukan.
            </p>

            <div class="table-pagination-footer">
                <span class="pagination-info" id="pengaduanPaginationInfo">Menampilkan 0 dari {{ $stats['total'] }} tiket</span>

                <div class="pagination-buttons" id="pengaduanPagination"></div>
            </div>

        </div>

        {{-- RIGHT COLUMN: TINDAK LANJUT PENGADUAN PANEL --}}
        <div class="content-panel pengaduan-detail-card" id="pengaduanDetailPanel">

            <div class="detail-card-header">
                <div class="detail-card-title">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                    <h3>Tindak Lanjut Pengaduan</h3>
                </div>
                <span class="badge-status-pill waiting" id="detailStatusBadge">-</span>
            </div>

            <div class="detail-meta-list">
                <div class="detail-meta-item">
                    <span class="meta-label">Nomor Tiket</span>
                    <strong class="meta-value" id="detailTiket">-</strong>
                </div>

                <div class="detail-meta-item">
                    <span class="meta-label">Nama Pemohon</span>
                    <strong class="meta-value" id="detailNama">-</strong>
                </div>

                <div class="detail-meta-item">
                    <span class="meta-label">No. WhatsApp</span>
                    <strong class="meta-value text-muted" id="detailNomor">-</strong>
                </div>

                <div class="detail-meta-item">
                    <span class="meta-label">Prioritas</span>
                    <strong class="meta-value" id="detailPrioritas">-</strong>
                </div>

                <div class="detail-complaint-box">
                    <span class="complaint-label">Isi Pengaduan:</span>
                    <p class="complaint-text" id="detailIsi">Belum ada pengaduan yang dipilih.</p>
                    <div class="complaint-attachments" id="detailLampiran" style="display: none;"></div>
                    <p class="complaint-text text-muted" id="detailTanggapanLama" style="display: none;"></p>
                    <a href="#" id="detailPercakapanLink" class="panel-text-link" style="display: none;">Buka percakapan WhatsApp →</a>
                </div>
            </div>

            <div class="detail-form-section" @unless ($canUpdate) style="display: none;" @endunless>
                <div class="form-group-custom">
                    <label for="ubahStatusPengaduan">Ubah Status Tiket</label>
                    <select id="ubahStatusPengaduan" class="form-select-clean">
                        <option value="Menunggu">Menunggu</option>
                        <option value="Diproses">Diproses</option>
                        <option value="Selesai">Selesai</option>
                    </select>
                </div>

                <div class="form-group-custom">
                    <label for="tanggapanPetugas">Form Tanggapan Petugas</label>
                    <textarea
                        id="tanggapanPetugas"
                        rows="4"
                        class="form-textarea-clean"
                        placeholder="Tulis tanggapan resmi..."
                    ></textarea>
                </div>

                <button type="button" class="btn-submit-action full-width" id="simpanStatusPengaduan">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    <span>Simpan & Kirim Tanggapan</span>
                </button>
            </div>

        </div>

    </div>

    {{-- MODAL FALLBACK UNTUK RESPONSIVE MOBILE --}}
    <div class="pengaduan-modal" id="pengaduanModal" style="display: none;">
        <button type="button" id="tutupPengaduanModal" style="display: none;"></button>
        <button type="button" id="batalDetailPengaduan" style="display: none;"></button>
    </div>

@endsection

@push('scripts')
<script>
    window.KANCIL_PENGADUAN = {
        items: @json($complaints),
        canUpdate: @json($canUpdate),
        perPage: 10,
    };
</script>
<script src="{{ asset('js/pengaduan.js') }}"></script>
@endpush

