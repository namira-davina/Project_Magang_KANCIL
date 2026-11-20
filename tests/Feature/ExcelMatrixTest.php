<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Matriks uji "Testing_Kancil Sapa.xlsx" (audit 20 Nov 2026, Sheet1 & Sheet2) dijalankan ulang lewat webhook asli.
 *
 * Kolom Excel: Menu (menu utama) → Topik/Bantuan (pilihan submenu) → Bantuan (0 = menu, 1 = petugas setelah jawaban)
 * → Bantuan (0 = menu, 1 = cek posisi, 2 = batal selama antrean). Setiap balasan bot dipantulkan balik sebagai echo
 * `outgoing_chat` dengan perubahan kosmetik seperti gateway prod (spasi ganda dipadatkan, garis pemisah dibuang) dan
 * tanpa id kirim, persis kondisi bug Sheet1 (1>7 dan 1>8 lalu 0/1 tidak dibalas). Syarat lulus: setiap pesan pemohon
 * dibalas tepat satu kali dengan kode yang benar, dan bot tidak pernah dianggap diambil alih petugas.
 */
class ExcelMatrixTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    private int $echoSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-20 13:20', 'Asia/Jakarta')); // Jumat, jam layanan (hari uji Excel)
        $this->configureIntegration('laravel');
        $this->seedFaqs();
        $this->fakeApis();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{list<string>, list<string>}> jalur input => kode balasan pertama tiap input */
    public static function excelPaths(): array
    {
        $cases = [];
        // Menu Paspor setelah feedback Imigrasi 20 Nov: 9 = Beda Data (kunci 0>12), 10 = Penambahan Nama (kunci 0>9).
        $answers = ['1' => '0>1', '2' => '0>2', '3' => '0>3', '4' => '0>4', '5' => '0>5', '6' => '0>6', '7' => '0>7', '8' => '0>8', '9' => '0>12', '10' => '0>9'];

        // Menu 1 (Paspor) → topik 0 = menu utama
        $cases['1 > 0'] = [['1', '0'], ['M-01', 'M-00']];

        foreach ($answers as $option => $path) {
            $cases["1 > {$option} > 0 (menu utama)"] = [['1', $option, '0'], ['M-01', 'ANS-'.$path, 'M-00']];
            $cases["1 > {$option} > 1 (petugas) > 0"] = [['1', $option, '1', '0'], ['M-01', 'ANS-'.$path, 'ESK-01', 'M-00']];
            $cases["1 > {$option} > 1 > 1 (cek posisi)"] = [['1', $option, '1', '1'], ['M-01', 'ANS-'.$path, 'ESK-01', 'LYN-STATUS']];
            $cases["1 > {$option} > 1 > 2 (batal)"] = [['1', $option, '1', '2'], ['M-01', 'ANS-'.$path, 'ESK-01', 'LYN-BATAL']];
        }

        $cases['1 > 1 > 2 (angka lain setelah jawaban)'] = [['1', '1', '2'], ['M-01', 'ANS-0>1', 'MSG-01']];
        $cases['1 > 11 (cek status) > 0'] = [['1', '11', '0'], ['M-01', 'STATUS', 'M-00']];
        $cases['1 > 11 > nomor > tempat 2'] = [['1', '11', '1498000000012345', '2'], ['M-01', 'STATUS', 'STATUS-PLACE', 'STATUS']];
        $cases['1 > 11 > nomor > 0'] = [['1', '11', '1498000000012345', '0'], ['M-01', 'STATUS', 'STATUS-PLACE', 'M-00']];
        $cases['1 > 11 > nomor + tempat (1 pesan)'] = [['1', '11', '1498000000012345 MPP Banyumas'], ['M-01', 'STATUS', 'STATUS']];
        $cases['1 > 12 (kembali)'] = [['1', '12'], ['M-01', 'M-00']];

        // Menu 2 (Izin Tinggal, Skenario 3.2)
        $cases['2 > 0'] = [['2', '0'], ['M-08', 'M-00']];
        $cases['2 > 4 (kembali)'] = [['2', '4'], ['M-08', 'M-00']];
        $cases['2 > 1 > 0'] = [['2', '1', '0'], ['M-08', 'M-09', 'M-00']];
        $cases['2 > 1 > 7 (kembali) > 4'] = [['2', '1', '7', '4'], ['M-08', 'M-09', 'M-08', 'M-00']];

        foreach (range(1, 6) as $option) {
            $path = "1>1>{$option}";
            $cases["2 > 1 > {$option} > 0"] = [['2', '1', (string) $option, '0'], ['M-08', 'M-09', 'ANS-'.$path, 'M-00']];
            $cases["2 > 1 > {$option} > 1 (petugas) > 2"] = [['2', '1', (string) $option, '1', '2'], ['M-08', 'M-09', 'ANS-'.$path, 'ESK-01', 'LYN-BATAL']];
        }

        $cases['2 > 2 > 0'] = [['2', '2', '0'], ['M-08', 'M-10', 'M-00']];
        $cases['2 > 2 > 4 (kembali) > 4'] = [['2', '2', '4', '4'], ['M-08', 'M-10', 'M-08', 'M-00']];

        foreach (range(1, 3) as $option) {
            $path = "1>2>{$option}";
            $cases["2 > 2 > {$option} > 0"] = [['2', '2', (string) $option, '0'], ['M-08', 'M-10', 'ANS-'.$path, 'M-00']];
            $cases["2 > 2 > {$option} > 1 (petugas) > 1"] = [['2', '2', (string) $option, '1', '1'], ['M-08', 'M-10', 'ANS-'.$path, 'ESK-01', 'LYN-STATUS']];
        }

        $cases['2 > 3 (lokasi) > 0'] = [['2', '3', '0'], ['M-08', 'ANS-1>3', 'M-00']];
        $cases['2 > 3 > 1 > 0'] = [['2', '3', '1', '0'], ['M-08', 'ANS-1>3', 'ESK-01', 'M-00']];
        $cases['2 > 3 > 1 > 1'] = [['2', '3', '1', '1'], ['M-08', 'ANS-1>3', 'ESK-01', 'LYN-STATUS']];
        $cases['2 > 3 > 1 > 2'] = [['2', '3', '1', '2'], ['M-08', 'ANS-1>3', 'ESK-01', 'LYN-BATAL']];

        // Menu 3 (Pengaduan = antrean petugas)
        $cases['3 > 0'] = [['3', '0'], ['ESK-01', 'M-00']];
        $cases['3 > 1 (cek posisi)'] = [['3', '1'], ['ESK-01', 'LYN-STATUS']];
        $cases['3 > 2 (batal)'] = [['3', '2'], ['ESK-01', 'LYN-BATAL']];

        return $cases;
    }

    /** Echo seperti gateway prod: tanpa id kirim, spasi ganda dipadatkan, garis pemisah dibuang. */
    private function echoLikeGateway(Message $sent): void
    {
        $sent->update(['external_message_id' => null]);
        $body = preg_replace('/ {2,}/', ' ', preg_replace('/^-{3,}$/m', '', (string) $sent->message_text));

        $this->postWebhook($this->incomingPayload(['message_id' => 'ECHO-'.++$this->echoSeq, 'message_body' => $body], 'outgoing_chat'))->assertOk();
    }

    /** Pemohon mengirim pesan; balasan bot baru dikembalikan setelah echo-nya diproses. @return list<Message> */
    private function send(string $text): array
    {
        Carbon::setTestNow(now()->addSeconds(12)); // jeda wajar antar pesan (Excel: 5-30 detik)
        $lastId = (int) Message::max('id');

        $this->postWebhook($this->incomingPayload(['message_body' => $text]))->assertOk();

        $replies = Message::where('id', '>', $lastId)->whereIn('sender_type', ['bot', 'ai', 'system'])->orderBy('id')->get()->all();

        foreach ($replies as $reply) {
            $this->echoLikeGateway($reply);
        }

        return $replies;
    }

    #[DataProvider('excelPaths')]
    public function test_every_excel_path_is_answered_and_bot_never_goes_silent(array $inputs, array $expectedCodes): void
    {
        $greeting = $this->send('Halo');
        $this->assertCount(1, $greeting);
        $this->assertSame(['M-00'], $greeting[0]->meta['codes']);

        foreach ($inputs as $i => $input) {
            $replies = $this->send($input);
            $step = 'Halo > '.implode(' > ', array_slice($inputs, 0, $i + 1));

            $this->assertCount(1, $replies, "Langkah \"{$step}\" harus dibalas tepat satu kali (Excel Sheet1: tidak merespon).");
            $this->assertSame($expectedCodes[$i], $replies[0]->meta['codes'][0], $step);
        }

        $this->assertSame(0, Message::where('sender_type', 'petugas')->count(), 'Echo bot tidak boleh tercatat sebagai petugas.');
        $this->assertNotSame(Conversation::MODE_HUMAN, Conversation::sole()->conversation_mode);
        $this->assertSame(Message::where('sender_type', 'user')->count(), Message::whereIn('sender_type', ['bot', 'ai', 'system'])->count());
    }

    public function test_sheet1_failure_path_full_session_like_tester(): void
    {
        // Satu sesi panjang seperti penguji: 1>7>0, lalu 1>8>0, lalu 1>7>1>0, lalu 1>8>1>2.
        $this->send('Halo');

        foreach ([['1', '7', '0'], ['1', '8', '0'], ['1', '7', '1', '0'], ['1', '8', '1', '2'], ['1', '9', '0'], ['2', '1', '3', '0']] as $path) {
            foreach ($path as $input) {
                $this->assertCount(1, $this->send($input), implode('>', $path)." input {$input}");
            }
        }

        $this->assertSame(0, Message::where('sender_type', 'petugas')->count());
        $this->assertSame(Conversation::MODE_BOT, Conversation::sole()->conversation_mode);
    }
}
