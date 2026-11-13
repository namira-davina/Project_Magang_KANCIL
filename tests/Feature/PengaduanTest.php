<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BotHelpers;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

/**
 * Halaman Pengaduan: data nyata, status + tanggapan (dikirim ke WhatsApp), akses per role.
 */
class PengaduanTest extends TestCase
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

    private function ticket(string $number, array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'ticket_number' => $number, 'user_name' => 'Budi', 'whatsapp_number' => '6281234567890',
            'complaint_text' => 'Antrean lama', 'status' => 'Menunggu', 'priority' => 'Sedang', 'submitted_at' => now(),
        ], $overrides));
    }

    public function test_index_shows_real_stats_and_items(): void
    {
        $this->actingAsAdmin();
        $cat = ComplaintCategory::create(['name' => 'Layanan paspor']);
        $this->ticket('ADU-202611-0001', ['category_id' => $cat->id, 'attachments' => [['path' => 'a']]]);
        $this->ticket('ADU-202611-0002', ['status' => 'Diproses', 'submitted_at' => now()->subDay()]);
        $this->ticket('ADU-202611-0003', ['status' => 'Selesai', 'submitted_at' => now()->subDays(10)]);

        $response = $this->get(route('pengaduan'))->assertOk();

        $this->assertSame(['total' => 3, 'menunggu' => 1, 'diproses' => 1, 'selesai' => 1], $response->viewData('stats'));
        $items = $response->viewData('complaints');
        $this->assertSame('#ADU-202611-0001', $items[0]['tiket']);
        $this->assertSame('Layanan paspor', $items[0]['kategori']);
        $this->assertSame(1, $items[0]['lampiran']);
        $this->assertStringStartsWith('Hari ini', $items[0]['tanggal']);
        $this->assertStringStartsWith('Kemarin', $items[1]['tanggal']);
        $this->assertSame('Tanpa Kategori', $items[1]['kategori']);
        $this->assertContains('Layanan paspor', $response->viewData('categories'));
        $response->assertDontSee('Hendro Wibowo')->assertSee('Manajemen Pengaduan');
    }

    public function test_update_status_and_response_notifies_reporter(): void
    {
        $admin = $this->actingAsAdmin();
        $chat = $this->startChat();
        $complaint = $this->ticket('ADU-202611-0001', ['conversation_id' => $chat->id]);

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Diproses', 'tanggapan' => 'Sedang kami tindak lanjuti.'])
            ->assertOk()
            ->assertJsonPath('complaint.status', 'Diproses')
            ->assertJsonPath('complaint.tanggapan', 'Sedang kami tindak lanjuti.')
            ->assertJsonPath('notified', true);

        $complaint->refresh();
        $this->assertNotNull($complaint->first_response_at);
        $this->assertNull($complaint->resolved_at);
        $this->assertSame($admin->id, $complaint->assigned_to);

        $wa = Message::sole();
        $this->assertSame('petugas', $wa->sender_type);
        $this->assertStringContainsString('*ADU-202611-0001*', $wa->message_text);
        $this->assertStringContainsString('Sedang kami tindak lanjuti.', $this->sentWhatsappTexts()[0]['message']);

        // Status berubah tanpa tanggapan: pelapor tetap diberi tahu statusnya.
        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Selesai'])->assertOk()->assertJsonPath('notified', true);
        $this->assertSame('Status pengaduan *ADU-202611-0001* kini: *Selesai*.', Message::latest('id')->value('message_text'));
        // Simpan ulang tanpa perubahan: tidak ada pesan.
        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Selesai'])->assertOk()->assertJsonPath('notified', false);
        $this->assertSame(2, Message::count());
        $complaint->refresh();
        $this->assertNotNull($complaint->resolved_at);
        $this->assertSame('Sedang kami tindak lanjuti.', $complaint->response_text, 'Tanggapan kosong tidak menghapus tanggapan lama.');

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Diproses'])->assertOk();
        $this->assertNull($complaint->fresh()->resolved_at, 'Dibuka lagi: waktu selesai dihapus.');
    }

    public function test_status_waiting_without_response_does_not_set_first_response(): void
    {
        $this->actingAsAdmin();
        $complaint = $this->ticket('ADU-202611-0001');

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Menunggu', 'tanggapan' => ''])->assertOk();

        $this->assertNull($complaint->fresh()->first_response_at);
    }

    public function test_response_for_ticket_without_whatsapp_conversation_is_not_sent(): void
    {
        $this->actingAsAdmin();
        $complaint = $this->ticket('ADU-202611-0001');

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Diproses', 'tanggapan' => 'Ok'])->assertJsonPath('notified', false);
        $this->assertSame(0, Message::count());
    }

    public function test_update_validation(): void
    {
        $this->actingAsAdmin();
        $complaint = $this->ticket('ADU-202611-0001');

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Baru'])->assertJsonValidationErrors(['status' => 'Status tidak valid.']);
        $this->putJson(route('pengaduan.update', $complaint), [])->assertJsonValidationErrors(['status' => 'Status wajib dipilih.']);
        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Diproses', 'tanggapan' => str_repeat('a', 2001)])->assertJsonValidationErrors('tanggapan');
        $this->putJson('/pengaduan/999', ['status' => 'Diproses'])->assertNotFound();
    }

    public function test_petugas_cannot_see_complaints_about_officers_or_update(): void
    {
        $this->actingAsAdmin(['role' => 'petugas']);
        $officer = ComplaintCategory::create(['name' => 'Pelayanan petugas']);
        $paspor = ComplaintCategory::create(['name' => 'Layanan paspor']);
        $hidden = $this->ticket('ADU-202611-0001', ['category_id' => $officer->id]);
        $this->ticket('ADU-202611-0002', ['category_id' => $paspor->id]);
        $this->ticket('ADU-202611-0003');

        $response = $this->get(route('pengaduan'))->assertOk();
        $this->assertSame(['#ADU-202611-0003', '#ADU-202611-0002'], collect($response->viewData('complaints'))->pluck('tiket')->sort()->reverse()->values()->all());
        $this->assertFalse($response->viewData('canUpdate'));

        $this->putJson(route('pengaduan.update', $hidden), ['status' => 'Selesai'])->assertForbidden();
    }

    public function test_pimpinan_sees_all_and_can_update(): void
    {
        $this->actingAsAdmin(['role' => 'pimpinan']);
        $officer = ComplaintCategory::create(['name' => 'Pelayanan petugas']);
        $complaint = $this->ticket('ADU-202611-0001', ['category_id' => $officer->id, 'escalation_level' => 2, 'priority' => 'Tinggi']);

        $items = $this->get(route('pengaduan'))->viewData('complaints');
        $this->assertSame(2, $items[0]['level']);
        $this->assertSame('Tinggi', $items[0]['prioritas']);

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Selesai'])->assertOk();
    }

    public function test_overdue_flag_is_exposed(): void
    {
        $this->actingAsAdmin();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Jakarta'));
        $this->ticket('ADU-202611-0001', ['priority' => 'Tinggi']);
        Carbon::setTestNow(Carbon::parse('2026-11-09 09:00', 'Asia/Jakarta'));

        $this->assertTrue($this->get(route('pengaduan'))->viewData('complaints')[0]['terlambat']);
    }

    public function test_initial_response_overdue_flag(): void
    {
        $this->actingAsAdmin();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Jakarta')); // Senin
        $complaint = $this->ticket('ADU-202611-0001', ['priority' => 'Sedang']);
        $low = $this->ticket('ADU-202611-0002', ['priority' => 'Rendah']);

        Carbon::setTestNow(Carbon::parse('2026-11-03 09:00', 'Asia/Jakarta')); // 1 hari kerja: masih dalam batas
        $items = $this->get(route('pengaduan'))->viewData('complaints')->keyBy('id');
        $this->assertFalse($items[$complaint->id]['terlambat_respon']);

        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00', 'Asia/Jakarta')); // 2 hari kerja
        $items = $this->get(route('pengaduan'))->viewData('complaints')->keyBy('id');
        $this->assertTrue($items[$complaint->id]['terlambat_respon']);
        $this->assertFalse($items[$complaint->id]['terlambat'], 'Batas penyelesaian (5 hari) belum lewat.');
        $this->assertFalse($items[$low->id]['terlambat_respon'], 'Rendah: batas respon awal 2 hari kerja.');

        $this->putJson(route('pengaduan.update', $complaint), ['status' => 'Diproses'])->assertOk();
        $this->assertFalse($this->get(route('pengaduan'))->viewData('complaints')->keyBy('id')[$complaint->id]['terlambat_respon'], 'Sudah direspons.');
    }
}
