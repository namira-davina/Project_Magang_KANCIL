<?php

return [

    // balesotomatis = Opsi A (AI BalesOtomatis menjawab, Laravel mencatat)
    // laravel       = Opsi B (menu rule-based + AI sendiri di Laravel; matikan AI Powered BalesOtomatis)
    'bot_engine' => env('BOT_ENGINE', 'balesotomatis'),

    // Sesi menu berakhir setelah N menit tanpa pesan (Dokumen Skenario: 30).
    'session_timeout_minutes' => (int) env('BOT_SESSION_TIMEOUT', 30),

    // Lama bot diam setelah petugas mengambil alih / membalas.
    'handoff_minutes' => (int) env('BOT_HANDOFF_MINUTES', 120),

    // Lama bot diam saat pemohon menunggu di antrean petugas (88 / Hubungi Petugas).
    // Lewat batas ini tanpa balasan petugas, bot aktif lagi. 0 = tunggu sampai petugas membalas/mengembalikan.
    'waiting_minutes' => (int) env('BOT_WAITING_MINUTES', 240),

    // FAQ yang boleh ditampilkan menu: verified = Terverifikasi + Aktif; all = semua non-draf (+ catatan "Informasi umum").
    'faq_scope' => env('BOT_FAQ_SCOPE', 'verified'),

    // Tambahkan MSG-07 pada ESK-01 di luar jam layanan.
    'outside_hours_notice' => (bool) env('BOT_OUTSIDE_HOURS_NOTICE', true),

    // Batas maksimal balasan bot per percakapan dalam 1 menit (anti-spam; 0 = nonaktif).
    'rate_limit_per_minute' => (int) env('BOT_RATE_LIMIT_PER_MINUTE', 10),

    // Kontak resmi (satu-satunya yang boleh disebut bot).
    'contact' => [
        'whatsapp' => '081217000900',
        'email' => 'cilacap@imigrasi.go.id',
    ],

    'complaint' => [
        'min_length' => 20,
        'max_attachments' => 3,
    ],
];
