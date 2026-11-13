<?php

namespace Tests\Unit;

use App\Models\Faq;
use App\Models\LlmLog;
use App\Services\Llm\LlmResponder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BotHelpers;
use Tests\TestCase;

class LlmResponderTest extends TestCase
{
    use BotHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureIntegration('laravel', llm: true);
    }

    private function responder(): LlmResponder
    {
        return app(LlmResponder::class);
    }

    private function faq(string $code, bool $published = true): Faq
    {
        return Faq::create([
            'code' => $code, 'question' => "Tanya {$code}?", 'answer' => "Jawab {$code}.",
            'verification_status' => $published ? 'terverifikasi' : 'perlu_verifikasi', 'is_active' => $published,
        ]);
    }

    public static function personalData(): array
    {
        return [
            'NIK 16 digit' => ['NIK 3301234567890001 ya', 'NIK [angka disamarkan] ya'],
            'nomor HP panjang' => ['hubungi 6281234567890', 'hubungi [angka disamarkan]'],
            'email' => ['kirim ke budi.s@mail.co.id', 'kirim ke [email disamarkan]'],
            'angka pendek tetap' => ['paspor 5 tahun Rp650000', 'paspor 5 tahun Rp650000'],
        ];
    }

    #[DataProvider('personalData')]
    public function test_masks_personal_data(string $input, string $expected): void
    {
        $this->assertSame($expected, LlmResponder::maskPersonalData($input));
    }

    public static function jsonContents(): array
    {
        return [
            'json murni' => ['{"answer":"a","faq_codes":["X"],"confident":true}', 'a'],
            'dibungkus teks' => ["Berikut:\n```json\n{\"answer\":\"b\",\"confident\":true}\n```", 'b'],
            'bukan json' => ['maaf saya tidak tahu', null],
        ];
    }

    #[DataProvider('jsonContents')]
    public function test_parses_json_responses(string $content, ?string $answer): void
    {
        $parsed = LlmResponder::parseJson($content);
        $this->assertSame($answer, $parsed['answer'] ?? null);
    }

    public function test_code_of_falls_back_to_id(): void
    {
        $faq = Faq::create(['question' => 'q', 'answer' => 'a']);
        $this->assertSame('FAQ-'.$faq->id, LlmResponder::codeOf($faq));
    }

    public function test_unavailable_when_disabled_or_limit_reached(): void
    {
        $conversation = $this->startChat();

        config(['llm.api_key' => null]);
        $this->assertFalse($this->responder()->isAvailable());
        $this->assertNull($this->responder()->answer($conversation, 'tanya'));

        config(['llm.api_key' => 'sk', 'llm.daily_request_limit' => 2]);
        LlmLog::create(['question' => 'a']);
        $this->assertTrue($this->responder()->isAvailable());
        LlmLog::create(['question' => 'b']);
        $this->assertFalse($this->responder()->isAvailable());

        // log kemarin tidak dihitung
        LlmLog::query()->update(['created_at' => Carbon::yesterday()]);
        $this->assertTrue($this->responder()->isAvailable());

        config(['llm.daily_request_limit' => 0]);
        LlmLog::query()->update(['created_at' => now()]);
        $this->assertTrue($this->responder()->isAvailable(), '0 = tanpa batas');
    }

    public function test_context_scope_verified_vs_all(): void
    {
        $this->faq('PSP-001');
        $this->faq('PSP-002', published: false);
        Faq::create(['code' => 'DRF-001', 'question' => 'q', 'answer' => 'a', 'verification_status' => 'draft']);
        Faq::create(['code' => 'VER-001', 'question' => 'q', 'answer' => 'a', 'verification_status' => 'terverifikasi', 'is_active' => false]);

        $this->assertSame(['PSP-001'], $this->responder()->contextFaqs()->pluck('code')->all());

        config(['llm.faq_scope' => 'all']);
        $this->assertSame(['PSP-001', 'PSP-002', 'VER-001'], $this->responder()->contextFaqs()->pluck('code')->all());
    }

    public function test_confident_answer_keeps_only_valid_codes(): void
    {
        $a = $this->faq('PSP-001');
        $b = $this->faq('PSP-002');
        $this->fakeApis($this->aiJson('Jawaban', ['psp-002', 'NOPE', 'PSP-001', 'PSP-002']));

        $answer = $this->responder()->answer($this->startChat(), 'tanya');

        $this->assertTrue($answer->confident);
        $this->assertSame(['PSP-002', 'PSP-001'], $answer->faqCodes);
        $this->assertSame([$b->id, $a->id], $answer->faqIds);
        $this->assertSame($b->id, $answer->primaryFaqId());
        $this->assertFalse($answer->usedUnverified);
    }

    public static function unconfidentReplies(): array
    {
        return [
            'confident false' => ['{"answer":"x","faq_codes":["PSP-001"],"confident":false}'],
            'tanpa kode' => ['{"answer":"x","faq_codes":[],"confident":true}'],
            'jawaban kosong' => ['{"answer":"  ","faq_codes":["PSP-001"],"confident":true}'],
            'confident string' => ['{"answer":"x","faq_codes":["PSP-001"],"confident":"true"}'],
            'bukan json' => ['saya tidak yakin'],
        ];
    }

    #[DataProvider('unconfidentReplies')]
    public function test_unconfident_replies(string $content): void
    {
        $this->faq('PSP-001');
        $this->fakeApis($content);

        $answer = $this->responder()->answer($this->startChat(), 'tanya');

        $this->assertNotNull($answer);
        $this->assertFalse($answer->confident);
        $this->assertSame('', $answer->text);
        $this->assertFalse(LlmLog::sole()->confident);
    }

    public function test_invalid_json_is_logged_as_error(): void
    {
        $this->faq('PSP-001');
        $this->fakeApis('bukan json');

        $this->responder()->answer($this->startChat(), 'tanya');

        $this->assertSame('Format jawaban AI tidak valid.', LlmLog::sole()->error);
    }

    public function test_api_failure_returns_null_and_logs(): void
    {
        $this->faq('PSP-001');
        $this->fakeApis(null, 503);

        $this->assertNull($this->responder()->answer($this->startChat(), 'tanya'));
        $log = LlmLog::sole();
        $this->assertStringContainsString('503', $log->error);
        $this->assertNotNull($log->latency_ms);
    }

    public function test_long_answers_are_truncated(): void
    {
        $this->faq('PSP-001');
        $this->fakeApis($this->aiJson(str_repeat('a', 3000), ['PSP-001']));

        $answer = $this->responder()->answer($this->startChat(), 'tanya');

        $this->assertLessThanOrEqual(1503, mb_strlen($answer->text));
    }

    public function test_history_limit_zero_sends_no_history(): void
    {
        config(['llm.history_messages' => 0]);
        $this->faq('PSP-001');
        $this->fakeApis($this->aiJson('ok', ['PSP-001']));
        $conversation = $this->startChat();
        $conversation->messages()->create(['sender_type' => 'user', 'message_text' => 'lama', 'sent_at' => now()]);

        $this->responder()->answer($conversation, 'baru');

        Http::assertSent(fn ($r) => count($r['messages']) === 2);
    }
}
