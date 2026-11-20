<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Faq;
use App\Models\ServiceTicket;
use App\Services\Bot\BotScript;
use App\Services\Bot\MenuAnswerResolver;
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
        // Isi jawaban = FAQ Inventarisasi 3.1 (audit 20 Nov), bukan teks Bogor hardcode.
        return [
            'biaya paspor' => [['1', '1'], 'Rp650.000 (paspor biasa elektronik laminasi, masa berlaku 5 tahun).', '0>1', null, ['TRF-001', 'TRF-002', 'TRF-004', 'TRF-003', 'TRF-011']],
            'paspor baru' => [['1', '2'], 'KTP elektronik, Kartu Keluarga, dan salah satu dari akta lahir/ijazah/buku nikah.', '0>2', null, ['PSP-001']],
            'penggantian paspor' => [['1', '3'], "• Kartu Keluarga\n• Akta lahir / ijazah / buku nikah\n• Paspor lama", '0>3', null, ['PSP-002']],
            'paspor anak' => [['1', '4'], 'Anak di bawah 5 tahun dapat langsung datang ke kantor imigrasi tanpa mendaftar di M-Paspor.', '0>4', 'C2', ['ANK-001', 'ANK-002', 'ANK-003', 'ANK-004', 'ANK-005', 'ANK-006', 'ANK-007', 'ANK-008', 'ANK-009']],
            'pendaftaran m-paspor' => [['1', '5'], 'Unduh aplikasi M-Paspor:', '0>5', null, ['PSP-013']],
            'layanan prioritas' => [['1', '6'], 'antrean khusus berkode C-(nomor urut)', '0>6', null, ['LYK-002']],
            'paspor hilang' => [['1', '7'], '• Surat kehilangan (kepolisian)', '0>7', 'C1', ['PSP-011']],
            'paspor rusak' => [['1', '8'], '• Denda paspor rusak: Rp500.000', '0>8', null, ['PSP-014']],
            'paspor beda data' => [['1', '9'], '• Penetapan pengadilan', '0>12', null, ['PSP-016']],
            'penambahan nama' => [['1', '10'], 'Surat rekomendasi travel (umrah) atau BPIH (haji)', '0>9', null, ['PSP-015']],
            // Menu 2 sesuai Skenario 3.2 (feedback Imigrasi 20 Nov: jangan ikut Bogor).
            'tarif re-entry permit' => [['2', '1', '1'], 'Sampai 30 hari: Rp300.000', '1>1>1', null, ['TRF-005', 'TRF-011']],
            'tarif ITK' => [['2', '1', '2'], 'Sampai 7 hari: Rp250.000', '1>1>2', null, ['TRF-006', 'TRF-011']],
            'tarif ITAS' => [['2', '1', '3'], 'Sampai 10 tahun: Rp12.000.000', '1>1>3', null, ['TRF-007', 'TRF-011']],
            'tarif ITAP' => [['2', '1', '4'], 'Jangka waktu tidak terbatas: Rp15.000.000', '1>1>4', null, ['TRF-008', 'TRF-011']],
            'tarif exit permit only' => [['2', '1', '5'], 'Rp100.000 per orang.', '1>1>5', null, ['TRF-009', 'TRF-011']],
            'tarif visa beberapa kali' => [['2', '1', '6'], 'Sampai 5 tahun: Rp10.000.000', '1>1>6', null, ['TRF-010', 'TRF-011']],
            'WNA meninggal: pelapor dan batas waktu' => [['2', '2', '1'], 'paling lambat 60 hari sejak tanggal kematian', '1>2>1', 'C6', ['WNA-001', 'WNA-002']],
            'WNA meninggal: dokumen' => [['2', '2', '2'], 'Izin tinggal terakhir (ITAS/ITAP)', '1>2>2', 'C6', ['WNA-003']],
            'WNA meninggal: alur' => [['2', '2', '3'], 'Penjamin melaporkan ke Kantor Imigrasi', '1>2>3', 'C6', ['WNA-004']],
            'lokasi kami' => [['2', '3'], 'Jl. Urip Sumoharjo No.249', '1>3', null, []],
        ];
    }

    #[DataProvider('answerScenarios')]
    public function test_answer_scenario(array $path, string $fragment, string $internalPath, ?string $escalationCode, array $faqCodes): void
    {
        $this->assertSame(['M-00'], $this->reply('Halo')->codes);

        $answer = $this->conversation($path);

        $this->assertSame(['ANS-'.$internalPath, ...$faqCodes], $answer->codes);
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
            ."*Status Hari Ini: BUKA*\nWaktu pelayanan tersedia setiap hari Senin–Kamis pukul 08.00–16.00 WIB dan Jumat pukul 08.00–16.30 WIB.\n\n"
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
            ."Ketik *7* Permohonan Paspor Hilang\nKetik *8* Permohonan Paspor Rusak\nKetik *9* Permohonan Paspor Beda Data\n"
            ."Ketik *10* Penambahan Nama pada Paspor\nKetik *11* Cek Status Paspor\nKetik *12* untuk kembali ke menu sebelumnya",
            $reply->text,
        );
        $this->assertStringNotContainsString('Drive Thru', $reply->text);
        $this->assertStringNotContainsString('Tujuan', $reply->text);
        $this->assertStringNotContainsString('Affidavit', $reply->text);

        foreach (array_keys(BotScript::ANSWERS) as $path) {
            $this->assertStringNotContainsString('Drive Thru', app(MenuAnswerResolver::class)->resolve($path)['text']);
        }
    }

    public function test_foreigner_menu_follows_scenario_document_not_bogor(): void
    {
        // Feedback Imigrasi 20 Nov: menu 2 disesuaikan Dokumen Inventarisasi 3.1 & Skenario 3.2, "jangan ikut Imigrasi Bogor".
        $this->reply('Halo');
        $reply = $this->reply('2');

        $this->assertSame(['M-08'], $reply->codes);
        $this->assertSame(
            "Silakan pilih topik yang ingin Anda ketahui lebih lanjut:\n\nKetik *0* untuk kembali ke menu utama\n"
            ."Ketik *1* Tarif Izin Tinggal dan Visa (Stay Permit & Visa Fees)\nKetik *2* Pelaporan WNA Meninggal (Death Report of a Foreigner)\n"
            ."Ketik *3* Lokasi Kami (Our Locations)\nKetik *4* untuk kembali ke menu sebelumnya",
            $reply->text,
        );
        $this->assertStringNotContainsString('Pengaduan Orang Asing', $reply->text);
        $this->assertSame(0, ServiceTicket::count(), 'Menu 2 tidak langsung masuk antrean seperti versi Bogor.');

        $tarif = $this->reply('1');
        $this->assertSame(['M-09'], $tarif->codes);
        $this->assertStringContainsString("Ketik *1* Izin Masuk Kembali (Re-entry Permit)\nKetik *2* Izin Tinggal Kunjungan / ITK (Visit Stay Permit)", $tarif->text);
        $this->assertStringContainsString("Ketik *6* Visa Kunjungan Beberapa Kali Perjalanan (Multiple-Entry Visit Visa)\nKetik *7* untuk kembali ke menu sebelumnya", $tarif->text);

        $this->assertSame(['M-08'], $this->reply('7')->codes, '7 = kembali ke menu Izin Tinggal.');

        $report = $this->reply('2');
        $this->assertSame(['M-10'], $report->codes);
        $this->assertStringContainsString("Ketik *3* Alur Pelaporan (Procedure)\nKetik *4* untuk kembali ke menu sebelumnya", $report->text);
        $this->assertSame(['M-08'], $this->reply('4')->codes);
        $this->assertSame(['M-00'], $this->reply('4')->codes, '4 di menu Izin Tinggal = menu utama.');
    }

    public function test_foreigner_tariff_answer_includes_legal_basis_and_88_reaches_officer(): void
    {
        $answer = $this->conversation(['Halo', '2', '1', '3']);

        $this->assertStringContainsString('Peraturan Pemerintah (PP) Nomor 45 Tahun 2024', $answer->text);
        $this->assertStringContainsString('*Berapa tarif Izin Tinggal Terbatas (ITAS)?*', $answer->text);
        $this->assertStringNotContainsString('*PNBP (', $answer->text, 'Blok PNBP paspor tidak dipakai untuk tarif WNA.');

        $this->assertSame('ESK-01', $this->reply('88')->codes[0]);
        $this->assertSame('1>1>3', EscalationLog::sole()->menu_path);
    }

    public function test_location_lists_office_and_three_mpp_in_same_format(): void
    {
        $text = $this->conversation(['Halo', '2', '3'])->text;

        // Feedback Imigrasi 20-21 Nov: kantor, MPP Cilacap, MPP Banyumas, MPP Purbalingga; format nama, alamat, peta.
        $this->assertStringContainsString(
            "📍 Kantor Imigrasi Kelas I TPI Cilacap\nJl. Urip Sumoharjo No.249, Pantusan, Gumilir, Kec. Cilacap Utara, Kabupaten Cilacap, Jawa Tengah 53231\nhttps://maps.app.goo.gl/nsSRkK4pQqpF6rHM6\n\n"
            ."📍 Mal Pelayanan Publik (MPP) Cilacap\nJl. Gatot Subroto No.268 Lantai 2, Karang Lor, Gunungsimping, Kec. Cilacap Tengah, Kabupaten Cilacap, Jawa Tengah 53224\nhttps://maps.app.goo.gl/8nMhuDQmjkW56mnE9\n\n"
            ."📍 Mal Pelayanan Publik (MPP) Banyumas\nJl. Dr. Angka No.45, Karangkobar, Sokanegara, Kec. Purwokerto Tim., Kabupaten Banyumas, Jawa Tengah 53115\nhttps://maps.app.goo.gl/9NsKFWBDHz8kRGRi6\n\n"
            ."📍 Mal Pelayanan Publik (MPP) Purbalingga\nJl. Raya Mayjen Sungkono No. 16, Selabaya, Kecamatan Kalimanah, Kabupaten Purbalingga, Jawa Tengah 53371\nhttps://maps.app.goo.gl/wFeYgwqUDX7Byi31A",
            $text,
        );
        $this->assertStringNotContainsString('8JoubSb8oX5GQ1NQ9', $text, 'Tautan MPP Cilacap lama diganti.');
        $this->assertStringNotContainsString('Bogor', $text);
        $this->assertStringNotContainsString('Tanah Sareal', $text);
    }

    public function test_foreigner_death_report_escalates_with_c6(): void
    {
        $this->conversation(['Halo', '2', '2', '2']);
        $this->reply('1');

        $log = EscalationLog::sole();
        $this->assertSame('C6', $log->escalation_code);
        $this->assertSame('Tinggi', $log->priority);
        $this->assertSame('1>2>2', $log->menu_path);
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
        $this->assertSame(['M-00'], $this->reply('12')->codes, '12 = kembali ke menu sebelumnya (menu utama).');

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
            '2026-11-13 16:30:00' => '*Status Hari Ini: TUTUP (di luar jam pelayanan)*',
            '2026-11-13 16:29:00' => '*Status Hari Ini: BUKA*',
            '2026-11-12 16:00:00' => '*Status Hari Ini: TUTUP (di luar jam pelayanan)*',
            '2026-11-12 15:59:00' => '*Status Hari Ini: BUKA*',
        ];

        foreach ($cases as $at => $expected) {
            Carbon::setTestNow(Carbon::parse($at, 'Asia/Jakarta'));
            $this->chat = null;
            $this->startChat(['wa_chat_id' => 'c'.md5($at), 'whatsapp_number' => '62'.random_int(100000000, 999999999)]);

            $text = $this->reply('Halo')->text;
            $this->assertStringContainsString($expected."\nWaktu pelayanan tersedia setiap hari Senin–Kamis pukul 08.00–16.00 WIB dan Jumat pukul 08.00–16.30 WIB.", $text, $at);
        }
    }

    /** @return array<string, array{string, bool}> */
    public static function officeMoments(): array
    {
        return [
            'Rabu 10.00 (buka)' => ['2026-11-11 10:00', false],
            'Rabu 07.59 (belum buka)' => ['2026-11-11 07:59', true],
            'Kamis 16.00 (tutup)' => ['2026-11-12 16:00', true],
            'Jumat 16.15 (masih buka)' => ['2026-11-13 16:15', false],
            'Jumat 16.30 (tutup)' => ['2026-11-13 16:30', true],
            'Sabtu 10.00' => ['2026-11-14 10:00', true],
            'Minggu malam' => ['2026-11-15 20:00', true],
            'Natal (libur nasional)' => ['2026-12-25 10:00', true],
        ];
    }

    #[DataProvider('officeMoments')]
    public function test_s49_handoff_outside_hours_adds_message_7_with_schedule(string $at, bool $closed): void
    {
        // Skenario 3.2 no. 49: hubungi petugas di luar jam layanan = Pesan 7 berisi jam layanan, antrean tetap dibuat.
        Carbon::setTestNow(Carbon::parse($at, 'Asia/Jakarta'));
        $this->reply('Halo');
        $reply = $this->reply('3');

        $this->assertHandoff($reply->text);
        $this->assertSame(1, ServiceTicket::count());

        if ($closed) {
            $this->assertSame(['ESK-01', 'LYN', 'MSG-07'], $reply->codes);
            $this->assertStringEndsWith(
                '

_Saat ini di luar jam layanan petugas. Jam layanan: Senin–Kamis pukul 08.00–16.00 WIB dan Jumat pukul 08.00–16.30 WIB. Permintaan Anda tetap tercatat dalam antrean._',
                $reply->text,
            );
        } else {
            $this->assertSame(['ESK-01', 'LYN'], $reply->codes);
            $this->assertStringNotContainsString('di luar jam layanan', $reply->text);
        }
    }

    public function test_message_7_also_after_answer_88_and_status_number(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00', 'Asia/Jakarta'));

        $this->conversation(['Halo', '1', '7']);
        $this->assertContains('MSG-07', $this->reply('1')->codes, 'Ketik 1 setelah jawaban.');

        $this->chat->update(['session_state' => null, 'conversation_mode' => Conversation::MODE_BOT]);
        ServiceTicket::query()->update(['status' => ServiceTicket::RESOLVED]);
        $this->conversation(['Halo', '1', '11']);
        $this->assertSame(['STATUS', 'LYN', 'MSG-07'], $this->reply('1498000000012345 MPP Purbalingga')->codes);
    }

    public function test_message_7_can_be_disabled(): void
    {
        config(['kancil.outside_hours_notice' => false]);
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00', 'Asia/Jakarta'));

        $this->reply('Halo');
        $this->assertSame(['ESK-01', 'LYN'], $this->reply('3')->codes);
    }

    public function test_cek_status_asks_number_then_queues_officer_with_the_number(): void
    {
        $prompt = $this->conversation(['Halo', '1', '11']);

        $this->assertSame(['STATUS'], $prompt->codes);
        $this->assertSame(
            "Silakan ketik *Nomor Permohonan* paspor Anda.\nContoh: 1498000000012345\n"
            ."Tempat permohonan: Imigrasi Cilacap / MPP Banyumas / MPP Purbalingga / Ngapak Banyumas / Ngapak Purbalingga\n\n"
            .'Ketik *0* untuk kembali ke menu utama.',
            $prompt->text,
        );

        $this->assertSame(['MSG-01', 'M-01'], $this->reply('bukan nomor')->codes);

        $place = $this->reply('1498 0000 0001 2345');
        $this->assertSame(['STATUS-PLACE'], $place->codes);
        $this->assertSame(
            "Nomor permohonan: *1498000000012345*\n\nPilih *tempat permohonan* paspor Anda:\n"
            ."Ketik *1* Imigrasi Cilacap\nKetik *2* MPP Banyumas\nKetik *3* MPP Purbalingga\nKetik *4* Ngapak Banyumas\nKetik *5* Ngapak Purbalingga\n\n"
            .'Ketik *0* untuk kembali ke menu utama.',
            $place->text,
        );
        $this->assertSame(0, ServiceTicket::count(), 'Tiket dibuat setelah tempat dipilih.');

        $this->assertSame(['MSG-01', 'M-01'], $this->reply('9')->codes, 'Angka di luar 1-5 = salah input.');
        $again = $this->reply('x');
        $this->assertSame(['MSG-02', 'M-01'], $again->codes);
        $this->assertStringContainsString('Pilih *tempat permohonan*', $again->text, 'Salah input mengulang pilihan tempat.');

        $queued = $this->reply('2');
        $this->assertSame(['STATUS', 'LYN'], $queued->codes);
        $this->assertHandoff($queued->text);
        $this->assertSame('Cek status paspor, no. permohonan 1498000000012345, tempat permohonan MPP Banyumas', ServiceTicket::sole()->question_text);
        $this->assertSame('0>10', ServiceTicket::sole()->menu_path);
        $this->assertSame(Conversation::MODE_WAITING, $this->chat->fresh()->conversation_mode);
    }

    public function test_cek_status_zero_returns_to_main_menu(): void
    {
        $this->conversation(['Halo', '1', '11']);
        $this->assertSame(['M-00'], $this->reply('0')->codes);
        $this->assertSame(0, ServiceTicket::count());

        $this->conversation(['1', '11', '1498000000012345']);
        $this->assertSame(['M-00'], $this->reply('0')->codes, '0 di langkah tempat = menu utama.');
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
        $this->assertSame(['MSG-02', 'M-01'], $this->reply('13')->codes, 'Angka di luar pilihan juga salah input.');
    }

    public function test_9_is_a_normal_option_not_a_back_command(): void
    {
        $this->reply('Halo');
        $this->assertSame(['MSG-01', 'M-00'], $this->reply('9')->codes, '9 tidak ada di menu utama.');
        $this->reply('1');
        $this->assertSame(['ANS-0>12', 'PSP-016'], $this->reply('9')->codes, '9 di menu paspor = Permohonan Paspor Beda Data.');
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

    public function test_s46_answer_with_unverified_faq_shows_message_5(): void
    {
        // Dokumen Skenario 3.2: jawaban yang FAQ-nya belum Aktif diganti Pesan 5 (gate status, audit 20 Nov).
        $this->holdFaq('PSP-001', 'ANK-003');

        $held = $this->conversation(['Halo', '1', '2']);
        $this->assertSame(['MSG-05', 'ANS-0>2'], $held->codes);
        $this->assertStringStartsWith(BotScript::msg('MSG-05'), $held->text);
        $this->assertStringNotContainsString('Kartu Keluarga', $held->text);
        $this->assertStringEndsWith(BotScript::answerFooter(), $held->text, 'Tetap ada pilihan 0 = menu, 1 = petugas.');

        $this->reply('0');
        $anak = $this->conversation(['1', '4']);
        $this->assertSame(['MSG-05', 'ANS-0>4'], $anak->codes, 'Satu FAQ sumber tertahan = seluruh jawaban tertahan.');

        $this->reply('0');
        $this->assertSame('ANS-0>7', $this->conversation(['1', '7'])->codes[0], 'FAQ lain tetap tampil.');

        $this->reply('1');
        $this->assertSame('C1', EscalationLog::latest('id')->first()->escalation_code);
    }

    public function test_escalation_after_held_answer_is_plain_a2(): void
    {
        $this->holdFaq('PSP-011');
        $this->conversation(['Halo', '1', '7']);
        $this->reply('1');

        $this->assertSame('A2', EscalationLog::sole()->escalation_code, 'Jawaban tidak tampil: bukan kasus khusus C1.');
    }

    public function test_activating_faq_in_dashboard_takes_effect_immediately(): void
    {
        $this->holdFaq('PSP-001');
        $this->assertSame('MSG-05', $this->conversation(['Halo', '1', '2'])->codes[0]);

        Faq::where('code', 'PSP-001')->update(['verification_status' => 'terverifikasi', 'is_active' => true]);
        $this->reply('0');

        $this->assertSame(['ANS-0>2', 'PSP-001'], $this->conversation(['1', '2'])->codes);
    }

    public function test_scope_all_shows_unverified_faq_as_emergency_fallback(): void
    {
        config(['kancil.faq_scope' => 'all']);
        $this->holdFaq('PSP-001');

        $this->assertSame(['ANS-0>2', 'PSP-001'], $this->conversation(['Halo', '1', '2'])->codes);
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
        $this->assertSame('ANS-0>1', $this->reply('1')->codes[0]);
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
