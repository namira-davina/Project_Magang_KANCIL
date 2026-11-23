<?php

return [

    // balesotomatis = Opsi A (AI BalesOtomatis menjawab, Laravel mencatat)
    // laravel       = Opsi B (menu rule-based + AI sendiri di Laravel; matikan AI Powered BalesOtomatis)
    'bot_engine' => env('BOT_ENGINE', 'laravel'),

    // Sesi menu berakhir setelah N menit tanpa pesan (Dokumen Skenario: 30).
    'session_timeout_minutes' => (int) env('BOT_SESSION_TIMEOUT', 30),

    // Lama bot diam setelah petugas mengambil alih / membalas.
    'handoff_minutes' => (int) env('BOT_HANDOFF_MINUTES', 60),

    // Tiket antrean petugas (88 / Hubungi Petugas / AI tidak tahu): urut FIFO, nomor LYN-YYYYMMDD-NNN.
    // Selama tiket menunggu bot diam (kecuali perintah "antrean"/"batal" dan pengingat posisi).
    'ticket' => [
        // Tiket menunggu ditutup otomatis (Kedaluwarsa) setelah N hari kerja tanpa diambil petugas.
        'expire_business_days' => (int) env('TICKET_EXPIRE_BUSINESS_DAYS', 2),
        // Jarak minimal pengingat posisi antrean saat pemohon mengirim pesan biasa selama menunggu (detik).
        'notice_seconds' => (int) env('TICKET_NOTICE_SECONDS', 30),
        // Default prefix kode tiket layanan petugas.
        'prefix' => env('TICKET_PREFIX', 'LYN'),
    ],

    // FAQ yang boleh ditampilkan menu: verified = Terverifikasi + Aktif; all = semua non-draf (+ catatan "Informasi umum").
    'faq_scope' => env('BOT_FAQ_SCOPE', 'verified'),

    // Tambahkan MSG-07 pada ESK-01 di luar jam layanan.
    'outside_hours_notice' => (bool) env('BOT_OUTSIDE_HOURS_NOTICE', true),

    // Batas maksimal balasan bot per percakapan dalam 1 menit (anti-spam; 0 = nonaktif).
    'rate_limit_per_minute' => (int) env('BOT_RATE_LIMIT_PER_MINUTE', 20),

    // true = balasan WhatsApp diproses dan dikirim langsung setelah webhook dijawab (tanpa menunggu worker antrean).
    // Balasan jadi ~2-3 detik; bila kirim gagal, otomatis dicoba ulang lewat antrean.
    'reply_inline' => (bool) env('BOT_REPLY_INLINE', false),

    // Nomor WhatsApp bot kantor sendiri: pesan kosong dari nomor ini tidak dibalas menu (cegah bot saling balas).
    'own_numbers' => array_values(array_filter(array_map('trim', explode(',', (string) env('BOT_OWN_NUMBERS', '6281217000900,6285184542256'))))),

    // Kontak resmi (satu-satunya yang boleh disebut bot).
    'contact' => [
        'whatsapp' => '081217000900',
        'email' => 'cilacap@imigrasi.go.id',
    ],

    // Identitas kantor untuk menu utama dan "Lokasi Kami" (satu-satunya bagian yang beda dari bot Imigrasi Bogor).
    'office' => [
        'name' => 'Kantor Imigrasi Kelas I TPI Cilacap',
        'address' => 'Jl. Urip Sumoharjo No.249, Pantusan, Gumilir, Kec. Cilacap Utara, Kabupaten Cilacap, Jawa Tengah 53231',
        'maps' => 'https://maps.app.goo.gl/nsSRkK4pQqpF6rHM6',
        // Lokasi layanan paspor lain (feedback Imigrasi 20-21 Nov), format sama dengan kantor: nama, alamat, peta.
        'other_locations' => [
            [
                'name' => 'Mal Pelayanan Publik (MPP) Cilacap',
                'address' => 'Jl. Gatot Subroto No.268 Lantai 2, Karang Lor, Gunungsimping, Kec. Cilacap Tengah, Kabupaten Cilacap, Jawa Tengah 53224',
                'maps' => 'https://maps.app.goo.gl/8nMhuDQmjkW56mnE9',
            ],
            [
                'name' => 'Mal Pelayanan Publik (MPP) Banyumas',
                'address' => 'Jl. Dr. Angka No.45, Karangkobar, Sokanegara, Kec. Purwokerto Tim., Kabupaten Banyumas, Jawa Tengah 53115',
                'maps' => 'https://maps.app.goo.gl/9NsKFWBDHz8kRGRi6',
            ],
            [
                'name' => 'Mal Pelayanan Publik (MPP) Purbalingga',
                'address' => 'Jl. Raya Mayjen Sungkono No. 16, Selabaya, Kecamatan Kalimanah, Kabupaten Purbalingga, Jawa Tengah 53371',
                'maps' => 'https://maps.app.goo.gl/wFeYgwqUDX7Byi31A',
            ],
        ],
    ],

    // Tarif PNBP paspor (PP Nomor 45 Tahun 2024). Feedback Imigrasi 20 Nov: ditampilkan terpisah per komponen,
    // tidak pernah dijumlahkan. Angka wajib sama dengan FAQ TRF-001..004 (dikunci tests/Unit/PnbpConsistencyTest).
    'pnbp' => [
        'legal_basis' => 'PP Nomor 45 Tahun 2024',
        'items' => [
            'paspor_5' => ['label' => 'Paspor elektronik 5 tahun', 'amount' => 650000, 'faq' => 'TRF-001'],
            'paspor_10' => ['label' => 'Paspor elektronik 10 tahun', 'amount' => 950000, 'faq' => 'TRF-002'],
            'denda_hilang' => ['label' => 'Denda paspor hilang', 'amount' => 1000000, 'faq' => 'TRF-003'],
            'denda_rusak' => ['label' => 'Denda paspor rusak', 'amount' => 500000, 'faq' => 'TRF-003'],
            'percepatan' => ['label' => 'Layanan percepatan (opsional)', 'amount' => 1000000, 'faq' => 'TRF-004'],
        ],
    ],

    // Tempat permohonan untuk Cek Status Paspor (feedback Imigrasi 20 Nov). Kunci = angka pilihan; 'match' = kata kunci
    // untuk mengenali tempat bila pemohon mengetik nomor + nama tempat dalam satu pesan.
    'application_places' => [
        '1' => ['label' => 'Imigrasi Cilacap', 'match' => ['imigrasi cilacap', 'kanim cilacap', 'kantor imigrasi', 'kanim', 'cilacap']],
        '2' => ['label' => 'MPP Banyumas', 'match' => ['mpp banyumas', 'mpp purwokerto', 'mpp bms']],
        '3' => ['label' => 'MPP Purbalingga', 'match' => ['mpp purbalingga', 'mpp pbg']],
        '4' => ['label' => 'Ngapak Banyumas', 'match' => ['ngapak banyumas', 'kancil ngapak banyumas', 'ngapak bms']],
        '5' => ['label' => 'Ngapak Purbalingga', 'match' => ['ngapak purbalingga', 'kancil ngapak purbalingga', 'ngapak pbg']],
    ],

    // Contoh nomor permohonan pada Cek Status Paspor: kode kantor Imigrasi Cilacap 1568 (feedback Imigrasi 23 Nov).
    // Hanya contoh tampilan; validasi tetap 10-25 digit tanpa memaksa awalan.
    'application_number_example' => '1568000000012345',

    'complaint' => [
        'min_length' => 20,
        'max_attachments' => 3,
    ],
];
