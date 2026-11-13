<?php

namespace Tests\Feature;

use App\Jobs\ProcessIncomingChat;
use App\Jobs\ProcessOutgoingChat;
use App\Jobs\SendWhatsappMessage;
use App\Jobs\UpdateMessageStatus;
use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Message;
use App\Models\ServiceTicket;
use App\Models\WebhookEvent;
use App\Services\BalesOtomatis\BalesOtomatisClient;
use App\Services\BalesOtomatis\BalesOtomatisException;
use App\Services\Bot\BotEngine;
use App\Services\Bot\EscalationRecorder;
use App\Services\Tickets\TicketQueueService;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Alur penuh: webhook → antrean (sync) → percakapan/pesan → BotEngine → kirim lewat API BalesOtomatis.
 */
class WhatsappPipelineTest extends TestCase
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

    // ------------------------------------------------------------ incoming_chat (Opsi B)

    public function test_incoming_message_creates_conversation_and_bot_replies_via_api(): void
    {
        $this->postWebhook($this->incomingPayload(['message_id' => 'IN-1', 'message_body' => 'Halo']))->assertOk();

        $conversation = Conversation::sole();
        $this->assertSame('6281234567890@s.whatsapp.net', $conversation->wa_chat_id);
        $this->assertSame('6281234567890', $conversation->whatsapp_number);
        $this->assertSame('Budi Santoso', $conversation->user_name);
        $this->assertSame('whatsapp', $conversation->channel);
        $this->assertSame(self::NUMBER_ID, $conversation->device_key);
        $this->assertNotNull($conversation->last_inbound_at);

        $user = Message::where('sender_type', 'user')->sole();
        $this->assertSame('IN-1', $user->external_message_id);
        $this->assertFalse($user->is_read);

        $bot = Message::where('sender_type', 'bot')->sole();
        $this->assertStringContainsString('Selamat Datang di Layanan Informasi dan Pengaduan', $bot->message_text);
        $this->assertSame(['codes' => ['M-00']], $bot->meta);
        $this->assertSame('sent', $bot->delivery_status);
        $this->assertSame('OUT-1', $bot->external_message_id);

        $sent = $this->sentWhatsappTexts();
        $this->assertCount(1, $sent);
        $this->assertSame('kode-akses-uji', $sent[0]['api_key']);
        $this->assertSame(self::NUMBER_ID, $sent[0]['number_id']);
        $this->assertSame('6281234567890', $sent[0]['phone_no']);
        $this->assertSame($bot->message_text, $sent[0]['message']);
        $this->assertSame($bot->client_uuid, $sent[0]['message_uuid']);

        $this->assertNotNull(WebhookEvent::sole()->processed_at);
    }

    public function test_full_menu_conversation_over_webhook(): void
    {
        foreach (['Halo', '1', '1', '1'] as $i => $text) {
            $this->postWebhook($this->incomingPayload(['message_id' => "IN-{$i}", 'message_body' => $text]));
        }

        $last = Message::where('sender_type', 'bot')->latest('id')->first();
        $this->assertStringContainsString('KTP elektronik', $last->message_text);
        $this->assertSame(4, Message::where('sender_type', 'bot')->count());
        $this->assertSame(1, Conversation::count());
    }

    public function test_duplicate_incoming_message_is_not_processed_twice(): void
    {
        $payload = $this->incomingPayload(['message_id' => 'IN-DUP']);
        $this->postWebhook($payload);

        // event baru (tanpa dedupe di webhook) dengan message_id sama tetap diabaikan job
        $event = WebhookEvent::create(['type' => 'incoming_chat', 'message_id' => 'IN-DUP', 'payload' => $payload]);
        (new ProcessIncomingChat($event->id))->handle(...$this->incomingDeps());

        $this->assertSame(1, Message::where('sender_type', 'user')->count());
        $this->assertNotNull($event->fresh()->processed_at);
    }

    public function test_lid_contact_without_phone_is_stored_but_reply_fails_gracefully(): void
    {
        $this->postWebhook($this->incomingPayload([
            'chat_id' => '123456789012345@lid', 'phone_number' => null, 'is_lid' => true, 'message_body' => 'Halo',
        ]));

        $conversation = Conversation::sole();
        $this->assertSame('123456789012345@lid', $conversation->wa_chat_id);
        $this->assertNull($conversation->whatsapp_number);

        $bot = Message::where('sender_type', 'bot')->sole();
        $this->assertSame('failed', $bot->delivery_status);
        $this->assertStringContainsString('LID', $bot->delivery_error);
        $this->assertSame([], $this->sentWhatsappTexts());
    }

    public function test_lid_contact_with_sender_phone_can_be_replied(): void
    {
        $this->postWebhook($this->incomingPayload([
            'chat_id' => '99999@lid', 'phone_number' => null, 'sender_phone' => '+62 812-1111-2222',
        ]));

        $this->assertSame('6281211112222', Conversation::sole()->whatsapp_number);
        $this->assertSame('6281211112222', $this->sentWhatsappTexts()[0]['phone_no']);
    }

    public function test_incoming_media_is_stored_privately(): void
    {
        Storage::fake('local');

        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload([
            'message_body' => null, 'has_media' => true, 'media_mime' => 'image/jpeg', 'media_name' => 'foto.jpg',
            'media_body' => base64_encode('JPEGDATA'),
        ]));

        $media = Message::where('sender_type', 'user')->where('message_type', 'image')->sole();
        $this->assertStringStartsWith('wa-media/', $media->attachment_path);
        $this->assertStringEndsWith('.jpg', $media->attachment_path);
        Storage::disk('local')->assertExists($media->attachment_path);
        $this->assertSame('JPEGDATA', Storage::disk('local')->get($media->attachment_path));
        $this->assertSame(['media_mime' => 'image/jpeg', 'media_name' => 'foto.jpg'], $media->meta);

        $this->assertStringContainsString('hanya menerima pesan teks', Message::where('sender_type', 'bot')->latest('id')->value('message_text'));
    }

    public function test_complaint_attachment_over_webhook_is_saved_on_ticket(): void
    {
        Storage::fake('local');

        foreach (['Halo', '3', '1', 'Budi', '1', 'Petugas parkir kantor meminta uang tambahan.', '1'] as $i => $text) {
            $this->postWebhook($this->incomingPayload(['message_id' => "C-{$i}", 'message_body' => $text]));
        }
        $this->postWebhook($this->incomingPayload([
            'message_id' => 'C-IMG', 'message_body' => null, 'has_media' => true, 'media_mime' => 'application/pdf',
            'media_name' => 'bukti.pdf', 'media_body' => base64_encode('%PDF'),
        ]));
        $this->postWebhook($this->incomingPayload(['message_id' => 'C-8', 'message_body' => 'selesai']));
        $this->postWebhook($this->incomingPayload(['message_id' => 'C-9', 'message_body' => '1']));

        $complaint = Complaint::sole();
        $this->assertSame('document', $complaint->attachments[0]['type']);
        $this->assertSame('bukti.pdf', $complaint->attachments[0]['name']);
        Storage::disk('local')->assertExists($complaint->attachments[0]['path']);
    }

    public function test_human_handling_silences_bot(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        Conversation::query()->update(['conversation_mode' => 'HUMAN_HANDLING', 'bot_paused_until' => now()->addHour()]);

        $this->postWebhook($this->incomingPayload(['message_body' => '0']));

        $this->assertSame(1, Message::where('sender_type', 'bot')->count());
        $this->assertSame(2, Message::where('sender_type', 'user')->count());
    }

    public function test_rate_limit_stops_bot_spam_in_same_minute(): void
    {
        config(['kancil.rate_limit_per_minute' => 2]);

        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload(['message_body' => '1']));
        $this->postWebhook($this->incomingPayload(['message_body' => '1']));
        $this->postWebhook($this->incomingPayload(['message_body' => '2']));

        $this->assertSame(4, Message::where('sender_type', 'user')->count());
        $this->assertSame(2, Message::where('sender_type', 'bot')->count());
    }

    public function test_bot_resumes_after_handoff_expires(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        Conversation::query()->update(['conversation_mode' => 'HUMAN_HANDLING', 'bot_paused_until' => now()->subMinute()]);

        $this->postWebhook($this->incomingPayload(['message_body' => '0']));

        $this->assertSame('BOT_ACTIVE', Conversation::sole()->conversation_mode);
        $this->assertSame(2, Message::where('sender_type', 'bot')->count());
    }

    public function test_closed_conversation_is_reopened_by_new_message(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        Conversation::query()->update(['conversation_mode' => 'CLOSED', 'closed_at' => now()]);

        $this->postWebhook($this->incomingPayload(['message_body' => '0']));

        $conversation = Conversation::sole();
        $this->assertSame('BOT_ACTIVE', $conversation->conversation_mode);
        $this->assertNull($conversation->closed_at);
    }

    public function test_is_from_me_in_incoming_is_ignored(): void
    {
        $this->postWebhook($this->incomingPayload(['is_from_me' => true]));

        $this->assertSame(0, Message::count());
        $this->assertNotNull(WebhookEvent::sole()->processed_at);
    }

    public function test_legacy_conversation_found_by_phone_gets_chat_id(): void
    {
        $legacy = Conversation::create(['whatsapp_number' => '6281234567890', 'channel' => 'whatsapp', 'started_at' => now()]);

        $this->postWebhook($this->incomingPayload());

        $this->assertSame(1, Conversation::count());
        $this->assertSame('6281234567890@s.whatsapp.net', $legacy->fresh()->wa_chat_id);
    }

    public function test_processing_error_is_recorded_on_event(): void
    {
        $event = WebhookEvent::create(['type' => 'incoming_chat', 'payload' => $this->incomingPayload()]);
        $engine = Mockery::mock(BotEngine::class);
        $engine->shouldReceive('handle')->andThrow(new \RuntimeException('meledak'));

        try {
            (new ProcessIncomingChat($event->id))->handle(app(WhatsappMessenger::class), $engine, app(EscalationRecorder::class), app(TicketQueueService::class));
            $this->fail('Exception harus diteruskan agar job tercatat gagal.');
        } catch (\RuntimeException) {
            $this->assertSame('meledak', $event->fresh()->error);
            $this->assertNull($event->fresh()->processed_at);
        }
    }

    public function test_missing_or_processed_event_is_skipped(): void
    {
        (new ProcessIncomingChat(999))->handle(...$this->incomingDeps());
        (new ProcessOutgoingChat(999))->handle(app(WhatsappMessenger::class), app(TicketQueueService::class));
        (new UpdateMessageStatus(999))->handle();

        $event = WebhookEvent::create(['type' => 'incoming_chat', 'payload' => $this->incomingPayload(), 'processed_at' => now()]);
        (new ProcessIncomingChat($event->id))->handle(...$this->incomingDeps());

        $this->assertSame(0, Message::count());
    }

    // ------------------------------------------------------------ Opsi A (BOT_ENGINE=balesotomatis)

    public function test_option_a_only_records_and_detects_requests_for_staff(): void
    {
        config(['kancil.bot_engine' => 'balesotomatis']);

        $this->postWebhook($this->incomingPayload(['message_body' => 'syarat paspor apa?']));
        $this->assertSame(0, Message::where('sender_type', 'bot')->count());
        $this->assertSame(0, EscalationLog::count());

        $this->postWebhook($this->incomingPayload(['message_body' => 'Saya mau bicara dengan petugas!']));
        $log = EscalationLog::sole();
        $this->assertSame('A2', $log->escalation_code);
        $this->assertSame('WAITING_HUMAN', Conversation::sole()->conversation_mode);

        $this->postWebhook($this->incomingPayload(['message_body' => '88']));
        $this->assertSame(1, EscalationLog::count(), 'Sudah menunggu petugas: tidak dicatat ulang.');
        $this->assertSame([], $this->sentWhatsappTexts());
    }

    // ------------------------------------------------------------ outgoing_chat

    public function test_echo_of_our_message_is_matched_by_uuid(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $bot = Message::where('sender_type', 'bot')->sole();
        $bot->update(['external_message_id' => null]);

        $this->postWebhook($this->incomingPayload(['message_id' => 'ECHO-1', 'message_body' => 'beda', 'message_uuid' => $bot->client_uuid], 'outgoing_chat'));

        $this->assertSame(1, Message::where('sender_type', 'bot')->count());
        $this->assertSame('ECHO-1', $bot->fresh()->external_message_id);
    }

    public function test_echo_of_our_message_is_matched_by_text(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $bot = Message::where('sender_type', 'bot')->sole();
        $bot->update(['external_message_id' => null, 'delivery_status' => 'queued']);

        $this->postWebhook($this->incomingPayload(['message_id' => 'ECHO-2', 'message_body' => $bot->message_text], 'outgoing_chat'));

        $this->assertSame(2, Message::count());
        $this->assertSame('ECHO-2', $bot->fresh()->external_message_id);
        $this->assertSame('sent', $bot->fresh()->delivery_status);
    }

    public function test_echo_is_still_matched_when_gateway_strips_dash_divider(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $bot = Message::where('sender_type', 'bot')->sole();
        $bot->update(['external_message_id' => null]);

        $strippedBody = str_replace("\n------------\n", "\n", (string) $bot->message_text);
        $this->assertNotSame($bot->message_text, $strippedBody);

        $this->postWebhook($this->incomingPayload(['message_id' => 'ECHO-STRIP', 'message_body' => $strippedBody], 'outgoing_chat'));

        $this->assertSame(2, Message::count());
        $this->assertSame('ECHO-STRIP', $bot->fresh()->external_message_id);
        $this->assertSame('BOT_ACTIVE', Conversation::sole()->conversation_mode);
        $this->assertSame(0, Message::where('sender_type', 'petugas')->count());
    }

    public function test_echo_with_known_external_id_is_ignored(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));

        $this->postWebhook($this->incomingPayload(['message_id' => 'OUT-1', 'message_body' => 'apa saja'], 'outgoing_chat'));

        $this->assertSame(2, Message::count());
    }

    public function test_manual_reply_from_phone_pauses_bot_in_option_b(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));

        $this->postWebhook($this->incomingPayload(['message_id' => 'HP-1', 'message_body' => 'Baik Pak, saya bantu.'], 'outgoing_chat'));

        $manual = Message::where('external_message_id', 'HP-1')->sole();
        $this->assertSame('petugas', $manual->sender_type);
        $conversation = Conversation::sole();
        $this->assertSame('HUMAN_HANDLING', $conversation->conversation_mode);
        $this->assertTrue($conversation->bot_paused_until->isFuture());
    }

    public function test_bot_echo_with_different_chat_id_joins_same_conversation(): void
    {
        // Pesan masuk memakai LID, echo balasan bot memakai JID nomor: tetap satu percakapan.
        $this->postWebhook($this->incomingPayload(['chat_id' => '99887766@lid', 'message_body' => 'Halo']));
        $bot = $this->lastBotMessage();

        $this->postWebhook($this->incomingPayload([
            'chat_id' => '6281234567890@s.whatsapp.net', 'message_id' => 'ECHO-1', 'message_body' => $bot->message_text,
        ], 'outgoing_chat'));

        $conversation = Conversation::sole();
        $this->assertSame('99887766@lid', $conversation->wa_chat_id);
        $this->assertSame('BOT_ACTIVE', $conversation->conversation_mode, 'Echo bot tidak dianggap petugas mengambil alih.');
        $this->assertSame(0, Message::where('sender_type', 'petugas')->count());
        $this->assertSame(['user', 'bot'], Message::orderBy('id')->pluck('sender_type')->all());
    }

    public function test_queue_keeps_bot_quiet_with_throttled_reminders_commands_and_cancel(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00', 'Asia/Jakarta'));
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload(['message_body' => '4']));

        $handoff = $this->lastBotMessage()->message_text;
        $this->assertStringContainsString('masuk antrean', $handoff);
        $this->assertStringContainsString('LYN-20261111-001', $handoff);
        $this->assertSame('WAITING_HUMAN', Conversation::sole()->conversation_mode);
        $ticket = ServiceTicket::sole();
        $this->assertSame('Menunggu', $ticket->status);
        $botCount = Message::where('sender_type', 'bot')->count();

        // Pesan biasa tepat setelah masuk antrean: bot diam (pengingat baru dikirim saat handoff).
        $this->postWebhook($this->incomingPayload(['message_body' => 'Pak, paspor saya hilang di Jakarta']));
        $this->postWebhook($this->incomingPayload(['message_body' => '1']));
        $this->assertSame($botCount, Message::where('sender_type', 'bot')->count(), 'Selama antrean bot diam.');
        $this->assertSame(4, Message::where('sender_type', 'user')->count(), 'Pesan pemohon tetap tercatat untuk petugas.');

        // Lewat 15 menit: pesan biasa dibalas pengingat posisi satu kali.
        Carbon::setTestNow(now()->addMinutes(16));
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo, masih lama?']));
        $this->assertSame($botCount + 1, Message::where('sender_type', 'bot')->count());
        $this->assertStringContainsString('Anda masih dalam antrean', $this->lastBotMessage()->message_text);
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo?']));
        $this->assertSame($botCount + 1, Message::where('sender_type', 'bot')->count(), 'Pengingat tidak diulang dalam 15 menit.');

        // Perintah "antrean": dijawab, dibatasi 1x per menit.
        $this->postWebhook($this->incomingPayload(['message_body' => 'antrean']));
        $this->assertSame($botCount + 1, Message::where('sender_type', 'bot')->count(), 'Perintah antrean dibatasi 1x per menit.');
        Carbon::setTestNow(now()->addMinutes(2));
        $this->postWebhook($this->incomingPayload(['message_body' => 'Cek antrean']));
        $this->assertSame($botCount + 2, Message::where('sender_type', 'bot')->count());
        $this->assertStringContainsString('Posisi antrean Anda: *ke-1*', $this->lastBotMessage()->message_text);

        // Tiket tidak lepas sendiri setelah berjam-jam (beda dengan batas tunggu lama).
        Carbon::setTestNow(now()->addMinutes(241));
        $this->artisan('kancil:pemeliharaan')->assertSuccessful();
        $this->assertSame('WAITING_HUMAN', Conversation::sole()->conversation_mode);

        $this->postWebhook($this->incomingPayload(['message_body' => 'batal']));
        $this->assertStringContainsString('dibatalkan', $this->lastBotMessage()->message_text);
        $this->assertSame('Batal', $ticket->fresh()->status);
        $this->assertSame('BOT_ACTIVE', Conversation::sole()->conversation_mode);

        $this->postWebhook($this->incomingPayload(['message_body' => '1']));
        $this->assertStringContainsString('Pelayanan Paspor WNI', $this->lastBotMessage()->message_text, 'Setelah batal bot melayani menu lagi.');
        Carbon::setTestNow();
    }

    public function test_officer_reply_while_waiting_takes_over(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload(['message_body' => '4']));

        $this->postWebhook($this->incomingPayload(['message_id' => 'HP-9', 'message_body' => 'Halo kak, ada yang bisa kami bantu?'], 'outgoing_chat'));

        $this->assertSame('HUMAN_HANDLING', Conversation::sole()->conversation_mode);
    }

    public function test_option_a_outgoing_is_recorded_as_ai_reply(): void
    {
        config(['kancil.bot_engine' => 'balesotomatis']);

        $this->postWebhook($this->incomingPayload(['message_id' => 'AI-1', 'message_body' => 'Jawaban AI BalesOtomatis'], 'outgoing_chat'));

        $ai = Message::sole();
        $this->assertSame('ai', $ai->sender_type);
        $this->assertSame('sent', $ai->delivery_status);
        $this->assertSame('BOT_ACTIVE', Conversation::sole()->conversation_mode);
    }

    // ------------------------------------------------------------ message_status (WABA)

    public function test_status_updates_only_move_forward(): void
    {
        $conversation = $this->startChat();
        $message = $conversation->messages()->create(['sender_type' => 'bot', 'message_text' => 'x', 'external_message_id' => 'wamid.1', 'delivery_status' => 'sent']);

        $this->postWebhook($this->incomingPayload(['message_id' => 'wamid.1', 'status' => 'read'], 'message_status'));
        $this->assertSame('read', $message->fresh()->delivery_status);

        $this->postWebhook($this->incomingPayload(['message_id' => 'wamid.1', 'status' => 'delivered'], 'message_status'));
        $this->assertSame('read', $message->fresh()->delivery_status);

        $this->postWebhook($this->incomingPayload(['message_id' => 'wamid.1', 'status' => 'failed'], 'message_status'));
        $this->assertSame('failed', $message->fresh()->delivery_status);
    }

    public function test_status_for_unknown_message_is_logged(): void
    {
        $this->postWebhook($this->incomingPayload(['message_id' => 'wamid.404', 'status' => 'read'], 'message_status'));

        $this->assertStringContainsString('tidak ditemukan', WebhookEvent::sole()->error);
    }

    // ------------------------------------------------------------ SendWhatsappMessage

    public function test_send_job_marks_non_retryable_error_failed(): void
    {
        $this->refakeHttp(['*' => Http::response(['code' => 403, 'message' => 'Paket tidak mendukung API'])]);
        $message = $this->queuedMessage();

        (new SendWhatsappMessage($message->id))->handle(app(BalesOtomatisClient::class));

        $this->assertSame('failed', $message->fresh()->delivery_status);
        $this->assertStringContainsString('Paket tidak mendukung API', $message->fresh()->delivery_error);
    }

    public function test_send_job_keeps_retryable_error_queued(): void
    {
        $this->refakeHttp(['*' => Http::response(['code' => 408, 'message' => 'Request timeout'])]);
        $message = $this->queuedMessage();

        (new SendWhatsappMessage($message->id))->handle(app(BalesOtomatisClient::class));

        $this->assertSame('queued', $message->fresh()->delivery_status);
        $this->assertStringContainsString('408', $message->fresh()->delivery_error);
    }

    public function test_send_job_failed_hook_marks_message(): void
    {
        $message = $this->queuedMessage();

        (new SendWhatsappMessage($message->id))->failed(new BalesOtomatisException('habis percobaan'));

        $this->assertSame('failed', $message->fresh()->delivery_status);
        $this->assertSame('habis percobaan', $message->fresh()->delivery_error);
    }

    public function test_send_job_skips_sent_and_missing_messages(): void
    {
        $message = $this->queuedMessage(['delivery_status' => 'sent']);

        (new SendWhatsappMessage($message->id))->handle(app(BalesOtomatisClient::class));
        (new SendWhatsappMessage(999))->handle(app(BalesOtomatisClient::class));

        $this->assertSame([], $this->sentWhatsappTexts());
    }

    public function test_send_job_fails_when_api_not_configured(): void
    {
        config(['balesotomatis.api_key' => null]);
        $message = $this->queuedMessage();

        (new SendWhatsappMessage($message->id))->handle(app(BalesOtomatisClient::class));

        $this->assertSame('failed', $message->fresh()->delivery_status);
        $this->assertStringContainsString('belum dikonfigurasi', $message->fresh()->delivery_error);
    }

    public function test_send_job_does_not_reuse_existing_external_id(): void
    {
        $this->refakeHttp(['*' => Http::response(['code' => 200, 'data' => ['message_id' => 'SAMA']])]);
        $conversation = $this->startChat();
        $conversation->messages()->create(['sender_type' => 'bot', 'message_text' => 'lama', 'external_message_id' => 'SAMA']);
        $message = $this->queuedMessage();

        (new SendWhatsappMessage($message->id))->handle(app(BalesOtomatisClient::class));

        $this->assertSame('sent', $message->fresh()->delivery_status);
        $this->assertNull($message->fresh()->external_message_id);
    }

    public function test_simulation_channel_messages_are_not_sent(): void
    {
        $conversation = $this->startChat(['channel' => 'simulasi', 'wa_chat_id' => 'simulasi:1']);

        $message = app(WhatsappMessenger::class)->queue($conversation, 'tes');

        $this->assertNull($message->delivery_status);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'balesotomatis'));
    }

    private function queuedMessage(array $overrides = []): Message
    {
        $conversation = $this->chat ?? $this->startChat();

        return $conversation->messages()->create(array_merge([
            'sender_type' => 'petugas', 'message_text' => 'Halo Pak', 'client_uuid' => 'uuid-1', 'delivery_status' => 'queued',
        ], $overrides));
    }

    private function incomingDeps(): array
    {
        return [
            app(WhatsappMessenger::class),
            app(BotEngine::class),
            app(EscalationRecorder::class),
            app(TicketQueueService::class),
        ];
    }
}
