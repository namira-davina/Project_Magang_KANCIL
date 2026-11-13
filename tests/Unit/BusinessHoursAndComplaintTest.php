<?php

namespace Tests\Unit;

use App\Models\BotSetting;
use App\Models\Complaint;
use App\Services\Bot\BusinessHours;
use App\Services\ComplaintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

class BusinessHoursAndComplaintTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setting(string $key, string $value): void
    {
        BotSetting::updateOrCreate(['setting_key' => $key], ['setting_value' => $value]);
    }

    public static function moments(): array
    {
        return [
            'Rabu 10:00' => ['2026-11-11 10:00', true],
            'Rabu 07:59' => ['2026-11-11 07:59', false],
            'Rabu 08:00' => ['2026-11-11 08:00', true],
            'Rabu 15:59' => ['2026-11-11 15:59', true],
            'Rabu 16:00' => ['2026-11-11 16:00', false],
            'Sabtu 10:00' => ['2026-11-14 10:00', false],
            'Minggu 10:00' => ['2026-11-15 10:00', false],
        ];
    }

    #[DataProvider('moments')]
    public function test_office_hours_in_wib(string $at, bool $open): void
    {
        $this->assertSame($open, (new BusinessHours)->isOpen(Carbon::parse($at, 'Asia/Jakarta')));
    }

    public function test_utc_time_is_converted_to_wib(): void
    {
        // 02:00 UTC = 09:00 WIB
        $this->assertTrue((new BusinessHours)->isOpen(Carbon::parse('2026-11-11 02:00', 'UTC')));
        // 10:00 UTC = 17:00 WIB
        $this->assertFalse((new BusinessHours)->isOpen(Carbon::parse('2026-11-11 10:00', 'UTC')));
    }

    public function test_custom_days_and_hours_from_settings(): void
    {
        $this->setting('working_days', 'Senin - Sabtu');
        $this->setting('working_hours_start', '07:30');
        $this->setting('working_hours_end', '12:00');

        $hours = new BusinessHours;
        $this->assertSame([1, 2, 3, 4, 5, 6], $hours->workingDays());
        $this->assertTrue($hours->isOpen(Carbon::parse('2026-11-14 07:30', 'Asia/Jakarta')));
        $this->assertFalse($hours->isOpen(Carbon::parse('2026-11-14 12:00', 'Asia/Jakarta')));
    }

    public function test_comma_separated_and_invalid_day_lists(): void
    {
        $this->setting('working_days', "Senin, Rabu, Jum'at");
        $this->assertSame([1, 3, 5], (new BusinessHours)->workingDays());

        $this->setting('working_days', 'tidak jelas');
        $this->assertSame([1, 2, 3, 4, 5], (new BusinessHours)->workingDays());
    }

    public function test_business_days_between_skip_weekend(): void
    {
        $hours = new BusinessHours;
        $friday = Carbon::parse('2026-11-13 15:00', 'Asia/Jakarta');

        $this->assertSame(0, $hours->businessDaysBetween($friday, Carbon::parse('2026-11-15 10:00', 'Asia/Jakarta')));
        $this->assertSame(1, $hours->businessDaysBetween($friday, Carbon::parse('2026-11-16 08:00', 'Asia/Jakarta')));
        $this->assertSame(5, $hours->businessDaysBetween($friday, Carbon::parse('2026-11-20 08:00', 'Asia/Jakarta')));
        $this->assertSame(0, $hours->businessDaysBetween($friday, $friday));
    }

    public function test_public_service_hours_text(): void
    {
        $this->assertNull((new BusinessHours)->publicText());
        $this->setting('service_hours_text', ' Senin–Jumat 08.00–16.00 WIB ');
        $this->assertSame('Senin–Jumat 08.00–16.00 WIB', (new BusinessHours)->publicText());
    }

    // ------------------------------------------------------------ ComplaintService

    public function test_ticket_numbers_are_sequential_per_month(): void
    {
        Carbon::setTestNow('2026-11-30 23:00:00');
        $service = app(ComplaintService::class);
        $this->assertSame('ADU-202611-0001', $service->nextTicketNumber());

        $this->complaint('ADU-202611-0009');
        $this->complaint('ADU-202610-0050');
        $this->assertSame('ADU-202611-0010', $service->nextTicketNumber());

        Carbon::setTestNow('2026-12-01 08:00:00');
        $this->assertSame('ADU-202612-0001', $service->nextTicketNumber());
    }

    public function test_create_from_bot_for_lid_contact_uses_chat_id(): void
    {
        $conversation = $this->startChat(['whatsapp_number' => null, 'wa_chat_id' => '123456789@lid']);

        $complaint = app(ComplaintService::class)->createFromBot($conversation, ['name' => 'A', 'category' => '2', 'text' => 'isi']);

        $this->assertSame('123456789@lid', $complaint->whatsapp_number);
        $this->assertSame('Layanan WNA / izin tinggal', $complaint->category->name);
        $this->assertSame('Sedang', $complaint->priority);
        $this->assertSame([], $complaint->attachments);
    }

    public function test_overdue_rules_per_priority(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Jakarta')); // Senin
        $tinggi = $this->complaint('ADU-202611-0001', ['priority' => 'Tinggi', 'submitted_at' => now()]);
        $rendah = $this->complaint('ADU-202611-0002', ['priority' => 'Rendah', 'submitted_at' => now()]);
        $service = app(ComplaintService::class);

        Carbon::setTestNow(Carbon::parse('2026-11-04 09:00', 'Asia/Jakarta')); // 2 hari kerja
        $this->assertTrue($service->isOverdue($tinggi, 0), 'Respon awal Tinggi: 1 hari kerja');
        $this->assertFalse($service->isOverdue($rendah, 0), 'Respon awal Rendah: 2 hari kerja');
        $this->assertFalse($service->isOverdue($tinggi, 1));

        Carbon::setTestNow(Carbon::parse('2026-11-06 09:00', 'Asia/Jakarta')); // 4 hari kerja
        $this->assertTrue($service->isOverdue($tinggi, 1), 'Selesai Tinggi: 3 hari kerja');

        $tinggi->update(['status' => 'Diproses']);
        $this->assertFalse($service->isOverdue($tinggi, 0), 'Sudah direspons');
        $tinggi->update(['status' => 'Selesai']);
        $this->assertFalse($service->isOverdue($tinggi, 1), 'Sudah selesai');
        $this->assertFalse($service->isOverdue(new Complaint(['status' => 'Menunggu'])), 'Tanpa prioritas');
    }

    public function test_escalate_overdue_moves_tickets_to_level_2(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Jakarta'));
        $old = $this->complaint('ADU-202611-0001', ['priority' => 'Tinggi', 'submitted_at' => now()]);
        $done = $this->complaint('ADU-202611-0002', ['priority' => 'Tinggi', 'submitted_at' => now(), 'status' => 'Selesai']);
        $fresh = $this->complaint('ADU-202611-0003', ['priority' => 'Sedang', 'submitted_at' => now()]);

        Carbon::setTestNow(Carbon::parse('2026-11-11 09:00', 'Asia/Jakarta')); // 7 hari kerja
        $this->assertSame(1, app(ComplaintService::class)->escalateOverdue());

        $this->assertSame(2, $old->fresh()->escalation_level);
        $this->assertSame(1, $done->fresh()->escalation_level);
        $this->assertSame(1, $fresh->fresh()->escalation_level);
    }

    private function complaint(string $ticket, array $overrides = []): Complaint
    {
        return Complaint::create(array_merge([
            'ticket_number' => $ticket, 'user_name' => 'A', 'whatsapp_number' => '628', 'complaint_text' => 'x', 'status' => 'Menunggu',
        ], $overrides));
    }
}
