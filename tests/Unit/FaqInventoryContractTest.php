<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Kontrak kalibrasi (audit 20 Nov 2026): data FAQ bot (database/data/faqs.php) harus sama isinya dengan
 * Dokumen Inventarisasi FAQ 3.1 (30 Okt 2026), tests/Fixtures/inventaris-faq-3.1.json (diekstrak dari PDF).
 * Format WhatsApp (bullet, tebal, baris baru, tanda baca pemisah) boleh beda; kata dan angka harus sama.
 */
class FaqInventoryContractTest extends TestCase
{
    /** Deviasi terdokumentasi: isi 3.1 tetap utuh di awal, lalu ada tambahan yang disebutkan. */
    private const APPENDED = [
        'TRF-003' => 'sipantas.kanimcilacap.com',
    ];

    /**
     * Deviasi terdokumentasi yang mengganti isi 3.1: feedback user 2 (20 Nov, pihak Imigrasi, lebih baru dari 3.1)
     * menetapkan syarat penggantian paspor = e-KTP, KK, akta lahir/ijazah/buku nikah, paspor lama.
     */
    private const REPLACED = [
        'PSP-002' => ['ktp elektronik', 'kartu keluarga', 'akta lahir / ijazah / buku nikah', 'paspor lama'],
        // Feedback user 3 (20 Nov sore): syarat paspor hilang mengikuti SIPANTAS (screenshot WA0034).
        'PSP-011' => ['kartu tanda penduduk (ktp)', 'kartu keluarga', 'surat kehilangan (kepolisian)', 'akte / ijazah / surat nikah', 'dokumen pendukung lainnya', 'paspor lama', 'sipantas.kanimcilacap.com'],
        // Keputusan pemilik 21 Nov: format CEK# ke 081217000900 (nomor bot sendiri, tidak dikenali bot) diganti menu 1 > 11.
        'AMB-009' => ['ketik 1', 'lalu 11', 'cek status paspor', 'nomor permohonan', 'tempat permohonan', 'mpp banyumas', 'ngapak purbalingga'],
        // Feedback user 4 (23 Nov, screenshot WA0008/WA0009): daftar "Persyaratan:" seperti PSP-002 + bukti permohonan M-Paspor.
        'PSP-001' => ['persyaratan:', 'ktp elektronik', 'kartu keluarga', 'akta lahir / ijazah / buku nikah', 'bukti permohonan m-paspor', 'bawa berkas asli dan fotokopi ukuran a4'],
    ];

    /** Deviasi pertanyaan terdokumentasi. Feedback user 4 (23 Nov, screenshot WA0007): surat pernyataan bermeterai. */
    private const QUESTION_REPLACED = [
        'ANK-005' => 'Siapa yang menandatangani surat pernyataan bermeterai jika kedua orang tua bercerai hidup?',
    ];

    /** FAQ baru di luar 3.1 (jawaban menu Bogor yang belum ada di inventaris). */
    private const EXTRA = ['PSP-013', 'PSP-014', 'PSP-015', 'PSP-016'];

    private static function inventory(): array
    {
        $rows = json_decode(file_get_contents(__DIR__.'/../Fixtures/inventaris-faq-3.1.json'), true);

        return array_column($rows, null, 'code');
    }

    private static function data(): array
    {
        return array_column(require __DIR__.'/../../database/data/faqs.php', null, 'code');
    }

    public static function normalize(string $text): string
    {
        $text = strtr($text, ['“' => '"', '”' => '"', '‘' => "'", '’' => "'", '›' => '>', '–' => '-']);
        $text = str_replace(['*', '_'], '', $text);
        $text = preg_replace('/\.?\s*[;•]\s*/u', ' ', $text);   // pemisah daftar: ; atau •
        $text = preg_replace('/\.+(?=\s|$)/u', '', $text);      // titik akhir kalimat (bukan 1.000.000)
        $text = preg_replace('/:\s*(?=\S)/u', ': ', $text);

        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
    }

    public function test_fixture_has_all_61_faqs_from_document(): void
    {
        $this->assertCount(61, self::inventory());
    }

    public function test_data_contains_exactly_inventory_codes_plus_documented_extras(): void
    {
        $expected = [...array_keys(self::inventory()), ...self::EXTRA];
        sort($expected);
        $actual = array_keys(self::data());
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_every_answer_matches_inventory_3_1(): void
    {
        $data = self::data();
        $mismatches = [];

        foreach (self::inventory() as $code => $row) {
            $expected = self::normalize($row['answer']);
            $actual = self::normalize($data[$code]['answer']);

            $ok = match (true) {
                isset(self::REPLACED[$code]) => collect(self::REPLACED[$code])->every(fn ($item) => str_contains($actual, $item)),
                isset(self::APPENDED[$code]) => str_starts_with($actual, $expected) && str_contains($actual, self::APPENDED[$code]),
                default => $actual === $expected,
            };

            if (! $ok) {
                $mismatches[] = "{$code}\n  3.1 : {$expected}\n  data: {$actual}";
            }
        }

        $this->assertSame([], $mismatches, "Jawaban FAQ menyimpang dari Inventarisasi 3.1:\n".implode("\n", $mismatches));
    }

    public function test_no_faq_tells_applicants_to_use_cek_hash_format(): void
    {
        // 081217000900 adalah nomor bot itu sendiri; format CEK#... tidak dikenali bot (keputusan 21 Nov: pakai menu 1 > 11).
        foreach (self::data() as $code => $row) {
            $this->assertStringNotContainsString('CEK#', $row['answer'], $code);
        }
    }

    public function test_menu_path_and_question_follow_inventory(): void
    {
        $data = self::data();

        foreach (self::inventory() as $code => $row) {
            $this->assertSame($row['menu_path'], $data[$code]['menu_path'], $code);
            $this->assertSame(
                self::normalize(self::QUESTION_REPLACED[$code] ?? str_replace('- ', '-', $row['question'])),
                self::normalize($data[$code]['question']),
                $code,
            );
        }
    }

    public function test_status_follows_inventory_and_extras_need_verification(): void
    {
        $data = self::data();

        foreach (self::inventory() as $code => $row) {
            $expected = $row['status'] === 'Aktif' ? 'terverifikasi' : 'perlu_verifikasi';
            $this->assertSame($expected, $data[$code]['status'] ?? null, $code);
        }

        foreach (self::EXTRA as $code) {
            $this->assertSame('perlu_verifikasi', $data[$code]['status'], $code);
            $this->assertStringContainsString('di luar Inventarisasi 3.1', $data[$code]['source'], $code);
        }
    }
}
