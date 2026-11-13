<?php

return [

    /*
    | AI pihak ketiga yang kompatibel OpenAI (default: SumoPod).
    | AI aktif hanya bila LLM_ENABLED=true dan LLM_API_KEY terisi.
    */

    'enabled' => (bool) env('LLM_ENABLED', true),

    'provider' => env('LLM_PROVIDER', 'sumopod'),

    'base_url' => rtrim((string) env('LLM_BASE_URL', 'https://ai.sumopod.com/v1'), '/'),

    'api_key' => env('LLM_API_KEY'),

    'model' => env('LLM_MODEL', 'gpt-5-nano'),

    'timeout' => (int) env('LLM_TIMEOUT', 40),

    // Model gpt-5 memakai max_completion_tokens (termasuk token reasoning).
    'max_completion_tokens' => (int) env('LLM_MAX_COMPLETION_TOKENS', 2000),

    // minimal | low | medium | high. Kosongkan bila provider menolak parameter ini.
    'reasoning_effort' => env('LLM_REASONING_EFFORT', 'low'),

    // verified = hanya FAQ terverifikasi + aktif (sesuai dokumen).
    // all      = semua FAQ, jawaban diberi catatan "informasi umum" (seperti AI BalesOtomatis saat ini).
    'faq_scope' => env('LLM_FAQ_SCOPE', 'verified'),

    // Batas panggilan AI per hari (kendali biaya). 0 = tanpa batas.
    'daily_request_limit' => (int) env('LLM_DAILY_REQUEST_LIMIT', 500),

    // Jumlah pesan terakhir yang ikut dikirim sebagai konteks percakapan.
    'history_messages' => (int) env('LLM_HISTORY_MESSAGES', 6),
];
