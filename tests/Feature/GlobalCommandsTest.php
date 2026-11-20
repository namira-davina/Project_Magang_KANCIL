<?php

namespace Tests\Feature;

use App\Models\ServiceTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Skenario 3.2 Bab 2: perintah yang berlaku di semua menu. Menu Bogor: 0 / 99 / "menu" = menu utama, 88 = petugas.
 * (9 = kembali tidak global karena 9 adalah opsi "Penambahan Nama" di menu Paspor; deviasi terdokumentasi.)
 */
class GlobalCommandsTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->atOfficeHours();
        $this->configureIntegration('laravel');
        $this->seedFaqs();
        $this->holdFaq('PSP-016'); // Beda Data belum diverifikasi: jawaban = Pesan 5
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{list<string>}> state awal (input setelah Halo) */
    public static function states(): array
    {
        return [
            'menu utama' => [[]],
            'menu paspor' => [['1']],
            'menu izin tinggal' => [['2']],
            'setelah jawaban' => [['1', '2']],
            'setelah jawaban tertahan (Pesan 5)' => [['1', '9']], // PSP-016 ditahan di setUp
            'setelah lokasi' => [['2', '3']],
            'cek status paspor' => [['1', '11']],
            'cek status: pilih tempat' => [['1', '11', '1498000000012345']],
            'menu tarif izin tinggal' => [['2', '1']],
            'menu pelaporan WNA meninggal' => [['2', '2']],
            'setelah jawaban tarif ITAS' => [['2', '1', '3']],
            'setelah 1x salah input' => [['1', 'xyz']],
            'setelah MSG-03' => [['abc', 'def', 'ghi']],
        ];
    }

    private function enter(array $inputs): void
    {
        $this->reply('Halo');

        foreach ($inputs as $input) {
            $this->reply($input);
        }
    }

    #[DataProvider('states')]
    public function test_main_menu_commands_work_everywhere(array $inputs): void
    {
        foreach (['0', '99', 'menu', 'MENU', ' 99. '] as $command) {
            $this->chat = null;
            $this->refreshApplicationState();
            $this->enter($inputs);

            $this->assertSame(['M-00'], $this->reply($command)->codes, "\"{$command}\" dari ".json_encode($inputs));
        }
    }

    #[DataProvider('states')]
    public function test_88_reaches_officer_everywhere(array $inputs): void
    {
        $this->enter($inputs);

        $reply = $this->reply('88');

        $this->assertSame('ESK-01', $reply->codes[0], json_encode($inputs));
        $this->assertSame(1, ServiceTicket::count());
    }

    public function test_88_during_complaint_form_discards_draft_and_queues(): void
    {
        $this->complaintFlow(['1', 'Budi Santoso']);

        $this->assertSame('ESK-01', $this->reply('88')->codes[0]);
        $this->assertNull($this->chat->fresh()->session_state['complaint']);
    }

    public function test_main_menu_while_queued_keeps_ticket(): void
    {
        $this->enter(['3']);

        $this->assertSame(['M-00'], $this->reply('0')->codes);
        $this->assertSame(ServiceTicket::WAITING, ServiceTicket::sole()->status);
    }

    private function refreshApplicationState(): void
    {
        $this->chat?->delete();
        ServiceTicket::query()->delete();
        $this->chat = $this->startChat(['wa_chat_id' => uniqid().'@s.whatsapp.net', 'whatsapp_number' => (string) random_int(6280000000000, 6289999999999)]);
    }
}
