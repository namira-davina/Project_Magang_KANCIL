<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Bot\BotScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Audit 20 Nov 2026, "4 kategori pengaduan.pdf": untuk tiap kategori, alur lengkap lewat webhook WhatsApp asli:
 * pemohon Halo → 3 → antrean masuk → petugas Ambil → balas → pemohon menulis keluhan → Jadikan Pengaduan
 * → Diproses (tanggapan) → Selesai (tanggapan). Semua pesan ke pemohon dicek, termasuk tidak ada nomor tiket.
 */
class FourComplaintCategoriesTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-20 13:14', 'Asia/Jakarta')); // Jumat, jam layanan (seperti di PDF)
        $this->configureIntegration('laravel');
        $this->seedFaqs();
        $this->fakeApis();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{string, string, string, string, bool}> */
    public static function categories(): array
    {
        // kategori, nama kategori, prioritas (PDF: Sedang/Sedang/Tinggi/Rendah), keluhan pemohon, terlihat oleh petugas
        return [
            '1 layanan paspor' => ['1', 'Layanan paspor', 'Sedang', 'Saya sudah mengajukan permohonan paspor, tetapi proses penerbitannya sudah cukup lama', true],
            '2 layanan WNA / izin tinggal' => ['2', 'Layanan WNA / izin tinggal', 'Sedang', 'Saya mengalami kendala saat mengurus perpanjangan izin tinggal', true],
            '3 pelayanan petugas' => ['3', 'Pelayanan petugas', 'Tinggi', 'Saya ingin menyampaikan keluhan karena petugas customer care kurang ramah', false],
            '4 lainnya' => ['4', 'Lainnya', 'Rendah', 'Saya ingin mengadukan kondisi toilet kantor yang kotor dan kurang terawat', true],
        ];
    }

    private function incoming(string $body): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => $body]))->assertOk();
    }

    private function lastTextToApplicant(): string
    {
        return (string) Message::whereIn('sender_type', ['bot', 'ai', 'system', 'petugas'])->latest('id')->value('message_text');
    }

    #[DataProvider('categories')]
    public function test_full_flow_per_category(string $key, string $name, string $priority, string $complaintText, bool $visibleToOfficer): void
    {
        // Pemohon
        $this->incoming('Halo');
        $this->assertStringContainsString('*Status Hari Ini: BUKA*', $this->lastTextToApplicant());
        $this->incoming('3');
        $handoff = $this->lastTextToApplicant();
        $this->assertHandoff($handoff);
        $ticket = ServiceTicket::sole();
        $this->assertSame(ServiceTicket::WAITING, $ticket->status);
        $this->assertSame('Layanan Pengaduan (menu utama)', BotScript::topicLabel($ticket->menu_path));

        // a. antrean masuk, b. ambil, balas dari dashboard
        $admin = User::factory()->role('admin')->create();
        $this->actingAs($admin)->getJson(route('antrean.data'))->assertOk()->assertJsonPath('tickets.0.status', ServiceTicket::WAITING);
        $this->postJson(route('antrean.ambil', $ticket))->assertOk();
        $this->postJson(route('percakapan.balas', $ticket->conversation_id), ['pesan' => 'Selamat siang ka, ada yang bisa kami bantu?'])->assertSuccessful();
        $this->assertSame(Conversation::MODE_HUMAN, Conversation::sole()->conversation_mode);

        $botMessagesBefore = Message::where('sender_type', 'bot')->count();
        $this->incoming($complaintText);
        $this->assertSame($botMessagesBefore, Message::where('sender_type', 'bot')->count(), 'Bot diam saat petugas menangani.');

        // c. jadikan pengaduan resmi
        $created = $this->postJson(route('antrean.pengaduan', $ticket), ['kategori' => $key, 'isi' => $complaintText])->assertCreated();
        $complaint = Complaint::sole();
        $created->assertJsonPath('pengaduan', $complaint->ticket_number);
        $this->assertSame($name, $complaint->category->name);
        $this->assertSame($priority, $complaint->priority);
        $this->assertSame('Menunggu', $complaint->status);
        $notice = $this->lastTextToApplicant();
        $this->assertStringContainsString('*pengaduan resmi*', $notice);
        $this->assertStringNotContainsString('ADU-', $notice, 'Nomor ADU tidak ditampilkan ke pemohon (feedback user 2).');
        $this->assertStringNotContainsString('LYN-', $notice);
        $this->assertSame(ServiceTicket::RESOLVED, $ticket->fresh()->status);

        // Petugas biasa tidak melihat pengaduan pelayanan petugas (untuk Admin/Pimpinan).
        $officer = User::factory()->role('petugas')->create();
        $html = $this->actingAs($officer)->get(route('pengaduan'))->assertOk()->getContent();
        $this->assertSame($visibleToOfficer, str_contains($html, $complaint->ticket_number));

        // d. proses pengaduan, e. selesai
        $this->actingAs($admin)->putJson(route('pengaduan.update', $complaint), ['status' => 'Diproses', 'tanggapan' => 'Sedang kami tindak lanjuti.'])
            ->assertOk()->assertJsonPath('notified', true);
        $this->assertSame("Tanggapan atas pengaduan Anda (status: *Diproses*):\nSedang kami tindak lanjuti.", $this->lastTextToApplicant());

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Selesai', 'tanggapan' => 'Pengaduan sudah kami selesaikan.'])->assertOk();
        $this->assertSame("Tanggapan atas pengaduan Anda (status: *Selesai*):\nPengaduan sudah kami selesaikan.", $this->lastTextToApplicant());
        $this->assertSame('Selesai', $complaint->fresh()->status);
        $this->assertNotNull($complaint->fresh()->resolved_at);

        // Setelah pengaduan, pemohon kembali dilayani bot.
        $this->incoming('Halo');
        $this->assertStringContainsString('Ketik *1* Pelayanan Paspor WNI', $this->lastTextToApplicant());

        // Semua pesan benar-benar dikirim ke gateway WhatsApp.
        $sent = collect($this->sentWhatsappTexts())->pluck('message')->filter()->implode("\n");
        $this->assertStringContainsString('Sedang kami tindak lanjuti.', $sent);
        $this->assertStringContainsString('Pengaduan sudah kami selesaikan.', $sent);
    }
}
