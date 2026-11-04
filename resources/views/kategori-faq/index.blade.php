
@extends('layouts.app')

@section('title', 'Kategori FAQ')
@section('breadcrumb', 'Kategori FAQ')

@section('content')

    @if (session('success'))
        <div class="content-panel" style="padding: 12px 18px; margin-bottom: 16px; border-left: 4px solid #22c55e;">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="content-panel" style="padding: 12px 18px; margin-bottom: 16px; border-left: 4px solid #ef4444;">
            <strong>Periksa kembali data:</strong>
            <ul style="margin: 6px 0 0 16px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

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
                    @forelse ($categories as $index => $category)
                        <tr
                            data-id="{{ $category->id }}"
                            data-nama="{{ strtolower($category->name) }}"
                            data-faq="{{ $category->total_faq }}"
                        >
                            <td>{{ $index + 1 }}</td>

                            <td class="font-medium">
                                {{ $category->name }}
                            </td>

                            <td class="text-muted">
                                {{ $category->total_faq }} FAQ
                            </td>

                            <td style="text-align: right;">
                                <button
                                    type="button"
                                    class="btn-link-action btn-edit-kategori"
                                    data-id="{{ $category->id }}"
                                    data-nama="{{ $category->name }}"
                                >
                                    Edit
                                </button>

                                <span class="action-divider">|</span>

                                <form
                                    action="{{ route('kategori-faq.destroy', $category->id) }}"
                                    method="POST"
                                    style="display: inline;"
                                    onsubmit="return confirm('Yakin ingin menghapus kategori ini?')"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button type="submit" class="btn-link-action">
                                        Hapus
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 30px;">
                                Belum ada kategori FAQ.
                            </td>
                        </tr>
                    @endforelse
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

            <form
                id="formEditKategori"
                method="POST"
                action=""
            >
                @csrf
                @method('PUT')

                <input type="hidden" id="editKategoriId" name="id">

                <div class="modal-form-body">
                    <div class="form-group-clean">
                        <label for="inputNamaKategori">Nama Kategori <span class="required-star">*</span></label>
                        <input
                            type="text"
                            id="inputNamaKategori"
                            name="namaKategori"
                            class="form-control-clean"
                            maxlength="100"
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
