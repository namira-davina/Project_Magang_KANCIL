
@extends('layouts.app')

@section('title', 'Tambah Kategori FAQ')
@section('breadcrumb', 'Kategori FAQ')

@section('content')

    <div class="page-heading-action">
        <div>
            <h1>Tambah Kategori FAQ</h1>
            <p>Tambahkan kategori baru untuk mengelompokkan pertanyaan FAQ layanan keimigrasian.</p>
        </div>

        <a href="{{ route('kategori-faq.index') }}" class="btn-outline">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Kembali ke Daftar</span>
        </a>
    </div>

    <div class="content-panel form-panel-card">
        @if ($errors->any())
            <div class="content-panel" style="padding: 15px; margin-bottom: 15px;">
                <strong>Periksa kembali data kategori:</strong>

                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="form-card-header">
            <h3>Informasi Kategori</h3>
            <p>Lengkapi formulir di bawah ini untuk menambahkan kategori baru.</p>
        </div>

        <form
            id="formTambahKategori"
            class="clean-form"
            action="{{ route('kategori-faq.store') }}"
            method="POST"
        >
            @csrf

            <div class="form-group-clean">
                <label for="namaKategori">Nama Kategori <span class="required-star">*</span></label>
                <input
                    type="text"
                    id="namaKategori"
                    name="namaKategori"
                    class="form-control-clean"
                    placeholder="Contoh: Paspor"
                    value="{{ old('namaKategori') }}"
                    required
                >
                <span class="field-hint">Masukkan nama kategori yang singkat dan mudah dikenali masyarakat.</span>
            </div>

            <div class="form-group-clean">
                <label for="deskripsiKategori">Deskripsi Layanan</label>
                <textarea
                    id="deskripsiKategori"
                    name="deskripsiKategori"
                    rows="4"
                    class="form-textarea-clean"
                    placeholder="Masukkan deskripsi cakupan pertanyaan dalam kategori ini..."
                >{{ old('deskripsiKategori') }}</textarea>
                <span class="field-hint">Jelaskan jenis pertanyaan atau layanan yang termasuk dalam kategori ini.</span>
            </div>

            <div class="form-group-clean">
                <label for="statusKategori">Status Kategori <span class="required-star">*</span></label>
                <select
                    id="statusKategori"
                    name="statusKategori"
                    class="form-select-clean"
                    required
                >
                    <option value="Aktif" {{ old('statusKategori', 'Aktif') == 'Aktif' ? 'selected' : '' }}>
                        Aktif
                    </option>

                    <option value="Nonaktif" {{ old('statusKategori') == 'Nonaktif' ? 'selected' : '' }}>
                        Nonaktif
                    </option>
                </select>
            </div>

            <div class="form-action-footer">
                <a href="{{ route('kategori-faq.index') }}" class="btn-modal-cancel">
                    Batal
                </a>

                <button type="submit" class="btn-primary-dark">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <span>Simpan Kategori</span>
                </button>
            </div>

        </form>

    </div>

@endsection

@push('scripts')
<script src="{{ asset('js/kategori-faq-create.js') }}"></script>
@endpush
