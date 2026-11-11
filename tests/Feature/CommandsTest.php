<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

class CommandsTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_user_with_role(): void
    {
        $this->artisan('kancil:buat-user', ['username' => 'agus', '--role' => 'petugas', '--name' => 'Agus Setiawan', '--password' => 'rahasia123'])
            ->expectsOutputToContain('Akun agus (petugas) tersimpan.')
            ->assertSuccessful();

        $user = User::where('username', 'agus')->sole();
        $this->assertSame('petugas', $user->role);
        $this->assertTrue(Hash::check('rahasia123', $user->password));

        $this->artisan('kancil:buat-user', ['username' => 'agus', '--role' => 'pimpinan', '--password' => 'rahasia456'])->assertSuccessful();
        $this->assertSame('pimpinan', $user->fresh()->role);
        $this->assertSame(1, User::count());
    }

    public function test_create_user_validation(): void
    {
        $this->artisan('kancil:buat-user', ['username' => 'x', '--role' => 'superadmin', '--password' => 'rahasia123'])->assertFailed();
        $this->artisan('kancil:buat-user', ['username' => 'x', '--password' => 'pendek'])->assertFailed();
        $this->artisan('kancil:buat-user', ['username' => 'x', '--password' => 'rahasia123', '--email' => 'bukan-email'])->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_create_user_asks_password_when_missing(): void
    {
        $this->artisan('kancil:buat-user', ['username' => 'rina'])
            ->expectsQuestion('Kata sandi', 'rahasia789')
            ->assertSuccessful();

        $this->assertSame('petugas', User::sole()->role);
    }

    public function test_maintenance_releases_handoffs_escalates_tickets_and_prunes_webhooks(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Jakarta'));
        $complaint = Complaint::create(['ticket_number' => 'ADU-202611-0001', 'user_name' => 'a', 'whatsapp_number' => '1',
            'complaint_text' => 'x', 'status' => 'Menunggu', 'priority' => 'Tinggi', 'submitted_at' => now()]);
        $old = WebhookEvent::create(['type' => 'incoming_chat', 'payload' => []]);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        Carbon::setTestNow(Carbon::parse('2026-11-12 09:00', 'Asia/Jakarta'));
        $recent = WebhookEvent::create(['type' => 'incoming_chat', 'payload' => []]);
        $expired = $this->startChat(['conversation_mode' => 'HUMAN_HANDLING', 'bot_paused_until' => now()->subMinute()]);
        $active = $this->startChat(['wa_chat_id' => 'b', 'conversation_mode' => 'HUMAN_HANDLING', 'bot_paused_until' => now()->addHour()]);
        $manual = $this->startChat(['wa_chat_id' => 'c', 'conversation_mode' => 'HUMAN_HANDLING', 'bot_paused_until' => null]);

        $this->artisan('kancil:pemeliharaan')
            ->expectsOutputToContain('Dikembalikan ke bot: 1; tiket naik ke Pimpinan: 1; webhook dihapus: 1.')
            ->assertSuccessful();

        $this->assertSame('BOT_ACTIVE', $expired->fresh()->conversation_mode);
        $this->assertSame('HUMAN_HANDLING', $active->fresh()->conversation_mode);
        $this->assertSame('HUMAN_HANDLING', $manual->fresh()->conversation_mode);
        $this->assertSame(2, $complaint->fresh()->escalation_level);
        $this->assertFalse(WebhookEvent::whereKey($old->id)->exists());
        $this->assertTrue(WebhookEvent::whereKey($recent->id)->exists());
    }

    public function test_maintenance_is_scheduled_every_five_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'kancil:pemeliharaan'));

        $this->assertNotNull($event);
        $this->assertSame('*/5 * * * *', $event->expression);
    }

    public function test_simulation_runs_bot_without_whatsapp(): void
    {
        $this->configureIntegration('laravel');
        $this->seedFaqs();
        Http::fake();

        $this->artisan('kancil:simulasi', ['pesan' => 'Halo'])->expectsOutputToContain('Selamat datang')->assertSuccessful();
        $this->artisan('kancil:simulasi', ['pesan' => '0'])->expectsOutputToContain('Menu WNI')->assertSuccessful();

        $conversation = Conversation::sole();
        $this->assertSame('simulasi', $conversation->channel);
        $this->assertSame(4, Message::count());
        $this->assertNull(Message::where('sender_type', 'bot')->first()->delivery_status);
        Http::assertNothingSent();

        $this->artisan('kancil:simulasi', ['pesan' => '1', '--reset' => true])->expectsOutputToContain('Selamat datang')->assertSuccessful();
    }

    public function test_simulation_interactive_mode(): void
    {
        $this->configureIntegration('laravel');
        $this->seedFaqs();

        $this->artisan('kancil:simulasi')
            ->expectsQuestion('Anda', 'Halo')
            ->expectsQuestion('Anda', 'keluar')
            ->assertSuccessful();

        $this->assertSame(1, Message::where('sender_type', 'user')->count());
    }

    public function test_ai_check_requires_key(): void
    {
        $this->configureIntegration('laravel', llm: false);

        $this->artisan('kancil:ai-cek')->expectsOutputToContain('LLM_API_KEY belum diisi')->assertFailed();
    }

    public function test_ai_check_reports_balance_and_response(): void
    {
        $this->configureIntegration('laravel', llm: true);
        Http::fake([
            'ai.sumopod.com/v2/user/info' => Http::response(['spend' => 0.1, 'max_budget' => 1.0]),
            'ai.sumopod.com/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{"ok":true}']]]]),
        ]);

        $this->artisan('kancil:ai-cek')
            ->expectsOutputToContain('Model: gpt-5-nano')
            ->expectsOutputToContain('Saldo: $0.9000')
            ->expectsOutputToContain('AI merespons: {"ok":true}')
            ->assertSuccessful();

        $this->artisan('kancil:ai-cek', ['--saldo' => true])->assertSuccessful();
    }

    public function test_ai_check_reports_failure(): void
    {
        $this->configureIntegration('laravel', llm: true);
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

        $this->artisan('kancil:ai-cek')
            ->expectsOutputToContain('Saldo tidak terbaca')
            ->expectsOutputToContain('Invalid API key')
            ->assertFailed();
    }

    public function test_balesotomatis_info(): void
    {
        $this->configureIntegration();
        config(['app.url' => 'https://kancil.example.com']);
        Http::fake(['*/info' => Http::response(['code' => 200, 'data' => ['number_id' => 'NUM-1']])]);

        $this->artisan('kancil:balesotomatis-info')
            ->expectsOutputToContain('https://kancil.example.com/webhook/balesotomatis/'.self::WEBHOOK_TOKEN)
            ->expectsOutputToContain('NUM-1')
            ->assertSuccessful();

        config(['balesotomatis.api_key' => null, 'balesotomatis.webhook_token' => '']);
        $this->artisan('kancil:balesotomatis-info')
            ->expectsOutputToContain('belum diisi')
            ->assertFailed();
    }
}
