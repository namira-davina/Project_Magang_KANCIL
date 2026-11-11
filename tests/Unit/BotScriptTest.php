<?php

namespace Tests\Unit;

use App\Services\Bot\BotEngine;
use App\Services\Bot\BotScript;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Konsistensi menu/jawaban dengan Dokumen Skenario FAQ dan Inventarisasi FAQ.
 */
class BotScriptTest extends TestCase
{
    private function faqData(): array
    {
        return collect(require database_path('data/faqs.php'))->keyBy('code')->all();
    }

    public function test_every_menu_option_points_to_existing_target(): void
    {
        foreach (BotScript::MENUS as $code => $menu) {
            foreach ($menu['options'] as $key => [$label, $target]) {
                $valid = isset(BotScript::MENUS[$target])
                    || (str_starts_with($target, 'ANS:') && isset(BotScript::ANSWERS[substr($target, 4)]))
                    || in_array($target, ['PGD-01', 'ESK'], true);

                $this->assertTrue($valid, "{$code} pilihan {$key} → {$target}");
                $this->assertNotSame('', $label);
                $this->assertNotContains($key, ['9', '99', '88'], 'Angka perintah tidak boleh dipakai sebagai pilihan.');
            }
        }
    }

    public function test_every_menu_reaches_main_menu_and_path_matches_parent(): void
    {
        foreach (BotScript::MENUS as $code => $menu) {
            $seen = [];
            for ($c = $code; $c !== null; $c = BotScript::MENUS[$c]['parent']) {
                $this->assertNotContains($c, $seen, "siklus pada {$code}");
                $seen[] = $c;
            }
            $this->assertSame('M-00', end($seen));
        }

        // M-xx yang dibuka dari induk memiliki jalur induk + pilihan.
        foreach (BotScript::MENUS as $code => $menu) {
            foreach ($menu['options'] as $key => [, $target]) {
                if (isset(BotScript::MENUS[$target])) {
                    $expected = ltrim($menu['path'].'>'.$key, '>');
                    $this->assertSame($expected, BotScript::MENUS[$target]['path'], $target);
                    $this->assertSame($code, BotScript::MENUS[$target]['parent'], $target);
                } elseif (str_starts_with($target, 'ANS:')) {
                    $this->assertSame(ltrim($menu['path'].'>'.$key, '>'), substr($target, 4));
                }
            }
        }
    }

    public function test_answers_use_existing_faqs_and_match_inventory_menu_path(): void
    {
        $faqs = $this->faqData();

        foreach (BotScript::ANSWERS as $path => $answer) {
            $this->assertNotEmpty($answer['faqs']);
            foreach ($answer['faqs'] as $code) {
                $this->assertArrayHasKey($code, $faqs, "{$path} memakai {$code}");
            }
            $this->assertSame($answer['code'] !== null, $answer['offer88'], "{$path}: tawaran 88 ⇔ kode eskalasi");
            if ($answer['code']) {
                $this->assertArrayHasKey($answer['code'], BotScript::PRIORITY);
            }
        }

        // Jalur menu di Inventarisasi (kolom "Jalur menu") sama dengan tabel jawaban.
        foreach ($faqs as $code => $faq) {
            $path = BotScript::pathForFaq($code);
            if (in_array($code, ['LYK-001', 'KTK-001'], true)) {
                $this->assertNull($path, "{$code} dipakai sebagai intro/kontak, bukan jawaban");

                continue;
            }
            $this->assertNotNull($path, "{$code} harus bisa dijangkau dari menu");
            if ($code !== 'TRF-011') {
                $this->assertSame($faq['menu_path'], $path, $code);
            }
        }
    }

