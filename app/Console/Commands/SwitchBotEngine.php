<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Pindah otak bot: bot Laravel + AI SumoPod (sumopod) <-> AI Powered BalesOtomatis (balesotomatis).
 * Mengubah BOT_ENGINE (dan LLM_ENABLED untuk sumopod) di .env; AI Powered di member area BalesOtomatis tetap diubah manual.
 */
class SwitchBotEngine extends Command
{
    protected $signature = 'kancil:mode {mode? : sumopod | balesotomatis}';

    protected $description = 'Lihat atau pindah mesin bot (sumopod = bot Laravel + AI SumoPod, balesotomatis = AI Powered BalesOtomatis)';

    private const MODES = ['sumopod' => 'laravel', 'laravel' => 'laravel', 'balesotomatis' => 'balesotomatis'];

    public function handle(): int
    {
        $mode = $this->argument('mode');

        if ($mode === null) {
            $laravel = config('kancil.bot_engine') === 'laravel';
            $this->line('Mode sekarang: '.($laravel ? 'sumopod (BOT_ENGINE=laravel)' : 'balesotomatis'));

            if ($laravel) {
                $this->line('AI SumoPod: '.(config('llm.enabled') && config('llm.api_key') ? 'aktif' : 'NONAKTIF (bot hanya menu)'));
            }

            return self::SUCCESS;
        }

        $engine = self::MODES[strtolower($mode)] ?? null;

        if ($engine === null) {
            $this->error('Mode tidak dikenal. Pakai: sumopod | balesotomatis');

            return self::FAILURE;
        }

        // Mode sumopod = bot Laravel + AI SumoPod: AI ikut dinyalakan (dulu harus diubah manual dan mudah terlupa).
        $values = $engine === 'laravel' ? ['BOT_ENGINE' => $engine, 'LLM_ENABLED' => 'true'] : ['BOT_ENGINE' => $engine];
        $this->writeEnv($values);

        config(['kancil.bot_engine' => $engine]);
        Artisan::call('config:clear');

        $this->info("BOT_ENGINE={$engine} tersimpan.");

        if ($engine === 'laravel') {
            config(['llm.enabled' => true]);
            $this->info('LLM_ENABLED=true tersimpan (AI SumoPod aktif).');

            if (! config('llm.api_key')) {
                $this->warn('LLM_API_KEY kosong: isi kunci SumoPod di .env, lalu cek dengan php artisan kancil:ai-cek.');
            }

            $this->warn('Langkah manual: MATIKAN "AI Powered" di member area BalesOtomatis agar tidak balas dobel.');
        } else {
            $this->warn('Langkah manual: NYALAKAN lagi "AI Powered" di member area BalesOtomatis.');
        }

        $this->warn('Restart queue worker (composer run dev / queue:listen) agar mode baru dipakai.');

        return self::SUCCESS;
    }

    /** @param  array<string, string>  $values */
    private function writeEnv(array $values): void
    {
        $path = app()->environmentFilePath();
        $env = file_exists($path) ? file_get_contents($path) : '';

        foreach ($values as $key => $value) {
            $env = preg_match("/^{$key}=.*$/m", $env)
                ? preg_replace("/^{$key}=.*$/m", "{$key}={$value}", $env)
                : rtrim($env)."\n{$key}={$value}\n";
        }

        file_put_contents($path, $env);
    }
}
