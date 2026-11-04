@extends('layouts.app')

@section('title', 'Tambah Data FAQ')
@section('breadcrumb', 'Database FAQ')

@section('content')

    @if ($errors->any())
        <div class="content-panel" style="padding: 12px 18px; margin-bottom: 16px; border-left: 4px solid #ef4444;">
            <strong>Periksa kembali data FAQ:</strong>
            <ul style="margin: 6px 0 0 16px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- BREADCRUMB INTERNAL KHUSUS DETAIL FAQ --}}
    <div class="faq-detail-breadcrumb">
        <span>KANCIL SAPA</span>
        <span class="sep">&gt;</span>
        <span>Sistem Administrasi</span>
        <span class="sep">&gt;</span>
        <span class="active">Database FAQ</span>
    </div>

    {{-- HEADER HALAMAN & ID REF --}}
    <div class="page-heading-action">
        <div>
            <div class="faq-sub-badge">
                <span class="badge-text-primary">Modul FAQ Bot</span>
                <span class="badge-dot-sep">•</span>
                <span class="badge-id-ref">ID Ref: FAQ-AUTO-NEW</span>
            </div>
            <h1>Tambah Data FAQ</h1>
            <p>Pertanyaan dan balasan otomatis bot WhatsApp KANCIL SAPA Imigrasi Cilacap.</p>
        </div>

        <a href="{{ route('faq.index') }}" class="btn-outline">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Kembali ke Daftar</span>
        </a>
    </div>

    {{-- FORMULIR PENGETAHUAN FAQ --}}
    <div class="content-panel form-panel-card">

        <div class="form-card-header-between">
            <div>
                <h3>Formulir Pengetahuan FAQ</h3>
                <p>Lengkapi data di bawah ini untuk sinkronisasi otomatis bot AI.</p>
            </div>
            <span class="required-notice-star">* Wajib diisi</span>
        </div>

        <form action="{{ route('faq.store') }}" method="POST" class="clean-form" id="formTambahFaq">
            @csrf

            {{-- 2 KOLOM KATEGORI & STATUS --}}
            <div class="form-row-2col">
                <div class="form-group-clean">
                    <label for="kategoriLayanan">Kategori Layanan <span class="required-star">*</span></label>
                    <select id="kategoriLayanan" name="kategori" class="form-select-clean" required>
                        <option value="">-- Pilih Kategori Layanan --</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ old('kategori') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group-clean">
                    <label for="statusPublikasi">Status Publikasi <span class="required-star">*</span></label>
                    <select id="statusPublikasi" name="status" class="form-select-clean" required>
                        <option value="draft" selected>Draf (Belum diverifikasi)</option>
                        <option value="perlu_verifikasi">Perlu Verifikasi</option>
                        <option value="terverifikasi">Terverifikasi</option>
                    </select>
                </div>
            </div>

            {{-- PERTANYAAN --}}
            <div class="form-group-clean">
                <label for="inputPertanyaan">Pertanyaan <span class="required-star">*</span></label>
                <input
                    type="text"
                    id="inputPertanyaan"
                    name="pertanyaan"
                    class="form-control-clean"
                    value="{{ old('pertanyaan') }}"
                    placeholder="Contoh: Apa saja syarat permohonan paspor baru?"
                    required
                >
            </div>

            {{-- KEYWORDS DENGAN HINT --}}
            <div class="form-group-clean">
                <div class="label-with-right-hint">
                    <label for="inputKeywords">Kata Kunci Pemicu (Keywords) <span class="required-star">*</span></label>
                    <span class="field-hint-right">Pisahkan dengan tanda koma (,)</span>
                </div>
                <input
                    type="text"
                    id="inputKeywords"
                    name="kata_kunci"
                    class="form-control-clean"
                    value="{{ old('kata_kunci') }}"
                    placeholder="Contoh: paspor baru, syarat paspor, bikin paspor, permohonan paspor"
                    required
                >
            </div>

            {{-- JAWABAN DENGAN COUNTER --}}
            <div class="form-group-clean">
                <div class="label-with-right-hint">
                    <label for="inputJawaban">Jawaban / Respon Bot WhatsApp <span class="required-star">*</span></label>
                    <span class="field-hint-right" id="charCounter">0 / 1000 Karakter</span>
                </div>
                <textarea
                    id="inputJawaban"
                    name="jawaban"
                    rows="5"
                    class="form-textarea-clean"
                    maxlength="1000"
                    placeholder="Tuliskan jawaban ringkas, jelas, dan santun yang dikirimkan otomatis oleh bot WhatsApp..."
                    required
                >{{ old('jawaban') }}</textarea>
            </div>

            {{-- TAUTAN RUJUKAN OPSIONAL --}}
            <div class="form-group-clean">
                <label for="tautanRujukan">Tautan Rujukan / Dokumen (Opsional)</label>
                <input
                    type="url"
                    id="tautanRujukan"
                    name="tautan"
                    class="form-control-clean"
                    value="{{ old('tautan') }}"
                    placeholder="https://contoh.go.id/informasi"
                >
            </div>

            {{-- TOMBOL AKSI KANAN BAWAH --}}
            <div class="form-action-footer right-align">
                <a href="{{ route('faq.index') }}" class="btn-modal-cancel">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    <span>Batal</span>
                </a>

                <button type="submit" class="btn-primary-dark">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    <span>Simpan FAQ</span>
                </button>
            </div>

        </form>

    </div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const textarea = document.getElementById('inputJawaban');
        const counter = document.getElementById('charCounter');

        if (textarea && counter) {
            textarea.addEventListener('input', function () {
                const len = this.value.length;
                counter.textContent = `${len} / 1000 Karakter`;
            });
        }
    });
</script>
@endpush
