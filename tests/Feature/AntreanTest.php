<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\Tickets\TicketQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Halaman Antrean Petugas dan integrasinya dengan Percakapan, Dashboard, Laporan, dan role.
 */
class AntreanTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->atOfficeHours();
        $this->configureIntegration('laravel');
        $this->fakeApis();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(string $role = 'petugas', array $attributes = []): User
    {
        return User::factory()->role($role)->create($attributes);
    }

    private function ticket(array $attributes = [], array $conversation = []): ServiceTicket
    {
        $n = ++$this->seq;
        $chat = Conversation::create(array_merge([
            'wa_chat_id' => "6281200000{$n}@s.whatsapp.net",
            'whatsapp_number' => "6281200000{$n}",
            'user_name' => "Pemohon {$n}",
            'channel' => 'whatsapp',
            'conversation_mode' => Conversation::MODE_WAITING,
            'started_at' => now(),
            'last_message_at' => now(),
        ], $conversation));

        [$ticket] = app(TicketQueueService::class)->open($chat, null, "Pertanyaan {$n}");

        if ($attributes) {
            $ticket->update($attributes);
        }

        return $ticket->fresh();
    }

    // ------------------------------------------------------------ akses

    public function test_guest_is_redirected_to_login(): void
    {
        $ticket = $this->ticket();

        $this->get(route('antrean'))->assertRedirect(route('login'));
        $this->get(route('antrean.data'))->assertRedirect(route('login'));
        $this->post(route('antrean.ambil-berikutnya'))->assertRedirect(route('login'));
        $this->post(route('antrean.ambil', $ticket))->assertRedirect(route('login'));
        $this->post(route('antrean.selesai', $ticket))->assertRedirect(route('login'));
    }

    /** @return array<string, array{string, bool}> */
    public static function roles(): array
    {
        return ['admin' => ['admin', true], 'petugas' => ['petugas', true], 'pimpinan' => ['pimpinan', false]];
    }

    /** @dataProvider roles */
    public function test_every_role_sees_queue_but_only_admin_and_officer_can_act(string $role, bool $canAct): void
    {
        $this->actingAs($this->user($role));
        $ticket = $this->ticket();

        $response = $this->get(route('antrean'))->assertOk()
            ->assertSee('Antrean Tiket Petugas')
            ->assertSee('Antrean Petugas'); // menu sidebar
        $this->assertSame($canAct, $response->viewData('canAct'));
        $canAct ? $response->assertSee('Ambil Antrean Berikutnya') : $response->assertDontSee('Ambil Antrean Berikutnya');

        $this->getJson(route('antrean.data'))->assertOk();

        $status = $canAct ? 200 : 403;
        $this->postJson(route('antrean.ambil', $ticket))->assertStatus($status);
        $this->postJson(route('antrean.selesai', $ticket->fresh()))->assertStatus($canAct ? 200 : 403);
        $this->postJson(route('antrean.ambil-berikutnya'))->assertStatus($canAct ? 404 : 403);
    }

    public function test_unknown_ticket_is_404(): void
    {
        $this->actingAs($this->user());

        $this->postJson('/antrean/999/ambil')->assertNotFound();
        $this->postJson('/antrean/999/selesai')->assertNotFound();
    }

    // ------------------------------------------------------------ data

    public function test_data_lists_waiting_fifo_then_handled_with_positions_history_and_stats(): void
    {
        $officer = $this->user('petugas', ['name' => 'Rina']);
        $this->actingAs($officer);

        $handled = $this->ticket(['status' => 'Ditangani', 'assigned_to' => $officer->id, 'taken_at' => now()]);
        Carbon::setTestNow(now()->addMinutes(10));
        $first = $this->ticket(['menu_path' => '0>1>5', 'escalation_code' => 'C1', 'priority' => 'Tinggi']);
        Carbon::setTestNow(now()->addMinutes(10));
        $second = $this->ticket();
        $doneToday = $this->ticket(['status' => 'Selesai', 'resolved_at' => now(), 'close_reason' => 'Diselesaikan petugas.']);
        $doneYesterday = $this->ticket(['status' => 'Batal', 'resolved_at' => now()->subDay()]);
        Carbon::setTestNow(now()->addMinutes(5));

        $json = $this->getJson(route('antrean.data'))->assertOk()->json();

        $this->assertSame([$first->id, $second->id, $handled->id], array_column($json['tickets'], 'id'));
        $this->assertSame([1, 2, null], array_column($json['tickets'], 'posisi'));
        $row = $json['tickets'][0];
        $this->assertSame($first->ticket_number, $row['nomor']);
        $this->assertSame('Persyaratan Paspor › Paspor Hilang', $row['topik']);
        $this->assertSame('C1', $row['kode']);
        $this->assertSame('Tinggi', $row['prioritas']);
        $this->assertSame('Pertanyaan 2', $row['pertanyaan']);
        $this->assertSame(15, $row['menunggu_menit']);
        $this->assertSame('Hari ini', $row['masuk_tanggal']);
        $this->assertSame(route('percakapan', ['id' => $first->conversation_id]), $row['percakapan_url']);
        $this->assertFalse($row['milik_saya']);
        $this->assertTrue($json['tickets'][2]['milik_saya']);
        $this->assertSame('Rina', $json['tickets'][2]['petugas']);

        $this->assertSame([$doneToday->id], array_column($json['history'], 'id'), 'Riwayat hanya hari ini.');
        $this->assertSame('Diselesaikan petugas.', $json['history'][0]['alasan']);

        $this->assertSame(2, $json['stats']['menunggu']);
        $this->assertSame(1, $json['stats']['ditangani']);
        $this->assertSame(1, $json['stats']['selesai_hari_ini']);
        $this->assertSame(15, $json['stats']['tertua_menit']);
        $this->assertSame(0, $json['stats']['rata_tunggu_menit']);
        $this->assertNotContains($doneYesterday->id, array_column($json['tickets'], 'id'));
    }

    public function test_empty_queue_payload(): void
    {
        $this->actingAs($this->user());

        $json = $this->getJson(route('antrean.data'))->json();

        $this->assertSame([], $json['tickets']);
        $this->assertSame([], $json['history']);
        $this->assertSame(['menunggu' => 0, 'ditangani' => 0, 'selesai_hari_ini' => 0, 'rata_tunggu_menit' => null, 'tertua_menit' => null], $json['stats']);
        $this->get(route('antrean'))->assertSee('Antrean kosong');
    }

    public function test_applicant_without_name_or_number_has_fallback_labels(): void
    {
        $this->actingAs($this->user());
        $this->ticket([], ['user_name' => null, 'whatsapp_number' => null, 'wa_chat_id' => '99887766@lid']);
        $this->ticket([], ['user_name' => null]);

        $rows = $this->getJson(route('antrean.data'))->json('tickets');

        $this->assertSame('Kontak WhatsApp', $rows[0]['nama']);
        $this->assertSame('99887766@lid', $rows[0]['telepon']);
        $this->assertStringStartsWith('+62 812-', $rows[1]['nama']);
    }

    // ------------------------------------------------------------ ambil

    public function test_claim_next_gives_oldest_ticket_and_chat_link(): void
    {
        $officer = $this->user();
        $this->actingAs($officer);
        $oldest = $this->ticket();
        Carbon::setTestNow(now()->addMinute());
        $this->ticket();

        $response = $this->postJson(route('antrean.ambil-berikutnya'))->assertOk();

        $response->assertJsonPath('ticket.id', $oldest->id)
            ->assertJsonPath('ticket.status', 'Ditangani')
            ->assertJsonPath('percakapan_url', route('percakapan', ['id' => $oldest->conversation_id]));
        $this->assertSame($officer->id, $oldest->fresh()->assigned_to);
        $this->assertSame('HUMAN_HANDLING', $oldest->conversation->fresh()->conversation_mode);
    }

    public function test_claim_next_on_empty_queue(): void
    {
        $this->actingAs($this->user());
        $this->ticket(['status' => 'Ditangani']);

        $this->postJson(route('antrean.ambil-berikutnya'))->assertNotFound()->assertJson(['message' => 'Antrean kosong.']);
    }

    public function test_two_officers_cannot_take_the_same_ticket(): void
    {
        $ticket = $this->ticket();
        $budi = $this->user('petugas', ['name' => 'Budi']);

        $this->actingAs($budi)->postJson(route('antrean.ambil', $ticket))->assertOk();
        $this->actingAs($this->user())->postJson(route('antrean.ambil', $ticket))
            ->assertStatus(409)
            ->assertJson(['message' => 'Percakapan ini sedang ditangani petugas lain (Budi).']);

        $this->actingAs($budi)->postJson(route('antrean.ambil', $ticket))->assertOk(); // ambil ulang miliknya sendiri
        $this->actingAs($this->user('admin'))->postJson(route('antrean.ambil', $ticket))->assertOk();
        $this->assertNotSame($budi->id, $ticket->fresh()->assigned_to);
    }

    public function test_closed_ticket_cannot_be_taken_or_resolved(): void
    {
        $this->actingAs($this->user());
        $ticket = $this->ticket(['status' => 'Kedaluwarsa']);

        $this->postJson(route('antrean.ambil', $ticket))->assertStatus(422)->assertJson(['message' => 'Tiket sudah ditutup.']);
        $this->postJson(route('antrean.selesai', $ticket))->assertStatus(422);
    }

    public function test_ticket_error_on_regular_form_post_redirects_back_with_message(): void
    {
        $this->actingAs($this->user());
        $ticket = $this->ticket(['status' => 'Selesai']);

        $this->from(route('antrean'))->post(route('antrean.ambil', $ticket))
            ->assertRedirect(route('antrean'))
            ->assertSessionHas('error', 'Tiket sudah ditutup.');
    }

    // ------------------------------------------------------------ selesai

    public function test_resolve_notifies_applicant_and_bot_resumes(): void
    {
        $officer = $this->user();
        $this->actingAs($officer);
        $ticket = $this->ticket();
        $this->postJson(route('antrean.ambil', $ticket))->assertOk();

        $this->postJson(route('antrean.selesai', $ticket))->assertOk()
            ->assertJsonPath('ticket.status', 'Selesai')
            ->assertJsonPath('ticket.posisi', null);

        $this->assertSame('BOT_ACTIVE', $ticket->conversation->fresh()->conversation_mode);
        $this->assertStringContainsString($ticket->ticket_number, Message::where('sender_type', 'system')->sole()->message_text);
        $this->assertCount(1, $this->sentWhatsappTexts());

        $history = $this->getJson(route('antrean.data'))->json('history');
        $this->assertSame($ticket->id, $history[0]['id']);
    }

    public function test_resolve_without_message(): void
    {
        $this->actingAs($this->user());
        $ticket = $this->ticket();

        $this->postJson(route('antrean.selesai', $ticket), ['kirim_pesan' => false])->assertOk();

        $this->assertSame(0, Message::count());
    }

    public function test_resolve_validates_flag(): void
    {
        $this->actingAs($this->user());

        $this->postJson(route('antrean.selesai', $this->ticket()), ['kirim_pesan' => 'mungkin'])
            ->assertStatus(422)->assertJsonValidationErrors('kirim_pesan');
    }

    public function test_officer_cannot_resolve_ticket_of_another_officer(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->user())->postJson(route('antrean.ambil', $ticket))->assertOk();

        $this->actingAs($this->user())->postJson(route('antrean.selesai', $ticket))->assertStatus(409);
        $this->assertSame('Ditangani', $ticket->fresh()->status);

        $this->actingAs($this->user('admin'))->postJson(route('antrean.selesai', $ticket))->assertOk();
    }

    // ------------------------------------------------------------ percakapan

    public function test_reply_from_inbox_takes_ticket_and_records_first_response(): void
    {
        $officer = $this->user();
        $ticket = $this->ticket();

        $this->actingAs($officer)->postJson(route('percakapan.balas', $ticket->conversation), ['pesan' => 'Selamat siang, saya bantu.'])
            ->assertCreated()
            ->assertJsonPath('conversation.ticket.status', 'Ditangani')
            ->assertJsonPath('conversation.assigned_name', $officer->name);

        $ticket->refresh();
        $this->assertSame('Ditangani', $ticket->status);
        $this->assertSame($officer->id, $ticket->assigned_to);
        $this->assertNotNull($ticket->first_response_at);
    }

    public function test_other_officer_cannot_reply_take_over_or_release_held_conversation(): void
    {
        $ticket = $this->ticket();
        $chat = $ticket->conversation;
        $this->actingAs($this->user('petugas', ['name' => 'Budi']))->postJson(route('percakapan.ambil-alih', $chat))->assertOk();
        $messages = Message::count();

        $this->actingAs($this->user());
        $this->postJson(route('percakapan.balas', $chat), ['pesan' => 'Halo'])->assertStatus(409)->assertJsonPath('message', 'Percakapan ini sedang ditangani petugas lain (Budi).');
        $this->postJson(route('percakapan.ambil-alih', $chat))->assertStatus(409);
        $this->postJson(route('percakapan.kembalikan', $chat))->assertStatus(409);

        $this->assertSame($messages, Message::count(), 'Balasan yang ditolak tidak tersimpan.');
        $this->assertSame('Ditangani', $ticket->fresh()->status);

        $this->actingAs($this->user('admin'))->postJson(route('percakapan.balas', $chat), ['pesan' => 'Saya lanjutkan.'])->assertCreated();
    }

    public function test_takeover_takes_ticket_without_counting_as_response(): void
    {
        $ticket = $this->ticket();

        $this->actingAs($this->user())->postJson(route('percakapan.ambil-alih', $ticket->conversation))->assertOk()
            ->assertJsonPath('conversation.mode', 'HUMAN_HANDLING');

        $this->assertSame('Ditangani', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->first_response_at);
    }

    public function test_release_with_ticket_resolves_and_notifies(): void
    {
        $ticket = $this->ticket();
        $this->actingAs($this->user());
        $this->postJson(route('percakapan.ambil-alih', $ticket->conversation))->assertOk();

        $this->postJson(route('percakapan.kembalikan', $ticket->conversation))->assertOk()
            ->assertJsonPath('conversation.mode', 'BOT_ACTIVE')
            ->assertJsonPath('conversation.ticket', null);

        $this->assertSame('Selesai', $ticket->fresh()->status);
        $this->assertSame(1, Message::where('sender_type', 'system')->count());
    }

    public function test_release_without_ticket_sends_nothing(): void
    {
        $chat = $this->startChat(['conversation_mode' => 'HUMAN_HANDLING']);

        $this->actingAs($this->user())->postJson(route('percakapan.kembalikan', $chat))->assertOk();

        $this->assertSame(0, Message::count());
        $this->assertSame('BOT_ACTIVE', $chat->fresh()->conversation_mode);
    }

    public function test_inbox_threads_show_ticket_and_queue_count(): void
    {
        $this->actingAs($this->user());
        $first = $this->ticket();
        Carbon::setTestNow(now()->addMinute());
        $second = $this->ticket();
        $plain = $this->startChat(['wa_chat_id' => 'x@s.whatsapp.net', 'whatsapp_number' => '6280', 'last_message_at' => now()]);

        $json = $this->getJson(route('percakapan.data'))->json();
        $threads = collect($json['threads'])->keyBy('id');

        $this->assertSame(2, $json['counts']['queue']);
        $this->assertSame($first->ticket_number, $threads[$first->conversation_id]['ticket']['number']);
        $this->assertSame(1, $threads[$first->conversation_id]['ticket']['position']);
        $this->assertSame(2, $threads[$second->conversation_id]['ticket']['position']);
        $this->assertSame($first->queued_at->getTimestamp(), $threads[$first->conversation_id]['ticket']['queued_ts']);
        $this->assertNull($threads[$plain->id]['ticket']);

        $one = $this->getJson(route('percakapan.pesan', $second->conversation))->json('conversation.ticket');
        $this->assertSame(2, $one['position'], 'Detail percakapan menghitung posisi sendiri.');
    }

    public function test_inbox_list_query_count_does_not_grow_with_tickets(): void
    {
        $this->actingAs($this->user());
        $this->ticket();
        DB::enableQueryLog();
        $this->getJson(route('percakapan.data'))->assertOk();
        $few = count(DB::getQueryLog());

        foreach (range(1, 10) as $i) {
            $this->ticket();
        }
        DB::flushQueryLog();
        $this->getJson(route('percakapan.data'))->assertOk();

        $this->assertSame($few, count(DB::getQueryLog()));
    }

    // ------------------------------------------------------------ sidebar, dashboard, laporan

    public function test_sidebar_badge_shows_waiting_count(): void
    {
        $this->actingAs($this->user('admin'));

        $this->get(route('admin.dashboard'))->assertSee('data-queue-badge style="display: none;"', false);

        $this->ticket();
        $this->ticket();
        $this->ticket(['status' => 'Ditangani']);

        $this->get(route('admin.dashboard'))->assertSeeInOrder(['Antrean Petugas', 'data-queue-badge', '>2</span>'], false);
    }

    public function test_dashboard_shows_today_queue_numbers(): void
    {
        $this->actingAs($this->user('admin'));
        $this->ticket();
        $this->ticket(['status' => 'Selesai', 'resolved_at' => now(), 'taken_at' => now()->addMinutes(12)]);

        $response = $this->get(route('admin.dashboard'))->assertOk()->assertSee('Antrean Menunggu');

        $this->assertSame(1, $response->viewData('antrean')['menunggu']);
        $this->assertSame(1, $response->viewData('antrean')['selesai_hari_ini']);
        $this->assertSame(12, $response->viewData('antrean')['rata_tunggu_menit']);
    }

    public function test_monthly_report_summarises_queue(): void
    {
        $this->actingAs($this->user('pimpinan'));
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00', 'Asia/Jakarta')); // Rabu
        $onTime = $this->ticket();
        $onTime->update(['status' => 'Selesai', 'taken_at' => now()->addMinutes(10), 'first_response_at' => now()->addMinutes(10), 'resolved_at' => now()->addMinutes(40)]);
        $late = $this->ticket();
        $late->update(['status' => 'Selesai', 'taken_at' => now()->addDays(2)->addMinutes(30), 'resolved_at' => now()->addDays(2)->addMinutes(50)]);
        $this->ticket(['status' => 'Kedaluwarsa', 'resolved_at' => now()->addDays(2)]);
        $this->ticket(['status' => 'Batal', 'resolved_at' => now()]);
        $this->ticket();
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00', 'Asia/Jakarta'));
        $this->ticket(); // bulan lalu
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Asia/Jakarta'));

        $response = $this->get(route('laporan', ['bulan' => 11, 'tahun' => 2026]))->assertOk()
            ->assertSee('Rekap Antrean Tiket Petugas')
            ->assertSee('tableAntreanLaporan', false);
        $antrean = $response->viewData('antrean');

        $this->assertSame(5, $antrean['total']);
        $this->assertSame(2, $antrean['selesai']);
        $this->assertSame(1, $antrean['kedaluwarsa']);
        $this->assertSame(1, $antrean['batal']);
        $this->assertSame(1, $antrean['terbuka']);
        $this->assertSame(2, $antrean['direspons']);
        $this->assertSame(1, $antrean['tepat_waktu']);
        $this->assertSame(50, $antrean['persen_tepat_waktu']);
        $this->assertSame(1460, $antrean['rata_tunggu_menit']); // (10 + 2910) / 2
        $this->assertSame(25, $antrean['rata_tangani_menit']);   // (30 + 20) / 2
    }

    public function test_monthly_report_without_tickets(): void
    {
        $this->actingAs($this->user('admin'));

        $antrean = $this->get(route('laporan'))->assertOk()->viewData('antrean');

        $this->assertSame(0, $antrean['total']);
        $this->assertNull($antrean['persen_tepat_waktu']);
        $this->assertNull($antrean['rata_tunggu_menit']);
        $this->assertNull($antrean['rata_tangani_menit']);
    }

    public function test_maintenance_expires_stale_tickets(): void
    {
        $ticket = $this->ticket();
        Carbon::setTestNow(now()->addDays(7));

        $this->artisan('kancil:pemeliharaan')->expectsOutputToContain('antrean kedaluwarsa: 1;')->assertSuccessful();

        $this->assertSame('Kedaluwarsa', $ticket->fresh()->status);
    }
}
