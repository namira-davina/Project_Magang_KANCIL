<?php

namespace Tests\Unit;

use App\Services\Bot\BotScript;
use App\Services\Bot\MenuAnswerResolver;
use Tests\TestCase;

/**
 * Tarif PNBP di config kancil.pnbp (blok PNBP jawaban menu) wajib sama dengan FAQ tarif TRF-001..004
 * (Inventarisasi 3.1, PP 45/2024; gambar "PNBP Dokumen Perjalanan RI" dari Imigrasi 20 Nov). Mencegah dua sumber berbeda.
 */
class PnbpConsistencyTest extends TestCase
{
    private function faq(string $code): string
    {
        return collect(require database_path('data/faqs.php'))->firstWhere('code', $code)['answer'];
    }

    public function test_amounts_match_official_tariff_and_faq_text(): void
    {
        $official = ['paspor_5' => 650000, 'paspor_10' => 950000, 'denda_hilang' => 1000000, 'denda_rusak' => 500000, 'percepatan' => 1000000];

        foreach ($official as $key => $amount) {
            $item = config("kancil.pnbp.items.{$key}");
            $this->assertSame($amount, $item['amount'], $key);
            $this->assertStringContainsString(BotScript::rupiah($amount), $this->faq($item['faq']), "{$key} vs {$item['faq']}");
        }

        $this->assertStringContainsString(str_replace('PP ', '', config('kancil.pnbp.legal_basis')), $this->faq('TRF-011')); // "Nomor 45 Tahun 2024"
    }

    public function test_every_pnbp_component_used_in_menu_has_known_source(): void
    {
        foreach (BotScript::ANSWERS as $path => $answer) {
            foreach (MenuAnswerResolver::pnbpSources($answer['pnbp'] ?? []) as $code) {
                $this->assertContains($code, ['TRF-001', 'TRF-002', 'TRF-003', 'TRF-004'], $path);
            }
        }
    }

    public function test_rupiah_format(): void
    {
        $this->assertSame('Rp650.000', BotScript::rupiah(650000));
        $this->assertSame('Rp1.000.000', BotScript::rupiah(1000000));
    }
}
