<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\BalesOtomatis\WebhookData;
use App\Services\Bot\BotEngine;
use App\Services\Bot\EscalationRecorder;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Webhook incoming_chat: simpan pesan pemohon, lalu
 *  - BOT_ENGINE=laravel       : jalankan BotEngine (kecuali petugas sedang menangani)
 *  - BOT_ENGINE=balesotomatis : AI BalesOtomatis yang menjawab; di sini hanya deteksi permintaan petugas.
 */
class ProcessIncomingChat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** Kata yang menandakan pemohon minta dilayani petugas (Opsi A). */
    private const HUMAN_KEYWORDS = ['88', 'petugas', 'cs', 'admin', 'operator', 'manusia', 'komplain', 'pengaduan', 'aduan'];

    public function __construct(public readonly int $eventId) {}

    public function handle(WhatsappMessenger $messenger, BotEngine $engine, EscalationRecorder $escalations): void
    {
        $event = WebhookEvent::find($this->eventId);

        if (! $event || $event->processed_at) {
            return;
        }

        $data = new WebhookData($event->data());

        try {
            if ($data->isGroup() || $data->isFromMe()) {
                $event->update(['processed_at' => now()]);

                return;
            }

            if ($data->messageId() && Message::where('external_message_id', $data->messageId())->exists()) {
                $event->update(['processed_at' => now()]);

                return;
            }

            [$conversation, $message, $previousInbound] = DB::transaction(function () use ($data, $messenger, $event) {
                $conversation = $messenger->resolveConversation($data, $event->number_key);
                $previousInbound = $conversation->last_inbound_at;

                $message = $conversation->messages()->create([
                    'sender_type' => 'user',
                    'message_type' => $data->messageType(),
                    'message_text' => $data->body(),
                    'is_read' => false,
                    'sent_at' => $data->sentAt(),
                    'external_message_id' => $data->messageId(),
                    'meta' => array_filter([
                        'media_mime' => $data->mediaMime(),
                        'media_name' => $data->mediaName(),
                        'media_ref' => $data->hasMedia() && config('balesotomatis.channel') === 'waba' ? $data->mediaBody() : null,
                    ]) ?: null,
                ]);

                if ($data->hasMedia() && config('balesotomatis.channel') !== 'waba') {
                    $message->update(['attachment_path' => $messenger->storeMedia($conversation, $data)]);
                }

                $conversation->fill([
                    'user_name' => $conversation->user_name ?: $data->name(),
                    'last_message_at' => now(),
                    'last_inbound_at' => now(),
                ]);

                if ($conversation->conversation_mode === Conversation::MODE_CLOSED) {
                    $conversation->conversation_mode = Conversation::MODE_BOT;
                    $conversation->closed_at = null;
                }

                $conversation->save();

                return [$conversation, $message, $previousInbound];
            });

            if (config('kancil.bot_engine') === 'laravel') {
                $this->runBot($conversation, $message, $previousInbound, $engine, $messenger);
            } else {
                $this->detectHumanRequest($conversation, $message, $escalations);
            }

            $event->update(['processed_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $event->update(['error' => mb_substr($e->getMessage(), 0, 2000)]);

            throw $e;
        }
    }

    private function runBot(Conversation $conversation, Message $message, $previousInbound, BotEngine $engine, WhatsappMessenger $messenger): void
    {
        // Petugas menangani atau pemohon masih di antrean: bot diam, pesan cukup tercatat di dashboard.
        if ($conversation->isHumanHandling() || $conversation->isWaitingForOfficer()) {
            return;
        }

        if (in_array($conversation->conversation_mode, [Conversation::MODE_HUMAN, Conversation::MODE_WAITING], true)) {
            // Masa ambil alih / antrean habis: bot aktif lagi.
            $conversation->update(['conversation_mode' => Conversation::MODE_BOT, 'bot_paused_until' => null]);
        }

        $limit = (int) config('kancil.rate_limit_per_minute', 10);

        if ($limit > 0) {
            $key = 'bot-reply:'.$conversation->id;

            if (RateLimiter::tooManyAttempts($key, $limit)) {
                return;
            }

            RateLimiter::hit($key, 60);
        }

        foreach ($engine->handle($conversation, $message, $previousInbound) as $reply) {
            $messenger->queue($conversation, $reply->text, $reply->sender, null, $reply->faqId, ['codes' => $reply->codes]);
        }
    }

    private function detectHumanRequest(Conversation $conversation, Message $message, EscalationRecorder $escalations): void
    {
        if ($conversation->conversation_mode !== Conversation::MODE_BOT || $message->message_type !== 'text') {
            return;
        }

        $text = BotEngine::normalize((string) $message->message_text);
        $words = preg_split('/\s+/', $text) ?: [];

        if ($text === '88' || array_intersect($words, self::HUMAN_KEYWORDS) !== []) {
            $escalations->record($conversation, 'A2', 'Permintaan petugas: '.mb_substr((string) $message->message_text, 0, 200));
            $conversation->update(['conversation_mode' => Conversation::MODE_WAITING]);
        }
    }
}
