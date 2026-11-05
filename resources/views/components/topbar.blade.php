
<header class="topbar">

    <div class="topbar-left">
        <div class="breadcrumb">
            <strong>@yield('breadcrumb', 'Dashboard')</strong>
            <span class="breadcrumb-separator">/</span>
            <span>Kantor Imigrasi Kelas I TPI Cilacap</span>
        </div>
    </div>

    <div class="topbar-right">
        <div class="profile-info">
            <strong>{{ Auth::user()->name ?? Auth::user()->username }}</strong>
            <span>{{ Auth::user()->jabatan ?? 'Petugas Imigrasi' }}</span>
        </div>

        {{-- Avatar dengan dropdown logout --}}
        <div class="profile-avatar-wrapper" id="profileDropdownToggle">
            <div class="profile-avatar">
                <div class="avatar-placeholder">
                    {{ strtoupper(substr(Auth::user()->name ?? Auth::user()->username, 0, 2)) }}
                </div>
            </div>
            <span class="profile-caret">▾</span>
        </div>

        {{-- Dropdown Menu --}}
        <div class="profile-dropdown" id="profileDropdownMenu">
            <div class="dropdown-user-info">
                <span class="dropdown-username">{{ Auth::user()->name ?? Auth::user()->username }}</span>
                <span class="dropdown-role">{{ Auth::user()->jabatan ?? 'Petugas Imigrasi' }}</span>
            </div>
            <div class="dropdown-divider"></div>
            <a href="{{ route('pengaturan') }}" class="dropdown-item-link">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                <span>Pengaturan Akun</span>
            </a>
            <div class="dropdown-divider"></div>
            <form action="{{ route('logout') }}" method="POST" class="dropdown-logout-form">
                @csrf
                <button type="submit" class="dropdown-item-logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Keluar dari Sistem</span>
                </button>
            </form>
        </div>
    </div>

</header>

<script>
    (function () {
        const toggle = document.getElementById('profileDropdownToggle');
        const menu   = document.getElementById('profileDropdownMenu');
        if (!toggle || !menu) return;

        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            menu.classList.toggle('open');
        });

        document.addEventListener('click', function () {
            menu.classList.remove('open');
        });

        menu.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    })();
</script>
