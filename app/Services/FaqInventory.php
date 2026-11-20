<?php

namespace App\Services;

use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\FaqKeyword;

/**
 * Data FAQ resmi (database/data/faqs.php = Inventarisasi FAQ 3.1 + PSP-013..015) dan cara membuat FAQ baru darinya.
 * Dipakai FaqSeeder (instalasi baru) dan kancil:faq-sync (menyelaraskan DB yang sudah berjalan).
 */
class FaqInventory
{
    private const STOPWORDS = [
        'apa', 'saja', 'yang', 'untuk', 'dengan', 'bagaimana', 'berapa', 'apakah', 'jika', 'harus', 'dapat',
        'bisa', 'atau', 'dan', 'dari', 'pada', 'melalui', 'cara', 'membuat', 'syarat', 'persyaratan', 'sudah',
        'siapa', 'tersebut', 'sampai', 'oleh', 'dalam', 'para', 'satu', 'lewat', 'tahun', 'hari', 'kerja',
    ];

    /** @return array<string, array{code: string, question: string, answer: string, menu_path: string, status?: string, source?: string}> */
    public function rows(): array
    {
        return collect(require database_path('data/faqs.php'))->keyBy('code')->all();
    }

    public function create(array $row): Faq
    {
        $faq = Faq::create([
            'code' => $row['code'],
            'menu_path' => $row['menu_path'],
            'category_id' => FaqCategory::where('name', self::categoryFor($row['code']))->value('id'),
            'question' => $row['question'],
            'answer' => $row['answer'],
            'source' => self::source($row),
            'verification_status' => self::status($row),
            'is_active' => self::status($row) === 'terverifikasi',
        ]);

        foreach (self::keywords($row) as $keyword) {
            FaqKeyword::create(['faq_id' => $faq->id, 'keyword' => $keyword]);
        }

        return $faq;
    }

    public static function status(array $row): string
    {
        return $row['status'] ?? 'perlu_verifikasi';
    }

    public static function source(array $row): string
    {
        return $row['source'] ?? 'Dokumen Inventarisasi FAQ 3.1';
    }

    public static function categoryFor(string $code): string
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
    public static function keywords(array $row): array
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
