<?php

namespace Tests\Feature;

use App\Models\EscalationLog;
use App\Models\ServiceTicket;
use App\Services\Bot\BotEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Cek Status Paspor dengan tempat permohonan (feedback Imigrasi 20 Nov, WA0060): nomor permohonan → tempat 1-5,
 * atau keduanya dalam satu pesan. Hasil: tiket antrean petugas berisi nomor + tempat.
 */
class PassportStatusCheckTest extends TestCase
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

    private function openStatus(): void
    {
        $this->conversation(['Halo', '1', '11']);
    }

    /** @return array<string, array{string, string}> */
    public static function placeChoices(): array
    {
        return [
            '1 Imigrasi Cilacap' => ['1', 'Imigrasi Cilacap'],
            '2 MPP Banyumas' => ['2', 'MPP Banyumas'],
            '3 MPP Purbalingga' => ['3', 'MPP Purbalingga'],
            '4 Ngapak Banyumas' => ['4', 'Ngapak Banyumas'],
            '5 Ngapak Purbalingga' => ['5', 'Ngapak Purbalingga'],
            'nama tempat diketik' => ['mpp purbalingga', 'MPP Purbalingga'],
            'nama tempat + tanda baca' => ['*Ngapak Banyumas*.', 'Ngapak Banyumas'],
        ];
    }

    #[DataProvider('placeChoices')]
    public function test_two_step_flow_records_number_and_place(string $choice, string $label): void
    {
        $this->openStatus();
        $this->assertSame(['STATUS-PLACE'], $this->reply('1568000000012345')->codes);

        $queued = $this->reply($choice);

        $this->assertSame(['STATUS', 'LYN'], $queued->codes);
        $ticket = ServiceTicket::sole();
        $this->assertSame("Cek status paspor, no. permohonan 1568000000012345, tempat permohonan {$label}", $ticket->question_text);
        $this->assertSame('A2', EscalationLog::sole()->escalation_code);
        $this->assertSame('0>10', EscalationLog::sole()->menu_path);
    }

    /** @return array<string, array{string, string}> */
    public static function oneMessage(): array
    {
        return [
            'nomor lalu tempat' => ['1568000000012345 MPP Banyumas', 'MPP Banyumas'],
            'tempat lalu nomor' => ['Ngapak Purbalingga 1568000000012345', 'Ngapak Purbalingga'],
            'nomor berspasi + kata tempat' => ['1568 0000 0001 2345 tempat permohonan: Imigrasi Cilacap', 'Imigrasi Cilacap'],
            'singkatan kanim' => ['1568000000012345 kanim', 'Imigrasi Cilacap'],
            'ngapak dikenali sebelum banyumas' => ['1568000000012345 kancil ngapak banyumas', 'Ngapak Banyumas'],
        ];
    }

    #[DataProvider('oneMessage')]
    public function test_number_and_place_in_one_message_skips_second_step(string $text, string $label): void
    {
        $this->openStatus();

        $queued = $this->reply($text);

        $this->assertSame(['STATUS', 'LYN'], $queued->codes);
        $this->assertStringEndsWith("tempat permohonan {$label}", ServiceTicket::sole()->question_text);
        $this->assertStringContainsString('1568000000012345', ServiceTicket::sole()->question_text);
    }

    public function test_three_invalid_places_give_message_3_without_ticket(): void
    {
        $this->openStatus();
        $this->reply('1568000000012345');

        $this->assertSame('MSG-01', $this->reply('6')->codes[0]);
        $this->assertSame('MSG-02', $this->reply('jakarta')->codes[0]);
        $this->assertSame(['MSG-03'], $this->reply('?')->codes);
        $this->assertSame(0, ServiceTicket::count());
    }

    public function test_88_at_place_step_reaches_officer(): void
    {
        $this->openStatus();
        $this->reply('1568000000012345');

        $this->assertSame('ESK-01', $this->reply('88')->codes[0]);
        $this->assertSame(1, ServiceTicket::count());
        $this->assertNull($this->chat->fresh()->session_state['status_number']);
    }

    public function test_place_without_number_is_invalid(): void
    {
        $this->openStatus();

        $this->assertSame('MSG-01', $this->reply('MPP Banyumas')->codes[0]);
        $this->assertSame(0, ServiceTicket::count());
    }

    public function test_re_entering_status_menu_forgets_previous_number(): void
    {
        $this->openStatus();
        $this->reply('1568000000012345');
        $this->reply('0');
        $this->conversation(['1', '11']);

        $this->assertSame(['STATUS-PLACE'], $this->reply('1568000000099999')->codes);
        $this->reply('3');
        $this->assertStringContainsString('1568000000099999', ServiceTicket::sole()->question_text);
    }

    public function test_place_matcher(): void
    {
        $this->assertSame(['MPP Banyumas', '1568'], BotEngine::applicationPlace('1568 MPP Banyumas'));
        $this->assertSame([null, 'tidak tahu'], BotEngine::applicationPlace('tidak tahu'));
        $this->assertNull(BotEngine::applicationPlace('banyumasan')[0], 'Hanya kata utuh yang dikenali.');
    }
}
