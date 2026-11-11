<?php

namespace App\Services\BalesOtomatis;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Klien API BalesOtomatis. Semua endpoint POST + body JSON, kredensial di body.
 * Respons wajib dicek field `code` karena HTTP bisa 200 walau gagal.
 */
class BalesOtomatisClient
{
    /** Kode yang layak dicoba ulang: timeout dan batas kirim per menit. */
    private const RETRYABLE = [408, 429, 500, 502, 503, 504];

    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('balesotomatis'));
    }

    public function isWaba(): bool
    {
        return ($this->config['channel'] ?? 'nonresmi') === 'waba';
    }

    public function isConfigured(): bool
    {
        if (empty($this->config['api_key'])) {
            return false;
        }

        return $this->isWaba() ? ! empty($this->config['licenses_key']) : ! empty($this->config['number_id']);
    }

    /** Kunci perangkat yang sah untuk header BLS-OTO-NUMBERID / data.number_key. */
    public function deviceKeys(): array
    {
        return array_values(array_filter([
            $this->config['number_id'] ?? null,
            $this->config['licenses_key'] ?? null,
        ]));
    }

    /**
     * Kirim pesan teks. Mengembalikan message id dari BalesOtomatis bila ada.
     *
     * @throws BalesOtomatisException
     */
    public function sendText(string $phone, string $message, ?string $uuid = null, ?string $quotedMsg = null): ?string
    {
        if ($this->isWaba()) {
            $response = $this->post('send_meta_personal_message', [
                ...$this->wabaCredentials(),
                'phone_no' => $phone,
                'message' => $message,
            ]);

            return $this->extractMessageId($response);
        }

        $response = $this->post('send_personal_message', array_filter([
            ...$this->credentials(),
            'phone_no' => $phone,
            'message' => $message,
            'message_uuid' => $uuid,
            'quoted_msg' => $quotedMsg,
            'enable_typing' => ! empty($this->config['enable_typing']) ? 1 : null,
        ], fn ($v) => $v !== null));

        return $this->extractMessageId($response);
    }

    /**
     * Kirim berkas dari URL publik.
     *
     * @throws BalesOtomatisException
     */
    public function sendFile(string $phone, string $fileUrl, ?string $caption = null, ?string $uuid = null): ?string
    {
        $response = $this->post('send_personal_file', array_filter([
            ...$this->credentials(),
            'phone_no' => $phone,
            'file_url' => $fileUrl,
            'message' => $caption,
            'message_uuid' => $uuid,
        ], fn ($v) => $v !== null));

        return $this->extractMessageId($response);
    }

    /**
     * Info perangkat (number_id, status koneksi).
     *
     * @throws BalesOtomatisException
     */
    public function info(): array
    {
        return $this->post('info', ['api_key' => $this->config['api_key'] ?? '']);
    }

    /**
     * @throws BalesOtomatisException
     */
    private function post(string $endpoint, array $body): array
    {
        if (empty($this->config['api_key'])) {
            throw new BalesOtomatisException('BALESOTOMATIS_API_KEY belum diisi.');
        }

        try {
            $http = Http::acceptJson()
                ->asJson()
                ->timeout((int) ($this->config['timeout'] ?? 20))
                ->post($this->config['base_url'].'/'.$endpoint, $body);
        } catch (ConnectionException $e) {
            throw new BalesOtomatisException('Gagal terhubung ke BalesOtomatis: '.$e->getMessage(), null, true);
        }

        $json = $http->json();
        $json = is_array($json) ? $json : [];
        $code = isset($json['code']) ? (int) $json['code'] : $http->status();

        if ($http->successful() && $code >= 200 && $code < 300) {
            return $json;
        }

        $message = (string) ($json['message'] ?? $http->body() ?: 'Kesalahan tidak diketahui');
        $retryable = in_array($code, self::RETRYABLE, true)
            || ($code === 400 && str_contains(strtolower($message), 'limit'));

        throw new BalesOtomatisException("BalesOtomatis {$endpoint} gagal ({$code}): {$message}", $code, $retryable);
    }

    private function credentials(): array
    {
        return [
            'api_key' => $this->config['api_key'],
            'number_id' => $this->config['number_id'] ?? '',
        ];
    }

    private function wabaCredentials(): array
    {
        return [
            'secret_key' => $this->config['api_key'],
            'licenses_key' => $this->config['licenses_key'] ?? '',
        ];
    }

    private function extractMessageId(array $response): ?string
    {
        foreach (['data.message_id', 'data.id', 'message_id', 'data.messages.0.id', 'id'] as $path) {
            $value = data_get($response, $path);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
