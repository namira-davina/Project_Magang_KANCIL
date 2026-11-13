<?php

namespace Tests\Feature;

use App\Models\EscalationLog;
use App\Models\FaqKeyword;
use App\Models\Message;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesData;
use Tests\TestCase;

/**
 * Guards the schema against drift from kancil_sapa.sql.
 */
class SchemaAndSeederTest extends TestCase
{
    use CreatesData, RefreshDatabase;

    public function test_seeder_loads_reference_data_from_dump(): void
    {
        $this->seed();

        $this->assertSame(8, DB::table('faq_categories')->count());
        // Kategori pengaduan PGD-03 (Dokumen Skenario FAQ).
        $this->assertSame(['Layanan paspor', 'Layanan WNA / izin tinggal', 'Pelayanan petugas', 'Lainnya'],
            DB::table('complaint_categories')->orderBy('id')->pluck('name')->all());
        $this->assertSame(['bot_name', 'service_hours_text', 'working_days', 'working_hours_end', 'working_hours_start'],
            DB::table('bot_settings')->orderBy('setting_key')->pluck('setting_key')->all());
        $this->assertSame('admin', DB::table('users')->value('username'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(8, DB::table('faq_categories')->count());
        $this->assertSame(4, DB::table('complaint_categories')->count());
        $this->assertSame(61, DB::table('faqs')->count());
        $this->assertSame(1, DB::table('users')->count());
    }

    public function test_tables_without_updated_at_match_the_dump(): void
    {
        foreach (['messages', 'escalation_logs', 'faq_keywords'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'created_at'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'updated_at'), $table);
        }
    }

    public function test_models_of_tables_without_updated_at_can_be_created(): void
    {
        $faq = $this->makeFaq($this->makeCategory()->id);
        $conv = $this->makeConversation();

        $message = Message::create(['conversation_id' => $conv, 'sender_type' => 'user', 'message_text' => 'hi']);
        $log = EscalationLog::create(['conversation_id' => $conv, 'escalation_type' => 'user_request']);
        $keyword = FaqKeyword::create(['faq_id' => $faq, 'keyword' => 'k']);

        $this->assertNotNull($message->fresh()->sent_at);
        $this->assertSame('Menunggu', $log->fresh()->status);
        $this->assertNotNull($keyword->fresh()->created_at);
    }

    public function test_conversation_defaults_match_the_dump(): void
    {
        $id = DB::table('conversations')->insertGetId(['ticket_number' => 'KS-1', 'whatsapp_number' => '628']);

        $row = DB::table('conversations')->find($id);
        $this->assertSame('simulasi', $row->channel);
        $this->assertSame('BOT_ACTIVE', $row->conversation_mode);
        $this->assertNotNull($row->started_at);
    }

    public function test_user_defaults_and_unique_constraints(): void
    {
        $id = DB::table('users')->insertGetId(['name' => 'P', 'username' => 'p', 'password' => 'x']);
        $this->assertSame('petugas', DB::table('users')->find($id)->role);
        $this->assertEquals(1, DB::table('users')->find($id)->is_active);

        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'Q', 'username' => 'p', 'password' => 'x']);
    }

    public function test_email_is_optional_but_unique(): void
    {
        DB::table('users')->insert(['name' => 'A', 'username' => 'a', 'password' => 'x']);
        DB::table('users')->insert(['name' => 'B', 'username' => 'b', 'password' => 'x']); // both NULL email

        $this->assertSame(2, DB::table('users')->count());
    }

    public function test_complaint_requires_reporter_details_and_defaults_to_menunggu(): void
    {
        $id = $this->makeComplaint();

        $this->assertSame('Menunggu', DB::table('complaints')->find($id)->status);
        $this->assertNotNull(DB::table('complaints')->find($id)->submitted_at);

        $this->expectException(QueryException::class);
        DB::table('complaints')->insert(['ticket_number' => 'X', 'complaint_text' => 'y']);
    }

    public function test_remember_me_login_works_with_schema(): void
    {
        $user = $this->makeUser();

        $this->post('/login', ['username' => 'admin', 'password' => 'password', 'remember' => '1'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertNotNull($user->fresh()->remember_token);
    }
}
