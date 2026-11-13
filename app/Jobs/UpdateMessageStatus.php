<?php

namespace App\Jobs;

use App\Models\Message;
use App\Models\WebhookEvent;
use App\Services\BalesOtomatis\WebhookData;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Webhook message_status (WABA): perbarui status terkirim/dibaca. Status tidak pernah mundur.
 */
class UpdateMessageStatus implements ShouldQueue
{
    use Queueable;

    private const RANK = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

    public function __construct(public readonly int $eventId) {}

    public function handle(): void
    {
        $event = WebhookEvent::find($this->eventId);

        if (! $event || $event->processed_at) {
            return;
        }

        $data = new WebhookData($event->data());
        $status = $data->deliveryStatus();
        $message = $data->messageId() ? Message::where('external_message_id', $data->messageId())->first() : null;

        if ($message && $status && self::RANK[$status] > (self::RANK[$message->delivery_status] ?? -1)) {
            $message->update(['delivery_status' => $status]);
        }

        $event->update([
            'processed_at' => now(),
            'error' => $message ? null : 'Pesan dengan message_id tersebut tidak ditemukan.',
        ]);
    }
}
