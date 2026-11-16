<?php

namespace App\Jobs;

use App\Models\Message;
use App\Services\BalesOtomatis\BalesOtomatisClient;
use App\Services\BalesOtomatis\BalesOtomatisException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Jobs\SyncJob;
use Throwable;

/**
 * Kirim satu pesan keluar (bot/AI/petugas) lewat BalesOtomatis dan perbarui delivery_status.
 */
class SendWhatsappMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public function __construct(public readonly int $messageId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function handle(BalesOtomatisClient $client): void
    {
        $message = Message::with('conversation')->find($this->messageId);

        if (! $message || in_array($message->delivery_status, ['sent', 'delivered', 'read'], true)) {
            return;
        }

        $phone = $message->conversation?->recipientPhone();

        if (! $phone) {
            $this->fail($message, 'Nomor WhatsApp kontak tidak diketahui (kontak LID tanpa nomor).');

            return;
        }

        if (! $client->isConfigured()) {
            $this->fail($message, 'BalesOtomatis belum dikonfigurasi (API key / number_id kosong).');

            return;
        }

        if (! $client->isWaba() && ! app()->runningUnitTests()) {
            $delayMs = (int) config('balesotomatis.reply_delay_ms', 0);
            if ($delayMs > 0) {
                usleep(($delayMs + random_int(0, 600)) * 1000);
            }
        }

        try {
            $externalId = $client->sendText($phone, (string) $message->message_text, $message->client_uuid);
        } catch (BalesOtomatisException $e) {
            $sync = $this->job === null || $this->job instanceof SyncJob;

            if ($e->retryable && $this->attempts() < $this->tries) {
                $message->update(['delivery_error' => $e->getMessage()]);
                $delay = $this->backoff()[$this->attempts() - 1] ?? 90;

                if (! $sync) {
                    $this->release($delay);
                } elseif (config('queue.default') !== 'sync') {
                    // Dikirim langsung (tanpa worker): ulangi lewat antrean agar tidak hilang.
                    static::dispatch($this->messageId)->delay($delay);
                }

                return;
            }

            $this->fail($message, $e->getMessage());

            return;
        }

        $message->update([
            'delivery_status' => 'sent',
            'delivery_error' => null,
            'external_message_id' => $externalId && ! Message::where('external_message_id', $externalId)->exists()
                ? $externalId
                : $message->external_message_id,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        Message::whereKey($this->messageId)->update([
            'delivery_status' => 'failed',
            'delivery_error' => mb_substr((string) $exception?->getMessage(), 0, 1000),
        ]);
    }

    private function fail(Message $message, string $reason): void
    {
        $message->update(['delivery_status' => 'failed', 'delivery_error' => $reason]);
    }
}
