<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Message;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Tickets\TicketQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Pengaduan: lampiran bisa dibuka, log B1 mengikuti status tiket, pemberitahuan status,
 * dan "Jadikan Pengaduan" dari tiket antrean petugas.
 */
class PengaduanLanjutanTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->atOfficeHours();
        $this->configureIntegration('laravel');
        $this->seedFaqs();
        $this->fakeApis();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role = 'petugas'): User
    {
        return User::factory()->role($role)->create();
    }

    /** Pengaduan lewat bot (PGD-01..08) dengan satu lampiran foto. */
    private function complaintViaBot(string $category = '1'): Complaint
    {
        Storage::fake('local');
        Storage::disk('local')->put('wa-media/1/foto.jpg', 'isi-foto');

        $this->conversation(['Halo', '3', '1', 'Budi Santoso', $category, 'Petugas loket meminta biaya tambahan di luar tarif resmi.', '1']);
        $this->reply('', 'image', ['attachment_path' => 'wa-media/1/foto.jpg', 'meta' => ['media_name' => 'bukti.jpg']]);
        $this->reply('selesai');
        $this->reply('1');

        return Complaint::sole();
    }

    // ------------------------------------------------------------ lampiran

    public function test_complaint_attachments_are_listed_and_downloadable(): void
    {
        $complaint = $this->complaintViaBot();
        $complaint->update(['attachments' => [...$complaint->attachments, ['message_id' => null, 'path' => null, 'type' => 'image', 'name' => null]]]);
        $this->actingAs($this->user('admin'));

        $item = $this->get(route('pengaduan'))->assertOk()->viewData('complaints')->first();

        $this->assertSame(2, $item['lampiran']);
        $this->assertSame('bukti.jpg', $item['lampiran_list'][0]['nama']);
        $this->assertNotNull($url = $item['lampiran_list'][0]['url']);
        $this->assertSame(['nama' => 'Lampiran 2', 'url' => null], $item['lampiran_list'][1], 'Berkas yang tidak tersimpan tidak diberi tautan.');

        $this->get($url)->assertOk()->assertDownload('bukti.jpg');
    }

    // ------------------------------------------------------------ log B1 & pemberitahuan

    public function test_b1_log_follows_complaint_status(): void
    {
        $complaint = $this->complaintViaBot();
        $log = EscalationLog::where('escalation_code', 'B1')->sole();
        $this->assertSame('Menunggu', $log->status);
        $admin = $this->user('admin');
        $this->actingAs($admin);

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Diproses'])->assertOk();
        $log->refresh();
        $this->assertSame('Diambil', $log->status);
        $this->assertSame($admin->id, $log->handled_by);
        $this->assertNotNull($log->taken_at);

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Selesai'])->assertOk();
        $this->assertSame('Selesai', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->completed_at);

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Menunggu'])->assertOk();
        $this->assertSame('Menunggu', $log->fresh()->status);
        $this->assertNull($log->fresh()->completed_at);
    }

    public function test_chat_takeover_and_release_do_not_touch_complaint_log(): void
    {
        $this->complaintViaBot();
        $log = EscalationLog::where('escalation_code', 'B1')->sole();
        $officer = $this->user();
        $this->actingAs($officer);

        $this->postJson(route('percakapan.ambil-alih', $this->chat))->assertOk();
        $this->assertSame('Menunggu', $log->fresh()->status, 'Ambil alih chat bukan menangani tiket pengaduan.');

        $this->postJson(route('percakapan.kembalikan', $this->chat))->assertOk();
        $this->assertSame('Menunggu', $log->fresh()->status);
    }

    public function test_status_change_on_simulation_channel_is_not_sent(): void
    {
        $chat = $this->startChat(['channel' => 'simulasi']);
        $complaint = Complaint::create(['ticket_number' => 'ADU-202611-0009', 'conversation_id' => $chat->id, 'user_name' => 'A',
            'whatsapp_number' => '1', 'complaint_text' => 'x', 'status' => 'Menunggu', 'submitted_at' => now()]);
        $this->actingAs($this->user('admin'));

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Selesai'])->assertOk()->assertJsonPath('notified', false);
        $this->assertSame(0, Message::count());
    }

    // ------------------------------------------------------------ jadikan pengaduan

    private function queuedTicket(): ServiceTicket
    {
        $this->reply('Halo');
        $this->reply('petugas loket kemarin kasar sekali');
        $this->reply('4');

        return ServiceTicket::sole();
    }

    public function test_officer_turns_queue_ticket_into_complaint(): void
    {
        $ticket = $this->queuedTicket();
        $officer = $this->user();
        $this->actingAs($officer)->postJson(route('antrean.ambil', $ticket))->assertOk();
        $data = $this->getJson(route('antrean.data'))->json('tickets.0');
        $this->assertSame(route('antrean.pengaduan', $ticket), $data['pengaduan_url']);
        $this->assertSame('petugas loket kemarin kasar sekali', $data['pertanyaan_lengkap']);

        $response = $this->postJson(route('antrean.pengaduan', $ticket), ['kategori' => '1', 'isi' => '  Paspor belum jadi setelah 10 hari kerja.  '])
            ->assertCreated()
            ->assertJsonPath('ticket.status', 'Selesai')
            ->assertJsonPath('pengaduan_url', route('pengaduan'));

        $complaint = Complaint::sole();
        $response->assertJsonPath('pengaduan', $complaint->ticket_number);
        $this->assertMatchesRegularExpression('/^ADU-202611-\d{4}$/', $complaint->ticket_number);
        $this->assertSame('Budi', $complaint->user_name);
        $this->assertSame('6281234567890', $complaint->whatsapp_number);
        $this->assertSame('Layanan paspor', $complaint->category->name);
        $this->assertSame('Sedang', $complaint->priority);
        $this->assertSame(1, $complaint->escalation_level);
        $this->assertSame('Paspor belum jadi setelah 10 hari kerja.', $complaint->complaint_text);
        $this->assertSame('Menunggu', $complaint->status);
        $this->assertSame($officer->id, $complaint->assigned_to);
        $this->assertSame($ticket->conversation_id, $complaint->conversation_id);

        $ticket->refresh();
        $this->assertSame('Selesai', $ticket->status);
        $this->assertSame("Dialihkan ke pengaduan {$complaint->ticket_number}.", $ticket->close_reason);
        $this->assertSame('BOT_ACTIVE', $ticket->conversation->fresh()->conversation_mode);

        $b1 = EscalationLog::where('escalation_code', 'B1')->sole();
        $this->assertStringStartsWith($complaint->ticket_number, $b1->reason);
        $this->assertStringContainsString("dari antrean {$ticket->ticket_number}", $b1->reason);

        $notice = Message::where('sender_type', 'system')->sole();
        $this->assertStringContainsString($ticket->ticket_number, $notice->message_text);
        $this->assertStringContainsString($complaint->ticket_number, $notice->message_text);
        $this->assertSame(['LYN-ADU', 'PGD-08'], $notice->meta['codes']);
        $this->assertSame(1, Message::where('sender_type', 'system')->count(), 'Tidak ada pesan penutup ganda.');

        // Pemohon bisa cek status pengaduan seperti biasa (PGD-09/10).
        $this->reply('0');
        $this->reply('3');
        $this->reply('2');
        $this->assertStringContainsString('*Baru*', $this->reply($complaint->ticket_number)->text);
    }

    public function test_waiting_ticket_can_be_turned_into_complaint_directly(): void
    {
        $ticket = $this->queuedTicket();
        $this->actingAs($this->user());

        $this->postJson(route('antrean.pengaduan', $ticket), ['kategori' => '4', 'isi' => 'Keluhan umum pemohon.'])->assertCreated();

        $this->assertSame('Rendah', Complaint::sole()->priority);
        $this->assertSame('Selesai', $ticket->fresh()->status);
    }

    public function test_complaint_about_officer_goes_to_leadership_and_is_hidden_from_officers(): void
    {
        $ticket = $this->queuedTicket();
        $officer = $this->user();
        $this->actingAs($officer);

        $this->postJson(route('antrean.pengaduan', $ticket), ['kategori' => '3', 'isi' => 'Petugas loket bersikap kasar kepada pemohon.'])->assertCreated();

        $complaint = Complaint::sole();
        $this->assertSame('Tinggi', $complaint->priority);
        $this->assertSame(2, $complaint->escalation_level, 'Kategori 3 langsung ke Pimpinan.');
        $this->assertCount(0, $this->get(route('pengaduan'))->viewData('complaints'));
        $this->actingAs($this->user('pimpinan'));
        $this->assertCount(1, $this->get(route('pengaduan'))->viewData('complaints'));
    }

    public function test_applicant_without_name_gets_fallback_name(): void
    {
        $this->startChat(['user_name' => null]);
        $ticket = $this->queuedTicket();
        Conversation::query()->update(['user_name' => null]);
        $this->actingAs($this->user());

        $this->postJson(route('antrean.pengaduan', $ticket), ['kategori' => '1', 'isi' => 'Isi pengaduan cukup panjang.'])->assertCreated();

        $this->assertSame('Pemohon WhatsApp', Complaint::sole()->user_name);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidComplaints(): array
    {
        return [
            'tanpa kategori' => [['isi' => 'Isi pengaduan cukup panjang.'], 'kategori'],
            'kategori salah' => [['kategori' => '9', 'isi' => 'Isi pengaduan cukup panjang.'], 'kategori'],
            'tanpa isi' => [['kategori' => '1'], 'isi'],
            'isi pendek' => [['kategori' => '1', 'isi' => 'pendek'], 'isi'],
            'isi panjang' => [['kategori' => '1', 'isi' => str_repeat('a', 5001)], 'isi'],
        ];
    }

    /** @dataProvider invalidComplaints */
    public function test_turning_into_complaint_validates_input(array $payload, string $field): void
    {
        $ticket = $this->queuedTicket();
        $this->actingAs($this->user());

        $this->postJson(route('antrean.pengaduan', $ticket), $payload)->assertStatus(422)->assertJsonValidationErrors($field);

        $this->assertSame(0, Complaint::count());
        $this->assertSame('Menunggu', $ticket->fresh()->status);
    }

    public function test_closed_ticket_cannot_become_complaint(): void
    {
        $ticket = $this->queuedTicket();
        $ticket->update(['status' => 'Batal']);
        $this->actingAs($this->user());

        $this->postJson(route('antrean.pengaduan', $ticket), ['kategori' => '1', 'isi' => 'Isi pengaduan cukup panjang.'])->assertStatus(422);

        $this->assertSame(0, Complaint::count());
    }

    public function test_other_officers_ticket_cannot_become_complaint_but_admin_can(): void
    {
        $ticket = $this->queuedTicket();
        app(TicketQueueService::class)->claim($ticket, $this->user());
        $payload = ['kategori' => '1', 'isi' => 'Isi pengaduan cukup panjang.'];

        $this->actingAs($this->user())->postJson(route('antrean.pengaduan', $ticket), $payload)->assertStatus(409);
        $this->assertSame(0, Complaint::count());

        $this->actingAs($this->user('admin'))->postJson(route('antrean.pengaduan', $ticket), $payload)->assertCreated();
        $this->assertSame(1, Complaint::count());
    }

    public function test_leadership_cannot_turn_ticket_into_complaint(): void
    {
        $ticket = $this->queuedTicket();

        $this->actingAs($this->user('pimpinan'))
            ->postJson(route('antrean.pengaduan', $ticket), ['kategori' => '1', 'isi' => 'Isi pengaduan cukup panjang.'])
            ->assertForbidden();
    }

    public function test_antrean_page_has_complaint_dialog_only_for_actors(): void
    {
        $this->actingAs($this->user());
        $this->get(route('antrean'))->assertSee('Jadikan Pengaduan Resmi')->assertSee('Pelayanan petugas (prioritas Tinggi)');

        $this->actingAs($this->user('pimpinan'));
        $this->get(route('antrean'))->assertDontSee('Jadikan Pengaduan Resmi');
    }
}
