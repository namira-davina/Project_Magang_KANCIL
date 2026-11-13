<?php

namespace Tests\Feature;

use App\Models\BotSetting;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Faq;
use App\Models\Message;
use App\Services\Bot\BotScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Skenario 1–58 Dokumen Skenario FAQ + kode Matriks Eskalasi, dijalankan langsung pada BotEngine.
 */
class BotScenarioTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->atOfficeHours();
        $this->configureIntegration('laravel');
        $this->seedFaqs();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Skenario 1–43: jalur menu → jawaban FAQ, tawaran 88, dan kode eskalasi saat 88 diketik.
     *
     * @return array<string, array{list<string>, list<string>, ?string}>
     */
    public static function faqScenarios(): array
    {
        return [
            'S1 paspor baru' => [['1', '1', '1'], ['PSP-001'], null],
            'S2 penggantian' => [['1', '1', '2'], ['PSP-002'], 'A4'],
            'S3 wisata' => [['1', '1', '3', '1'], ['PSP-003'], null],
            'S4 belajar' => [['1', '1', '3', '2'], ['PSP-004'], null],
            'S5 berobat' => [['1', '1', '3', '3'], ['PSP-005'], null],
            'S6 CPMI' => [['1', '1', '3', '4'], ['PSP-006'], null],
            'S7 pelaut' => [['1', '1', '3', '5'], ['PSP-007'], null],
            'S8 umrah' => [['1', '1', '3', '6'], ['PSP-008'], null],
            'S9 haji' => [['1', '1', '3', '7'], ['PSP-009'], null],
            'S10 magang' => [['1', '1', '3', '8'], ['PSP-010'], null],
            'S11 paspor hilang' => [['1', '1', '5'], ['PSP-011'], 'C1'],
            'S12 affidavit' => [['1', '1', '6'], ['PSP-012'], 'C3'],
            'S13 anak syarat umum' => [['1', '1', '4', '1'], ['ANK-001', 'ANK-002', 'ANK-003', 'ANK-004'], null],
            'S14 orang tua cerai' => [['1', '1', '4', '2'], ['ANK-005'], 'C2'],
            'S15 orang tua meninggal' => [['1', '1', '4', '3'], ['ANK-006', 'ANK-007', 'ANK-008'], 'C2'],
            'S16 adopsi' => [['1', '1', '4', '4'], ['ANK-009'], 'C2'],
            'S17 ATM' => [['1', '2', '1'], ['BYR-001'], null],
            'S18 m-banking' => [['1', '2', '2'], ['BYR-002'], null],
            'S19 aplikasi' => [['1', '2', '3'], ['BYR-003'], null],
            'S20 pos/minimarket' => [['1', '2', '4'], ['BYR-004'], null],
            'S21 ambil sendiri' => [['1', '3', '1'], ['AMB-001'], null],
            'S22 diwakilkan satu KK' => [['1', '3', '2'], ['AMB-002'], null],
            'S23 diwakilkan beda KK' => [['1', '3', '3'], ['AMB-003'], null],
            'S24 cek status' => [['1', '4'], ['AMB-009'], null],
            'S25 pengiriman pos' => [['1', '7'], ['AMB-004', 'AMB-005', 'AMB-006', 'AMB-007', 'AMB-008'], 'C5'],
            'S26 tarif paspor' => [['1', '6'], ['TRF-001', 'TRF-002', 'TRF-003', 'TRF-004', 'TRF-011'], 'A4'],
            'S27 re-entry' => [['2', '1', '1'], ['TRF-005', 'TRF-011'], null],
            'S28 ITK' => [['2', '1', '2'], ['TRF-006', 'TRF-011'], null],
            'S29 ITAS' => [['2', '1', '3'], ['TRF-007', 'TRF-011'], null],
            'S30 ITAP' => [['2', '1', '4'], ['TRF-008', 'TRF-011'], null],
            'S31 exit permit' => [['2', '1', '5'], ['TRF-009', 'TRF-011'], null],
            'S32 visa beberapa kali' => [['2', '1', '6'], ['TRF-010', 'TRF-011'], null],
            'S34 KANCIL NARI' => [['1', '5', '1'], ['LYK-002'], null],
            'S35 KANCIL PERSAMI' => [['1', '5', '2'], ['LYK-003'], 'A4'],
            'S36 KANCIL PESAT' => [['1', '5', '3'], ['LYK-004', 'LYK-005'], 'C4'],
            'S37 Eazy Paspor' => [['1', '5', '4'], ['LYK-006', 'LYK-007', 'LYK-008'], null],
            'S38 KANCIL NGAPAK' => [['1', '5', '5'], ['LYK-009'], 'A4'],
            'S39 HOTS' => [['1', '5', '6'], ['LYK-010', 'LYK-011'], null],
            'S40 WNA meninggal: pelapor' => [['2', '2', '1'], ['WNA-001', 'WNA-002'], 'C6'],
            'S41 WNA meninggal: dokumen' => [['2', '2', '2'], ['WNA-003'], 'C6'],
            'S42 WNA meninggal: alur' => [['2', '2', '3'], ['WNA-004'], 'C6'],
        ];
    }

    #[DataProvider('faqScenarios')]
    public function test_faq_scenario(array $path, array $faqCodes, ?string $escalationCode): void
    {
        $this->assertSame(['M-00'], $this->reply('Halo')->codes);

        $answer = $this->conversation($path);

        $this->assertSame($faqCodes, $answer->codes);
        $faqs = Faq::whereIn('code', $faqCodes)->get()->keyBy('code');

        foreach ($faqCodes as $code) {
            $this->assertStringContainsString($faqs[$code]->answer, $answer->text, $code);
        }

        if (count($faqCodes) > 1) {
            $this->assertStringContainsString('*'.$faqs[$faqCodes[0]]->question.'*', $answer->text);
        }

        $this->assertStringStartsWith('*Berikut ini adalah informasi dan solusi yang Anda cari:*', $answer->text);
        $this->assertStringEndsWith(BotScript::answerFooter(), $answer->text);
        $this->assertSame($faqs[$faqCodes[0]]->id, $answer->faqId);
        $this->assertSame($faqs[$faqCodes[0]]->id, Message::where('sender_type', 'user')->latest('id')->value('faq_id'));

        $esk = $this->reply('1');
        $this->assertSame(['ESK-01', 'LYN'], $esk->codes, 'Ketik 1 setelah jawaban = hubungi Petugas.');
        $this->assertStringStartsWith(BotScript::handoff(), $esk->text);
        $this->assertStringNotContainsString('081217000900', $esk->text);
        $this->assertStringNotContainsString('cilacap@imigrasi.go.id', $esk->text);

        $log = EscalationLog::latest('id')->first();
        $expected = $escalationCode ?? 'A2';
        $this->assertSame($expected, $log->escalation_code);
        $this->assertSame(BotScript::PRIORITY[$expected], $log->priority);

        if ($escalationCode) {
            $this->assertSame($faqs[$faqCodes[0]]->id, $log->faq_id);
            // Jalur internal tetap memakai nomor lama (WNI = 0, WNA = 1).
            $internal = [...[$path[0] === '1' ? '0' : '1'], ...array_slice($path, 1)];
            $this->assertSame(implode('>', $internal), $log->menu_path);
        }

        $this->assertSame(Conversation::MODE_WAITING, $this->chat->fresh()->conversation_mode);
        $this->assertSame(['M-00'], $this->reply('99')->codes);
    }

    public function test_main_menu_text_matches_document(): void
    {
        $text = $this->reply('Halo')->text;

        $this->assertMatchesRegularExpression('/^Selamat (Pagi|Siang|Sore|Malam), Halo/', $text);
        $this->assertStringContainsString('Selamat Datang di Layanan Informasi dan Pengaduan *Kantor Imigrasi Kelas I TPI Cilacap*.', $text);
        $this->assertStringContainsString("Silakan pilih menu di bawah ini:\n\nKetik *1* Pelayanan Paspor WNI\nKetik *2* Pelayanan Izin Tinggal (For Foreigners)\nKetik *3* Layanan Pengaduan (Complaint Support)\nKetik *4* Hubungi Petugas (Contact Officer)", $text);
        $this->assertStringEndsWith("Ketik angka pilihan Anda, lalu ikuti petunjuk selanjutnya\n------------\n_Type your selection, then follow the instructions_", $text);
        $this->assertStringNotContainsString('88', $text);
        $this->assertStringNotContainsString('langsung mengetik pertanyaan', $text, 'Tanpa AI tidak ada petunjuk tanya bebas.');
    }

    public function test_every_menu_lists_its_options_and_footer(): void
    {
        $this->reply('Halo');

        foreach ([['1', 'M-01'], ['1', 'M-02'], ['3', 'M-03'], ['9', 'M-02'], ['4', 'M-04'], ['9', 'M-02'], ['9', 'M-01'],
            ['2', 'M-05'], ['9', 'M-01'], ['3', 'M-06'], ['9', 'M-01'], ['5', 'M-07'], ['0', 'M-00'],
            ['2', 'M-08'], ['1', 'M-09'], ['9', 'M-08'], ['2', 'M-10']] as [$input, $menu]) {
            $reply = $this->reply($input);
            $this->assertSame([$menu], $reply->codes, "input {$input}");

            if ($menu !== 'M-00') {
                $this->assertStringStartsWith('*'.BotScript::MENUS[$menu]['title'].'*', $reply->text);
                $this->assertStringContainsString("Silakan pilih topik yang ingin Anda ketahui lebih lanjut:\n\nKetik *0* untuk kembali ke menu utama\n", $reply->text);
                $this->assertSame(BotScript::MENUS[$menu]['parent'] !== 'M-00', str_ends_with($reply->text, 'Ketik *9* untuk kembali ke menu sebelumnya'), $menu);
                foreach (BotScript::MENUS[$menu]['options'] as $key => [$label]) {
                    $this->assertStringContainsString("Ketik *{$key}* {$label}", $reply->text);
                }
            }
        }
    }

    public function test_s33_special_services_menu_shows_lyk_001_intro_when_published(): void
    {
        $reply = $this->conversation(['Halo', '1', '5']);

        $this->assertSame(['M-07'], $reply->codes);
        $this->assertStringContainsString('KANCIL NARI (layanan prioritas)', $reply->text);

        $this->holdFaq('LYK-001');
        $this->reply('9');
        $this->assertStringNotContainsString('layanan prioritas', $this->reply('5')->text);
    }

    public function test_s43_menu_hubungi_petugas_queues_without_contact_and_logs_a2_with_ktk_001(): void
    {
        $this->reply('Halo');
        $reply = $this->reply('4');

        $this->assertSame(['KTK-001', 'ESK-01', 'LYN'], $reply->codes);
        $this->assertStringStartsWith(BotScript::handoff(), $reply->text);
        $this->assertStringContainsString('masuk antrean', $reply->text);
        $this->assertStringNotContainsString('081217000900', $reply->text);
        $this->assertSame(Conversation::MODE_WAITING, $this->chat->fresh()->conversation_mode);
        $this->assertNull($this->chat->fresh()->bot_paused_until, 'Antrean tiket: bot diam sampai tiket ditutup.');
        $log = EscalationLog::sole();
        $this->assertSame('A2', $log->escalation_code);
        $this->assertSame('Rendah', $log->priority);
        $this->assertSame(Faq::where('code', 'KTK-001')->value('id'), $log->faq_id);
    }

    public function test_s44_three_invalid_inputs_then_88_logs_a1_once(): void
    {
        $this->reply('Halo');

        $first = $this->reply('paspor saya hilang di luar negeri');
        $this->assertSame(['MSG-01', 'M-00'], $first->codes);
        $this->assertStringStartsWith(BotScript::msg('MSG-01'), $first->text);
        $this->assertStringContainsString('Ketik *1* Pelayanan Paspor WNI', $first->text);

        $second = $this->reply('gimana ya?');
        $this->assertSame(['MSG-02', 'M-00'], $second->codes);

        $third = $this->reply('tolong bantu');
        $this->assertSame(['MSG-03'], $third->codes);
        $this->assertSame(BotScript::msg('MSG-03'), $third->text);

        $log = EscalationLog::sole();
        $this->assertSame('A1', $log->escalation_code);
        $this->assertSame('tolong bantu', $log->reason);

        $this->assertSame(['ESK-01', 'LYN'], $this->reply('88')->codes);
        $this->assertSame(1, EscalationLog::count(), '88 setelah MSG-03 tidak dicatat dua kali.');
        $this->assertSame(['M-00'], $this->reply('99')->codes);
    }

    public function test_greeting_mid_session_shows_main_menu_with_greeting(): void
    {
        $this->conversation(['Halo', '1', '1']);

        $reply = $this->reply('Siang kak');
        $this->assertSame(['M-00'], $reply->codes);
        $this->assertStringContainsString('Halo *Budi*', $reply->text);
        $this->assertSame(0, EscalationLog::count(), 'Sapaan bukan salah input.');
    }

    public function test_invalid_counter_resets_after_valid_input(): void
    {
        $this->reply('Halo');
        $this->reply('x');
        $this->reply('y');
        $this->assertSame(['M-01'], $this->reply('1')->codes);
        $this->assertSame(['MSG-01', 'M-01'], $this->reply('z')->codes, 'Hitungan salah input mulai dari awal.');
        $this->assertSame(['MSG-02', 'M-01'], $this->reply('8')->codes, 'Angka di luar pilihan juga salah input.');
    }

    public function test_after_answer_only_0_and_1_are_valid(): void
    {
        $this->conversation(['Halo', '1', '1', '1']);

        $invalid = $this->reply('abc');
        $this->assertSame(['MSG-01', 'M-02'], $invalid->codes);
        $this->assertStringEndsWith(BotScript::answerFooter(), $invalid->text, 'Salah input setelah jawaban mengulang pilihan 0/1.');
        $this->assertSame(['MSG-02', 'M-02'], $this->reply('2')->codes, 'Setelah jawaban, angka lain tidak memilih menu.');
        $this->assertSame(['M-02'], $this->reply('0')->codes, '0 setelah jawaban kembali ke menu jawaban.');
        $this->assertSame(['PSP-002'], $this->reply('2')->codes);
        $this->assertSame(['M-02'], $this->reply('9')->codes, '9 tetap diterima sebagai kembali.');
        $this->assertSame(['M-01'], $this->reply('9')->codes);
        $this->assertSame(['M-00'], $this->reply('0')->codes, '0 di menu = menu utama.');
    }

    public function test_s45_photo_outside_attachment_step_gets_msg_04(): void
    {
        $this->reply('Halo');
        $reply = $this->reply('', 'image');

        $this->assertSame(['MSG-04', 'M-00'], $reply->codes);
        $this->assertStringStartsWith(BotScript::msg('MSG-04'), $reply->text);
        $this->assertSame(['M-01'], $this->reply('1')->codes);
    }

    public function test_first_message_media_still_gets_main_menu(): void
    {
        $this->assertSame(['M-00'], $this->reply('', 'image')->codes);
    }

    public function test_s46_unpublished_answer_shows_msg_05_and_logs_a3(): void
    {
        $this->holdFaq('PSP-001');
        $reply = $this->conversation(['Halo', '1', '1', '1']);

        $this->assertSame(['MSG-05'], $reply->codes);
        $this->assertSame(BotScript::msg('MSG-05')."\n\n".BotScript::answerFooter(), $reply->text);
        $this->assertStringNotContainsString('081217000900', $reply->text);
        $log = EscalationLog::sole();
        $this->assertSame('A3', $log->escalation_code);
        $this->assertSame('Sedang', $log->priority);
        $this->assertSame(Faq::where('code', 'PSP-001')->value('id'), $log->faq_id);
        $this->assertSame('0>1>1', $log->menu_path);
    }

    public function test_faq_scope_all_shows_unverified_answer_with_note(): void
    {
        $this->holdFaq('PSP-001');
        config(['kancil.faq_scope' => 'all']);

        $reply = $this->conversation(['Halo', '1', '1', '1']);

        $this->assertSame(['PSP-001'], $reply->codes);
        $this->assertStringContainsString(Faq::where('code', 'PSP-001')->value('answer'), $reply->text);
        $this->assertStringContainsString(BotScript::msg('AI-DISCLAIMER'), $reply->text);

        Faq::where('code', 'PSP-002')->update(['verification_status' => 'draft']);
        $this->reply('0');
        $this->assertSame(['MSG-05'], $this->reply('2')->codes, 'Draf tetap tidak tampil.');
    }

    public function test_88_after_held_special_answer_logs_a2_not_c_code(): void
    {
        $this->holdFaq('PSP-011');
        $this->conversation(['Halo', '1', '1', '5']);
        $this->reply('1');

        $this->assertSame(['A3', 'A2'], EscalationLog::orderBy('id')->pluck('escalation_code')->all());
    }

    public function test_partially_published_answer_shows_published_part_and_msg_05(): void
    {
        $this->holdFaq('ANK-003');
        $reply = $this->conversation(['Halo', '1', '1', '4', '1']);

        $this->assertSame(['ANK-001', 'ANK-002', 'ANK-004', 'MSG-05'], $reply->codes);
        $this->assertStringContainsString(BotScript::msg('MSG-05'), $reply->text);
        $this->assertStringNotContainsString(Faq::where('code', 'ANK-003')->value('answer'), $reply->text);
        $this->assertSame('A3', EscalationLog::sole()->escalation_code);
    }

    public function test_s47_session_expires_after_30_minutes(): void
    {
        $this->reply('Halo');
        $this->reply('1');

        Carbon::setTestNow(now()->addMinutes(31));
        $reply = $this->reply('1');

        $this->assertSame(['MSG-06', 'M-00'], $reply->codes);
        $this->assertStringStartsWith(BotScript::msg('MSG-06'), $reply->text);
        $this->assertSame(['M-01'], $this->reply('1')->codes);
    }

    public function test_session_still_active_at_29_minutes(): void
    {
        $this->reply('Halo');
        $this->reply('1');

        Carbon::setTestNow(now()->addMinutes(29));
        $this->assertSame(['M-02'], $this->reply('1')->codes);
    }

    public function test_s48_navigation_with_9_and_99(): void
    {
        $this->conversation(['Halo', '1', '1', '3']);

        $this->assertSame(['M-02'], $this->reply('9')->codes);
        $this->assertSame(['M-00'], $this->reply('99')->codes);
        $this->reply('1');
        $this->assertSame(['M-00'], $this->reply('0')->codes, '0 = menu utama.');
        $this->assertSame(['M-00'], $this->reply('menu')->codes, '"menu" sama dengan 99.');
        $this->assertSame(['M-00'], $this->reply('9')->codes, '9 di menu utama tetap di menu utama.');
    }

    public function test_commands_tolerate_spaces_and_punctuation(): void
    {
        $this->reply('Halo');
        $this->assertSame(['M-01'], $this->reply(' 1. ')->codes);
        $this->assertSame(['M-00'], $this->reply('MENU!')->codes);
    }

    public function test_s49_outside_office_hours_adds_msg_07(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 20:00:00', 'Asia/Jakarta')); // Sabtu malam

        $this->reply('Halo');
        $menu4 = $this->reply('4');
        $this->assertSame(['KTK-001', 'ESK-01', 'LYN', 'MSG-07'], $menu4->codes);
        $this->assertStringStartsWith(BotScript::handoff(), $menu4->text);
        $this->assertStringContainsString('Saat ini di luar jam layanan petugas.', $menu4->text);
        $this->assertStringNotContainsString('jam layanan:', $menu4->text, 'Jam layanan belum dikonfirmasi, jadi tidak disebut.');

        $this->reply('1');
        $this->assertSame(['ESK-01', 'LYN', 'MSG-07'], $this->reply('88')->codes);
    }

    public function test_msg_07_mentions_service_hours_once_confirmed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 17:30:00', 'Asia/Jakarta'));
        BotSetting::where('setting_key', 'service_hours_text')->update(['setting_value' => 'Senin–Jumat 08.00–16.00 WIB']);

        $this->reply('Halo');
        $this->assertStringContainsString('jam layanan: Senin–Jumat 08.00–16.00 WIB._', $this->reply('88')->text);
    }

    public function test_msg_07_can_be_disabled(): void
    {
        config(['kancil.outside_hours_notice' => false]);
        Carbon::setTestNow(Carbon::parse('2026-11-15 10:00:00', 'Asia/Jakarta'));

        $this->reply('Halo');
        $this->assertSame(['ESK-01', 'LYN'], $this->reply('88')->codes);
    }

    // ------------------------------------------------------------ pengaduan

    public function test_s50_complaint_without_attachment(): void
    {
        $this->reply('Halo');
        $this->assertSame(['PGD-01'], $this->reply('3')->codes);
        $this->assertSame(['PGD-02'], $this->reply('1')->codes);
        $this->assertSame(['PGD-03'], $this->reply('Budi Santoso')->codes);
        $this->assertSame(['PGD-04'], $this->reply('1')->codes);
        $this->assertSame(['PGD-05'], $this->reply('Antrean loket paspor sangat lama tanpa kejelasan.')->codes);

        $summary = $this->reply('2');
        $this->assertSame(['PGD-07'], $summary->codes);
        $this->assertStringContainsString("Nama: Budi Santoso\nKategori: Layanan paspor\nIsi: Antrean loket paspor sangat lama tanpa kejelasan.\nLampiran: tidak ada", $summary->text);
        $this->assertSame(0, Complaint::count(), 'Data hanya tersimpan setelah memilih Kirim.');

        $done = $this->reply('1');
        $this->assertSame(['PGD-08'], $done->codes);
        $this->assertStringContainsString('ADU-202611-0001', $done->text);
        $this->assertStringContainsString('Status awal tiket: Baru.', $done->text);

        $complaint = Complaint::sole();
        $this->assertSame('ADU-202611-0001', $complaint->ticket_number);
        $this->assertSame('Menunggu', $complaint->status);
        $this->assertSame('Sedang', $complaint->priority);
        $this->assertSame(1, $complaint->escalation_level);
        $this->assertSame('Layanan paspor', $complaint->category->name);
        $this->assertSame('6281234567890', $complaint->whatsapp_number);
        $this->assertSame($this->chat->id, $complaint->conversation_id);

        $log = EscalationLog::sole();
        $this->assertSame('B1', $log->escalation_code);
        $this->assertSame('Sedang', $log->priority);

        $this->assertSame(['M-00'], $this->reply('99')->codes);
    }

    public function test_s51_complaint_with_attachments_max_three(): void
    {
        $this->conversation(['Halo', '3', '1', 'Siti', '3', 'Petugas loket bersikap tidak ramah saat melayani.']);
        $this->assertSame(['PGD-06'], $this->reply('1')->codes);

        foreach ([1, 2, 3] as $n) {
            $ack = $this->reply('', 'image', ['attachment_path' => "wa-media/x/{$n}.jpg"]);
            $this->assertStringContainsString("Berkas ke-{$n} diterima.", $ack->text);
        }

        $full = $this->reply('', 'document');
        $this->assertStringContainsString('Batas 3 berkas', $full->text);

        $this->assertSame(['MSG-01', 'PGD-06'], $this->reply('apa ini')->codes, 'Teks selain "selesai" ditolak.');
        $summary = $this->reply('selesai');
        $this->assertStringContainsString('Lampiran: 3 berkas', $summary->text);

        $this->reply('1');
        $complaint = Complaint::sole();
        $this->assertCount(3, $complaint->attachments);
        $this->assertSame('wa-media/x/1.jpg', $complaint->attachments[0]['path']);
        $this->assertSame('Tinggi', $complaint->priority, 'Kategori 3 berprioritas Tinggi.');
        $this->assertSame(2, $complaint->escalation_level, 'Kategori 3 langsung ke Pimpinan.');
    }

    public function test_s52_short_complaint_text_is_rejected(): void
    {
        $this->conversation(['Halo', '3', '1', 'Budi', '4']);

        $short = $this->reply('lambat sekali');
        $this->assertSame(['PGD-04'], $short->codes);
        $this->assertStringContainsString('minimal 20 karakter', $short->text);

        $this->assertSame(['PGD-05'], $this->reply('Pelayanan informasi lambat sekali hari ini.')->codes);
    }

    public function test_s53_cancel_mid_complaint(): void
    {
        $this->conversation(['Halo', '3', '1', 'Budi']);
        $reply = $this->reply('batal');

        $this->assertSame(['MSG-08', 'M-00'], $reply->codes);
        $this->assertStringStartsWith(BotScript::msg('MSG-08'), $reply->text);
        $this->assertSame(0, Complaint::count());
        $this->assertSame('B0', EscalationLog::sole()->escalation_code);
        $this->assertSame(['M-01'], $this->reply('1')->codes);
    }

    public function test_s54_cancel_on_summary(): void
    {
        $this->conversation(['Halo', '3', '1', 'Budi', '1', 'Isi pengaduan yang cukup panjang untuk diterima.', '2']);
        $this->assertSame(['MSG-08', 'M-00'], $this->reply('2')->codes);
        $this->assertSame(0, Complaint::count());
    }

    public function test_s55_three_invalid_inputs_in_complaint_discard_draft(): void
    {
        $this->conversation(['Halo', '3', '1', 'Budi']);

        $this->assertSame(['MSG-01', 'PGD-03'], $this->reply('abc')->codes);
        $this->assertSame(['MSG-02', 'PGD-03'], $this->reply('xyz')->codes);
        $this->assertSame(['MSG-03'], $this->reply('ok')->codes);

        $this->assertSame(['B0'], EscalationLog::pluck('escalation_code')->all(), '3x salah di pengaduan = B0, bukan A1.');
        $this->assertNull($this->chat->fresh()->session_state['complaint']);
        $this->assertSame(['M-00'], $this->reply('99')->codes);
    }

    public function test_s56_check_own_ticket_status_with_response(): void
    {
        $complaint = Complaint::create([
            'ticket_number' => 'ADU-202611-0007', 'conversation_id' => $this->startChat()->id, 'user_name' => 'Budi',
            'whatsapp_number' => '6281234567890', 'complaint_text' => 'x', 'status' => 'Diproses', 'response_text' => 'Sedang kami cek.',
        ]);

        $this->conversation(['Halo', '3']);
        $this->assertSame(['PGD-09'], $this->reply('2')->codes);
        $reply = $this->reply(' adu-202611-0007 ');

        $this->assertSame(['PGD-10'], $reply->codes);
        $this->assertStringContainsString('Status pengaduan *ADU-202611-0007*: *Diproses*.', $reply->text);
        $this->assertStringContainsString('Tanggapan petugas: Sedang kami cek.', $reply->text);

        $complaint->update(['status' => 'Menunggu', 'response_text' => null]);
        $again = $this->reply('ADU-202611-0007');
        $this->assertStringContainsString('*Baru*', $again->text, 'Status Menunggu tampil sebagai "Baru" ke pemohon.');
        $this->assertStringNotContainsString('Tanggapan petugas', $again->text);
    }

    public function test_ticket_found_by_same_number_from_other_conversation(): void
    {
        Complaint::create([
            'ticket_number' => 'ADU-202610-0001', 'user_name' => 'Budi', 'whatsapp_number' => '6281234567890',
            'complaint_text' => 'x', 'status' => 'Selesai',
        ]);

        $this->conversation(['Halo', '3', '2']);
        $this->assertStringContainsString('*Selesai*', $this->reply('ADU-202610-0001')->text);
    }

    public function test_s57_ticket_of_other_number_is_not_shown(): void
    {
        Complaint::create([
            'ticket_number' => 'ADU-202611-0009', 'user_name' => 'Orang Lain', 'whatsapp_number' => '6289999999999',
            'complaint_text' => 'rahasia', 'status' => 'Diproses', 'response_text' => 'jangan bocor',
        ]);

        $this->conversation(['Halo', '3', '2']);
        $reply = $this->reply('ADU-202611-0009');

        $this->assertSame(['PGD-10'], $reply->codes);
        $this->assertStringContainsString('tidak ditemukan', $reply->text);
        $this->assertStringNotContainsString('jangan bocor', $reply->text);
        $this->assertStringContainsString('tidak ditemukan', $this->reply('ADU-000000-0000')->text);
    }

    public function test_s58_session_expires_before_complaint_sent(): void
    {
        $this->conversation(['Halo', '3', '1', 'Budi']);
        Carbon::setTestNow(now()->addMinutes(45));

        $reply = $this->reply('1');
        $this->assertSame(['MSG-09', 'M-00'], $reply->codes);
        $this->assertStringStartsWith(BotScript::msg('MSG-09'), $reply->text);
        $this->assertSame('B0', EscalationLog::sole()->escalation_code);
        $this->assertSame(0, Complaint::count());
    }

    public function test_complaint_back_navigation_with_9(): void
    {
        $this->conversation(['Halo', '3']);
        $this->assertSame(['M-00'], $this->reply('9')->codes, '9 di PGD-01 kembali ke menu utama.');

        $this->conversation(['3', '1', 'Budi', '1']);
        $this->assertSame(['PGD-03'], $this->reply('9')->codes);
        $this->assertSame(['PGD-02'], $this->reply('9')->codes);
        $this->assertSame(['PGD-01'], $this->reply('9')->codes);
        $this->assertSame(['PGD-09'], $this->reply('2')->codes);
        $this->assertSame(['PGD-01'], $this->reply('9')->codes);
    }

    public function test_88_and_99_inside_complaint_discard_draft(): void
    {
        $this->conversation(['Halo', '3', '1', 'Budi']);
        $this->assertSame(['ESK-01', 'LYN'], $this->reply('88')->codes);
        $this->assertSame(['B0', 'A2'], EscalationLog::orderBy('id')->pluck('escalation_code')->all());

        $this->conversation(['99', '3', '1', 'Budi']);
        $this->assertSame(['M-00'], $this->reply('99')->codes);
        $this->assertSame(2, EscalationLog::where('escalation_code', 'B0')->count());
    }

    public function test_cancel_without_draft_does_not_log_b0(): void
    {
        $this->conversation(['Halo', '3']);
        $this->assertSame(['MSG-08', 'M-00'], $this->reply('batal')->codes);
        $this->assertSame(0, EscalationLog::count());
    }

    public function test_complaint_numbering_and_repeat_goes_to_level_2(): void
    {
        foreach (['ADU-202611-0001', 'ADU-202611-0002'] as $expected) {
            $this->conversation(['99', '3', '1', 'Budi', '4', 'Isi pengaduan yang cukup panjang untuk diterima.', '2']);
            $this->assertStringContainsString($expected, $this->reply('1')->text);
        }

        $complaints = Complaint::orderBy('id')->get();
        $this->assertSame('Rendah', $complaints[0]->priority);
        $this->assertSame(1, $complaints[0]->escalation_level);
        $this->assertSame(2, $complaints[1]->escalation_level, 'Pengaduan berulang naik ke Pimpinan.');
    }

    public function test_media_in_non_attachment_complaint_step_gets_msg_04_with_prompt(): void
    {
        $this->conversation(['Halo', '3', '1']);
        $this->assertSame(['MSG-04', 'PGD-02'], $this->reply('', 'image')->codes);
    }

    public function test_empty_name_is_asked_again(): void
    {
        $this->conversation(['Halo', '3', '1']);
        $this->assertSame(['PGD-02'], $this->reply('  ')->codes);
    }

    public function test_session_state_and_current_menu_are_persisted(): void
    {
        $this->conversation(['Halo', '1', '1']);
        $conversation = $this->chat->fresh();

        $this->assertSame('M-02', $conversation->current_menu);
        $this->assertSame('M-02', $conversation->session_state['menu']);
    }

    public function test_escalation_moves_bot_conversation_to_waiting_but_keeps_human_mode(): void
    {
        $this->startChat(['conversation_mode' => Conversation::MODE_HUMAN]);
        $this->reply('Halo');
        $this->reply('88');

        $this->assertSame(Conversation::MODE_HUMAN, $this->chat->fresh()->conversation_mode);
    }

    public function test_bot_works_when_faq_rows_are_missing(): void
    {
        Faq::where('code', 'PSP-001')->delete();

        $reply = $this->conversation(['Halo', '1', '1', '1']);
        $this->assertSame(['MSG-05'], $reply->codes);
    }
}
