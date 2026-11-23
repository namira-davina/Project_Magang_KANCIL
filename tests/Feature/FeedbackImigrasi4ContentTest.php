<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\User;
use App\Services\Bot\BotScript;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Feedback Imigrasi 23 Nov 2026 (feedback user 4), konten:
 *  WA0007  ANK-005 "surat pernyataan bermeterai" (hanya ANK-005, keputusan pemilik)
 *  WA0008  Paspor Baru (PSP-001) + "Bukti permohonan M-Paspor" (hanya PSP-001, PSP-002 tidak diubah)
 *  WA0009  PSP-001 dibuat daftar "Persyaratan:" seperti PSP-002
 *  WA0010  contoh nomor permohonan berawalan 1568
 * Dari data sumber, lewat sinkron ke DB prod lama, sampai teks yang diterima pemohon.
 */
class FeedbackImigrasi4ContentTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    private const PSP_001 = "*Persyaratan:*\n• KTP elektronik\n• Kartu Keluarga\n• Akta lahir / ijazah / buku nikah\n• Bukti permohonan M-Paspor\nBawa berkas asli dan fotokopi ukuran A4.";

    private const ANK_005_QUESTION = 'Siapa yang menandatangani surat pernyataan bermeterai jika kedua orang tua bercerai hidup?';

    /** Isi prod 23 Nov sebelum fix (dibaca read-only dari DB prod). */
    private const PROD_PSP_001 = 'KTP elektronik, Kartu Keluarga, dan salah satu dari akta lahir/ijazah/buku nikah. Bawa berkas asli dan fotokopi ukuran A4.';

    private const PROD_ANK_005_QUESTION = 'Siapa yang menandatangani surat pernyataan jika kedua orang tua bercerai hidup?';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private static function data(): array
    {
        return array_column(require base_path('database/data/faqs.php'), null, 'code');
    }

    public function test_source_data_psp_001_is_a_requirement_list_with_m_paspor_proof(): void
    {
        $this->assertSame(self::PSP_001, self::data()['PSP-001']['answer']);
    }

    public function test_psp_001_uses_same_list_format_as_psp_002(): void
    {
        $data = self::data();

        foreach (['PSP-001', 'PSP-002'] as $code) {
            $this->assertStringStartsWith("*Persyaratan:*\n• KTP elektronik\n• Kartu Keluarga\n• Akta lahir / ijazah / buku nikah\n", $data[$code]['answer'], $code);
        }
    }

    public function test_psp_002_is_unchanged_by_this_feedback(): void
    {
        // Keputusan pemilik 23 Nov: bukti permohonan M-Paspor hanya untuk Paspor Baru (PSP-002 = pertanyaan ke Imigrasi).
        $this->assertSame(
            "*Persyaratan:*\n• KTP elektronik\n• Kartu Keluarga\n• Akta lahir / ijazah / buku nikah\n• Paspor lama\nDaftar terlebih dahulu melalui aplikasi M-Paspor.",
            self::data()['PSP-002']['answer'],
        );
    }

    public function test_only_ank_005_question_gets_bermeterai(): void
    {
        $data = self::data();

        $this->assertSame(self::ANK_005_QUESTION, $data['ANK-005']['question']);
        $this->assertStringNotContainsString('bermeterai', $data['ANK-005']['answer'], 'Jawaban ANK-005 tidak diubah.');

        foreach (['ANK-006', 'ANK-007', 'ANK-008', 'ANK-009'] as $code) {
            $this->assertStringNotContainsString('bermeterai', $data[$code]['question'].$data[$code]['answer'], "{$code} tidak termasuk cakupan.");
        }
    }

    public function test_spelling_follows_kbbi_meterai_not_materai(): void
    {
        foreach (self::data() as $code => $row) {
            $this->assertDoesNotMatchRegularExpression('/materai/i', $row['question'].' '.$row['answer'], "{$code}: ejaan KBBI \"meterai\".");
        }
    }

    public function test_applicant_sees_new_psp_001_list_in_menu_1_2(): void
    {
        $this->atOfficeHours();
        $this->configureIntegration('laravel');
        $this->seedFaqs();
        $this->reply('Halo');

        $answer = $this->conversation(['1', '2']);

        $this->assertSame(['ANS-0>2', 'PSP-001'], $answer->codes);
        $this->assertSame(
            BotScript::answer(self::PSP_001."\n\n".BotScript::pnbpBlock(['paspor_5', 'paspor_10'])),
            $answer->text,
            'Daftar persyaratan, lalu blok PNBP terpisah, lalu footer 9/0/1.',
        );
    }

    public function test_applicant_sees_bermeterai_in_child_passport_answer(): void
    {
        $this->atOfficeHours();
        $this->configureIntegration('laravel');
        $this->seedFaqs();
        $this->reply('Halo');

        $answer = $this->conversation(['1', '4'])->text;

        $this->assertStringContainsString('*'.self::ANK_005_QUESTION."*\nJika ada penetapan pengadilan tentang hak asuh", $answer);
        $this->assertStringContainsString('Surat pernyataan jaminan orang tua bermeterai', $answer, 'ANK-001 tetap.');
    }

    public function test_status_check_prompt_shows_1568_example(): void
    {
        $this->atOfficeHours();
        $this->configureIntegration('laravel');
        $this->seedFaqs();
        $this->reply('Halo');

        $prompt = $this->conversation(['1', '11'])->text;

        $this->assertStringContainsString("Contoh: 1568000000012345\n", $prompt);
        $this->assertStringNotContainsString('1498', $prompt);
    }

    public function test_faq_sync_updates_prod_like_db_without_touching_verified_status(): void
    {
        $this->seed(DatabaseSeeder::class);
        Faq::query()->update(['verification_status' => 'terverifikasi', 'is_active' => true]);
        Faq::where('code', 'PSP-001')->update(['answer' => self::PROD_PSP_001]);
        Faq::where('code', 'ANK-005')->update(['question' => self::PROD_ANK_005_QUESTION]);

        $this->artisan('kancil:faq-sync')
            ->expectsOutputToContain('~ PSP-001')
            ->expectsOutputToContain('~ ANK-005')
            ->expectsOutputToContain('isi 2,')
            ->assertSuccessful();
        $this->assertSame(self::PROD_PSP_001, Faq::where('code', 'PSP-001')->value('answer'), 'Dry-run tidak mengubah DB.');

        $this->artisan('kancil:faq-sync --apply')->assertSuccessful();

        $this->assertSame(self::PSP_001, Faq::where('code', 'PSP-001')->value('answer'));
        $this->assertSame(self::ANK_005_QUESTION, Faq::where('code', 'ANK-005')->value('question'));
        $this->assertSame(Faq::count(), Faq::published()->count(), 'Semua FAQ tetap terverifikasi + aktif (bot tidak jatuh ke Pesan 5).');

        $this->artisan('kancil:faq-sync')->expectsOutputToContain('isi 0,')->assertSuccessful();
    }

    public function test_faq_sync_skips_psp_001_edited_in_dashboard_unless_forced(): void
    {
        $this->seed(DatabaseSeeder::class);
        $officer = User::factory()->create();
        Faq::where('code', 'PSP-001')->update(['answer' => self::PROD_PSP_001, 'updated_by' => $officer->id]);

        $this->artisan('kancil:faq-sync --apply')->assertSuccessful();
        $this->assertSame(self::PROD_PSP_001, Faq::where('code', 'PSP-001')->value('answer'), 'Suntingan petugas dilindungi.');

        $this->artisan('kancil:faq-sync --apply --force')->assertSuccessful();
        $this->assertSame(self::PSP_001, Faq::where('code', 'PSP-001')->value('answer'));
    }
}
