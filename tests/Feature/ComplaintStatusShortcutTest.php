<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Services\Bot\BotEngine;
use App\Services\Bot\BotScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

/**
 * Skenario 3.2 no. 56-57: cek status pengaduan dengan nomor ADU, hanya untuk nomor WhatsApp pelapor.
 * Menu Bogor tidak punya submenu cek status, jadi nomor ADU yang diketik di mana saja langsung dijawab.
 */
class ComplaintStatusShortcutTest extends TestCase
{
    use BotHelpers, CreatesData, RefreshDatabase;

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

    private function ownComplaint(string $status = 'Diproses', ?string $response = 'Sedang kami tindak lanjuti.'): Complaint
    {
        $this->startChat();

        return Complaint::find($this->makeComplaint($status, [
            'ticket_number' => 'ADU-202611-0005',
            'conversation_id' => $this->chat->id,
            'whatsapp_number' => '6281234567890',
            'response_text' => $response,
        ]));
    }

    /** @return array<string, array{string, ?string}> */
    public static function numberFormats(): array
    {
        return [
            'baku' => ['ADU-202611-0005', 'ADU-202611-0005'],
            'huruf kecil + spasi' => [' adu 202611 0005 ', 'ADU-202611-0005'],
            'tanpa pemisah' => ['ADU2026110005', 'ADU-202611-0005'],
            'dengan pagar (dashboard)' => ['#ADU-202611-0005', 'ADU-202611-0005'],
            'tebal WhatsApp' => ['*ADU-202611-0005*', 'ADU-202611-0005'],
            'kurang digit' => ['ADU-20261-0005', null],
            'tiket antrean LYN' => ['LYN-20261120-011', null],
            'kalimat' => ['status ADU-202611-0005 gimana', null],
        ];
    }

    #[DataProvider('numberFormats')]
    public function test_number_formats(string $input, ?string $expected): void
    {
        $this->assertSame($expected, BotEngine::complaintNumber($input));
    }

    public function test_owner_gets_status_and_response_from_any_menu(): void
    {
        $this->ownComplaint();
        $this->conversation(['Halo', '1']);

        $reply = $this->reply('adu-202611-0005');

        $this->assertSame(['PGD-10'], $reply->codes);
        $this->assertSame(
            "Status pengaduan *ADU-202611-0005*: *Diproses*.\nTanggapan petugas: Sedang kami tindak lanjuti.\n\n".BotScript::MAIN_MENU_FOOTER,
            $reply->text,
        );
        $this->assertSame(['M-00'], $this->reply('0')->codes);
    }

    public function test_new_status_shows_as_baru_for_applicant(): void
    {
        $this->ownComplaint('Menunggu', null);

        $reply = $this->reply('ADU-202611-0005');

        $this->assertStringStartsWith('Status pengaduan *ADU-202611-0005*: *Baru*.', $reply->text);
        $this->assertStringNotContainsString('Tanggapan petugas', $reply->text);
    }

    public function test_first_message_of_session_can_be_complaint_number(): void
    {
        $this->ownComplaint();

        $this->assertSame(['PGD-10'], $this->reply('ADU-202611-0005')->codes, 'Sesi baru: tidak perlu menu dulu.');
    }

    public function test_other_whatsapp_number_cannot_see_status(): void
    {
        $this->makeComplaint('Diproses', ['ticket_number' => 'ADU-202611-0009', 'whatsapp_number' => '6289999999999', 'response_text' => 'rahasia']);
        $this->startChat();

        $reply = $this->reply('ADU-202611-0009');

        $this->assertSame(['PGD-10'], $reply->codes);
        $this->assertStringStartsWith('Nomor tiket tidak ditemukan untuk nomor WhatsApp ini.', $reply->text);
        $this->assertStringNotContainsString('rahasia', $reply->text);
        $this->assertStringNotContainsString('Diproses', $reply->text);
    }

    public function test_unknown_number_is_not_found(): void
    {
        $this->startChat();

        $this->assertStringStartsWith('Nomor tiket tidak ditemukan', $this->reply('ADU-209912-9999')->text);
    }

    public function test_complaint_form_text_is_not_hijacked(): void
    {
        $this->ownComplaint();
        $this->complaintFlow(['1', 'Budi Santoso', '1']);

        $reply = $this->reply('ADU-202611-0005');

        $this->assertSame(['PGD-04'], $reply->codes, 'Saat mengisi isi pengaduan, teks tetap diperlakukan sebagai isian.');
    }
}
