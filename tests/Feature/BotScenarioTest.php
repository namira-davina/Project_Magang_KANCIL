<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\ServiceTicket;
use App\Services\Bot\BotScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Menu bot meniru layanan WhatsApp Imigrasi Bogor (tanpa Drive Thru dan persyaratan berdasarkan tujuan),
 * ditambah status hari ini, eskalasi Matriks Eskalasi, dan alur pengaduan PGD (dormant: tidak ada di menu utama).
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
     * Jalur menu => potongan teks jawaban, jalur internal, dan kode eskalasi saat "Ketik 1".
     *
     * @return array<string, array{list<string>, string, string, ?string}>
     */
    public static function answerScenarios(): array
    {
        return [
            'biaya paspor' => [['1', '1'], 'PNBP Paspor Elektronik 5 Tahun : Rp 650.000', '0>1', null],
            'paspor baru' => [['1', '2'], 'Dokumen persyaratan Paspor Baru:', '0>2', null],
            'penggantian paspor' => [['1', '3'], 'Dokumen persyaratan Penggantian Paspor:', '0>3', null],
            'paspor anak' => [['1', '4'], 'Persyaratan Paspor Anak (Orang Tua Bercerai):', '0>4', 'C2'],
            'pendaftaran m-paspor' => [['1', '5'], 'Silakan unduh aplikasi M-Paspor:', '0>5', null],
            'layanan prioritas' => [['1', '6'], 'Layanan prioritas diperuntukkan bagi:', '0>6', null],
            'paspor hilang' => [['1', '7'], 'Surat Kehilangan dari Kepolisian', '0>7', 'C1'],
            'paspor rusak' => [['1', '8'], 'Paspor Lama yang rusak', '0>8', null],
            'penambahan nama' => [['1', '9'], 'Persyaratan Penambahan Nama:', '0>9', null],
            'lokasi kami' => [['2', '3'], 'Jl. Urip Sumoharjo No.249', '1>3', null],
        ];
    }

    #[DataProvider('answerScenarios')]
    public function test_answer_scenario(array $path, string $fragment, string $internalPath, ?string $escalationCode): void
    {
        $this->assertSame(['M-00'], $this->reply('Halo')->codes);

        $answer = $this->conversation($path);

        $this->assertSame(['ANS-'.$internalPath], $answer->codes);
        $this->assertStringContainsString($fragment, $answer->text);
        $this->assertStringStartsWith('*Berikut ini adalah informasi dan solusi yang Anda cari:*', $answer->text);
        $this->assertStringEndsWith(BotScript::answerFooter(), $answer->text);

        $esk = $this->reply('1');
        $this->assertSame(['ESK-01', 'LYN'], $esk->codes, 'Ketik 1 setelah jawaban = hubungi Petugas.');
        $this->assertHandoff($esk->text);

        $log = EscalationLog::latest('id')->first();
        $this->assertSame($escalationCode ?? 'A2', $log->escalation_code);
        $this->assertSame(BotScript::PRIORITY[$escalationCode ?? 'A2'], $log->priority);

        if ($escalationCode) {
            $this->assertSame($internalPath, $log->menu_path);
        }

        $this->assertSame(Conversation::MODE_WAITING, $this->chat->fresh()->conversation_mode);
        $this->assertSame(['M-00'], $this->reply('99')->codes);
    }

    public function test_main_menu_matches_bogor_with_cilacap_identity_and_status(): void
    {
        $text = $this->reply('Halo')->text;

        $this->assertSame(
            "Selamat Pagi, Halo *Budi*\n\n"
            ."Selamat Datang , di Layanan Informasi dan Pengaduan Kantor Imigrasi Kelas I TPI Cilacap.\n\n"
            ."*Status Hari Ini: BUKA*\nWaktu pelayanan tersedia setiap hari Senin–Jumat pukul 08.00–16.00 WIB.\n\n"
            ."Silakan pilih menu di bawah ini:\n\n"
            ."Ketik *1* Pelayanan Paspor WNI\nKetik *2* Pelayanan Izin Tinggal (For Foreigners)\nKetik *3* Layanan Pengaduan (Complaint Support)\n\n"
            ."Ketik angka pilihan Anda, lalu ikuti petunjuk selanjutnya\n------------\n_Type your selection, then follow the instructions_",
            $text,
        );
        $this->assertStringNotContainsString('Bogor', $text);
        $this->assertStringNotContainsString('langsung mengetik pertanyaan', $text);
    }

    public function test_passport_menu_matches_bogor_without_drive_thru_and_purpose(): void
    {
        $this->reply('Halo');
        $reply = $this->reply('1');

        $this->assertSame(['M-01'], $reply->codes);
        $this->assertSame(
            "Silakan pilih topik yang ingin Anda ketahui lebih lanjut:\n\n"
            ."Ketik *0* untuk kembali ke menu utama\n"
            ."Ketik *1* Biaya Paspor\nKetik *2* Persyaratan Paspor Baru\nKetik *3* Persyaratan Penggantian Paspor\n"
            ."Ketik *4* Persyaratan Paspor Anak\nKetik *5* Pendaftaran Paspor (M-Paspor)\nKetik *6* Layanan Prioritas Ramah HAM\n"
            ."Ketik *7* Permohonan Paspor Hilang\nKetik *8* Permohonan Paspor Rusak\nKetik *9* Penambahan Nama pada Paspor\n"
            ."Ketik *10* Cek Status Paspor\nKetik *11* untuk kembali ke menu sebelumnya",
            $reply->text,
        );
        $this->assertStringNotContainsString('Drive Thru', $reply->text);
        $this->assertStringNotContainsString('Tujuan', $reply->text);
        $this->assertStringNotContainsString('Affidavit', $reply->text);

        foreach (BotScript::ANSWERS as $answer) {
            $this->assertStringNotContainsString('Drive Thru', (string) $answer['text']);
        }
    }

    public function test_foreigner_menu_matches_bogor(): void
    {
        $this->reply('Halo');
        $reply = $this->reply('2');

        $this->assertSame(['M-08'], $reply->codes);
        $this->assertSame(
            "Silakan pilih topik yang ingin Anda ketahui lebih lanjut:\n\nKetik *0* untuk kembali ke menu utama\n"
            ."Ketik *1* Izin Tinggal (Residence Permit)\nKetik *2* Pengaduan Orang Asing (Foreigner Complaint)\n"
            ."Ketik *3* Lokasi Kami\nKetik *4* untuk kembali ke menu sebelumnya",
            $reply->text,
        );

        foreach (['1' => '1>1', '2' => '1>2'] as $key => $path) {
            $this->reply('99');
            $this->reply('2');
            $queued = $this->reply($key);
            $this->assertSame(['ESK-01', 'LYN'], $queued->codes);
            $this->assertHandoff($queued->text);
            $this->assertSame($path, EscalationLog::latest('id')->first()->menu_path);
            $this->chat->update(['conversation_mode' => Conversation::MODE_BOT]);
        }
    }

    public function test_location_uses_cilacap_identity(): void
    {
        $text = $this->conversation(['Halo', '2', '3'])->text;

        $this->assertStringContainsString('📍 Kantor Imigrasi Kelas I TPI Cilacap', $text);
        $this->assertStringContainsString('Pantusan, Gumilir', $text);
        $this->assertStringNotContainsString('Bogor', $text);
        $this->assertStringNotContainsString('Tanah Sareal', $text);
    }

    public function test_menu_3_queues_officer_like_bogor_and_logs_a2(): void
    {
        $this->reply('Halo');
        $reply = $this->reply('3');

        $this->assertSame(['ESK-01', 'LYN'], $reply->codes);
        $this->assertHandoff($reply->text);
        $this->assertSame(Conversation::MODE_WAITING, $this->chat->fresh()->conversation_mode);
        $this->assertNull($this->chat->fresh()->bot_paused_until, 'Antrean tiket: bot diam sampai tiket ditutup.');

        $log = EscalationLog::sole();
        $this->assertSame('A2', $log->escalation_code);
        $this->assertSame('3', $log->menu_path);
        $this->assertSame(1, ServiceTicket::count());
    }

    public function test_zero_after_answer_and_back_options_return_to_main_menu_with_greeting(): void
    {
        $this->conversation(['Halo', '1', '2']);
        $afterAnswer = $this->reply('0');
        $this->assertSame(['M-00'], $afterAnswer->codes, '0 setelah jawaban menampilkan menu utama (perilaku Bogor).');
        $this->assertStringStartsWith('Selamat Pagi, Halo *Budi*', $afterAnswer->text);

        $this->reply('1');
        $this->assertSame(['M-00'], $this->reply('11')->codes, '11 = kembali ke menu sebelumnya (menu utama).');

        $this->reply('2');
        $this->assertSame(['M-00'], $this->reply('4')->codes);

        $this->reply('1');
        $this->assertSame(['M-00'], $this->reply('0')->codes, '0 di submenu = menu utama.');
    }

    public function test_status_hari_ini_follows_day_and_time(): void
    {
        $cases = [
            '2026-11-14 10:00:00' => '*Status Hari Ini: LIBUR (Akhir Pekan)*',
            '2026-11-15 10:00:00' => '*Status Hari Ini: LIBUR (Akhir Pekan)*',
            '2026-12-25 10:00:00' => '*Status Hari Ini: LIBUR (Hari Raya Natal)*',
            '2027-03-10 10:00:00' => '*Status Hari Ini: LIBUR (Hari Raya Idul Fitri 1448 H)*',
            '2027-05-19 10:00:00' => '*Status Hari Ini: LIBUR (Cuti Bersama Waisak)*',
            '2026-11-13 07:59:00' => '*Status Hari Ini: TUTUP (di luar jam pelayanan)*',
            '2026-11-13 16:00:00' => '*Status Hari Ini: TUTUP (di luar jam pelayanan)*',
            '2026-11-13 15:59:00' => '*Status Hari Ini: BUKA*',
        ];

        foreach ($cases as $at => $expected) {
            Carbon::setTestNow(Carbon::parse($at, 'Asia/Jakarta'));
            $this->chat = null;
            $this->startChat(['wa_chat_id' => 'c'.md5($at), 'whatsapp_number' => '62'.random_int(100000000, 999999999)]);

            $text = $this->reply('Halo')->text;
            $this->assertStringContainsString($expected."\nWaktu pelayanan tersedia setiap hari Senin–Jumat pukul 08.00–16.00 WIB.", $text, $at);
        }
    }

    public function test_handoff_text_is_same_outside_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 20:00:00', 'Asia/Jakarta'));

        $this->reply('Halo');
        $this->assertHandoff($this->reply('3')->text);
    }

    public function test_cek_status_asks_number_then_queues_officer_with_the_number(): void
    {
        $prompt = $this->conversation(['Halo', '1', '10']);

        $this->assertSame(['STATUS'], $prompt->codes);
        $this->assertSame("Silakan ketik *Nomor Permohonan* paspor Anda.\nContoh: 1498000000012345\n\nKetik *0* untuk kembali ke menu utama.", $prompt->text);

        $this->assertSame(['MSG-01', 'M-01'], $this->reply('bukan nomor')->codes);

        $queued = $this->reply('1498 0000 0001 2345');
        $this->assertSame(['STATUS', 'LYN'], $queued->codes);
        $this->assertHandoff($queued->text);
        $this->assertStringContainsString('1498000000012345', ServiceTicket::sole()->question_text);
        $this->assertSame(Conversation::MODE_WAITING, $this->chat->fresh()->conversation_mode);
    }

    public function test_cek_status_zero_returns_to_main_menu(): void
    {
        $this->conversation(['Halo', '1', '10']);
        $this->assertSame(['M-00'], $this->reply('0')->codes);
        $this->assertSame(0, ServiceTicket::count());
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
        $this->assertSame(['MSG-02', 'M-01'], $this->reply('12')->codes, 'Angka di luar pilihan juga salah input.');
    }

    public function test_9_is_a_normal_option_not_a_back_command(): void
    {
        $this->reply('Halo');
        $this->assertSame(['MSG-01', 'M-00'], $this->reply('9')->codes, '9 tidak ada di menu utama.');
        $this->reply('1');
        $this->assertSame(['ANS-0>9'], $this->reply('9')->codes, '9 di menu paspor = Penambahan Nama pada Paspor.');
    }

    public function test_after_answer_only_0_and_1_are_valid(): void
    {
        $this->conversation(['Halo', '1', '2']);

        $invalid = $this->reply('abc');
        $this->assertSame(['MSG-01', 'M-01'], $invalid->codes);
        $this->assertStringEndsWith(BotScript::answerFooter(), $invalid->text, 'Salah input setelah jawaban mengulang pilihan 0/1.');
        $this->assertSame(['MSG-02', 'M-01'], $this->reply('3')->codes, 'Setelah jawaban, angka lain tidak memilih menu.');
        $this->assertSame(['M-00'], $this->reply('0')->codes);
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

    public function test_answers_do_not_depend_on_faq_verification(): void
    {
        $this->holdFaq('PSP-001', 'PSP-011');

        $this->assertSame(['ANS-0>2'], $this->conversation(['Halo', '1', '2'])->codes);
        $this->reply('0');
        $this->assertSame(['ANS-0>7'], $this->conversation(['1', '7'])->codes);
    }

    public function test_88_after_special_answer_logs_matrix_code(): void
    {
        $this->conversation(['Halo', '1', '7']);
        $this->reply('1');

        $this->assertSame(['C1'], EscalationLog::pluck('escalation_code')->all());
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
        $this->assertSame(['ANS-0>1'], $this->reply('1')->codes);
    }

    public function test_navigation_with_99_and_menu(): void
    {
        $this->conversation(['Halo', '1', '3']);

        $this->assertSame(['M-00'], $this->reply('99')->codes);
        $this->reply('1');
        $this->assertSame(['M-00'], $this->reply('menu')->codes, '"menu" sama dengan 99.');
    }

    public function test_commands_tolerate_spaces_and_punctuation(): void
    {
        $this->reply('Halo');
        $this->assertSame(['M-01'], $this->reply(' 1. ')->codes);
        $this->assertSame(['M-00'], $this->reply('MENU!')->codes);
    }

    public function test_session_state_and_current_menu_are_persisted(): void
    {
        $this->conversation(['Halo', '1']);
        $conversation = $this->chat->fresh();

        $this->assertSame('M-01', $conversation->current_menu);
        $this->assertSame('M-01', $conversation->session_state['menu']);
    }

    public function test_escalation_moves_bot_conversation_to_waiting_but_keeps_human_mode(): void
    {
        $this->startChat(['conversation_mode' => Conversation::MODE_HUMAN]);
        $this->reply('Halo');
        $this->reply('88');

        $this->assertSame(Conversation::MODE_HUMAN, $this->chat->fresh()->conversation_mode);
    }

    // ------------------------------------------------------------ pengaduan

    public function test_s50_complaint_without_attachment(): void
    {
        $this->complaintFlow();
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
        $this->complaintFlow(['1', 'Siti', '3', 'Petugas loket bersikap tidak ramah saat melayani.']);
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
        $this->complaintFlow(['1', 'Budi', '4']);

        $short = $this->reply('lambat sekali');
        $this->assertSame(['PGD-04'], $short->codes);
        $this->assertStringContainsString('minimal 20 karakter', $short->text);

        $this->assertSame(['PGD-05'], $this->reply('Pelayanan informasi lambat sekali hari ini.')->codes);
    }

    public function test_s53_cancel_mid_complaint(): void
    {
        $this->complaintFlow(['1', 'Budi']);
        $reply = $this->reply('batal');

        $this->assertSame(['MSG-08', 'M-00'], $reply->codes);
        $this->assertStringStartsWith(BotScript::msg('MSG-08'), $reply->text);
        $this->assertSame(0, Complaint::count());
        $this->assertSame('B0', EscalationLog::sole()->escalation_code);
        $this->assertSame(['M-01'], $this->reply('1')->codes);
    }

    public function test_s54_cancel_on_summary(): void
    {
        $this->complaintFlow(['1', 'Budi', '1', 'Isi pengaduan yang cukup panjang untuk diterima.', '2']);
        $this->assertSame(['MSG-08', 'M-00'], $this->reply('2')->codes);
        $this->assertSame(0, Complaint::count());
    }

    public function test_s55_three_invalid_inputs_in_complaint_discard_draft(): void
    {
        $this->complaintFlow(['1', 'Budi']);

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

        $this->complaintFlow();
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

        $this->complaintFlow(['2']);
        $this->assertStringContainsString('*Selesai*', $this->reply('ADU-202610-0001')->text);
    }

    public function test_s57_ticket_of_other_number_is_not_shown(): void
    {
        Complaint::create([
            'ticket_number' => 'ADU-202611-0009', 'user_name' => 'Orang Lain', 'whatsapp_number' => '6289999999999',
            'complaint_text' => 'rahasia', 'status' => 'Diproses', 'response_text' => 'jangan bocor',
        ]);

        $this->complaintFlow(['2']);
        $reply = $this->reply('ADU-202611-0009');

        $this->assertSame(['PGD-10'], $reply->codes);
        $this->assertStringContainsString('tidak ditemukan', $reply->text);
        $this->assertStringNotContainsString('jangan bocor', $reply->text);
        $this->assertStringContainsString('tidak ditemukan', $this->reply('ADU-000000-0000')->text);
    }

    public function test_s58_session_expires_before_complaint_sent(): void
    {
        $this->complaintFlow(['1', 'Budi']);
        Carbon::setTestNow(now()->addMinutes(45));

        $reply = $this->reply('1');
        $this->assertSame(['MSG-09', 'M-00'], $reply->codes);
        $this->assertStringStartsWith(BotScript::msg('MSG-09'), $reply->text);
        $this->assertSame('B0', EscalationLog::sole()->escalation_code);
        $this->assertSame(0, Complaint::count());
    }

    public function test_complaint_back_navigation_with_9(): void
    {
        $this->complaintFlow();
        $this->assertSame(['M-00'], $this->reply('9')->codes, '9 di PGD-01 kembali ke menu utama.');

        $this->complaintFlow(['1', 'Budi', '1']);
        $this->assertSame(['PGD-03'], $this->reply('9')->codes);
        $this->assertSame(['PGD-02'], $this->reply('9')->codes);
        $this->assertSame(['PGD-01'], $this->reply('9')->codes);
        $this->assertSame(['PGD-09'], $this->reply('2')->codes);
        $this->assertSame(['PGD-01'], $this->reply('9')->codes);
    }

    public function test_88_and_99_inside_complaint_discard_draft(): void
    {
        $this->complaintFlow(['1', 'Budi']);
        $this->assertSame(['ESK-01', 'LYN'], $this->reply('88')->codes);
        $this->assertSame(['B0', 'A2'], EscalationLog::orderBy('id')->pluck('escalation_code')->all());

        $this->complaintFlow(['1', 'Budi']);
        $this->assertSame(['M-00'], $this->reply('99')->codes);
        $this->assertSame(2, EscalationLog::where('escalation_code', 'B0')->count());
    }

    public function test_cancel_without_draft_does_not_log_b0(): void
    {
        $this->complaintFlow();
        $this->assertSame(['MSG-08', 'M-00'], $this->reply('batal')->codes);
        $this->assertSame(0, EscalationLog::count());
    }

    public function test_complaint_numbering_and_repeat_goes_to_level_2(): void
    {
        foreach (['ADU-202611-0001', 'ADU-202611-0002'] as $expected) {
            $this->complaintFlow(['1', 'Budi', '4', 'Isi pengaduan yang cukup panjang untuk diterima.', '2']);
            $this->assertStringContainsString($expected, $this->reply('1')->text);
        }

        $complaints = Complaint::orderBy('id')->get();
        $this->assertSame('Rendah', $complaints[0]->priority);
        $this->assertSame(1, $complaints[0]->escalation_level);
        $this->assertSame(2, $complaints[1]->escalation_level, 'Pengaduan berulang naik ke Pimpinan.');
    }

    public function test_media_in_non_attachment_complaint_step_gets_msg_04_with_prompt(): void
    {
        $this->complaintFlow(['1']);
        $this->assertSame(['MSG-04', 'PGD-02'], $this->reply('', 'image')->codes);
    }

    public function test_empty_name_is_asked_again(): void
    {
        $this->complaintFlow(['1']);
        $this->assertSame(['PGD-02'], $this->reply('  ')->codes);
    }
}
