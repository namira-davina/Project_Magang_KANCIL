<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Services\FaqInventory;
use Illuminate\Database\Seeder;

/**
 * Impor FAQ dari Dokumen Inventarisasi FAQ 3.1 (database/data/faqs.php: 61 FAQ + PSP-013..015).
 * Status awal mengikuti data (3.1: "perlu_verifikasi", nonaktif): bot menampilkan MSG-05 sampai Admin memverifikasi.
 * Idempoten: FAQ yang sudah ada (berdasarkan kode) tidak ditimpa agar hasil verifikasi tetap.
 * Untuk menyelaraskan isi FAQ di DB yang sudah berjalan, pakai `php artisan kancil:faq-sync`.
 */
class FaqSeeder extends Seeder
{
    public function run(FaqInventory $inventory): void
    {
        foreach ($inventory->rows() as $code => $row) {
            if (! Faq::where('code', $code)->exists()) {
                $inventory->create($row);
            }
        }
    }
}