    public function test_escalation_codes_match_matrix(): void
    {
        $codes = collect(BotScript::ANSWERS)->pluck('code')->filter()->unique()->sort()->values()->all();
        $this->assertSame(['A4', 'C1', 'C2', 'C3', 'C4', 'C5', 'C6'], $codes);

        $this->assertSame('Tinggi', BotScript::PRIORITY['C1']);
        $this->assertSame('Tinggi', BotScript::PRIORITY['C4']);
        $this->assertSame('Tinggi', BotScript::PRIORITY['C6']);
        $this->assertSame('Sedang', BotScript::PRIORITY['A3']);
        $this->assertSame(['Layanan paspor', 'Sedang'], BotScript::COMPLAINT_CATEGORIES['1']);
        $this->assertSame(['Pelayanan petugas', 'Tinggi'], BotScript::COMPLAINT_CATEGORIES['3']);
        $this->assertSame(['Lainnya', 'Rendah'], BotScript::COMPLAINT_CATEGORIES['4']);
    }

    public function test_standard_messages_match_document(): void
    {
        $this->assertSame('Maaf, pilihan tidak valid. Silakan ketik angka sesuai daftar menu.', BotScript::msg('MSG-01'));
        $this->assertSame('Sesi sebelumnya telah berakhir. Silakan pilih layanan kembali:', BotScript::msg('MSG-06'));
        $this->assertSame('Pengaduan dibatalkan. Anda dapat memulai kembali kapan saja.', BotScript::msg('MSG-08'));
        $this->assertStringContainsString('081217000900', BotScript::msg('MSG-05'));
        $this->assertStringContainsString('cilacap@imigrasi.go.id', BotScript::esk01());
        $this->assertSame('Saat ini di luar jam layanan petugas. Silakan hubungi kembali pada jam layanan.', BotScript::msg('MSG-07'));
        $this->assertStringEndsWith('jam layanan: 08-16.', BotScript::msg('MSG-07', '08-16'));
    }

    public function test_main_menu_hint_toggle(): void
    {
        $this->assertStringNotContainsString('pertanyaan', BotScript::mainMenu(false));
        $this->assertStringEndsWith('Anda juga dapat langsung mengetik pertanyaan.', BotScript::mainMenu(true));
    }

    public function test_unknown_message_ids_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BotScript::msg('MSG-99');
    }

    public function test_unknown_pgd_ids_throw(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BotScript::pgd('PGD-99');
    }

    public function test_pgd_summary_and_status_texts(): void
    {
        $summary = BotScript::pgd('PGD-07', ['name' => 'A', 'category' => 'Lainnya', 'text' => 'isi', 'attachments' => 2]);
        $this->assertStringContainsString('Lampiran: 2 berkas', $summary);
        $this->assertStringContainsString("*1* = Kirim\n*2* = Batalkan", $summary);

        $status = BotScript::pgd('PGD-10', ['ticket' => 'ADU-1', 'status' => 'Selesai', 'response' => null]);
        $this->assertStringNotContainsString('Tanggapan', $status);
    }

    public function test_normalize_commands(): void
    {
        $this->assertSame('99', BotEngine::normalize(' 99. '));
        $this->assertSame('menu', BotEngine::normalize('MENU!!'));
        $this->assertSame('batal', BotEngine::normalize('"Batal"'));
        $this->assertSame('adu-202611-0001', BotEngine::normalize('ADU-202611-0001'));
        $this->assertSame('', BotEngine::normalize('...'));
    }

    public function test_inventory_answers_fit_whatsapp_and_form_limits(): void
    {
        $faqs = $this->faqData();

        $this->assertCount(61, $faqs);
        foreach ($faqs as $code => $faq) {
            $this->assertMatchesRegularExpression('/^(PSP|ANK|AMB|BYR|TRF|LYK|WNA|KTK)-\d{3}$/', $code);
            $this->assertLessThanOrEqual(1600, mb_strlen($faq['answer']), $code);
            $this->assertLessThanOrEqual(255, mb_strlen($faq['question']), $code);
            $this->assertStringNotContainsString('**', $faq['answer'], "{$code}: markdown ganda harus jadi *tebal* WhatsApp");
            $this->assertStringNotContainsString('\\', $faq['answer'], $code);
        }
    }
}
