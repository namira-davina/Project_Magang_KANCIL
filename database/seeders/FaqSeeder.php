<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\FaqKeyword;
use Illuminate\Database\Seeder;

/**
 * Impor 61 FAQ dari Dokumen Inventarisasi FAQ (database/data/faqs.php).
 * Status awal "perlu_verifikasi" dan nonaktif: bot menampilkan MSG-05 sampai Admin memverifikasi.
 * Idempoten: FAQ yang sudah ada (berdasarkan kode) tidak ditimpa agar hasil verifikasi tetap.
 */
class FaqSeeder extends Seeder
{
    private const STOPWORDS = [
        'apa', 'saja', 'yang', 'untuk', 'dengan', 'bagaimana', 'berapa', 'apakah', 'jika', 'harus', 'dapat',
        'bisa', 'atau', 'dan', 'dari', 'pada', 'melalui', 'cara', 'membuat', 'syarat', 'persyaratan', 'sudah',
        'siapa', 'tersebut', 'sampai', 'oleh', 'dalam', 'para', 'satu', 'lewat', 'tahun', 'hari', 'kerja',
    ];

    public function run(): void
    {
        $categories = FaqCategory::pluck('id', 'name');

        foreach (require database_path('data/faqs.php') as $row) {
            if (Faq::where('code', $row['code'])->exists()) {
                continue;
            }

            $faq = Faq::create([
                'code' => $row['code'],
                'menu_path' => $row['menu_path'],
                'category_id' => $categories[$this->categoryFor($row['code'])] ?? null,
                'question' => $row['question'],
                'answer' => $row['answer'],
                'source' => 'Dokumen Inventarisasi FAQ',
                'verification_status' => 'perlu_verifikasi',
                'is_active' => false,
            ]);

            foreach ($this->keywords($row) as $keyword) {
                FaqKeyword::create(['faq_id' => $faq->id, 'keyword' => $keyword]);
            }
        }
    }

    private function categoryFor(string $code): string
    {
        $number = (int) substr($code, 4);

        return match (substr($code, 0, 3)) {
            'PSP', 'ANK', 'AMB' => 'Paspor',
            'BYR' => 'Biaya dan Pembayaran',
            'TRF' => match (true) {
                $number >= 5 && $number <= 9 => 'Izin Tinggal',
                $number === 10 => 'Visa',
                default => 'Biaya dan Pembayaran',
            },
            'WNA' => 'Izin Tinggal',
            default => 'Informasi Umum',
        };
    }

    /** @return list<string> */
    private function keywords(array $row): array
    {
        $words = preg_split('/[^\p{L}\p{N}-]+/u', mb_strtolower($row['question'])) ?: [];

        return collect($words)
            ->filter(fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, self::STOPWORDS, true))
            ->unique()
            ->take(6)
            ->prepend(strtolower($row['code']))
            ->values()
            ->all();
    }
}
