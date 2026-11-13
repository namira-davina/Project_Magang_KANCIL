<?php

namespace App\Services\Whatsapp;

use App\Jobs\SendWhatsappMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\BalesOtomatis\WebhookData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Menyimpan pesan keluar lalu mengantrekan pengiriman ke WhatsApp,
 * serta mencari/membuat percakapan dari data webhook.
 */
class WhatsappMessenger
{
    public const MEDIA_DISK = 'local';

    public const MAX_MEDIA_BYTES = 10 * 1024 * 1024;

    public function queue(
        Conversation $conversation,
        string $text,
        string $senderType = 'bot',
        ?int $userId = null,
        ?int $faqId = null,
        array $meta = [],
    ): Message {
        $isWhatsapp = $conversation->channel === 'whatsapp';

        $message = $conversation->messages()->create([
            'sender_type' => $senderType,
            'sender_user_id' => $userId,
            'message_type' => 'text',
            'message_text' => $text,
            'faq_id' => $faqId,
            'is_read' => true,
            'sent_at' => now(),
            'client_uuid' => (string) Str::uuid(),
            'delivery_status' => $isWhatsapp ? 'queued' : null,
            'meta' => $meta ?: null,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        if ($isWhatsapp) {
            SendWhatsappMessage::dispatch($message->id);
        }

        return $message;
    }

    public function resolveConversation(WebhookData $data, ?string $deviceKey = null): Conversation
    {
        $chatId = $data->chatId();
        $phone = $data->phone();

        $conversation = $chatId ? Conversation::where('wa_chat_id', $chatId)->first() : null;

        // Satu kontak bisa datang dengan chat_id berbeda (pesan masuk @lid, echo keluar @s.whatsapp.net).
        // Cocokkan lewat nomor agar tidak terbentuk percakapan ganda.
        if (! $conversation && $phone) {
            $conversation = Conversation::where('whatsapp_number', $phone)->oldest('id')->first();
        }

        $conversation ??= new Conversation([
            'channel' => 'whatsapp',
            'conversation_mode' => Conversation::MODE_BOT,
            'started_at' => now(),
        ]);

        $conversation->fill(array_filter([
            'wa_chat_id' => $conversation->wa_chat_id ?: $chatId,
            'whatsapp_number' => $conversation->whatsapp_number ?: $phone,
            'device_key' => $deviceKey ?: $conversation->device_key,
            'channel' => 'whatsapp',
        ], fn ($v) => $v !== null));

        $conversation->save();

        return $conversation;
    }

    /** Simpan media base64 (WA non-resmi) ke disk privat. Mengembalikan path atau null. */
    public function storeMedia(Conversation $conversation, WebhookData $data): ?string
    {
        $body = $data->mediaBody();

        if (! $body || strlen($body) > (int) ceil(self::MAX_MEDIA_BYTES * 4 / 3) + 4) {
            return null;
        }

        if (str_contains($body, ',') && str_starts_with($body, 'data:')) {
            $body = substr($body, strpos($body, ',') + 1);
        }

        $binary = base64_decode($body, true);

        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_MEDIA_BYTES) {
            return null;
        }

        $extension = self::extensionFor($data->mediaMime(), $data->mediaName());
        $path = "wa-media/{$conversation->id}/".Str::uuid().($extension ? ".{$extension}" : '');

        Storage::disk(self::MEDIA_DISK)->put($path, $binary);

        return $path;
    }

    public static function extensionFor(?string $mime, ?string $name): ?string
    {
        $fromName = $name ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) : '';

        if ($fromName !== '' && preg_match('/^[a-z0-9]{1,8}$/', $fromName)) {
            return $fromName;
        }

        return match (strtolower(explode(';', (string) $mime)[0])) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'video/mp4' => 'mp4',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            default => null,
        };
    }
}
