<?php

namespace App\Console\Commands;

use App\Services\BalesOtomatis\BalesOtomatisClient;
use App\Services\BalesOtomatis\BalesOtomatisException;
use Illuminate\Console\Command;

class BalesOtomatisInfo extends Command
{
    protected $signature = 'kancil:balesotomatis-info';

    protected $description = 'Tampilkan info perangkat BalesOtomatis (untuk mengisi BALESOTOMATIS_NUMBER_ID) dan URL webhook';

    public function handle(BalesOtomatisClient $client): int
    {
        $token = (string) config('balesotomatis.webhook_token');
        $this->line('URL webhook: '.($token !== ''
            ? rtrim((string) config('app.url'), '/').'/webhook/balesotomatis/'.$token
            : '(BALESOTOMATIS_WEBHOOK_TOKEN belum diisi)'));
        $this->line('Mesin bot (BOT_ENGINE): '.config('kancil.bot_engine'));

        try {
            $info = $client->info();
        } catch (BalesOtomatisException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($info, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
