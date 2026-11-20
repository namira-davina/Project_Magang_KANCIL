<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\BalesOtomatis\WebhookData;
use App\Services\Tickets\TicketQueueService;
use App\Services\Whatsapp\EchoText;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Webhook outgoing_chat (hanya WA non-resmi): pesan keluar dari nomor kantor,
 * termasuk echo pesan yang dikirim dashboard, balasan AI BalesOtomatis, dan balasan petugas dari HP.
 */
class ProcessOutgoingChat implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $eventId) {}

    public function handle(WhatsappMessenger $messenger, TicketQueueService $tickets): void
    {
        $event = WebhookEvent::find($this->eventId);

        if (! $event || $event->processed_at) {
            return;
        }

        $data = new WebhookData($event->data());

        try {
            if (! $data->isGroup()) {
                $this->record($data, $messenger, $tickets, $event->number_key);
            }

            $event->update(['processed_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $event->update(['error' => mb_substr($e->getMessage(), 0, 2000)]);

            throw $e;
        }
    }

    private function record(WebhookData $data, WhatsappMessenger $messenger, TicketQueueService $tickets, ?string $deviceKey): void
    {
        $externalId = $data->messageId();

        if ($externalId && Message::where('external_message_id', $externalId)->exists()) {
            return;
        }

        // Stub kosong dari perangkat kantor bukan balasan petugas: jangan dicatat dan jangan ambil alih bot.
        if ($data->isBlank()) {
            return;
        }

        $conversation = $messenger->resolveConversation($data, $deviceKey);

        if ($own = $this->findOwnMessage($conversation, $data)) {
            $own->update([
                'external_message_id' => $own->external_message_id ?: $externalId,
                'delivery_status' => in_array($own->delivery_status, ['delivered', 'read'], true) ? $own->delivery_status : 'sent',
                'delivery_error' => null,
            ]);

            return;
        }

        $engineIsLaravel = config('kancil.bot_engine') === 'laravel';

        $conversation->messages()->create([
            // Opsi A: pesan keluar yang bukan dari dashboard = jawaban AI BalesOtomatis (atau CS dari HP).
            // Opsi B: bot Laravel sudah tercatat, jadi sisanya balasan petugas dari HP/aplikasi.
            'sender_type' => $engineIsLaravel ? 'petugas' : 'ai',
            'message_type' => $data->messageType(),
            'message_text' => $data->body(),
            'is_read' => true,
            'sent_at' => $data->sentAt(),
            'external_message_id' => $externalId,
            'delivery_status' => 'sent',
            'meta' => array_filter(['source' => 'outgoing_chat', 'takeover' => $engineIsLaravel ?: null]),
        ]);

        $update = ['last_message_at' => now()];

        if ($engineIsLaravel) {
            // Jejak audit: bila echo bot gagal dicocokkan (kasus 20 Nov), kejadiannya langsung terlihat di log.
            Log::warning('Bot diambil alih oleh pesan keluar dari perangkat kantor.', [
                'conversation_id' => $conversation->id,
                'external_message_id' => $externalId,
                'text' => mb_substr((string) $data->body(), 0, 200),
            ]);

            // Sama seperti "hentikan AI jika CS membalas manual".
            $update['conversation_mode'] = Conversation::MODE_HUMAN;
            $update['bot_paused_until'] = now()->addMinutes((int) config('kancil.handoff_minutes', 120));
        }

        $conversation->update($update);

        if ($engineIsLaravel) {
            $tickets->markRespondedExternally($conversation);
        }
    }

    /** Cocokkan echo dengan pesan yang kita kirim: message_uuid, lalu teks yang sama dalam 10 menit terakhir. */
    private function findOwnMessage(Conversation $conversation, WebhookData $data): ?Message
    {
        $uuid = $data->data['message_uuid'] ?? $data->data['uuid'] ?? null;

        if (is_string($uuid) && $uuid !== '') {
            $match = Message::where('client_uuid', $uuid)->first();

            if ($match) {
                return $match;
            }
        }

        $body = $data->body();

        if ($body === null) {
            return null;
        }

        $candidates = $conversation->messages()
            ->whereIn('sender_type', ['bot', 'ai', 'petugas', 'system'])
            ->whereNotNull('client_uuid')
            ->where('sent_at', '>=', now()->subMinutes(10))
            ->orderByRaw('external_message_id IS NULL DESC')
            ->orderByDesc('id')
            ->get();

        // Utamakan kecocokan persis; bila BalesOtomatis/WA mengubah teks secara kosmetik (spasi ganda, garis pemisah,
        // emoji, kutip), cocokkan teks ternormalisasi agar echo bot tidak disangka balasan petugas dari HP.
        if ($exact = $candidates->firstWhere('message_text', $body)) {
            return $exact;
        }

        return $candidates->first(fn (Message $message) => EchoText::same($body, $message->message_text));
    }
}
