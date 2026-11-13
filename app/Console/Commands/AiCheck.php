<?php

namespace App\Console\Commands;

use App\Services\Llm\LlmClient;
use App\Services\Llm\LlmException;
use App\Services\Llm\LlmResponder;
use Illuminate\Console\Command;

class AiCheck extends Command
{
    protected $signature = 'kancil:ai-cek {--saldo : Hanya tampilkan saldo akun AI}';

    protected $description = 'Cek koneksi AI (SumoPod), model, jumlah FAQ yang boleh dipakai, dan saldo';

    public function handle(LlmClient $client, LlmResponder $responder): int
    {
        $this->line('Model: '.$client->model().' | base URL: '.config('llm.base_url').' | cakupan FAQ: '.config('llm.faq_scope'));

        if (! config('llm.api_key')) {
            $this->error('LLM_API_KEY belum diisi di .env.');

            return self::FAILURE;
        }

        try {
            $balance = $client->balance();
            $this->line(sprintf('Saldo: %s (terpakai %s dari %s)',
                $balance['balance'] === null ? '-' : '$'.number_format($balance['balance'], 4),
                '$'.number_format($balance['spend'], 4),
                $balance['max_budget'] === null ? '-' : '$'.number_format($balance['max_budget'], 4)));
        } catch (LlmException $e) {
            $this->warn('Saldo tidak terbaca: '.$e->getMessage());
        }

        if ($this->option('saldo')) {
            return self::SUCCESS;
        }

        $this->line('FAQ yang boleh dipakai AI: '.$responder->contextFaqs()->count());

        try {
            $response = $client->chat([
                ['role' => 'system', 'content' => 'Balas JSON {"ok": true}.'],
                ['role' => 'user', 'content' => 'tes'],
            ]);
        } catch (LlmException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('AI merespons: '.mb_substr($response->content, 0, 200));

        return self::SUCCESS;
    }
}
