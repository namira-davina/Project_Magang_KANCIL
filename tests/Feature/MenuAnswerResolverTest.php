<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Services\Bot\BotScript;
use App\Services\Bot\MenuAnswerResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Jawaban menu Bogor dari tabel FAQ + gate status (audit 20 Nov 2026, Skenario 3.2 Pesan 5).
 */
class MenuAnswerResolverTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    private function resolver(): MenuAnswerResolver
    {
        return app(MenuAnswerResolver::class);
    }

    public function test_all_sources_published_composes_text_in_mapping_order(): void
    {
        $this->seedFaqs();

        $answer = $this->resolver()->resolve('0>1');

        $this->assertFalse($answer['held']);
        $this->assertSame(['TRF-001', 'TRF-002', 'TRF-004', 'TRF-003', 'TRF-011'], $answer['codes']);
        $this->assertSame(Faq::where('code', 'TRF-001')->value('id'), $answer['faq_id']);

        $positions = collect(['TRF-001', 'TRF-002', 'TRF-004', 'TRF-003', 'TRF-011'])
            ->map(fn ($code) => mb_strpos($answer['text'], '*'.Faq::where('code', $code)->value('question').'*'));
        $this->assertSame($positions->sort()->values()->all(), $positions->values()->all(), 'Urutan mengikuti pemetaan.');
        $this->assertNotContains(false, $positions->all());
    }

    public function test_single_source_shows_answer_without_heading(): void
    {
        $this->seedFaqs();

        $answer = $this->resolver()->resolve('1>2>2');

        $this->assertSame(trim(Faq::where('code', 'WNA-003')->value('answer')), $answer['text']);
    }

    public function test_seeded_data_is_held_until_verified(): void
    {
        $this->seedFaqs(publish: false);

        foreach (array_keys(BotScript::ANSWERS) as $path) {
            $answer = $this->resolver()->resolve($path);

            if ($path === '1>3') {
                $this->assertFalse($answer['held'], 'Lokasi Kami tidak bergantung FAQ.');

                continue;
            }

            $this->assertTrue($answer['held'], $path);
            $this->assertSame(BotScript::msg('MSG-05'), $answer['text'], $path);
            $this->assertNull($answer['faq_id']);
        }
    }

    public function test_inactive_or_draft_or_missing_source_holds_answer(): void
    {
        $this->seedFaqs();

        Faq::where('code', 'LYK-002')->update(['is_active' => false]);
        $this->assertTrue($this->resolver()->resolve('0>6')['held'], 'Terverifikasi tapi nonaktif.');

        Faq::where('code', 'PSP-013')->update(['verification_status' => 'draft']);
        config(['kancil.faq_scope' => 'all']);
        $this->assertTrue($this->resolver()->resolve('0>5')['held'], 'Draf tidak pernah tampil.');

        config(['kancil.faq_scope' => 'verified']);
        Faq::where('code', 'PSP-014')->delete();
        $this->assertTrue($this->resolver()->resolve('0>8')['held'], 'FAQ sumber dihapus.');
    }

    public function test_dashboard_edit_changes_menu_answer(): void
    {
        $this->seedFaqs();
        Faq::where('code', 'PSP-001')->update(['answer' => 'Teks baru dari dashboard.']);

        $this->assertStringStartsWith("Teks baru dari dashboard.\n\n*PNBP (PP Nomor 45 Tahun 2024):*", $this->resolver()->resolve('0>2')['text']);
    }

    public function test_pnbp_block_follows_answer_and_is_gated_by_tariff_faq(): void
    {
        $this->seedFaqs();

        $this->assertStringEndsWith(
            "*PNBP (PP Nomor 45 Tahun 2024):*\n• Paspor elektronik 5 tahun: Rp650.000\n• Paspor elektronik 10 tahun: Rp950.000\n• Denda paspor rusak: Rp500.000",
            $this->resolver()->resolve('0>8')['text'],
        );

        // Tarif dasar belum terverifikasi: jawaban yang menampilkan PNBP ikut tertahan (Pesan 5), yang tidak memakai PNBP tetap tampil.
        Faq::where('code', 'TRF-002')->update(['verification_status' => 'perlu_verifikasi', 'is_active' => false]);
        $this->assertTrue($this->resolver()->resolve('0>8')['held']);
        $this->assertTrue($this->resolver()->resolve('0>2')['held']);
        $this->assertFalse($this->resolver()->resolve('0>4')['held'], 'Paspor anak hanya memakai tarif 5 tahun (TRF-001).');
        $this->assertFalse($this->resolver()->resolve('1>2>3')['held']);

        Faq::where('code', 'TRF-003')->update(['verification_status' => 'perlu_verifikasi', 'is_active' => false]);
        $this->assertTrue($this->resolver()->resolve('0>7')['held'], 'Denda hilang bersumber TRF-003.');
    }

    public function test_unknown_path_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->resolver()->resolve('9>9');
    }
}
