@extends('layouts.app')

@section('title', 'Pengaturan')
@section('breadcrumb', 'Pengaturan')

@section('content')

    {{-- HEADER HALAMAN --}}
    <div class="page-heading-action">
        <div class="heading-with-icon">
            <span class="heading-title-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
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
            </span>
            <div>
                <h1>Pengaturan</h1>
                <p>Kelola informasi akun pegawai.</p>
            </div>
        </div>
    </div>

    {{-- DUA KARTU PENGATURAN TERTUMPUK SESUAI DESAIN --}}
    <div class="pengaturan-vertical-stack">

        {{-- CARD 1: PROFIL PEGAWAI --}}
        <div class="content-panel settings-card">
            <div class="settings-card-header">
                <span class="settings-section-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </span>
                <h3>Profil Pegawai</h3>
            </div>

            <div class="settings-card-body">
                <div class="form-group-clean">
                    <label for="namaPetugas">Nama Lengkap</label>
                    <input
                        type="text"
                        id="namaPetugas"
                        class="form-control-clean"
                        value="Agus Setiawan, S.IP."
                    >
                </div>

                <div class="form-group-clean">
                    <label for="emailPetugas">Email</label>
                    <input
                        type="email"
                        id="emailPetugas"
                        class="form-control-clean"
                        value="agus.setiawan@kemenkumham.go.id"
                    >
                </div>

                <div class="form-group-clean">
                    <label for="usernamePetugas">Username</label>
                    <input
                        type="text"
                        id="usernamePetugas"
                        class="form-control-clean"
                        value="agus_tikim"
                    >
                </div>

                <div class="settings-action-row">
                    <button type="button" class="btn-primary-dark" id="btnSimpanAkun">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span>Simpan Perubahan</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- CARD 2: UBAH PASSWORD --}}
        <div class="content-panel settings-card">
            <div class="settings-card-header">
                <span class="settings-section-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
                    </svg>
                </span>
                <h3>Ubah Password</h3>
            </div>

            <div class="settings-card-body">
                <div class="form-group-clean">
                    <label for="passwordLama">Password Lama</label>
                    <input
                        type="password"
                        id="passwordLama"
                        class="form-control-clean"
                        placeholder="Masukkan password lama"
                    >
                </div>

                <div class="form-group-clean">
                    <label for="passwordBaru">Password Baru</label>
                    <input
                        type="password"
                        id="passwordBaru"
                        class="form-control-clean"
                        placeholder="Masukkan password baru"
                    >
                </div>

                <div class="form-group-clean">
                    <label for="konfirmasiPassword">Konfirmasi Password Baru</label>
                    <input
                        type="password"
                        id="konfirmasiPassword"
                        class="form-control-clean"
                        placeholder="Ulangi password baru"
                    >
                </div>

                <div class="settings-action-row">
                    <button type="button" class="btn-primary-dark" id="btnUbahPassword">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Ubah Password</span>
                    </button>
                </div>
            </div>
        </div>

    </div>

@endsection

@push('scripts')
<script src="{{ asset('js/pengaturan.js') }}"></script>
@endpush