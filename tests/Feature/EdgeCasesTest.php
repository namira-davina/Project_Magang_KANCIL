<?php

namespace Tests\Feature;

use App\Jobs\ProcessOutgoingChat;
use App\Models\BotSetting;
use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Faq;
use App\Models\FaqKeyword;
use App\Models\LlmLog;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\BalesOtomatis\WebhookData;
use App\Services\ComplaintService;
use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use App\Services\MonthlyReport;
use App\Services\Tickets\TicketQueueService;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BotHelpers;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

/**
 * Kasus tepi: balapan unik, galat, media rusak, label tanggal, relasi model.
 */
class EdgeCasesTest extends TestCase
{
    use BotHelpers, CreatesData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private static function uniqueViolation(): QueryException
    {
        return new QueryException('sqlite', 'insert', [], new RuntimeException('UNIQUE constraint failed: x'));
    }

    public function test_webhook_race_on_unique_key_returns_duplicate(): void
    {
        Queue::fake();
        WebhookEvent::creating(fn () => throw self::uniqueViolation());

        $this->postWebhook($this->incomingPayload())->assertOk()->assertSee('duplicate');
    }

    public function test_webhook_other_database_errors_are_not_swallowed(): void
    {
        WebhookEvent::creating(fn () => throw new QueryException('sqlite', 'insert', [], new RuntimeException('disk penuh')));

        $this->postWebhook($this->incomingPayload())->assertStatus(500);
    }

    public function test_ticket_number_retries_on_unique_collision(): void
    {
        $failures = 1;
        Complaint::creating(function () use (&$failures) {
            if ($failures-- > 0) {
                throw self::uniqueViolation();
            }
        });

        $complaint = app(ComplaintService::class)->createFromBot($this->startChat(), ['name' => 'A', 'category' => '1', 'text' => 'isi']);

        $this->assertStringStartsWith('ADU-', $complaint->ticket_number);
    }

    public function test_ticket_number_gives_up_on_other_errors(): void
    {
        Complaint::creating(fn () => throw new QueryException('sqlite', 'insert', [], new RuntimeException('lain')));

        $this->expectException(QueryException::class);
        app(ComplaintService::class)->createFromBot($this->startChat(), ['name' => 'A', 'category' => '1', 'text' => 'isi']);
    }

    public function test_outgoing_job_records_error_and_rethrows(): void
    {
        $event = WebhookEvent::create(['type' => 'outgoing_chat', 'payload' => $this->incomingPayload([], 'outgoing_chat')]);
        $messenger = Mockery::mock(WhatsappMessenger::class);
        $messenger->shouldReceive('resolveConversation')->andThrow(new RuntimeException('gagal'));

        try {
            (new ProcessOutgoingChat($event->id))->handle($messenger, app(TicketQueueService::class));
            $this->fail();
        } catch (RuntimeException) {
            $this->assertSame('gagal', $event->fresh()->error);
        }
    }

    public function test_outgoing_media_without_text_is_recorded_as_new_message(): void
    {
        $this->fakeApis();
        $chat = $this->startChat();
        $chat->messages()->create(['sender_type' => 'bot', 'message_text' => 'x', 'client_uuid' => 'u', 'sent_at' => now()]);

        $this->postWebhook($this->incomingPayload(['message_body' => null, 'has_media' => true, 'media_mime' => 'image/png'], 'outgoing_chat'));

        $this->assertSame('image', Message::latest('id')->first()->message_type);
    }

    public function test_outgoing_group_event_is_skipped_by_job(): void
    {
        $event = WebhookEvent::create(['type' => 'outgoing_chat', 'payload' => $this->incomingPayload(['is_group' => true], 'outgoing_chat')]);

        (new ProcessOutgoingChat($event->id))->handle(app(WhatsappMessenger::class), app(TicketQueueService::class));

        $this->assertSame(0, Message::count());
        $this->assertNotNull($event->fresh()->processed_at);
    }

