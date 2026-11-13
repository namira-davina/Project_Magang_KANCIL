
<aside class="sidebar">

    <div class="sidebar-brand">
        <img src="{{ asset('images/logo-imigrasi.png') }}" alt="Logo Imigrasi Cilacap" class="brand-logo-img">

        <div class="brand-text">
            <h2>KANCIL SAPA</h2>
            <span>IMIGRASI CILACAP</span>
        </div>
    </div>

    <div class="sidebar-menu">
        <span class="menu-label">MENU UTAMA</span>

        <a href="{{ route('admin.dashboard') }}"
           class="menu-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
            <span class="menu-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
            </span>
            <span>Dashboard</span>
        </a>

        <a href="{{ route('percakapan') }}"
           class="menu-item {{ request()->routeIs('percakapan') ? 'active' : '' }}">
            <span class="menu-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </span>
            <span>Percakapan</span>
        </a>

        @php($antreanMenunggu = \App\Models\ServiceTicket::where('status', \App\Models\ServiceTicket::WAITING)->count())
        <a href="{{ route('antrean') }}"
           class="menu-item {{ request()->routeIs('antrean') ? 'active' : '' }}">
            <span class="menu-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </span>
            <span>Antrean Petugas</span>
            <span class="menu-badge" data-queue-badge style="{{ $antreanMenunggu === 0 ? 'display: none;' : '' }}">{{ $antreanMenunggu }}</span>
        </a>

        @if (auth()->user()?->hasRole('admin'))
        <a href="{{ route('faq.index') }}"
           class="menu-item {{ request()->routeIs('faq.*') ? 'active' : '' }}">
            <span class="menu-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </span>
            <span>Database FAQ</span>
        </a>
        @endif

        @if (auth()->user()?->hasRole('admin'))
        <a href="{{ route('kategori-faq.index') }}"
           class="menu-item {{ request()->routeIs('kategori-faq*') ? 'active' : '' }}">
            <span class="menu-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
            </span>
            <span>Kategori FAQ</span>
        </a>
        @endif

        <a href="{{ route('pengaduan') }}"
           class="menu-item {{ request()->routeIs('pengaduan') ? 'active' : '' }}">
            <span class="menu-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </span>
            <span>Pengaduan</span>
        </a>

        @if (auth()->user()?->hasRole('admin', 'pimpinan'))
        <a href="{{ route('laporan') }}"
           class="menu-item {{ request()->routeIs('laporan') ? 'active' : '' }}">
            <span class="menu-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="20" x2="18" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="6" y1="20" x2="6" y2="14"></line>
                </svg>
            </span>
            <span>Laporan</span>
        </a>
        @endif

        <a href="{{ route('pengaturan') }}"
           class="menu-item {{ request()->routeIs('pengaturan') ? 'active' : '' }}">
            <span class="menu-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </span>
            <span>Pengaturan</span>
        </a>
    </div>

    <div class="sidebar-bottom">

        {{-- Tombol Logout --}}
        <form action="{{ route('logout') }}" method="POST" class="sidebar-logout-form">
            @csrf
            <button type="submit" class="sidebar-logout-btn">
                <span class="menu-icon">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </span>
                <span>Keluar</span>
            </button>
        </form>

        <div class="sidebar-status-card">
            <div class="status-card-header">
                <span class="status-indicator-dot"></span>
                <span class="status-title">SISTEM SIAGA</span>
                <span class="status-pill">Online</span>
            </div>
            <div class="status-card-body">
                <span class="status-subtitle">Status Operasional Pelayanan</span>
                <span class="status-time">Senin – Jumat | 08.00 – 16.00 WIB</span>
            </div>
        </div>
    </div>

</aside>
