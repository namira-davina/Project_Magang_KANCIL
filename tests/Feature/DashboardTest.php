<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    public function test_empty_dashboard_has_zeroed_stats(): void
    {
        $this->actingAsAdmin();

        $response = $this->get(route('admin.dashboard'))->assertOk();

        foreach (['totalPertanyaan', 'terjawabOtomatis', 'totalEskalasi', 'totalPengaduan',
            'pengaduanSelesai', 'pengaduanDiproses', 'pengaduanMenunggu'] as $key) {
            $this->assertSame(0, $response->viewData($key), $key);
        }
        $this->assertCount(7, $response->viewData('chartLabels'));
        $this->assertSame([0, 0, 0, 0, 0, 0, 0], $response->viewData('chartBot'));
        $this->assertCount(0, $response->viewData('kategoriPopuler'));
        $this->assertCount(0, $response->viewData('percakapanTerbaru'));
    }

    public function test_counts_messages_complaints_and_escalations(): void
    {
        $this->actingAsAdmin();
        $conv = $this->makeConversation();
        $this->makeMessage($conv, 'user');
        $this->makeMessage($conv, 'user');
        $this->makeMessage($conv, 'bot');
        $this->makeMessage($conv, 'ai');
        $this->makeMessage($conv, 'petugas');
        $this->makeEscalation($conv);
        foreach (['Menunggu', 'Diproses', 'Diproses', 'Selesai', 'Selesai', 'Selesai'] as $status) {
            $this->makeComplaint($status);
        }

        $r = $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame(2, $r->viewData('totalPertanyaan'));
        $this->assertSame(2, $r->viewData('terjawabOtomatis'));
        $this->assertSame(1, $r->viewData('totalEskalasi'));
        $this->assertSame(6, $r->viewData('totalPengaduan'));
        $this->assertSame(3, $r->viewData('pengaduanSelesai'));
        $this->assertSame(2, $r->viewData('pengaduanDiproses'));
        $this->assertSame(1, $r->viewData('pengaduanMenunggu'));
    }

    public function test_chart_buckets_last_seven_days_and_ignores_older_messages(): void
    {
        $this->actingAsAdmin();
        $conv = $this->makeConversation();
        $this->makeMessage($conv, 'bot', ['sent_at' => now()]);
        $this->makeMessage($conv, 'ai', ['sent_at' => now()]);
        $this->makeMessage($conv, 'petugas', ['sent_at' => now()->subDays(6)]);
        $this->makeMessage($conv, 'bot', ['sent_at' => now()->subDays(7)->startOfDay()->subSecond()]);

        $r = $this->get(route('admin.dashboard'));

        $this->assertSame([0, 0, 0, 0, 0, 0, 2], $r->viewData('chartBot'));
        $this->assertSame([1, 0, 0, 0, 0, 0, 0], $r->viewData('chartPetugas'));
    }

    public function test_popular_categories_are_ranked_with_percentages(): void
    {
        $this->actingAsAdmin();
        $conv = $this->makeConversation();
        $paspor = $this->makeCategory('Paspor');
        $visa = $this->makeCategory('Visa');
        $faqPaspor = $this->makeFaq($paspor->id);
        $faqVisa = $this->makeFaq($visa->id);
        foreach ([$faqPaspor, $faqPaspor, $faqPaspor, $faqVisa] as $faq) {
            $this->makeMessage($conv, 'user', ['faq_id' => $faq]);
        }
        // bot replies must not count
        $this->makeMessage($conv, 'bot', ['faq_id' => $faqVisa]);

        $top = $this->get(route('admin.dashboard'))->viewData('kategoriPopuler');

        $this->assertSame(['Paspor', 'Visa'], $top->pluck('name')->all());
        $this->assertSame([75, 25], $top->pluck('persentase')->map(fn ($p) => (int) $p)->all());
    }

    public function test_popular_categories_limited_to_five(): void
    {
        $this->actingAsAdmin();
        $conv = $this->makeConversation();
        foreach (range(1, 7) as $i) {
            $faq = $this->makeFaq($this->makeCategory("Kat $i")->id);
            $this->makeMessage($conv, 'user', ['faq_id' => $faq]);
        }

        $this->assertCount(5, $this->get(route('admin.dashboard'))->viewData('kategoriPopuler'));
    }

    public function test_latest_conversations_show_last_message_newest_first_max_five(): void
    {
        $this->actingAsAdmin();
        foreach (range(1, 6) as $i) {
            $conv = $this->makeConversation(['user_name' => "U$i", 'last_message_at' => now()->subMinutes(10 - $i)]);
            $this->makeMessage($conv, 'user', ['message_text' => "lama $i", 'sent_at' => now()->subHours(2)]);
            $this->makeMessage($conv, 'bot', ['message_text' => "baru $i", 'sent_at' => now()->subHour()]);
        }

        $list = $this->get(route('admin.dashboard'))->viewData('percakapanTerbaru');

        $this->assertCount(5, $list);
        $this->assertSame('U6', $list->first()->user_name);
        $this->assertSame('baru 6', $list->first()->pesan_terakhir);
        $this->assertSame('bot', $list->first()->pengirim_terakhir);
    }

    public function test_conversation_without_messages_has_null_last_message(): void
    {
        $this->actingAsAdmin();
        $this->makeConversation();

        $row = $this->get(route('admin.dashboard'))->viewData('percakapanTerbaru')->first();

        $this->assertNull($row->pesan_terakhir);
    }

    public function test_dashboard_uses_constant_number_of_queries(): void
    {
        $this->actingAsAdmin();
        $conv = $this->makeConversation();
        $this->makeMessage($conv, 'user');

        DB::enableQueryLog();
        $this->get(route('admin.dashboard'))->assertOk();
        $few = count(DB::getQueryLog());

        foreach (range(1, 20) as $i) {
            $c = $this->makeConversation();
            $this->makeMessage($c, 'user');
            $this->makeMessage($c, 'bot');
        }
        DB::flushQueryLog();
        $this->get(route('admin.dashboard'))->assertOk();

        $this->assertSame($few, count(DB::getQueryLog()));
    }
}
