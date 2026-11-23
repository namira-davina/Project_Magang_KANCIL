<?php

namespace Tests\Unit;

use App\Models\Faq;
use App\Services\Bot\BotEngine;
use App\Services\Bot\BotScript;
use App\Services\Bot\MenuAnswerResolver;
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

    /** Teks jawaban menu seperti yang dirakit MenuAnswerResolver dari data FAQ (semua FAQ dianggap Aktif). */
    private function answerText(string $path): string
    {
        $faqs = collect(BotScript::ANSWERS[$path]['faq'])
            ->map(fn (string $code) => new Faq(['question' => $this->faqData()[$code]['question'], 'answer' => $this->faqData()[$code]['answer']]));

        $text = MenuAnswerResolver::compose($faqs);
        $pnbp = BotScript::ANSWERS[$path]['pnbp'] ?? [];

        return $pnbp === [] ? $text : $text."\n\n".BotScript::pnbpBlock($pnbp);
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

        // Jalur internal: menu utama 1/2 = 0/1 (WNI/WNA), selebihnya jalur induk + nomor pilihan. Pengecualian terdokumentasi:
        // kunci stabil setelah Beda Data disisipkan di menu Paspor (feedback Imigrasi 20 Nov), agar data tiket lama tetap benar.
        $stable = ['M-01' => ['9' => '0>12', '10' => '0>9']];

        foreach (BotScript::MENUS as $code => $menu) {
            $keys = array_map('strval', array_keys($menu['options']));
            $this->assertSame(array_map('strval', range(1, count($keys))), $keys, "{$code}: nomor pilihan berurutan dari 1");

            if ($code !== 'M-00') {
                $options = $menu['options'];
                $this->assertSame('BACK', end($options)[1], "{$code}: pilihan terakhir = kembali ke menu sebelumnya");
            }

            foreach ($menu['options'] as $key => [, $target]) {
                $expected = $stable[$code][$key] ?? ltrim($menu['path'].'>'.($code === 'M-00' ? (string) ((int) $key - 1) : $key), '>');

                if (isset(BotScript::MENUS[$target])) {
                    $this->assertSame($expected, BotScript::MENUS[$target]['path'], $target);
                    $this->assertSame($code, BotScript::MENUS[$target]['parent'], $target);
                } elseif (str_starts_with($target, 'ANS:')) {
                    $this->assertSame($expected, substr($target, 4), "{$code} > {$key}");
                }
            }
        }
    }

    public function test_answers_have_text_and_valid_escalation_codes(): void
    {
        $faqs = $this->faqData();

        foreach (BotScript::ANSWERS as $path => $answer) {
            if ($path === '1>3') {
                $this->assertNull($answer['faq'], 'Lokasi Kami dari config kantor, bukan FAQ.');

                continue;
            }

            $this->assertNotEmpty($answer['faq'], $path);

            foreach ($answer['faq'] as $code) {
                $this->assertArrayHasKey($code, $faqs, "{$path}: FAQ sumber {$code} harus ada di data");
            }

            $text = BotScript::answer($this->answerText($path));
            $this->assertLessThanOrEqual(4096, mb_strlen($text), "{$path}: muat di satu pesan WhatsApp");

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
        // Dokumen Skenario 3.2, Pesan 1-9 (audit 20 Nov); 99 diganti 0 sesuai menu Bogor, nomor kantor tidak disebut.
        $this->assertSame('Maaf, pilihan tidak valid. Silakan ketik angka sesuai daftar menu.', BotScript::msg('MSG-01'));
        $this->assertSame('Maaf, pilihan masih belum sesuai. Bot ini hanya menerima angka pilihan seperti pada menu.', BotScript::msg('MSG-02'));
        $this->assertSame('Mohon maaf, bot belum dapat memahami pesan Anda.

Ketik *88* untuk menghubungi Petugas
Ketik *0* untuk menu utama', BotScript::msg('MSG-03'));
        $this->assertSame('Maaf, bot hanya menerima pesan teks berupa angka pilihan. Silakan ketik angka sesuai menu.', BotScript::msg('MSG-04'));
        $this->assertStringStartsWith('Informasi ini sedang diperbarui. Untuk kepastian', BotScript::msg('MSG-05'));
        $this->assertSame('Sesi sebelumnya telah berakhir. Silakan pilih layanan kembali:', BotScript::msg('MSG-06'));
        $this->assertSame('_Saat ini di luar jam layanan petugas. Jam layanan: Senin–Jumat pukul 08.00–16.00 WIB. Permintaan Anda tetap tercatat dalam antrean._', BotScript::msg('MSG-07', 'Senin–Jumat pukul 08.00–16.00 WIB'));
        $this->assertSame('Pengaduan dibatalkan. Anda dapat memulai kembali kapan saja.', BotScript::msg('MSG-08'));
        $this->assertStringContainsString('Pilih *3* (Layanan Pengaduan)', BotScript::msg('MSG-09'), 'Menu Bogor: pengaduan = 3.');

        // Bot tidak menyebut nomor/email kantor; pemohon masuk antrean petugas dengan teks yang sama seperti Imigrasi Bogor.
        foreach (['MSG-01', 'MSG-02', 'MSG-03', 'MSG-04', 'MSG-05', 'MSG-06', 'MSG-07', 'MSG-08', 'MSG-09', 'AI-UNKNOWN'] as $id) {
            $this->assertStringNotContainsString('081217000900', BotScript::msg($id), $id);
            $this->assertStringNotContainsString('cilacap@imigrasi.go.id', BotScript::msg($id), $id);
        }
        $handoff = BotScript::handoff(1, 2);
        $this->assertStringContainsString('🎫 *Permintaan Anda diteruskan ke Petugas*', $handoff);
        $this->assertStringContainsString("Nomor antrean hari ini: *1*\nPosisi antrean Anda: *ke-2*", $handoff);
        $this->assertStringNotContainsString('Nomor tiket', $handoff);
        $this->assertStringEndsWith("Ketik *0* kembali ke menu utama\nKetik *1* cek posisi antrean\nKetik *2* batalkan antrean", $handoff);
    }

    public function test_answer_format_follows_bogor_style(): void
    {
        $text = BotScript::answer('Isi jawaban.');

        $this->assertSame(
            "*Berikut ini adalah informasi dan solusi yang Anda cari:*\n\nIsi jawaban.\n\n------------------------\n\n"
            ."Butuh bantuan lebih lanjut?\n\n*Ketik 9* untuk kembali ke menu sebelumnya\n*Ketik 0* untuk kembali ke menu utama\n"
            ."*Ketik 1* untuk menghubungi Petugas\n\n*Terima kasih.*",
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

        $this->assertCount(65, $faqs);
        foreach ($faqs as $code => $faq) {
            $this->assertMatchesRegularExpression('/^(PSP|ANK|AMB|BYR|TRF|LYK|WNA|KTK)-\d{3}$/', $code);
            $this->assertLessThanOrEqual(1600, mb_strlen($faq['answer']), $code);
            $this->assertLessThanOrEqual(255, mb_strlen($faq['question']), $code);
            $this->assertStringNotContainsString('**', $faq['answer'], "{$code}: markdown ganda harus jadi *tebal* WhatsApp");
            $this->assertStringNotContainsString('\\', $faq['answer'], $code);
        }
    }

    public function test_passport_answers_mention_sipantas_and_updated_requirements(): void
    {
        $sipantas = 'https://sipantas.kanimcilacap.com/';

        // Biaya (TRF-003): BAP hilang/rusak/beda data online lewat SIPANTAS (feedback user 3: bukan penambahan nama).
        $fee = $this->answerText('0>1');
        $this->assertStringContainsString('paspor hilang, rusak, dan beda data dapat diajukan online melalui '.$sipantas, $fee);
        $this->assertStringNotContainsString('penambahan nama', $fee);
        $this->assertStringContainsString('Rp650.000', $fee);
        $this->assertStringContainsString('Biaya layanan percepatan adalah Rp1.000.000.', $fee);
        $this->assertStringContainsString('PP) Nomor 45 Tahun 2024', $fee);

        // SIPANTAS hanya untuk hilang (0>7), rusak (0>8), beda data (0>12), sesuai kategori di sipantas.kanimcilacap.com.
        foreach (['0>7', '0>8', '0>12'] as $path) {
            $this->assertStringContainsString($sipantas, $this->answerText($path), $path);
        }
        $this->assertStringNotContainsString('sipantas', $this->answerText('0>9'), 'Penambahan nama bukan lewat SIPANTAS.');

        // Syarat SIPANTAS (screenshot WA0032-34).
        $this->assertStringContainsString('• Surat kehilangan (kepolisian)', $this->answerText('0>7'));
        $this->assertStringContainsString('• Penetapan pengadilan', $this->answerText('0>12'));
        $this->assertStringNotContainsString('Penetapan pengadilan', $this->answerText('0>8'));
        $this->assertStringContainsString('(terdapat tanggal, bulan, dan tahun lahir)', $this->answerText('0>8'));

        // Penggantian (feedback user 2): tambah KK dan akta lahir/ijazah/buku nikah.
        $replace = $this->answerText('0>3');
        $this->assertStringContainsString('• Kartu Keluarga', $replace);
        $this->assertStringContainsString('• Akta lahir / ijazah / buku nikah', $replace);

        // Paspor anak (3.1 ANK-002): anak di bawah 5 tahun TIDAK wajib M-Paspor (teks Bogor lama keliru).
        $this->assertStringContainsString('Anak di bawah 5 tahun dapat langsung datang ke kantor imigrasi tanpa mendaftar di M-Paspor.', $this->answerText('0>4'));

        // Layanan prioritas = KANCIL NARI (LYK-002), tanpa "Ibu Menyusui" yang tidak ada di 3.1.
        $this->assertStringNotContainsString('Menyusui', $this->answerText('0>6'));
    }

    public function test_pnbp_is_listed_separately_never_summed(): void
    {
        // Feedback Imigrasi 20 Nov: "jangan langsung misal paspor rusak 5thn jadi 1.650.000, sebutin paspor 5thn 650rb dendanya 1jt".
        $expected = [
            '0>2' => ['Paspor elektronik 5 tahun: Rp650.000', 'Paspor elektronik 10 tahun: Rp950.000'],
            '0>4' => ['Paspor elektronik 5 tahun: Rp650.000'],
            '0>7' => ['Paspor elektronik 5 tahun: Rp650.000', 'Paspor elektronik 10 tahun: Rp950.000', 'Denda paspor hilang: Rp1.000.000'],
            '0>8' => ['Paspor elektronik 5 tahun: Rp650.000', 'Paspor elektronik 10 tahun: Rp950.000', 'Denda paspor rusak: Rp500.000'],
            '0>12' => ['Paspor elektronik 5 tahun: Rp650.000', 'Paspor elektronik 10 tahun: Rp950.000'],
        ];

        foreach ($expected as $path => $lines) {
            $text = $this->answerText($path);
            $this->assertStringContainsString('*PNBP (PP Nomor 45 Tahun 2024):*', $text, $path);

            foreach ($lines as $line) {
                $this->assertStringContainsString('• '.$line, $text, $path);
            }
        }

        $this->assertStringNotContainsString('10 tahun', $this->answerText('0>4'), 'Paspor anak berlaku 5 tahun (ANK-004).');
        $this->assertStringNotContainsString('Denda paspor rusak', $this->answerText('0>7'));
        $this->assertStringNotContainsString('Denda paspor hilang', $this->answerText('0>8'));

        // Tidak ada angka gabungan di jawaban mana pun (tarif dasar + denda/percepatan).
        foreach (array_keys(BotScript::ANSWERS) as $path) {
            if ($path === '1>3') {
                continue;
            }

            foreach (['1.150.000', '1.450.000', '1.650.000', '1.950.000'] as $total) {
                $this->assertStringNotContainsString($total, $this->answerText($path), "{$path}: angka gabungan {$total}");
            }

            if (str_starts_with($path, '1>')) {
                $this->assertArrayNotHasKey('pnbp', BotScript::ANSWERS[$path], "{$path}: tarif WNA dari FAQ TRF-005..010, bukan PNBP paspor");
            }
        }
    }

    public function test_pnbp_block_rejects_unknown_component(): void
    {
        $this->expectException(InvalidArgumentException::class);

        BotScript::pnbpBlock(['paspor_5', 'paspor_rusak_5']);
    }

    public function test_answers_have_no_double_spaces_that_gateway_would_collapse(): void
    {
        // BalesOtomatis memadatkan spasi ganda pada echo; teks bot dijaga tanpa spasi ganda / indentasi.
        foreach (array_keys(BotScript::ANSWERS) as $path) {
            if ($path !== '1>3') {
                $this->assertDoesNotMatchRegularExpression('/ {2,}/', BotScript::answer($this->answerText($path)), $path);
            }
        }
    }

    public function test_status_prompt_example_uses_cilacap_office_code_1568(): void
    {
        // Feedback Imigrasi 23 Nov (WA0010): "contohnya diganti ya kode kita depannya 1568xxxxx".
        $this->assertSame('1568000000012345', config('kancil.application_number_example'));
        $this->assertMatchesRegularExpression('/^1568\d{12}$/', config('kancil.application_number_example'));
        $this->assertStringContainsString("Contoh: 1568000000012345\n", BotScript::statusPrompt());
        $this->assertStringNotContainsString('1498', BotScript::statusPrompt());

        config(['kancil.application_number_example' => '1568999999999999']);
        $this->assertStringContainsString('Contoh: 1568999999999999', BotScript::statusPrompt(), 'Contoh diambil dari config.');
    }

    public function test_bot_source_has_no_old_1498_example(): void
    {
        foreach (['app/Services/Bot/BotScript.php', 'app/Services/Bot/BotEngine.php', 'config/kancil.php', 'database/data/faqs.php'] as $file) {
            $this->assertStringNotContainsString('1498', file_get_contents(base_path($file)), $file);
        }
    }

    public function test_example_number_passes_the_status_number_validation(): void
    {
        $example = (string) config('kancil.application_number_example');

        $this->assertMatchesRegularExpression('/^\d{10,25}$/', $example, 'Contoh harus lolos validasi langkah 1 cek status.');
        $this->assertSame(['MPP Banyumas', $example], BotEngine::applicationPlace("{$example} MPP Banyumas"));
    }
}
