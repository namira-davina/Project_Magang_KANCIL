<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Faq;
use App\Models\FaqKeyword;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FaqSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

/**
 * Impor 61 FAQ, aktivasi FAQ untuk bot, dan skema integrasi.
 */
class FaqIntegrationTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    public function test_seeder_imports_61_faqs_held_for_verification(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(61, Faq::count());
        $this->assertSame(61, Faq::where('verification_status', 'perlu_verifikasi')->where('is_active', false)->count());
        $this->assertSame(0, Faq::published()->count());

        $psp = Faq::where('code', 'PSP-001')->sole();
        $this->assertSame('0>1>1', $psp->menu_path);
        $this->assertSame('Paspor', $psp->category->name);
        $this->assertContains('psp-001', $psp->keywords->pluck('keyword')->all());

        $this->assertSame('Biaya dan Pembayaran', Faq::where('code', 'BYR-001')->sole()->category->name);
        $this->assertSame('Izin Tinggal', Faq::where('code', 'TRF-007')->sole()->category->name);
        $this->assertSame('Visa', Faq::where('code', 'TRF-010')->sole()->category->name);
        $this->assertSame('Informasi Umum', Faq::where('code', 'KTK-001')->sole()->category->name);
        $this->assertSame('Izin Tinggal', Faq::where('code', 'WNA-001')->sole()->category->name);
    }

    public function test_reseeding_keeps_verification_work(): void
    {
        $this->seed(DatabaseSeeder::class);
        Faq::where('code', 'PSP-001')->update(['verification_status' => 'terverifikasi', 'is_active' => true, 'answer' => 'Dikoreksi petugas']);
        $keywords = FaqKeyword::count();

        $this->seed(FaqSeeder::class);

        $faq = Faq::where('code', 'PSP-001')->sole();
        $this->assertTrue($faq->isPublished());
        $this->assertSame('Dikoreksi petugas', $faq->answer);
        $this->assertSame(61, Faq::count());
        $this->assertSame($keywords, FaqKeyword::count());
    }

    public function test_seeded_faqs_are_valid_for_the_edit_form(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('username', 'admin')->sole());

        $faq = Faq::where('code', 'LYK-006')->sole();
        $this->get(route('faq.edit', $faq->id))->assertOk()->assertSee('Aktif ditampilkan bot');

        $this->put(route('faq.update', $faq->id), [
            'kategori' => $faq->category_id, 'status' => 'terverifikasi', 'pertanyaan' => $faq->question,
            'kata_kunci' => 'eazy, paspor', 'jawaban' => $faq->answer, 'aktif' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($faq->fresh()->isPublished());
        $this->assertSame('LYK-006', $faq->fresh()->code, 'Kode FAQ tidak hilang saat diedit.');
    }

    public function test_activation_checkbox_rules(): void
    {
        $this->actingAsAdmin();
        $cat = $this->makeCategory();
        $id = $this->makeFaq($cat->id);
        $payload = fn (array $o) => $this->validFaqPayload($cat->id, $o);

        $this->put(route('faq.update', $id), $payload(['status' => 'terverifikasi', 'aktif' => '1']));
        $this->assertDatabaseHas('faqs', ['id' => $id, 'is_active' => 1]);

        $this->put(route('faq.update', $id), $payload(['status' => 'terverifikasi', 'aktif' => '0']));
        $this->assertDatabaseHas('faqs', ['id' => $id, 'is_active' => 0]);

        $this->put(route('faq.update', $id), $payload(['status' => 'perlu_verifikasi', 'aktif' => '1']));
        $this->assertDatabaseHas('faqs', ['id' => $id, 'is_active' => 0]); // belum terverifikasi tidak boleh aktif

        $this->put(route('faq.update', $id), $payload(['status' => 'terverifikasi', 'aktif' => 'ya']))->assertSessionHasErrors('aktif');
    }

    public function test_index_shows_status_for_seeded_and_activated_faqs(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('username', 'admin')->sole());
        Faq::where('code', 'PSP-001')->update(['verification_status' => 'terverifikasi', 'is_active' => true]);

        $html = $this->get(route('faq.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '● Aktif<'));
        $this->assertSame(60, substr_count($html, 'Perlu Verifikasi</span>') + substr_count($html, "● Perlu Verifikasi\n"));
    }

    public function test_integration_schema(): void
    {
        foreach ([
            'conversations' => ['wa_chat_id', 'device_key', 'session_state', 'last_inbound_at', 'bot_paused_until'],
            'messages' => ['external_message_id', 'client_uuid', 'delivery_status', 'delivery_error', 'meta', 'raw_payload'],
            'escalation_logs' => ['escalation_code', 'faq_id', 'priority', 'menu_path'],
            'complaints' => ['priority', 'escalation_level', 'attachments', 'first_response_at'],
            'faqs' => ['code', 'menu_path'],
            'webhook_events' => ['type', 'number_key', 'message_id', 'dedupe_key', 'payload', 'processed_at', 'error'],
            'llm_logs' => ['question', 'answer', 'faq_codes', 'confident', 'prompt_tokens', 'completion_tokens', 'latency_ms', 'error'],
        ] as $table => $columns) {
            $this->assertTrue(Schema::hasColumns($table, $columns), $table);
        }
    }

    public function test_conversation_ticket_and_number_are_optional_and_pimpinan_role_exists(): void
    {
        $id = Conversation::create(['wa_chat_id' => 'x@lid', 'channel' => 'whatsapp'])->id;
        $this->assertNull(Conversation::find($id)->ticket_number);

        $this->makeUser(['role' => 'pimpinan']);
        $this->assertSame('pimpinan', User::sole()->role);
    }

    public function test_external_message_id_and_wa_chat_id_are_unique(): void
    {
        $c = Conversation::create(['wa_chat_id' => 'dup', 'channel' => 'whatsapp']);
        $c->messages()->create(['sender_type' => 'user', 'external_message_id' => 'E1']);

        $this->expectException(QueryException::class);
        $c->messages()->create(['sender_type' => 'user', 'external_message_id' => 'E1']);
    }

    public function test_migration_can_roll_back_and_reapply(): void
    {
        Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]);
        $this->assertFalse(Schema::hasTable('webhook_events'));
        $this->assertFalse(Schema::hasColumn('faqs', 'code'));

        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('webhook_events'));
    }
}
