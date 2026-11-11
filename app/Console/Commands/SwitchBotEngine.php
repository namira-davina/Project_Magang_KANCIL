<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Pindah otak bot: AI SumoPod di Laravel (laravel) <-> AI Powered BalesOtomatis (balesotomatis).
 * Hanya mengubah BOT_ENGINE di .env; AI Powered di member area BalesOtomatis tetap diubah manual.
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
            $this->line('Mode sekarang: '.(config('kancil.bot_engine') === 'laravel' ? 'sumopod (BOT_ENGINE=laravel)' : 'balesotomatis'));

            return self::SUCCESS;
        }

        $engine = self::MODES[strtolower($mode)] ?? null;

        if ($engine === null) {
            $this->error('Mode tidak dikenal. Pakai: sumopod | balesotomatis');

            return self::FAILURE;
        }

        $path = app()->environmentFilePath();
        $env = file_exists($path) ? file_get_contents($path) : '';
        $env = preg_match('/^BOT_ENGINE=.*$/m', $env)
            ? preg_replace('/^BOT_ENGINE=.*$/m', 'BOT_ENGINE='.$engine, $env)
            : rtrim($env)."\nBOT_ENGINE={$engine}\n";
        file_put_contents($path, $env);

        config(['kancil.bot_engine' => $engine]);
        Artisan::call('config:clear');

        $this->info("BOT_ENGINE={$engine} tersimpan.");

        if ($engine === 'laravel') {
            $this->warn('Langkah manual: MATIKAN "AI Powered" di member area BalesOtomatis agar tidak balas dobel.');
        } else {
            $this->warn('Langkah manual: NYALAKAN lagi "AI Powered" di member area BalesOtomatis.');
        }

        $this->warn('Restart queue worker (composer run dev / queue:listen) agar mode baru dipakai.');

        return self::SUCCESS;
    }
}
