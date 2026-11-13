<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Faq;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BotHelpers;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

/**
 * Halaman Percakapan: inbox nyata, polling, balas, ambil alih / kembalikan ke bot, lampiran.
 */
class PercakapanTest extends TestCase
{
    use BotHelpers, CreatesData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
        $this->fakeApis();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_index_renders_real_threads_and_counts(): void
    {
        $this->actingAsAdmin();
        $a = $this->startChat(['user_name' => 'Siti Aminah', 'last_message_at' => now()->subMinute()]);
        $a->messages()->create(['sender_type' => 'user', 'message_text' => 'Pesan terakhir Siti', 'sent_at' => now()]);
        $this->startChat(['wa_chat_id' => 'b@s.whatsapp.net', 'whatsapp_number' => '628222', 'user_name' => null,
            'conversation_mode' => 'WAITING_HUMAN', 'last_message_at' => now()]);

        $response = $this->get(route('percakapan'))->assertOk();

        $threads = $response->viewData('threads');
        $this->assertCount(2, $threads);
        $this->assertSame('628222', $threads[0]['name'], 'Tanpa nama: tampil nomor.');
        $this->assertSame('human', $threads[0]['filter']);
        $this->assertSame('Menunggu Petugas', $threads[0]['badge']);
        $this->assertSame('Siti Aminah', $threads[1]['name']);
        $this->assertSame('SA', $threads[1]['initial']);
        $this->assertSame('+62 812-3456-7890', $threads[1]['phone']);
        $this->assertSame('Pesan terakhir Siti', $threads[1]['preview']);
        $this->assertSame(1, $threads[1]['unread']);
        $this->assertSame('#KS-'.str_pad((string) $a->id, 5, '0', STR_PAD_LEFT), $threads[1]['code']);
        $this->assertSame(['all' => 2, 'bot' => 1, 'human' => 1], $response->viewData('counts'));
        $this->assertTrue($response->viewData('canReply'));

        $response->assertSee('Monitoring Percakapan WhatsApp')->assertDontSee('Hendro Wibowo');
    }

    public function test_index_can_preselect_conversation(): void
    {
        $this->actingAsAdmin();
        $this->assertSame(5, $this->get(route('percakapan', ['id' => 5]))->viewData('selectedId'));
    }

    public function test_threads_json_for_polling(): void
    {
        $this->actingAsAdmin();
        $this->startChat(['conversation_mode' => 'HUMAN_HANDLING']);

        $this->getJson(route('percakapan.data'))
            ->assertOk()
            ->assertJsonPath('counts.human', 1)
            ->assertJsonPath('threads.0.badge', 'Sedang Ditangani')
            ->assertJsonPath('threads.0.badge_class', 'agent');
    }

    public function test_messages_json_marks_user_messages_read(): void
    {
        $this->atOfficeHours();
        $this->actingAsAdmin();
        $chat = $this->startChat();
        $faq = Faq::create(['code' => 'PSP-001', 'question' => 'q', 'answer' => 'a']);
        $chat->messages()->create(['sender_type' => 'user', 'message_text' => 'Halo', 'sent_at' => now()->subMinutes(2)]);
        $chat->messages()->create(['sender_type' => 'bot', 'message_text' => 'Menu', 'sent_at' => now()->subMinute(), 'faq_id' => $faq->id]);
        $chat->messages()->create(['sender_type' => 'user', 'message_type' => 'image', 'message_text' => null, 'attachment_path' => 'wa-media/1/a.jpg', 'sent_at' => now()]);
        $chat->messages()->create(['sender_type' => 'petugas', 'message_text' => 'Gagal', 'delivery_status' => 'failed', 'delivery_error' => 'API mati', 'sent_at' => now()]);

        $json = $this->getJson(route('percakapan.pesan', $chat))->assertOk()->json();

        $this->assertSame(['user', 'bot', 'user', 'petugas'], array_column($json['messages'], 'sender_type'));
        $this->assertSame('Budi', $json['messages'][0]['sender']);
        $this->assertSame('Bot KANCIL SAPA', $json['messages'][1]['sender']);
        $this->assertSame('PSP-001', $json['messages'][1]['faq_code']);
        $this->assertSame('[Foto]', $json['messages'][2]['text']);
        $this->assertStringContainsString('/lampiran/', $json['messages'][2]['attachment_url']);
        $this->assertSame('API mati', $json['messages'][3]['delivery_error']);
        $this->assertStringStartsWith('Hari ini', $json['messages'][0]['date']);
        $this->assertSame(0, Message::where('sender_type', 'user')->where('is_read', false)->count());
    }

