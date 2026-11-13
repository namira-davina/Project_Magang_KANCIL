
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
                    Semua (<span data-count="all">{{ number_format($counts['all'], 0, ',', '.') }}</span>)
                </button>
                <button type="button" class="conversation-filter-btn" data-filter="bot">
                    Otomatis Bot (<span data-count="bot">{{ number_format($counts['bot'], 0, ',', '.') }}</span>)
                </button>
                <button type="button" class="conversation-filter-btn" data-filter="human">
                    Perlu Petugas (<span data-count="human">{{ number_format($counts['human'], 0, ',', '.') }}</span>)
                </button>
            </div>

            <div class="chat-thread-list" id="conversationList">
                <p class="table-empty-notice" id="conversationEmpty" style="padding: 16px; text-align: center; color: #64748b; font-size: 13px;{{ count($threads) ? ' display: none;' : '' }}">
                    Belum ada percakapan WhatsApp.
                </p>
            </div>

        </div>

        {{-- KOLOM KANAN: DETAIL PERCAKAPAN (CHAT ROOM) --}}
        <div class="content-panel chat-room-panel">

            {{-- CHAT HEADER --}}
            <div class="chat-room-header">
                <div class="chat-target-profile">
                    <div class="chat-avatar-circle" id="chatAvatar">--</div>
                    <div>
                        <div class="chat-target-name-row">
                            <h3 id="chatContactName">Pilih percakapan</h3>
                            <span class="badge-status-pill bot" id="chatModeBadge" style="display: none;"></span>
                        </div>
                        <span class="chat-target-phone" id="chatContactStatus">-</span>
                    </div>
                </div>

                <div class="chat-header-actions">
                    <button type="button" class="btn-primary-dark" id="takeoverButton" @unless ($canReply) style="display: none;" @endunless disabled>
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
                    <span>Pilih percakapan di sebelah kiri untuk melihat isi chat.</span>
                </div>
            </div>

            {{-- CHAT INPUT AREA --}}
            <div class="chat-room-input-container" @unless ($canReply) style="display: none;" @endunless>
                <form id="chatReplyForm" class="chat-input-form-block">
                    <textarea
                        id="chatReplyInput"
                        class="chat-textarea-field"
                        rows="3"
                        placeholder="Tulis instruksi resmi petugas ke WhatsApp pemohon..."
                        maxlength="4000"
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
<script>
    window.KANCIL_PERCAKAPAN = {
        threads: @json($threads),
        counts: @json($counts),
        selectedId: @json($selectedId),
        canReply: @json($canReply),
        urls: {
            data: @json(route('percakapan.data')),
            pesan: @json(url('/percakapan/__ID__/pesan')),
            balas: @json(url('/percakapan/__ID__/balas')),
            ambilAlih: @json(url('/percakapan/__ID__/ambil-alih')),
            kembalikan: @json(url('/percakapan/__ID__/kembalikan')),
        },
        pollMs: 8000,
    };
</script>
<script src="{{ asset('js/percakapan.js') }}"></script>
@endpush
