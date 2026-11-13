@extends('layouts.app')

@section('title', 'Antrean Petugas')
@section('breadcrumb', 'Antrean Petugas')

@section('content')

    <div class="page-heading-action">
        <div>
            <h1>Antrean Tiket Petugas</h1>
            <p>Pemohon yang minta dihubungkan ke petugas, dilayani sesuai urutan masuk (yang lebih dulu bertanya dilayani lebih dulu).</p>
        </div>

        @if ($canAct)
        <div class="heading-buttons">
            <button type="button" class="btn-primary-dark" id="btnAmbilBerikutnya">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Ambil Antrean Berikutnya</span>
            </button>
        </div>
        @endif
    </div>

    <div class="stat-grid-4">
        <div class="stat-metric-card">
            <div class="stat-metric-header"><span class="stat-metric-label">MENUNGGU</span></div>
            <div class="stat-metric-value" id="statMenunggu">{{ $stats['menunggu'] }}</div>
            <div class="stat-metric-footer" id="statTertua">{{ $stats['tertua_menit'] !== null ? 'Terlama menunggu '.$stats['tertua_menit'].' menit' : 'Antrean kosong' }}</div>
        </div>
        <div class="stat-metric-card">
            <div class="stat-metric-header"><span class="stat-metric-label">SEDANG DITANGANI</span></div>
            <div class="stat-metric-value" id="statDitangani">{{ $stats['ditangani'] }}</div>
        </div>
        <div class="stat-metric-card">
            <div class="stat-metric-header"><span class="stat-metric-label">SELESAI HARI INI</span></div>
            <div class="stat-metric-value" id="statSelesai">{{ $stats['selesai_hari_ini'] }}</div>
        </div>
        <div class="stat-metric-card">
            <div class="stat-metric-header"><span class="stat-metric-label">RATA-RATA TUNGGU HARI INI</span></div>
            <div class="stat-metric-value" id="statRataTunggu">{{ $stats['rata_tunggu_menit'] !== null ? $stats['rata_tunggu_menit'].' mnt' : '-' }}</div>
        </div>
    </div>

    <div class="content-panel">
        <div class="panel-heading-row">
            <div>
                <h3>Antrean Aktif</h3>
                <p>Urut dari yang paling awal masuk. Tiket menunggu tampil di atas, lalu tiket yang sedang ditangani.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="clean-data-table">
                <thead>
                    <tr>
                        <th width="70">POSISI</th>
                        <th>TIKET</th>
                        <th>PEMOHON</th>
                        <th>TOPIK / PERTANYAAN</th>
                        <th>MASUK</th>
                        <th>MENUNGGU</th>
                        <th>STATUS</th>
                        <th style="text-align: right;">AKSI</th>
                    </tr>
                </thead>
                <tbody id="antreanTableBody"></tbody>
            </table>
        </div>

        <p id="antreanEmpty" class="table-empty-notice" style="display: none;">Tidak ada pemohon dalam antrean.</p>
    </div>

    <div class="content-panel" style="margin-top: 20px;">
        <div class="panel-heading-row">
            <div>
                <h3>Riwayat Hari Ini</h3>
                <p>Tiket yang sudah selesai, dibatalkan pemohon, atau kedaluwarsa hari ini.</p>
            </div>
        </div>

        <div class="table-responsive">
            <table class="clean-data-table">
                <thead>
                    <tr>
                        <th>TIKET</th>
                        <th>PEMOHON</th>
                        <th>TOPIK</th>
                        <th>MASUK</th>
                        <th>DITUTUP</th>
                        <th>STATUS</th>
                        <th>PETUGAS</th>
                        <th>KETERANGAN</th>
                    </tr>
                </thead>
                <tbody id="riwayatTableBody"></tbody>
            </table>
        </div>

        <p id="riwayatEmpty" class="table-empty-notice" style="display: none;">Belum ada tiket yang ditutup hari ini.</p>
    </div>

    @if ($canAct)
    <dialog id="dialogPengaduan" class="ticket-dialog">
        <form id="formPengaduan">
            <h3>Jadikan Pengaduan Resmi</h3>
            <p class="text-secondary-cell">
                Tiket <strong id="dialogPengaduanTiket">-</strong> ditutup dan pemohon menerima nomor pengaduan (ADU).
                Pengaduan lalu ditindaklanjuti Admin/Pimpinan di halaman Pengaduan.
            </p>

            <div class="form-group-custom">
                <label for="dialogKategori">Kategori</label>
                <select id="dialogKategori" class="form-select-clean" required>
                    @foreach (\App\Services\Bot\BotScript::COMPLAINT_CATEGORIES as $key => [$categoryName, $categoryPriority])
                        <option value="{{ $key }}">{{ $categoryName }} (prioritas {{ $categoryPriority }})</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group-custom">
                <label for="dialogIsi">Isi pengaduan (kronologi singkat)</label>
                <textarea id="dialogIsi" rows="5" class="form-textarea-clean" minlength="10" maxlength="5000" required></textarea>
            </div>

            <div class="ticket-dialog-actions">
                <button type="button" class="btn-outline" id="dialogBatal">Batal</button>
                <button type="submit" class="btn-primary-dark" id="dialogSimpan">Buat Pengaduan</button>
            </div>
        </form>
    </dialog>
    @endif

@endsection

@push('scripts')
<script>
    window.KANCIL_ANTREAN = {
        tickets: @json($tickets),
        history: @json($history),
        stats: @json($stats),
        canAct: @json($canAct),
        urls: {
            data: @json(route('antrean.data')),
            ambilBerikutnya: @json(route('antrean.ambil-berikutnya')),
        },
        pollMs: 8000,
    };
</script>
<script src="{{ asset('js/antrean.js') }}"></script>
@endpush
