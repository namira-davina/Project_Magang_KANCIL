
@extends('layouts.app')

@section('title', 'Percakapan')
@section('breadcrumb', 'Percakapan')

@section('content')

    {{-- HEADER HALAMAN --}}
    <div class="page-heading-action">
        <div>
            <h1>Monitoring Percakapan WhatsApp</h1>
            <p>Daftar percakapan warga yang masuk melalui WhatsApp Gateway KANCIL SAPA.</p>
        </div>
    </div>

    {{-- LAYOUT 2-KOLOM PERCAKAPAN WHATSAPP --}}
    <div class="conversation-split-layout">

        {{-- KOLOM KIRI: DAFTAR CHAT & FILTER --}}
        <div class="content-panel chat-list-sidebar">

            <div class="chat-search-wrap">
                <div class="search-input-box">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <input
                        type="text"
                        id="conversationSearch"
                        placeholder="Cari nama atau nomor WhatsApp..."
                    >
                </div>
            </div>

            <div class="chat-filter-pills">
                <button type="button" class="conversation-filter-btn active" data-filter="all">
                    Semua (1.250)
                </button>
                <button type="button" class="conversation-filter-btn" data-filter="bot">
                    Otomatis Bot (980)
                </button>
                <button type="button" class="conversation-filter-btn" data-filter="human">
                    Perlu Petugas (12)
                </button>
            </div>

            <div class="chat-thread-list" id="conversationList">

                {{-- THREAD 1 --}}
                <div class="chat-thread-item active"
                     data-id="1"
                     data-name="Hendro Wibowo"
                     data-phone="+62 813-8821-4920"
                     data-initial="HW"
                     data-badge="Perlu Petugas"
                     data-filter-type="human">
                    <div class="thread-top-row">
                        <strong class="thread-user-name">Hendro Wibowo</strong>
                        <span class="thread-time">10:41</span>
                    </div>
                    <div class="thread-phone">+62 813-8821-4920</div>
                    <p class="thread-preview">Saya sudah ada surat keterangan dari BPBD dan kantor desa Sidareja...</p>
                    <div class="thread-footer-row">
                        <span class="badge-status-pill waiting">Menunggu Petugas</span>
                        <span class="thread-ticket-id">#KS-2025-0842</span>
                    </div>
                </div>

                {{-- THREAD 2 --}}
                <div class="chat-thread-item"
                     data-id="2"
                     data-name="Siti Rahmawati"
                     data-phone="+62 852-9102-3344"
                     data-initial="SR"
                     data-badge="Otomatis Bot"
                     data-filter-type="bot">
                    <div class="thread-top-row">
                        <strong class="thread-user-name">Siti Rahmawati</strong>
                        <span class="thread-time">10:35</span>
                    </div>
                    <div class="thread-phone">+62 852-9102-3344</div>
                    <p class="thread-preview">Biaya perpanjangan visa izin tinggal lansia subyek penjamin...</p>
                    <div class="thread-footer-row">
                        <span class="badge-status-pill bot">Bot Terjawab</span>
                        <span class="thread-ticket-id">#KS-2025-0839</span>
                    </div>
                </div>

                {{-- THREAD 3 --}}
                <div class="chat-thread-item"
                     data-id="3"
                     data-name="Budi Santoso"
                     data-phone="+62 812-7744-9001"
                     data-initial="BS"
                     data-badge="Sedang Ditangani"
                     data-filter-type="human">
                    <div class="thread-top-row">
                        <strong class="thread-user-name">Budi Santoso</strong>
                        <span class="thread-time">10:15</span>
                    </div>
                    <div class="thread-phone">+62 812-7744-9001</div>
                    <p class="thread-preview">Jadwal kuota M-Paspor Cilacap minggu ini belum ada...</p>
                    <div class="thread-footer-row">
                        <span class="badge-status-pill agent">Sedang Ditangani</span>
                        <span class="thread-ticket-id">#KS-2025-0833</span>
                    </div>
                </div>

                {{-- THREAD 4 --}}
                <div class="chat-thread-item"
                     data-id="4"
                     data-name="Rahmat Hidayat"
                     data-phone="+62 878-3921-8812"
                     data-initial="RH"
                     data-badge="Otomatis Bot"
                     data-filter-type="bot">
                    <div class="thread-top-row">
                        <strong class="thread-user-name">Rahmat Hidayat</strong>
                        <span class="thread-time">09:50</span>
                    </div>
                    <div class="thread-phone">+62 878-3921-8812</div>
                    <p class="thread-preview">Syarat paspor umroh kilat langsung jadi apakah bisa?</p>
                    <div class="thread-footer-row">
                        <span class="badge-status-pill bot">Bot Terjawab</span>
                        <span class="thread-ticket-id">#KS-2025-0827</span>
                    </div>
                </div>

            </div>

        </div>

        {{-- KOLOM KANAN: DETAIL PERCAKAPAN (CHAT ROOM) --}}
        <div class="content-panel chat-room-panel">

            {{-- CHAT HEADER --}}
            <div class="chat-room-header">
                <div class="chat-target-profile">
                    <div class="chat-avatar-circle" id="chatAvatar">HW</div>
                    <div>
                        <div class="chat-target-name-row">
                            <h3 id="chatContactName">Hendro Wibowo</h3>
                            <span class="badge-status-pill waiting" id="chatModeBadge">Perlu Petugas</span>
                        </div>
                        <span class="chat-target-phone" id="chatContactStatus">+62 813-8821-4920</span>
                    </div>
                </div>

                <div class="chat-header-actions">
                    <button type="button" class="btn-primary-dark" id="takeoverButton">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>Ambil Alih Percakapan</span>
                    </button>
                </div>
            </div>

            {{-- CHAT MESSAGES PANE --}}
            <div class="chat-room-messages" id="chatMessages">

                <div class="chat-date-pill-divider">
                    <span>Hari ini, 24 Oktober 2025</span>
                </div>

                {{-- BUBBLE 1: PESAN DARI WARGA --}}
                <div class="chat-bubble-row received">
                    <div class="chat-bubble-sender">Hendro Wibowo <span class="bubble-timestamp">10:38</span></div>
                    <div class="chat-bubble-content citizen">
                        Selamat pagi Kantor Imigrasi Cilacap. Paspor saya rusak terendam banjir di Sidareja dua hari lalu. Kertasnya robek dan luntur. Mohon bantuan bagaimana cara pengurusannya? Apakah kena denda paspor rusak?
                    </div>
                </div>

                {{-- BUBBLE 2: JAWABAN OTOMATIS BOT --}}
                <div class="chat-bubble-row received bot-response">
                    <div class="chat-bubble-sender">Bot KANCIL SAPA <span class="bubble-timestamp">10:39</span></div>
                    <div class="chat-bubble-content bot">
                        <p>Halo Bpk. Hendro Wibowo, terima kasih telah menghubungi Layanan Informasi Resmi Kantor Imigrasi Kelas I TPI Cilacap.</p>
                        <p style="margin-top: 8px;">Untuk paspor rusak akibat keadaan kahar (bencana banjir):</p>
                        <ul style="margin: 6px 0 0 16px; padding: 0;">
                            <li>Bebas biaya denda kerusakan (Rp 500.000) dengan melampirkan Surat Keterangan Bencana Alam dari BPBD/Kelurahan.</li>
                            <li>Wajib mengikuti Berita Acara Pemeriksaan (BAP) di Kantor Imigrasi.</li>
                        </ul>
                    </div>
                </div>

                {{-- BUBBLE 3: PERTANYAAN LANJUTAN WARGA --}}
                <div class="chat-bubble-row received">
                    <div class="chat-bubble-sender">Hendro Wibowo <span class="bubble-timestamp">10:41</span></div>
                    <div class="chat-bubble-content citizen">
                        Saya sudah ada surat keterangan dari BPBD dan kantor desa Sidareja pak. Apakah saya bisa langsung datang siang ini tanpa daftar antrean M-Paspor? Soalnya saya ada jadwal keberangkatan mendesak minggu depan.
                    </div>
                </div>

                {{-- BUBBLE 4: INSTRUKSI RESMI PETUGAS --}}
                <div class="chat-bubble-row sent">
                    <div class="chat-bubble-sender text-right">Petugas: Agus Setiawan, S.IP. <span class="bubble-timestamp">10:43</span></div>
                    <div class="chat-bubble-content official">
                        <p>Selamat pagi Bpk. Hendro. Berkas keterangan BPBD Anda telah tercatat. Untuk kasus darurat bencana alam, Bapak dipersilakan datang langsung (walk-in) ke loket Pelayanan BAP hari ini sebelum pukul 14:00 WIB.</p>
                        <div class="official-sent-status">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Terkirim ke WhatsApp</span>
                        </div>
                    </div>
                </div>

            </div>

            {{-- CHAT INPUT AREA --}}
            <div class="chat-room-input-container">
                <form id="chatReplyForm" class="chat-input-form-block">
                    <textarea
                        id="chatReplyInput"
                        class="chat-textarea-field"
                        rows="3"
                        placeholder="Tulis instruksi resmi petugas ke WhatsApp pemohon..."
                    ></textarea>

                    <div class="chat-input-bottom-bar">
                        <div class="gateway-indicator">
                            <span class="attachment-icon-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                                </svg>
                            </span>
                            <span class="gateway-text">WhatsApp Business Gateway</span>
                        </div>

                        <button type="submit" class="btn-primary-dark" id="sendMessageButton">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polygon points="5 3 19 12 5 21 5 3"></polygon>
                            </svg>
                            <span>Kirim Pesan</span>
                        </button>
                    </div>
                </form>
            </div>

        </div>

    </div>

@endsection

@push('scripts')
<script src="{{ asset('js/percakapan.js') }}"></script>
@endpush
