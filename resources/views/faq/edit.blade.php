@extends('layouts.app')

@section('title', 'Edit Data FAQ')
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

    {{-- HEADER HALAMAN & STATUS BADGE --}}
    <div class="page-heading-action">
        <div>
            <div class="faq-title-meta-row">
                <h1>Edit Data FAQ #{{ $faq->id }}</h1>
                @if ($faq->is_active && $faq->verification_status === 'terverifikasi')
                    <span class="status-dot-badge active">● Aktif Publikasi</span>
                @else
                    <span class="status-dot-badge draft">● {{ ucfirst($faq->verification_status ?? 'Draf') }}</span>
                @endif
                <span class="badge-id-ref">ID: FAQ-CLP-{{ str_pad($faq->id, 3, '0', STR_PAD_LEFT) }}</span>
            </div>
            <p>Perbarui informasi pertanyaan dan jawaban otomatis bot WhatsApp layanan keimigrasian KANCIL SAPA.</p>
            <div class="faq-last-updated">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Terakhir diperbarui pada {{ $faq->updated_at ? \Carbon\Carbon::parse($faq->updated_at)->translatedFormat('d M Y, H:i') . ' WIB' : '-' }}</span>
            </div>
        </div>

        <a href="{{ route('faq.index') }}" class="btn-outline">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            <span>Kembali ke Daftar</span>
        </a>
    </div>

    {{-- FORMULIR EDIT FAQ --}}
    <div class="content-panel form-panel-card">

        <div class="form-card-header-between">
            <div class="header-with-edit-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <h3>Formulir Edit FAQ</h3>
            </div>
            <span class="badge-id-ref">ID: FAQ-CLP-{{ str_pad($faq->id, 3, '0', STR_PAD_LEFT) }}</span>
        </div>

        {{-- Form hapus diletakkan di LUAR form edit, dihubungkan via atribut form= --}}
        <form
            id="deleteFaqForm"
            action="{{ route('faq.destroy', $faq->id) }}"
            method="POST"
            onsubmit="return confirm('Hapus FAQ ini secara permanen?')"
        >
            @csrf
            @method('DELETE')
        </form>

        <form action="{{ route('faq.update', $faq->id) }}" method="POST" class="clean-form" id="formEditFaq">
            @csrf
            @method('PUT')

            {{-- ROW 1: KATEGORI & STATUS --}}
            <div class="form-row-2col">
                <div class="form-group-clean">
                    <label for="kategoriLayanan">Kategori Layanan <span class="required-star">*</span></label>
                    <select id="kategoriLayanan" name="kategori" class="form-select-clean" required>
                        <option value="">-- Pilih Kategori Layanan --</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" {{ old('kategori', $faq->category_id) == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group-clean">
                    <label for="statusPublikasi">Status Publikasi <span class="required-star">*</span></label>
                    <select id="statusPublikasi" name="status" class="form-select-clean" required>
                        <option value="draft" {{ old('status', $faq->verification_status) === 'draft' ? 'selected' : '' }}>Draf (Belum diverifikasi)</option>
                        <option value="perlu_verifikasi" {{ old('status', $faq->verification_status) === 'perlu_verifikasi' ? 'selected' : '' }}>Perlu Verifikasi</option>
                        <option value="terverifikasi" {{ old('status', $faq->verification_status) === 'terverifikasi' ? 'selected' : '' }}>Terverifikasi</option>
                    </select>
                </div>
            </div>

            {{-- PERTANYAAN --}}
            <div class="form-group-clean">
                <label for="pertanyaan">Pertanyaan <span class="required-star">*</span></label>
                <input
                    type="text"
                    id="pertanyaan"
                    name="pertanyaan"
                    class="form-control-clean"
                    value="{{ old('pertanyaan', $faq->question) }}"
                    required
                >
            </div>

            {{-- KEYWORDS --}}
            <div class="form-group-clean">
                <label for="kata_kunci">Kata Kunci Pemicu <span class="required-star">*</span></label>
                <input
                    type="text"
                    id="kata_kunci"
                    name="kata_kunci"
                    class="form-control-clean"
                    value="{{ old('kata_kunci', $keywords) }}"
                    required
                >
                <span class="field-hint">Pisahkan kata kunci dengan tanda koma.</span>
            </div>

            {{-- ISI JAWABAN DENGAN COUNTER --}}
            <div class="form-group-clean">
                <div class="label-with-right-hint">
                    <label for="jawaban">Isi Jawaban / Respon Bot <span class="required-star">*</span></label>
                    <span class="field-hint-right" id="charCounter">{{ strlen(old('jawaban', $faq->answer)) }} Karakter</span>
                </div>
                <textarea
                    id="jawaban"
                    name="jawaban"
                    rows="6"
                    class="form-textarea-clean"
                    maxlength="1000"
                    required
                >{{ old('jawaban', $faq->answer) }}</textarea>
            </div>

            {{-- TAUTAN RUJUKAN OPSIONAL --}}
            <div class="form-group-clean">
                <div class="label-with-right-hint">
                    <label for="tautanRujukan">Tautan Rujukan / Dokumen (Opsional)</label>
                    <span class="field-hint-right">Pendaftaran antrean wajib via aplikasi M-Paspor.</span>
                </div>
                <input
                    type="url"
                    id="tautanRujukan"
                    name="tautan"
                    class="form-control-clean"
                    value="{{ old('tautan', $faq->source_url) }}"
                >
            </div>

            {{-- FORM ACTION FOOTER: HAPUS DI KIRI, BATAL & SIMPAN DI KANAN --}}
            <div class="form-action-footer space-between">
                {{-- Tombol hapus dihubungkan ke form hapus terpisah via form="deleteFaqForm" --}}
                <button type="submit" form="deleteFaqForm" class="btn-danger-outline">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span>Hapus FAQ Ini</span>
                </button>

                <div class="action-buttons-group">
                    <a href="{{ route('faq.index') }}" class="btn-modal-cancel">
                        Batal
                    </a>

                    <button type="submit" class="btn-primary-dark">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </div>

        </form>

    </div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const textarea = document.getElementById('jawaban');
        const counter = document.getElementById('charCounter');

        if (textarea && counter) {
            textarea.addEventListener('input', function () {
                const len = this.value.length;
                counter.textContent = `${len} Karakter`;
            });
        }
    });
</script>
@endpush
