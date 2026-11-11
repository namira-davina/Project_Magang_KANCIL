<?php

namespace App\Services\Llm;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Klien chat completions yang kompatibel OpenAI (SumoPod: https://ai.sumopod.com/v1).
 */
class LlmClient
{
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('llm'));
    }

    public function isEnabled(): bool
    {
        return ! empty($this->config['enabled']) && ! empty($this->config['api_key']);
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? 'gpt-5-nano');
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     *
     * @throws LlmException
     */
    public function chat(array $messages, bool $json = true): LlmResponse
    {
        if (! $this->isEnabled()) {
            throw new LlmException('AI tidak aktif atau LLM_API_KEY belum diisi.');
        }

        $body = array_filter([
            'model' => $this->model(),
            'messages' => $messages,
            'max_completion_tokens' => (int) ($this->config['max_completion_tokens'] ?? 2000),
            'reasoning_effort' => $this->config['reasoning_effort'] ?: null,
            'response_format' => $json ? ['type' => 'json_object'] : null,
        ], fn ($v) => $v !== null);

        try {
            $response = Http::withToken((string) $this->config['api_key'])
                ->acceptJson()
                ->timeout((int) ($this->config['timeout'] ?? 40))
                ->post($this->config['base_url'].'/chat/completions', $body);
        } catch (ConnectionException $e) {
            throw new LlmException('Gagal terhubung ke layanan AI: '.$e->getMessage());
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'error.message') ?? $response->body();
            throw new LlmException("Layanan AI menolak permintaan ({$response->status()}): ".mb_substr((string) $message, 0, 300));
        }

        $content = data_get($response->json(), 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new LlmException('Layanan AI mengembalikan jawaban kosong.');
        }

        return new LlmResponse(
            $content,
            data_get($response->json(), 'model'),
            data_get($response->json(), 'usage.prompt_tokens'),
            data_get($response->json(), 'usage.completion_tokens'),
        );
    }

    /**
     * Saldo akun (GET /v2/user/info): balance = max_budget - spend.
     *
     * @return array{spend: float, max_budget: float|null, balance: float|null}
     *
     * @throws LlmException
     */
    public function balance(): array
    {
        if (empty($this->config['api_key'])) {
            throw new LlmException('LLM_API_KEY belum diisi.');
        }

        $root = preg_replace('#/v1$#', '', (string) $this->config['base_url']);

        try {
            $response = Http::withToken((string) $this->config['api_key'])
                ->acceptJson()
                ->timeout(15)
                ->get($root.'/v2/user/info');
        } catch (ConnectionException $e) {
            throw new LlmException('Gagal terhubung ke layanan AI: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new LlmException("Gagal membaca saldo ({$response->status()}).");
        }

        $spend = (float) data_get($response->json(), 'spend', 0);
        $max = data_get($response->json(), 'max_budget');

        return [
            'spend' => $spend,
            'max_budget' => $max === null ? null : (float) $max,
            'balance' => $max === null ? null : round((float) $max - $spend, 6),
        ];
    }
}
