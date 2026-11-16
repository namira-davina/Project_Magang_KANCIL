<?php

return [

    // balesotomatis = Opsi A (AI BalesOtomatis menjawab, Laravel mencatat)
    // laravel       = Opsi B (menu rule-based + AI sendiri di Laravel; matikan AI Powered BalesOtomatis)
    'bot_engine' => env('BOT_ENGINE', 'laravel'),

    // Sesi menu berakhir setelah N menit tanpa pesan (Dokumen Skenario: 30).
    'session_timeout_minutes' => (int) env('BOT_SESSION_TIMEOUT', 30),

    // Lama bot diam setelah petugas mengambil alih / membalas.
    'handoff_minutes' => (int) env('BOT_HANDOFF_MINUTES', 120),

    // Tiket antrean petugas (88 / Hubungi Petugas / AI tidak tahu): urut FIFO, nomor LYN-YYYYMMDD-NNN.
    // Selama tiket menunggu bot diam (kecuali perintah "antrean"/"batal" dan pengingat posisi).
    'ticket' => [
        // Tiket menunggu ditutup otomatis (Kedaluwarsa) setelah N hari kerja tanpa diambil petugas.
        'expire_business_days' => (int) env('TICKET_EXPIRE_BUSINESS_DAYS', 2),
        // Jarak minimal pengingat posisi antrean saat pemohon mengirim pesan biasa selama menunggu.
        'notice_minutes' => (int) env('TICKET_NOTICE_MINUTES', 15),
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
        // Lokasi layanan paspor lain (nama => tautan peta).
        'other_locations' => [
            'MPP Kabupaten Banyumas' => 'https://www.google.com/maps/search/?api=1&query=Mal+Pelayanan+Publik+Banyumas',
            'MPP Kabupaten Purbalingga' => 'https://www.google.com/maps/search/?api=1&query=Mal+Pelayanan+Publik+Purbalingga',
            'Kantor Kemenag Kabupaten Purbalingga' => 'https://www.google.com/maps/search/?api=1&query=Kantor+Kemenag+Purbalingga',
        ],
    ],

    'complaint' => [
        'min_length' => 20,
        'max_attachments' => 3,
    ],
];
