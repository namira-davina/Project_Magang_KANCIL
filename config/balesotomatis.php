<?php

return [

    /*
    | Kredensial API BalesOtomatis (https://api.balesotomatis.co.id/docs/).
    | Disimpan hanya di .env, tidak pernah ke database atau browser.
    */

    'base_url' => rtrim((string) env('BALESOTOMATIS_BASE_URL', 'https://api.balesotomatis.co.id/public/v1'), '/'),

    // Kode Akses (Integration > Kode Akses). Untuk WABA dipakai sebagai secret_key.
    'api_key' => env('BALESOTOMATIS_API_KEY'),

    // WA non-resmi: dari endpoint `info` (php artisan kancil:balesotomatis-info).
    'number_id' => env('BALESOTOMATIS_NUMBER_ID'),

    // WABA (Meta): nanti saat migrasi ke nomor resmi.
    'licenses_key' => env('BALESOTOMATIS_LICENSES_KEY'),

    // nonresmi | waba
    'channel' => env('BALESOTOMATIS_CHANNEL', 'nonresmi'),

    // String acak 40+ karakter, bagian dari URL webhook.
    'webhook_token' => env('BALESOTOMATIS_WEBHOOK_TOKEN'),

    'timeout' => (int) env('BALESOTOMATIS_TIMEOUT', 20),

    // Aktifkan indikator "mengetik" saat bot membalas.
    'enable_typing' => (bool) env('BALESOTOMATIS_ENABLE_TYPING', false),

    // Hapus webhook_events lebih lama dari N hari.
    'retention_days' => (int) env('BALESOTOMATIS_RETENTION_DAYS', 30),
];
