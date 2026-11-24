<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\FaqKeyword;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    /** Kondisi prod 20 Nov: 61 FAQ lama, semua terverifikasi + aktif, beberapa isi lama, tanpa PSP-013..015. */
    private function prodLikeState(): void
    {
        $this->seed(DatabaseSeeder::class);
        Faq::whereIn('code', ['PSP-013', 'PSP-014', 'PSP-015', 'PSP-016'])->each(fn (Faq $faq) => $faq->keywords()->delete() && $faq->delete());
        Faq::query()->update(['verification_status' => 'terverifikasi', 'is_active' => true]);
        Faq::where('code', 'AMB-004')->update(['answer' => "• Lama penyelesaian tidak tercantum\n• silakan hubungi petugas."]);
    }

    public function test_dry_run_reports_without_changing_anything(): void
    {
        $this->prodLikeState();
        $before = Faq::orderBy('id')->get()->toArray();

        $this->artisan('kancil:faq-sync')
            ->expectsOutputToContain('+ PSP-013')
            ->expectsOutputToContain('~ AMB-004')
            ->expectsOutputToContain('(tidak diubah; pakai --reset-status)')
            ->expectsOutputToContain('Dry-run (tidak ada perubahan): baru 4, isi 1, status 0, status beda (dibiarkan) 61, dilewati 0, sama 0')
            ->assertSuccessful();

        $this->assertSame($before, Faq::orderBy('id')->get()->toArray());
    }

    public function test_apply_creates_missing_updates_content_and_keeps_status(): void
    {
        $this->prodLikeState();

        $this->artisan('kancil:faq-sync --apply')->assertSuccessful();

        $this->assertSame(65, Faq::count());
        $this->assertStringContainsString('Paspor percepatan: 1 hari kerja', Faq::where('code', 'AMB-004')->value('answer'));
        $this->assertSame(61, Faq::published()->count(), 'Status lama dipertahankan tanpa --reset-status.');

        $new = Faq::where('code', 'PSP-014')->sole();
        $this->assertSame('perlu_verifikasi', $new->verification_status);
        $this->assertFalse($new->is_active);
        $this->assertNotNull($new->category_id);
        $this->assertTrue(FaqKeyword::where('faq_id', $new->id)->where('keyword', 'psp-014')->exists());

        $this->artisan('kancil:faq-sync')->expectsOutputToContain('baru 0, isi 0, status 0, status beda (dibiarkan) 61, dilewati 0, sama 4')->assertSuccessful();
    }

    public function test_dashboard_edits_are_protected_unless_forced(): void
    {
        $this->prodLikeState();
        $officer = User::factory()->create();
        Faq::where('code', 'AMB-004')->update(['updated_by' => $officer->id]);

        $this->artisan('kancil:faq-sync --apply')->expectsOutputToContain('pernah disunting di dashboard')->assertSuccessful();
        $this->assertStringStartsWith('• Lama penyelesaian', Faq::where('code', 'AMB-004')->value('answer'));

        $this->artisan('kancil:faq-sync --apply --force')->assertSuccessful();
        $this->assertStringStartsWith('• Paspor percepatan: 1 hari kerja', Faq::where('code', 'AMB-004')->value('answer'));
    }

    public function test_reset_status_follows_inventory(): void
    {
        $this->prodLikeState();

        $this->artisan('kancil:faq-sync --apply --reset-status')->assertSuccessful();

        $this->assertSame(0, Faq::published()->count());
        $this->assertSame(65, Faq::where('verification_status', 'perlu_verifikasi')->where('is_active', false)->count());
    }

    public function test_reset_status_without_apply_is_still_dry_run(): void
    {
        $this->prodLikeState();

        $this->artisan('kancil:faq-sync --reset-status')->assertSuccessful();

        $this->assertSame(61, Faq::published()->count());
    }
}