    /** @return array<string, array{?string}> */
    public static function badMedia(): array
    {
        return [
            'kosong' => [null],
            'bukan base64' => ['%%%%'],
            'terlalu besar' => [str_repeat('A', (int) (WhatsappMessenger::MAX_MEDIA_BYTES * 4 / 3) + 100)],
        ];
    }

    #[DataProvider('badMedia')]
    public function test_invalid_media_is_not_stored(?string $body): void
    {
        Storage::fake('local');

        $path = app(WhatsappMessenger::class)->storeMedia($this->startChat(), new WebhookData(['media_body' => $body, 'media_mime' => 'image/png']));

        $this->assertNull($path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_data_uri_media_and_unknown_type(): void
    {
        Storage::fake('local');

        $path = app(WhatsappMessenger::class)->storeMedia($this->startChat(), new WebhookData([
            'media_body' => 'data:application/octet-stream;base64,'.base64_encode('BIN'), 'media_mime' => 'application/x-aneh',
        ]));

        $this->assertStringNotContainsString('.', basename($path));
        $this->assertSame('BIN', Storage::disk('local')->get($path));
    }

    /** @return array<string, array{string, string}> */
    public static function moreExtensions(): array
    {
        return [
            ['image/jpg', 'jpg'], ['image/webp', 'webp'], ['image/gif', 'gif'], ['audio/mpeg', 'mp3'], ['audio/mp4', 'm4a'],
            ['video/mp4', 'mp4'], ['application/msword', 'doc'],
            ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx'],
        ];
    }

    #[DataProvider('moreExtensions')]
    public function test_more_media_extensions(string $mime, string $ext): void
    {
        $this->assertSame($ext, WhatsappMessenger::extensionFor($mime, null));
    }

    public function test_pending_status_string(): void
    {
        $this->assertSame('queued', (new WebhookData(['status' => 'pending']))->deliveryStatus());
    }

    public function test_recipient_phone_from_jid_and_lid(): void
    {
        $this->assertSame('6281', (new Conversation(['wa_chat_id' => '6281@s.whatsapp.net']))->recipientPhone());
        $this->assertNull((new Conversation(['wa_chat_id' => '1@lid']))->recipientPhone());
        $this->assertSame('KS-00007', (new Conversation)->forceFill(['id' => 7])->displayCode());
        $this->assertSame('T-1', (new Conversation(['ticket_number' => 'T-1']))->displayCode());
    }

    public function test_human_handling_without_pause_end_stays_human(): void
    {
        $this->assertTrue((new Conversation(['conversation_mode' => 'HUMAN_HANDLING']))->isHumanHandling());
        $this->assertFalse((new Conversation(['conversation_mode' => 'BOT_ACTIVE']))->isHumanHandling());
    }

    public function test_thread_and_message_labels_for_all_modes_and_senders(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00', 'Asia/Jakarta'));
        $this->actingAsAdmin();
        $chat = $this->startChat(['conversation_mode' => 'CLOSED', 'last_message_at' => now()->subDays(3), 'user_name' => 'Madonna']);
        $chat->messages()->create(['sender_type' => 'ai', 'message_text' => 'a', 'sent_at' => now()->subDay()]);
        $chat->messages()->create(['sender_type' => 'system', 'message_text' => 's', 'sent_at' => now()->subDays(3)]);
        $chat->messages()->create(['sender_type' => 'petugas', 'message_text' => 'p', 'sent_at' => now()->subDays(3)]);
        $chat->messages()->create(['sender_type' => 'user', 'message_type' => 'audio', 'sent_at' => now()]);
        $chat->messages()->create(['sender_type' => 'user', 'message_type' => 'document', 'sent_at' => now()]);
        $chat->messages()->create(['sender_type' => 'user', 'message_type' => 'other', 'sent_at' => now()]);

        $thread = $this->get(route('percakapan'))->viewData('threads')[0];
        $this->assertSame('Selesai', $thread['badge']);
        $this->assertSame('success', $thread['badge_class']);
        $this->assertSame('MA', $thread['initial']);
        $this->assertSame('08 Nov', $thread['time']);

        $messages = collect($this->getJson(route('percakapan.pesan', $chat))->json('messages'))->keyBy('sender_type');
        $all = $this->getJson(route('percakapan.pesan', $chat))->json('messages');
        $this->assertSame('AI KANCIL SAPA', $messages['ai']['sender']);
        $this->assertStringStartsWith('Kemarin', $messages['ai']['date']);
        $this->assertSame('Sistem', $messages['system']['sender']);
        $this->assertSame('Petugas', $messages['petugas']['sender']);
        $this->assertStringStartsWith('Minggu, 08 November 2026', $messages['system']['date']);
        $this->assertSame(['[Pesan suara]', '[Dokumen]', '[Lampiran]'], array_slice(array_column($all, 'text'), 3));
    }

    public function test_thread_initials_fallback(): void
    {
        $this->actingAsAdmin();
        $this->startChat(['user_name' => '1234', 'last_message_at' => now()]);

        $this->assertSame('WA', $this->get(route('percakapan'))->viewData('threads')[0]['initial']);
    }

    public function test_unknown_contact_label(): void
    {
        $this->actingAsAdmin();
        $this->startChat(['user_name' => null, 'whatsapp_number' => null, 'wa_chat_id' => '9@lid']);

        $thread = $this->get(route('percakapan'))->viewData('threads')[0];
        $this->assertSame('Kontak WhatsApp', $thread['name']);
        $this->assertSame('9@lid', $thread['phone']);
    }

    public function test_report_ignores_unresolved_or_unprioritised_tickets_for_punctuality(): void
    {
        Complaint::create(['ticket_number' => 'ADU-1', 'user_name' => 'a', 'whatsapp_number' => '1', 'complaint_text' => 'x',
            'status' => 'Selesai', 'priority' => null, 'submitted_at' => now()]);

        $report = app(MonthlyReport::class)->build((int) now()->month, (int) now()->year);

        $this->assertSame(1, $report['tiket']['tepatWaktu']);
    }

    public function test_llm_balance_connection_error(): void
    {
        Http::fake(fn () => throw new ConnectionException('putus'));

        $this->expectException(LlmException::class);
        (new LlmClient(['api_key' => 'k', 'base_url' => 'https://ai.sumopod.com/v1']))->balance();
    }

    public function test_model_relations(): void
    {
        $user = $this->makeUser();
        $chat = $this->startChat(['assigned_to' => $user->id]);
        $category = ComplaintCategory::create(['name' => 'K']);
        $complaint = Complaint::create(['ticket_number' => 'ADU-1', 'conversation_id' => $chat->id, 'category_id' => $category->id,
            'user_name' => 'a', 'whatsapp_number' => '1', 'complaint_text' => 'x', 'status' => 'Menunggu']);
        $faq = Faq::create(['code' => 'X-1', 'question' => 'q', 'answer' => 'a']);
        $keyword = FaqKeyword::create(['faq_id' => $faq->id, 'keyword' => 'k']);
        $log = EscalationLog::create(['conversation_id' => $chat->id, 'escalation_type' => 'user_request', 'faq_id' => $faq->id, 'handled_by' => $user->id]);
        $setting = BotSetting::create(['setting_key' => 'k', 'updated_by' => $user->id]);
        $llm = LlmLog::create(['conversation_id' => $chat->id, 'question' => 'q']);

        $this->assertTrue($chat->complaint->is($complaint));
        $this->assertTrue($chat->assignedPetugas->is($user));
        $this->assertTrue($category->complaints->first()->is($complaint));
        $this->assertTrue($keyword->faq->is($faq));
        $this->assertTrue($log->faq->is($faq));
        $this->assertTrue($log->conversation->is($chat));
        $this->assertTrue($log->handler->is($user));
        $this->assertTrue($setting->updater->is($user));
        $this->assertTrue($llm->conversation->is($chat));
        $this->assertTrue($user->handledEscalations->first()->is($log));
        $this->assertTrue($user->assignedConversations->first()->is($chat));
        $this->assertSame(1, EscalationLog::escalations()->count());
    }
}
