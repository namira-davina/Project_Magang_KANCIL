<?php

namespace Tests\Unit;

use App\Services\BalesOtomatis\WebhookData;
use App\Services\Whatsapp\WhatsappMessenger;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebhookDataTest extends TestCase
{
    public static function phones(): array
    {
        return [
            'phone_number' => [['phone_number' => '6281234567890'], '6281234567890'],
            'phone_number bertanda' => [['phone_number' => '+62 812-3456-7890'], '6281234567890'],
            'awalan 0' => [['phone_number' => '081234567890'], '6281234567890'],
            'sender_phone' => [['phone_number' => null, 'sender_phone' => '6289'], '6289'],
            'dari JID' => [['chat_id' => '6285700001111@s.whatsapp.net'], '6285700001111'],
            'WABA nomor polos' => [['chat_id' => '6285700001111'], '6285700001111'],
            'LID tanpa nomor' => [['chat_id' => '12345@lid', 'phone_number' => null], null],
            'grup' => [['chat_id' => '1203@g.us'], null],
            'kosong' => [[], null],
        ];
    }

    #[DataProvider('phones')]
    public function test_phone(array $data, ?string $expected): void
    {
        $this->assertSame($expected, (new WebhookData($data))->phone());
    }

    public function test_chat_id_falls_back_to_wa_id_then_phone(): void
    {
        $this->assertSame('x@lid', (new WebhookData(['chat_id' => 'x@lid', 'wa_id' => 'w']))->chatId());
        $this->assertSame('w', (new WebhookData(['wa_id' => 'w']))->chatId());
        $this->assertSame('628', (new WebhookData(['phone_number' => '628']))->chatId());
        $this->assertNull((new WebhookData([]))->chatId());
    }

    public function test_group_and_from_me_flags(): void
    {
        $this->assertTrue((new WebhookData(['is_group' => true]))->isGroup());
        $this->assertTrue((new WebhookData(['is_group' => 'true']))->isGroup());
        $this->assertTrue((new WebhookData(['chat_id' => '1@g.us']))->isGroup());
        $this->assertFalse((new WebhookData(['is_group' => false, 'chat_id' => '1@s.whatsapp.net']))->isGroup());
        $this->assertTrue((new WebhookData(['is_from_me' => 1]))->isFromMe());
        $this->assertFalse((new WebhookData([]))->isFromMe());
    }

    public static function mediaTypes(): array
    {
        return [
            'teks' => [['has_media' => false], 'text'],
            'foto' => [['has_media' => true, 'media_mime' => 'image/jpeg'], 'image'],
            'stiker' => [['has_media' => true, 'media_mime' => 'image/webp'], 'image'],
            'suara' => [['has_media' => true, 'media_mime' => 'audio/ogg; codecs=opus'], 'audio'],
            'pdf' => [['has_media' => true, 'media_mime' => 'application/pdf'], 'document'],
            'video' => [['has_media' => true, 'media_mime' => 'video/mp4'], 'other'],
            'lokasi' => [['location_attached' => true], 'other'],
        ];
    }

    #[DataProvider('mediaTypes')]
    public function test_message_type(array $data, string $expected): void
    {
        $this->assertSame($expected, (new WebhookData($data))->messageType());
    }

    public function test_sent_at_parses_int_ms_string_and_invalid(): void
    {
        Carbon::setTestNow('2026-11-11 10:00:00');

        $this->assertSame(1762833600, (new WebhookData(['timestamp' => 1762833600]))->sentAt()->timestamp);
        $this->assertSame(1762833600, (new WebhookData(['timestamp' => 1762833600123]))->sentAt()->timestamp);
        $this->assertSame(1762833600, (new WebhookData(['timestamp' => '1762833600']))->sentAt()->timestamp);
        $this->assertSame('2026-11-10', (new WebhookData(['timestamp' => '2026-11-10T08:00:00Z']))->sentAt()->toDateString());
        $this->assertTrue((new WebhookData(['timestamp' => 'bukan tanggal']))->sentAt()->equalTo(now()));
        $this->assertTrue((new WebhookData([]))->sentAt()->equalTo(now()));

        Carbon::setTestNow();
    }

    public static function statuses(): array
    {
        return [
            ['sent', 'sent'], ['DELIVERED', 'delivered'], ['read', 'read'], ['played', 'read'], ['failed', 'failed'],
            [1, 'sent'], [2, 'delivered'], [3, 'read'], [-1, 'failed'], [0, 'queued'], ['aneh', null], [null, null],
        ];
    }

    #[DataProvider('statuses')]
    public function test_delivery_status(mixed $raw, ?string $expected): void
    {
        $this->assertSame($expected, (new WebhookData(['status' => $raw]))->deliveryStatus());
    }

    public function test_status_from_message_ack_field(): void
    {
        $this->assertSame('delivered', (new WebhookData(['message_ack' => 2]))->deliveryStatus());
    }

    public function test_strings_are_trimmed_to_column_sizes(): void
    {
        $data = new WebhookData([
            'message_id' => str_repeat('m', 200), 'name' => str_repeat('n', 150), 'chat_id' => str_repeat('c', 150),
            'message_body' => '   ', 'number_key' => 123,
        ]);

        $this->assertSame(150, strlen($data->messageId()));
        $this->assertSame(100, strlen($data->name()));
        $this->assertSame(100, strlen($data->chatId()));
        $this->assertNull($data->body());
        $this->assertSame('123', $data->numberKey());
    }

    public static function extensions(): array
    {
        return [
            ['image/jpeg', null, 'jpg'], ['image/png', null, 'png'], ['application/pdf', null, 'pdf'],
            ['audio/ogg; codecs=opus', null, 'ogg'], ['application/x-unknown', null, null],
            ['image/jpeg', 'Bukti.PNG', 'png'], ['image/jpeg', 'aneh.<script>', 'jpg'],
        ];
    }

    #[DataProvider('extensions')]
    public function test_media_extension(?string $mime, ?string $name, ?string $expected): void
    {
        $this->assertSame($expected, WhatsappMessenger::extensionFor($mime, $name));
    }
}
