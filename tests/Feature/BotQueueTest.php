<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Message;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Bot\BotEngine;
use App\Services\Bot\BotScript;
use App\Services\Tickets\TicketQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Alur pemohon: semua jalan masuk antrean petugas membuat tiket, pesan selama antre,
 * batal, Opsi A, balasan petugas dari HP, dan simulasi.
 */
class BotQueueTest extends TestCase
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

    private function queued(string $text): array
    {
        $this->chat->refresh();
        $message = $this->chat->messages()->create(['sender_type' => 'user', 'message_type' => 'text', 'message_text' => $text, 'sent_at' => now()]);

        return app(BotEngine::class)->handleQueued($this->chat, $message);
    }

    // ------------------------------------------------------------ semua jalan masuk antrean

    public function test_menu_hubungi_petugas_creates_ticket_and_tells_number_and_position(): void
    {
        $this->reply('Halo');
        $reply = $this->reply('4');

        $ticket = ServiceTicket::sole();
        $this->assertSame(['KTK-001', 'ESK-01', 'LYN'], $reply->codes);
        $this->assertStringContainsString("Nomor tiket: *{$ticket->ticket_number}*", $reply->text);
        $this->assertStringContainsString('Nomor antrean hari ini: *1*', $reply->text);
        $this->assertStringContainsString('Posisi antrean Anda: *ke-1* (berikutnya dilayani)', $reply->text);
        $this->assertStringContainsString(BotScript::QUEUE_HELP, $reply->text);
        $this->assertStringNotContainsString('081217000900', $reply->text);

        $this->assertSame('Menunggu', $ticket->status);
        $this->assertSame('A2', $ticket->escalation_code);
        $this->assertSame('3', $ticket->menu_path);
        $this->assertSame(EscalationLog::sole()->id, $ticket->escalation_log_id);
        $this->assertNull($ticket->question_text, 'Hanya salam + angka: tidak ada pertanyaan.');
        $chat = $this->chat->fresh();
        $this->assertSame('WAITING_HUMAN', $chat->conversation_mode);
        $this->assertNull($chat->bot_paused_until);
    }

    public function test_second_applicant_is_behind_the_first(): void
    {
        $this->reply('Halo');
        $this->reply('4');

        $this->startChat(['wa_chat_id' => '6289999@s.whatsapp.net', 'whatsapp_number' => '6289999', 'user_name' => 'Sari']);
        $this->reply('Halo');
        $reply = $this->reply('4');

        $this->assertStringContainsString('LYN-20261111-002', $reply->text);
        $this->assertStringContainsString('Nomor antrean hari ini: *2*', $reply->text);
        $this->assertStringContainsString('Posisi antrean Anda: *ke-2*', $reply->text);
        $this->assertStringNotContainsString('berikutnya dilayani', $reply->text);
    }

    public function test_88_after_special_case_answer_carries_code_priority_and_topic(): void
    {
        $this->conversation(['Halo', '1', '1', '5']); // Paspor hilang (C1)
        $reply = $this->reply('88');

        $ticket = ServiceTicket::sole();
        $this->assertContains('LYN', $reply->codes);
        $this->assertSame('C1', $ticket->escalation_code);
        $this->assertSame('Tinggi', $ticket->priority);
        $this->assertSame('0>1>5', $ticket->menu_path);
        $this->assertSame('Persyaratan Paspor › Paspor Hilang', BotScript::topicLabel($ticket->menu_path));
    }

    public function test_typing_1_after_regular_answer_queues_with_answer_path(): void
    {
        $this->conversation(['Halo', '1', '1', '1']); // Paspor baru
        $this->reply('1');

        $ticket = ServiceTicket::sole();
        $this->assertSame('A2', $ticket->escalation_code);
        $this->assertSame('0>1>1', $ticket->menu_path);
    }

    public function test_88_from_submenu_records_menu_path(): void
    {
        $this->conversation(['Halo', '1', '1']);
        $this->reply('88');

        $this->assertSame('0>1', ServiceTicket::sole()->menu_path);
        $this->assertSame('Persyaratan Paspor', BotScript::topicLabel('0>1'));
    }

    public function test_question_typed_before_88_is_kept_for_officer(): void
    {
        $this->reply('Halo');
        $this->reply('paspor saya rusak kena air bagaimana?');
        $this->reply('2');
        $this->reply('88');

        $this->assertSame('paspor saya rusak kena air bagaimana?', ServiceTicket::sole()->question_text);
    }

    public function test_old_question_from_previous_session_is_not_used(): void
    {
        $this->reply('Halo');
        $this->reply('paspor saya rusak kena air bagaimana?');
        Carbon::setTestNow(now()->addHour());
        $this->reply('Halo');
        $this->reply('4');

        $this->assertNull(ServiceTicket::sole()->question_text);
    }

    public function test_88_after_three_invalid_inputs_links_a1_log(): void
    {
        $this->reply('Halo');
        $this->reply('7');
        $this->reply('8');
        $this->assertSame(['MSG-03'], $this->reply('9x')->codes);
        $reply = $this->reply('88');

        $ticket = ServiceTicket::sole();
        $this->assertContains('LYN', $reply->codes);
        $this->assertSame('A1', $ticket->escalation_code);
        $this->assertSame(1, EscalationLog::count(), '88 setelah MSG-03 tidak dicatat dua kali.');
    }

    public function test_88_while_filling_complaint_discards_draft_and_queues(): void
    {
        $this->conversation(['Halo', '3', '1', 'Budi Santoso']);
        $reply = $this->reply('88');

        $this->assertContains('LYN', $reply->codes);
        $this->assertSame(['B0', 'A2'], EscalationLog::orderBy('id')->pluck('escalation_code')->all());
        $this->assertSame('A2', ServiceTicket::sole()->escalation_code);
    }

    public function test_ai_unknown_answer_queues_with_question(): void
    {
        $this->configureIntegration('laravel', llm: true);
        $this->fakeApis($this->aiJson('Tidak tahu', [], false));
        $this->reply('Halo');

        $reply = $this->reply('apakah bisa bikin paspor di hari libur nasional?');

        $ticket = ServiceTicket::sole();
        $this->assertSame(['AI-UNKNOWN', 'ESK-01', 'LYN'], $reply->codes);
        $this->assertSame('ai', $reply->sender);
        $this->assertStringContainsString($ticket->ticket_number, $reply->text);
        $this->assertSame('apakah bisa bikin paspor di hari libur nasional?', $ticket->question_text);
        $this->assertSame('AI', $ticket->menu_path);
        $this->assertSame('A1', $ticket->escalation_code);
    }

    public function test_outside_hours_adds_notice_after_queue_details(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-14 10:00', 'Asia/Jakarta')); // Sabtu
        $this->reply('Halo');
        $reply = $this->reply('4');

        $this->assertSame(['KTK-001', 'ESK-01', 'LYN', 'MSG-07'], $reply->codes);
        $this->assertStringContainsString('LYN-20261114-001', $reply->text);
        $this->assertStringEndsWith(BotScript::msg('MSG-07'), $reply->text);
    }

    public function test_escalating_again_reuses_open_ticket(): void
    {
        $this->reply('Halo');
        $this->reply('4');
        $this->reply('1');            // langsung lewat engine (tanpa job): menu
        $again = $this->reply('88');

        $this->assertSame(1, ServiceTicket::count());
        $this->assertStringContainsString(ServiceTicket::sole()->ticket_number, $again->text);
    }

    // ------------------------------------------------------------ pesan selama antre

    public function test_queued_messages_reminder_is_throttled(): void
    {
        $this->reply('Halo');
        $this->reply('4');

        $this->assertSame([], $this->queued('halo pak'), 'Baru saja diberi tahu saat handoff.');

        Carbon::setTestNow(now()->addMinutes(16));
        $replies = $this->queued('halo pak, masih lama?');
        $this->assertCount(1, $replies);
        $this->assertSame(['LYN-STATUS'], $replies[0]->codes);
        $this->assertStringContainsString('Anda masih dalam antrean', $replies[0]->text);
        $this->assertStringContainsString('Posisi antrean Anda: *ke-1*', $replies[0]->text);

        $this->assertSame([], $this->queued('pak?'));

        config(['kancil.ticket.notice_minutes' => 5]);
        Carbon::setTestNow(now()->addMinutes(6));
        $this->assertCount(1, $this->queued('pak??'), 'Jarak pengingat bisa diatur.');
    }

    /** @return array<string, array{string}> */
    public static function statusCommands(): array
    {
        return [
            'antrean' => ['antrean'], 'antrian' => ['Antrian'], 'cek antrean' => ['cek antrean'], 'cek antrian' => ['Cek antrian!'],
            'posisi' => ['posisi'], 'status' => ['status'], 'antrean saya' => ['antrean saya'], 'cek status' => ['cek status'],
        ];
    }

    /** @dataProvider statusCommands */
    public function test_status_command_answers_after_one_minute(string $command): void
    {
        $this->reply('Halo');
        $this->reply('4');

        $this->assertSame([], $this->queued($command), 'Masih dalam 1 menit sejak handoff.');

        Carbon::setTestNow(now()->addSeconds(61));
        $replies = $this->queued($command);
        $this->assertCount(1, $replies);
        $this->assertSame(['LYN-STATUS'], $replies[0]->codes);
    }

    public function test_status_shows_position_moving_forward(): void
    {
        $first = $this->startChat(['wa_chat_id' => 'a@s.whatsapp.net', 'whatsapp_number' => '6281']);
        $this->reply('Halo');
        $this->reply('4');
        $this->startChat(['wa_chat_id' => 'b@s.whatsapp.net', 'whatsapp_number' => '6282']);
        $this->reply('Halo');
        $this->reply('4');

        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertStringContainsString('*ke-2*', $this->queued('antrean')[0]->text);

        app(TicketQueueService::class)->claim($first->serviceTickets()->sole(), User::factory()->create());

        Carbon::setTestNow(now()->addMinutes(2));
        $this->assertStringContainsString('*ke-1* (berikutnya dilayani)', $this->queued('antrean')[0]->text);
    }

    public function test_status_while_ticket_is_handled_says_officer_is_on_it(): void
    {
        $this->reply('Halo');
        $this->reply('4');
        ServiceTicket::query()->update(['status' => 'Ditangani', 'last_notice_at' => now()->subHour()]);

        $replies = $this->queued('batal'); // tidak bisa batal saat sudah ditangani

        $this->assertStringContainsString('sedang ditangani', $replies[0]->text);
        $this->assertSame('Ditangani', ServiceTicket::sole()->status);
    }

    /** @return array<string, array{string}> */
    public static function cancelCommands(): array
    {
        return ['batal' => ['batal'], 'Batal.' => ['Batal.'], 'batalkan' => ['batalkan'], 'batal antrean' => ['batal antrean'], 'batal antrian' => ['batal antrian'], 'cancel' => ['cancel']];
    }

    /** @dataProvider cancelCommands */
    public function test_cancel_command_leaves_queue_and_shows_menu(string $command): void
    {
        $this->reply('Halo');
        $this->reply('4');
        $number = ServiceTicket::sole()->ticket_number;

        $replies = $this->queued($command);

        $this->assertSame(['LYN-BATAL', 'M-00'], $replies[0]->codes);
        $this->assertStringStartsWith(BotScript::queueCancelled($number), $replies[0]->text);
        $this->assertStringContainsString('Silakan pilih menu', $replies[0]->text);
        $this->assertSame('Batal', ServiceTicket::sole()->status);
        $chat = $this->chat->fresh();
        $this->assertSame('BOT_ACTIVE', $chat->conversation_mode);
        $this->assertSame('M-00', $chat->current_menu);
        $this->assertSame('M-00', $chat->session_state['menu']);

        $this->assertStringContainsString('Pelayanan Paspor WNI', $this->reply('1')->text, 'Bot melayani menu lagi.');
    }

    public function test_media_while_queued_gets_reminder_only_after_interval(): void
    {
        $this->reply('Halo');
        $this->reply('4');
        Carbon::setTestNow(now()->addMinutes(20));
        $media = $this->chat->messages()->create(['sender_type' => 'user', 'message_type' => 'image', 'sent_at' => now()]);

        $replies = app(BotEngine::class)->handleQueued($this->chat->fresh(), $media);

        $this->assertSame(['LYN-STATUS'], $replies[0]->codes);
    }

    public function test_handle_queued_without_ticket_stays_silent(): void
    {
        $this->startChat(['conversation_mode' => 'WAITING_HUMAN']);

        $this->assertSame([], $this->queued('antrean'));
    }

    // ------------------------------------------------------------ lewat webhook (job)

    public function test_webhook_messages_while_queued_go_to_queue_handler(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload(['message_body' => '4']));
        Carbon::setTestNow(now()->addMinutes(2));

        $this->postWebhook($this->incomingPayload(['message_body' => 'antrean']));

        $this->assertStringContainsString('Anda masih dalam antrean', $this->lastBotMessage()->message_text);
        $this->assertSame(['LYN-STATUS'], $this->lastBotMessage()->meta['codes']);
    }

    public function test_webhook_while_officer_handles_is_silent_even_for_queue_commands(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload(['message_body' => '4']));
        app(TicketQueueService::class)->assign(Conversation::sole(), User::factory()->create());
        $bots = Message::whereIn('sender_type', ['bot', 'ai'])->count();

        Carbon::setTestNow(now()->addMinutes(30));
        $this->postWebhook($this->incomingPayload(['message_body' => 'antrean']));
        $this->postWebhook($this->incomingPayload(['message_body' => 'batal']));

        $this->assertSame($bots, Message::whereIn('sender_type', ['bot', 'ai'])->count());
        $this->assertSame('Ditangani', ServiceTicket::sole()->status);
    }

    public function test_waiting_conversation_without_ticket_returns_to_bot(): void
    {
        // Data lama sebelum fitur tiket: menunggu tanpa tiket tidak boleh membuat pemohon terkunci.
        $this->startChat(['conversation_mode' => 'WAITING_HUMAN', 'bot_paused_until' => null]);

        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));

        $this->assertSame('BOT_ACTIVE', Conversation::sole()->conversation_mode);
        $this->assertStringContainsString('Selamat Datang', $this->lastBotMessage()->message_text);
    }

    public function test_rate_limit_also_applies_while_queued(): void
    {
        config(['kancil.rate_limit_per_minute' => 2]);
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload(['message_body' => '4']));
        ServiceTicket::query()->update(['last_notice_at' => now()->subHour()]); // jeda pengingat tidak menahan
        $bots = Message::where('sender_type', 'bot')->count();

        $this->postWebhook($this->incomingPayload(['message_body' => 'antrean']));
        $this->assertSame($bots, Message::where('sender_type', 'bot')->count(), 'Batas 2 balasan/menit sudah terpakai.');

        Carbon::setTestNow(now()->addSeconds(61));
        $this->postWebhook($this->incomingPayload(['message_body' => 'antrean']));
        $this->assertSame($bots + 1, Message::where('sender_type', 'bot')->count(), 'Menit berikutnya dijawab.');
    }

    public function test_option_a_request_for_officer_opens_ticket_silently(): void
    {
        $this->configureIntegration('balesotomatis');

        $this->postWebhook($this->incomingPayload(['message_body' => 'saya mau bicara dengan petugas']));

        $ticket = ServiceTicket::sole();
        $this->assertSame('Menunggu', $ticket->status);
        $this->assertSame('A2', $ticket->escalation_code);
        $this->assertSame('saya mau bicara dengan petugas', $ticket->question_text);
        $this->assertSame('WAITING_HUMAN', Conversation::sole()->conversation_mode);
        $this->assertSame([], $this->sentWhatsappTexts(), 'Opsi A: AI BalesOtomatis yang membalas.');

        $this->postWebhook($this->incomingPayload(['message_body' => 'petugas tolong']));
        $this->assertSame(1, ServiceTicket::count(), 'Tidak dobel saat masih menunggu.');
    }

    public function test_option_b_reply_from_officer_phone_marks_ticket_handled(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload(['message_body' => '4']));

        $this->postWebhook($this->incomingPayload(['message_id' => 'HP-1', 'message_body' => 'Baik Pak, saya bantu.'], 'outgoing_chat'));

        $ticket = ServiceTicket::sole();
        $this->assertSame('Ditangani', $ticket->status);
        $this->assertNotNull($ticket->first_response_at);
        $this->assertSame('HUMAN_HANDLING', Conversation::sole()->conversation_mode);
    }

    public function test_option_a_outgoing_ai_reply_does_not_touch_ticket(): void
    {
        $this->configureIntegration('balesotomatis');
        $this->postWebhook($this->incomingPayload(['message_body' => '88']));

        $this->postWebhook($this->incomingPayload(['message_id' => 'AI-1', 'message_body' => 'Jawaban AI'], 'outgoing_chat'));

        $this->assertSame('Menunggu', ServiceTicket::sole()->status);
    }

    public function test_handoff_expiry_closes_handled_ticket_and_bot_resumes(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload(['message_body' => '4']));
        app(TicketQueueService::class)->assign(Conversation::sole(), User::factory()->create(), responded: true);

        Carbon::setTestNow(now()->addMinutes(121));
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));

        $this->assertSame('Selesai', ServiceTicket::sole()->status);
        $this->assertSame('BOT_ACTIVE', Conversation::sole()->conversation_mode);
        $this->assertStringContainsString('Selamat Datang', $this->lastBotMessage()->message_text);
    }

    public function test_echo_of_ticket_closing_message_does_not_trigger_takeover(): void
    {
        $this->postWebhook($this->incomingPayload(['message_body' => 'Halo']));
        $this->postWebhook($this->incomingPayload(['message_body' => '4']));
        $officer = User::factory()->create();
        app(TicketQueueService::class)->resolve(ServiceTicket::sole(), $officer);
        $closing = Message::where('sender_type', 'system')->sole();

        $this->postWebhook($this->incomingPayload(['message_id' => 'ECHO-SYS', 'message_body' => $closing->message_text], 'outgoing_chat'));

        $this->assertSame('BOT_ACTIVE', Conversation::sole()->conversation_mode);
        $this->assertSame(0, Message::where('sender_type', 'petugas')->count());
        $this->assertSame('sent', $closing->fresh()->delivery_status, 'Echo dicocokkan ke pesan sistem kita.');
    }

    public function test_merging_duplicate_conversations_keeps_one_open_ticket(): void
    {
        $a = $this->startChat(['wa_chat_id' => '111@lid', 'whatsapp_number' => '6285786470923', 'conversation_mode' => 'WAITING_HUMAN']);
        $b = $this->startChat(['wa_chat_id' => '6285786470923@s.whatsapp.net', 'whatsapp_number' => '6285786470923', 'conversation_mode' => 'BOT_ACTIVE']);
        $queue = app(TicketQueueService::class);
        [$older] = $queue->open($a);
        Carbon::setTestNow(now()->addMinute());
        [$newer] = $queue->open($b);

        $this->artisan('kancil:gabung-percakapan')->assertSuccessful();

        $merged = Conversation::sole();
        $this->assertSame(2, $merged->serviceTickets()->count());
        $this->assertSame($older->id, $merged->openTicket->id);
        $this->assertSame('Batal', $newer->fresh()->status);
        $this->assertStringContainsString($older->ticket_number, $newer->fresh()->close_reason);
        $this->assertSame('WAITING_HUMAN', $merged->conversation_mode);
    }

    // ------------------------------------------------------------ simulasi

    public function test_simulation_shows_queue_and_silence(): void
    {
        $this->artisan('kancil:simulasi', ['pesan' => 'Halo'])->assertSuccessful();
        $this->artisan('kancil:simulasi', ['pesan' => '4'])->expectsOutputToContain('LYN-20261111-001')->assertSuccessful();
        $this->artisan('kancil:simulasi', ['pesan' => 'halo pak'])->expectsOutputToContain('Bot diam')->assertSuccessful();
        $this->artisan('kancil:simulasi', ['pesan' => 'batal'])->expectsOutputToContain('dibatalkan')->assertSuccessful();
    }

    // ------------------------------------------------------------ teks

    public function test_queue_texts(): void
    {
        $this->assertSame(
            BotScript::handoff()."\n\nNomor tiket: *LYN-1*\nNomor antrean hari ini: *3*\nPosisi antrean Anda: *ke-2*\n\n"
            ."Petugas akan membalas di chat ini sesuai urutan antrean.\n".BotScript::QUEUE_HELP,
            BotScript::handoff('LYN-1', 3, 2),
        );
        $this->assertStringNotContainsString('Posisi', BotScript::handoff('LYN-1', 3, null));
        $this->assertStringNotContainsString('Nomor antrean', BotScript::handoff('LYN-1', null, 1));
        $this->assertSame('Tiket *LYN-1* sedang ditangani *Petugas*. Mohon tunggu balasan di chat ini.', BotScript::queueStatus('LYN-1', 1, null, true));
        $this->assertStringContainsString('*LYN-1*', BotScript::ticketResolved('LYN-1'));
        $this->assertStringContainsString('Ketik *88*', BotScript::ticketExpired('LYN-1'));
    }

    /** @return array<string, array{?string, string}> */
    public static function topics(): array
    {
        return [
            'kosong' => [null, 'Umum'],
            'string kosong' => ['', 'Umum'],
            'ai' => ['AI', 'Pertanyaan bebas (AI)'],
            'menu 3' => ['3', 'Hubungi Petugas (menu utama)'],
            'jawaban' => ['0>2>1', 'Cara Pembayaran Paspor › ATM'],
            'jawaban level 1' => ['0>4', 'Pelayanan Paspor WNI › Cek Status Permohonan'],
            'menu' => ['1>1', 'Tarif Izin Tinggal dan Visa'],
            'tidak dikenal' => ['9>9', '9>9'],
        ];
    }

    /** @dataProvider topics */
    public function test_topic_label(?string $path, string $label): void
    {
        $this->assertSame($label, BotScript::topicLabel($path));
    }
}
