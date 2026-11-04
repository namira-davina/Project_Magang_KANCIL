@extends('layouts.app')

@section('title', 'Edit Data FAQ')
@section('breadcrumb', 'Database FAQ')

@section('content')

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
                <h1>Edit Data FAQ #{{ $id ?? 1 }}</h1>
                <span class="status-dot-badge active">● Aktif Publikasi</span>
                <span class="badge-id-ref">ID: FAQ-CLP-001</span>
            </div>
            <p>Perbarui informasi pertanyaan dan jawaban otomatis bot WhatsApp layanan keimigrasian KANCIL SAPA.</p>
            <div class="faq-last-updated">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Terakhir diperbarui oleh <strong>Agus Setiawan, S.IP.</strong> pada 28 Okt 2025, 09:15 WIB</span>
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
            <span class="badge-id-ref">ID: FAQ-CLP-001</span>
        </div>

        <form action="{{ route('faq.index') }}" method="GET" class="clean-form" id="formEditFaq">

            {{-- ROW 1: KATEGORI & STATUS RADIO --}}
            <div class="form-row-2col">
                <div class="form-group-clean">
                    <label for="kategoriLayanan">Kategori Layanan <span class="required-star">*</span></label>
                    <select id="kategoriLayanan" name="kategori" class="form-select-clean" required>
                        <option value="Paspor" selected>Layanan Paspor Republik Indonesia (WNI)</option>
                        <option value="Visa">Visa & Izin Tinggal</option>
                        <option value="Biaya & Jadwal">Biaya dan Jadwal Pelayanan</option>
                        <option value="Pengaduan">Pengaduan Masyarakat</option>
                    </select>
                </div>

                <div class="form-group-clean">
                    <label>Status Publikasi <span class="required-star">*</span></label>
                    <div class="radio-group-clean">
                        <label class="radio-label">
                            <input type="radio" name="status" value="Aktif" checked>
                            <span>Aktif</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="status" value="Draf">
                            <span>Draf / Nonaktif</span>
                        </label>
                    </div>
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
                    value="Apa syarat pembuatan paspor baru untuk WNI?"
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
                    value="syarat paspor, paspor baru, bikin paspor, dokumen paspor wni"
                    required
                >
                <span class="field-hint">Pisahkan kata kunci dengan tanda koma.</span>
            </div>

            {{-- ISI JAWABAN DENGAN COUNTER --}}
            <div class="form-group-clean">
                <div class="label-with-right-hint">
                    <label for="jawaban">Isi Jawaban / Respon Bot <span class="required-star">*</span></label>
                    <span class="field-hint-right" id="charCounter">235 Karakter</span>
                </div>
                <textarea
                    id="jawaban"
                    name="jawaban"
                    rows="6"
                    class="form-textarea-clean"
                    required
                >Halo Sahabat Mido Cilacap! 👋

Persyaratan permohonan paspor baru RI bagi WNI dewasa:
1. e-KTP asli & fotokopi
2. Kartu Keluarga asli & fotokopi
3. Akta Kelahiran / Ijazah / Buku Nikah asli & fotokopi
4. Surat penetapan ganti nama (jika ada)</textarea>
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
                    value="https://cilacap.imigrasi.go.id/layanan-paspor"
                >
            </div>

            {{-- FORM ACTION FOOTER: HAPUS DI KIRI, BATAL & SIMPAN DI KANAN --}}
            <div class="form-action-footer space-between">
                <button type="button" class="btn-danger-outline" onclick="if(confirm('Hapus FAQ ini secara permanen?')) window.location.href='{{ route('faq.index') }}';">
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

        const form = document.getElementById('formEditFaq');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                alert('Perubahan data FAQ berhasil disimpan.');
                window.location.href = "{{ route('faq.index') }}";
            });
        }
    });
</script>
@endpush