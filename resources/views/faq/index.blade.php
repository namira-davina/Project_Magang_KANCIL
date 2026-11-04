
@extends('layouts.app')

@section('title', 'Database FAQ')
@section('breadcrumb', 'Database FAQ')

@section('content')

    @if (session('success'))
        <div class="content-panel" style="padding: 12px 18px; margin-bottom: 16px; border-left: 4px solid #22c55e;">
            {{ session('success') }}
        </div>
    @endif

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

    {{-- HEADER HALAMAN & OVERLINE --}}
    <div class="page-heading-action">
        <div>
            <span class="page-overline">BASIS PENGETAHUAN • AUTOMASI WHATSAPP</span>
            <h1>Database FAQ</h1>
            <p>Kelola daftar tanya-jawab otomatis bot layanan keimigrasian WhatsApp.</p>
        </div>

        <div class="heading-buttons">
            <button type="button" class="btn-outline" id="btnExportFaq" onclick="window.print()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Ekspor</span>
            </button>

            <a href="{{ route('faq.create') }}" class="btn-primary-dark">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                <span>Tambah FAQ</span>
            </a>
        </div>
    </div>

    {{-- MAIN TABLE CARD LANGSUNG SESUAI DESAIN --}}
    <div class="content-panel faq-table-card">

        <div class="faq-filter-toolbar">
            <div class="search-input-box faq-search-box">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input
                    type="text"
                    id="faqSearch"
                    placeholder="Cari pertanyaan atau kata kunci..."
                >
            </div>

            <div class="select-dropdown-box">
                <select id="faqCategoryFilter" class="form-select-clean">
                    <option value="">Semua Kategori</option>

                    @foreach ($categories as $category)
                        <option value="{{ $category->name }}">
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="select-dropdown-box">
                <select id="faqStatusFilter" class="form-select-clean">
                    <option value="">Semua Status</option>
                    <option value="Aktif">Aktif</option>
                    <option value="Draf">Draf</option>
                    <option value="Perlu Verifikasi">Perlu Verifikasi</option>
                    <option value="Nonaktif">Nonaktif</option>
                </select>
            </div>

            <button type="button" id="resetFaqFilter" class="btn-reset-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="23 4 23 10 17 10"></polyline>
                    <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                </svg>
                <span>Reset</span>
            </button>
        </div>

        <div class="table-responsive">
            <table class="clean-data-table faq-data-table">
                <thead>
                    <tr>
                        <th width="60">NO</th>
                        <th>PERTANYAAN</th>
                        <th width="150">KATEGORI</th>
                        <th>JAWABAN SINGKAT</th>
                        <th width="110">STATUS</th>
                        <th width="100" style="text-align: right;">AKSI</th>
                    </tr>
                </thead>

                <tbody id="faqTableBody">

                    @forelse ($faqs as $index => $faq)

                        @php
                            if (!$faq->is_active && $faq->verification_status === 'terverifikasi') {
                                $status = 'Nonaktif';
                            } elseif ($faq->verification_status === 'terverifikasi') {
                                $status = 'Aktif';
                            } elseif ($faq->verification_status === 'perlu_verifikasi') {
                                $status = 'Perlu Verifikasi';
                            } else {
                                $status = 'Draf';
                            }
                        @endphp

                        <tr class="faq-row"
                            data-category="{{ $faq->category_name ?? '' }}"
                            data-status="{{ $status }}">

                            <td>{{ $index + 1 }}</td>

                            <td class="font-medium">
                                {{ $faq->question }}
                            </td>

                            <td class="text-muted">
                                {{ $faq->category_name ?? 'Tanpa Kategori' }}
                            </td>

                            <td class="text-secondary-cell">
                                {{ \Illuminate\Support\Str::limit($faq->answer, 100) }}
                            </td>

                            <td>
                                @if ($status === 'Aktif')
                                    <span class="status-dot-badge active">● Aktif</span>
                                @else
                                    <span class="status-dot-badge draft">
                                        ● {{ $status }}
                                    </span>
                                @endif
                            </td>

                            <td style="text-align: right;">
                                <a href="{{ route('faq.edit', $faq->id) }}"
                                class="btn-icon-action"
                                title="Edit FAQ">

                                    <!-- SVG edit yang sudah ada tetap digunakan -->
                                    <svg width="15" height="15" viewBox="0 0 24 24"
                                        fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round"
                                        stroke-linejoin="round">
                                        <path d="M12 20h9"></path>
                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                    </svg>
                                </a>
                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td colspan="6" style="text-align: center;">
                                Belum ada data FAQ.
                            </td>
                        </tr>

                    @endforelse

                </tbody>
            </table>
        </div>

        {{-- INFORMASI DAN PAGINATION SESUAI DESAIN HALAMAN 7 --}}
        <div class="table-pagination-footer">
            <span class="pagination-info" id="faqDataInfo">Menampilkan 1 - 6 dari 42 entri</span>

            <div class="pagination-buttons" id="faqPagination">
                <button type="button" class="btn-page-nav" disabled>&laquo;</button>
                <button type="button" class="btn-page-num active">1</button>
                <button type="button" class="btn-page-num">2</button>
                <button type="button" class="btn-page-num">3</button>
                <span class="pagination-ellipsis">...</span>
                <button type="button" class="btn-page-num">7</button>
                <button type="button" class="btn-page-nav">&raquo;</button>
            </div>
        </div>

    </div>

@endsection

@push('scripts')
<script src="{{ asset('js/faq.js') }}"></script>
@endpush
