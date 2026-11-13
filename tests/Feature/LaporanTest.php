<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\EscalationLog;
use App\Models\Faq;
use App\Models\FaqCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BotHelpers;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

/**
 * Laporan bulanan dari data nyata (Matriks Eskalasi 7.2).
 */
class LaporanTest extends TestCase
{
    use BotHelpers, CreatesData, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_defaults_to_current_month_and_is_empty(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('laporan'))->assertOk();

        $this->assertSame(11, $response->viewData('month'));
        $this->assertSame(2026, $response->viewData('year'));
        $this->assertSame('November 2026', $response->viewData('periodLabel'));
        $this->assertSame(0, $response->viewData('totalPertanyaan'));
        $this->assertSame(array_fill(0, 12, 0), $response->viewData('chart')['values']);
        $this->assertCount(12, $response->viewData('eskalasiPerKode'));
        $response->assertSee('Belum ada pertanyaan pada periode ini.')->assertDontSee('Prosedur perpanjangan ITAS TKA');
    }

    public function test_monthly_figures(): void
    {
        $this->actingAsAdmin();
        $chat = $this->startChat();
        $category = FaqCategory::create(['name' => 'Paspor']);
        $faq = Faq::create(['code' => 'PSP-001', 'question' => 'Syarat paspor baru?', 'answer' => 'a', 'category_id' => $category->id]);
        $faq2 = Faq::create(['code' => 'TRF-001', 'question' => 'Tarif?', 'answer' => 'a']);

        $msg = fn (string $sender, string $at, ?int $faqId = null) => $chat->messages()->create([
            'sender_type' => $sender, 'message_text' => 'x', 'sent_at' => Carbon::parse($at), 'faq_id' => $faqId,
        ]);
        $msg('user', '2026-11-05', $faq->id);
        $msg('user', '2026-11-06', $faq->id);
        $msg('user', '2026-11-07', $faq2->id);
        $msg('user', '2026-10-30', $faq2->id);           // bulan lain
        $msg('bot', '2026-11-05', $faq->id);             // jawaban FAQ
        $msg('bot', '2026-11-05');                       // menu
        $msg('ai', '2026-11-07', $faq2->id);             // jawaban AI

        foreach (['A1', 'A3', 'A3', 'C1', 'B0', 'B1'] as $code) {
            EscalationLog::create(['conversation_id' => $chat->id, 'escalation_type' => 'user_request', 'escalation_code' => $code, 'escalated_at' => Carbon::parse('2026-11-10')]);
        }
        EscalationLog::create(['conversation_id' => $chat->id, 'escalation_type' => 'user_request', 'escalation_code' => 'A2', 'escalated_at' => Carbon::parse('2026-10-10')]);

        Complaint::create(['ticket_number' => 'ADU-202611-0001', 'user_name' => 'a', 'whatsapp_number' => '1', 'complaint_text' => 'x',
            'status' => 'Selesai', 'priority' => 'Tinggi', 'submitted_at' => Carbon::parse('2026-11-02 09:00'), 'resolved_at' => Carbon::parse('2026-11-03 09:00')]);
        Complaint::create(['ticket_number' => 'ADU-202611-0002', 'user_name' => 'a', 'whatsapp_number' => '1', 'complaint_text' => 'x',
            'status' => 'Selesai', 'priority' => 'Tinggi', 'submitted_at' => Carbon::parse('2026-11-02 09:00'), 'resolved_at' => Carbon::parse('2026-11-12 09:00'), 'escalation_level' => 2]);

        $r = $this->get(route('laporan', ['bulan' => 11, 'tahun' => 2026]))->assertOk();

        $this->assertSame(3, $r->viewData('totalPertanyaan'));
        $this->assertSame(2, $r->viewData('terjawabOtomatis'));
        $this->assertSame(5, $r->viewData('totalEskalasi'), 'B0 tidak dihitung sebagai eskalasi.');
        $this->assertSame(2, $r->viewData('totalPengaduan'));
        $this->assertEquals(['selesai' => 2, 'tepatWaktu' => 1, 'level2' => 1], $r->viewData('tiket'));

        $values = $r->viewData('chart')['values'];
        $this->assertSame(1, $values[9]);
        $this->assertSame(3, $values[10]);

        $top = $r->viewData('topFaq');
        $this->assertSame(['PSP-001', 'TRF-001'], $top->pluck('code')->all());
        $this->assertSame([2, 1], $top->pluck('jumlah')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('Paspor', $top[0]->category);

        $byCode = $r->viewData('eskalasiPerKode')->keyBy('kode');
        $this->assertSame(2, $byCode['A3']->jumlah);
        $this->assertSame(1, $byCode['B0']->jumlah);
        $this->assertSame(0, $byCode['A2']->jumlah);
        $this->assertSame('Tinggi', $byCode['C1']->prioritas);

        $r->assertSee('Syarat paspor baru?')->assertSee('2 kali')->assertSee('tableEskalasiLaporan', false);

        $october = $this->get(route('laporan', ['bulan' => 10, 'tahun' => 2026]));
        $this->assertSame(1, $october->viewData('totalPertanyaan'));
        $this->assertSame(1, $october->viewData('totalEskalasi'));
    }

    public function test_invalid_filter_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->get(route('laporan', ['bulan' => 13]))->assertSessionHasErrors('bulan');
        $this->get(route('laporan', ['tahun' => 1999]))->assertSessionHasErrors('tahun');
    }

    public function test_access_by_role(): void
    {
        $this->actingAsAdmin(['role' => 'pimpinan']);
        $this->get(route('laporan'))->assertOk();

        $this->actingAs($this->makeUser(['role' => 'petugas', 'username' => 'p', 'email' => 'p@x.id']));
        $this->get(route('laporan'))->assertForbidden();
    }
}
