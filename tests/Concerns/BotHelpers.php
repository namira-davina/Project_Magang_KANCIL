<?php

namespace Tests\Concerns;

use App\Models\Conversation;
use App\Models\Faq;
use App\Models\Message;
use App\Services\Bot\BotEngine;
use App\Services\Bot\BotReply;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

trait BotHelpers
{
    protected const WEBHOOK_TOKEN = 'token-rahasia-webhook-kancil-sapa-0123456789';

    protected const NUMBER_ID = 'NUM-CILACAP-1';

    protected ?Conversation $chat = null;

    /** Rabu 11 Nov 2026 10:00 WIB: di dalam jam layanan. */
    protected function atOfficeHours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00:00', 'Asia/Jakarta'));
    }

    protected function configureIntegration(string $engine = 'laravel', bool $llm = false): void
    {
        config([
            'balesotomatis.api_key' => 'kode-akses-uji',
            'balesotomatis.number_id' => self::NUMBER_ID,
            'balesotomatis.licenses_key' => null,
            'balesotomatis.channel' => 'nonresmi',
            'balesotomatis.webhook_token' => self::WEBHOOK_TOKEN,
            'balesotomatis.base_url' => 'https://api.balesotomatis.co.id/public/v1',
            'kancil.bot_engine' => $engine,
            'llm.enabled' => $llm,
            'llm.api_key' => $llm ? 'sk-uji' : null,
            'llm.base_url' => 'https://ai.sumopod.com/v1',
            'llm.model' => 'gpt-5-nano',
            'llm.faq_scope' => 'verified',
            'llm.daily_request_limit' => 500,
        ]);
    }

    /** Fake API BalesOtomatis + SumoPod. $aiContent = isi jawaban AI (string JSON) atau null. */
    protected function fakeApis(?string $aiContent = null, int $aiStatus = 200): void
    {
        $counter = 0;

        Http::fake([
            'api.balesotomatis.co.id/*' => function () use (&$counter) {
                $counter++;

                return Http::response(['code' => 200, 'message' => 'success', 'type' => 'send', 'data' => ['message_id' => 'OUT-'.$counter]]);
            },
            'ai.sumopod.com/*' => fn () => Http::response(
                $aiStatus === 200
                    ? ['model' => 'gpt-5-nano', 'choices' => [['message' => ['content' => $aiContent ?? '{}']]], 'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50]]
                    : ['error' => ['message' => 'gagal']],
                $aiStatus,
            ),
        ]);
    }

    /** Ganti semua stub Http::fake sebelumnya. */
    protected function refakeHttp(array|callable $stubs): void
    {
        Http::swap(new Factory(app('events')));
        Http::fake($stubs);
    }

    protected function aiJson(string $answer, array $codes, bool $confident = true): string
    {
        return json_encode(['answer' => $answer, 'faq_codes' => $codes, 'confident' => $confident]);
    }

    protected function seedFaqs(bool $publish = true): void
    {
        $this->seed(DatabaseSeeder::class);

        if ($publish) {
            Faq::query()->update(['verification_status' => 'terverifikasi', 'is_active' => true]);
        }
    }

    protected function holdFaq(string ...$codes): void
    {
        Faq::whereIn('code', $codes)->update(['verification_status' => 'perlu_verifikasi', 'is_active' => false]);
    }

    protected function startChat(array $attributes = []): Conversation
    {
        return $this->chat = Conversation::create(array_merge([
            'wa_chat_id' => '6281234567890@s.whatsapp.net',
            'whatsapp_number' => '6281234567890',
            'user_name' => 'Budi',
            'channel' => 'whatsapp',
            'conversation_mode' => Conversation::MODE_BOT,
            'started_at' => now(),
        ], $attributes));
    }

    /**
     * Kirim pesan ke BotEngine seperti ProcessIncomingChat.
     *
     * @return list<BotReply>
     */
    protected function say(string $text, string $type = 'text', array $messageOverrides = []): array
    {
        $this->chat ??= $this->startChat();
        $this->chat->refresh();
        $previous = $this->chat->last_inbound_at;

        $message = $this->chat->messages()->create(array_merge([
            'sender_type' => 'user',
            'message_type' => $type,
            'message_text' => $type === 'text' ? $text : ($text === '' ? null : $text),
            'sent_at' => now(),
        ], $messageOverrides));

        $this->chat->update(['last_inbound_at' => now(), 'last_message_at' => now()]);

        return app(BotEngine::class)->handle($this->chat, $message, $previous);
    }

    /** Kirim pesan dan kembalikan satu-satunya balasan. */
    protected function reply(string $text, string $type = 'text', array $overrides = []): BotReply
    {
        $replies = $this->say($text, $type, $overrides);
        $this->assertCount(1, $replies, 'Bot harus membalas tepat satu pesan.');

        return $replies[0];
    }

    /** @param  list<string>  $inputs */
    protected function conversation(array $inputs): BotReply
    {
        $last = null;

        foreach ($inputs as $input) {
            $last = $this->reply($input);
        }

        return $last;
    }

    protected function incomingPayload(array $data = [], string $type = 'incoming_chat'): array
    {
        return [
            'type' => $type,
            'spec' => 'bo.omni.event.v1',
            'data' => array_merge([
                'number_key' => self::NUMBER_ID,
                'chat_id' => '6281234567890@s.whatsapp.net',
                'message_id' => 'IN-'.uniqid(),
                'name' => 'Budi Santoso',
                'timestamp' => now()->timestamp,
                'message_body' => 'Halo',
                'has_media' => false,
                'is_from_me' => $type === 'outgoing_chat',
                'is_group' => false,
                'phone_number' => '6281234567890',
            ], $data),
        ];
    }

    protected function postWebhook(array $payload, ?string $token = null, array $headers = [])
    {
        return $this->postJson('/webhook/balesotomatis/'.($token ?? self::WEBHOOK_TOKEN), $payload, $headers);
    }

    /** Pesan yang dikirim ke API BalesOtomatis. */
    protected function sentWhatsappTexts(): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_contains($r->url(), 'api.balesotomatis.co.id'))
            ->map(fn (Request $r) => $r->data())
            ->values()
            ->all();
    }

    protected function lastBotMessage(): ?Message
    {
        return Message::whereIn('sender_type', ['bot', 'ai'])->latest('id')->first();
    }
}
