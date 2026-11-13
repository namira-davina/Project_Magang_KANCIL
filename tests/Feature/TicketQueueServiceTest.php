<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\EscalationLog;
use App\Models\Message;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Bot\EscalationRecorder;
use App\Services\Tickets\TicketException;
use App\Services\Tickets\TicketQueueService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Antrean tiket petugas (TicketQueueService): nomor, FIFO, ambil, selesai, batal, kedaluwarsa, bentrok antar petugas.
 */
class TicketQueueServiceTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    private TicketQueueService $queue;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->atOfficeHours();
        $this->configureIntegration('laravel');
        $this->fakeApis();
        $this->queue = app(TicketQueueService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function applicant(array $attributes = []): Conversation
    {
        $n = ++$this->seq;

        return Conversation::create(array_merge([
            'wa_chat_id' => "62811000000{$n}@s.whatsapp.net",
            'whatsapp_number' => "62811000000{$n}",
            'user_name' => "Pemohon {$n}",
            'channel' => 'whatsapp',
            'conversation_mode' => Conversation::MODE_WAITING,
            'started_at' => now(),
        ], $attributes));
    }

    private function enqueue(array $attributes = [], ?string $question = null): ServiceTicket
    {
        return $this->queue->open($this->applicant($attributes), null, $question)[0];
    }

    private function officer(string $role = 'petugas'): User
    {
        return User::factory()->role($role)->create();
    }

    // ------------------------------------------------------------ nomor tiket

    public function test_open_numbers_tickets_per_day_and_resets_next_day(): void
    {
        $a = $this->enqueue();
        $b = $this->enqueue();

        $this->assertSame('LYN-20261111-001', $a->ticket_number);
        $this->assertSame(1, $a->queue_no);
        $this->assertSame('LYN-20261111-002', $b->ticket_number);
        $this->assertSame('2026-11-11', $b->queue_date->toDateString());
        $this->assertSame('Menunggu', $a->status);
        $this->assertNotNull($a->queued_at);
        $this->assertNotNull($a->last_notice_at, 'Handoff sudah memberi tahu posisi, jadi pengingat berikutnya ditahan.');

        Carbon::setTestNow(Carbon::parse('2026-11-12 08:00', 'Asia/Jakarta'));
        $c = $this->enqueue();
        $this->assertSame('LYN-20261112-001', $c->ticket_number);
        $this->assertSame(1, $c->queue_no);
    }

    public function test_queue_date_follows_jakarta_time_not_utc(): void
    {
        // 00:30 WIB = 17:30 UTC hari sebelumnya.
        Carbon::setTestNow(Carbon::parse('2026-11-12 00:30', 'Asia/Jakarta'));

        $this->assertSame('LYN-20261112-001', $this->enqueue()->ticket_number);
    }

    public function test_open_reuses_open_ticket_instead_of_duplicating(): void
    {
        $chat = $this->applicant();
        [$first, $created] = $this->queue->open($chat);
        [$again, $createdAgain] = $this->queue->open($chat, null, 'pertanyaan lain');

        $this->assertTrue($created);
        $this->assertFalse($createdAgain);
        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, ServiceTicket::count());

        // Tiket yang sedang ditangani juga dipakai ulang.
        $first->update(['status' => 'Ditangani']);
        $this->assertSame($first->id, $this->queue->open($chat)[0]->id);
    }

    public function test_open_after_ticket_closed_creates_new_ticket(): void
    {
        $chat = $this->applicant();
        [$first] = $this->queue->open($chat);
        $first->update(['status' => 'Selesai']);

        [$second, $created] = $this->queue->open($chat);

        $this->assertTrue($created);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, $second->queue_no);
    }

    public function test_open_copies_escalation_details_and_cleans_question(): void
    {
        $chat = $this->applicant();
        $log = app(EscalationRecorder::class)->record($chat, 'C1', 'x', null, '0>1>5');

        [$ticket] = $this->queue->open($chat, $log, '  '.str_repeat('a', 2500).'  ');

        $this->assertSame($log->id, $ticket->escalation_log_id);
        $this->assertSame('C1', $ticket->escalation_code);
        $this->assertSame('Tinggi', $ticket->priority);
        $this->assertSame('0>1>5', $ticket->menu_path);
        $this->assertSame(2000, mb_strlen($ticket->question_text));
        $this->assertSame($log->id, $ticket->escalationLog->id);
        $this->assertSame($chat->id, $ticket->conversation->id);

        $this->assertNull($this->enqueue([], '   ')->question_text, 'Pertanyaan kosong disimpan null.');
    }

    public function test_queue_number_gives_up_after_repeated_unique_collisions(): void
    {
        // Nomor tiket yang akan dibuat sudah dipakai baris lain (data rusak): coba ulang lalu menyerah.
        ServiceTicket::create([
            'ticket_number' => 'LYN-20261111-001', 'queue_date' => '2026-01-01', 'queue_no' => 1,
            'conversation_id' => $this->applicant()->id, 'status' => 'Selesai', 'queued_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        $this->enqueue();
    }

    // ------------------------------------------------------------ posisi FIFO

    public function test_positions_follow_first_come_first_served(): void
    {
        $first = $this->enqueue();
        Carbon::setTestNow(now()->addMinute());
        $second = $this->enqueue();
        $third = $this->enqueue(); // waktu sama dengan $second: urut id

        $this->assertSame(1, $this->queue->position($first));
        $this->assertSame(2, $this->queue->position($second));
        $this->assertSame(3, $this->queue->position($third));
        $this->assertSame([$first->id => 1, $second->id => 2, $third->id => 3], $this->queue->positions());

        $this->queue->claim($first, $this->officer());

        $this->assertNull($this->queue->position($first->fresh()), 'Tiket yang ditangani tidak punya posisi.');
        $this->assertSame(1, $this->queue->position($second));
        $this->assertSame(2, $this->queue->position($third));
        $this->assertSame([$second->id => 1, $third->id => 2], $this->queue->positions());
    }

    public function test_older_ticket_is_ahead_even_if_created_later_by_id(): void
    {
        $newer = $this->enqueue();
        $older = $this->enqueue();
        $older->update(['queued_at' => now()->subHour()]);

        $this->assertSame(1, $this->queue->position($older->fresh()));
        $this->assertSame(2, $this->queue->position($newer));
    }

    // ------------------------------------------------------------ ambil / tangani

    public function test_assign_moves_ticket_to_handling_and_conversation_to_officer(): void
    {
        $officer = $this->officer();
        $ticket = $this->enqueue();
        $chat = $ticket->conversation;
        $log = EscalationLog::create(['conversation_id' => $chat->id, 'escalation_type' => 'user_request', 'status' => 'Menunggu']);

        $result = $this->queue->assign($chat, $officer);

        $this->assertSame($ticket->id, $result->id);
        $ticket->refresh();
        $this->assertSame('Ditangani', $ticket->status);
        $this->assertSame($officer->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->taken_at);
        $this->assertNull($ticket->first_response_at, 'Ambil alih belum dihitung sebagai respon.');
        $chat->refresh();
        $this->assertSame('HUMAN_HANDLING', $chat->conversation_mode);
        $this->assertSame($officer->id, $chat->assigned_to);
        $this->assertTrue($chat->bot_paused_until->isFuture());
        $this->assertSame('Diambil', $log->fresh()->status);
        $this->assertSame($officer->id, $log->fresh()->handled_by);

        Carbon::setTestNow(now()->addMinutes(5));
        $takenAt = $ticket->taken_at;
        $this->queue->assign($chat, $officer, responded: true);
        $this->assertEquals($takenAt, $ticket->fresh()->taken_at, 'Waktu ambil tidak berubah.');
        $this->assertNotNull($first = $ticket->fresh()->first_response_at);

        Carbon::setTestNow(now()->addMinutes(5));
        $this->queue->assign($chat, $officer, responded: true);
        $this->assertEquals($first, $ticket->fresh()->first_response_at, 'Respon pertama tidak ditimpa.');
    }

    public function test_assign_without_ticket_still_hands_conversation_to_officer(): void
    {
        $chat = $this->applicant(['conversation_mode' => 'BOT_ACTIVE']);

        $this->assertNull($this->queue->assign($chat, $this->officer()));
        $this->assertSame('HUMAN_HANDLING', $chat->fresh()->conversation_mode);
    }

    public function test_officer_cannot_take_conversation_held_by_another_officer_but_admin_can(): void
    {
        $budi = $this->officer();
        $sari = $this->officer();
        $admin = $this->officer('admin');
        $ticket = $this->enqueue();
        $this->queue->assign($ticket->conversation, $budi);

        try {
            $this->queue->assign($ticket->conversation->fresh(), $sari);
            $this->fail('Petugas lain harus ditolak.');
        } catch (TicketException $e) {
            $this->assertSame(409, $e->status);
            $this->assertStringContainsString($budi->name, $e->getMessage());
        }

        $this->assertSame($budi->id, $ticket->fresh()->assigned_to);

        $this->queue->assign($ticket->conversation->fresh(), $admin);
        $this->assertSame($admin->id, $ticket->fresh()->assigned_to);
        $this->assertSame($admin->id, $ticket->conversation->fresh()->assigned_to);
    }

    public function test_ticket_handled_by_other_blocks_officer_even_if_handoff_window_lapsed(): void
    {
        $budi = $this->officer();
        $ticket = $this->enqueue();
        $this->queue->assign($ticket->conversation, $budi);
        // Masa ambil alih habis tapi pemeliharaan belum berjalan.
        $ticket->conversation->update(['bot_paused_until' => now()->subMinute()]);

        $this->expectExceptionObject(TicketException::takenByOther($budi->name));
        $this->queue->claim($ticket->fresh(), $this->officer());
    }

    public function test_handling_ticket_without_known_officer_can_be_taken(): void
    {
        $ticket = $this->enqueue();
        $ticket->update(['status' => 'Ditangani', 'assigned_to' => null]); // dibalas dari HP
        $officer = $this->officer();

        $this->queue->claim($ticket, $officer);

        $this->assertSame($officer->id, $ticket->fresh()->assigned_to);
    }

    public function test_claim_rejects_closed_tickets(): void
    {
        foreach (['Selesai', 'Kedaluwarsa', 'Batal'] as $status) {
            $ticket = $this->enqueue();
            $ticket->update(['status' => $status]);

            try {
                $this->queue->claim($ticket, $this->officer());
                $this->fail("Tiket {$status} tidak boleh diambil.");
            } catch (TicketException $e) {
                $this->assertSame(422, $e->status);
            }
        }
    }

    public function test_claim_next_takes_oldest_waiting_ticket(): void
    {
        $officer = $this->officer();
        $this->assertNull($this->queue->claimNext($officer), 'Antrean kosong.');

        $handled = $this->enqueue();
        $handled->update(['status' => 'Ditangani', 'assigned_to' => $this->officer()->id]);
        Carbon::setTestNow(now()->addMinute());
        $oldest = $this->enqueue();
        Carbon::setTestNow(now()->addMinute());
        $next = $this->enqueue();

        $claimed = $this->queue->claimNext($officer);
        $this->assertSame($oldest->id, $claimed->id);
        $this->assertSame('Ditangani', $claimed->status);
        $this->assertSame($officer->id, $claimed->assigned_to);
        $this->assertSame('HUMAN_HANDLING', $oldest->conversation->fresh()->conversation_mode);

        $this->assertSame($next->id, $this->queue->claimNext($this->officer())->id);
        $this->assertNull($this->queue->claimNext($this->officer()));
    }

    public function test_claim_next_puts_ticket_back_when_conversation_is_held_by_other_officer(): void
    {
        $holder = $this->officer();
        $ticket = $this->enqueue(['conversation_mode' => 'HUMAN_HANDLING', 'bot_paused_until' => now()->addHour()]);
        $ticket->conversation->update(['assigned_to' => $holder->id]);

        try {
            $this->queue->claimNext($this->officer());
            $this->fail('Harus bentrok.');
        } catch (TicketException $e) {
            $this->assertSame(409, $e->status);
        }

        $ticket->refresh();
        $this->assertSame('Menunggu', $ticket->status);
        $this->assertNull($ticket->assigned_to);
        $this->assertNull($ticket->taken_at);
    }

    public function test_external_reply_marks_ticket_handled_without_officer(): void
    {
        $ticket = $this->enqueue();

        $this->queue->markRespondedExternally($ticket->conversation);

        $ticket->refresh();
        $this->assertSame('Ditangani', $ticket->status);
        $this->assertNull($ticket->assigned_to);
        $this->assertNotNull($ticket->taken_at);
        $this->assertNotNull($ticket->first_response_at);

        $closed = $this->enqueue();
        $closed->update(['status' => 'Selesai']);
        $this->queue->markRespondedExternally($closed->conversation);
        $this->assertSame('Selesai', $closed->fresh()->status, 'Tiket tertutup tidak dibuka lagi.');
    }

    // ------------------------------------------------------------ selesai / batal

    public function test_resolve_closes_ticket_notifies_applicant_and_returns_to_bot(): void
    {
        $officer = $this->officer();
        $ticket = $this->enqueue();
        $this->queue->assign($ticket->conversation, $officer, responded: true);

        $resolved = $this->queue->resolve($ticket->fresh(), $officer);

        $this->assertSame('Selesai', $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame('Diselesaikan petugas.', $resolved->close_reason);
        $chat = $ticket->conversation->fresh();
        $this->assertSame('BOT_ACTIVE', $chat->conversation_mode);
        $this->assertNull($chat->assigned_to);
        $this->assertSame(0, $chat->escalationLogs()->where('status', '!=', 'Selesai')->count());

        $message = Message::where('sender_type', 'system')->sole();
        $this->assertStringContainsString($ticket->ticket_number, $message->message_text);
        $this->assertSame($officer->id, $message->sender_user_id);
        $this->assertSame(['LYN-SELESAI'], $message->meta['codes']);
        $sent = $this->sentWhatsappTexts();
        $this->assertCount(1, $sent, 'Pemberitahuan dikirim ke WhatsApp.');
        $this->assertStringContainsString($ticket->ticket_number, json_encode($sent));
    }

    public function test_resolve_waiting_ticket_directly_records_officer(): void
    {
        $officer = $this->officer();
        $ticket = $this->enqueue();

        $resolved = $this->queue->resolve($ticket, $officer, notify: false);

        $this->assertSame($officer->id, $resolved->assigned_to);
        $this->assertNotNull($resolved->taken_at);
        $this->assertSame(0, Message::count(), 'Tanpa pemberitahuan.');
        Http::assertNothingSent();
    }

    public function test_resolve_on_simulation_channel_records_but_does_not_send(): void
    {
        $ticket = $this->enqueue(['channel' => 'simulasi']);

        $this->queue->resolve($ticket, $this->officer());

        $this->assertSame(1, Message::where('sender_type', 'system')->count());
        $this->assertNull(Message::sole()->delivery_status);
        Http::assertNothingSent();
    }

    public function test_resolve_rejects_closed_ticket_and_other_officers_ticket(): void
    {
        $budi = $this->officer();
        $ticket = $this->enqueue();
        $this->queue->assign($ticket->conversation, $budi);

        try {
            $this->queue->resolve($ticket->fresh(), $this->officer());
            $this->fail('Petugas lain tidak boleh menyelesaikan.');
        } catch (TicketException $e) {
            $this->assertSame(409, $e->status);
        }

        $this->assertSame('Selesai', $this->queue->resolve($ticket->fresh(), $this->officer('admin'))->status, 'Admin boleh.');

        $this->expectExceptionObject(TicketException::closed());
        $this->queue->resolve($ticket->fresh(), $budi);
    }

    public function test_cancel_only_allowed_while_waiting(): void
    {
        $ticket = $this->enqueue();
        EscalationLog::create(['conversation_id' => $ticket->conversation_id, 'escalation_type' => 'user_request', 'status' => 'Menunggu']);

        $this->queue->cancel($ticket);

        $ticket->refresh();
        $this->assertSame('Batal', $ticket->status);
        $this->assertSame('Dibatalkan pemohon.', $ticket->close_reason);
        $this->assertSame('BOT_ACTIVE', $ticket->conversation->fresh()->conversation_mode);
        $this->assertSame('Selesai', EscalationLog::sole()->status);

        $handled = $this->enqueue();
        $handled->update(['status' => 'Ditangani']);
        $this->expectExceptionObject(TicketException::closed());
        $this->queue->cancel($handled);
    }

    // ------------------------------------------------------------ kedaluwarsa

    public function test_waiting_ticket_expires_after_two_business_days_skipping_weekend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-13 15:00', 'Asia/Jakarta')); // Jumat
        $ticket = $this->enqueue();

        Carbon::setTestNow(Carbon::parse('2026-11-16 15:00', 'Asia/Jakarta')); // Senin: 1 hari kerja
        $this->assertSame(0, $this->queue->expireStale());
        $this->assertSame('Menunggu', $ticket->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-17 08:00', 'Asia/Jakarta')); // Selasa: 2 hari kerja
        $this->assertSame(1, $this->queue->expireStale());

        $ticket->refresh();
        $this->assertSame('Kedaluwarsa', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
        $this->assertStringContainsString('2 hari kerja', $ticket->close_reason);
        $this->assertSame('BOT_ACTIVE', $ticket->conversation->fresh()->conversation_mode);
        $message = Message::where('sender_type', 'system')->sole();
        $this->assertStringContainsString('ditutup otomatis', $message->message_text);
        $this->assertSame(['LYN-KEDALUWARSA'], $message->meta['codes']);

        $this->assertSame(0, $this->queue->expireStale(), 'Tidak diproses dua kali.');
    }

    public function test_expiry_limit_is_configurable_and_at_least_one_day(): void
    {
        config(['kancil.ticket.expire_business_days' => 0]);
        $ticket = $this->enqueue();

        $this->assertSame(0, $this->queue->expireStale(), 'Hari yang sama tidak pernah kedaluwarsa.');

        Carbon::setTestNow(now()->addDay());
        $this->assertSame(1, $this->queue->expireStale());
        $this->assertSame('Kedaluwarsa', $ticket->fresh()->status);
    }

    public function test_expiry_in_option_a_is_silent_and_keeps_handled_tickets(): void
    {
        $this->configureIntegration('balesotomatis');
        $waiting = $this->enqueue();
        $handled = $this->enqueue();
        $handled->update(['status' => 'Ditangani']);

        Carbon::setTestNow(now()->addDays(7));
        $this->assertSame(1, $this->queue->expireStale());

        $this->assertSame('Kedaluwarsa', $waiting->fresh()->status);
        $this->assertSame('Ditangani', $handled->fresh()->status);
        $this->assertSame(0, Message::count(), 'Opsi A: tidak mengirim pesan.');
    }

    public function test_expiry_leaves_conversation_mode_alone_when_already_back_on_bot(): void
    {
        $ticket = $this->enqueue(['conversation_mode' => 'BOT_ACTIVE']);

        Carbon::setTestNow(now()->addDays(7));
        $this->queue->expireStale();

        $this->assertSame('BOT_ACTIVE', $ticket->conversation->fresh()->conversation_mode);
        $this->assertSame('Kedaluwarsa', $ticket->fresh()->status);
    }

    // ------------------------------------------------------------ kembali ke bot

    public function test_return_to_bot_on_expiry_closes_open_tickets(): void
    {
        $handled = $this->enqueue();
        $handled->update(['status' => 'Ditangani']);
        $waiting = $this->enqueue();

        $handled->conversation->returnToBot();
        $waiting->conversation->returnToBot();

        $this->assertSame('Selesai', $handled->fresh()->status);
        $this->assertStringContainsString('masa penanganan', $handled->fresh()->close_reason);
        $this->assertSame('Kedaluwarsa', $waiting->fresh()->status);
    }

    public function test_return_to_bot_by_officer_resolves_open_tickets(): void
    {
        $waiting = $this->enqueue();

        $waiting->conversation->returnToBot(closeAll: true);

        $this->assertSame('Selesai', $waiting->fresh()->status);
        $this->assertSame('Dikembalikan ke bot oleh petugas.', $waiting->fresh()->close_reason);
        $this->assertNull($waiting->conversation->fresh()->openTicket);
    }

    public function test_open_ticket_relation_ignores_closed_tickets(): void
    {
        $chat = $this->applicant();
        [$old] = $this->queue->open($chat);
        $old->update(['status' => 'Selesai']);
        [$current] = $this->queue->open($chat);

        $this->assertSame($current->id, $chat->fresh()->openTicket->id);
        $this->assertSame(2, $chat->serviceTickets()->count());
        $this->assertTrue($current->isOpen());
        $this->assertFalse($old->fresh()->isOpen());
        $this->assertSame($current->id, ServiceTicket::open()->sole()->id);
    }
}
