<?php

return [

    // balesotomatis = Opsi A (AI BalesOtomatis menjawab, Laravel mencatat)
    // laravel       = Opsi B (menu rule-based + AI sendiri di Laravel; matikan AI Powered BalesOtomatis)
    'bot_engine' => env('BOT_ENGINE', 'balesotomatis'),

    // Sesi menu berakhir setelah N menit tanpa pesan (Dokumen Skenario: 30).
    'session_timeout_minutes' => (int) env('BOT_SESSION_TIMEOUT', 30),

    // Lama bot diam setelah petugas mengambil alih / membalas.
    'handoff_minutes' => (int) env('BOT_HANDOFF_MINUTES', 120),

    // Tambahkan MSG-07 pada ESK-01 di luar jam layanan.
    'outside_hours_notice' => (bool) env('BOT_OUTSIDE_HOURS_NOTICE', true),

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
