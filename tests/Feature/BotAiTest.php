<?php

namespace Tests\Feature;

use App\Models\EscalationLog;
use App\Models\Faq;
use App\Models\LlmLog;
use App\Models\Message;
use App\Services\Bot\BotScript;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

/**
 * Bot + AI (SumoPod gpt-5-nano): pertanyaan bebas dijawab dari FAQ, wajib menyebut kode FAQ, fallback aman.
 */
class BotAiTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->atOfficeHours();
        $this->configureIntegration('laravel', llm: true);
        $this->seedFaqs();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_free_question_mid_session_is_answered_by_ai_with_faq_source(): void
    {
        $this->fakeApis($this->aiJson('Bapak/Ibu, syarat paspor baru: *KTP elektronik*, KK, akta lahir.', ['psp-001']));
        $this->reply('Halo');

        $reply = $this->reply('apa syarat bikin paspor baru?');

        $this->assertSame(['AI', 'PSP-001'], $reply->codes);
        $this->assertSame('ai', $reply->sender);
        $this->assertStringStartsWith('Bapak/Ibu, syarat paspor baru', $reply->text);
        $this->assertStringEndsWith(BotScript::msg('AI-FOOTER'), $reply->text);
        $psp001 = Faq::where('code', 'PSP-001')->value('id');
        $this->assertSame($psp001, $reply->faqId);
        $this->assertSame($psp001, Message::where('sender_type', 'user')->latest('id')->value('faq_id'));

        $log = LlmLog::sole();
        $this->assertTrue($log->confident);
        $this->assertSame(['PSP-001'], $log->faq_codes);
        $this->assertSame(100, $log->prompt_tokens);
        $this->assertSame('gpt-5-nano', $log->model);
    }

    public function test_main_menu_mentions_free_questions_when_ai_is_on(): void
    {
        $this->fakeApis();
        $this->assertStringContainsString('Anda juga dapat langsung mengetik pertanyaan.', $this->reply('Halo')->text);
    }

    public function test_first_message_question_is_answered_directly(): void
    {
        $this->fakeApis($this->aiJson('Tarif paspor 5 tahun Rp650.000.', ['TRF-001']));

        $reply = $this->reply('Berapa biaya paspor 5 tahun?');

        $this->assertSame(['AI', 'TRF-001'], $reply->codes);
        $this->assertSame('M-00', $this->chat->fresh()->session_state['menu']);
    }

    public function test_first_message_greeting_shows_menu_without_calling_ai(): void
    {
        $this->fakeApis();

        $this->assertSame(['M-00'], $this->reply('Assalamualaikum pak')->codes);
        Http::assertNothingSent();
    }

    public function test_greeting_mid_session_shows_main_menu(): void
    {
        $this->fakeApis();
        $this->conversation(['Halo', '0']);

        $this->assertSame(['M-00'], $this->reply('selamat pagi min')->codes);
        $this->assertSame(0, LlmLog::count());
    }

    public function test_first_message_question_that_ai_cannot_answer_falls_back_to_menu(): void
    {
        $this->fakeApis($this->aiJson('', [], false));

        $this->assertSame(['M-00'], $this->reply('Kapan kantor imigrasi buka hari minggu?')->codes);
        $this->assertSame(0, EscalationLog::count());
    }

    public function test_unknown_question_mid_session_gives_contact_and_logs_a1(): void
    {
        $this->fakeApis($this->aiJson('Tidak tahu', [], false));
        $this->reply('Halo');

        $reply = $this->reply('alamat kantor imigrasi di mana ya?');

        $this->assertSame(['AI-UNKNOWN', 'ESK-01'], $reply->codes);
        $this->assertStringContainsString('081217000900', $reply->text);
        $log = EscalationLog::sole();
        $this->assertSame('A1', $log->escalation_code);
        $this->assertStringContainsString('alamat kantor', $log->reason);
        $this->assertSame('WAITING_HUMAN', $this->chat->fresh()->conversation_mode);
    }

    public function test_ai_codes_outside_context_are_rejected(): void
    {
        $this->fakeApis($this->aiJson('Jawaban karangan', ['XXX-999'], true));
        $this->reply('Halo');

        $this->assertSame(['AI-UNKNOWN', 'ESK-01'], $this->reply('pertanyaan aneh sekali')->codes);
        $this->assertFalse(LlmLog::sole()->confident);
    }

    public function test_unpublished_faqs_are_not_given_to_ai_by_default(): void
    {
        Faq::query()->update(['verification_status' => 'perlu_verifikasi', 'is_active' => false]);
        $this->fakeApis($this->aiJson('x', ['PSP-001']));
        $this->reply('Halo');

        $reply = $this->reply('syarat paspor baru apa saja?');

        $this->assertSame(['AI-UNKNOWN', 'ESK-01'], $reply->codes);
        $this->assertStringContainsString('Belum ada FAQ', LlmLog::sole()->error);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'sumopod'));
    }

    public function test_scope_all_uses_unverified_faqs_with_disclaimer(): void
    {
        config(['llm.faq_scope' => 'all']);
        Faq::query()->update(['verification_status' => 'perlu_verifikasi', 'is_active' => false]);
        $this->fakeApis($this->aiJson('Syarat: KTP, KK.', ['PSP-001']));
        $this->reply('Halo');

        $reply = $this->reply('syarat paspor baru apa saja?');

        $this->assertSame(['AI', 'PSP-001'], $reply->codes);
        $this->assertStringContainsString(BotScript::msg('AI-DISCLAIMER'), $reply->text);
    }

    public function test_ai_error_falls_back_to_rule_based_invalid_flow(): void
    {
        $this->fakeApis(null, 500);
        $this->reply('Halo');

        $reply = $this->reply('syarat paspor baru apa saja?');

        $this->assertSame(['MSG-01', 'M-00'], $reply->codes);
        $this->assertNotNull(LlmLog::sole()->error);
    }

    public function test_daily_limit_stops_ai_calls(): void
    {
        config(['llm.daily_request_limit' => 1]);
        $this->fakeApis($this->aiJson('Jawaban.', ['PSP-001']));
        $this->reply('Halo');

        $this->assertSame(['AI', 'PSP-001'], $this->reply('syarat paspor baru apa?')->codes);
        $this->assertSame(['MSG-01', 'M-00'], $this->reply('syarat paspor lagi dong')->codes);
        $this->assertSame(1, LlmLog::count());
    }

    public function test_88_after_ai_answer_uses_answer_escalation_code(): void
    {
        $this->fakeApis($this->aiJson('Syarat paspor hilang ...', ['PSP-011']));
        $this->reply('Halo');
        $this->reply('paspor saya hilang, apa syaratnya?');
        $this->reply('88');

        $log = EscalationLog::sole();
        $this->assertSame('C1', $log->escalation_code);
        $this->assertSame('Tinggi', $log->priority);
    }

    public function test_numbers_still_drive_menu_without_ai(): void
    {
        $this->fakeApis();
        $this->assertSame(['M-01'], $this->conversation(['Halo', '0'])->codes);
        $this->assertSame(0, LlmLog::count());
    }

    public function test_ai_is_not_used_inside_complaint_flow(): void
    {
        $this->fakeApis($this->aiJson('x', ['PSP-001']));
        $this->conversation(['Halo', '2', '1']);

        $this->assertSame(['PGD-03'], $this->reply('Budi Santoso')->codes);
        $this->assertSame(0, LlmLog::count());
    }

    public function test_request_to_sumopod_is_well_formed_and_masks_personal_data(): void
    {
        $this->fakeApis($this->aiJson('ok', ['PSP-001']));
        $this->reply('Halo');
        $this->reply('NIK saya 3301234567890001 email budi@mail.com, syarat paspor?');

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'ai.sumopod.com/v1/chat/completions')) {
                return false;
            }

            $body = $request->data();
            $all = json_encode($body);

            return $request->hasHeader('Authorization', 'Bearer sk-uji')
                && $body['model'] === 'gpt-5-nano'
                && $body['max_completion_tokens'] === 2000
                && $body['reasoning_effort'] === 'low'
                && $body['response_format'] === ['type' => 'json_object']
                && ! array_key_exists('temperature', $body)
                && $body['messages'][0]['role'] === 'system'
                && str_contains($body['messages'][0]['content'], '[PSP-001]')
                && str_contains($body['messages'][0]['content'], '081217000900')
                && ! str_contains($all, '3301234567890001')
                && ! str_contains($all, 'budi@mail.com')
                && end($body['messages'])['role'] === 'user';
        });
    }

    public function test_conversation_history_is_sent_as_context(): void
    {
        $this->fakeApis($this->aiJson('ok', ['PSP-001']));
        $this->reply('Halo');
        // simpan balasan bot seperti job
        $this->chat->messages()->create(['sender_type' => 'bot', 'message_text' => 'MENU UTAMA', 'sent_at' => now()]);
        $this->reply('syarat paspor baru apa?');

        Http::assertSent(function (Request $request) {
            if (! str_contains($request->url(), 'sumopod')) {
                return false;
            }
            $messages = $request->data()['messages'];
            $roles = array_column($messages, 'role');

            return in_array('assistant', $roles, true)
                && collect($messages)->where('role', 'user')->where('content', 'syarat paspor baru apa?')->count() === 1;
        });
    }
}
