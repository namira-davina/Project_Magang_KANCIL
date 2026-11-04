
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Masuk Petugas - KANCIL SAPA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/kancil.css') }}">
</head>
<body class="login-body">

    <div class="login-wrapper">

        {{-- BRAND HEADER --}}
        <div class="login-header-brand">
            <div class="login-logo-container">
                <img src="{{ asset('images/logo-imigrasi.png') }}" alt="Logo Imigrasi Cilacap" class="login-official-logo">
            </div>

            <div class="login-agency-tag">
                <span class="yellow-dot">●</span>
                <span>DIREKTORAT JENDERAL IMIGRASI</span>
            </div>

            <h1 class="login-app-title">KANCIL SAPA</h1>
            <p class="login-office-title">Kantor Imigrasi Kelas I TPI Cilacap</p>
        </div>

        {{-- MAIN LOGIN CARD --}}
        <div class="login-card-box">

            <div class="login-card-title-group">
                <h2>Masuk Petugas</h2>
                <p>Silakan masukkan username dan kata sandi akun kedinasan Anda.</p>
            </div>

            @if (session('message'))
                <div class="login-alert-banner">
                    {{ session('message') }}
                </div>
            @endif

            <form action="{{ route('login.submit') }}" method="POST" class="login-form">
                @csrf

                <div class="form-group-login">
                    <div class="label-row">
                        <label for="username">Username</label>
                        <span class="input-hint-right">Format Kedinasan</span>
                    </div>

                    <div class="input-icon-wrapper">
                        <span class="input-icon">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </span>
                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="contoh: agus_tikim"
                            autocomplete="username"
                            required
                        >
                    </div>
                </div>

                <div class="form-group-login">
                    <div class="label-row">
                        <label for="password">Kata Sandi</label>
                    </div>

                    <div class="input-icon-wrapper">
                        <span class="input-icon">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Masukkan kata sandi kedinasan"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            class="password-toggle-btn"
                            id="togglePassword"
                            aria-label="Tampilkan kata sandi"
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="login-meta-row">
                    <label class="remember-checkbox-label">
                        <input type="checkbox" name="remember" value="1">
                        <span>Ingat saya di perangkat ini</span>
                    </label>

                    <a href="#" class="forgot-password-link">
                        Lupa kata sandi?
                    </a>
                </div>

                <button type="submit" class="login-submit-button">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <span>Masuk ke Sistem</span>
                </button>
            </form>

            <div class="restricted-warning-box">
                <span class="warning-shield-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </span>
                <div class="warning-text">
                    <strong>Akses Khusus Terbatas</strong>
                    <p>Sistem Khusus Internal Pegawai. Akses tidak sah dapat dikenakan sanksi sesuai ketentuan hukum yang berlaku.</p>
                </div>
            </div>

        </div>

        {{-- FOOTER COPYRIGHT --}}
        <div class="login-bottom-copyright">
            <span class="dot-accent">●</span>
            <span>KANCIL SAPA v2.4 • Kantor Imigrasi Cilacap © 2025</span>
        </div>

    </div>

    <script src="{{ asset('js/kancil.js') }}"></script>
</body>
</html>
