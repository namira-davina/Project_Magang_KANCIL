<?php

namespace App\Services\BalesOtomatis;

use Illuminate\Support\Carbon;

/**
 * Pembaca field `data` webhook BalesOtomatis (spec bo.omni.event.v1).
 * Non-resmi: chat_id JID (…@s.whatsapp.net / …@lid / …@g.us), timestamp int, media_body base64.
 * WABA: chat_id = nomor, timestamp string, media_body = media ID Meta.
 */
final class WebhookData
{
    public function __construct(public readonly array $data) {}

    public function chatId(): ?string
    {
        $chatId = $this->string('chat_id') ?? $this->string('wa_id') ?? $this->phone();

        return $chatId ? mb_substr($chatId, 0, 100) : null;
    }

    /** Nomor 628xxx tanpa +, null bila kontak LID tanpa nomor. */
    public function phone(): ?string
    {
        foreach (['phone_number', 'sender_phone'] as $key) {
            $digits = preg_replace('/\D/', '', (string) ($this->data[$key] ?? ''));
            if ($digits !== '') {
                return self::normalizePhone($digits);
            }
        }

        $chatId = (string) ($this->data['chat_id'] ?? '');

        if (str_ends_with($chatId, '@s.whatsapp.net') || preg_match('/^\+?\d{8,15}$/', $chatId)) {
            return self::normalizePhone(preg_replace('/\D/', '', strstr($chatId, '@', true) ?: $chatId));
        }

        return null;
    }

    public static function normalizePhone(string $digits): string
    {
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return mb_substr($digits, 0, 25);
    }

    public function messageId(): ?string
    {
        $id = $this->string('message_id');

        return $id ? mb_substr($id, 0, 150) : null;
    }

    public function name(): ?string
    {
        $name = $this->string('name');

        return $name ? mb_substr($name, 0, 100) : null;
    }

    public function body(): ?string
    {
        return $this->string('message_body');
    }

    public function numberKey(): ?string
    {
        return $this->string('number_key');
    }

    public function isGroup(): bool
    {
        return filter_var($this->data['is_group'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || str_ends_with((string) ($this->data['chat_id'] ?? ''), '@g.us');
    }

    public function isFromMe(): bool
    {
        return filter_var($this->data['is_from_me'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function hasMedia(): bool
    {
        return filter_var($this->data['has_media'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($this->data['location_attached'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    public function mediaMime(): ?string
    {
        return $this->string('media_mime');
    }

    public function mediaName(): ?string
    {
        return $this->string('media_name');
    }

    public function mediaBody(): ?string
    {
        return $this->string('media_body');
    }

    /** text | image | document | audio | other */
    public function messageType(): string
    {
        if (! $this->hasMedia()) {
            return 'text';
        }

        $mime = strtolower((string) $this->mediaMime());

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'audio/') => 'audio',
            str_starts_with($mime, 'application/'), str_starts_with($mime, 'text/') => 'document',
            default => 'other',
        };
    }

    public function sentAt(): Carbon
    {
        $ts = $this->data['timestamp'] ?? null;
        $tz = config('app.timezone');

        if (is_numeric($ts) && (int) $ts > 0) {
            $ts = (int) $ts;

            return Carbon::createFromTimestamp($ts > 9_999_999_999 ? intdiv($ts, 1000) : $ts, $tz);
        }

        if (is_string($ts) && $ts !== '') {
            try {
                return Carbon::parse($ts)->setTimezone($tz);
            } catch (\Throwable) {
                // pakai waktu terima
            }
        }

        return now();
    }

    /** Status pengiriman WABA → queued|sent|failed|delivered|read. */
    public function deliveryStatus(): ?string
    {
        $raw = $this->data['status'] ?? $this->data['message_status'] ?? $this->data['message_ack'] ?? $this->data['ack'] ?? null;

        if (is_numeric($raw)) {
            return match ((int) $raw) {
                -1 => 'failed', 0 => 'queued', 1 => 'sent', 2 => 'delivered', 3, 4 => 'read', default => null,
            };
        }

        return match (strtolower((string) $raw)) {
            'sent', 'server' => 'sent',
            'delivered', 'delivery_ack' => 'delivered',
            'read', 'played' => 'read',
            'failed', 'error' => 'failed',
            'pending', 'queued' => 'queued',
            default => null,
        };
    }

    private function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
