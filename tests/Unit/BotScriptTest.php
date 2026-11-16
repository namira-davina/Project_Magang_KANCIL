<?php

namespace Tests\Unit;

use App\Services\Bot\BotEngine;
use App\Services\Bot\BotScript;
use Illuminate\Support\Carbon;
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
                    || str_starts_with($target, 'ESK:') || str_starts_with($target, 'STATUS:')
                    || in_array($target, ['PGD-01', 'BACK'], true);

                $this->assertTrue($valid, "{$code} pilihan {$key} → {$target}");
                $this->assertNotSame('', $label);
                $this->assertNotContains((string) $key, ['0', '99', '88'], 'Angka perintah tidak boleh dipakai sebagai pilihan.');
            }
        }
    }

    public function test_options_are_numbered_sequentially_and_back_is_last(): void
    {
        foreach (BotScript::MENUS as $code => $menu) {
            $this->assertSame(range(1, count($menu['options'])), array_map('intval', array_keys($menu['options'])), $code);

            if ($code !== 'M-00') {
                $this->assertSame('BACK', end($menu['options'])[1], "{$code}: pilihan terakhir = kembali ke menu sebelumnya");
            }
        }
    }

    public function test_every_menu_reaches_main_menu_and_paths_follow_numbering(): void
    {
        foreach (BotScript::MENUS as $code => $menu) {
            $seen = [];
            for ($c = $code; $c !== null; $c = BotScript::MENUS[$c]['parent']) {
                $this->assertNotContains($c, $seen, "siklus pada {$code}");
                $seen[] = $c;
            }
            $this->assertSame('M-00', end($seen));
        }

        // Jalur internal: menu utama 1/2 = 0/1 (WNI/WNA), selebihnya jalur induk + nomor pilihan.
        foreach (BotScript::MENUS as $code => $menu) {
            foreach ($menu['options'] as $key => [, $target]) {
                $key = $code === 'M-00' ? (string) ((int) $key - 1) : $key;

                if (isset(BotScript::MENUS[$target])) {
                    $this->assertSame(ltrim($menu['path'].'>'.$key, '>'), BotScript::MENUS[$target]['path'], $target);
                    $this->assertSame($code, BotScript::MENUS[$target]['parent'], $target);
                } elseif (str_starts_with($target, 'ANS:')) {
                    $this->assertSame(ltrim($menu['path'].'>'.$key, '>'), substr($target, 4));
                }
            }
        }
    }

    public function test_answers_have_text_and_valid_escalation_codes(): void
    {
        foreach (BotScript::ANSWERS as $path => $answer) {
            if ($path !== '1>3') {
                $this->assertNotEmpty($answer['text'], $path);
                $this->assertLessThanOrEqual(1600, mb_strlen($answer['text']), "{$path}: muat di satu pesan WhatsApp");
            }

            if ($answer['code']) {
                $this->assertArrayHasKey($answer['code'], BotScript::PRIORITY);
            }
        }

        $this->assertSame('C1', BotScript::ANSWERS['0>7']['code'], 'Paspor hilang = C1');
        $this->assertSame('C2', BotScript::ANSWERS['0>4']['code'], 'Paspor anak = C2');
    }

    public function test_escalation_priorities_match_matrix(): void
    {
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
        $this->assertSame('Mohon maaf, pilihan Anda tidak tersedia. Silakan ketik angka sesuai menu di bawah ini.', BotScript::msg('MSG-01'));
        $this->assertSame('Sesi sebelumnya telah berakhir.', BotScript::msg('MSG-06'));
        $this->assertSame('Pengaduan dibatalkan. Anda dapat memulai kembali kapan saja.', BotScript::msg('MSG-08'));

        // Bot tidak menyebut nomor/email kantor; pemohon masuk antrean petugas dengan teks yang sama seperti Imigrasi Bogor.
        foreach (['MSG-01', 'MSG-02', 'MSG-03', 'MSG-04', 'MSG-05', 'MSG-06', 'MSG-07', 'MSG-08', 'MSG-09', 'AI-UNKNOWN'] as $id) {
            $this->assertStringNotContainsString('081217000900', BotScript::msg($id), $id);
            $this->assertStringNotContainsString('cilacap@imigrasi.go.id', BotScript::msg($id), $id);
        }
        $this->assertSame('Terima kasih atas pesan Anda.

Mohon menunggu, pesan Anda telah diterima dan masuk antrean.', BotScript::handoff());
    }

    public function test_answer_format_follows_bogor_style(): void
    {
        $text = BotScript::answer('Isi jawaban.');

        $this->assertSame(
            "*Berikut ini adalah informasi dan solusi yang Anda cari:*\n\nIsi jawaban.\n\n------------------------\n\n"
            ."Butuh bantuan lebih lanjut?\n\n*Ketik 0* untuk kembali ke menu sebelumnya\n*Ketik 1* untuk menghubungi Petugas\n\n*Terima kasih.*",
            $text,
        );
    }

    public function test_greeting_follows_time_of_day_and_name(): void
    {
        $at = fn (string $time) => Carbon::parse("2026-11-13 {$time}", 'Asia/Jakarta');

        $this->assertSame('Selamat Pagi, Halo *Budi*', BotScript::greeting('Budi', $at('08:00')));
        $this->assertSame('Selamat Siang, Halo *Budi*', BotScript::greeting('Budi', $at('12:00')));
        $this->assertSame('Selamat Sore, Halo *Budi*', BotScript::greeting('Budi', $at('16:00')));
        $this->assertSame('Selamat Malam, Halo', BotScript::greeting(null, $at('20:00')));
        $this->assertSame('Selamat Malam, Halo *Budi*', BotScript::greeting('*Bu_di*', $at('02:00')), 'Format WhatsApp di nama dibuang.');
    }

    public function test_main_menu_has_greeting_office_status_and_no_officer_option(): void
    {
        $text = BotScript::mainMenu('Budi', '*Status Hari Ini: BUKA*');

        $this->assertStringStartsWith('Selamat ', $text);
        $this->assertStringContainsString('Halo *Budi*', $text);
        $this->assertStringContainsString('Selamat Datang , di Layanan Informasi dan Pengaduan Kantor Imigrasi Kelas I TPI Cilacap.

*Status Hari Ini: BUKA*

Silakan pilih menu', $text);
        $this->assertStringNotContainsString('Hubungi Petugas (Contact Officer)', $text);
        $this->assertStringNotContainsString('pertanyaan', $text);
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
        $this->assertStringContainsString("Ketik *1* Kirim pengaduan\nKetik *2* Batalkan", $summary);

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
