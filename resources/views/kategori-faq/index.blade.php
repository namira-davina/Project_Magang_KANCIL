
@extends('layouts.app')

@section('title', 'Kategori FAQ')
@section('breadcrumb', 'Kategori FAQ')

@section('content')

    {{-- HEADER HALAMAN --}}
    <div class="page-heading-action">
        <div>
            <h1>Kategori FAQ</h1>
            <p>Kelola kategori pertanyaan layanan keimigrasian.</p>
        </div>

        <a href="{{ route('kategori-faq.create') }}" class="btn-primary-dark">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Tambah Kategori</span>
        </a>
    </div>

    {{-- KONTEN UTAMA KATEGORI --}}
    <div class="content-panel kategori-panel-card">

        <div class="kategori-table-toolbar">
            <div class="search-input-box compact">
                <input
                    type="text"
                    id="kategoriSearchInput"
                    placeholder="Cari kategori..."
                >
                <button type="button" class="search-submit-icon" aria-label="Cari">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
            </div>

            <div class="kategori-counter-info">
                Total Kategori: <strong id="kategoriTotalCount">6 data</strong>
            </div>
        </div>

        <div class="table-responsive">
            <table class="clean-data-table kategori-data-table">
                <thead>
                    <tr>
                        <th width="70">NO</th>
                        <th>NAMA KATEGORI</th>
                        <th width="200">JUMLAH FAQ</th>
                        <th width="160" style="text-align: right;">AKSI</th>
                    </tr>
                </thead>
                <tbody id="kategoriTableBody">
                    <tr data-id="1" data-nama="Paspor" data-faq="15 FAQ">
                        <td>1</td>
                        <td class="font-medium">Paspor</td>
                        <td class="text-muted">15 FAQ</td>
                        <td style="text-align: right;">
                            <button type="button" class="btn-link-action btn-edit-kategori" data-id="1" data-nama="Paspor">Edit</button>
                            <span class="action-divider">|</span>
                            <button type="button" class="btn-link-action btn-delete-kategori" data-id="1">Hapus</button>
                        </td>
                    </tr>
                    <tr data-id="2" data-nama="Visa" data-faq="8 FAQ">
                        <td>2</td>
                        <td class="font-medium">Visa</td>
                        <td class="text-muted">8 FAQ</td>
                        <td style="text-align: right;">
                            <button type="button" class="btn-link-action btn-edit-kategori" data-id="2" data-nama="Visa">Edit</button>
                            <span class="action-divider">|</span>
                            <button type="button" class="btn-link-action btn-delete-kategori" data-id="2">Hapus</button>
                        </td>
                    </tr>
                    <tr data-id="3" data-nama="Izin Tinggal" data-faq="6 FAQ">
                        <td>3</td>
                        <td class="font-medium">Izin Tinggal</td>
                        <td class="text-muted">6 FAQ</td>
                        <td style="text-align: right;">
                            <button type="button" class="btn-link-action btn-edit-kategori" data-id="3" data-nama="Izin Tinggal">Edit</button>
                            <span class="action-divider">|</span>
                            <button type="button" class="btn-link-action btn-delete-kategori" data-id="3">Hapus</button>
                        </td>
                    </tr>
                    <tr data-id="4" data-nama="Biaya dan Pembayaran" data-faq="5 FAQ">
                        <td>4</td>
                        <td class="font-medium">Biaya dan Pembayaran</td>
                        <td class="text-muted">5 FAQ</td>
                        <td style="text-align: right;">
                            <button type="button" class="btn-link-action btn-edit-kategori" data-id="4" data-nama="Biaya dan Pembayaran">Edit</button>
                            <span class="action-divider">|</span>
                            <button type="button" class="btn-link-action btn-delete-kategori" data-id="4">Hapus</button>
                        </td>
                    </tr>
                    <tr data-id="5" data-nama="Jadwal Pelayanan" data-faq="4 FAQ">
                        <td>5</td>
                        <td class="font-medium">Jadwal Pelayanan</td>
                        <td class="text-muted">4 FAQ</td>
                        <td style="text-align: right;">
                            <button type="button" class="btn-link-action btn-edit-kategori" data-id="5" data-nama="Jadwal Pelayanan">Edit</button>
                            <span class="action-divider">|</span>
                            <button type="button" class="btn-link-action btn-delete-kategori" data-id="5">Hapus</button>
                        </td>
                    </tr>
                    <tr data-id="6" data-nama="Pengaduan" data-faq="3 FAQ">
                        <td>6</td>
                        <td class="font-medium">Pengaduan</td>
                        <td class="text-muted">3 FAQ</td>
                        <td style="text-align: right;">
                            <button type="button" class="btn-link-action btn-edit-kategori" data-id="6" data-nama="Pengaduan">Edit</button>
                            <span class="action-divider">|</span>
                            <button type="button" class="btn-link-action btn-delete-kategori" data-id="6">Hapus</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="table-pagination-footer">
            <span class="pagination-info" id="kategoriPaginationInfo">Menampilkan 1 - 6 dari 6 entri</span>

            <div class="pagination-buttons">
                <button type="button" class="btn-page-nav" disabled>&laquo;</button>
                <button type="button" class="btn-page-num active">1</button>
                <button type="button" class="btn-page-nav" disabled>&raquo;</button>
            </div>
        </div>

    </div>

    {{-- MODAL EDIT KATEGORI (HALAMAN 8) --}}
    <div class="clean-modal-backdrop" id="modalEditKategori" style="display: none;">
        <div class="clean-modal-card">
            <div class="clean-modal-header">
                <div>
                    <h3 class="modal-title">Edit Kategori</h3>
                    <p class="modal-subtitle">Ubah informasi nama kategori pertanyaan FAQ.</p>
                </div>
                <button type="button" class="modal-close-icon-btn" id="btnTutupModalKategori">&times;</button>
            </div>

            <form id="formEditKategori">
                <input type="hidden" id="editKategoriId">

                <div class="modal-form-body">
                    <div class="form-group-clean">
                        <label for="inputNamaKategori">Nama Kategori <span class="required-star">*</span></label>
                        <input
                            type="text"
                            id="inputNamaKategori"
                            class="form-control-clean"
                            required
                        >
                        <p class="form-helper-note">
                            <span class="helper-info-icon">ⓘ</span>
                            Perubahan nama kategori akan diperbarui pada seluruh FAQ terkait.
                        </p>
                    </div>
                </div>

                <div class="clean-modal-footer">
                    <button type="button" class="btn-modal-cancel" id="btnBatalModalKategori">Batal</button>
                    <button type="submit" class="btn-primary-dark">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
<script src="{{ asset('js/kategori-faq.js') }}"></script>
@endpush