    public function test_officer_reply_is_sent_and_takes_over(): void
    {
        $officer = $this->actingAsAdmin(['name' => 'Agus Setiawan']);
        $chat = $this->startChat();
        $chat->escalationLogs()->create(['escalation_type' => 'user_request', 'escalation_code' => 'A2', 'status' => 'Menunggu']);

        $response = $this->postJson(route('percakapan.balas', $chat), ['pesan' => '  Silakan datang ke loket 3.  '])
            ->assertCreated()
            ->assertJsonPath('message.sender', 'Petugas: Agus Setiawan')
            ->assertJsonPath('message.text', 'Silakan datang ke loket 3.')
            ->assertJsonPath('conversation.mode', 'HUMAN_HANDLING');

        $message = Message::sole();
        $this->assertSame('petugas', $message->sender_type);
        $this->assertSame($officer->id, $message->sender_user_id);
        $this->assertSame('sent', $message->delivery_status);
        $this->assertSame('Silakan datang ke loket 3.', $this->sentWhatsappTexts()[0]['message']);

        $chat->refresh();
        $this->assertSame($officer->id, $chat->assigned_to);
        $this->assertTrue($chat->bot_paused_until->isFuture());

        $log = EscalationLog::sole();
        $this->assertSame('Diambil', $log->status);
        $this->assertSame($officer->id, $log->handled_by);
        $this->assertNotNull($response->json('message.id'));
    }

    public function test_reply_validation(): void
    {
        $this->actingAsAdmin();
        $chat = $this->startChat();

        $this->postJson(route('percakapan.balas', $chat), ['pesan' => ''])->assertUnprocessable()->assertJsonValidationErrors(['pesan' => 'Pesan wajib diisi.']);
        $this->postJson(route('percakapan.balas', $chat), ['pesan' => str_repeat('a', 4001)])->assertUnprocessable();
        $this->assertSame(0, Message::count());
    }

    public function test_takeover_and_release(): void
    {
        $officer = $this->actingAsAdmin();
        $chat = $this->startChat(['conversation_mode' => 'WAITING_HUMAN']);
        $chat->escalationLogs()->create(['escalation_type' => 'user_request', 'escalation_code' => 'A2', 'status' => 'Menunggu']);

        $this->postJson(route('percakapan.ambil-alih', $chat))->assertOk()->assertJsonPath('conversation.mode', 'HUMAN_HANDLING');
        $this->assertSame($officer->id, $chat->fresh()->assigned_to);

        $this->postJson(route('percakapan.kembalikan', $chat))->assertOk()->assertJsonPath('conversation.mode', 'BOT_ACTIVE');
        $chat->refresh();
        $this->assertNull($chat->bot_paused_until);
        $this->assertNull($chat->assigned_to);
        $this->assertSame('Selesai', EscalationLog::sole()->status);
    }

    public function test_attachment_download_is_private(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('wa-media/1/bukti.pdf', '%PDF');
        $chat = $this->startChat();
        $message = $chat->messages()->create(['sender_type' => 'user', 'message_type' => 'document', 'attachment_path' => 'wa-media/1/bukti.pdf', 'meta' => ['media_name' => 'Bukti Bayar.pdf']]);
        $missing = $chat->messages()->create(['sender_type' => 'user', 'message_type' => 'image', 'attachment_path' => 'wa-media/1/hilang.jpg']);
        $none = $chat->messages()->create(['sender_type' => 'user', 'message_text' => 'teks']);

        $this->get(route('lampiran', $message))->assertRedirect('/login');

        $this->actingAsAdmin();
        $this->get(route('lampiran', $message))->assertOk()->assertDownload('Bukti Bayar.pdf');
        $this->get(route('lampiran', $missing))->assertNotFound();
        $this->get(route('lampiran', $none))->assertNotFound();
        $this->assertFileDoesNotExist(public_path('wa-media'));
    }

    public function test_unknown_conversation_is_404(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/percakapan/999/pesan')->assertNotFound();
        $this->postJson('/percakapan/999/balas', ['pesan' => 'x'])->assertNotFound();
    }

    public function test_guest_cannot_use_inbox_endpoints(): void
    {
        $chat = $this->startChat();

        $this->get(route('percakapan.data'))->assertRedirect('/login');
        $this->get(route('percakapan.pesan', $chat))->assertRedirect('/login');
        $this->post(route('percakapan.balas', $chat), ['pesan' => 'x'])->assertRedirect('/login');
        $this->post(route('percakapan.ambil-alih', $chat))->assertRedirect('/login');
        $this->post(route('percakapan.kembalikan', $chat))->assertRedirect('/login');
    }

    public function test_pimpinan_can_read_but_not_reply(): void
    {
        $this->actingAsAdmin(['role' => 'pimpinan']);
        $chat = $this->startChat();

        $this->get(route('percakapan'))->assertOk()->assertViewHas('canReply', false);
        $this->getJson(route('percakapan.pesan', $chat))->assertOk();
        $this->postJson(route('percakapan.balas', $chat), ['pesan' => 'x'])->assertForbidden();
        $this->postJson(route('percakapan.ambil-alih', $chat))->assertForbidden();
        $this->assertSame(0, Message::count());
    }

    public function test_petugas_can_reply(): void
    {
        $this->actingAsAdmin(['role' => 'petugas']);
        $chat = $this->startChat();

        $this->postJson(route('percakapan.balas', $chat), ['pesan' => 'Halo'])->assertCreated();
    }

    public function test_reply_to_lid_contact_is_saved_as_failed(): void
    {
        $this->actingAsAdmin();
        $chat = $this->startChat(['wa_chat_id' => '1@lid', 'whatsapp_number' => null]);

        $this->postJson(route('percakapan.balas', $chat), ['pesan' => 'Halo'])->assertCreated();

        $this->assertSame('failed', Message::sole()->delivery_status);
        $this->assertSame('1@lid', Conversation::sole()->recipientPhone() ?? '1@lid');
    }
}
